@extends('layouts.app')

@section('title', 'Recherche')

@section('content')
<h4 class="mb-3">Recherche</h4>
<form method="GET" class="card p-3 mb-3">
    <div class="row g-2">
        <div class="col-md-4"><input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Titre, référence, contenu…"></div>
        <div class="col-md-2">
            <select name="type_id" class="form-select">
                <option value="">Type</option>
                @foreach ($types as $t)<option value="{{ $t->id }}" @selected(($filters['type_id'] ?? '') == $t->id)>{{ $t->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select">
                <option value="">Statut</option>
                @foreach (['draft' => 'Brouillon', 'in_review' => 'En revue', 'approved' => 'Approuvé', 'archived' => 'Archivé'] as $k => $v)
                    <option value="{{ $k }}" @selected(($filters['status'] ?? '') == $k)>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="tag" class="form-select">
                <option value="">Tag</option>
                @foreach ($tags as $t)<option value="{{ $t->name }}" @selected(($filters['tag'] ?? '') == $t->name)>{{ $t->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2"><div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="fulltext" value="1" id="ft" @checked($filters['fulltext'] ?? false)>
            <label class="form-check-label small" for="ft">Plein texte</label>
        </div></div>
        <div class="col-12"><button class="btn btn-primary"><i class="bi bi-search"></i> Rechercher</button></div>
    </div>
</form>

<div class="card">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Document</th><th>Type</th><th>Statut</th><th>Version</th><th>Mis à jour</th><th></th></tr></thead>
        <tbody>
        @forelse ($documents as $doc)
            <tr>
                <td><a href="{{ route('documents.show', $doc) }}" class="text-decoration-none fw-semibold">{{ $doc->title }}</a>
                    @if ($doc->reference)<div class="small text-muted">{{ $doc->reference }}</div>@endif
                </td>
                <td>{{ $doc->type->name ?? '—' }}</td>
                <td><span class="badge bg-info">{{ $doc->status }}</span></td>
                <td>v{{ $doc->currentVersion->version ?? '—' }}</td>
                <td class="small text-muted">{{ $doc->updated_at->diffForHumans() }}</td>
                <td><a href="{{ route('documents.show', $doc) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-4">Aucun résultat. La recherche ne retourne que les documents accessibles.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $documents->links() }}</div>
@endsection
