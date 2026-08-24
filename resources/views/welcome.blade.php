<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ app_display_name() }}</title>
    <style>
        :root {
            --color-background: #f8fafc;
            --color-surface: #f4dcdc;
            --color-foreground: #0f172a;
            --color-muted: #64748b;
            --color-accent: #4f46e5;
            --font-body: ui-monospace, 'Cascadia Code', monospace;
            --border: 1px solid rgba(15, 23, 42, 0.08);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: var(--color-background);
            color: var(--color-foreground);
            font-family: var(--font-body);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            line-height: 1.6;
        }
        a { color: var(--color-accent); text-underline-offset: 0.15em; }
        :focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px; }
        .card {
            max-width: 560px;
            width: 100%;
            background: var(--color-surface);
            border: var(--border);
            border-radius: 8px;
            padding: 3rem 2.5rem;
        }
        .eyebrow { font-size: .75rem; letter-spacing: .08em; text-transform: uppercase; color: var(--color-muted); margin: 0 0 .5rem; font-weight: 700; }
        h1 { margin: 0 0 .75rem; font-size: 2rem; font-weight: 700; }
        p.lead { color: var(--color-muted); margin: 0 0 1.5rem; }
        .actions { display: flex; gap: .75rem; }
        .btn {
            display: inline-block;
            padding: .6rem 1.4rem;
            border: 1px solid var(--color-accent);
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
        }
        .btn-primary { background: var(--color-accent); color: #fff; }
        .btn-outline { color: var(--color-accent); }
        .btn-outline:hover { background: var(--color-accent); color: #fff; }
        .foot { margin-top: 2rem; font-size: .85rem; color: var(--color-muted); }
        @media (hover: hover) {
            .btn { transition: transform .2s ease, box-shadow .2s ease; }
            .btn:hover { transform: translateY(-6px) scale(1.02); box-shadow: 0 14px 30px rgba(0,0,0,.18); }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition: none !important; animation: none !important; }
            .btn { transform: none !important; }
        }
    </style>
</head>
<body>
    <div class="card">
        <p class="eyebrow">GED SaaS Multi-Tenant</p>
        <h1>{{ app_display_name() }}</h1>
        <p class="lead">Plateforme de Gestion Électronique de Documents — espaces, versions,
            workflows, partage sécurisé et cycle de vie documentaire.</p>
        <div class="actions">
            @if (Route::has('login'))
                @auth
                    <a class="btn btn-primary" href="{{ url('/dashboard') }}">Tableau de bord</a>
                @else
                    <a class="btn btn-primary" href="{{ route('login') }}">Se connecter</a>
                @endauth
            @endif
        </div>
    </div>
    <p class="foot">© {{ date('Y') }} {{ app_display_name() }}</p>
</body>
</html>
