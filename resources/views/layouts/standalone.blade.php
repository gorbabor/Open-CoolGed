<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Kaeged GED') — {{ app_display_name() }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --color-foreground: #0f172a; --color-accent: #4f46e5; --color-background: #f8fafc; --color-surface: #f4dcdc; --radius: 8px; }
        body { background: var(--color-background); color: var(--color-foreground); font-family: ui-monospace, 'Cascadia Code', monospace; }
        h1, h2, h3, h4, h5, h6 { font-family: inherit; color: var(--color-foreground); }
        .toolbar { background: var(--color-accent); color: #fff; padding: .5rem 1rem; display: flex; align-items: center; gap: .75rem; }
        .toolbar a { color: #cbd5e1; text-decoration: none; }
        .toolbar a:hover { color: #fff; }
        .toolbar .title { font-weight: 700; }
        .editor-wrap { height: calc(100vh - 56px); }
        .btn-primary { background-color: var(--color-accent); border-color: var(--color-accent); border-radius: var(--radius); }
        .btn-primary:hover { background-color: color-mix(in srgb, var(--color-accent), #000 12%); border-color: var(--color-accent); }
        @media (hover: hover) {
            .btn, button:not(:disabled) { transition: transform .2s ease, box-shadow .2s ease; }
            .btn:hover, button:not(:disabled):hover { transform: translateY(-6px) scale(1.02); box-shadow: 0 14px 30px rgba(0,0,0,.18); }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition: none !important; animation: none !important; }
            .btn, button { transform: none !important; }
        }
    </style>
    @yield('head')
</head>
<body>
<div class="toolbar">
    <a href="{{ route('dashboard') }}"><i class="bi bi-arrow-left"></i> Retour</a>
    <span class="title">@yield('toolbar-title', app_display_name())</span>
    <span class="ms-auto small">@auth {{ auth()->user()->name }} @endauth</span>
</div>
@yield('content')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@yield('scripts')
</body>
</html>
