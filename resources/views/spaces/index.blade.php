@extends('layouts.app')

@section('title', 'Espaces et dossiers')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Espaces et dossiers</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newSpace"><i class="bi bi-plus-lg"></i> Espace</button>
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
                    <form method="POST" action="{{ route('spaces.rename', $space) }}" class="d-flex gap-1 mb-2">
                        @csrf
                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $space->name }}" required maxlength="255">
                        <button class="btn btn-sm btn-outline-secondary" title="Renommer l'espace"><i class="bi bi-pencil"></i></button>
                    </form>
                    @if ($space->description)<p class="small text-muted">{{ $space->description }}</p>@endif

                    <ul class="list-unstyled small mb-2">
                        @foreach ($space->folders as $folder)
                            <li class="d-flex justify-content-between py-1 border-bottom">
                                <span><i class="bi bi-folder"></i> {{ $folder->name }}
                                    <span class="text-muted">({{ $folder->documents_count }} doc.)</span></span>
                                <form method="POST" action="{{ route('folders.delete', $folder) }}">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-link text-danger p-0" title="Supprimer">✕</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>

                    <form method="POST" action="{{ route('folders.store', $space) }}" class="d-flex gap-2">
                        @csrf
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="Nouveau dossier" required>
                        <button class="btn btn-sm btn-outline-primary">Ajouter</button>
                    </form>

                    <div class="text-end mt-2">
                        <form method="POST" action="{{ route('spaces.delete', $space) }}" class="d-inline">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-link text-danger" onclick="return confirm('Supprimer cet espace ?')">Supprimer l'espace</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12"><div class="card p-4 text-center text-muted">Aucun espace. Créez-en un pour structurer vos documents.</div></div>
    @endforelse
</div>

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
@endsection
