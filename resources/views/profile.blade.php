@extends('layouts.app')

@section('title', 'Profil')

@section('content')
<h4 class="mb-3">Mon profil</h4>

@php $registry = app(\App\Themes\ThemeRegistry::class); @endphp

<div class="card p-4 mb-3">
    <h6>Identité</h6>
    <div class="mb-3">
        <strong>Nom :</strong> {{ $user->name }}<br>
        <strong>Email :</strong> {{ $user->email }}<br>
        <strong>Tenant :</strong> {{ $user->tenant->name ?? 'Super Admin' }}<br>
        <strong>Statut :</strong> <span class="badge bg-{{ $user->isActive() ? 'success' : 'danger' }}">{{ $user->isActive() ? 'Actif' : 'Suspendu' }}</span>
        @if ($user->last_login_at)<br><strong>Dernière connexion :</strong> {{ $user->last_login_at->format('d/m/Y H:i') }}@endif
    </div>
</div>

<div class="card p-4 mb-3">
    <h6>Apparence</h6>
    <form method="POST" action="{{ route('profile.theme') }}" class="row g-2 align-items-end mb-3">
        @csrf
        <div class="col-md-5">
            <label class="form-label small">Mon thème (couleurs + police)</label>
            <select name="theme" class="form-select">
                <option value="">— Thème de l'entreprise —</option>
                @foreach ($registry->all() as $slug => $t)
                    <option value="{{ $slug }}" @selected($user->theme === $slug)>{{ $t['label'] }}</option>
                @endforeach
            </select>
            <div class="form-text">Votre choix personnel l'emporte sur le thème de l'entreprise.</div>
        </div>
        <div class="col-md-5">
            <label class="form-label small">Aperçu des combinaisons</label>
            <div class="d-flex flex-wrap gap-2">
                @foreach ($registry->all() as $slug => $t)
                    <span class="d-inline-flex flex-column align-items-center" style="cursor:pointer"
                          title="{{ $t['label'] }} — police : {{ $t['font'] }}">
                        <span class="d-inline-flex overflow-hidden rounded" style="border:1px solid rgba(0,0,0,.15)">
                            <span style="width:18px;height:18px;background:{{ $t['palettes']['light']['background'] }}"></span>
                            <span style="width:18px;height:18px;background:{{ $t['palettes']['light']['surface'] }}"></span>
                            <span style="width:18px;height:18px;background:{{ $t['palettes']['light']['accent'] }}"></span>
                            <span style="width:18px;height:18px;background:{{ $t['palettes']['light']['foreground'] }}"></span>
                        </span>
                        <span class="small mt-1" style="font-family:{{ $t['font'] }}">{{ $t['label'] }}</span>
                    </span>
                @endforeach
            </div>
        </div>
        <div class="col-md-2"><button class="btn btn-primary">Enregistrer</button></div>
    </form>
    <hr>
    <form method="POST" action="{{ route('profile.theme-mode') }}" class="row g-2 align-items-end">
        @csrf
        <div class="col-md-4">
            <label class="form-label small">Mode (clair / sombre / auto)</label>
            <select name="theme_mode" class="form-select">
                <option value="auto" @selected(($user->theme_mode ?? 'auto') === 'auto')>Auto (suit le système)</option>
                <option value="light" @selected(($user->theme_mode ?? '') === 'light')>Clair</option>
                <option value="dark" @selected(($user->theme_mode ?? '') === 'dark')>Sombre</option>
            </select>
            <div class="form-text">Votre choix personnel l'emporte sur le mode par défaut de l'entreprise.</div>
        </div>
        <div class="col-md-2"><button class="btn btn-primary">Enregistrer</button></div>
    </form>
</div>

<div class="card p-4 mb-3">
    <h6>Mot de passe</h6>
    <form method="POST" action="{{ route('profile.update-password') }}" class="row g-2">
        @csrf
        <div class="col-md-4"><label class="form-label small">Mot de passe actuel</label>
            <input type="password" name="current_password" class="form-control" required autocomplete="current-password"></div>
        <div class="col-md-4"><label class="form-label small">Nouveau mot de passe</label>
            <input type="password" name="password" class="form-control" required autocomplete="new-password"></div>
        <div class="col-md-4"><label class="form-label small">Confirmation</label>
            <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password"></div>
        <div class="col-12"><button class="btn btn-primary">Changer le mot de passe</button></div>
    </form>
</div>

<div class="card p-4">
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
