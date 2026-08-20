@extends('layouts.app')

@section('title', 'Administration — Jobs IA')

@section('content')
<h4 class="mb-3">Jobs IA / OCR</h4>

<div class="card">
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>ID</th><th>Type</th><th>Fournisseur</th><th>Document</th><th>Statut</th><th>Date</th></tr></thead>
        <tbody>
        @forelse ($jobs as $job)
            <tr>
                <td>#{{ $job->id }}</td>
                <td>{{ $job->job_type }}</td>
                <td>{{ $job->provider }}</td>
                <td class="small"><a href="{{ route('documents.show', $job->document) }}">{{ $job->document->title }}</a></td>
                <td><span class="badge bg-{{ $job->status === 'succeeded' ? 'success' : ($job->status === 'failed' ? 'danger' : 'warning') }}">{{ $job->status }}</span></td>
                <td class="small text-muted">{{ $job->created_at->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-4">Aucun job.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $jobs->links() }}</div>
@endsection
