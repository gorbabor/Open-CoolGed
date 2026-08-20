@extends('layouts.app')

@section('title', 'Administration — Audit')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Journal d'audit</h4>
    <a href="{{ route('admin.audit.export', request()->query()) }}" class="btn btn-outline-success">
        <i class="bi bi-download"></i> Exporter CSV</a>
</div>

<form method="GET" class="card p-3 mb-3">
    <div class="row g-2">
        <div class="col-md-4"><input type="text" name="action" value="{{ request('action') }}" class="form-control" placeholder="Action (ex: document, workflow, admin…)"></div>
        <div class="col-md-3">
            <select name="user_id" class="form-select">
                <option value="">Tous les utilisateurs</option>
                @foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2"><input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}" title="Du"></div>
        <div class="col-md-2"><input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}" title="Au"></div>
        <div class="col-md-1"><button class="btn btn-outline-primary w-100">Filtrer</button></div>
    </div>
</form>

<div class="card">
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Ressource</th><th>Détails</th></tr></thead>
        <tbody>
        @forelse ($logs as $log)
            <tr>
                <td class="small text-nowrap">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                <td class="small">{{ $log->user->name ?? 'système' }}</td>
                <td><code>{{ $log->action }}</code></td>
                <td class="small">{{ $log->resource_type }}#{{ $log->resource_id }}</td>
                <td class="small text-muted">{{ json_encode($log->details, JSON_UNESCAPED_UNICODE) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-4">Aucun événement.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $logs->links() }}</div>
@endsection
