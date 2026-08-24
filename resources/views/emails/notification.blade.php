<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }}</title>
<style>
body { margin:0; background:#f8fafc; color:#0f172a; font-family:ui-monospace,'Cascadia Code',monospace; line-height:1.6; }
.wrap { max-width:560px; margin:40px auto; background:#f4dcdc; border:1px solid rgba(15,23,42,.08); border-radius:8px; }
.head { padding:20px 28px; border-bottom:1px solid rgba(15,23,42,.08); font-weight:700; color:#0f172a; }
.body { padding:24px 28px; }
.foot { padding:14px 28px; border-top:1px solid rgba(15,23,42,.08); font-size:.85em; color:#64748b; }
a.btn { display:inline-block; margin-top:14px; padding:10px 18px; background:#4f46e5; color:#fff; text-decoration:none; border-radius:8px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="head">{{ $appName }}</div>
  <div class="body">
    <h2 style="margin-top:0;font-size:1.2em;">{{ $title }}</h2>
    @if ($body)
        <p style="white-space:pre-line;">{{ $body }}</p>
    @endif
    @if ($link)
        <a class="btn" href="{{ url($link) }}">Voir dans l'application</a>
    @endif
  </div>
  <div class="foot">Envoyé par {{ $appName }}</div>
</div>
</body>
</html>
