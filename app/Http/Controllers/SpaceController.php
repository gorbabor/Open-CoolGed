<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\Space;
use App\Services\AuditService;
use App\Services\PermissionService;
use Illuminate\Http\Request;

class SpaceController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private PermissionService $permissions,
    ) {}

    private function requireSpaceAdmin(): void
    {
        if (! $this->permissions->can(auth()->user(), 'admin.spaces')) {
            abort(403, 'Permission administrateur requise.');
        }
    }

    public function index()
    {
        $spaces = Space::where('is_personal', false)
            ->withCount('documents')
            ->with(['folders.documents'])
            ->orderBy('name')
            ->get();

        return view('spaces.index', [
            'spaces' => $spaces,
            'canManage' => $this->permissions->can(auth()->user(), 'admin.spaces'),
        ]);
    }

    public function store(Request $request)
    {
        $this->requireSpaceAdmin();

        $data = $request->validate([
            'name' => ['required', 'max:255'],
            'description' => ['nullable'],
            'color' => ['nullable', 'max:7'],
        ]);

        $space = Space::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? '#0d6efd',
        ]);

        $this->audit->log('space.created', 'space', $space->id);

        return back()->with('success', 'Espace créé.');
    }

    public function addFolder(Request $request, int $space)
    {
        $this->requireSpaceAdmin();

        $space = $this->space($space);

        $data = $request->validate([
            'name' => ['required', 'max:255'],
            'parent_id' => ['nullable', 'exists:folders,id'],
        ]);

        $folder = Folder::create([
            'tenant_id' => auth()->user()->tenant_id,
            'space_id' => $space->id,
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
        ]);

        $this->audit->log('folder.created', 'folder', $folder->id);

        return back()->with('success', 'Dossier créé.');
    }

    public function renameFolder(Request $request, int $folder)
    {
        $this->requireSpaceAdmin();

        $folder = $this->folder($folder);
        $folder->update(['name' => $request->validate(['name' => ['required', 'max:255']])['name']]);
        $this->audit->log('folder.renamed', 'folder', $folder->id);

        return back()->with('success', 'Dossier renommé.');
    }

    public function deleteFolder(int $folder)
    {
        $this->requireSpaceAdmin();

        $folder = $this->folder($folder);

        if ($folder->documents()->count() > 0 || $folder->children()->count() > 0) {
            return back()->withErrors(['folder' => 'Le dossier contient encore des éléments.']);
        }

        $folder->delete();
        $this->audit->log('folder.deleted', 'folder', $folder->id);

        return back()->with('success', 'Dossier supprimé.');
    }

    public function renameSpace(Request $request, int $space)
    {
        $this->requireSpaceAdmin();

        $space = $this->space($space);
        $this->guardPersonal($space);

        $space->update(['name' => $request->validate(['name' => ['required', 'max:255']])['name']]);
        $this->audit->log('space.renamed', 'space', $space->id);

        return back()->with('success', 'Espace renommé.');
    }

    public function destroy(int $space)
    {
        $this->requireSpaceAdmin();

        $space = $this->space($space);
        $this->guardPersonal($space);

        if ($space->documents()->count() > 0 || $space->folders()->count() > 0) {
            return back()->withErrors(['space' => 'L\'espace contient encore des éléments.']);
        }

        $space->delete();
        $this->audit->log('space.deleted', 'space', $space->id);

        return back()->with('success', 'Espace supprimé.');
    }

    private function guardPersonal(Space $space): void
    {
        if ($space->is_personal) {
            abort(403, 'Espace personnel géré automatiquement.');
        }
    }
}
