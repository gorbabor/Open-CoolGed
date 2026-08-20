@extends('layouts.app')

@section('title', 'Administration — Groupes')

@section('content')
<h4 class="mb-3">Groupes</h4>

<div class="card p-3 mb-3">
    <form method="POST" action="{{ route('admin.groups.store') }}" class="d-flex gap-2">
        @csrf
        <input type="text" name="name" class="form-control" placeholder="Nom du groupe (ex: Direction, RH, Projets…)" required>
        <button class="btn btn-primary">Créer</button>
    </form>
</div>

@foreach ($groups as $g)
    <div class="card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="mb-0">{{ $g->name }} <span class="badge bg-light text-dark">{{ $g->users_count }} membres</span></h6>
            <div class="d-flex gap-1">
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editGroup{{ $g->id }}">Renommer</button>
                <form method="POST" action="{{ route('admin.groups.delete', $g) }}"
                      onsubmit="return confirm('Supprimer ce groupe ? Les utilisateurs ne sont pas supprimés.')">@csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger">Supprimer</button>
                </form>
            </div>
        </div>
        <div class="small text-muted mb-2">Membres : {{ $g->users->pluck('name')->implode(', ') ?: '—' }}</div>
        <form method="POST" action="{{ route('admin.groups.members', $g) }}">
            @csrf
            <div class="d-flex gap-2">
                <select name="user_ids[]" class="form-select form-select-sm" multiple size="4">
                    @foreach ($users as $u)<option value="{{ $u->id }}" @selected($g->users->contains('id', $u->id))>{{ $u->name }}</option>@endforeach
                </select>
                <button class="btn btn-sm btn-primary align-self-start">Mettre à jour</button>
            </div>
        </form>
        <hr class="my-2">
        <div class="small fw-semibold mb-1">Rôles du groupe <span class="text-muted fw-normal">(hérités par les membres)</span></div>
        <form method="POST" action="{{ route('admin.groups.roles', $g) }}">
            @csrf
            <div class="d-flex gap-2 align-items-start">
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($roles as $r)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="role_ids[]" value="{{ $r->id }}" id="gr{{ $g->id }}-{{ $r->id }}"
                                @checked($g->roles->contains('id', $r->id))>
                            <label class="form-check-label small" for="gr{{ $g->id }}-{{ $r->id }}">{{ $r->name }}</label>
                        </div>
                    @endforeach
                </div>
                <button class="btn btn-sm btn-outline-primary align-self-start">Enregistrer les rôles</button>
            </div>
        </form>
    </div>
    <div class="modal fade" id="editGroup{{ $g->id }}">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('admin.groups.update', $g) }}" class="modal-content">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Renommer le groupe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="text" name="name" class="form-control" value="{{ $g->name }}" required>
                </div>
                <div class="modal-footer"><button class="btn btn-primary">Enregistrer</button></div>
            </form>
        </div>
    </div>
@endforeach
@endsection
