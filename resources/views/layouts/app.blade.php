@php
    // Branding par tenant (Administration → Paramètres → Branding) : variables CSS.
    $brandColor = auth()->check() && ! auth()->user()->isSuperAdmin()
        ? (auth()->user()->tenant->branding['color'] ?? null) : null;
    $brandLogo = auth()->check() && ! auth()->user()->isSuperAdmin()
        ? (auth()->user()->tenant->branding['logo_url'] ?? null) : null;
    // Thème visuel : registry (tenant > plateforme > kami) + mode (user > tenant > plateforme > auto).
    $themeRegistry = app(\App\Themes\ThemeRegistry::class);
    $themeSlug = $themeRegistry->resolveTheme();
    $themeMode = $themeRegistry->resolveMode();
    $theme = $themeRegistry->get($themeSlug);
    $palette = $themeRegistry->palette($themeMode === 'dark' ? 'dark' : 'light');
    $effectiveAccent = $brandColor ?? $palette['accent'];
    $accentRgb = implode(',', array_map('hexdec', str_split(ltrim($effectiveAccent, '#'), 2)));
    // Menu Administration : visible uniquement selon les permissions effectives
    // (rôles directs + rôles des groupes) — un utilisateur sans permission admin.*
    // ne voit aucun lien d'administration.
    $adminLinks = [
        ['perm' => 'admin.users', 'label' => 'Utilisateurs', 'icon' => 'bi-people', 'route' => 'admin.users'],
        ['perm' => 'admin.groups', 'label' => 'Groupes', 'icon' => 'bi-diagram-2', 'route' => 'admin.groups'],
        ['perm' => 'admin.roles', 'label' => 'Rôles &amp; permissions', 'icon' => 'bi-shield-check', 'route' => 'admin.roles'],
        ['perm' => 'admin.types', 'label' => 'Types &amp; métadonnées', 'icon' => 'bi-tags', 'route' => 'admin.types'],
        ['perm' => 'admin.referentials', 'label' => 'Référentiels', 'icon' => 'bi-list-check', 'route' => 'admin.referentials'],
        ['perm' => 'admin.referentials', 'label' => 'Import CSV', 'icon' => 'bi-upload', 'route' => 'admin.import-csv'],
        ['perm' => 'admin.audit', 'label' => 'Audit', 'icon' => 'bi-journal-text', 'route' => 'admin.audit'],
        ['perm' => 'admin.settings', 'label' => 'Paramètres', 'icon' => 'bi-sliders', 'route' => 'admin.settings'],
    ];
    $adminVisible = auth()->check()
        ? array_filter($adminLinks, fn ($l) => app(\App\Services\PermissionService::class)->can(auth()->user(), $l['perm']))
        : [];
    $nextMode = $themeMode === 'dark' ? 'light' : 'dark';
@endphp
<!DOCTYPE html>
<html lang="{{ auth()->check() ? (auth()->user()->tenant->settings['language'] ?? 'fr') : 'fr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Kaeged GED') — {{ app_display_name() }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    @if ($theme['fontLink'])
        <link href="https://fonts.googleapis.com/css2?{{ $theme['fontLink'] }}&display=swap" rel="stylesheet">
    @endif
    <style>
        :root {
            --brand: {{ $effectiveAccent }};
            --color-background: {{ $palette['background'] }};
            --color-surface: {{ $palette['surface'] }};
            --color-foreground: {{ $palette['foreground'] }};
            --color-muted: {{ $palette['muted'] }};
            --color-accent: {{ $effectiveAccent }};
            --color-accent-rgb: {{ $accentRgb }};
            --color-accent-hover: color-mix(in srgb, var(--brand), #000 12%);
            --font-body: {!! $theme['font'] !!};
            --font-display: {!! $theme['font'] !!};
            --radius: {{ $theme['radius'] }};
            --border: 1px solid {{ $themeMode === 'dark' ? 'rgba(248, 250, 252, 0.12)' : 'rgba(15, 23, 42, 0.08)' }};
        }
        html { scroll-behavior: smooth; }
        body { background: var(--color-background); color: var(--color-foreground); font-family: var(--font-body); }
        h1, h2, h3, h4, h5, h6, .h1, .h2, .h3, .h4, .h5, .h6 { font-family: var(--font-display); color: var(--color-foreground); }
        a { color: var(--color-accent); text-underline-offset: 0.15em; }
        :focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px; }
        .sidebar { min-height: 100vh; display: flex; flex-direction: column; background: var(--color-surface); border-right: var(--border); }
        .sidebar .nav-link { color: var(--color-foreground); border-left: 3px solid transparent; border-radius: 0; transition: background 150ms ease, border-color 150ms ease, color 150ms ease; }
        .sidebar .nav-link:hover { background: rgba(var(--color-accent-rgb), 0.05); color: var(--color-accent-hover); }
        .sidebar .nav-link.active { border-left-color: var(--color-accent) !important; background: rgba(var(--color-accent-rgb), 0.07); color: var(--color-accent-hover) !important; font-weight: 700; }
        .sidebar .brand { color: var(--color-foreground); font-weight: 700; padding: 1rem; border-bottom: var(--border); }
        .sidebar .brand:hover { color: var(--color-accent-hover); }
        .content { padding: 1.5rem; }
        .card { border: var(--border); border-radius: var(--radius); box-shadow: none; background: var(--color-surface); }
        .btn { border-radius: var(--radius); }
        .btn-primary { background-color: var(--color-accent); border-color: var(--color-accent); }
        .btn-primary:hover { background-color: var(--color-accent-hover); border-color: var(--color-accent); }
        .btn-outline-primary { color: var(--color-accent); border-color: var(--color-accent); }
        .btn-outline-primary:hover { background-color: var(--color-accent); border-color: var(--color-accent); }
        .progress-bar { background-color: var(--color-accent); }
        .text-primary { color: var(--color-accent) !important; }
        .nav-tabs .nav-link.active { color: var(--color-foreground); background: var(--color-surface); border-color: var(--color-foreground) var(--color-foreground) var(--color-surface); border-radius: var(--radius); }
        .nav-tabs .nav-link { color: var(--color-muted); }
        .table { --bs-table-bg: transparent; }
        .table thead th { border-bottom-color: rgba(15, 23, 42, 0.15); color: var(--color-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; font-size: .75rem; }
        .badge { border-radius: var(--radius); }
        .form-control, .form-select { border-radius: var(--radius); background: var(--color-background); color: var(--color-foreground); border-color: rgba(var(--color-accent-rgb), 0.2); }
        .form-control::placeholder { color: var(--color-muted); opacity: .7; }
        .form-select option { background-color: var(--color-surface); color: var(--color-foreground); }
        .form-control:focus, .form-select:focus { border-color: var(--color-accent); box-shadow: 0 0 0 .2rem rgba(var(--color-accent-rgb), .1); color: var(--color-foreground); background-color: var(--color-background); }
        label, .form-label, .form-check-label, .form-text { color: var(--color-foreground); }
        .form-text { color: var(--color-muted) !important; }
        .form-check-input { background-color: var(--color-background); border-color: var(--color-muted); }
        .form-check-input:checked { background-color: var(--color-accent); border-color: var(--color-accent); }
        .input-group-text { background-color: var(--color-surface); color: var(--color-foreground); border-color: rgba(var(--color-accent-rgb), 0.2); }
        .navbar { background: var(--color-surface) !important; border-bottom: var(--border); }
        .alert { border-radius: var(--radius); }
        /* Lisibilité en mode sombre : les classes Bootstrap à couleurs fixes suivent le thème. */
        .text-muted { color: var(--color-muted) !important; }
        .text-dark { color: var(--color-foreground) !important; }
        .table-light, .table-light th, .table-light td { background-color: var(--color-surface); color: var(--color-foreground); }
        .table thead th { border-bottom-color: var(--border); }
        .bg-light { background-color: var(--color-surface) !important; color: var(--color-foreground); }
        .bg-white { background-color: var(--color-surface) !important; color: var(--color-foreground); }
        .text-secondary { color: var(--color-muted) !important; }
        .border-secondary { border-color: var(--color-muted) !important; }
        .pagination .page-link { background-color: var(--color-surface); color: var(--color-accent); border-color: var(--border); }
        .pagination .page-link:hover { background-color: var(--color-background); color: var(--color-accent-hover); }
        .pagination .page-item.active .page-link { background-color: var(--color-accent); border-color: var(--color-accent); color: var(--color-background); }
        .pagination .page-item.disabled .page-link { background-color: var(--color-surface); color: var(--color-muted); border-color: var(--border); }
        .sidebar .nav-link.sub-link { padding-left: 1.25rem; font-size: .9rem; }
        .sidebar .nav-link.sub-link.active { border-left-color: var(--color-accent); }
        .sidebar .collapse .nav-link { border-radius: 0; }
        /* Arbre des espaces : densité, lignes guides par niveau et chevron de pliage. */
        .space-tree { font-size: .8125rem; line-height: 1.35; }
        .space-tree .folder-row { display: flex; align-items: center; gap: .35rem; padding: .12rem .3rem; border-radius: var(--radius); }
        .space-tree .folder-row:hover { background: rgba(var(--color-accent-rgb), .06); }
        .space-tree .folder-caret, .space-tree .caret-spacer { flex: 0 0 1.1rem; width: 1.1rem; text-align: center; }
        .space-tree .folder-caret { padding: 0; border: 0; background: none; color: var(--color-muted); cursor: pointer; line-height: 1; }
        .space-tree .folder-caret .bi { font-size: .8em; transition: transform .15s ease; }
        .space-tree .folder-caret[aria-expanded="true"] .bi { transform: rotate(90deg); }
        .space-tree .folder-toggle { padding: 0; border: 0; background: none; color: inherit; cursor: pointer; text-align: left; }
        .space-tree .folder-caret:hover, .space-tree .folder-toggle:hover { transform: none; box-shadow: none; }
        .space-tree .folder-sub { font-size: .95em; }
        .space-tree .folder-icon-root { color: rgba(var(--color-accent-rgb), .85); }
        .space-tree .folder-icon-sub { color: var(--color-muted); }
        .space-tree .folder-count { font-size: .7rem; color: var(--color-muted); background: var(--color-background); border: var(--border); border-radius: 99px; padding: .02rem .45rem; white-space: nowrap; }
        .space-tree .folder-actions { font-size: .85em; }
        .space-tree .folder-children { margin: .05rem 0 .05rem 1.05rem; padding-left: .55rem; border-left: 1px dashed rgba(var(--color-accent-rgb), .35); }
        .space-tree .folder-children > li { position: relative; }
        .space-tree .folder-children > li::before { content: ""; position: absolute; left: -.55rem; top: .75em; width: .4rem; border-top: 1px dashed rgba(var(--color-accent-rgb), .35); }
        .space-tree .folder-documents { margin: .05rem 0 .05rem 1.05rem; padding-left: .55rem; border-left: 1px dashed rgba(var(--color-accent-rgb), .35); }
        @media (hover: hover) {
            .card, .btn, button:not(:disabled) { transition: transform .2s ease, box-shadow .2s ease; }
            .card:hover, .btn:hover, button:not(:disabled):hover { transform: translateY(-6px) scale(1.02); box-shadow: 0 14px 30px rgba(0,0,0,.18); }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition: none !important; animation: none !important; scroll-behavior: auto !important; }
            .card, .btn, button { transform: none !important; }
        }
    </style>
</head>
<body>
<div class="container-fluid p-0">
    <div class="row g-0">
        @auth
            <nav class="col-auto sidebar">
                    <div class="brand">
                        @if ($brandLogo)<img src="{{ $brandLogo }}" alt="logo" style="height:24px" class="me-1">@else<i class="bi bi-folder2-open"></i>@endif
                        {{ $brandLogo ? '' : app_display_name() }}
                    </div>
                    <ul class="nav flex-column">
                        @php
                            $menuService = app(\App\Services\MenuService::class);
                            $menuTenant = auth()->user()->isSuperAdmin() ? null : auth()->user()->tenant;
                            $menuItems = $menuService->items($menuTenant, auth()->user());
                            $adminMenuLabel = $menuService->adminLabel($menuTenant);
                            $menuActive = [
                                'dashboard' => request()->routeIs('dashboard'),
                                'documents' => request()->routeIs('documents.*') && ! request('personal'),
                                'personal' => (bool) request('personal'),
                                'v02' => request()->routeIs('v02.my-documents'),
                                'spaces' => request()->routeIs('spaces.*'),
                                'search' => request()->routeIs('search'),
                                'workflows' => request()->routeIs('workflows.*'),
                                'tasks' => request()->routeIs('tasks.*'),
                                'notifications' => request()->routeIs('notifications.*'),
                            ];
                        @endphp
                        @foreach ($menuItems as $menuKey => $menuItem)
                        <li class="nav-item"><a class="nav-link {{ ($menuActive[$menuKey] ?? false) ? 'active' : '' }}" href="{{ route($menuItem['route'], $menuItem['params']) }}"><i class="bi {{ $menuItem['icon'] }}"></i> {{ $menuItem['custom'] ? $menuItem['label'] : __($menuItem['label']) }}
                            @if ($menuKey === 'notifications')
                            @php $unreadCount = auth()->user()->notifications()->whereNull('read_at')->count(); @endphp
                            @if ($unreadCount > 0)<span class="badge bg-danger rounded-pill ms-1" id="notifBadge">{{ $unreadCount }}</span>@else<span class="badge bg-danger rounded-pill ms-1 d-none" id="notifBadge">0</span>@endif
                            @endif
                        </a></li>
                        @endforeach
                        @php $adminActive = request()->routeIs('admin.*'); @endphp
                        @if (count($adminVisible) > 0)
                        <li class="nav-item">
                            <a class="nav-link d-flex justify-content-between align-items-center {{ $adminActive ? 'active' : '' }}"
                               data-bs-toggle="collapse" href="#adminSubmenu" role="button" aria-expanded="{{ $adminActive ? 'true' : 'false' }}" aria-controls="adminSubmenu">
                                <span><i class="bi bi-gear"></i> {{ $adminMenuLabel }}</span>
                                <i class="bi bi-chevron-{{ $adminActive ? 'down' : 'right' }} small"></i>
                            </a>
                            <div class="collapse {{ $adminActive ? 'show' : '' }}" id="adminSubmenu">
                                <ul class="nav flex-column ms-3 border-start border-secondary">
                                    @foreach ($adminVisible as $link)
                                    <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs($link['route']) ? 'active' : '' }}" href="{{ route($link['route']) }}"><i class="bi {{ $link['icon'] }}"></i> {{ __($link['label']) }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        </li>
                        @endif
                    </ul>
                    <div class="mt-auto pt-3 border-top" style="border-color: var(--border) !important;">
                        <form method="POST" action="{{ route('profile.theme-mode') }}">
                            @csrf
                            <input type="hidden" name="theme_mode" value="{{ $nextMode }}">
                            <button type="submit" class="nav-link w-100 text-start" title="Basculer clair/sombre"
                                style="border:none;background:none;">
                                <i class="bi {{ $themeMode === 'dark' ? 'bi-sun' : 'bi-moon-stars' }}"></i>
                                {{ $themeMode === 'dark' ? 'Mode clair' : 'Mode sombre' }}
                            </button>
                        </form>
                    </div>
                </nav>
        @endauth
        <div class="col">
            <nav class="navbar navbar-light border-bottom px-3">
                <span class="navbar-brand mb-0 h6">
                    @auth
                        @if (auth()->user()->isSuperAdmin())
                            <i class="bi bi-shield-lock"></i> Super Admin — {{ app_display_name() }}
                        @else
                            {{ auth()->user()->tenant->name ?? '—' }}
                        @endif
                    @endauth
                </span>
                <div class="d-flex align-items-center gap-3">
                    @auth
                        <form method="POST" action="{{ route('profile.locale') }}" class="d-flex align-items-center gap-1">
                            @csrf
                            <label class="small text-muted mb-0">{{ __('Langue') }}</label>
                            <select name="locale" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                                <option value="fr" @selected(app()->getLocale() === 'fr')>{{ __('Français') }}</option>
                                <option value="en" @selected(app()->getLocale() === 'en')>{{ __('Anglais') }}</option>
                            </select>
                        </form>
                        <a href="{{ route('profile') }}" class="text-decoration-none text-dark"><i class="bi bi-person-circle"></i> {{ auth()->user()->name }}</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-sm btn-outline-secondary">{{ __('Déconnexion') }}</button></form>
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
@auth
@php
    $darkPalette = $theme['palettes']['dark'];
    $lightPalette = $theme['palettes']['light'];
@endphp
<script>
document.addEventListener('DOMContentLoaded', () => {
    const mode = {!! json_encode($themeMode) !!};
    const light = {!! json_encode($lightPalette) !!};
    const dark = {!! json_encode($darkPalette) !!};
    const accent = {!! json_encode($effectiveAccent) !!};
    const themeFont = {!! json_encode($theme['font']) !!};
    const themeRadius = {!! json_encode($theme['radius']) !!};

    function applyPalette(p) {
        const r = document.documentElement.style;
        r.setProperty('--color-background', p.background);
        r.setProperty('--color-surface', p.surface);
        r.setProperty('--color-foreground', p.foreground);
        r.setProperty('--color-muted', p.muted);
        r.setProperty('--color-accent', accent);
        r.setProperty('--color-accent-hover', accent);
        r.setProperty('--font-body', themeFont);
        r.setProperty('--font-display', themeFont);
        r.setProperty('--radius', themeRadius);
    }

    const systemDark = window.matchMedia('(prefers-color-scheme: dark)');
    function sync() {
        if (mode === 'auto') {
            applyPalette(systemDark.matches ? dark : light);
        }
    }
    sync();
    systemDark.addEventListener('change', sync);
});

// Badge de notifications : rafraîchissement périodique (60 s) sans rechargement.
const badge = document.getElementById('notifBadge');
if (badge) {
    const refresh = async () => {
        try {
            const res = await fetch(@json(route('notifications.unread-count')), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) return;
            const data = await res.json();
            const count = data.count ?? 0;
            if (count > 0) {
                badge.textContent = count;
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
        } catch (e) { /* silencieux : le badge reste tel quel */ }
    };
    setInterval(refresh, 60000);
}
</script>
@endauth
</body>
</html>
