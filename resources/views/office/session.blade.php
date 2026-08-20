@extends('layouts.app')

@section('title', 'Session d\'édition')

@section('content')
<h4 class="mb-3">Édition en ligne</h4>
<div class="card p-4">
    <p><strong>Document :</strong> {{ $session->document->title }} (v{{ $version->version }})</p>
    <p><strong>Fournisseur :</strong> {{ config('ged.office_provider') }} — session valide jusqu'à {{ $session->expires_at->format('d/m/Y H:i') }}</p>
    <p class="small text-muted">Le document est verrouillé (check-out). Au retour du fichier, une nouvelle version sera créée automatiquement.</p>

    <a href="{{ route('office.download', $session->raw_token) }}" class="btn btn-primary mb-3"><i class="bi bi-download"></i> Télécharger pour édition</a>

    <form method="POST" action="{{ route('office.return', $session->raw_token) }}" enctype="multipart/form-data">
        @csrf
        <div class="row g-2">
            <div class="col-md-6"><input type="file" name="file" class="form-control" required></div>
            <div class="col-md-3"><button class="btn btn-success w-100">Retourner le fichier</button></div>
        </div>
    </form>
</div>
@endsection
