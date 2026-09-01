@extends('layouts.app')

@section('title', __('Nouveau document'))

@section('content')
<h4 class="mb-3">{{ __('Importer un document') }}</h4>
<form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="card p-4">
    @csrf
    <div class="row">
        <div class="col-12 mb-3">
            <label class="form-label">Emplacement</label>
            <div class="btn-group" role="group">
                <input type="radio" class="btn-check" name="storage_scope" id="scopePersonal" value="personal" checked>
                <label class="btn btn-outline-primary" for="scopePersonal">Personnel</label>
                <input type="radio" class="btn-check" name="storage_scope" id="scopeShared" value="shared">
                <label class="btn btn-outline-primary" for="scopeShared">Partagé</label>
            </div>
            <div class="form-text">Personnel : visible uniquement par vous. Partagé : visible selon les droits de l’espace.</div>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">{{ __('Titre') }} *</label>
            <input type="text" name="title" class="form-control" required>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">{{ __('Référence') }}</label>
            <input type="text" name="reference" class="form-control">
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">{{ __('Espace') }} *</label>
            <select name="space_id" id="spaceSelect" class="form-select" required>
                @foreach ($spaces as $s)<option value="{{ $s->id }}" @selected(old('space_id') == $s->id)>{{ $s->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">{{ __('Dossier') }}</label>
            <select name="folder_id" id="folderSelect" class="form-select">
                <option value="">— {{ __('Aucun') }} —</option>
                @foreach ($folders as $f)
                    <option value="{{ $f->id }}" data-space="{{ $f->space_id }}" @selected(old('folder_id') == $f->id)>{{ $f->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">{{ __('Type documentaire') }}</label>
            <select name="document_type_id" class="form-select">
                <option value="">— {{ __('Sans type') }} —</option>
                @foreach ($types as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">{{ __('Domaine') }}</label>
            <select name="domain_id" class="form-select">
                <option value="">— {{ __('Aucun') }} —</option>
                @foreach ($domains as $d)<option value="{{ $d->id }}" @selected(old('domain_id') == $d->id)>{{ $d->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">{{ __('Processus') }}</label>
            <select name="process_id" class="form-select">
                <option value="">— {{ __('Aucun') }} —</option>
                @foreach ($processes as $p)<option value="{{ $p->id }}" @selected(old('process_id') == $p->id)>{{ $p->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-12 mb-3">
            <h6>{{ __('Référentiels d\'application') }}</h6>
            <div class="row">
                @foreach ($applicationTypes as $type)
                    @if (in_array($type, ['domain', 'process'], true)) @continue @endif
                    <div class="col-md-4 mb-2">
                        <label class="form-label small text-capitalize">{{ $type }}</label>
                        <select name="application[{{ $type }}]" class="form-select form-select-sm">
                            <option value="">— Aucun —</option>
                            @foreach (($applicationRefs[$type] ?? collect()) as $r)
                                <option value="{{ $r->id }}" @selected(old('application.'.$type) == $r->id)>{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">{{ __('Confidentialité') }}</label>
            <select name="confidentiality" class="form-select">
                @foreach (['public', 'internal', 'confidential', 'secret'] as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
            </select>
        </div>
        <div class="col-12 mb-3">
            <label class="form-label">{{ __('Description') }}</label>
            <textarea name="description" class="form-control" rows="2"></textarea>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">{{ __('Date d\'expiration') }}</label>
            <input type="date" name="expiration_at" class="form-control">
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">{{ __('Fichier') }} * (PDF, Office, TXT, CSV, images)</label>
            <input type="file" name="file" class="form-control" required>
        </div>
        @if ($definitions->isNotEmpty())
            <div class="col-12 mb-3">
                <h6>{{ __('Métadonnées') }}</h6>
                <div class="row">
                    @foreach ($definitions as $def)
                        <div class="col-md-4 mb-2">
                            <label class="form-label small">{{ $def->name }}{{ $def->required ? ' *' : '' }}</label>
                            <input type="text" name="metadata[{{ $def->id }}]" class="form-control form-control-sm" @required($def->required)>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
        <div class="col-md-6 mb-3">
            <label class="form-label">{{ __('Tags') }} ({{ __('séparés par virgule') }})</label>
            <input type="text" name="tags[]" class="form-control" placeholder="contrat, 2026…">
        </div>
    </div>
    <button class="btn btn-primary"><i class="bi bi-upload"></i> {{ __('Importer et créer') }}</button>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const spaceSelect = document.getElementById('spaceSelect');
    const folderSelect = document.getElementById('folderSelect');

    if (!spaceSelect || !folderSelect) return;

    // Keep every option accessible for the filter, then apply it.
    const allOptions = Array.from(folderSelect.options);

    function filterFolders() {
        const spaceId = spaceSelect.value;
        const selected = folderSelect.value;

        folderSelect.innerHTML = '';
        folderSelect.appendChild(allOptions[0].cloneNode(true)); // « — Aucun — »

        allOptions.slice(1)
            .filter(o => o.dataset.space === spaceId)
            .forEach(o => folderSelect.appendChild(o.cloneNode(true)));

        // Restore the previous selection if it still belongs to this space.
        if (allOptions.some(o => o.value === selected && o.dataset.space === spaceId)) {
            folderSelect.value = selected;
        }
    }

    spaceSelect.addEventListener('change', filterFolders);
    filterFolders();
});
</script>
@endsection
