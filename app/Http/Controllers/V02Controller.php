<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Referential;
use App\Models\Space;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Services\V02DocumentService;
use Illuminate\Http\Request;

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

        $documents = $query->orderByDesc('updated_at')->paginate(config('ged.pagination'));

        return view('v02.my-documents', [
            'documents' => $documents,
            'spaces' => Space::orderBy('name')->get(),
            'types' => DocumentType::orderBy('name')->get(),
            'domains' => Referential::where('type', 'domain')->orderBy('name')->get(),
            'processes' => Referential::where('type', 'process')->orderBy('name')->get(),
            'filters' => $request->only(['q', 'space_id', 'type_id', 'domain_id', 'process_id', 'criticality']),
            'v02' => $this->v02,
        ]);
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

        return view('admin.referentials', [
            'type' => $type,
            'types' => Referential::TYPES,
            'items' => Referential::where('type', $type)->orderBy('name')->get(),
        ]);
    }

    public function storeReferential(Request $request)
    {
        $this->requireAdminReferentials();

        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', Referential::TYPES)],
            'name' => ['required', 'max:255'],
            'code' => ['nullable', 'max:50'],
        ]);

        $item = V02DocumentService::ensureReferential(
            auth()->user()->tenant_id,
            $data['type'],
            $data['name'],
            $data['code'] ?? null
        );

        app(AuditService::class)->log('admin.referential.created', 'referential', $item->id, ['type' => $data['type']]);

        return back()->with('success', 'Référentiel « '.$item->name.' » créé (ou déjà existant).');
    }

    public function updateReferential(Request $request, int $referential)
    {
        $this->requireAdminReferentials();

        $item = Referential::findOrFail($referential);

        $data = $request->validate([
            'name' => ['required', 'max:255'],
            'code' => ['nullable', 'max:50'],
        ]);

        $item->update($data);
        app(AuditService::class)->log('admin.referential.updated', 'referential', $item->id, ['type' => $item->type]);

        return back()->with('success', 'Référentiel mis à jour.');
    }

    public function deleteReferential(int $referential)
    {
        $this->requireAdminReferentials();

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
    }

    private function requireAdminReferentials(): void
    {
        if (! $this->permissions->can(auth()->user(), 'admin.referentials')) {
            abort(403, 'Permission administrateur requise.');
        }
    }
}
