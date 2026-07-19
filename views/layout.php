<?php
/** @var string $appName */
/** @var string $viewFile */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($appName ?? 'License Portal') ?></title>
    <link rel="icon" href="/assets/favicon.png" type="image/png" sizes="any">
    <link rel="apple-touch-icon" href="/assets/favicon.png">
    <style>
        :root { color-scheme: light dark; --bg:#0b1220; --card:#121a2b; --text:#e8eefc; --muted:#93a0b8; --accent:#5eead4; --border:#243049; --danger:#f87171; --ok:#4ade80; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: radial-gradient(1200px 600px at 10% -10%, #1a2a4a, var(--bg)); color: var(--text); min-height: 100vh; }
        a { color: var(--accent); text-decoration: none; }
        .wrap { max-width: 960px; margin: 0 auto; padding: 32px 20px; }
        .card { background: color-mix(in srgb, var(--card) 92%, black); border: 1px solid var(--border); border-radius: 16px; padding: 24px; box-shadow: 0 20px 50px rgba(0,0,0,.25); }
        h1,h2 { margin: 0 0 12px; font-weight: 650; letter-spacing: -.02em; }
        p.muted, .muted { color: var(--muted); }
        label { display:block; font-size: 13px; color: var(--muted); margin-bottom: 6px; }
        input, select, textarea, button { font: inherit; }
        input, select, textarea { width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid var(--border); background: #0a1020; color: var(--text); margin-bottom: 14px; }
        button, .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border:0; border-radius: 10px; padding: 10px 14px; background: var(--accent); color:#042f2e; font-weight: 650; cursor:pointer; }
        .btn-secondary { background: transparent; border: 1px solid var(--border); color: var(--text); }
        .btn-danger { background: #7f1d1d; color: #fecaca; }
        .row { display:flex; gap:12px; flex-wrap: wrap; align-items: center; }
        .top { display:flex; justify-content: space-between; align-items:center; margin-bottom: 20px; gap: 12px; }
        table { width:100%; border-collapse: collapse; }
        th, td { text-align:left; padding: 10px 8px; border-bottom: 1px solid var(--border); font-size: 14px; vertical-align: top; }
        th { color: var(--muted); font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .badge { display:inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; border: 1px solid var(--border); }
        .badge.active { color: var(--ok); border-color: color-mix(in srgb, var(--ok) 40%, var(--border)); }
        .badge.blocked, .badge.revoked { color: var(--danger); border-color: color-mix(in srgb, var(--danger) 40%, var(--border)); }
        .flash { margin-bottom: 16px; padding: 12px 14px; border-radius: 12px; background: color-mix(in srgb, var(--accent) 15%, transparent); border: 1px solid color-mix(in srgb, var(--accent) 35%, var(--border)); }
        .error { color: var(--danger); margin-bottom: 12px; }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; }
        .grid-2 { display:grid; grid-template-columns: 1.2fr .8fr; gap: 16px; }
        @media (max-width: 800px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<?php require $viewFile; ?>
</body>
</html>
