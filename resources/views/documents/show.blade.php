@extends('layouts.app')

@section('title', $document->title)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">{{ $document->title }}</h4>
        <div class="text-muted small">
            {{ $document->space->name ?? '—' }}@if ($document->folder) / {{ $document->folder->pathLabel() }}@endif ·
            {{ $document->type->name ?? 'Sans type' }} · statut <span class="badge bg-info">{{ $document->statusLabel() }}</span>
            @if ($document->isArchived())<span class="badge bg-secondary">archivé</span>@endif
        </div>
    </div>
    <div class="d-flex gap-2">
        @if ($document->space?->is_personal && $document->space->personal_user_id === auth()->id())
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#publishPersonal">Publier dans un espace</button>
        @endif
        @if (!$document->currentVersion)
            <span class="badge bg-warning text-dark align-self-center">sans fichier</span>
        @endif
        @if ($canDownload)
            <a href="{{ route('documents.download', $document) }}" class="btn btn-outline-primary"><i class="bi bi-download"></i> {{ __('Télécharger') }}</a>
        @endif
        @if ($canEdit)
            <a href="{{ route('office.start', $document) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> {{ __('Éditer') }}</a>
        @endif
        @if ($canDelete)
            <form method="POST" action="{{ route('documents.trash', $document) }}">@csrf
                <button class="btn btn-outline-danger" onclick="return confirm('Déplacer vers la corbeille ?')"><i class="bi bi-trash"></i></button>
            </form>
        @endif
    </div>
</div>

@if ($document->currentVersion)
<div class="card mb-3">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <strong>v{{ $document->currentVersion->version }}</strong> — {{ $document->currentVersion->file_name }}
            <div class="small text-muted">{{ number_format($document->currentVersion->size / 1024, 1) }} Ko · {{ $document->currentVersion->mime_type }} · checksum {{ substr($document->currentVersion->checksum, 0, 12) }}…</div>
        </div>
        <div>
            @if ($canDownload)
                <a href="{{ route('documents.download', [$document]) }}" class="btn btn-sm btn-primary"><i class="bi bi-download"></i> Version courante</a>
            @endif
        </div>
    </div>
</div>
@else
<div class="alert alert-warning py-2 small">
    <i class="bi bi-exclamation-triangle"></i> Ce document n'a pas encore de fichier.
    @if ($contentEditable) Joignez une première version depuis l'onglet <strong>Versions</strong> ci-dessous. @endif
</div>
@endif

<ul class="nav nav-tabs" id="docTabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-preview">{{ __('Aperçu') }}</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-versions">{{ __('Versions') }}</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-metadata">{{ __('Métadonnées') }}</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-workflow">{{ __('Workflow') }}</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-ai">{{ __('IA') }}</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-comments">{{ __('Commentaires') }}</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-share">{{ __('Partage') }}</a></li>
</ul>

<div class="tab-content card p-3">
    <div class="tab-pane fade show active" id="tab-preview">
        @if (str_starts_with($document->currentVersion?->mime_type ?? '', 'image/'))
            <img src="{{ route('documents.download', $document) }}" class="img-fluid" alt="aperçu">
        @elseif ($viewerType !== null)
            <iframe src="{{ route('office.viewer', $document) }}" class="w-100 border-0" style="height: 650px" title="Aperçu"></iframe>
        @elseif (($document->currentVersion?->mime_type ?? '') === 'text/plain' || str_ends_with($document->currentVersion?->file_name ?? '', '.txt'))
            <pre class="mb-0">{{ $document->currentVersion->extracted_text ?: 'Pas de contenu textuel indexé.' }}</pre>
        @else
            <p class="text-muted mb-0">Aperçu non disponible pour ce format. <a href="{{ route('documents.download', $document) }}">Télécharger</a> pour consulter.</p>
        @endif
    </div>

    <div class="tab-pane fade" id="tab-versions">
        @if ($contentEditable)
            <form method="POST" action="{{ route('documents.upload-version', $document) }}" enctype="multipart/form-data" class="mb-3">
                @csrf
                <div class="row g-2">
                    <div class="col-md-4"><input type="file" name="file" class="form-control" required></div>
                    <div class="col-md-5"><input type="text" name="comment" class="form-control" placeholder="Commentaire (ex: « [mineur] correction »)"></div>
                    <div class="col-md-3"><button class="btn btn-primary w-100">Créer une version</button></div>
                </div>
            </form>
        @elseif ($lifecycleFrozen)
            <div class="alert alert-warning py-2 small">
                <i class="bi bi-lock"></i> Document verrouillé (statut « {{ $document->statusLabel() }} ») —
                le contenu ne peut plus être modifié. Une <strong>nouvelle révision</strong> (nouveau cycle de validation) est requise.
            </div>
        @else
            <div class="alert alert-info py-2 small">
                <i class="bi bi-info-circle"></i> Vous n'avez pas la permission de modifier le contenu de ce document.
            </div>
        @endif
        <table class="table table-sm">
            <thead><tr><th>Version</th><th>Commentaire</th><th>Auteur</th><th>Date</th><th>Taille</th><th></th></tr></thead>
            <tbody>
            @foreach ($document->versions as $v)
                <tr @if ($v->id === $document->current_version_id) class="table-primary" @endif>
                    <td><strong>v{{ $v->version }}</strong></td>
                    <td class="small">{{ $v->comment }}</td>
                    <td class="small">{{ $v->creator->name ?? '—' }}</td>
                    <td class="small">{{ $v->created_at->format('d/m/Y H:i') }}</td>
                    <td class="small">{{ number_format($v->size / 1024, 1) }} Ko</td>
                    <td class="text-end">
                        @if ($canDownload)<a href="{{ route('documents.download-version', [$document, $v]) }}" class="btn btn-sm btn-outline-secondary">Télécharger</a>@endif
                        @if ($canEdit && $v->id !== $document->current_version_id)
                            <form method="POST" action="{{ route('documents.restore-version', [$document, $v]) }}" class="d-inline">@csrf
                                <button class="btn btn-sm btn-outline-primary" onclick="return confirm('Restaurer cette version (nouvelle version courante) ?')">Restaurer</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="tab-pane fade" id="tab-metadata">
        @if (! $metadataEditable)
            <div class="alert alert-{{ $lifecycleFrozen ? 'warning' : 'info' }} py-2 small">
                <i class="bi {{ $lifecycleFrozen ? 'bi-lock' : 'bi-info-circle' }}"></i>
                @if ($lifecycleFrozen)
                    Document verrouillé (statut « {{ $document->statusLabel() }} ») — les caractéristiques
                    ne peuvent plus être modifiées. Une <strong>nouvelle révision</strong> est requise.
                @else
                    Vous n'avez pas la permission de modifier les caractéristiques de ce document.
                @endif
            </div>
            <dl class="row small mb-0">
                <dt class="col-sm-3">Titre</dt><dd class="col-sm-9">{{ $document->title }}</dd>
                <dt class="col-sm-3">Référence</dt><dd class="col-sm-9">{{ $document->reference ?? '—' }}</dd>
                <dt class="col-sm-3">Espace</dt><dd class="col-sm-9">{{ $document->space?->name ?? '—' }}</dd>
                <dt class="col-sm-3">Dossier</dt><dd class="col-sm-9">{{ $document->folder?->name ?? '—' }}</dd>
                <dt class="col-sm-3">Type documentaire</dt><dd class="col-sm-9">{{ $document->type?->name ?? '—' }}</dd>
                <dt class="col-sm-3">Domaine</dt><dd class="col-sm-9">{{ $document->domain?->name ?? '—' }}</dd>
                <dt class="col-sm-3">Processus</dt><dd class="col-sm-9">{{ $document->process?->name ?? '—' }}</dd>
                @if ($document->referentials->isNotEmpty())
                    <dt class="col-sm-3">Application</dt>
                    <dd class="col-sm-9">{{ $document->referentials->pluck('name')->implode(', ') }}</dd>
                @endif
                <dt class="col-sm-3">Confidentialité</dt><dd class="col-sm-9">{{ $document->confidentiality }}</dd>
                <dt class="col-sm-3">Expiration</dt><dd class="col-sm-9">{{ $document->expiration_at?->format('d/m/Y') ?? '—' }}</dd>
                @if ($document->tags->isNotEmpty())
                    <dt class="col-sm-3">Tags</dt><dd class="col-sm-9">{{ $document->tags->pluck('name')->implode(', ') }}</dd>
                @endif
            </dl>
        @else
        <form method="POST" action="{{ route('documents.metadata', $document) }}">
            @csrf
            <div class="row">
                <div class="col-md-6 mb-2"><label class="form-label small">Titre</label>
                    <input type="text" name="title" class="form-control form-control-sm" value="{{ $document->title }}" required></div>
                <div class="col-md-6 mb-2"><label class="form-label small">Référence</label>
                    <input type="text" name="reference" class="form-control form-control-sm" value="{{ $document->reference }}"></div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-2"><label class="form-label small">Espace</label>
                    <select name="space_id" id="metaSpace" class="form-select form-select-sm" required>
                        @foreach ($spaces as $s)<option value="{{ $s->id }}" @selected($document->space_id === $s->id)>{{ $s->name }}</option>@endforeach
                    </select></div>
                <div class="col-md-4 mb-2"><label class="form-label small">Dossier</label>
                    <select name="folder_id" id="metaFolder" class="form-select form-select-sm">
                        <option value="">— Aucun —</option>
                        @foreach ($folders as $f)
                            <option value="{{ $f->id }}" data-space="{{ $f->space_id }}" @selected($document->folder_id === $f->id)>{{ str_repeat('— ', $f->depth() - 1) }}{{ $f->name }}</option>
                        @endforeach
                    </select></div>
                <div class="col-md-4 mb-2"><label class="form-label small">Type documentaire</label>
                    <select name="document_type_id" class="form-select form-select-sm">
                        <option value="">— Sans type —</option>
                        @foreach ($types as $t)<option value="{{ $t->id }}" @selected($document->document_type_id === $t->id)>{{ $t->name }}</option>@endforeach
                    </select></div>
                <div class="col-md-3 mb-2"><label class="form-label small">Domaine</label>
                    <select name="domain_id" class="form-select form-select-sm">
                        <option value="">— Aucun —</option>
                        @foreach ($domains as $d)<option value="{{ $d->id }}" @selected($document->domain_id === $d->id)>{{ $d->name }}</option>@endforeach
                    </select></div>
                <div class="col-md-3 mb-2"><label class="form-label small">Processus</label>
                    <select name="process_id" class="form-select form-select-sm">
                        <option value="">— Aucun —</option>
                        @foreach ($processes as $p)<option value="{{ $p->id }}" @selected($document->process_id === $p->id)>{{ $p->name }}</option>@endforeach
                    </select></div>
            </div>
            <div class="row">
                @foreach ($applicationTypes as $type)
                    @if (in_array($type, ['domain', 'process'], true)) @continue @endif
                    @php $current = $document->referentials->firstWhere('type', $type); @endphp
                    <div class="col-md-4 mb-2">
                        <label class="form-label small text-capitalize">{{ $type }}</label>
                        <select name="application[{{ $type }}]" class="form-select form-select-sm">
                            <option value="">— Aucun —</option>
                            @foreach (($applicationRefs[$type] ?? collect()) as $r)
                                <option value="{{ $r->id }}" @selected($current?->id === $r->id)>{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            <div class="row">
                <div class="col-md-4 mb-2"><label class="form-label small">Confidentialité</label>
                    <select name="confidentiality" class="form-select form-select-sm">
                        @foreach (['public', 'internal', 'confidential', 'secret'] as $c)<option value="{{ $c }}" @selected($document->confidentiality === $c)>{{ $c }}</option>@endforeach
                    </select></div>
                <div class="col-md-4 mb-2"><label class="form-label small">Expiration</label>
                    <input type="date" name="expiration_at" class="form-control form-control-sm" value="{{ $document->expiration_at?->format('Y-m-d') }}"></div>
            </div>
            @foreach ($definitions as $def)
                <div class="mb-2">
                    <label class="form-label small">{{ $def->name }}</label>
                    <input type="text" name="metadata[{{ $def->id }}]" class="form-control form-control-sm" value="{{ $metadata[$def->id] ?? '' }}">
                </div>
            @endforeach
            <div class="mb-2">
                <label class="form-label small">Tags</label>
                <input type="text" name="tags[]" class="form-control form-control-sm" value="{{ $document->tags->pluck('name')->implode(', ') }}">
            </div>
            <button class="btn btn-sm btn-primary">Enregistrer</button>
        </form>
        @endif
    </div>

    <div class="tab-pane fade" id="tab-workflow">
        @if ($workflow && $workflow->status === 'active')
            <p><strong>{{ $workflow->workflow->name }}</strong> — statut : <span class="badge bg-warning">actif</span></p>
            <p class="small text-muted">Étape courante : {{ $workflow->currentStep->name ?? '—' }}</p>
            <table class="table table-sm">
                <thead><tr><th>Étape</th><th>Assigné à</th><th>Statut</th><th>Décision</th></tr></thead>
                <tbody>
                @foreach ($workflow->tasks as $t)
                    <tr>
                        <td>{{ $t->step->name }}</td>
                        <td class="small">{{ $t->assignee->name ?? '—' }}</td>
                        <td><span class="badge bg-{{ $t->status === 'pending' ? 'warning' : 'success' }}">{{ $t->status }}</span></td>
                        <td class="small">{{ $t->decision_comment }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <form method="POST" action="{{ route('workflows.start', $document) }}" class="d-flex gap-2">
                @csrf
                <select name="workflow_id" class="form-select" required>
                    @foreach ($activeWorkflows as $wf)<option value="{{ $wf->id }}">{{ $wf->name }}</option>@endforeach
                </select>
                <button class="btn btn-primary">Démarrer le workflow</button>
            </form>
            @if ($document->status === 'approved' && $activeWorkflows->isNotEmpty())
                <div class="alert alert-info py-2 small mt-2 mb-0">
                    <i class="bi bi-arrow-repeat"></i> Le document est approuvé — relancez un workflow
                    (nouvelle révision) avec le formulaire ci-dessus.
                </div>
            @endif
        @endif

        @if (count($workflowHistory) > 0)
            <hr>
            <h6 class="small text-muted">Historique des instances</h6>
            @foreach ($workflowHistory as $h)
                <div class="border rounded p-2 mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <strong class="small">{{ $h->workflow->name }}</strong>
                        <span class="badge bg-{{ $h->status === 'active' ? 'warning' : ($h->status === 'completed' ? 'success' : 'secondary') }}">{{ $h->status }}</span>
                    </div>
                    <div class="small text-muted">Démarré par {{ $h->creator->name ?? '—' }} le {{ $h->created_at->format('d/m/Y H:i') }}</div>
                    @foreach ($h->tasks as $t)
                        <div class="small ms-2">
                            <i class="bi bi-arrow-return-right"></i> {{ $t->step->name }} — {{ $t->status }}
                            @if ($t->actor) · {{ $t->actor->name }} le {{ $t->acted_at?->format('d/m/Y H:i') }}@endif
                            @if ($t->decision_comment) — « {{ $t->decision_comment }} »@endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif
    </div>

    <div class="tab-pane fade" id="tab-ai">
        @if ($canAi)
            <div class="row g-2 mb-3">
                @foreach (['summary' => 'Résumé', 'qa' => 'Question/Réponse', 'classification' => 'Classification', 'extraction' => 'Extraction', 'correction' => 'Correction', 'translation' => 'Traduction'] as $k => $label)
                    <div class="col-auto">
                        <form method="POST" action="{{ route('ai.dispatch', $document) }}">@csrf
                            <input type="hidden" name="job_type" value="{{ $k }}">
                            <button class="btn btn-sm btn-outline-primary">{{ $label }}</button>
                        </form>
                    </div>
                @endforeach
                <div class="col-auto">
                    <form method="POST" action="{{ route('ai.ocr', $document) }}">@csrf
                        <button class="btn btn-sm btn-outline-secondary">OCR</button>
                    </form>
                </div>
            </div>
        @endif
        @foreach ($jobs as $job)
            <div class="border rounded p-2 mb-2">
                <div class="d-flex justify-content-between">
                    <strong class="small">{{ $job->job_type }}</strong>
                    <span class="badge bg-{{ $job->status === 'succeeded' ? 'success' : ($job->status === 'failed' ? 'danger' : 'warning') }}">{{ $job->status }}</span>
                </div>
                <div class="small text-muted">Fournisseur : {{ $job->provider }} · {{ $job->created_at->diffForHumans() }}</div>
                @if ($job->error)<div class="small text-danger mt-1">{{ $job->error }}</div>@endif
                @if ($job->result)
                    <pre class="small bg-light p-2 mt-2 mb-1" style="white-space:pre-wrap">{{ json_encode($job->result->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    <div class="small text-muted">Confiance : {{ $job->result->confidence }} · Validé : {{ $job->result->validated_at ? 'oui' : 'non' }}</div>
                    @if (!$job->result->validated_at)
                        <div class="mt-2">
                            <form method="POST" action="{{ route('ai.results.validate', $job->result) }}" class="d-inline">@csrf
                                <input type="hidden" name="accepted" value="1">
                                <button class="btn btn-sm btn-success">Valider</button>
                            </form>
                            <form method="POST" action="{{ route('ai.results.validate', $job->result) }}" class="d-inline">@csrf
                                <input type="hidden" name="accepted" value="0">
                                <button class="btn btn-sm btn-outline-danger">Rejeter</button>
                            </form>
                        </div>
                    @endif
                @endif
            </div>
        @endforeach
    </div>

    <div class="tab-pane fade" id="tab-comments">
        @if ($canComment)
            <form method="POST" action="{{ route('documents.comment', $document) }}" class="mb-3 d-flex gap-2">
                @csrf
                <input type="text" name="body" class="form-control" placeholder="Votre commentaire…" required>
                <button class="btn btn-primary">Commenter</button>
            </form>
        @endif
        @foreach ($document->comments as $c)
            <div class="border-bottom py-2">
                <strong class="small">{{ $c->user->name }}</strong> <span class="small text-muted">· {{ $c->created_at->diffForHumans() }}</span>
                <div class="small">{{ $c->body }}</div>
            </div>
        @endforeach
    </div>

    <div class="tab-pane fade" id="tab-share">
        @if ($canShare)
            <form method="POST" action="{{ route('documents.share', $document) }}" class="row g-2 mb-3">
                @csrf
                <div class="col-md-4">
                    <select name="shared_with_user_id" class="form-select">
                        <option value="">— Utilisateur —</option>
                        @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="shared_with_group_id" class="form-select">
                        <option value="">— Groupe —</option>
                        @foreach ($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="permission" class="form-select">
                        @foreach (['view', 'download', 'edit'] as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2"><input type="date" name="expires_at" class="form-control" title="Expiration"></div>
                <div class="col-md-1"><button class="btn btn-primary w-100">Partager</button></div>
            </form>

            @if ($externalSharingEnabled)
                <hr>
                <h6 class="small text-muted">Lien externe (hors organisation)</h6>
                <form method="POST" action="{{ route('documents.share', $document) }}" class="row g-2">
                    @csrf
                    <input type="hidden" name="external" value="1">
                    <div class="col-md-3">
                        <select name="permission" class="form-select">
                            <option value="view">Consultation</option>
                            <option value="download">Téléchargement</option>
                        </select>
                    </div>
                    <div class="col-md-3"><input type="date" name="expires_at" class="form-control" required title="Expiration (obligatoire)"></div>
                    <div class="col-md-3"><input type="password" name="password" class="form-control" placeholder="Mot de passe (optionnel)"></div>
                    <div class="col-md-3"><button class="btn btn-outline-primary w-100">Créer le lien</button></div>
                </form>
                <p class="small text-muted mt-2 mb-0">Le lien expire au plus tard selon la politique de l'organisation. Accès par jeton sécurisé.</p>
            @endif
        @endif
        <table class="table table-sm">
            <thead><tr><th>Bénéficiaire</th><th>Droit</th><th>Expire</th><th></th></tr></thead>
            <tbody>
            @foreach ($document->shares as $s)
                <tr @if (!$s->isActive()) class="text-muted" @endif>
                    <td>
                        @if ($s->is_external)
                            <span class="badge bg-info">lien externe</span>
                        @else
                            {{ $s->user->name ?? ($s->group->name ?? '—') }}
                        @endif
                    </td>
                    <td>{{ $s->permission }}</td>
                    <td class="small">{{ $s->expires_at?->format('d/m/Y') ?? '—' }}</td>
                    <td class="text-end">
                        @if ($s->isActive())
                            <form method="POST" action="{{ route('shares.revoke', [$document, $s]) }}">@csrf
                                <button class="btn btn-sm btn-outline-danger">Révoquer</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

@if ($document->space?->is_personal && $document->space->personal_user_id === auth()->id())
<div class="modal fade" id="publishPersonal" tabindex="-1"><div class="modal-dialog"><form method="POST" action="{{ route('documents.publish', $document) }}" class="modal-content">@csrf
    <div class="modal-header"><h5 class="modal-title">Publier dans un espace partagé</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><select name="space_id" class="form-select" required><option value="">Choisir un espace…</option>@foreach ($spaces as $space)<option value="{{ $space->id }}">{{ $space->name }}</option>@endforeach</select></div>
    <div class="modal-footer"><button class="btn btn-primary">Publier</button></div>
</form></div></div>
@endif

<script>
document.addEventListener('DOMContentLoaded', () => {
    const spaceSelect = document.getElementById('metaSpace');
    const folderSelect = document.getElementById('metaFolder');

    if (!spaceSelect || !folderSelect) return;

    const allOptions = Array.from(folderSelect.options);

    function filterFolders() {
        const spaceId = spaceSelect.value;
        const selected = folderSelect.value;

        folderSelect.innerHTML = '';
        folderSelect.appendChild(allOptions[0].cloneNode(true));

        allOptions.slice(1)
            .filter(o => o.dataset.space === spaceId)
            .forEach(o => folderSelect.appendChild(o.cloneNode(true)));

        if (allOptions.some(o => o.value === selected && o.dataset.space === spaceId)) {
            folderSelect.value = selected;
        }
    }

    spaceSelect.addEventListener('change', filterFolders);
    filterFolders();
});
</script>
@endsection
