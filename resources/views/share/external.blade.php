@extends('layouts.standalone')

@section('title', 'Document partagé')
@section('toolbar-title', 'Document partagé')

@section('content')
<div class="container py-5">
    <div class="card mx-auto" style="max-width: 560px">
        <div class="card-body p-4 text-center">
            @if ($locked)
                <h5 class="mb-3"><i class="bi bi-lock"></i> Document protégé</h5>
                <p class="text-muted small">Ce document est protégé par un mot de passe.</p>
                <form method="POST" action="{{ route('share.external.unlock', $token) }}" class="d-flex gap-2">
                    @csrf
                    <input type="password" name="password" class="form-control" placeholder="Mot de passe" required autofocus>
                    <button class="btn btn-primary">Déverrouiller</button>
                </form>
            @elseif ($document && $version)
                <h5 class="mb-1">{{ $document->title }}</h5>
                <div class="text-muted small mb-3">{{ $version->file_name }} · v{{ $version->version }} ·
                    @if ($share->permission === 'view') consultation @elseif ($share->permission === 'download') téléchargement @else modification @endif
                </div>

                @if (str_starts_with($version->mime_type, 'image/'))
                    <img src="{{ route('share.external.download', $token) }}" class="img-fluid rounded mb-3" alt="aperçu">
                @elseif ($version->extracted_text)
                    <pre class="text-start bg-light p-3 rounded small" style="max-height:300px;overflow:auto">{{ $version->extracted_text }}</pre>
                @endif

                <a href="{{ route('share.external.download', $token) }}" class="btn btn-primary">
                    <i class="bi bi-download"></i> Télécharger
                </a>
                <div class="text-muted small mt-3">
                    Partage expirant le {{ $share->expires_at?->format('d/m/Y H:i') }}
                </div>
            @else
                <h5 class="text-danger"><i class="bi bi-exclamation-triangle"></i> Document indisponible</h5>
                <p class="text-muted small mb-0">Le document partagé n'existe plus.</p>
            @endif
        </div>
    </div>
</div>
@endsection
