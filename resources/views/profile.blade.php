@extends('layouts.app')

@section('title', 'Profil')

@section('content')
<h4 class="mb-3">Mon profil</h4>

<div class="card p-4">
    <div class="mb-3">
        <strong>Nom :</strong> {{ $user->name }}<br>
        <strong>Email :</strong> {{ $user->email }}<br>
        <strong>Tenant :</strong> {{ $user->tenant->name ?? 'Super Admin' }}
    </div>

    <hr>

    <h6>Authentification à deux facteurs (MFA)</h6>
    @if ($user->mfa_enabled)
        <span class="badge bg-success">MFA activé</span>
        <form method="POST" action="{{ route('profile.mfa.disable') }}" class="mt-2">@csrf
            <button class="btn btn-sm btn-outline-danger">Désactiver le MFA</button>
        </form>
    @else
        @if ($secret)
            <p class="small text-muted">Scannez cette URL avec votre application d'authentification, puis saisissez un code pour confirmer :</p>
            <code class="d-block bg-light p-2 mb-2">{{ $otpauth }}</code>
            <form method="POST" action="{{ route('profile.mfa.enable') }}" class="d-flex gap-2">
                @csrf
                <input type="text" name="code" class="form-control" placeholder="Code à 6 chiffres" maxlength="6" required>
                <button class="btn btn-primary">Activer</button>
            </form>
        @else
            <form method="POST" action="{{ route('profile.mfa.enable') }}">@csrf
                <button class="btn btn-outline-primary">Générer le secret MFA</button>
            </form>
        @endif
    @endif
</div>
@endsection
