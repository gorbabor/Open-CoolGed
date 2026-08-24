<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Folder;
use App\Models\Group;
use App\Models\MetadataDefinition;
use App\Models\Share;
use App\Models\Space;
use App\Models\User;
use App\Models\Workflow;
use App\Services\AuditService;
use App\Services\DocumentLifecycleService;
use App\Services\DocumentService;
use App\Services\EditorRegistry;
use App\Services\PermissionService;
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

        $query = Document::with(['currentVersion', 'type', 'space', 'folder'])
            ->whereIn('id', $accessibleIds ?: [0]);

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
            'title', 'reference', 'status', 'confidentiality',
            'space_id', 'type_id', 'updated_at', 'created_at',
        ];
        $sort = in_array($request->input('sort'), $sortable, true) ? $request->input('sort') : 'updated_at';
        $dir = strtolower($request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Tri par espace/type : jointure sur la table liée.
        if ($sort === 'space_id') {
            $query->leftJoin('spaces', 'documents.space_id', '=', 'spaces.id')
                ->orderBy('spaces.name', $dir);
        } elseif ($sort === 'type_id') {
            $query->leftJoin('document_types', 'documents.document_type_id', '=', 'document_types.id')
                ->orderBy('document_types.name', $dir);
        } else {
            $query->orderBy($sort, $dir);
        }

        $documents = $query->paginate(config('ged.pagination'))->withQueryString();

        // Colonnes métadonnées : préférence persistée en session (fallback requête → session).
        $definitions = MetadataDefinition::orderBy('name')->get();
        $requestedCols = collect($request->input('cols', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
        $selectedCols = $request->has('cols')
            ? $requestedCols
            : session('doc_columns', []);
        if ($request->has('cols')) {
            session(['doc_columns' => $selectedCols]);
        }
        $selectedCols = array_values(array_intersect($selectedCols, $definitions->pluck('id')->all()));

        if ($selectedCols !== []) {
            $documents->load(['metadataValues' => fn ($q) => $q->whereIn('definition_id', $selectedCols)]);
        }

        return view('documents.index', [
            'documents' => $documents,
            'spaces' => Space::orderBy('name')->get(),
            'types' => DocumentType::orderBy('name')->get(),
            'definitions' => $definitions,
            'selectedCols' => $selectedCols,
            'sort' => $sort,
            'dir' => $dir,
            'filters' => $request->only(['q', 'space_id', 'folder_id', 'status', 'type_id', 'confidentiality']),
        ]);
    }

    public function create()
    {
        return view('documents.create', [
            'spaces' => Space::orderBy('name')->get(),
            'types' => DocumentType::orderBy('name')->get(),
            'definitions' => MetadataDefinition::orderBy('name')->get(),
            'folders' => Folder::with('space')->orderBy('name')->get(),
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
            'file' => ['required', 'file'],
            'metadata' => ['nullable', 'array'],
            'tags' => ['nullable', 'array'],
        ]);

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
        $document->update([
            'title' => $data['title'],
            'space_id' => $data['space_id'],
            'folder_id' => $folderId,
            'document_type_id' => $typeId,
            'reference' => $request->input('reference', $document->reference),
            'confidentiality' => $request->input('confidentiality', $document->confidentiality),
            'expiration_at' => $request->input('expiration_at') ?: null,
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
