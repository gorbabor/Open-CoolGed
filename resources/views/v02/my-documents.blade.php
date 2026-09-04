@extends('layouts.app')

@section('title', __('Documents applicables à mon poste'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">{{ __('Documents applicables à mon poste') }}</h4>
    <div class="d-flex gap-2 align-items-center">
        @include('documents._view-toggle')
        <span class="badge bg-success">{{ __('Version active · Approuvés') }}</span>
    </div>
</div>

<form method="GET" class="card p-3 mb-3">
    <div class="row g-2">
        <div class="col-md-3"><input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ __('Titre, code, référence…') }}"></div>
        <div class="col-md-2">
            <select name="space_id" class="form-select">
                <option value="">{{ __('Section') }}</option>
                @foreach ($spaces as $s)<option value="{{ $s->id }}" @selected(($filters['space_id'] ?? '') == $s->id)>{{ $s->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="type_id" class="form-select">
                <option value="">{{ __('Famille') }}</option>
                @foreach ($types as $t)<option value="{{ $t->id }}" @selected(($filters['type_id'] ?? '') == $t->id)>{{ $t->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="domain_id" class="form-select">
                <option value="">{{ __('Domaine') }}</option>
                @foreach ($domains as $d)<option value="{{ $d->id }}" @selected(($filters['domain_id'] ?? '') == $d->id)>{{ $d->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="criticality" class="form-select">
                <option value="">{{ __('Criticité') }}</option>
                @foreach (['standard', 'important', 'critical'] as $c)<option value="{{ $c }}" @selected(($filters['criticality'] ?? '') == $c)>{{ $c }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-1"><button class="btn btn-outline-primary w-100"><i class="bi bi-funnel"></i></button></div>
        <input type="hidden" name="view" value="{{ $viewMode }}">
        @if ($group !== null)<input type="hidden" name="group" value="{{ $group }}">@endif
        @if ($viewMode === 'list' && $groupValue !== null)<input type="hidden" name="value" value="{{ $groupValue }}">@endif
    </div>
    @if ($viewMode === 'list')
    <div class="row mt-2">
        <div class="col-md-6">
            <details class="small">
                <summary class="form-label small mb-1" style="cursor:pointer">{{ __('Colonnes à afficher…') }}</summary>
                <div class="border rounded p-2" style="max-height:180px;overflow:auto">
                    @foreach ($v02Columns as $key => $label)
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="cols[]" value="{{ $key }}" id="v02-col-{{ str_replace(':', '-', $key) }}" @checked(in_array($key, $selectedCols, true))><label class="form-check-label small" for="v02-col-{{ str_replace(':', '-', $key) }}">{{ __($label) }}</label></div>
                    @endforeach
                    @foreach ($definitions as $def)
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="cols[]" value="{{ $def->id }}" id="v02-col-{{ $def->id }}" @checked(in_array((string) $def->id, $selectedCols, true))><label class="form-check-label small" for="v02-col-{{ $def->id }}">{{ $def->name }}</label></div>
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
    <a href="{{ request()->fullUrlWithQuery(['view' => 'cards', 'value' => null]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-grid-3x3-gap-fill"></i> Retour aux cartes</a>
</div>
@endif

@if ($viewMode === 'cards')
    @include('documents._cards')
@else
<div class="card">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            @php
                $sortUrl = fn ($col) => route('v02.my-documents', array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => $col, 'dir' => $sort === $col && $dir === 'asc' ? 'desc' : 'asc']));
                $arrow = fn ($col) => $sort === $col ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
            @endphp
            <tr>
                <th><a href="{{ $sortUrl('document_code') }}" class="text-decoration-none">Code{{ $arrow('document_code') }}</a></th><th>Titre</th><th>Famille</th><th>Section</th><th>Version</th><th><a href="{{ $sortUrl('effective_date') }}" class="text-decoration-none">Application{{ $arrow('effective_date') }}</a></th><th><a href="{{ $sortUrl('next_review_date') }}" class="text-decoration-none">Prochaine revue{{ $arrow('next_review_date') }}</a></th><th><a href="{{ $sortUrl('owner') }}" class="text-decoration-none">Propriétaire{{ $arrow('owner') }}</a></th><th><a href="{{ $sortUrl('criticality') }}" class="text-decoration-none">Criticité{{ $arrow('criticality') }}</a></th>
                @foreach ($v02Columns as $key => $label)
                    @if (in_array($key, $selectedCols, true))
                        <th>@if (in_array($key, ['reference', 'domain', 'process'], true))<a href="{{ $sortUrl($key) }}" class="text-decoration-none">{{ __($label) }}{{ $arrow($key) }}</a>@else{{ __($label) }}@endif</th>
                    @endif
                @endforeach
                @foreach ($definitions as $def)
                    @if (in_array((string) $def->id, $selectedCols, true))<th><a href="{{ $sortUrl('meta:'.$def->id) }}" class="text-decoration-none">{{ $def->name }}{{ $arrow('meta:'.$def->id) }}</a></th>@endif
                @endforeach
                <th></th>
            </tr>
        </thead>
        <tbody>
        @forelse ($documents as $doc)
            <tr>
                <td class="small"><code>{{ $doc->document_code ?: ($doc->reference ?? '—') }}</code></td>
                <td><a href="{{ route('documents.show', $doc) }}" class="text-decoration-none fw-semibold">{{ $doc->title }}</a></td>
                <td>{{ $doc->type->name ?? '—' }}</td>
                <td class="small">{{ $doc->space->name ?? '—' }}</td>
                <td>v{{ $doc->currentVersion->version ?? '—' }}</td>
                <td class="small">{{ $doc->effective_date?->format('d/m/Y') ?? '—' }}</td>
                <td class="small">
                    {{ $doc->next_review_date?->format('d/m/Y') ?? '—' }}
                    @if ($doc->next_review_date && $doc->next_review_date->isPast())
                        <span class="badge bg-danger">en retard</span>
                    @elseif ($doc->next_review_date && $doc->next_review_date->diffInDays(now()) <= 30)
                        <span class="badge bg-warning">proche</span>
                    @endif
                </td>
                <td class="small">{{ $doc->owner->name ?? '—' }}</td>
                <td><span class="badge bg-{{ $doc->criticality === 'critical' ? 'danger' : ($doc->criticality === 'important' ? 'warning' : 'secondary') }}">{{ $doc->criticality }}</span></td>
                @php $metaValues = $doc->metadataValues->keyBy('definition_id'); @endphp
                @foreach ($v02Columns as $key => $label)
                    @if (in_array($key, $selectedCols, true))
                        @php $columnValue = match ($key) {
                            'reference' => $doc->reference,
                            'domain' => $doc->domain?->name,
                            'process' => $doc->process?->name,
                            'effective_date' => $doc->effective_date?->format('d/m/Y'),
                            'next_review_date' => $doc->next_review_date?->format('d/m/Y'),
                            'owner' => $doc->owner?->name,
                            'criticality' => $doc->criticality,
                            default => str_starts_with($key, 'ref:') ? $doc->referentials->firstWhere('type', substr($key, 4))?->name : null,
                        }; @endphp
                        <td class="small">{{ $columnValue ?? '—' }}</td>
                    @endif
                @endforeach
                @foreach ($definitions as $def)
                    @if (in_array((string) $def->id, $selectedCols, true))<td class="small">{{ $metaValues[$def->id]->value ?? '—' }}</td>@endif
                @endforeach
                <td class="text-end">
                    <div class="d-flex gap-1 justify-content-end">
                        <a href="{{ route('documents.show', $doc) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                        @if ($doc->read_ack_required && !$v02->hasAcknowledged($doc, auth()->user()))
                            <form method="POST" action="{{ route('v02.acknowledge', $doc) }}">@csrf
                                <button class="btn btn-sm btn-outline-success" title="Accuser lecture"><i class="bi bi-check2-square"></i> Accuser</button>
                            </form>
                        @elseif ($doc->read_ack_required)
                            <span class="badge bg-success align-self-center">lu</span>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="10" class="text-center text-muted py-4">Aucun document applicable à votre poste.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $documents->links() }}</div>
@endif
@endsection
