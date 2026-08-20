@extends('layouts.app')

@section('title', 'Workflows')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Workflows</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newWorkflow"><i class="bi bi-plus-lg"></i> Nouveau workflow</button>
</div>

@foreach ($workflows as $wf)
    <div class="card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-0">{{ $wf->name }}
                    <span class="badge bg-{{ $wf->is_active ? 'success' : 'secondary' }} ms-2">{{ $wf->is_active ? 'actif' : 'inactif' }}</span>
                    @if ($wf->documentType)<span class="badge bg-light text-dark ms-1">{{ $wf->documentType->name }}</span>@endif
                </h6>
                <div class="small text-muted mt-1">
                    @foreach ($wf->steps as $i => $step)
                        {{ $i > 0 ? ' → ' : '' }}<strong>{{ $step->name }}</strong>
                        <span class="text-secondary">({{ $step->assignee_type }})</span>
                    @endforeach
                </div>
            </div>
            <form method="POST" action="{{ route('workflows.toggle', $wf) }}">@csrf
                <button class="btn btn-sm btn-outline-secondary">{{ $wf->is_active ? 'Désactiver' : 'Activer' }}</button>
            </form>
        </div>
    </div>
@endforeach

<div class="modal fade" id="newWorkflow">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('workflows.store') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Nouveau workflow</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6"><label class="form-label">Nom *</label>
                        <input type="text" name="name" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Type documentaire</label>
                        <select name="document_type_id" class="form-select">
                            <option value="">Tous</option>
                            @foreach ($types as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                        </select></div>
                </div>
                <h6>Étapes</h6>
                <div id="steps">
                    <div class="row g-2 mb-2 step-row">
                        <div class="col-md-5"><input type="text" name="steps[0][name]" class="form-control" placeholder="Nom de l'étape" required></div>
                        <div class="col-md-4">
                            <select name="steps[0][assignee_type]" class="form-select assignee-type">
                                <option value="user">Utilisateur</option>
                                <option value="group">Groupe</option>
                                <option value="role">Rôle</option>
                                <option value="creator">Créateur</option>
                            </select>
                        </div>
                        <div class="col-md-3"><input type="number" name="steps[0][assignee_id]" class="form-control" placeholder="ID (optionnel)"></div>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addStep"><i class="bi bi-plus"></i> Étape</button>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Créer le workflow</button></div>
        </form>
    </div>
</div>

<script>
let stepIndex = 1;
document.getElementById('addStep').addEventListener('click', () => {
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2 step-row';
    row.innerHTML = `
        <div class="col-md-5"><input type="text" name="steps[${stepIndex}][name]" class="form-control" placeholder="Nom de l'étape" required></div>
        <div class="col-md-4"><select name="steps[${stepIndex}][assignee_type]" class="form-select">
            <option value="user">Utilisateur</option><option value="group">Groupe</option>
            <option value="role">Rôle</option><option value="creator">Créateur</option></select></div>
        <div class="col-md-3"><input type="number" name="steps[${stepIndex}][assignee_id]" class="form-control" placeholder="ID (optionnel)"></div>`;
    document.getElementById('steps').appendChild(row);
    stepIndex++;
});
</script>
@endsection
