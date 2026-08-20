@extends('layouts.app')

@section('title', 'Administration — Types documentaires')

@section('content')
<h4 class="mb-3">Types documentaires et métadonnées</h4>

<div class="row">
    <div class="col-md-6">
        <div class="card p-3 mb-3">
            <h6>Nouveau type documentaire</h6>
            <form method="POST" action="{{ route('admin.types.store') }}">
                @csrf
                <div class="mb-2"><input type="text" name="name" class="form-control" placeholder="Nom (ex: Contrat, Facture…)" required></div>
                <div class="mb-2"><input type="number" name="retention_days" class="form-control" placeholder="Rétention (jours, optionnel)"></div>
                <button class="btn btn-primary btn-sm">Créer</button>
            </form>
        </div>
        <div class="card p-3">
            <h6>Nouveau champ de métadonnée</h6>
            <form method="POST" action="{{ route('admin.metadata.store') }}">
                @csrf
                <div class="mb-2"><input type="text" name="name" class="form-control" placeholder="Nom du champ (ex: Montant HT)" required></div>
                <div class="mb-2">
                    <select name="type" class="form-select">
                        @foreach (['text', 'longtext', 'number', 'date', 'boolean', 'list', 'multiselect', 'user'] as $t)
                            <option value="{{ $t }}">{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-2"><input type="text" name="options" class="form-control" placeholder="Options (virgule, pour list)"></div>
                <div class="form-check mb-2"><input type="checkbox" name="required" value="1" class="form-check-input" id="req"><label class="form-check-label" for="req">Obligatoire</label></div>
                <button class="btn btn-primary btn-sm">Créer</button>
            </form>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card p-3">
            <h6>Types existants</h6>
            <table class="table table-sm align-middle">
                <thead><tr><th>Nom</th><th>Rétention</th><th>Workflows</th><th></th></tr></thead>
                <tbody>
                @foreach ($types as $t)
                    <tr>
                        <td>{{ $t->name }}</td>
                        <td>{{ $t->retention_days ? $t->retention_days.' j' : '—' }}</td>
                        <td class="small">{{ $t->workflows->pluck('name')->implode(', ') ?: '—' }}</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editType{{ $t->id }}">Modifier</button>
                            <form method="POST" action="{{ route('admin.types.delete', $t) }}" class="d-inline"
                                  onsubmit="return confirm('Supprimer ce type ?')">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Suppr.</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card p-3 mt-3">
            <h6>Champs de métadonnées</h6>
            <ul class="list-unstyled small mb-0">
                @foreach ($definitions as $d)
                    <li class="py-1 border-bottom d-flex justify-content-between align-items-center">
                        <span>{{ $d->name }} <span class="text-muted">({{ $d->type }}{{ $d->required ? ', requis' : '' }})</span></span>
                        <span class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editDef{{ $d->id }}">Modifier</button>
                            <form method="POST" action="{{ route('admin.metadata.delete', $d) }}"
                                  onsubmit="return confirm('Supprimer ce champ ?')">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Suppr.</button>
                            </form>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>

@foreach ($types as $t)
    <div class="modal fade" id="editType{{ $t->id }}">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('admin.types.update', $t) }}" class="modal-content">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Modifier le type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label small">Nom</label>
                        <input type="text" name="name" class="form-control" value="{{ $t->name }}" required></div>
                    <div class="mb-2"><label class="form-label small">Rétention (jours, vide = illimité)</label>
                        <input type="number" name="retention_days" class="form-control" value="{{ $t->retention_days }}"></div>
                </div>
                <div class="modal-footer"><button class="btn btn-primary">Enregistrer</button></div>
            </form>
        </div>
    </div>
@endforeach

@foreach ($definitions as $d)
    <div class="modal fade" id="editDef{{ $d->id }}">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('admin.metadata.update', $d) }}" class="modal-content">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Modifier le champ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label small">Nom</label>
                        <input type="text" name="name" class="form-control" value="{{ $d->name }}" required></div>
                    <div class="mb-2"><label class="form-label small">Type</label>
                        <select name="type" class="form-select">
                            @foreach (['text', 'longtext', 'number', 'date', 'boolean', 'list', 'multiselect', 'user'] as $t)
                                <option value="{{ $t }}" @selected($d->type === $t)>{{ $t }}</option>
                            @endforeach
                        </select></div>
                    <div class="mb-2"><label class="form-label small">Options (virgule, pour list)</label>
                        <input type="text" name="options" class="form-control" value="{{ is_array($d->options) ? implode(',', $d->options) : '' }}"></div>
                    <div class="form-check"><input type="checkbox" name="required" value="1" class="form-check-input" id="req{{ $d->id }}" @checked($d->required)>
                        <label class="form-check-label" for="req{{ $d->id }}">Obligatoire</label></div>
                </div>
                <div class="modal-footer"><button class="btn btn-primary">Enregistrer</button></div>
            </form>
        </div>
    </div>
@endforeach
@endsection
