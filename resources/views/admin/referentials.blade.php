@extends('layouts.app')

@section('title', __('Administration') . ' — ' . __('Référentiels V02'))

@section('content')
<h4 class="mb-3">{{ __('Référentiels V02') }}</h4>

<ul class="nav nav-pills mb-3">
    @foreach ($types as $t)
        <li class="nav-item">
            <a class="nav-link {{ $type === $t ? 'active' : '' }}" href="{{ route('admin.referentials', ['type' => $t]) }}">{{ ucfirst($t) }}s</a>
        </li>
    @endforeach
</ul>

<div class="card p-3 mb-3">
    <form method="POST" action="{{ route('admin.referentials.store') }}" class="d-flex gap-2">
        @csrf
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="text" name="name" class="form-control" placeholder="{{ __('Nom') }} (ex. Responsable Qualité)" required>
        <input type="text" name="code" class="form-control" placeholder="{{ __('Code') }} ({{ __('optionnel') }})" style="max-width:180px">
        <button class="btn btn-primary">{{ __('Ajouter') }}</button>
    </form>
</div>

<div class="card">
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>{{ __('Nom') }}</th><th>{{ __('Code') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
        <tbody>
        @forelse ($items as $item)
            <tr>
                <td>
                    <form method="POST" action="{{ route('admin.referentials.update', $item) }}" class="d-flex gap-1">
                        @csrf
                        <input type="hidden" name="code" value="{{ $item->code ?? '' }}">
                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $item->name }}" required style="max-width:260px">
                        <button class="btn btn-sm btn-outline-secondary" title="{{ __('Renommer') }}"><i class="bi bi-pencil"></i></button>
                    </form>
                </td>
                <td>
                    <form method="POST" action="{{ route('admin.referentials.update', $item) }}" class="d-flex gap-1">
                        @csrf
                        <input type="hidden" name="name" value="{{ $item->name }}">
                        <input type="text" name="code" class="form-control form-control-sm" value="{{ $item->code ?? '' }}" placeholder="—" style="max-width:140px">
                        <button class="btn btn-sm btn-outline-secondary" title="{{ __('Mettre à jour le code') }}"><i class="bi bi-check-lg"></i></button>
                    </form>
                </td>
                <td class="text-end">
                    <form method="POST" action="{{ route('admin.referentials.delete', $item) }}" class="d-inline">@csrf @method('DELETE')
                        <button class="btn btn-sm btn-link text-danger" onclick="return confirm('Supprimer ce référentiel ?')" title="{{ __('Supprimer') }}"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="3" class="text-center text-muted py-4">{{ __('Aucun référentiel de ce type.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
