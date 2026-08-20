@extends('layouts.app')

@section('title', 'Aperçu')

@section('content')
<div class="card p-4">
    <h5>{{ $document->title }} — v{{ $version->version }}</h5>
    <p class="text-muted small">{{ $version->file_name }} · {{ $version->mime_type }} · {{ number_format($version->size / 1024, 1) }} Ko</p>
    @if (str_starts_with($version->mime_type ?? '', 'image/'))
        <img src="{{ route('documents.download', $document) }}" class="img-fluid" alt="aperçu">
    @elseif ($version->extracted_text)
        <pre class="bg-light p-3">{{ $version->extracted_text }}</pre>
    @else
        <p class="text-muted">Aucun aperçu textuel disponible.</p>
    @endif
    <a href="{{ route('documents.show', $document) }}" class="btn btn-outline-secondary mt-3">Retour</a>
</div>
@endsection
