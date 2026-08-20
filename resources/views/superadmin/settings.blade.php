@extends('layouts.app')

@section('title', 'Super Admin — Paramètres plateforme')

@section('content')
<h4 class="mb-3">Paramètres plateforme <span class="text-muted fs-6">(défauts appliqués aux nouveaux tenants)</span></h4>

<form method="POST" action="{{ route('superadmin.settings.update') }}">
    @csrf
    <div class="row">
        <div class="col-lg-6">
            <div class="card p-3 mb-3">
                <h6>Sécurité</h6>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="mfa_required_admin" value="1" id="pmfaA" @checked($settings['mfa_required_admin'] ?? false)>
                    <label class="form-check-label" for="pmfaA">MFA requis pour les admins (V2)</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="mfa_required_validator" value="1" id="pmfaV" @checked($settings['mfa_required_validator'] ?? false)>
                    <label class="form-check-label" for="pmfaV">MFA requis pour les validateurs (V2)</label>
                </div>
                <div class="row">
                    <div class="col-6 mb-2"><label class="form-label small">Session (min, V2)</label>
                        <input type="number" name="session_expiration_minutes" class="form-control" value="{{ $settings['session_expiration_minutes'] ?? 120 }}"></div>
                    <div class="col-6 mb-2"><label class="form-label small">Longueur min. mot de passe</label>
                        <input type="number" name="password_min_length" class="form-control" value="{{ $settings['password_min_length'] ?? 8 }}"></div>
                </div>
            </div>
            <div class="card p-3 mb-3">
                <h6>Documents & partage</h6>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="auto_lock_on_edit" value="1" id="plock" @checked($settings['auto_lock_on_edit'] ?? true)>
                    <label class="form-check-label" for="plock">Verrouillage auto à l'édition</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="comment_required" value="1" id="pcomment" @checked($settings['comment_required'] ?? false)>
                    <label class="form-check-label" for="pcomment">Commentaire de version obligatoire</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="external_sharing_enabled" value="1" id="pshare" @checked($settings['external_sharing_enabled'] ?? false)>
                    <label class="form-check-label" for="pshare">Partage externe autorisé</label>
                </div>
                <div class="row">
                    <div class="col-6 mb-2"><label class="form-label small">Lien externe max (jours)</label>
                        <input type="number" name="external_share_max_days" class="form-control" value="{{ $settings['external_share_max_days'] ?? 30 }}"></div>
                    <div class="col-6 mb-2"><label class="form-label small">Purge corbeille (jours)</label>
                        <input type="number" name="trash_purge_days" class="form-control" value="{{ $settings['trash_purge_days'] ?? 30 }}"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card p-3 mb-3">
                <h6>Formats autorisés par défaut</h6>
                @foreach ($mimeChoices as $mime)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="allowed_mimes[]" value="{{ $mime }}" id="pm-{{ \Illuminate\Support\Str::slug($mime) }}"
                            @checked(in_array($mime, $settings['allowed_mimes'] ?? $mimeChoices))>
                        <label class="form-check-label small" for="pm-{{ \Illuminate\Support\Str::slug($mime) }}">{{ $mime }}</label>
                    </div>
                @endforeach
            </div>
            <div class="card p-3">
                <h6>IA</h6>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="ai_enabled" value="1" id="pai" @checked($settings['ai_enabled'] ?? true)>
                    <label class="form-check-label" for="pai">IA activée</label>
                </div>
                <div class="mb-2"><label class="form-label small">Fournisseurs autorisés</label>
                    <input type="text" name="ai_providers[]" class="form-control" value="{{ implode(',', $settings['ai_providers'] ?? ['mock']) }}"></div>
            </div>
        </div>
    </div>
    <button class="btn btn-primary">Enregistrer les paramètres plateforme</button>
</form>
@endsection
