@extends('layouts.app')

@section('title', __('Import CSV'))

@section('content')
<h4 class="mb-3">{{ __('Import CSV') }}</h4>

<div class="card p-3 mb-3">
    <h6>{{ __('Importer le registre documentaire (CSV)') }}</h6>
    <div class="form-text mb-3">
        {{ __('Le CSV doit contenir l\'en-tête exact (colonnes séparées par des points-virgules). La colonne fichier est optionnelle : si renseignée avec un chemin existant sur le serveur, le fichier est attaché au document (version 1.0) — tous les formats autorisés par la politique de l\'organisation (PDF, Word, Excel, PowerPoint, texte, images…).') }}
    </div>
    <form method="POST" action="{{ route('admin.import-csv.post') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
        @csrf
        <div class="col-md-6">
            <label class="form-label small">{{ __('Fichier CSV') }}</label>
            <input type="file" name="csv" class="form-control" accept=".csv,.txt" required>
        </div>
        <div class="col-md-2">
            <label class="form-label small">&nbsp;</label>
            <button class="btn btn-primary w-100">{{ __('Importer') }}</button>
        </div>
        <div class="col-md-4">
            <a href="{{ route('admin.import-csv.template') }}" class="btn btn-outline-primary w-100">
                <i class="bi bi-download"></i> {{ __('Télécharger le template') }}
            </a>
        </div>
    </form>
</div>

@if ($report)
<div class="card p-3 mb-3">
    <h6>{{ __('Rapport d\'import') }}</h6>
    <p>
        <span class="badge bg-success">{{ $report['ok'] }} {{ __('document(s) importé(s)') }}</span>
        <span class="badge bg-danger">{{ count($report['errors']) }} {{ __('erreur(s)') }}</span>
        <span class="badge bg-warning">{{ count($report['file_errors'] ?? []) }} {{ __('avertissement(s) fichier') }}</span>
    </p>
    @if (! empty($report['created']))
        <div class="small text-muted mb-2">{{ __('Créés') }} : {{ implode(', ', array_slice($report['created'], 0, 10)) }}{{ count($report['created']) > 10 ? '…' : '' }}</div>
    @endif
    @if (! empty($report['errors']))
        <ul class="small text-danger mb-2">
            @foreach ($report['errors'] as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    @endif
    @if (! empty($report['file_errors']))
        <ul class="small text-warning mb-0">
            @foreach ($report['file_errors'] as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    @endif
</div>
@endif
@endsection
