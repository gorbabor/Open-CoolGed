<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Folder;
use App\Models\MetadataDefinition;
use App\Models\Referential;
use App\Models\Space;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DocumentGroupService;
use App\Services\DocumentService;
use App\Services\PermissionService;
use App\Services\V02DocumentService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Périmètre V02 — « Documents applicables à mon poste » (V02 §13) :
 * documents approuvés/applicables, version active, non obsolètes, filtrés par
 * les dimensions de l'utilisateur (poste, département, direction, site, entité,
 * pays) ou par ses groupes de sécurité.
 */
class V02Controller extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private V02DocumentService $v02,
    ) {}

    public function myDocuments(Request $request)
    {
        $user = auth()->user();
        $accessibleIds = $this->permissions->accessibleDocumentIds($user);

        $query = Document::with(['currentVersion', 'type', 'space', 'folder', 'domain', 'process', 'owner', 'referentials'])
            ->whereIn('id', $accessibleIds ?: [0])
            ->where('status', 'approuve_applicable')
            ->where('is_active_version', true);

        // Filtre multi-dimensions : dimensions de l'utilisateur OU groupes de sécurité.
        $dimensionIds = $user->dimensionIds();
        $query->where(function ($q) use ($dimensionIds, $user) {
            $q->whereHas('referentials', fn ($r) => $r->whereIn('referential_id', $dimensionIds ?: [0]))
                ->orWhereIn('owner_id', [$user->id])
                ->orWhereIn('reviewer_id', [$user->id])
                ->orWhereIn('approver_id', [$user->id]);
        });

        if ($request->filled('space_id')) {
            $query->where('space_id', $request->input('space_id'));
        }
        if ($request->filled('type_id')) {
            $query->where('document_type_id', $request->input('type_id'));
        }
        if ($request->filled('domain_id')) {
            $query->where('domain_id', $request->input('domain_id'));
        }
        if ($request->filled('process_id')) {
            $query->where('process_id', $request->input('process_id'));
        }
        if ($request->filled('criticality')) {
            $query->where('criticality', $request->input('criticality'));
        }
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(fn ($sub) => $sub->where('title', 'like', "%{$q}%")
                ->orWhere('document_code', 'like', "%{$q}%")
                ->orWhere('reference', 'like', "%{$q}%"));
        }

        $view = $request->input('view') ?: auth()->user()->doc_view;
        $view = in_array($view, ['list', 'cards'], true) ? $view : 'list';
        if ($request->has('view')) {
            auth()->user()->update(['doc_view' => $view]);
        }

        $definitions = MetadataDefinition::orderBy('name')->get();
        $groupService = app(DocumentGroupService::class);
        $dimensions = $groupService->dimensions(true, $definitions);
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

        $v02Columns = [
            'domain' => 'Domaine', 'process' => 'Processus', 'effective_date' => "Date d'application",
            'next_review_date' => 'Prochaine revue', 'owner' => 'Propriétaire', 'criticality' => 'Criticité',
            'ref:job' => 'Poste', 'ref:department' => 'Département', 'ref:direction' => 'Direction',
            'ref:site' => 'Site', 'ref:entity' => 'Entité', 'ref:country' => 'Pays',
        ];
        $requestedCols = collect($request->input('cols', []))->map(fn ($id) => (string) $id)->values()->all();
        $selectedCols = $request->has('cols') ? $requestedCols : (auth()->user()->my_doc_columns ?? []);
        $selectedCols = array_values(array_filter($selectedCols, fn ($id) => array_key_exists($id, $v02Columns) || ctype_digit($id)));
        if ($request->has('cols')) {
            auth()->user()->update(['my_doc_columns' => $selectedCols]);
        }
        $sortable = ['domain', 'process', 'effective_date', 'next_review_date', 'owner', 'criticality'];
        $sort = $request->input('sort', 'updated_at');
        $dir = strtolower($request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if ($view !== 'cards') {
            if (str_starts_with((string) $sort, 'meta:')) {
                $metaId = (int) substr((string) $sort, 5);
                if ($definitions->firstWhere('id', $metaId)) {
                    $query->select('documents.*')->leftJoin('metadata_values as v02_mv', fn ($join) => $join->on('v02_mv.document_id', '=', 'documents.id')->where('v02_mv.definition_id', '=', $metaId))->orderByRaw('v02_mv.value IS NULL')->orderBy('v02_mv.value', $dir);
                } else {
                    $query->orderByDesc('updated_at');
                }
            } elseif (in_array($sort, $sortable, true)) {
                $column = match ($sort) {
                    'domain' => 'domain_id', 'process' => 'process_id', 'owner' => 'owner_id', default => $sort,
                };
                $query->orderBy($column, $dir);
            } else {
                $query->orderByDesc('updated_at');
            }
            $documents = $query->paginate(config('ged.pagination'))->withQueryString();
            $metaIds = array_values(array_filter($selectedCols, fn ($id) => ctype_digit($id)));
            if ($metaIds !== []) {
                $documents->load(['metadataValues' => fn ($q) => $q->whereIn('definition_id', $metaIds)]);
            }
        } else {
            $documents = null;
        }

        return view('v02.my-documents', [
            'documents' => $documents,
            'spaces' => Space::orderBy('name')->get(),
            'types' => DocumentType::orderBy('name')->get(),
            'domains' => Referential::where('type', 'domain')->orderBy('name')->get(),
            'processes' => Referential::where('type', 'process')->orderBy('name')->get(),
            'filters' => $request->only(['q', 'space_id', 'type_id', 'domain_id', 'process_id', 'criticality']),
            'v02Columns' => $v02Columns,
            'selectedCols' => $selectedCols,
            'definitions' => $definitions,
            'v02' => $this->v02,
            'sort' => $sort,
            'dir' => $dir,
            'viewMode' => $view,
            'dimensions' => $dimensions,
            'group' => $group,
            'groupValue' => $value,
            'cards' => $cards,
            'activeLabel' => $activeLabel,
        ]);
    }

    /** Écran d'import CSV (registre V02) — formulaire + lien template. */
    public function importCsvForm()
    {
        $this->requireAdminReferentials();

        return view('admin.import-csv', [
            'report' => session('import_report'),
        ]);
    }

    /** Téléchargement du template CSV (en-tête + exemples). */
    public function downloadTemplate(): StreamedResponse
    {
        $this->requireAdminReferentials();

        $header = ['id', 'code', 'titre', 'description', 'section', 'lot', 'famille', 'statut', 'proprietaire', 'domaine', 'processus', 'application', 'criticite', 'date_application', 'prochaine_revue', 'fichier'];

        $rows = [
            ['021', 'KAE-GOV-POL-021-V01', 'Politique de gouvernance Groupe', 'Politique de gouvernance Groupe', 'Section 01 - Gouvernance Groupe et juridique', '01 - Gouvernance Groupe et juridique', 'Politique', 'brouillon', 'Direction Générale Groupe', 'Gouvernance', 'Gouvernance documentaire', 'site:Siège;country:Côte d\'Ivoire', 'standard', '2026-01-01', '2027-01-01', '/chemin/serveur/KAE-GOV-POL-021-V01.pdf'],
            ['022', 'KAE-GOV-MAN-022-V01', 'Manuel de gouvernance Groupe', 'Manuel de gouvernance Groupe', 'Section 01 - Gouvernance Groupe et juridique', '01 - Gouvernance Groupe et juridique', 'Manuel', 'brouillon', 'Secrétariat Général Groupe', '', '', '', 'standard', '', '', '/chemin/serveur/KAE-GOV-MAN-022-V01.docx'],
        ];

        $lines = [implode(';', $header)];
        foreach ($rows as $r) {
            $lines[] = implode(';', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $r));
        }

        return response()->streamDownload(function () use ($lines) {
            echo implode("\r\n", $lines);
        }, 'template-import-csv.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Traitement de l'import CSV : crée les documents (espace=section, dossier=lot,
     * type=famille) et attache le fichier si la colonne « fichier » pointe vers un
     * fichier existant sur le serveur (tous formats autorisés par la politique MIME).
     */
    public function importCsv(Request $request)
    {
        $this->requireAdminReferentials();

        $request->validate(['csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $content = file_get_contents($request->file('csv')->getRealPath());
        $lines = preg_split('/\r\n|\r|\n/', (string) $content);
        $lines = array_values(array_filter($lines, fn ($l) => trim((string) $l) !== ''));
        $rows = array_map(fn ($line) => str_getcsv($line, ';'), $lines);
        $header = array_map(fn ($h) => trim((string) $h), array_shift($rows) ?? []);

        $report = ['ok' => 0, 'errors' => [], 'file_errors' => [], 'created' => []];
        $seenIds = [];
        $tenantId = auth()->user()->tenant_id;
        $user = auth()->user();
        $documents = app(DocumentService::class);

        foreach ($rows as $i => $row) {
            $line = $i + 2;

            if (count($row) !== count($header)) {
                $report['errors'][] = "Ligne $line : nombre de colonnes invalide (".count($row).' au lieu de '.count($header).')';

                continue;
            }
            $data = array_combine($header, $row);

            try {
                $this->validateImportRow($data, $tenantId, $seenIds);
            } catch (\RuntimeException $e) {
                $report['errors'][] = "Ligne $line : {$e->getMessage()}";

                continue;
            }

            [$document, $createdRefs] = $this->createImportDocument($data, $tenantId, $user);
            $report['referentials_created'] = ($report['referentials_created'] ?? 0) + $createdRefs;
            $report['ok']++;
            $seenIds[$data['id']] = true;
            $report['created'][] = $document->title;

            // Attachement du fichier (optionnel) : chemin serveur, tous formats autorisés.
            if (! empty($data['fichier'])) {
                $path = trim($data['fichier']);
                if (! is_file($path)) {
                    $report['file_errors'][] = "Ligne $line ({$data['code']}) : fichier introuvable — document créé sans fichier.";
                } else {
                    try {
                        $documents->addVersion($user, $document, new UploadedFile($path, basename($path)), 'Version initiale (import CSV)');
                    } catch (\Throwable $e) {
                        $report['file_errors'][] = "Ligne $line ({$data['code']}) : fichier non attaché — ".$e->getMessage();
                    }
                }
            }
        }

        app(AuditService::class)->log('admin.import_csv', 'tenant', $tenantId, ['ok' => $report['ok'], 'errors' => count($report['errors'])]);

        return back()->with('import_report', $report)->with('success', "Import terminé : {$report['ok']} document(s), ".count($report['errors']).' erreur(s).');
    }

    private function validateImportRow(array $d, int $tenantId, array &$seenIds): void
    {
        if (empty($d['id']) || empty($d['code']) || empty($d['titre'])) {
            throw new \RuntimeException('id/code/titre obligatoires');
        }
        if (isset($seenIds[$d['id']]) || Document::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('document_code', $d['code'])->exists()) {
            throw new \RuntimeException("doublon id/code : {$d['code']}");
        }
        if (! in_array($d['statut'] ?? '', V02DocumentService::STATUSES, true)) {
            throw new \RuntimeException('statut invalide : '.($d['statut'] ?? ''));
        }
    }

    private function createImportDocument(array $d, int $tenantId, User $user): array
    {
        $space = Space::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => $d['section']],
            ['tenant_id' => $tenantId, 'name' => $d['section']]
        );
        $folder = Folder::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId, 'space_id' => $space->id, 'name' => $d['lot'] ?: 'Général'],
            ['tenant_id' => $tenantId, 'space_id' => $space->id, 'name' => $d['lot'] ?: 'Général']
        );
        $type = DocumentType::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => $d['famille']],
            ['tenant_id' => $tenantId, 'name' => $d['famille'], 'slug' => Str::slug($d['famille']).'-'.uniqid()]
        );
        $owner = User::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('name', $d['proprietaire'])->first();

        $createdRefs = 0;
        $domainId = null;
        if (! empty($d['domaine'])) {
            $domainId = V02DocumentService::ensureReferential($tenantId, 'domain', $d['domaine'])->id;
            $createdRefs++;
        }
        $processId = null;
        if (! empty($d['processus'])) {
            $processId = V02DocumentService::ensureReferential($tenantId, 'process', $d['processus'])->id;
            $createdRefs++;
        }

        $document = Document::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'space_id' => $space->id,
            'folder_id' => $folder->id,
            'document_type_id' => $type->id,
            'title' => $d['titre'],
            'description' => $d['description'] ?? null,
            'reference' => $d['id'],
            'document_code' => $d['code'],
            'owner_id' => $owner?->id,
            'domain_id' => $domainId,
            'process_id' => $processId,
            'status' => $d['statut'] ?? 'brouillon',
            'criticality' => in_array($d['criticite'] ?? '', ['standard', 'important', 'critical']) ? $d['criticite'] : 'standard',
            'effective_date' => $d['date_application'] ?: null,
            'next_review_date' => $d['prochaine_revue'] ?: null,
            'is_active_version' => ($d['statut'] ?? '') === 'approuve_applicable',
            'created_by' => $owner?->id ?? $user->id,
        ]);

        // Référentiels d'application (colonne « application » : site:Siège;country:Côte d'Ivoire).
        $pivots = [];
        foreach ($this->parseApplication((string) ($d['application'] ?? '')) as $type => $name) {
            $ref = V02DocumentService::ensureReferential($tenantId, $type, $name);
            $createdRefs++;
            $pivots[$ref->id] = ['tenant_id' => $tenantId, 'type' => $type];
        }
        if ($pivots !== []) {
            $document->referentials()->attach($pivots);
        }

        return [$document, $createdRefs];
    }

    /** Parse « site:Siège;country:Côte d'Ivoire » → [type => name]. */
    private function parseApplication(string $raw): array
    {
        $out = [];
        foreach (explode(';', $raw) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $seg = explode(':', $part, 2);
            $type = strtolower(trim($seg[0]));
            $name = trim($seg[1] ?? '');
            if ($name === '' || ! in_array($type, ['job', 'department', 'direction', 'site', 'entity', 'country'], true)) {
                continue;
            }
            $out[$type] = $name;
        }

        return $out;
    }

    /** Lot D : accuser lecture. */
    public function acknowledge(Request $request, int $document)
    {
        $document = $this->doc($document);

        try {
            $this->v02->acknowledge($document, auth()->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['ack' => $e->getMessage()]);
        }

        return back()->with('success', 'Lecture accusée.');
    }

    /** Lot A : CRUD des référentiels (admin). */
    public function referentials(Request $request)
    {
        $this->requireAdminReferentials();

        $type = $request->input('type', 'domain');

        return $this->asTenantAdmin(fn () => view('admin.referentials', [
            'type' => $type,
            'types' => Referential::TYPES,
            'items' => Referential::where('type', $type)->orderBy('name')->get(),
        ]));
    }

    public function storeReferential(Request $request)
    {
        $this->requireAdminReferentials();

        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', Referential::TYPES)],
            'name' => ['required', 'max:255'],
            'code' => ['nullable', 'max:50'],
        ]);

        $result = $this->asTenantAdmin(function () use ($data) {
            $item = V02DocumentService::ensureReferential(
                auth()->user()->tenant_id,
                $data['type'],
                $data['name'],
                $data['code'] ?? null
            );

            app(AuditService::class)->log('admin.referential.created', 'referential', $item->id, ['type' => $data['type']]);

            return $item;
        });

        return back()->with('success', 'Référentiel « '.$result->name.' » créé (ou déjà existant).');
    }

    public function updateReferential(Request $request, int $referential)
    {
        $this->requireAdminReferentials();

        $data = $request->validate([
            'name' => ['required', 'max:255'],
            'code' => ['nullable', 'max:50'],
        ]);

        $result = $this->asTenantAdmin(function () use ($referential, $data) {
            $item = Referential::findOrFail($referential);
            $item->update($data);
            app(AuditService::class)->log('admin.referential.updated', 'referential', $item->id, ['type' => $item->type]);

            return $item;
        });

        return back()->with('success', 'Référentiel mis à jour.');
    }

    public function deleteReferential(int $referential)
    {
        $this->requireAdminReferentials();

        $result = $this->asTenantAdmin(function () use ($referential) {
            $item = Referential::findOrFail($referential);

            $inUse = $item->documents()->exists()
                || Document::where('tenant_id', auth()->user()->tenant_id)
                    ->where(fn ($q) => $q->where('domain_id', $item->id)->orWhere('process_id', $item->id))
                    ->exists()
                || User::where('tenant_id', auth()->user()->tenant_id)
                    ->where(fn ($q) => $q->where('job_id', $item->id)->orWhere('department_id', $item->id)
                        ->orWhere('direction_id', $item->id)->orWhere('site_id', $item->id)
                        ->orWhere('entity_id', $item->id)->orWhere('country_id', $item->id))
                    ->exists();

            if ($inUse) {
                return back()->withErrors(['referential' => 'Ce référentiel est utilisé par des documents ou des utilisateurs.']);
            }

            $item->delete();
            app(AuditService::class)->log('admin.referential.deleted', 'referential', $item->id);

            return back()->with('success', 'Référentiel supprimé.');
        });

        return $result;
    }

    /**
     * Exécute une opération d'administration des référentiels avec le contexte
     * tenant : le middleware SetTenantContext met un contexte null pour le super
     * admin (RM-003 : pas d'accès au contenu par défaut), ce qui rendrait les
     * requêtes scopées invisibles (0=1). Le super admin gère les référentiels de
     * SON tenant de rattachement — on rétablit donc son contexte le temps de
     * l'opération (restauré quoi qu'il arrive).
     */
    private function asTenantAdmin(callable $fn)
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin()) {
            return $fn();
        }

        $previous = TenantContext::get();
        TenantContext::set($user->tenant_id);

        try {
            return $fn();
        } finally {
            TenantContext::set($previous);
        }
    }

    private function requireAdminReferentials(): void
    {
        if (! $this->permissions->can(auth()->user(), 'admin.referentials')) {
            abort(403, 'Permission administrateur requise.');
        }
    }
}
