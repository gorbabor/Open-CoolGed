<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation terminée — Open-CoolGed</title>
<style>
  body{font-family:Segoe UI,Arial,sans-serif;background:#f0f7fb;color:#0c2d48;margin:0;padding:3rem 1rem}
  .wrap{max-width:640px;margin:0 auto;background:#fff;border:1px solid #dcebf5;border-radius:10px;padding:1.6rem 1.8rem}
  h1{font-size:1.4rem;color:#166534;margin-top:0}
  table{width:100%;border-collapse:collapse;font-size:.92rem;margin-top:.8rem}
  td{border-bottom:1px solid #eef2f7;padding:.4rem .3rem}
  a.btn{display:inline-block;margin-top:1.2rem;padding:.55rem 1.2rem;border-radius:8px;background:#1d7ab3;color:#fff;text-decoration:none}
  .muted{color:#5b7c99;font-size:.88rem}
</style>
</head>
<body>
<div class="wrap">
    <h1>Installation terminée</h1>
    <p>La base de données a été créée, les migrations exécutées et les comptes initiaux enregistrés.
       L'assistant d'installation est désormais désactivé (verrou posé).</p>

    @if (! empty($accounts))
    <table>
        @foreach ($accounts as $role => $email)
            <tr><td>{{ ucfirst(str_replace('_', ' ', $role)) }}</td><td><code>{{ $email }}</code></td></tr>
        @endforeach
    </table>
    <p class="muted">Les mots de passe sont ceux saisis dans l'assistant (comptes de démonstration :
       mot de passe <code>password123</code> — à changer en production).</p>
    @endif

    <a class="btn" href="{{ url('/login') }}">Se connecter</a>
</div>
</body>
</html>
