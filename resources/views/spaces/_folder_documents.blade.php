<ul class="list-unstyled mb-1 mt-1">
    @forelse ($documents as $doc)
    <li class="py-1">
        <a href="{{ route('documents.show', $doc) }}" class="text-decoration-none">{{ $doc->title }}</a>
        <span class="text-muted">
            @if ($doc->currentVersion)v{{ $doc->currentVersion->version }}@else{{ __('sans fichier') }}@endif
            <span class="badge bg-{{ $doc->status === 'approved' ? 'success' : ($doc->status === 'archived' ? 'secondary' : 'info') }}">{{ $doc->statusLabel() }}</span>
            {{ $doc->updated_at->diffForHumans() }}
        </span>
    </li>
    @empty
    <li class="text-muted py-1">{{ __('Aucun document dans ce dossier.') }}</li>
    @endforelse
</ul>
@if ($total > $documents->count())
<p class="small text-muted mb-1">{{ __(':shown premiers sur :total documents.', ['shown' => $documents->count(), 'total' => $total]) }}</p>
@endif
@if ($total > 0)
<a href="{{ route('documents.index', ['folder_id' => $folder->id]) }}" class="small">{{ __('Voir tous les documents du dossier') }}</a>
@endif
