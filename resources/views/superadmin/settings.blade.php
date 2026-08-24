@extends('layouts.app')

@section('title', 'Super Admin — Paramètres plateforme')

@section('content')
<h4 class="mb-3">Paramètres plateforme <span class="text-muted fs-6">(défauts appliqués aux nouveaux tenants)</span></h4>

<form method="POST" action="{{ route('superadmin.settings.update') }}">
    @csrf
    <div class="card p-3 mb-3">
        <h6>Identité de la plateforme</h6>
        <div class="mb-2">
            <label class="form-label small">Nom de la plateforme (défaut : Open-CoolGed)</label>
            <input type="text" name="app_name" class="form-control" value="{{ $settings['app_name'] ?? 'Open-CoolGed' }}" maxlength="255">
            <div class="form-text">Nom affiché par défaut sur le login et les titres — chaque admin tenant peut le surcharger pour son entreprise (Branding).</div>
        </div>
        @php $registry = app(\App\Themes\ThemeRegistry::class); @endphp
        <div class="row">
            <div class="col-md-6 mb-2">
                <label class="form-label small">Thème par défaut (hérité par les tenants)</label>
                <select name="theme" class="form-select">
                    @foreach ($registry->all() as $slug => $t)
                        <option value="{{ $slug }}" @selected(($settings['theme'] ?? \App\Themes\ThemeRegistry::DEFAULT_THEME) === $slug)>{{ $t['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 mb-2">
                <label class="form-label small">Mode par défaut</label>
                <select name="theme_mode" class="form-select">
                    <option value="auto" @selected(($settings['theme_mode'] ?? 'auto') === 'auto')>Auto (suit le système)</option>
                    <option value="light" @selected(($settings['theme_mode'] ?? '') === 'light')>Clair</option>
                    <option value="dark" @selected(($settings['theme_mode'] ?? '') === 'dark')>Sombre</option>
                </select>
            </div>
        </div>
    </div>
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
                <h6>Fournisseurs IA (LLM)</h6>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="ai_enabled" value="1" id="pai" @checked($settings['ai_enabled'] ?? true)>
                    <label class="form-check-label" for="pai">IA activée par défaut</label>
                </div>
                <div class="mb-2"><label class="form-label small">Fournisseurs autorisés (virgule)</label>
                    <input type="text" name="ai_providers" class="form-control" value="{{ implode(',', $settings['ai_providers'] ?? ['mock']) }}">
                    <div class="form-text">Ex. : mock, openai, anthropic — les tenants héritent de cette liste.</div>
                </div>
                <hr>
                <h6 class="small text-muted">Clés API (chiffrées, héritées par les tenants)</h6>
                <div class="mb-2"><label class="form-label small">Clé API OpenAI</label>
                    <input type="password" name="openai_api_key" class="form-control" value="" autocomplete="new-password" placeholder="{{ ! empty($settings['openai_api_key']) ? '•••••••• (laisser vide pour conserver)' : 'sk-…' }}"></div>
                <div class="mb-2"><label class="form-label small">Modèle OpenAI</label>
                    <input type="text" name="openai_model" class="form-control" value="{{ $settings['openai_model'] ?? 'gpt-4o-mini' }}"></div>
                <div class="mb-2"><label class="form-label small">Clé API Anthropic</label>
                    <input type="password" name="anthropic_api_key" class="form-control" value="" autocomplete="new-password" placeholder="{{ ! empty($settings['anthropic_api_key']) ? '•••••••• (laisser vide pour conserver)' : 'sk-ant-…' }}"></div>
                <div class="mb-2"><label class="form-label small">Modèle Anthropic</label>
                    <input type="text" name="anthropic_model" class="form-control" value="{{ $settings['anthropic_model'] ?? 'claude-3-5-haiku' }}"></div>
                <div class="d-flex gap-2 mt-2">
                    <button class="btn btn-outline-primary btn-sm" formaction="{{ route('superadmin.settings.test-ai') }}" name="provider" value="openai">Tester OpenAI</button>
                    <button class="btn btn-outline-primary btn-sm" formaction="{{ route('superadmin.settings.test-ai') }}" name="provider" value="anthropic">Tester Anthropic</button>
                    <span class="small text-muted align-self-center">Teste la clé plateforme (appel minimal).</span>
                </div>
                <div class="form-text mt-2">Sans clé, le fournisseur mock est utilisé (aucun coût). Les tenants peuvent surcharger ces clés dans leurs Paramètres → IA.</div>
            </div>
        </div>
    </div>
    <button class="btn btn-primary">Enregistrer les paramètres plateforme</button>
</form>
@endsection
