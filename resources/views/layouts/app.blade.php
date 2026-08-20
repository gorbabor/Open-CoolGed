@php
    // Branding par tenant (Administration → Paramètres → Branding) : variables CSS.
    $brandColor = auth()->check() && ! auth()->user()->isSuperAdmin()
        ? (auth()->user()->tenant->branding['color'] ?? null) : null;
    $brandLogo = auth()->check() && ! auth()->user()->isSuperAdmin()
        ? (auth()->user()->tenant->branding['logo_url'] ?? null) : null;
    // Menu Administration : visible uniquement selon les permissions effectives
    // (rôles directs + rôles des groupes) — un utilisateur sans permission admin.*
    // ne voit aucun lien d'administration.
    $adminLinks = [
        ['perm' => 'admin.users', 'label' => 'Utilisateurs', 'icon' => 'bi-people', 'route' => 'admin.users'],
        ['perm' => 'admin.groups', 'label' => 'Groupes', 'icon' => 'bi-diagram-2', 'route' => 'admin.groups'],
        ['perm' => 'admin.roles', 'label' => 'Rôles &amp; permissions', 'icon' => 'bi-shield-check', 'route' => 'admin.roles'],
        ['perm' => 'admin.types', 'label' => 'Types &amp; métadonnées', 'icon' => 'bi-tags', 'route' => 'admin.types'],
        ['perm' => 'admin.referentials', 'label' => 'Référentiels', 'icon' => 'bi-list-check', 'route' => 'admin.referentials'],
        ['perm' => 'admin.audit', 'label' => 'Audit', 'icon' => 'bi-journal-text', 'route' => 'admin.audit'],
        ['perm' => 'admin.settings', 'label' => 'Paramètres', 'icon' => 'bi-sliders', 'route' => 'admin.settings'],
    ];
    $adminVisible = auth()->check() && ! auth()->user()->isSuperAdmin()
        ? array_filter($adminLinks, fn ($l) => app(\App\Services\PermissionService::class)->can(auth()->user(), $l['perm']))
        : [];
@endphp
<!DOCTYPE html>
<html lang="{{ auth()->check() ? (auth()->user()->tenant->settings['language'] ?? 'fr') : 'fr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Kaeged GED') — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --brand: {{ $brandColor ?? '#0d6efd' }}; }
        body { background: #f4f6f9; }
        .sidebar { min-height: 100vh; background: #1e293b; }
        .sidebar .nav-link { color: #cbd5e1; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: var(--brand); color: #fff; }
        .sidebar .brand { color: #fff; font-weight: 700; padding: 1rem; }
        .content { padding: 1.5rem; }
        .card { border: none; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .btn-primary { background-color: var(--brand); border-color: var(--brand); }
        .btn-primary:hover { background-color: color-mix(in srgb, var(--brand), #000 12%); border-color: var(--brand); }
        .btn-outline-primary { color: var(--brand); border-color: var(--brand); }
        .btn-outline-primary:hover { background-color: var(--brand); border-color: var(--brand); }
        .progress-bar { background-color: var(--brand); }
        .text-primary { color: var(--brand) !important; }
        .sidebar .nav-link.sub-link { padding-left: 1.25rem; font-size: .9rem; }
        .sidebar .nav-link.sub-link.active { background: var(--brand); }
        .sidebar .collapse .nav-link { border-radius: 0 .375rem .375rem 0; }
    </style>
</head>
<body>
<div class="container-fluid p-0">
    <div class="row g-0">
        @auth
            @if (!auth()->user()->isSuperAdmin())
                <nav class="col-auto sidebar">
                    <div class="brand">
                        @if ($brandLogo)<img src="{{ $brandLogo }}" alt="logo" style="height:24px" class="me-1">@else<i class="bi bi-folder2-open"></i>@endif
                        {{ $brandLogo ? '' : 'Kaeged' }}
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2"></i> Tableau de bord</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('documents.*') ? 'active' : '' }}" href="{{ route('documents.index') }}"><i class="bi bi-files"></i> Documents</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('v02.my-documents') ? 'active' : '' }}" href="{{ route('v02.my-documents') }}"><i class="bi bi-briefcase"></i> Mes documents</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('spaces.*') ? 'active' : '' }}" href="{{ route('spaces.index') }}"><i class="bi bi-collection"></i> Espaces</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('search') ? 'active' : '' }}" href="{{ route('search') }}"><i class="bi bi-search"></i> Recherche</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('workflows.*') ? 'active' : '' }}" href="{{ route('workflows.index') }}"><i class="bi bi-diagram-3"></i> Workflows</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('tasks.*') ? 'active' : '' }}" href="{{ route('tasks.index') }}"><i class="bi bi-check2-square"></i> Mes tâches</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}" href="{{ route('notifications.index') }}"><i class="bi bi-bell"></i> Notifications</a></li>
                        @php $adminActive = request()->routeIs('admin.*'); @endphp
                        @if (count($adminVisible) > 0)
                        <li class="nav-item">
                            <a class="nav-link d-flex justify-content-between align-items-center {{ $adminActive ? 'active' : '' }}"
                               data-bs-toggle="collapse" href="#adminSubmenu" role="button" aria-expanded="{{ $adminActive ? 'true' : 'false' }}" aria-controls="adminSubmenu">
                                <span><i class="bi bi-gear"></i> Administration</span>
                                <i class="bi bi-chevron-{{ $adminActive ? 'down' : 'right' }} small"></i>
                            </a>
                            <div class="collapse {{ $adminActive ? 'show' : '' }}" id="adminSubmenu">
                                <ul class="nav flex-column ms-3 border-start border-secondary">
                                    @foreach ($adminVisible as $link)
                                    <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs($link['route']) ? 'active' : '' }}" href="{{ route($link['route']) }}"><i class="bi {{ $link['icon'] }}"></i> {!! $link['label'] !!}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        </li>
                        @endif
                    </ul>
                </nav>
            @endif
        @endauth
        <div class="col">
            <nav class="navbar navbar-light bg-white border-bottom px-3">
                <span class="navbar-brand mb-0 h6">
                    @auth
                        @if (auth()->user()->isSuperAdmin())
                            <i class="bi bi-shield-lock"></i> Super Admin — Plateforme
                        @else
                            {{ auth()->user()->tenant->name ?? '—' }}
                        @endif
                    @endauth
                </span>
                <div class="d-flex align-items-center gap-3">
                    @auth
                        <a href="{{ route('profile') }}" class="text-decoration-none text-dark"><i class="bi bi-person-circle"></i> {{ auth()->user()->name }}</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-sm btn-outline-secondary">Déconnexion</button></form>
                    @endauth
                </div>
            </nav>
            <main class="content">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
