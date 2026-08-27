@extends('layouts.app')

@section('title', __('Tableau de bord'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">{{ __('Bonjour, :name', ['name' => auth()->user()->name]) }}</h4>
    <a href="{{ route('documents.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Nouveau document') }}</a>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div class="text-muted small">{{ __('Stockage utilisé') }}</div>
            <div class="fs-4 fw-bold">{{ $storageUsedMb }} Mo <span class="fs-6 text-muted">/ {{ $storageQuotaMb }} Mo</span></div>
            <div class="progress mt-2" style="height:6px">
                <div class="progress-bar" style="width: {{ min(100, $storageUsedMb / max(1, $storageQuotaMb) * 100) }}%"></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div class="text-muted small">{{ __('Documents récents') }}</div>
            <div class="fs-4 fw-bold">{{ $recent->count() }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div class="text-muted small">{{ __('Tâches en attente') }}</div>
            <div class="fs-4 fw-bold">{{ $tasks->count() }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div class="text-muted small">{{ __('Notifications non lues') }}</div>
            <div class="fs-4 fw-bold">{{ $notifications->whereNull('read_at')->count() }}</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-7">
        <div class="card p-3 mb-3">
            <h6 class="border-bottom pb-2">{{ __('Documents récents') }}</h6>
            @forelse ($recent as $doc)
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <div>
                        <a href="{{ route('documents.show', $doc) }}" class="text-decoration-none">{{ $doc->title }}</a>
                        <div class="small text-muted">{{ $doc->space->name ?? '—' }} · v{{ $doc->currentVersion->version ?? '—' }} · {{ $doc->statusLabel() }}</div>
                    </div>
                    <span class="badge bg-light text-dark">{{ $doc->type->name ?? __('Sans type') }}</span>
                </div>
            @empty
                <p class="text-muted small mb-0">{{ __('Aucun document récent.') }}</p>
            @endforelse
        </div>
        <div class="card p-3">
            <h6 class="border-bottom pb-2">{{ __('Mes tâches') }}</h6>
            @forelse ($tasks as $task)
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <div>
                        <strong>{{ $task->step->name ?? __('Étape') }}</strong>
                        <div class="small text-muted">{{ $task->instance->document->title ?? '—' }} · {{ __('échéance') }} {{ $task->due_at?->format('d/m/Y') }}</div>
                    </div>
                    <a href="{{ route('tasks.index') }}" class="btn btn-sm btn-outline-primary">{{ __('Traiter') }}</a>
                </div>
            @empty
                <p class="text-muted small mb-0">{{ __('Aucune tâche en attente.') }}</p>
            @endforelse
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card p-3">
            <h6 class="border-bottom pb-2">{{ __('Notifications') }}</h6>
            @forelse ($notifications as $n)
                <div class="py-2 border-bottom">
                    <div class="small {{ $n->read_at ? 'text-muted' : 'fw-bold' }}">{{ $n->title }}</div>
                    <div class="small text-muted">{{ $n->created_at->diffForHumans() }}</div>
                </div>
            @empty
                <p class="text-muted small mb-0">{{ __('Aucune notification.') }}</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
