@extends('layouts.app')

@section('title', 'Administration — Utilisateurs')

@section('content')
<h4 class="mb-3">Utilisateurs <span class="text-muted fs-6">({{ $users->count() }} / {{ auth()->user()->tenant->user_quota }})</span></h4>

<div class="card p-3 mb-3">
    <h6>Créer un utilisateur</h6>
    <form method="POST" action="{{ route('admin.users.store') }}" class="row g-2">
        @csrf
        <div class="col-md-3"><input type="text" name="name" class="form-control" placeholder="Nom" required></div>
        <div class="col-md-3"><input type="email" name="email" class="form-control" placeholder="Email" required></div>
        <div class="col-md-3">
            <select name="role_id" class="form-select" required>
                <option value="">Rôle…</option>
                @foreach ($roles as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2"><input type="password" name="password" class="form-control" placeholder="Mot de passe"></div>
        <div class="col-md-1"><button type="submit" class="btn btn-primary w-100">Créer</button></div>
        @foreach (['job' => 'Poste', 'department' => 'Département', 'direction' => 'Direction', 'site' => 'Site', 'entity' => 'Entité', 'country' => 'Pays'] as $type => $label)
            <div class="col-md-2">
                <select name="{{ $type }}_id" class="form-select">
                    <option value="">{{ $label }}…</option>
                    @foreach (($dimensionRefs[$type] ?? collect()) as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
                </select>
            </div>
        @endforeach
    </form>
</div>

<div class="card">
    <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Nom</th><th>Email</th><th>Rôles</th><th>Groupes</th><th>Permissions effectives</th><th>Statut</th><th>Dernière connexion</th><th></th></tr></thead>
        <tbody>
        @foreach ($users as $u)
            <tr>
                <td>{{ $u->name }}</td>
                <td>{{ $u->email }}</td>
                <td class="small">@foreach ($u->roles as $r)<span class="badge bg-info me-1">{{ $r->name }}</span>@endforeach</td>
                <td class="small">@foreach ($u->groups as $g)<span class="badge bg-light text-dark me-1">{{ $g->name }}</span>@endforeach</td>
                <td class="small">
                    @php $perms = $effectivePermissions[$u->id] ?? []; @endphp
                    @if ($perms)
                        <span class="text-muted" title="{{ implode(', ', $perms) }}">{{ count($perms) }} permissions</span>
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            @foreach (array_slice($perms, 0, 5) as $p)
                                <span class="badge bg-secondary-subtle text-secondary">{{ $p }}</span>
                            @endforeach
                            @if (count($perms) > 5)<span class="badge bg-light text-muted">+{{ count($perms) - 5 }}</span>@endif
                        </div>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td><span class="badge bg-{{ $u->isSuspended() ? 'danger' : 'success' }}">{{ $u->status }}</span></td>
                <td class="small text-muted">{{ $u->last_login_at?->diffForHumans() ?? 'jamais' }}</td>
                <td>
                    <div class="d-flex gap-1">
                        <form method="POST" action="{{ route('admin.users.toggle', $u) }}">@csrf
                            <button type="button" class="btn btn-sm btn-outline-{{ $u->isSuspended() ? 'success' : 'danger' }}">
                                {{ $u->isSuspended() ? 'Réactiver' : 'Suspendre' }}</button>
                        </form>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUser{{ $u->id }}">Modifier</button>
                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resetUser{{ $u->id }}">MDP</button>
                        <form method="POST" action="{{ route('admin.users.delete', $u) }}"
                              onsubmit="return confirm('Supprimer cet utilisateur (logique) ?')">@csrf @method('DELETE')
                            <button type="button" class="btn btn-sm btn-outline-danger">Suppr.</button>
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

@foreach ($users as $u)
    <div class="modal fade" id="editUser{{ $u->id }}" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('admin.users.update', $u) }}" class="modal-content">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Modifier {{ $u->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label small">Nom</label>
                        <input type="text" name="name" class="form-control" value="{{ $u->name }}" required></div>
                    <div class="mb-2"><label class="form-label small">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ $u->email }}" required></div>
                    <div class="mb-2"><label class="form-label small">Rôle</label>
                        <select name="role_id" class="form-select">
                            @foreach ($roles as $r)<option value="{{ $r->id }}" @selected($u->roles->contains('id', $r->id))>{{ $r->name }}</option>@endforeach
                        </select></div>
                    <div class="mb-2"><label class="form-label small">Groupes</label>
                        <select name="group_ids[]" class="form-select" multiple size="4">
                            @foreach ($groups as $g)<option value="{{ $g->id }}" @selected($u->groups->contains('id', $g->id))>{{ $g->name }}</option>@endforeach
                        </select></div>
                    <div class="mb-2"><label class="form-label small">Dimensions V02</label>
                        <div class="row g-1">
                            @foreach (['job' => 'Poste', 'department' => 'Département', 'direction' => 'Direction', 'site' => 'Site', 'entity' => 'Entité', 'country' => 'Pays'] as $type => $label)
                                <div class="col-6">
                                    <label class="form-label small text-muted text-capitalize">{{ $label }}</label>
                                    <select name="{{ $type }}_id" class="form-select form-select-sm">
                                        <option value="">— Aucun —</option>
                                        @foreach (($dimensionRefs[$type] ?? collect()) as $r)
                                            <option value="{{ $r->id }}" @selected($u->{$type.'_id'} === $r->id)>{{ $r->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Enregistrer</button></div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="resetUser{{ $u->id }}" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('admin.users.reset-password', $u) }}" class="modal-content">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Réinitialiser le mot de passe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="password" name="password" class="form-control" placeholder="Nouveau mot de passe" required minlength="8">
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-warning">Réinitialiser</button></div>
            </form>
        </div>
    </div>
@endforeach
@endsection
