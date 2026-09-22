<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation — Open-CoolGed</title>
<style>
  body{font-family:Segoe UI,Arial,sans-serif;background:#f0f7fb;color:#0c2d48;margin:0;padding:2rem 1rem}
  .wrap{max-width:760px;margin:0 auto}
  h1{font-size:1.5rem;margin-bottom:.3rem} h2{font-size:1.05rem;color:#1d7ab3;margin-top:1.6rem}
  .card{background:#fff;border:1px solid #dcebf5;border-radius:10px;padding:1.2rem 1.4rem;margin-top:1rem}
  table{width:100%;border-collapse:collapse;font-size:.9rem}
  td,th{border-bottom:1px solid #eef2f7;padding:.35rem .4rem;text-align:left}
  .ok{color:#15803d;font-weight:600}.ko{color:#b91c1c;font-weight:600}
  label{display:block;font-size:.85rem;margin:.6rem 0 .15rem}
  input,select{width:100%;padding:.45rem .55rem;border:1px solid #cbd9ea;border-radius:8px;font:inherit;box-sizing:border-box}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:.6rem 1rem}
  .alert{padding:.6rem .9rem;border-radius:8px;margin:.8rem 0;font-size:.92rem}
  .alert.error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}
  .alert.success{background:#dcfce7;border:1px solid #86efac;color:#166534}
  .actions{display:flex;gap:.6rem;margin-top:1.2rem}
  button{padding:.55rem 1.1rem;border-radius:8px;border:1px solid #1d7ab3;background:#1d7ab3;color:#fff;font:inherit;cursor:pointer}
  button.secondary{background:#fff;color:#1d7ab3}
  .muted{color:#5b7c99;font-size:.85rem}
</style>
</head>
<body>
<div class="wrap">
    <h1>Installation d'Open-CoolGed</h1>
    <p class="muted">Assistant d'installation : prérequis, base de données, comptes initiaux. Une fois installé, cet assistant devient inaccessible.</p>

    @if (session('install_error'))
        <div class="alert error">{{ session('install_error') }}</div>
    @endif
    @if (session('success'))
        <div class="alert success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert error"><ul style="margin:0;padding-left:1.1rem">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="card">
        <h2 style="margin-top:0">1. Prérequis</h2>
        <table>
            @foreach ($requirements as $check)
                <tr>
                    <td>{{ $check['label'] }} @if ($check['hint'])<span class="muted">— {{ $check['hint'] }}</span>@endif</td>
                    <td style="width:80px" class="{{ $check['ok'] ? 'ok' : 'ko' }}">{{ $check['ok'] ? 'OK' : 'KO' }}</td>
                </tr>
            @endforeach
        </table>
        @if (! $requirementsPassed)
            <div class="alert error">Corrigez les prérequis signalés « KO » avant de poursuivre.</div>
        @endif
    </div>

    <form method="POST" action="{{ route('install.run') }}">
        @csrf
        <div class="card">
            <h2 style="margin-top:0">2. Base de données</h2>
            <div class="grid">
                <div>
                    <label>Type</label>
                    <select name="db_driver">
                        <option value="mysql" @selected(old('db_driver', 'mysql') === 'mysql')>MySQL / MariaDB</option>
                        <option value="sqlite" @selected(old('db_driver') === 'sqlite')>SQLite (fichier)</option>
                    </select>
                </div>
                <div>
                    <label>Base de données</label>
                    <input type="text" name="db_database" value="{{ old('db_database') }}" required>
                </div>
                <div>
                    <label>Hôte</label>
                    <input type="text" name="db_host" value="{{ old('db_host', '127.0.0.1') }}">
                </div>
                <div>
                    <label>Port</label>
                    <input type="number" name="db_port" value="{{ old('db_port', '3306') }}">
                </div>
                <div>
                    <label>Utilisateur</label>
                    <input type="text" name="db_username" value="{{ old('db_username') }}">
                </div>
                <div>
                    <label>Mot de passe</label>
                    <input type="password" name="db_password" value="">
                </div>
            </div>
            <div class="actions">
                <button type="submit" class="secondary" formaction="{{ route('install.test') }}">Tester la connexion</button>
            </div>
        </div>

        <div class="card">
            <h2 style="margin-top:0">3. Application et comptes initiaux</h2>
            <label>URL de l'application</label>
            <input type="url" name="app_url" value="{{ old('app_url', url('/')) }}" required>

            <h2>Super administrateur (plateforme)</h2>
            <div class="grid">
                <div><label>Nom</label><input type="text" name="super_name" value="{{ old('super_name') }}" required></div>
                <div><label>Email</label><input type="email" name="super_email" value="{{ old('super_email') }}" required></div>
                <div><label>Mot de passe (min. 8)</label><input type="password" name="super_password" required></div>
            </div>

            <h2>Organisation (tenant) et administrateur</h2>
            <div class="grid">
                <div><label>Nom de l'organisation</label><input type="text" name="org_name" value="{{ old('org_name') }}" required></div>
                <div><label>Administrateur — nom</label><input type="text" name="admin_name" value="{{ old('admin_name') }}" required></div>
                <div><label>Administrateur — email</label><input type="email" name="admin_email" value="{{ old('admin_email') }}" required></div>
                <div><label>Administrateur — mot de passe (min. 8)</label><input type="password" name="admin_password" required></div>
            </div>

            <label style="display:flex;align-items:center;gap:.5rem;margin-top:.9rem">
                <input type="checkbox" name="demo_accounts" value="1" style="width:auto" @checked(old('demo_accounts', true))>
                Créer les comptes de démonstration (utilisateur et validateur — démonstration uniquement)
            </label>
        </div>

        <div class="actions">
            <button type="submit" @disabled(! $requirementsPassed)>Lancer l'installation</button>
            <span class="muted" style="align-self:center">Migrations + comptes + verrou d'installation</span>
        </div>
    </form>
</div>
</body>
</html>
