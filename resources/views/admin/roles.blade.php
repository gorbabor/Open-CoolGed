@extends('layouts.app')

@section('title', 'Administration — Rôles et permissions')

@section('content')
<h4 class="mb-3">Rôles et permissions (RBAC)</h4>

<div class="card p-3 mb-3">
    <form method="POST" action="{{ route('admin.roles.store') }}" class="d-flex gap-2">
        @csrf
        <input type="text" name="name" class="form-control" placeholder="Nom du rôle personnalisé" required>
        <button class="btn btn-primary">Créer</button>
    </form>
</div>

@foreach ($roles as $role)
    <div class="card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-start">
            <h6 class="mb-0">{{ $role->name }}
                @if ($role->is_system)<span class="badge bg-light text-dark">système</span>@endif
            </h6>
            <div class="d-flex gap-1">
                @if (!$role->is_system)
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#renameRole{{ $role->id }}">Renommer</button>
                    <form method="POST" action="{{ route('admin.roles.duplicate', $role) }}">@csrf
                        <button class="btn btn-sm btn-outline-secondary">Dupliquer</button>
                    </form>
                    <form method="POST" action="{{ route('admin.roles.delete', $role) }}"
                          onsubmit="return confirm('Supprimer ce rôle ?')">@csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Supprimer</button>
                    </form>
                @endif
            </div>
        </div>
        <form method="POST" action="{{ route('admin.roles.update', $role) }}">
            @csrf
            <table class="table table-sm mt-2">
                <thead><tr><th>Permission</th><th>Portée</th><th>Refus explicite</th></tr></thead>
                <tbody>
                @foreach ($permissions->groupBy('group') as $group => $perms)
                    <tr class="table-light"><td colspan="3"><strong>{{ $group ?: 'Général' }}</strong></td></tr>
                    @foreach ($perms as $p)
                        @php $rp = $role->permissions->firstWhere('slug', $p->slug); @endphp
                        <tr>
                            <td>{{ $p->name }} <code class="small text-muted">{{ $p->slug }}</code></td>
                            <td>
                                <select name="permissions[{{ $p->slug }}][scope_type]" class="form-select form-select-sm">
                                    @foreach (['tenant' => 'Tenant', 'space' => 'Espace', 'folder' => 'Dossier', 'document' => 'Document'] as $k => $v)
                                        <option value="{{ $k }}" @selected($rp?->pivot->scope_type === $k)>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="permissions[{{ $p->slug }}][scope_id]" class="form-select form-select-sm">
                                    <option value="">— (tous)</option>
                                    @foreach ($spaces as $s)<option value="{{ $s->id }}" @selected($rp?->pivot->scope_id == $s->id && $rp?->pivot->scope_type === 'space')>Espace : {{ $s->name }}</option>@endforeach
                                </select>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input" name="permissions[{{ $p->slug }}][deny]" value="1" @checked($rp?->pivot->denied)>
                            </td>
                        </tr>
                    @endforeach
                @endforeach
                </tbody>
            </table>
            <button class="btn btn-sm btn-primary">Enregistrer les permissions</button>
        </form>
    </div>
    @if (!$role->is_system)
        <div class="modal fade" id="renameRole{{ $role->id }}">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('admin.roles.rename', $role) }}" class="modal-content">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">Renommer le rôle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <input type="text" name="name" class="form-control" value="{{ $role->name }}" required>
                    </div>
                    <div class="modal-footer"><button class="btn btn-primary">Enregistrer</button></div>
                </form>
            </div>
        </div>
    @endif
@endforeach
@endsection
