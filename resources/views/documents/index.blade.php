@extends('layouts.app')

@section('title', __('Documents'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">{{ __('Documents') }}</h4>
    <div class="d-flex gap-2 align-items-center">
        @include('documents._view-toggle')
        @if ($canCreate)
        <a href="{{ route('documents.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Importer') }}</a>
        @endif
    </div>
</div>

<form class="card p-3 mb-3" method="GET">
    <div class="row g-2">
        <div class="col-md-3"><input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ __('Recherche par titre/référence…') }}"></div>
        <div class="col-md-2">
            <select name="space_id" class="form-select">
                <option value="">{{ __('Espace') }}</option>
                @foreach ($spaces as $s)<option value="{{ $s->id }}" @selected(($filters['space_id'] ?? '') == $s->id)>{{ $s->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="type_id" class="form-select">
                <option value="">{{ __('Type') }}</option>
                @foreach ($types as $t)<option value="{{ $t->id }}" @selected(($filters['type_id'] ?? '') == $t->id)>{{ $t->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select">
                <option value="">{{ __('Statut') }}</option>
                @foreach (['draft' => __('Brouillon'), 'in_review' => __('En revue'), 'approved' => __('Approuvé'), 'archived' => __('Archivé')] as $k => $v)
                    <option value="{{ $k }}" @selected(($filters['status'] ?? '') == $k)>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><select name="confidentiality" class="form-select">
            <option value="">{{ __('Confidentialité') }}</option>
            @foreach (['public', 'internal', 'confidential', 'secret'] as $c)<option value="{{ $c }}" @selected(($filters['confidentiality'] ?? '') == $c)>{{ $c }}</option>@endforeach
        </select></div>
        <div class="col-md-1"><button class="btn btn-outline-primary w-100"><i class="bi bi-funnel"></i></button></div>
        <input type="hidden" name="view" value="{{ $viewMode }}">
        @if ($group !== null)<input type="hidden" name="group" value="{{ $group }}">@endif
        @if ($viewMode === 'list' && $groupValue !== null)<input type="hidden" name="value" value="{{ $groupValue }}">@endif
    </div>
    @if ($definitions->isNotEmpty() || count($extraColumns) > 0)
    <div class="row mt-2">
        <div class="col-md-6">
            <details class="small">
                <summary class="form-label small mb-1" style="cursor:pointer">{{ __('Colonnes à afficher…') }}</summary>
                <div class="border rounded p-2" style="max-height:160px;overflow:auto">
                    @foreach ($extraColumns as $key => $label)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="cols[]" value="{{ $key }}" id="col-{{ $key }}" @checked(in_array($key, $selectedCols, true))>
                            <label class="form-check-label small" for="col-{{ $key }}">{{ __($label) }}</label>
                        </div>
                    @endforeach
                    @foreach ($definitions as $def)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="cols[]" value="{{ $def->id }}" id="col-{{ $def->id }}" @checked(in_array((string) $def->id, $selectedCols, true))>
                            <label class="form-check-label small" for="col-{{ $def->id }}">{{ $def->name }}</label>
                        </div>
                    @endforeach
                </div>
                <button class="btn btn-sm btn-outline-primary mt-2">{{ __('Appliquer les colonnes') }}</button>
            </details>
        </div>
    </div>
    @endif
</form>

@if ($viewMode === 'list' && $activeLabel !== null)
<div class="alert alert-light border d-flex justify-content-between align-items-center py-2 mb-3">
    <div class="small"><strong>{{ __('Regroupement :') }}</strong> {{ $dimensions[$group] ?? $group }} — {{ $activeLabel }}</div>
    <a href="{{ request()->fullUrlWithQuery(['view' => 'cards', 'value' => null]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-grid-3x3-gap-fill"></i> {{ __('Retour aux cartes') }}</a>
</div>
@endif

@if ($viewMode === 'cards')
    @include('documents._cards')
@else
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
                <th><a href="{{ $sortUrl('title') }}" class="text-decoration-none">{{ __('Titre') }}{{ $arrow('title') }}</a></th>
                <th><a href="{{ $sortUrl('space_id') }}" class="text-decoration-none">{{ __('Espace') }}{{ $arrow('space_id') }}</a></th>
                <th><a href="{{ $sortUrl('type_id') }}" class="text-decoration-none">{{ __('Type') }}{{ $arrow('type_id') }}</a></th>
                <th>{{ __('Version') }}</th>
                <th><a href="{{ $sortUrl('status') }}" class="text-decoration-none">{{ __('Statut') }}{{ $arrow('status') }}</a></th>
                <th><a href="{{ $sortUrl('updated_at') }}" class="text-decoration-none">{{ __('Mis à jour') }}{{ $arrow('updated_at') }}</a></th>
                @foreach ($extraColumns as $key => $label)
                    @if (in_array($key, $selectedCols, true))
                        <th>@if (in_array($key, ['reference', 'document_code'], true))<a href="{{ $sortUrl($key) }}" class="text-decoration-none">{{ __($label) }}{{ $arrow($key) }}</a>@else{{ __($label) }}@endif</th>
                    @endif
                @endforeach
                @foreach ($definitions as $def)
                    @if (in_array((string) $def->id, $selectedCols, true))
                        <th><a href="{{ $sortUrl('meta:'.$def->id) }}" class="text-decoration-none">{{ $def->name }}{{ $arrow('meta:'.$def->id) }}</a></th>
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
                <td><a href="{{ route('documents.show', $doc) }}" class="text-decoration-none fw-semibold">{{ $doc->title }}</a></td>
                <td>{{ $doc->space->name ?? '—' }}</td>
                <td>{{ $doc->type->name ?? '—' }}</td>
                <td>@if ($doc->currentVersion)v{{ $doc->currentVersion->version }}@else<span class="badge bg-warning text-dark">sans fichier</span>@endif</td>
                <td><span class="badge bg-{{ $doc->status === 'approved' ? 'success' : ($doc->status === 'archived' ? 'secondary' : 'info') }}">{{ $doc->statusLabel() }}</span></td>
                <td class="small text-muted">{{ $doc->updated_at->diffForHumans() }}</td>
                @foreach ($extraColumns as $key => $label)
                    @if (in_array($key, $selectedCols, true))
                        @php
                            $value = match ($key) {
                                'reference' => $doc->reference,
                                'document_code' => $doc->document_code,
                                'domain' => $doc->domain?->name,
                                'process' => $doc->process?->name,
                                default => $doc->referentials->firstWhere('type', substr($key, 4))?->name,
                            };
                        @endphp
                        <td class="small">{{ $value ?? '—' }}</td>
                    @endif
                @endforeach
                @foreach ($definitions as $def)
                    @if (in_array((string) $def->id, $selectedCols, true))
                        <td class="small">{{ $values[$def->id]->value ?? '—' }}</td>
                    @endif
                @endforeach
                <td><a href="{{ route('documents.show', $doc) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
            </tr>
        @empty
            <tr><td colspan="{{ 7 + count($selectedCols) }}" class="text-center text-muted py-4">{{ __('Aucun document. Importer un premier document.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $documents->links() }}</div>
@endif
@endsection
