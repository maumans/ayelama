{{--
    Gabarit commun aux pages d'erreur.

    Les pages par défaut de Laravel affichent le libellé HTTP en anglais (« Not Found »,
    « Forbidden », « Page Expired ») : incohérent avec le reste de l'application, et peu
    parlant pour le personnel de l'office. Chaque code a sa propre vue qui hérite de
    celle-ci et fournit un titre et une explication en français.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titre') — Ayelema</title>
    <style>
        :root { --ink:#15263F; --seal:#B0863C; --bg:#F5F5F3; }
        * { box-sizing:border-box; }
        body {
            margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
            background:var(--bg); color:var(--ink); padding:24px;
            font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
        }
        .carte {
            background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:40px;
            max-width:480px; width:100%; text-align:center; box-shadow:0 1px 3px rgb(0 0 0 / .06);
        }
        .code { font-size:13px; font-weight:600; letter-spacing:.08em; color:var(--seal); text-transform:uppercase; }
        h1 { font-family:"Source Serif 4",Georgia,serif; font-size:26px; margin:12px 0 10px; }
        p  { color:#64748b; font-size:14px; line-height:1.6; margin:0 0 26px; }
        a  {
            display:inline-block; background:var(--ink); color:#fff; text-decoration:none;
            padding:11px 22px; border-radius:8px; font-size:14px; font-weight:500;
        }
        a:hover { background:#1F3A5F; }
    </style>
</head>
<body>
    <div class="carte">
        <div class="code">Erreur @yield('code')</div>
        <h1>@yield('titre')</h1>
        <p>@yield('explication')</p>
        <a href="{{ url('/') }}">Retour au tableau de bord</a>
    </div>
</body>
</html>
