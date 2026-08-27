@php
    $toggleList = request()->fullUrlWithQuery(['view' => 'list', 'value' => null]);
    $toggleCards = request()->fullUrlWithQuery(['view' => 'cards', 'value' => null]);
@endphp
<div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Mode d\'affichage') }}">
    <a href="{{ $toggleList }}" class="btn btn-outline-secondary{{ ($viewMode ?? 'list') === 'list' ? ' active' : '' }}" title="{{ __('Vue liste') }}"><i class="bi bi-list-ul"></i></a>
    <a href="{{ $toggleCards }}" class="btn btn-outline-secondary{{ ($viewMode ?? 'list') === 'cards' ? ' active' : '' }}" title="{{ __('Vue cartes') }}"><i class="bi bi-grid-3x3-gap-fill"></i></a>
</div>
