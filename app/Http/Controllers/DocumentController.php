<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Folder;
use App\Models\Group;
use App\Models\MetadataDefinition;
use App\Models\Referential;
use App\Models\Share;
use App\Models\Space;
use App\Models\User;
use App\Models\Workflow;
use App\Services\AuditService;
use App\Services\DocumentGroupService;
use App\Services\DocumentLifecycleService;
use App\Services\DocumentService;
use App\Services\EditorRegistry;
use App\Services\PermissionService;
use App\Services\PersonalSpaceService;
use App\Services\TenantSettings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    public function __construct(
        private DocumentService $documents,
        private PermissionService $permissions,
        private AuditService $audit,
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $accessibleIds = $this->permissions->accessibleDocumentIds($user);

        $query = Document::with(['currentVersion', 'type', 'space', 'folder', 'domain', 'process', 'referentials'])
            ->whereIn('documents.id', $accessibleIds ?: [0]);

        if ($request->boolean('personal')) {
            $personal = app(PersonalSpaceService::class)->ensure($user);
            $query->where('space_id', $personal->id);
        } else {
            $query->whereHas('space', fn ($q) => $q->where('is_personal', false));
        }

        if ($request->filled('space_id')) {
            $query->where('space_id', $request->input('space_id'));
        }
        if ($request->filled('folder_id')) {
            $query->where('folder_id', $request->input('folder_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('type_id')) {
            $query->where('document_type_id', $request->input('type_id'));
        }
        if ($request->filled('confidentiality')) {
            $query->where('confidentiality', $request->input('confidentiality'));
        }
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        // Tri sécurisé : liste blanche de colonnes, direction validée (asc/desc).
        $sortable = [
            'title', 'reference', 'document_code', 'status', 'confidentiality',
            'space_id', 'type_id', 'updated_at', 'created_at',
        ];

        // Colonnes métadonnées : préférence persistée en session (fallback requête → session).
        // Chargées AVANT le tri pour valider sort=meta:{id} contre les colonnes sélectionnées.
        $definitions = MetadataDefinition::orderBy('name')->get();
        $requestedCols = collect($request->input('cols', []))
            ->map(fn ($id) => (string) $id)
            ->filter(fn ($id) => ctype_digit($id) || in_array($id, ['domain', 'process', 'ref:job', 'ref:department', 'ref:direction', 'ref:site', 'ref:entity', 'ref:country', 'reference', 'document_code'], true))
            ->values()
            ->all();
        $selectedCols = $request->has('cols')
            ? $requestedCols
            : (auth()->user()->doc_columns ?? []);
        if ($request->has('cols')) {
            auth()->user()->update(['doc_columns' => $selectedCols]);
        }
        $allowedExtraCols = ['domain', 'process', 'ref:job', 'ref:department', 'ref:direction', 'ref:site', 'ref:entity', 'ref:country', 'reference', 'document_code'];
        $selectedCols = array_values(array_filter($selectedCols, fn ($id) => in_array((int) $id, $definitions->pluck('id')->all(), true) || in_array($id, $allowedExtraCols, true)));

        $view = $request->input('view') ?: auth()->user()->doc_view;
        $view = in_array($view, ['list', 'cards'], true) ? $view : 'list';
        if ($request->has('view')) {
            auth()->user()->update(['doc_view' => $view]);
        }

        $groupService = app(DocumentGroupService::class);
        $dimensions = $groupService->dimensions(false, $definitions);
        $group = $request->input('group') ?: auth()->user()->doc_group;
        if ($group === null || ! array_key_exists($group, $dimensions)) {
            $group = array_key_first($dimensions);
        }
        if ($request->has('group')) {
            auth()->user()->update(['doc_group' => $group]);
        }
        $value = $request->input('value');
        $value = $value !== null && $value !== '' ? (string) $value : null;

        $cards = null;
        $activeLabel = null;
        if ($view === 'cards') {
            $cards = $groupService->groups($query, $group);
        } elseif ($value !== null) {
            $activeLabel = $groupService->valueLabel($group, $value);
            $query = $groupService->applyFilter($query, $group, $value);
        }

        $sort = in_array($request->input('sort'), $sortable, true) ? $request->input('sort') : 'updated_at';
        $dir = strtolower($request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Tri par métadonnée : sort=meta:{definition_id} — validé contre les définitions existantes
        // (le lien de tri inclut déjà cols[] dans l'URL ; la colonne devient triable même sans sélection préalable).
        if (str_starts_with((string) $request->input('sort'), 'meta:')) {
            $metaId = (int) substr((string) $request->input('sort'), 5);
            $metaDef = $definitions->firstWhere('id', $metaId);
            if ($metaDef !== null) {
                $sort = 'meta:'.$metaId; // la vue doit connaître le tri actif pour l'alternance ▲/▼

                // La colonne triée est implicitement ajoutée à l'affichage.
                if (! in_array($metaId, $selectedCols, true)) {
                    $selectedCols[] = $metaId;
                    session(['doc_columns' => $selectedCols]);
                }

                // Jointure sur la valeur de métadonnée (unique document_id + definition_id).
                $query->select('documents.*')
                    ->leftJoin('metadata_values as mv', function ($join) use ($metaId) {
                        $join->on('mv.document_id', '=', 'documents.id')
                            ->where('mv.definition_id', '=', $metaId);
                    });

                // Tri typé : numérique / date / texte. NULLS LAST portable
                // (les documents sans valeur passent en dernier, asc ET desc).
                $cast = match ($metaDef->type) {
                    'number' => 'CAST(mv.value AS DECIMAL(20,4))',
                    'date' => 'CAST(mv.value AS DATE)',
                    default => 'mv.value',
                };
                $query->orderByRaw('mv.value IS NULL')
                    ->orderByRaw("{$cast} {$dir}");
            } else {
                $query->orderBy($sort, $dir);
            }
        } elseif ($sort === 'space_id') {
            $query->select('documents.*')
                ->leftJoin('spaces', 'documents.space_id', '=', 'spaces.id')
                ->orderBy('spaces.name', $dir);
        } elseif ($sort === 'type_id') {
            $query->select('documents.*')
                ->leftJoin('document_types', 'documents.document_type_id', '=', 'document_types.id')
                ->orderBy('document_types.name', $dir);
        } else {
            $query->orderBy($sort, $dir);
        }

        $documents = $view === 'cards' ? null : $query->paginate(config('ged.pagination'))->withQueryString();

        $selectedMetaIds = array_values(array_filter($selectedCols, fn ($id) => ctype_digit((string) $id)));
        if ($documents !== null && $selectedMetaIds !== []) {
            $documents->load(['metadataValues' => fn ($q) => $q->whereIn('definition_id', $selectedMetaIds)]);
        }

        return view('documents.index', [
            'documents' => $documents,
            'canCreate' => $this->permissions->can($user, 'documents.create'),
            'spaces' => Space::orderBy('name')->get(),
            'types' => DocumentType::orderBy('name')->get(),
            'definitions' => $definitions,
            'selectedCols' => $selectedCols,
            'extraColumns' => ['reference' => 'Référence', 'document_code' => 'Code', 'domain' => 'Domaine', 'process' => 'Processus', 'ref:job' => 'Poste', 'ref:department' => 'Département', 'ref:direction' => 'Direction', 'ref:site' => 'Site', 'ref:entity' => 'Entité', 'ref:country' => 'Pays'],
            'sort' => $sort,
            'dir' => $dir,
            'filters' => $request->only(['q', 'space_id', 'folder_id', 'status', 'type_id', 'confidentiality']),
            'viewMode' => $view,
            'dimensions' => $dimensions,
            'group' => $group,
            'groupValue' => $value,
            'cards' => $cards,
            'activeLabel' => $activeLabel,
        ]);
    }

    public function create()
    {
        if (! $this->permissions->can(auth()->user(), 'documents.create')) {
            abort(403, 'Création de documents non autorisée.');
        }

        $personalSpace = app(PersonalSpaceService::class)->ensure(auth()->user());

        return view('documents.create', [
            'spaces' => Space::where('is_personal', false)->orderBy('name')->get(),
            'personalSpace' => $personalSpace,
            'types' => DocumentType::orderBy('name')->get(),
            'definitions' => MetadataDefinition::orderBy('name')->get(),
            'folders' => Folder::with('space')->orderBy('name')->get(),
            'domains' => Referential::where('type', 'domain')->orderBy('name')->get(),
            'processes' => Referential::where('type', 'process')->orderBy('name')->get(),
            'applicationTypes' => Referential::TYPES,
            'applicationRefs' => Referential::orderBy('type')->orderBy('name')->get()->groupBy('type'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'max:255'],
            'space_id' => ['required', 'exists:spaces,id'],
            'folder_id' => ['nullable', 'exists:folders,id'],
            'document_type_id' => ['nullable', 'exists:document_types,id'],
            'reference' => ['nullable', 'max:255'],
            'description' => ['nullable'],
            'confidentiality' => ['nullable', 'in:public,internal,confidential,secret'],
            'expiration_at' => ['nullable', 'date'],
            'domain_id' => ['nullable', 'exists:referentials,id'],
            'process_id' => ['nullable', 'exists:referentials,id'],
            'application' => ['nullable', 'array'],
            'storage_scope' => ['nullable', 'in:personal,shared'],
            'file' => ['nullable', 'file'],
            'metadata' => ['nullable', 'array'],
            'tags' => ['nullable', 'array'],
        ]);

        $personalSpace = app(PersonalSpaceService::class)->ensure(auth()->user());
        if (($data['storage_scope'] ?? 'shared') === 'personal') {
            $data['space_id'] = $personalSpace->id;
            $data['folder_id'] = null;
            $data['owner_id'] = auth()->id();
        } elseif (! app(PersonalSpaceService::class)->canAccess(auth()->user(), Space::findOrFail($data['space_id']))) {
            abort(403);
        }

        try {
            $document = $this->documents->create(auth()->user(), $data, $request->file('file'));
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('documents.show', $document)->with('success', 'Document créé.');
    }

    public function show(int $document)
    {
        $document = $this->doc($document);
        $user = auth()->user();

        if (! $this->permissions->can($user, 'documents.view', $document)) {
            abort(403, 'Accès refusé à ce document.');
        }

        $this->audit->log('document.viewed', 'document', $document->id);

        $viewerType = $document->currentVersion
            ? app(EditorRegistry::class)->resolveViewer($document->currentVersion)
            : null;

        return view('documents.show', [
            'document' => $document->load(['versions.creator', 'currentVersion', 'type', 'space', 'folder', 'comments.user', 'shares', 'tags']),
            'viewerType' => $viewerType,
            'canDownload' => $this->permissions->can($user, 'documents.download', $document),
            'canEdit' => $this->permissions->can($user, 'documents.edit', $document),
            'canShare' => $this->permissions->can($user, 'documents.share', $document),
            'canComment' => $this->permissions->can($user, 'documents.comment', $document),
            'canAi' => $this->permissions->can($user, 'ai.use', $document),
            'canDelete' => $this->permissions->can($user, 'documents.delete', $document),
            'lifecycle' => app(DocumentLifecycleService::class),
            'lifecycleFrozen' => app(DocumentLifecycleService::class)->isFrozen($document),
            'metadataEditable' => app(DocumentLifecycleService::class)->metadataEditable($user, $document),
            'contentEditable' => app(DocumentLifecycleService::class)->contentEditable($user, $document),
            'definitions' => MetadataDefinition::orderBy('name')->get(),
            'metadata' => $document->metadataValues->pluck('value', 'definition_id'),
            'spaces' => Space::orderBy('name')->get(),
            'folders' => Folder::with('space')->orderBy('name')->get(),
            'types' => DocumentType::orderBy('name')->get(),
            'users' => User::where('tenant_id', $user->tenant_id)->where('id', '!=', $user->id)->get(),
            'groups' => Group::orderBy('name')->get(),
            'jobs' => $document->aiJobs()->with('result')->orderByDesc('id')->get(),
            'workflow' => $document->workflowInstances()->with(['workflow', 'currentStep', 'tasks.step'])->orderByDesc('id')->first(),
            'workflowHistory' => $document->workflowInstances()->with(['workflow', 'currentStep', 'tasks.step', 'tasks.actor', 'creator'])->orderByDesc('id')->get(),
            'activeWorkflows' => Workflow::where('is_active', true)->get(),
            'externalSharingEnabled' => TenantSettings::for($document->tenant)->externalSharingEnabled(),
            'domains' => Referential::where('type', 'domain')->orderBy('name')->get(),
            'processes' => Referential::where('type', 'process')->orderBy('name')->get(),
            'applicationTypes' => Referential::TYPES,
            'applicationRefs' => Referential::orderBy('type')->orderBy('name')->get()->groupBy('type'),
        ]);
    }

    public function uploadVersion(Request $request, int $document)
    {
        $document = $this->doc($document);
        $request->validate(['file' => ['required', 'file']]);

        // Tenant policy: a version comment can be required (Administration → Documents).
        if (TenantSettings::for($document->tenant)->get('comment_required') && trim((string) $request->input('comment')) === '') {
            return back()->withErrors(['comment' => 'Le commentaire de version est obligatoire pour cette organisation.']);
        }

        try {
            $this->documents->addVersion(
                auth()->user(),
                $document,
                $request->file('file'),
                $request->input('comment') ?: 'Nouvelle version'
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back()->with('success', 'Nouvelle version créée.');
    }

    public function download(int $document, ?int $version = null)
    {
        $document = $this->doc($document);
        $user = auth()->user();

        if (! $this->permissions->can($user, 'documents.download', $document)) {
            abort(403, 'Téléchargement non autorisé.');
        }

        $version = $version ? $this->version($version) : $document->currentVersion;
        if (! $version) {
            abort(404);
        }

        $this->audit->log('document.downloaded', 'document_version', $version->id);

        return Storage::disk(config('ged.storage_disk'))->download($version->file_path, $version->file_name);
    }

    public function preview(int $document)
    {
        $document = $this->doc($document);

        if (! $this->permissions->can(auth()->user(), 'documents.preview', $document)) {
            abort(403);
        }

        $version = $document->currentVersion;

        return view('documents.preview', compact('document', 'version'));
    }

    public function restoreVersion(Request $request, int $document, int $version)
    {
        $document = $this->doc($document);
        $version = $this->version($version);

        try {
            $this->documents->restoreVersion(auth()->user(), $document, $version);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['version' => $e->getMessage()]);
        }

        return back()->with('success', 'Version restaurée (nouvelle version courante créée).');
    }

    public function trash(int $document)
    {
        $document = $this->doc($document);

        if (! $this->permissions->can(auth()->user(), 'documents.delete', $document)) {
            abort(403);
        }

        $document->delete();
        $this->audit->log('document.trashed', 'document', $document->id);

        return redirect()->route('documents.index')->with('success', 'Document déplacé vers la corbeille.');
    }

    public function restore(int $document)
    {
        $document = $this->doc($document);
        $document->restore();
        $this->audit->log('document.restored', 'document', $document->id);

        return back()->with('success', 'Document restauré.');
    }

    public function archive(int $document)
    {
        $document = $this->doc($document);

        if (! $this->permissions->can(auth()->user(), 'documents.archive', $document)) {
            abort(403);
        }

        $document->update(['status' => 'archived', 'archived_at' => now()]);
        $this->audit->log('document.archived', 'document', $document->id);

        return back()->with('success', 'Document archivé.');
    }

    public function unarchive(int $document)
    {
        $document = $this->doc($document);
        $document->update(['status' => 'draft', 'archived_at' => null]);
        $this->audit->log('document.unarchived', 'document', $document->id);

        return back()->with('success', 'Document désarchivé.');
    }

    public function deletePermanently(int $document)
    {
        $document = $this->doc($document);

        if (! $this->permissions->can(auth()->user(), 'documents.delete', $document)) {
            abort(403);
        }

        foreach ($document->versions as $version) {
            Storage::disk(config('ged.storage_disk'))->delete($version->file_path);
        }

        $id = $document->id;
        $document->forceDelete();
        $this->audit->log('document.deleted_permanently', 'document', $id);

        return redirect()->route('documents.index')->with('success', 'Document supprimé définitivement.');
    }

    public function comment(Request $request, int $document)
    {
        $document = $this->doc($document);

        if (! $this->permissions->can(auth()->user(), 'documents.comment', $document)) {
            abort(403);
        }

        $request->validate(['body' => ['required', 'string', 'max:5000']]);

        Comment::create([
            'tenant_id' => $document->tenant_id,
            'document_id' => $document->id,
            'user_id' => auth()->id(),
            'body' => $request->input('body'),
        ]);

        $this->audit->log('document.commented', 'document', $document->id);

        return back()->with('success', 'Commentaire ajouté.');
    }

    public function share(Request $request, int $document)
    {
        $document = $this->doc($document);

        if (! $this->permissions->can(auth()->user(), 'documents.share', $document)) {
            abort(403);
        }

        // RM-009: a share can never grant more rights than the issuer holds.
        if (! $this->permissions->can(auth()->user(), 'documents.share', $document)) {
            abort(403);
        }

        $settings = TenantSettings::for($document->tenant);

        if ($request->boolean('external')) {
            // External link: requires the tenant option, an expiry (max configured),
            // and optionally a password. Token-scoped access — no public URL (RM-012).
            if (! $settings->externalSharingEnabled()) {
                return back()->withErrors(['external' => 'Le partage externe est désactivé pour cette organisation.']);
            }

            $data = $request->validate([
                'permission' => ['required', 'in:view,download'],
                'expires_at' => ['required', 'date', 'after:today'],
                'password' => ['nullable', 'min:4'],
            ]);

            $maxDays = (int) $settings->get('external_share_max_days', 30);
            if (now()->diffInDays(Carbon::parse($data['expires_at']), false) > $maxDays) {
                return back()->withErrors(['expires_at' => "L'expiration ne peut pas dépasser {$maxDays} jours."]);
            }

            $rawToken = Str::random(64);
            $share = Share::create([
                'tenant_id' => $document->tenant_id,
                'document_id' => $document->id,
                'permission' => $data['permission'],
                'expires_at' => $data['expires_at'],
                'created_by' => auth()->id(),
                'is_external' => true,
                'token' => Share::hashToken($rawToken),
                'password_hash' => $data['password'] ? Hash::make($data['password']) : null,
            ]);

            $this->audit->log('share.external.created', 'share', $share->id, ['document' => $document->id]);

            return back()->with('success', 'Lien externe créé : '.route('share.external.show', $rawToken));
        }

        $data = $request->validate([
            'shared_with_user_id' => ['nullable', 'exists:users,id'],
            'shared_with_group_id' => ['nullable', 'exists:groups,id'],
            'permission' => ['required', 'in:view,download,edit'],
            'expires_at' => ['nullable', 'date'],
        ]);

        if (! $data['shared_with_user_id'] && ! $data['shared_with_group_id']) {
            return back()->withErrors(['share' => 'Choisissez un bénéficiaire.']);
        }

        Share::create(array_merge($data, [
            'tenant_id' => $document->tenant_id,
            'document_id' => $document->id,
            'created_by' => auth()->id(),
        ]));

        $this->audit->log('document.shared', 'document', $document->id, $data);

        return back()->with('success', 'Partage créé.');
    }

    public function publishPersonal(Request $request, int $document)
    {
        $document = $this->doc($document);
        $user = auth()->user();
        $personal = $document->space;
        if (! $personal->is_personal || $personal->personal_user_id !== $user->id) {
            abort(403);
        }

        $data = $request->validate(['space_id' => ['required', 'exists:spaces,id']]);
        $target = Space::findOrFail($data['space_id']);
        if ($target->is_personal || ! app(PersonalSpaceService::class)->canAccess($user, $target)) {
            abort(403);
        }

        $document->update(['space_id' => $target->id, 'folder_id' => null]);
        $this->audit->log('document.personal.published', 'document', $document->id, ['from_space' => $personal->id, 'to_space' => $target->id]);

        return back()->with('success', 'Document publié dans l’espace partagé.');
    }

    public function revokeShare(int $document, int $share)
    {
        $this->doc($document);
        $share = $this->shareModel($share);
        $share->update(['revoked_at' => now()]);
        $this->audit->log('share.revoked', 'share', $share->id);

        return back()->with('success', 'Partage révoqué.');
    }

    public function updateMetadata(Request $request, int $document)
    {
        $document = $this->doc($document);
        $user = auth()->user();

        $lifecycle = app(DocumentLifecycleService::class);

        if ($lifecycle->isFrozen($document)) {
            return back()->withErrors(['title' => 'Ce document est verrouillé (statut « '.$document->statusLabel().' »). Une nouvelle révision est requise pour modifier ses caractéristiques.']);
        }

        if (! $this->permissions->can($user, 'documents.metadata', $document)) {
            abort(403);
        }

        $data = $request->validate([
            'title' => ['required', 'max:255'],
            'space_id' => ['required', 'exists:spaces,id'],
            'folder_id' => ['nullable', 'exists:folders,id'],
            'document_type_id' => ['nullable', 'exists:document_types,id'],
            'reference' => ['nullable', 'max:255'],
            'confidentiality' => ['nullable', 'in:public,internal,confidential,secret'],
            'expiration_at' => ['nullable', 'date'],
            'domain_id' => ['nullable', 'exists:referentials,id'],
            'process_id' => ['nullable', 'exists:referentials,id'],
            'application' => ['nullable', 'array'],
        ]);

        $folderId = $request->input('folder_id') ?: null;
        $typeId = $request->input('document_type_id') ?: null;

        // A folder must belong to the selected space (same guard as on import).
        if ($folderId) {
            $folder = Folder::find($folderId);
            if (! $folder || $folder->space_id !== (int) $data['space_id']) {
                return back()->withErrors(['folder_id' => 'Le dossier ne dépend pas de l\'espace sélectionné.']);
            }
        }

        $this->documents->saveMetadata($document, $request->input('metadata', []));
        $this->documents->syncTags($document, $request->input('tags', []));
        $this->documents->syncApplicationReferentials($document, $request->input('application', []));
        $document->update([
            'title' => $data['title'],
            'space_id' => $data['space_id'],
            'folder_id' => $folderId,
            'document_type_id' => $typeId,
            'reference' => $request->input('reference', $document->reference),
            'confidentiality' => $request->input('confidentiality', $document->confidentiality),
            'expiration_at' => $request->input('expiration_at') ?: null,
            'domain_id' => $request->input('domain_id') ?: null,
            'process_id' => $request->input('process_id') ?: null,
        ]);

        $this->audit->log('document.metadata.updated', 'document', $document->id, [
            'title' => $data['title'],
            'space_id' => $data['space_id'],
            'folder_id' => $folderId,
            'document_type_id' => $typeId,
        ]);

        return back()->with('success', 'Métadonnées mises à jour.');
    }
}
