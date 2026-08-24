@extends('layouts.app')

@section('title', 'Documents')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Documents</h4>
    <a href="{{ route('documents.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Importer</a>
</div>

<form class="card p-3 mb-3" method="GET">
    <div class="row g-2">
        <div class="col-md-3"><input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Recherche par titre/référence…"></div>
        <div class="col-md-2">
            <select name="space_id" class="form-select">
                <option value="">Espace</option>
                @foreach ($spaces as $s)<option value="{{ $s->id }}" @selected(($filters['space_id'] ?? '') == $s->id)>{{ $s->name }}</option>@endforeach
            </select>
        </div>
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
        <div class="col-md-2"><select name="confidentiality" class="form-select">
            <option value="">Confidentialité</option>
            @foreach (['public', 'internal', 'confidential', 'secret'] as $c)<option value="{{ $c }}" @selected(($filters['confidentiality'] ?? '') == $c)>{{ $c }}</option>@endforeach
        </select></div>
        <div class="col-md-1"><button class="btn btn-outline-primary w-100"><i class="bi bi-funnel"></i></button></div>
    </div>
    @if ($definitions->isNotEmpty())
    <div class="row mt-2">
        <div class="col-md-6">
            <details class="small">
                <summary class="form-label small mb-1" style="cursor:pointer">Colonnes métadonnées à afficher…</summary>
                <div class="border rounded p-2" style="max-height:160px;overflow:auto">
                    @foreach ($definitions as $def)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="cols[]" value="{{ $def->id }}" id="col-{{ $def->id }}"
                                @checked(in_array($def->id, $selectedCols))>
                            <label class="form-check-label small" for="col-{{ $def->id }}">{{ $def->name }}</label>
                        </div>
                    @endforeach
                </div>
                <button class="btn btn-sm btn-outline-primary mt-2">Appliquer les colonnes</button>
            </details>
        </div>
    </div>
    @endif
</form>

<div class="card">
    @php
        $sortUrl = fn ($col) => route('documents.index', array_merge(
            request()->except(['sort', 'dir', 'page']),
            ['sort' => $col, 'dir' => $sort === $col && $dir === 'asc' ? 'desc' : 'asc'],
        ));
        $arrow = fn ($col) => $sort === $col ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    @endphp
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th><a href="{{ $sortUrl('title') }}" class="text-decoration-none">Titre{{ $arrow('title') }}</a></th>
                <th><a href="{{ $sortUrl('space_id') }}" class="text-decoration-none">Espace{{ $arrow('space_id') }}</a></th>
                <th><a href="{{ $sortUrl('type_id') }}" class="text-decoration-none">Type{{ $arrow('type_id') }}</a></th>
                <th>Version</th>
                <th><a href="{{ $sortUrl('status') }}" class="text-decoration-none">Statut{{ $arrow('status') }}</a></th>
                <th><a href="{{ $sortUrl('updated_at') }}" class="text-decoration-none">Mis à jour{{ $arrow('updated_at') }}</a></th>
                @foreach ($definitions as $def)
                    @if (in_array($def->id, $selectedCols))
                        <th>{{ $def->name }}</th>
                    @endif
                @endforeach
                <th></th>
            </tr>
        </thead>
        <tbody>
        @forelse ($documents as $doc)
            @php
                $values = $doc->metadataValues->keyBy('definition_id');
            @endphp
            <tr>
                <td><a href="{{ route('documents.show', $doc) }}" class="text-decoration-none fw-semibold">{{ $doc->title }}</a>
                    @if ($doc->reference)<div class="small text-muted">{{ $doc->reference }}</div>@endif
                </td>
                <td>{{ $doc->space->name ?? '—' }}</td>
                <td>{{ $doc->type->name ?? '—' }}</td>
                <td>v{{ $doc->currentVersion->version ?? '—' }}</td>
                <td><span class="badge bg-{{ $doc->status === 'approved' ? 'success' : ($doc->status === 'archived' ? 'secondary' : 'info') }}">{{ $doc->statusLabel() }}</span></td>
                <td class="small text-muted">{{ $doc->updated_at->diffForHumans() }}</td>
                @foreach ($definitions as $def)
                    @if (in_array($def->id, $selectedCols))
                        <td class="small">{{ $values[$def->id]->value ?? '—' }}</td>
                    @endif
                @endforeach
                <td><a href="{{ route('documents.show', $doc) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
            </tr>
        @empty
            <tr><td colspan="{{ 7 + count($selectedCols) }}" class="text-center text-muted py-4">Aucun document. <a href="{{ route('documents.create') }}">Importer un premier document</a>.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $documents->links() }}</div>
@endsection
