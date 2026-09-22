@extends('layouts.app')

@section('title', 'Espaces et dossiers')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Espaces et dossiers</h4>
    @if ($canManage)
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newSpace"><i class="bi bi-plus-lg"></i> Espace</button>
    @endif
</div>

<div class="row">
    @forelse ($spaces as $space)
        <div class="col-md-6 col-lg-4 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="d-flex justify-content-between">
                        <span><i class="bi bi-collection" style="color:{{ $space->color }}"></i> {{ $space->name }}</span>
                        <span class="badge bg-light text-dark">{{ $space->documents_count }} doc.</span>
                    </h6>
                    @if ($canManage)
                    <form method="POST" action="{{ route('spaces.rename', $space) }}" class="d-flex gap-1 mb-2">
                        @csrf
                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $space->name }}" required maxlength="255">
                        <button class="btn btn-sm btn-outline-secondary" title="Renommer l'espace"><i class="bi bi-pencil"></i></button>
                    </form>
                    @endif
                    @if ($space->description)<p class="small text-muted">{{ $space->description }}</p>@endif

                    <ul class="list-unstyled small mb-2">
                        @include('spaces._tree', [
                            'nodes' => $roots[$space->id] ?? collect(),
                            'tree' => $tree,
                            'space' => $space,
                            'maxDepth' => $maxDepth,
                            'canManage' => $canManage,
                        ])
                    </ul>

                    @if ($canManage)
                    <form method="POST" action="{{ route('folders.store', $space) }}" class="d-flex gap-2">
                        @csrf
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="Nouveau dossier" required>
                        <select name="parent_id" class="form-select form-select-sm" style="max-width:180px">
                            <option value="">— Racine —</option>
                            @foreach (($spaceFolders[$space->id] ?? collect()) as $candidate)
                                @if ($candidate->depth() < $maxDepth)
                                <option value="{{ $candidate->id }}">{{ str_repeat('— ', $candidate->depth() - 1) }}{{ $candidate->name }}</option>
                                @endif
                            @endforeach
                        </select>
                        <button class="btn btn-sm btn-outline-primary">Ajouter</button>
                    </form>
                    @endif

                    @if ($canManage)
                    <div class="text-end mt-2">
                        <form method="POST" action="{{ route('spaces.delete', $space) }}" class="d-inline">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-link text-danger" onclick="return confirm('Supprimer cet espace ?')">Supprimer l'espace</button>
                        </form>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="col-12"><div class="card p-4 text-center text-muted">{{ $canManage ? 'Aucun espace. Créez-en un pour structurer vos documents.' : 'Aucun espace pour le moment.' }}</div></div>
    @endforelse
</div>

@if ($canManage)
<div class="modal fade" id="newSpace">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('spaces.store') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Nouvel espace</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Nom *</label>
                    <input type="text" name="name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"></textarea></div>
                <div class="mb-3"><label class="form-label">Couleur</label>
                    <input type="color" name="color" class="form-control form-control-color" value="#0d6efd"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Créer</button></div>
        </form>
    </div>
</div>
@endif
@endsection
