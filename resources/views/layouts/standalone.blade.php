<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Kaeged GED') — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .toolbar { background: #1e293b; color: #fff; padding: .5rem 1rem; display: flex; align-items: center; gap: .75rem; }
        .toolbar a { color: #cbd5e1; text-decoration: none; }
        .toolbar a:hover { color: #fff; }
        .toolbar .title { font-weight: 600; }
        .editor-wrap { height: calc(100vh - 56px); }
    </style>
    @yield('head')
</head>
<body>
<div class="toolbar">
    <a href="{{ route('dashboard') }}"><i class="bi bi-arrow-left"></i> Retour</a>
    <span class="title">@yield('toolbar-title', config('app.name'))</span>
    <span class="ms-auto small">@auth {{ auth()->user()->name }} @endauth</span>
</div>
@yield('content')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@yield('scripts')
</body>
</html>
