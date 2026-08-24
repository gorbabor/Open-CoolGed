<?php

namespace App\Http\Controllers;

use App\Mail\NotificationEmail;
use App\Models\AuditLog;
use App\Models\DocumentType;
use App\Models\Group;
use App\Models\MetadataDefinition;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Space;
use App\Models\User;
use App\Models\Workflow;
use App\Services\AiService;
use App\Services\AuditService;
use App\Services\DocumentService;
use App\Services\MailSettingsService;
use App\Services\PermissionService;
use App\Services\QuotaService;
use App\Services\StorageService;
use App\Services\TenantSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private PermissionService $permissions,
        private QuotaService $quota,
        private StorageService $storage,
    ) {}

    private function requireAdmin(string $permission): void
    {
        if (! $this->permissions->can(auth()->user(), $permission)) {
            abort(403, 'Permission administrateur requise.');
        }
    }

    private function tenantSettings(): TenantSettings
    {
        return TenantSettings::for(auth()->user()->tenant);
    }

    /*
     |--------------------------------------------------------------------------
     | Utilisateurs
     |--------------------------------------------------------------------------
     */

    public function users(Request $request)
    {
        $this->requireAdmin('admin.users');

        $users = User::where('tenant_id', auth()->user()->tenant_id)
            ->with(['roles', 'groups.roles'])
            ->orderBy('name')
            ->get();

        // CA-RBAC6: effective permissions per user (direct roles + group roles).
        $effective = [];
        foreach ($users as $u) {
            $roleIds = $this->permissions->effectiveRoleIds($u);
            $effective[$u->id] = Permission::whereHas('roles', fn ($q) => $q->whereIn('roles.id', $roleIds))
                ->orderBy('group')->orderBy('name')
                ->pluck('slug')
                ->all();
        }

        return view('admin.users', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
            'groups' => Group::orderBy('name')->get(),
            'quota' => $this->quota,
            'effectivePermissions' => $effective,
        ]);
    }

    public function storeUser(Request $request)
    {
        $this->requireAdmin('admin.users');

        $data = $request->validate([
            'name' => ['required', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role_id' => ['required', 'exists:roles,id'],
            'password' => ['nullable', 'min:'.(int) $this->tenantSettings()->get('password_min_length', 8)],
        ]);

        if (! $this->quota->canAddUser(auth()->user()->tenant)) {
            return back()->withErrors(['email' => 'Quota d\'utilisateurs atteint.']);
        }

        $user = User::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'] ?: Str::random(16),
            'status' => 'active',
        ]);

        $user->roles()->attach($data['role_id'], ['tenant_id' => $user->tenant_id]);

        if ($request->filled('group_ids')) {
            $user->groups()->attach($request->input('group_ids'), ['tenant_id' => $user->tenant_id]);
        }

        $this->audit->log('admin.user.created', 'user', $user->id);

        return back()->with('success', 'Utilisateur créé et invité par email.');
    }

    public function updateUser(Request $request, int $user)
    {
        $this->requireAdmin('admin.users');
        $user = $this->tenantUser($user);

        $data = $request->validate([
            'name' => ['required', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'role_id' => ['nullable', 'exists:roles,id'],
        ]);

        $user->update(['name' => $data['name'], 'email' => $data['email']]);

        if (! empty($data['role_id'])) {
            $user->roles()->sync([$data['role_id'] => ['tenant_id' => $user->tenant_id]]);
        }

        if ($request->has('group_ids')) {
            $pivots = collect($request->input('group_ids', []))
                ->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $user->tenant_id]])->all();
            $user->groups()->sync($pivots);
        }

        $this->audit->log('admin.user.updated', 'user', $user->id);

        return back()->with('success', 'Utilisateur mis à jour.');
    }

    public function resetUserPassword(Request $request, int $user)
    {
        $this->requireAdmin('admin.users');
        $user = $this->tenantUser($user);

        $data = $request->validate(['password' => ['required', 'min:'.(int) $this->tenantSettings()->get('password_min_length', 8)]]);

        $user->update(['password' => $data['password']]);
        $this->audit->log('admin.user.password_reset', 'user', $user->id);

        return back()->with('success', 'Mot de passe réinitialisé.');
    }

    public function toggleUser(int $user)
    {
        $this->requireAdmin('admin.users');
        $user = $this->tenantUser($user);

        $user->update(['status' => $user->isSuspended() ? 'active' : 'suspended']);
        $this->audit->log('admin.user.toggled', 'user', $user->id, ['status' => $user->status]);

        return back()->with('success', 'Statut utilisateur mis à jour.');
    }

    public function deleteUser(int $user)
    {
        $this->requireAdmin('admin.users');
        $user = $this->tenantUser($user);

        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'Vous ne pouvez pas supprimer votre propre compte.']);
        }

        // RM-010: logical delete keeps audit/history references intact.
        $user->delete();
        $this->audit->log('admin.user.deleted', 'user', $user->id);

        return back()->with('success', 'Utilisateur supprimé (logique).');
    }

    /*
     |--------------------------------------------------------------------------
     | Groupes
     |--------------------------------------------------------------------------
     */

    public function groups()
    {
        $this->requireAdmin('admin.groups');

        return view('admin.groups', [
            'groups' => Group::withCount('users')->with(['users', 'roles'])->orderBy('name')->get(),
            'users' => User::where('tenant_id', auth()->user()->tenant_id)->orderBy('name')->get(),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function storeGroup(Request $request)
    {
        $this->requireAdmin('admin.groups');

        $group = Group::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $request->validate(['name' => ['required', 'max:255']])['name'],
        ]);

        $this->audit->log('admin.group.created', 'group', $group->id);

        return back()->with('success', 'Groupe créé.');
    }

    public function updateGroup(Request $request, int $group)
    {
        $this->requireAdmin('admin.groups');
        $group = $this->group($group);

        $group->update(['name' => $request->validate(['name' => ['required', 'max:255']])['name']]);
        $this->audit->log('admin.group.updated', 'group', $group->id);

        return back()->with('success', 'Groupe renommé.');
    }

    public function groupMembers(Request $request, int $group)
    {
        $this->requireAdmin('admin.groups');
        $group = $this->group($group);

        $ids = $request->input('user_ids', []);
        $pivots = collect($ids)->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $group->tenant_id]])->all();
        $group->users()->sync($pivots);

        $this->audit->log('admin.group.members.updated', 'group', $group->id);

        return back()->with('success', 'Membres mis à jour.');
    }

    /** CA-RBAC5: link roles to a group — members inherit them. */
    public function groupRoles(Request $request, int $group)
    {
        $this->requireAdmin('admin.groups');
        $group = $this->group($group);

        $roleIds = $request->input('role_ids', []);
        $pivots = collect($roleIds)->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $group->tenant_id]])->all();
        $group->roles()->sync($pivots);

        $this->audit->log('admin.group.roles.updated', 'group', $group->id, ['roles' => $roleIds]);

        return back()->with('success', 'Rôles du groupe mis à jour — les membres héritent de ces rôles.');
    }

    public function deleteGroup(int $group)
    {
        $this->requireAdmin('admin.groups');
        $group = $this->group($group);

        // Deleting a group never deletes its users (CDG §12).
        $group->users()->detach();
        $group->delete();
        $this->audit->log('admin.group.deleted', 'group', $group->id);

        return back()->with('success', 'Groupe supprimé.');
    }

    /*
     |--------------------------------------------------------------------------
     | Rôles et permissions
     |--------------------------------------------------------------------------
     */

    public function roles()
    {
        $this->requireAdmin('admin.roles');

        return view('admin.roles', [
            'roles' => Role::with('permissions')->orderBy('name')->get(),
            'permissions' => Permission::orderBy('group')->orderBy('name')->get(),
            'spaces' => Space::orderBy('name')->get(),
        ]);
    }

    public function storeRole(Request $request)
    {
        $this->requireAdmin('admin.roles');

        $role = Role::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $request->validate(['name' => ['required', 'max:255']])['name'],
            'slug' => Str::slug($request->input('name')).'-'.uniqid(),
        ]);

        $this->audit->log('admin.role.created', 'role', $role->id);

        return back()->with('success', 'Rôle créé.');
    }

    public function updateRoleName(Request $request, int $role)
    {
        $this->requireAdmin('admin.roles');
        $role = $this->role($role);

        $role->update(['name' => $request->validate(['name' => ['required', 'max:255']])['name']]);
        $this->audit->log('admin.role.renamed', 'role', $role->id);

        return back()->with('success', 'Rôle renommé.');
    }

    public function duplicateRole(int $role)
    {
        $this->requireAdmin('admin.roles');
        $role = $this->role($role);

        $copy = Role::create([
            'tenant_id' => $role->tenant_id,
            'name' => $role->name.' (copie)',
            'slug' => $role->slug.'-copy-'.uniqid(),
            'is_system' => false,
        ]);

        foreach ($role->permissions as $perm) {
            RolePermission::create([
                'role_id' => $copy->id,
                'permission_id' => $perm->id,
                'scope_type' => $perm->pivot->scope_type,
                'scope_id' => $perm->pivot->scope_id,
                'denied' => $perm->pivot->denied,
            ]);
        }

        $this->audit->log('admin.role.duplicated', 'role', $role->id, ['copy' => $copy->id]);

        return back()->with('success', 'Rôle dupliqué ('.$copy->name.').');
    }

    public function updateRole(Request $request, int $role)
    {
        $this->requireAdmin('admin.roles');
        $role = $this->role($role);

        $perms = $request->input('permissions', []);

        RolePermission::where('role_id', $role->id)->delete();

        foreach ($perms as $permSlug => $conf) {
            $permission = Permission::where('slug', $permSlug)->first();
            if (! $permission) {
                continue;
            }

            $denied = ($conf['deny'] ?? false) === '1';

            RolePermission::create([
                'role_id' => $role->id,
                'permission_id' => $permission->id,
                'scope_type' => $conf['scope_type'] ?? 'tenant',
                'scope_id' => ($conf['scope_id'] ?? null) ?: null,
                'denied' => $denied,
            ]);
        }

        $this->audit->log('admin.role.permissions.updated', 'role', $role->id, ['permissions' => array_keys($perms)]);

        return back()->with('success', 'Permissions du rôle mises à jour.');
    }

    public function deleteRole(int $role)
    {
        $this->requireAdmin('admin.roles');
        $role = $this->role($role);

        if ($role->is_system) {
            return back()->withErrors(['role' => 'Un rôle système ne peut pas être supprimé.']);
        }

        if (\DB::table('user_role')->where('role_id', $role->id)->exists()) {
            return back()->withErrors(['role' => 'Ce rôle est affecté à des utilisateurs — réaffectez-les d\'abord.']);
        }

        RolePermission::where('role_id', $role->id)->delete();
        $role->delete();
        $this->audit->log('admin.role.deleted', 'role', $role->id);

        return back()->with('success', 'Rôle supprimé.');
    }

    /*
     |--------------------------------------------------------------------------
     | Types documentaires & métadonnées
     |--------------------------------------------------------------------------
     */

    public function types()
    {
        $this->requireAdmin('admin.types');

        return view('admin.types', [
            'types' => DocumentType::with('workflows')->orderBy('name')->get(),
            'definitions' => MetadataDefinition::orderBy('name')->get(),
        ]);
    }

    public function storeType(Request $request)
    {
        $this->requireAdmin('admin.types');

        $data = $request->validate([
            'name' => ['required', 'max:255'],
            'retention_days' => ['nullable', 'integer', 'min:0'],
            'metadata_keys' => ['nullable', 'array'],
        ]);

        $type = DocumentType::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.uniqid(),
            'metadata_schema' => $data['metadata_keys'] ?? [],
            'retention_days' => $data['retention_days'] ?: null,
        ]);

        $this->audit->log('admin.type.created', 'document_type', $type->id);

        return back()->with('success', 'Type documentaire créé.');
    }

    public function updateType(Request $request, int $type)
    {
        $this->requireAdmin('admin.types');
        $type = DocumentType::findOrFail($type);

        $data = $request->validate([
            'name' => ['required', 'max:255'],
            'retention_days' => ['nullable', 'integer', 'min:0'],
        ]);

        $type->update([
            'name' => $data['name'],
            'retention_days' => $data['retention_days'] ?: null,
        ]);

        $this->audit->log('admin.type.updated', 'document_type', $type->id);

        return back()->with('success', 'Type documentaire mis à jour.');
    }

    public function deleteType(int $type)
    {
        $this->requireAdmin('admin.types');
        $type = DocumentType::findOrFail($type);

        if (\DB::table('documents')->where('document_type_id', $type->id)->exists()) {
            return back()->withErrors(['type' => 'Des documents utilisent ce type — réaffectez-les d\'abord.']);
        }

        $type->delete();
        $this->audit->log('admin.type.deleted', 'document_type', $type->id);

        return back()->with('success', 'Type documentaire supprimé.');
    }

    public function storeMetadataDefinition(Request $request)
    {
        $this->requireAdmin('admin.types');

        $data = $request->validate([
            'name' => ['required', 'max:255'],
            'type' => ['required', 'in:text,longtext,number,date,boolean,list,multiselect,user'],
            'required' => ['nullable', 'boolean'],
        ]);

        $def = MetadataDefinition::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $data['name'],
            'key' => Str::slug($data['name']).'-'.uniqid(),
            'type' => $data['type'],
            'required' => $request->boolean('required'),
            'options' => $request->filled('options') ? array_filter(array_map('trim', explode(',', $request->input('options')))) : null,
        ]);

        $this->audit->log('admin.metadata.created', 'metadata_definition', $def->id);

        return back()->with('success', 'Champ de métadonnée créé.');
    }

    public function updateMetadataDefinition(Request $request, int $definition)
    {
        $this->requireAdmin('admin.types');
        $def = MetadataDefinition::findOrFail($definition);

        $data = $request->validate([
            'name' => ['required', 'max:255'],
            'type' => ['required', 'in:text,longtext,number,date,boolean,list,multiselect,user'],
            'required' => ['nullable', 'boolean'],
        ]);

        $def->update([
            'name' => $data['name'],
            'type' => $data['type'],
            'required' => $request->boolean('required'),
            'options' => $request->filled('options') ? array_filter(array_map('trim', explode(',', $request->input('options')))) : null,
        ]);

        $this->audit->log('admin.metadata.updated', 'metadata_definition', $def->id);

        return back()->with('success', 'Champ de métadonnée mis à jour.');
    }

    public function deleteMetadataDefinition(int $definition)
    {
        $this->requireAdmin('admin.types');
        $def = MetadataDefinition::findOrFail($definition);

        if (\DB::table('metadata_values')->where('definition_id', $def->id)->exists()) {
            return back()->withErrors(['definition' => 'Des valeurs existent pour ce champ.']);
        }

        $def->delete();
        $this->audit->log('admin.metadata.deleted', 'metadata_definition', $def->id);

        return back()->with('success', 'Champ de métadonnée supprimé.');
    }

    /*
     |--------------------------------------------------------------------------
     | Audit
     |--------------------------------------------------------------------------
     */

    public function audit(Request $request)
    {
        $this->requireAdmin('admin.audit');

        $logs = $this->auditQuery($request)->paginate(50);

        return view('admin.audit', ['logs' => $logs, 'users' => User::where('tenant_id', auth()->user()->tenant_id)->get()]);
    }

    public function auditExport(Request $request): StreamedResponse
    {
        $this->requireAdmin('admin.audit');

        $logs = $this->auditQuery($request)->limit(10000)->get();

        $filename = 'audit-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Utilisateur', 'Action', 'Ressource', 'Détails', 'IP']);

            foreach ($logs as $log) {
                fputcsv($out, [
                    $log->created_at?->format('d/m/Y H:i:s'),
                    $log->user?->name ?? 'système',
                    $log->action,
                    ($log->resource_type ?? '').'#'.($log->resource_id ?? ''),
                    json_encode($log->details, JSON_UNESCAPED_UNICODE),
                    $log->ip,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function auditQuery(Request $request)
    {
        return AuditLog::with('user')
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', '%'.$request->input('action').'%'))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->input('user_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('date_to')))
            ->orderByDesc('id');
    }

    /*
     |--------------------------------------------------------------------------
     | Paramètres (onglets)
     |--------------------------------------------------------------------------
     */

    public function settings()
    {
        $this->requireAdmin('admin.settings');

        return view('admin.settings', [
            'tenant' => auth()->user()->tenant,
            'settings' => $this->tenantSettings()->all(),
            'storageUsedMb' => $this->storage->tenantStorageMb(auth()->user()->tenant),
            'mimeChoices' => DocumentService::DEFAULT_ALLOWED_MIMES,
            'workflowChoices' => Workflow::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $this->requireAdmin('admin.settings');

        $tenant = auth()->user()->tenant;
        $settings = $this->tenantSettings();

        $tenant->update([
            'storage_quota_mb' => $request->integer('storage_quota_mb', $tenant->storage_quota_mb),
            'user_quota' => $request->integer('user_quota', $tenant->user_quota),
            'max_file_size_mb' => $request->integer('max_file_size_mb', $tenant->max_file_size_mb),
        ]);

        // Branding intégré au formulaire principal (1 seul bouton d'enregistrement).
        if ($request->hasAny(['brand_color', 'brand_color_custom', 'brand_logo_url', 'brand_name', 'theme', 'theme_mode'])) {
            $tenant->update([
                'branding' => [
                    'color' => $request->boolean('brand_color_custom') ? ($request->input('brand_color') ?: null) : null,
                    'logo_url' => $request->input('brand_logo_url') ?: null,
                    'brand_name' => $request->input('brand_name') ?: null,
                    'theme' => $request->input('theme') ?: null,
                    'theme_mode' => $request->input('theme_mode') ?: null,
                ],
            ]);
        }

        // Messagerie SMTP par tenant (mot de passe chiffré).
        $mailValues = [
            'mail_enabled' => $request->boolean('mail_enabled'),
            'smtp_host' => $request->input('smtp_host') ?: null,
            'smtp_port' => $request->integer('smtp_port', 587),
            'smtp_username' => $request->input('smtp_username') ?: null,
            'smtp_from_address' => $request->input('smtp_from_address') ?: null,
            'smtp_from_name' => $request->input('smtp_from_name') ?: null,
        ];

        if ($request->filled('smtp_password')) {
            $mailValues['smtp_password'] = Crypt::encryptString($request->input('smtp_password'));
        }

        $this->tenantSettings()->set($mailValues);

        // Fournisseurs IA : champ texte « séparés par des virgules » → tableau.
        $providers = array_values(array_filter(array_map('trim', explode(',', (string) $request->input('ai_providers', 'mock')))));

        $settings->set([
            // Général
            'language' => $request->input('language', 'fr'),
            'timezone' => $request->input('timezone', 'UTC'),
            // Sécurité (configurable seulement — application MFA/session en V2)
            'mfa_required_admin' => $request->boolean('mfa_required_admin'),
            'mfa_required_validator' => $request->boolean('mfa_required_validator'),
            'session_expiration_minutes' => max(15, $request->integer('session_expiration_minutes', 120)),
            'password_min_length' => max(8, $request->integer('password_min_length', 8)),
            // Documents (appliqués)
            'allowed_mimes' => $request->input('allowed_mimes', DocumentService::DEFAULT_ALLOWED_MIMES),
            'auto_lock_on_edit' => $request->boolean('auto_lock_on_edit'),
            'comment_required' => $request->boolean('comment_required'),
            // Rétention
            'default_retention_days' => $request->filled('default_retention_days') ? (int) $request->input('default_retention_days') : null,
            'trash_purge_days' => $request->integer('trash_purge_days', 30),
            'retention_alert_days' => $request->integer('retention_alert_days', 30),
            // Notifications
            'notif_tasks' => $request->boolean('notif_tasks'),
            'notif_shares' => $request->boolean('notif_shares'),
            'notif_deadlines' => $request->boolean('notif_deadlines'),
            'email_notifications' => $request->boolean('email_notifications'),
            // Partage externe (appliqué)
            'external_sharing_enabled' => $request->boolean('external_sharing_enabled'),
            'external_share_max_days' => $request->integer('external_share_max_days', 30),
            'external_share_password_required' => $request->boolean('external_share_password_required'),
            // IA
            'ai_enabled' => $request->boolean('ai_enabled'),
            'ai_providers' => $providers ?: ['mock'],
            // Clés API LLM du tenant (vide = héritage plateforme) — chiffrées.
            'openai_api_key' => $request->filled('openai_api_key') ? Crypt::encryptString($request->input('openai_api_key')) : ($settings->get('openai_api_key')),
            'openai_model' => $request->input('openai_model') ?: ($settings->get('openai_model') ?: 'gpt-4o-mini'),
            'anthropic_api_key' => $request->filled('anthropic_api_key') ? Crypt::encryptString($request->input('anthropic_api_key')) : ($settings->get('anthropic_api_key')),
            'anthropic_model' => $request->input('anthropic_model') ?: ($settings->get('anthropic_model') ?: 'claude-3-5-haiku'),
            // Workflows
            'default_workflow_type' => $request->filled('default_workflow_type') ? (int) $request->input('default_workflow_type') : null,
        ]);

        $this->audit->log('admin.settings.updated', 'tenant', $tenant->id, $tenant->getChanges());

        return back()->with('success', 'Paramètres enregistrés.');
    }

    /** Envoi d'un email de test depuis la messagerie du tenant (Paramètres → Messagerie). */
    public function testMail(Request $request)
    {
        $this->requireAdmin('admin.settings');

        $tenant = auth()->user()->tenant;
        $service = app(MailSettingsService::class);

        if (! $service->enabled($tenant)) {
            return back()->withErrors(['mail' => 'La messagerie n\'est pas activée ou le serveur SMTP n\'est pas renseigné.']);
        }

        try {
            $service->configure($tenant);
            Mail::to(auth()->user()->email)->send(new NotificationEmail(
                title: 'Test de messagerie — '.$service->fromName($tenant),
                body: 'Cet email confirme que la messagerie SMTP de votre organisation est correctement configurée.',
                appName: $service->fromName($tenant),
            ));

            $this->audit->log('admin.settings.mail_test', 'tenant', $tenant->id);

            return back()->with('success', 'Email de test envoyé à '.auth()->user()->email.'.');
        } catch (\Throwable $e) {
            return back()->withErrors(['mail' => 'Échec de l\'envoi : '.$e->getMessage()]);
        }
    }

    /** Test de connexion à un fournisseur LLM (onglet IA) — clé résolue tenant > plateforme. */
    public function testAi(Request $request)
    {
        $this->requireAdmin('admin.settings');

        $provider = $request->input('provider', 'openai');
        if (! in_array($provider, ['openai', 'anthropic'], true)) {
            return back()->withErrors(['ai' => 'Fournisseur inconnu.']);
        }

        try {
            app(AiService::class)->testConnection(auth()->user()->tenant, $provider);

            $this->audit->log('admin.settings.ai_test', 'tenant', auth()->user()->tenant_id, ['provider' => $provider]);

            return back()->with('success', "Connexion à {$provider} réussie.");
        } catch (\Throwable $e) {
            return back()->withErrors(['ai' => 'Échec de la connexion : '.$e->getMessage()]);
        }
    }

    /*
     |--------------------------------------------------------------------------
     | Branding (Lot A — variables CSS)
     |--------------------------------------------------------------------------
     */

    public function updateBranding(Request $request)
    {
        $this->requireAdmin('admin.settings');

        $data = $request->validate([
            'brand_color' => ['nullable', 'max:7'],
            'brand_logo_url' => ['nullable', 'url', 'max:500'],
        ]);

        auth()->user()->tenant->update([
            'branding' => [
                'color' => $data['brand_color'] ?: null,
                'logo_url' => $data['brand_logo_url'] ?: null,
            ],
        ]);

        $this->audit->log('admin.branding.updated', 'tenant', auth()->user()->tenant_id);

        return back()->with('success', 'Branding mis à jour.');
    }
}
