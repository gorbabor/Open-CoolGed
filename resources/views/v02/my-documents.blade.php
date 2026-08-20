@extends('layouts.app')

@section('title', 'Documents applicables à mon poste')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Documents applicables à mon poste</h4>
    <span class="badge bg-success">Version active · Approuvés</span>
</div>

<form method="GET" class="card p-3 mb-3">
    <div class="row g-2">
        <div class="col-md-3"><input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Titre, code, référence…"></div>
        <div class="col-md-2">
            <select name="space_id" class="form-select">
                <option value="">Section</option>
                @foreach ($spaces as $s)<option value="{{ $s->id }}" @selected(($filters['space_id'] ?? '') == $s->id)>{{ $s->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="type_id" class="form-select">
                <option value="">Famille</option>
                @foreach ($types as $t)<option value="{{ $t->id }}" @selected(($filters['type_id'] ?? '') == $t->id)>{{ $t->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="domain_id" class="form-select">
                <option value="">Domaine</option>
                @foreach ($domains as $d)<option value="{{ $d->id }}" @selected(($filters['domain_id'] ?? '') == $d->id)>{{ $d->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="criticality" class="form-select">
                <option value="">Criticité</option>
                @foreach (['standard', 'important', 'critical'] as $c)<option value="{{ $c }}" @selected(($filters['criticality'] ?? '') == $c)>{{ $c }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-1"><button class="btn btn-outline-primary w-100"><i class="bi bi-funnel"></i></button></div>
    </div>
</form>

<div class="card">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr><th>Code</th><th>Titre</th><th>Famille</th><th>Section</th><th>Version</th><th>Application</th><th>Prochaine revue</th><th>Propriétaire</th><th>Criticité</th><th></th></tr>
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
@endsection
