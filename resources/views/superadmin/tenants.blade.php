@extends('layouts.app')

@section('title', 'Super Admin — Tenants')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Super Administration — Plateforme</h4>
    <a href="{{ route('superadmin.settings') }}" class="btn btn-outline-primary"><i class="bi bi-gear"></i> Paramètres plateforme</a>
</div>

<div class="row mb-4">
    @foreach ($stats as $label => $value)
        <div class="col-md-3">
            <div class="card p-3 text-center">
                <div class="text-muted small">{{ ucfirst($label) }}</div>
                <div class="fs-4 fw-bold">{{ $value }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="card p-3 mb-3">
    <h6>Créer un tenant</h6>
    <form method="POST" action="{{ route('superadmin.tenants.store') }}" class="row g-2">
        @csrf
        <div class="col-md-4"><input type="text" name="name" class="form-control" placeholder="Nom de l'entreprise" required></div>
        <div class="col-md-2"><input type="text" name="plan" class="form-control" placeholder="Plan"></div>
        <div class="col-md-2"><input type="number" name="storage_quota_mb" class="form-control" placeholder="Quota Mo" value="5120"></div>
        <div class="col-md-2"><input type="number" name="user_quota" class="form-control" placeholder="Utilisateurs" value="50"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Créer</button></div>
        <div class="col-12">
            <div class="form-text mb-1">Admin initial (optionnel) — sinon créer l'admin ensuite via « Créer un admin » sur la ligne du tenant.</div>
            <div class="row g-2">
                <div class="col-md-4"><input type="text" name="admin_name" class="form-control" placeholder="Nom de l'admin (optionnel)"></div>
                <div class="col-md-4"><input type="email" name="admin_email" class="form-control" placeholder="Email de l'admin (optionnel)"></div>
                <div class="col-md-4"><input type="password" name="admin_password" class="form-control" placeholder="Mot de passe ≥ 10 caractères (optionnel)"></div>
            </div>
        </div>
    </form>
</div>

<div class="card p-3 mb-3">
    <h6>Créer un super administrateur</h6>
    <form method="POST" action="{{ route('superadmin.superadmins.store') }}" class="row g-2">
        @csrf
        <div class="col-md-3"><input type="text" name="name" class="form-control" placeholder="Nom" required></div>
        <div class="col-md-3"><input type="email" name="email" class="form-control" placeholder="Email" required></div>
        <div class="col-md-3"><input type="password" name="password" class="form-control" placeholder="Mot de passe" required></div>
        <div class="col-md-3"><button class="btn btn-outline-primary w-100">Créer</button></div>
    </form>
</div>

<div class="card p-3 mb-3">
    <h6>Super administrateurs ({{ $superAdmins->count() }})</h6>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>Nom</th><th>Email</th><th>Statut</th><th>Dernière connexion</th><th></th></tr></thead>
        <tbody>
        @forelse ($superAdmins as $sa)
            <tr>
                <td>{{ $sa->name }}</td>
                <td>{{ $sa->email }}</td>
                <td><span class="badge bg-{{ $sa->isSuspended() ? 'danger' : 'success' }}">{{ $sa->status }}</span></td>
                <td class="small text-muted">{{ $sa->last_login_at?->diffForHumans() ?? 'jamais' }}</td>
                <td class="text-end">
                    <form method="POST" action="{{ route('superadmin.users.toggle', $sa) }}">@csrf
                        <button class="btn btn-sm btn-outline-{{ $sa->isSuspended() ? 'success' : 'danger' }}"
                            onclick="return confirm('{{ $sa->isSuspended() ? 'Réactiver' : 'Suspendre' }} ce super administrateur ?')">
                            {{ $sa->isSuspended() ? 'Réactiver' : 'Suspendre' }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-3">Aucun super administrateur.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="card">
    <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Tenant</th><th>Plan</th><th>Statut</th><th>Utilisateurs</th><th>Quota stockage</th><th></th></tr></thead>
        <tbody>
        @foreach ($tenants as $t)
            <tr>
                <td>{{ $t->name }}</td>
                <td>{{ $t->plan }}</td>
                <td><span class="badge bg-{{ $t->isSuspended() ? 'danger' : 'success' }}">{{ $t->status }}</span></td>
                <td>{{ $t->users_count }}</td>
                <td>{{ $t->storage_quota_mb }} Mo</td>
                <td>
                    <div class="d-flex gap-1 justify-content-end">
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#adminModal{{ $t->id }}"><i class="bi bi-person-plus"></i> Créer un admin</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#resetTenant{{ $t->id }}"><i class="bi bi-arrow-counterclockwise"></i> Réinitialiser</button>
                        <form method="POST" action="{{ route('superadmin.tenants.toggle', $t) }}">@csrf
                            <button class="btn btn-sm btn-outline-{{ $t->isSuspended() ? 'success' : 'danger' }}">
                                {{ $t->isSuspended() ? 'Réactiver' : 'Suspendre' }}</button>
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

@foreach ($tenants as $t)
    <div class="modal fade" id="adminModal{{ $t->id }}" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('superadmin.tenants.admin', $t) }}" class="modal-content">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Créer un admin — {{ $t->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nom *</label>
                        <input type="text" name="name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Mot de passe * (≥ 10 caractères)</label>
                        <input type="password" name="password" class="form-control" required minlength="10"></div>
                </div>
                <div class="modal-footer"><button class="btn btn-primary">Créer et rattacher</button></div>
            </form>
        </div>
    </div>
@endforeach

@foreach ($tenants as $t)
    <div class="modal fade" id="resetTenant{{ $t->id }}" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('superadmin.tenants.reset', $t) }}" class="modal-content">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Réinitialiser — {{ $t->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 small">
                        Purge irréversible du contenu : documents, versions, fichiers, espaces, dossiers, types,
                        métadonnées, tags, référentiels, workflows, notifications, partages, audits, jobs IA.
                        Sont conservés : le tenant, ses utilisateurs, rôles, paramètres et branding.
                        <strong>Une sauvegarde est créée automatiquement avant le reset.</strong>
                    </div>
                    <div class="mb-2"><label class="form-label small">Saisir le slug pour confirmer : <code>{{ $t->slug }}</code></label>
                        <input type="text" name="confirm" class="form-control" required autocomplete="off"></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-danger">Réinitialiser le contenu</button></div>
            </form>
        </div>
    </div>
@endforeach
@endsection
