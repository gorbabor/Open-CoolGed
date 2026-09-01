<?php

namespace App\Http\Controllers;

use App\Models\PlatformSettings;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AiService;
use App\Services\AuditService;
use App\Services\DocumentService;
use App\Services\PersonalSpaceService;
use App\Services\StorageService;
use App\Services\SystemRoleService;
use App\Themes\ThemeRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class SuperAdminController extends Controller
{
    public function __construct(private AuditService $audit, private StorageService $storage) {}

    public function tenants()
    {
        return view('superadmin.tenants', [
            'tenants' => Tenant::withCount('users')->orderBy('name')->get(),
            'superAdmins' => User::withoutGlobalScopes()
                ->where('is_super_admin', true)
                ->orderBy('name')
                ->get(),
            'stats' => [
                'tenants' => Tenant::count(),
                'users' => User::withoutGlobalScopes()->where('is_super_admin', false)->count(),
                'documents' => \DB::table('documents')->count(),
                'storageMb' => round((float) \DB::table('document_versions')->sum('size') / 1048576, 1),
            ],
        ]);
    }

    public function storeTenant(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'max:255'],
            'plan' => ['nullable', 'max:100'],
            'storage_quota_mb' => ['nullable', 'integer', 'min:1'],
            'user_quota' => ['nullable', 'integer', 'min:1'],
            // Admin initial optionnel : permet de lier un compte admin au tenant dès sa création.
            'admin_name' => ['nullable', 'required_with:admin_email,admin_password', 'max:255'],
            'admin_email' => ['nullable', 'required_with:admin_name,admin_password', 'email', 'unique:users,email'],
            'admin_password' => ['nullable', 'required_with:admin_name,admin_email', 'min:10'],
        ]);

        // D-ADM4: platform settings provide the defaults for new tenants.
        $tenantSettings = ['ai_enabled' => true, 'ai_providers' => ['mock']];
        PlatformSettings::instance()->applyToNewTenant($tenantSettings);

        $tenant = Tenant::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.uniqid(),
            'plan' => $data['plan'] ?? 'standard',
            'storage_quota_mb' => $data['storage_quota_mb'] ?? 5120,
            'user_quota' => $data['user_quota'] ?? 50,
            'settings' => $tenantSettings,
        ]);

        $this->audit->log('superadmin.tenant.created', 'tenant', $tenant->id);
        $this->ensureSystemRoles($tenant);

        if ($data['admin_name'] ?? null) {
            $this->createTenantAdmin($tenant, $data['admin_name'], $data['admin_email'], $data['admin_password']);
        }

        return back()->with('success', 'Tenant créé.');
    }

    /** Rattache un compte admin (rôle tenant_admin) à une entreprise existante. */
    public function storeTenantAdmin(Request $request, int $tenant)
    {
        $tenant = $this->tenant($tenant);

        $data = $request->validate([
            'name' => ['required', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:10'],
        ]);

        $this->createTenantAdmin($tenant, $data['name'], $data['email'], $data['password']);

        return back()->with('success', 'Administrateur créé et rattaché à « '.$tenant->name.' ».');
    }

    private function createTenantAdmin(Tenant $tenant, string $name, string $email, string $password): User
    {
        $roles = $this->ensureSystemRoles($tenant);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'status' => 'active',
        ]);

        $user->roles()->attach($roles['tenant_admin']->id, ['tenant_id' => $tenant->id]);
        app(PersonalSpaceService::class)->ensure($user);
        $this->audit->log('superadmin.tenant_admin.created', 'user', $user->id, ['tenant_id' => $tenant->id]);

        return $user;
    }

    /** Paramètres plateforme (défauts appliqués aux nouveaux tenants). */
    public function platformSettings()
    {
        $settings = PlatformSettings::instance();

        return view('superadmin.settings', [
            'settings' => $settings->allSettings(),
            'mimeChoices' => DocumentService::DEFAULT_ALLOWED_MIMES,
        ]);
    }

    public function updatePlatformSettings(Request $request)
    {
        $settings = PlatformSettings::instance();

        $settings->set([
            'app_name' => $request->input('app_name') ?: 'Open-CoolGed',
            'theme' => $request->input('theme') ?: ThemeRegistry::DEFAULT_THEME,
            'theme_mode' => $request->input('theme_mode') ?: 'auto',
            'mfa_required_admin' => $request->boolean('mfa_required_admin'),
            'mfa_required_validator' => $request->boolean('mfa_required_validator'),
            'session_expiration_minutes' => max(15, $request->integer('session_expiration_minutes', 120)),
            'password_min_length' => max(8, $request->integer('password_min_length', 8)),
            'allowed_mimes' => $request->input('allowed_mimes', DocumentService::DEFAULT_ALLOWED_MIMES),
            'auto_lock_on_edit' => $request->boolean('auto_lock_on_edit'),
            'comment_required' => $request->boolean('comment_required'),
            'default_retention_days' => $request->filled('default_retention_days') ? (int) $request->input('default_retention_days') : null,
            'trash_purge_days' => $request->integer('trash_purge_days', 30),
            'external_sharing_enabled' => $request->boolean('external_sharing_enabled'),
            'external_share_max_days' => $request->integer('external_share_max_days', 30),
            'ai_enabled' => $request->boolean('ai_enabled'),
            'ai_providers' => collect($request->input('ai_providers', ['mock']))->flatMap(fn ($v) => array_map('trim', explode(',', (string) $v)))->filter()->values()->all() ?: ['mock'],
            // Clés API LLM (chiffrées) + modèles — hérités par les tenants (tous les fournisseurs du registry).
            ...collect(config('llm.providers', []))->mapWithKeys(function ($cfg, $slug) use ($request, $settings) {
                $keyField = $slug.'_api_key';
                $modelField = $slug.'_model';

                return [
                    $keyField => $request->filled($keyField) ? Crypt::encryptString($request->input($keyField)) : ($settings->get($keyField)),
                    $modelField => $request->input($modelField) ?: ($settings->get($modelField) ?: $cfg['default_model']),
                ];
            })->all(),
        ]);

        $this->audit->log('superadmin.platform_settings.updated', 'tenant', null, ['settings' => array_keys($request->all())]);

        return back()->with('success', 'Paramètres plateforme enregistrés — appliqués aux nouveaux tenants.');
    }

    public function toggleTenant(int $tenant)
    {
        $tenant = $this->tenant($tenant);
        $tenant->update(['status' => $tenant->isSuspended() ? 'active' : 'suspended']);
        $this->audit->log('superadmin.tenant.toggled', 'tenant', $tenant->id, ['status' => $tenant->status]);

        return back()->with('success', 'Tenant '.($tenant->isSuspended() ? 'suspendu' : 'réactivé').'.');
    }

    /** Test de connexion à un fournisseur LLM (clé plateforme — héritée par les tenants). */
    public function testAi(Request $request)
    {
        $provider = $request->input('provider', 'openai');
        if (config('llm.providers.'.$provider) === null) {
            return back()->withErrors(['ai' => 'Fournisseur inconnu.']);
        }

        try {
            // Tenant factice sans clé propre : la résolution retombe sur la clé plateforme.
            $dummy = new Tenant(['settings' => [], 'branding' => []]);
            app(AiService::class)->testConnection($dummy, $provider);

            $this->audit->log('superadmin.platform.ai_test', 'tenant', null, ['provider' => $provider]);

            return back()->with('success', "Connexion à {$provider} réussie (clé plateforme).");
        } catch (\Throwable $e) {
            return back()->withErrors(['ai' => 'Échec de la connexion : '.$e->getMessage()]);
        }
    }

    /** Suspendre / réactiver un super administrateur (jamais le dernier actif). */
    public function toggleSuperAdmin(int $user)
    {
        $user = User::withoutGlobalScopes()->findOrFail($user);

        if (! $user->isSuperAdmin()) {
            abort(404);
        }

        if ($user->isActive() && $this->activeSuperAdminCount() <= 1) {
            return back()->withErrors(['superadmin' => 'Impossible de suspendre le dernier super administrateur actif.']);
        }

        $user->update(['status' => $user->isSuspended() ? 'active' : 'suspended']);
        $this->audit->log('superadmin.user.toggled', 'user', $user->id, ['status' => $user->status]);

        return back()->with('success', 'Super administrateur '.($user->isSuspended() ? 'suspendu' : 'réactivé').'.');
    }

    private function activeSuperAdminCount(): int
    {
        return User::withoutGlobalScopes()->where('is_super_admin', true)->where('status', 'active')->count();
    }

    public function storeSuperAdmin(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:10'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_super_admin' => true,
            'status' => 'active',
        ]);

        $this->audit->log('superadmin.created', 'user', $user->id);

        return back()->with('success', 'Super administrateur créé.');
    }

    /** System roles are created per tenant (RM-029: tenant admin separated from platform admin). */
    public function ensureSystemRoles(Tenant $tenant): array
    {
        return app(SystemRoleService::class)->ensureFor($tenant);
    }
}
