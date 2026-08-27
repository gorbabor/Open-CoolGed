@extends('layouts.app')

@section('title', __('Notifications'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">{{ __('Notifications') }}</h4>
    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf
        <button class="btn btn-sm btn-outline-secondary">{{ __('Tout marquer comme lu') }}</button>
    </form>
</div>

<div class="card p-3">
    @forelse ($notifications as $n)
        <div class="d-flex justify-content-between py-2 border-bottom {{ $n->read_at ? 'text-muted' : '' }}">
            <div>
                <div class="{{ $n->read_at ? '' : 'fw-bold' }}">{{ $n->title }}</div>
                @if ($n->body)<div class="small">{{ $n->body }}</div>@endif
                <div class="small text-muted">{{ $n->type }} · {{ $n->created_at->format('d/m/Y H:i') }}</div>
            </div>
            @if (!$n->read_at)
                <form method="POST" action="{{ route('notifications.read', $n) }}">@csrf
                    <button class="btn btn-sm btn-outline-primary">{{ __('Ouvrir') }}</button>
                </form>
            @endif
        </div>
    @empty
        <p class="text-muted mb-0">{{ __('Aucune notification.') }}</p>
    @endforelse
</div>
<div class="mt-3">{{ $notifications->links() }}</div>
@endsection
