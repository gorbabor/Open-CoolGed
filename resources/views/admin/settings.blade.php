@extends('layouts.app')

@section('title', 'Administration — Paramètres')

@section('content')
<h4 class="mb-3">Paramètres du tenant</h4>

<ul class="nav nav-tabs mb-3" id="settingsTabs">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-general">Général</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-security">Sécurité</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-documents">Documents</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-retention">Rétention</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-notifications">Notifications</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-sharing">Partage</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-ai">IA</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-workflows">Workflows</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-branding">Branding</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-mail">Messagerie</a></li>
</ul>

@php
    // Libellés lisibles pour les formats MIME (le MIME reste la valeur envoyée).
    $mimeLabels = [
        'application/pdf' => 'PDF',
        'application/msword' => 'Word (DOC)',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word (DOCX)',
        'application/vnd.ms-excel' => 'Excel (XLS)',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel (XLSX)',
        'application/vnd.ms-powerpoint' => 'PowerPoint (PPT)',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'PowerPoint (PPTX)',
        'text/plain' => 'Texte (TXT)',
        'text/csv' => 'CSV',
        'application/json' => 'JSON',
        'text/html' => 'HTML',
        'text/markdown' => 'Markdown (MD)',
        'image/jpeg' => 'Image (JPEG)',
        'image/png' => 'Image (PNG)',
        'image/tiff' => 'Image (TIFF)',
    ];
    $currentMimes = $settings['allowed_mimes'] ?? $mimeChoices;
    $emailConfigured = in_array(config('mail.default'), ['smtp', 'mailgun', 'postmark', 'ses'], true)
        && config('mail.mailers.smtp.host') !== 'smtp.mailtrap.io';
@endphp

<form method="POST" action="{{ route('admin.settings.update') }}">
    @csrf
    <div class="tab-content">

        <div class="tab-pane fade show active" id="tab-general">
            <div class="card p-3">
                <h6>Quotas</h6>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <label class="form-label small">Stockage (Mo)</label>
                        <div class="input-group">
                            <input type="number" name="storage_quota_mb" class="form-control" value="{{ $tenant->storage_quota_mb }}">
                            <span class="input-group-text">utilisé : {{ $storageUsedMb }} Mo</span>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2"><label class="form-label small">Utilisateurs</label>
                        <input type="number" name="user_quota" class="form-control" value="{{ $tenant->user_quota }}"></div>
                    <div class="col-md-4 mb-2"><label class="form-label small">Taille max de fichier (Mo)</label>
                        <input type="number" name="max_file_size_mb" class="form-control" value="{{ $tenant->max_file_size_mb }}"></div>
                </div>
                <h6 class="mt-3">Organisation</h6>
                <div class="row">
                    <div class="col-md-4 mb-2"><label class="form-label small">Langue de l'interface</label>
                        <select name="language" class="form-select">
                            <option value="fr" @selected(($settings['language'] ?? 'fr') === 'fr')>Français</option>
                            <option value="en" @selected(($settings['language'] ?? 'fr') === 'en')>English</option>
                        </select></div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label small">Fuseau horaire</label>
                        <input type="text" name="timezone" class="form-control" value="{{ $settings['timezone'] ?? 'UTC' }}" placeholder="Europe/Paris ou Africa/Abidjan">
                        <div class="form-text">Utilisé pour les dates et échéances. Ex. : Europe/Paris, Africa/Abidjan.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-security">
            <div class="card p-3">
                <div class="alert alert-warning py-2 small">
                    <i class="bi bi-info-circle"></i> Les options MFA et d'expiration de session sont
                    <strong>enregistrées mais appliquées en V2</strong> — elles n'ont pas encore d'effet.
                    La longueur minimale du mot de passe est appliquée dès maintenant.
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="mfa_required_admin" value="1" id="mfaAdmin" @checked($settings['mfa_required_admin'] ?? false)>
                    <label class="form-check-label" for="mfaAdmin">MFA obligatoire pour les administrateurs <span class="badge bg-secondary">V2</span></label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="mfa_required_validator" value="1" id="mfaValid" @checked($settings['mfa_required_validator'] ?? false)>
                    <label class="form-check-label" for="mfaValid">MFA obligatoire pour les validateurs <span class="badge bg-secondary">V2</span></label>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-2"><label class="form-label small">Expiration de session (minutes) <span class="badge bg-secondary">V2</span></label>
                        <input type="number" name="session_expiration_minutes" class="form-control" value="{{ $settings['session_expiration_minutes'] ?? 120 }}"></div>
                    <div class="col-md-4 mb-2"><label class="form-label small">Longueur min. mot de passe (appliquée)</label>
                        <input type="number" name="password_min_length" class="form-control" value="{{ $settings['password_min_length'] ?? 8 }}"></div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-documents">
            <div class="card p-3">
                <h6>Formats autorisés à l'import <span class="badge bg-success">appliqués immédiatement</span></h6>
                <div class="row">
                    @foreach ($mimeChoices as $mime)
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="allowed_mimes[]" value="{{ $mime }}" id="mime-{{ \Illuminate\Support\Str::slug($mime) }}"
                                    @checked(in_array($mime, $currentMimes))>
                                <label class="form-check-label small" for="mime-{{ \Illuminate\Support\Str::slug($mime) }}"
                                    title="{{ $mime }}">{{ $mimeLabels[$mime] ?? $mime }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="form-text mb-2">Survolez un format pour voir son type technique (MIME).</div>
                <hr>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="auto_lock_on_edit" value="1" id="autoLock" @checked($settings['auto_lock_on_edit'] ?? true)>
                    <label class="form-check-label" for="autoLock">Verrouiller automatiquement le document à l'édition <span class="badge bg-success">appliqué</span></label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="comment_required" value="1" id="commentReq" @checked($settings['comment_required'] ?? false)>
                    <label class="form-check-label" for="commentReq">Commentaire de version obligatoire <span class="badge bg-success">appliqué</span></label>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-retention">
            <div class="card p-3">
                <div class="row">
                    <div class="col-md-4 mb-2"><label class="form-label small">Durée de rétention par défaut (jours, vide = illimité)</label>
                        <input type="number" name="default_retention_days" class="form-control" value="{{ $settings['default_retention_days'] ?? '' }}">
                        <div class="form-text">Appliqué aux types documentaires sans rétention propre.</div></div>
                    <div class="col-md-4 mb-2"><label class="form-label small">Purge de la corbeille après (jours)</label>
                        <input type="number" name="trash_purge_days" class="form-control" value="{{ $settings['trash_purge_days'] ?? 30 }}"></div>
                    <div class="col-md-4 mb-2"><label class="form-label small">Alerte avant suppression (jours)</label>
                        <input type="number" name="retention_alert_days" class="form-control" value="{{ $settings['retention_alert_days'] ?? 30 }}"></div>
                </div>
                <p class="small text-muted mb-0">La rétention par type documentaire se configure dans « Types documentaires ».</p>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-notifications">
            <div class="card p-3">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="notif_tasks" value="1" id="ntTasks" @checked($settings['notif_tasks'] ?? true)>
                    <label class="form-check-label" for="ntTasks">Notifications de tâches (workflows)</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="notif_shares" value="1" id="ntShares" @checked($settings['notif_shares'] ?? true)>
                    <label class="form-check-label" for="ntShares">Notifications de partages</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="notif_deadlines" value="1" id="ntDeadlines" @checked($settings['notif_deadlines'] ?? true)>
                    <label class="form-check-label" for="ntDeadlines">Notifications d'échéances</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="email_notifications" value="1" id="ntEmail" @checked($settings['email_notifications'] ?? false)>
                    <label class="form-check-label" for="ntEmail">Notifications par email</label>
                </div>
                @if (! $emailConfigured)
                    <div class="alert alert-warning py-2 small">
                        <i class="bi bi-exclamation-triangle"></i> SMTP non configuré sur ce serveur —
                        les emails ne seront <strong>pas envoyés</strong> même si cette option est cochée
                        (les notifications restent visibles dans l'application).
                    </div>
                @endif
            </div>
        </div>

        <div class="tab-pane fade" id="tab-sharing">
            <div class="card p-3">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="external_sharing_enabled" value="1" id="extShare" @checked($settings['external_sharing_enabled'] ?? false)>
                    <label class="form-check-label" for="extShare">Autoriser les liens de partage externes <span class="badge bg-success">appliqué</span></label>
                </div>
                <div class="form-text mb-2">Désactivé par défaut à la création du tenant. Les liens externes exigent
                    toujours une expiration et sont révocables (accès par jeton sécurisé, jamais d'URL publique).</div>
                <div class="row">
                    <div class="col-md-4 mb-2"><label class="form-label small">Durée max d'un lien externe (jours)</label>
                        <input type="number" name="external_share_max_days" class="form-control" value="{{ $settings['external_share_max_days'] ?? 30 }}"></div>
                    <div class="col-md-4 mb-2"><label class="form-label small">Mot de passe requis sur les liens externes</label>
                        <select name="external_share_password_required" class="form-select">
                            <option value="0" @selected(!($settings['external_share_password_required'] ?? false))>Optionnel</option>
                            <option value="1" @selected($settings['external_share_password_required'] ?? false)>Obligatoire</option>
                        </select></div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-ai">
            <div class="card p-3">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="ai_enabled" value="1" id="aiEnabled" @checked($settings['ai_enabled'] ?? true)>
                    <label class="form-check-label" for="aiEnabled">IA activée pour ce tenant</label>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Fournisseurs autorisés (séparés par des virgules)</label>
                    <input type="text" name="ai_providers" class="form-control" value="{{ implode(',', is_array($settings['ai_providers'] ?? ['mock']) ? $settings['ai_providers'] : ['mock']) }}">
                    <div class="form-text">Ex. : mock, openai, anthropic — seuls les fournisseurs avec une clé configurée sont réellement utilisés.</div>
                </div>
                <hr>
                <h6 class="small text-muted">Clés API LLM (chiffrées — vide = héritage plateforme)</h6>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label small">Clé API OpenAI</label>
                        <input type="password" name="openai_api_key" class="form-control" value="" autocomplete="new-password" placeholder="{{ ! empty($settings['openai_api_key']) ? '•••••••• (laisser vide pour conserver)' : 'sk-… (sinon héritée)' }}">
                        <label class="form-label small mt-2">Modèle OpenAI</label>
                        <input type="text" name="openai_model" class="form-control" value="{{ $settings['openai_model'] ?? 'gpt-4o-mini' }}">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label small">Clé API Anthropic</label>
                        <input type="password" name="anthropic_api_key" class="form-control" value="" autocomplete="new-password" placeholder="{{ ! empty($settings['anthropic_api_key']) ? '•••••••• (laisser vide pour conserver)' : 'sk-ant-… (sinon héritée)' }}">
                        <label class="form-label small mt-2">Modèle Anthropic</label>
                        <input type="text" name="anthropic_model" class="form-control" value="{{ $settings['anthropic_model'] ?? 'claude-3-5-haiku' }}">
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 mt-2">
                    <button class="btn btn-outline-primary btn-sm" formaction="{{ route('admin.settings.test-ai') }}" name="provider" value="openai">Tester OpenAI</button>
                    <button class="btn btn-outline-primary btn-sm" formaction="{{ route('admin.settings.test-ai') }}" name="provider" value="anthropic">Tester Anthropic</button>
                    <span class="small text-muted">Teste la clé résolue (tenant ou plateforme) — nécessite l'enregistrement préalable des paramètres.</span>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-workflows">
            <div class="card p-3">
                <div class="mb-2">
                    <label class="form-label small">Workflow par défaut</label>
                    <select name="default_workflow_type" class="form-select">
                        <option value="">— Aucun —</option>
                        @foreach ($workflowChoices as $wf)
                            <option value="{{ $wf->id }}" @selected(($settings['default_workflow_type'] ?? null) == $wf->id)>{{ $wf->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Workflow proposé par défaut lors du démarrage manuel sur un document.</div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-branding">
            @php $registry = app(\App\Themes\ThemeRegistry::class); @endphp
            <div class="card p-3">
                <h6>Branding <span class="badge bg-success">appliqué</span></h6>
                <div class="form-text mb-2">Le nom de marque, le thème, la couleur et le logo sont appliqués à l'interface
                    (sidebar, boutons, barres, titres, emails). Enregistrés avec le bouton « Enregistrer les paramètres » ci-dessous.
                    Nom de marque vide = nom de la plateforme ({{ app_display_name() }}).</div>
                <div class="row">
                    <div class="col-md-4 mb-2"><label class="form-label small">Nom de marque</label>
                        <input type="text" name="brand_name" class="form-control" value="{{ $tenant->branding['brand_name'] ?? '' }}" placeholder="{{ app_display_name() }}" maxlength="255"></div>
                    <div class="col-md-4 mb-2"><label class="form-label small">Couleur personnalisée (optionnelle)</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="checkbox" name="brand_color_custom" value="1" id="brandColorCustom" class="form-check-input"
                                @checked(! empty($tenant->branding['color']))>
                            <input type="color" name="brand_color" id="brandColorInput" class="form-control form-control-color"
                                value="{{ $tenant->branding['color'] ?? '#4f46e5' }}" @disabled(empty($tenant->branding['color']))>
                        </div>
                        <div class="form-text">Décochez pour utiliser la couleur du thème.</div>
                    </div>
                    <div class="col-md-4 mb-2"><label class="form-label small">Logo (URL)</label>
                        <input type="url" name="brand_logo_url" class="form-control" value="{{ $tenant->branding['logo_url'] ?? '' }}" placeholder="https://…/logo.png"></div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label small">Thème (palette + police)</label>
                        <select name="theme" class="form-select">
                            <option value="">— Thème plateforme —</option>
                            @foreach ($registry->all() as $slug => $t)
                                <option value="{{ $slug }}" @selected(($tenant->branding['theme'] ?? '') === $slug)>{{ $t['label'] }}</option>
                            @endforeach
                        </select>
                        <div class="d-flex gap-1 mt-2">
                            @foreach ($registry->all() as $slug => $t)
                                <span class="d-inline-block rounded-circle" style="width:14px;height:14px;background:{{ $t['palettes']['light']['accent'] }};outline:2px solid {{ ($tenant->branding['theme'] ?? '') === $slug ? '#000' : 'transparent' }}" title="{{ $t['label'] }}"></span>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label small">Mode par défaut (utilisateurs sans préférence)</label>
                        <select name="theme_mode" class="form-select">
                            <option value="">— Auto —</option>
                            <option value="light" @selected(($tenant->branding['theme_mode'] ?? '') === 'light')>Clair</option>
                            <option value="dark" @selected(($tenant->branding['theme_mode'] ?? '') === 'dark')>Sombre</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-mail">
            <div class="card p-3">
                <h6>Messagerie (SMTP) <span class="badge bg-{{ $settings['mail_enabled'] ?? false ? 'success' : 'secondary' }}">{{ ($settings['mail_enabled'] ?? false) ? 'activée' : 'désactivée' }}</span></h6>
                <div class="form-text mb-2">Configurez un serveur SMTP pour envoyer les notifications (tâches,
                    partages, échéances) par email. Le mot de passe est stocké chiffré. Si le SMTP du serveur
                    est déjà configuré (hébergeur), laissez vide pour utiliser celui-ci.</div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="mail_enabled" value="1" id="mailEnabled" @checked($settings['mail_enabled'] ?? false)>
                    <label class="form-check-label" for="mailEnabled">Activer l'envoi d'emails via ce SMTP</label>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-2"><label class="form-label small">Serveur SMTP (host)</label>
                        <input type="text" name="smtp_host" class="form-control" value="{{ $settings['smtp_host'] ?? '' }}" placeholder="smtp.example.com"></div>
                    <div class="col-md-3 mb-2"><label class="form-label small">Port</label>
                        <input type="number" name="smtp_port" class="form-control" value="{{ $settings['smtp_port'] ?? 587 }}" placeholder="587"></div>
                    <div class="col-md-3 mb-2"><label class="form-label small">Cryptage</label>
                        <input type="text" class="form-control" value="{{ ($settings['smtp_port'] ?? 587) == 465 ? 'ssl' : 'tls' }}" disabled>
                        <div class="form-text">Auto : ssl (465) / tls (587)</div></div>
                    <div class="col-md-6 mb-2"><label class="form-label small">Utilisateur</label>
                        <input type="text" name="smtp_username" class="form-control" value="{{ $settings['smtp_username'] ?? '' }}" autocomplete="off"></div>
                    <div class="col-md-6 mb-2"><label class="form-label small">Mot de passe</label>
                        <input type="password" name="smtp_password" class="form-control" value="" placeholder="{{ $settings['smtp_password'] ? '•••••••• (laisser vide pour conserver)' : '' }}" autocomplete="new-password"></div>
                    <div class="col-md-6 mb-2"><label class="form-label small">Adresse d'expédition (from)</label>
                        <input type="email" name="smtp_from_address" class="form-control" value="{{ $settings['smtp_from_address'] ?? '' }}" placeholder="no-reply@votre-domaine.com"></div>
                    <div class="col-md-6 mb-2"><label class="form-label small">Nom d'expéditeur</label>
                        <input type="text" name="smtp_from_name" class="form-control" value="{{ $settings['smtp_from_name'] ?? '' }}" placeholder="{{ app_display_name() }}"></div>
                </div>
                <div class="d-flex align-items-center gap-2 mt-2">
                    <button class="btn btn-outline-primary btn-sm" formaction="{{ route('admin.settings.test-mail') }}">Tester l'envoi</button>
                    <span class="small text-muted">Envoie un email de test à votre adresse ({{ auth()->user()->email }}).</span>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <button class="btn btn-primary">Enregistrer les paramètres</button>
        </div>
    </div>
</form>
@endsection
