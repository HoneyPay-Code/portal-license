<?php
/** @var string $title */
/** @var string $appName */
/** @var string $content */
/** @var string|null $nav */
$nav = $nav ?? 'guest';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= htmlspecialchars(($title ?? '').' · '.$appName) ?></title>
    <link rel="icon" href="/assets/favicon.png" type="image/png" sizes="any">
    <link rel="apple-touch-icon" href="/assets/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f7f8fa;
            --surface: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --accent: #0f172a;
            --accent-soft: #f1f5f9;
            --ok: #15803d;
            --danger: #b91c1c;
            --radius: 14px;
            --shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px rgba(15,23,42,.04);
            --safe-bottom: env(safe-area-inset-bottom, 0px);
        }
        *, *::before, *::after { box-sizing: border-box; }
        html {
            -webkit-text-size-adjust: 100%;
            overflow-x: clip;
            max-width: 100%;
        }
        body {
            margin: 0;
            font-family: "DM Sans", system-ui, sans-serif;
            background:
                radial-gradient(900px 400px at 100% -10%, #eef2ff 0%, transparent 55%),
                radial-gradient(700px 320px at -5% 0%, #f0fdf4 0%, transparent 50%),
                var(--bg);
            color: var(--text);
            min-height: 100vh;
            min-height: 100dvh;
            overflow-x: clip;
            max-width: 100vw;
            width: 100%;
        }
        img, video, iframe, svg { max-width: 100%; height: auto; }
        a { color: inherit; }
        .shell {
            max-width: 1080px;
            width: 100%;
            margin: 0 auto;
            padding: 16px 16px calc(88px + var(--safe-bottom));
            overflow-x: clip;
        }
        .shell.shell-wide { max-width: 1280px; }
        .topbar {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            margin-bottom: 20px; padding: 12px 14px; background: var(--surface);
            border: 1px solid var(--border); border-radius: 18px; box-shadow: var(--shadow);
            position: sticky; top: 8px; z-index: 40;
            max-width: 100%;
            min-width: 0;
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            min-width: 0;
            flex: 1 1 auto;
            max-width: min(52vw, 220px);
        }
        .brand-logo {
            display: block;
            height: 32px;
            width: auto;
            max-width: 160px;
            object-fit: contain;
            object-position: left center;
        }
        .brand-admin-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--muted);
            white-space: nowrap;
        }
        .auth-brand {
            display: flex;
            justify-content: center;
            margin: 0 0 20px;
        }
        .auth-brand .brand-logo {
            height: 40px;
            max-width: 200px;
        }
        .auth-card .auth-brand {
            margin-bottom: 18px;
        }
        .nav-toggle {
            display: none;
            width: 42px; height: 42px; border-radius: 12px; border: 1px solid var(--border);
            background: #fff; cursor: pointer; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .nav-toggle span {
            display: block; width: 18px; height: 2px; background: var(--text); position: relative;
        }
        .nav-toggle span::before, .nav-toggle span::after {
            content: ""; position: absolute; left: 0; width: 18px; height: 2px; background: var(--text);
        }
        .nav-toggle span::before { top: -6px; }
        .nav-toggle span::after { top: 6px; }
        #nav-open { display: none; }
        .nav { display: flex; gap: 4px; flex-wrap: wrap; align-items: center; }
        .nav a {
            text-decoration: none; font-size: 14px; color: var(--muted);
            padding: 8px 12px; border-radius: 999px; white-space: nowrap;
        }
        .nav a:hover, .nav a.active { background: var(--accent-soft); color: var(--text); }
        .card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 20px; box-shadow: var(--shadow);
            max-width: 100%;
            min-width: 0;
            overflow-wrap: anywhere;
        }
        h1 { margin: 0 0 8px; font-size: clamp(22px, 5vw, 28px); letter-spacing: -.03em; overflow-wrap: anywhere; }
        h2 { margin: 0 0 12px; font-size: clamp(16px, 4vw, 18px); letter-spacing: -.02em; overflow-wrap: anywhere; }
        p { overflow-wrap: anywhere; }
        .muted { color: var(--muted); }
        .grid { display: grid; gap: 16px; max-width: 100%; min-width: 0; }
        .grid > * { min-width: 0; max-width: 100%; }
        .grid-2 { grid-template-columns: minmax(0, 1.2fr) minmax(0, .8fr); }
        label { display:block; font-size: 13px; color: var(--muted); margin-bottom: 6px; font-weight: 500; }
        input, select, textarea {
            width: 100%; max-width: 100%; padding: 12px 12px; border-radius: 10px; border: 1px solid var(--border);
            background: #fff; color: var(--text); font: inherit; margin-bottom: 14px;
            font-size: 16px; /* evita zoom no iOS */
        }
        input:focus, select:focus, textarea:focus { outline: 2px solid #cbd5e1; border-color: #94a3b8; }
        button, .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            border: 0; border-radius: 10px; padding: 12px 16px; background: var(--accent);
            color: #fff; font-weight: 600; cursor: pointer; text-decoration: none; font: inherit;
            min-height: 44px;
            max-width: 100%;
        }
        .btn-secondary { background: #fff; color: var(--text); border: 1px solid var(--border); }
        .btn-danger { background: #fff; color: var(--danger); border: 1px solid #fecaca; }
        .btn-sm { padding: 8px 12px; font-size: 13px; min-height: 36px; }
        .table-wrap {
            width: 100%; max-width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior-x: contain;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            text-align: left; padding: 12px 8px; border-bottom: 1px solid var(--border);
            font-size: 14px; vertical-align: top;
            overflow-wrap: anywhere; word-break: break-word;
        }
        th { color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
        code, .mono {
            font-family: "IBM Plex Mono", ui-monospace, monospace;
            font-size: 12px;
            overflow-wrap: anywhere;
            word-break: break-word;
            max-width: 100%;
        }
        .license-key {
            display: block;
            width: 100%;
            max-width: 100%;
            padding: 10px 12px;
            background: var(--accent-soft);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-family: "IBM Plex Mono", ui-monospace, monospace;
            font-size: 12px;
            line-height: 1.45;
            overflow-wrap: anywhere;
            word-break: break-all;
        }
        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px;
            border: 1px solid var(--border); background: var(--accent-soft);
            max-width: 100%;
        }
        .badge.ok { color: var(--ok); border-color: #bbf7d0; background: #f0fdf4; }
        .badge.bad { color: var(--danger); border-color: #fecaca; background: #fef2f2; }
        .flash { margin-bottom: 16px; padding: 12px 14px; border-radius: 12px; border: 1px solid #bbf7d0; background: #f0fdf4; color: #166534; overflow-wrap: anywhere; }
        .error { margin-bottom: 16px; padding: 12px 14px; border-radius: 12px; border: 1px solid #fecaca; background: #fef2f2; color: var(--danger); overflow-wrap: anywhere; }
        .hero-card { padding: clamp(20px, 4vw, 32px); }
        .docs { line-height: 1.65; overflow-wrap: anywhere; word-break: break-word; max-width: 100%; }
        .docs h1, .docs h2, .docs h3 { letter-spacing: -.02em; }
        .docs pre, .docs pre.code {
            background: #0f172a; color: #e2e8f0; padding: 14px; border-radius: 12px;
            overflow-x: auto; max-width: 100%; white-space: pre; -webkit-overflow-scrolling: touch;
        }
        .docs code { background: #f1f5f9; padding: 1px 6px; border-radius: 6px; word-break: break-word; }
        .docs pre.code code, .docs pre code { background: transparent; padding: 0; color: inherit; word-break: normal; }
        .docs a { overflow-wrap: anywhere; word-break: break-all; }
        .docs img, .docs table { max-width: 100%; }
        .auth-wrap {
            min-height: 100vh; min-height: 100dvh; display: grid; place-items: center;
            padding: 20px 16px calc(24px + var(--safe-bottom));
            width: 100%; max-width: 100vw; overflow-x: clip;
        }
        .auth-card { width: 100%; max-width: min(420px, 100%); min-width: 0; }
        .stack { display: flex; flex-direction: column; gap: 12px; max-width: 100%; min-width: 0; }
        .row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; max-width: 100%; min-width: 0; }
        .row > * { min-width: 0; max-width: 100%; }
        .row > form, .row > .btn, .row > button { flex: 1 1 auto; min-width: min(100%, 160px); }
        .license-list { display: flex; flex-direction: column; gap: 12px; }
        .license-item {
            display: flex; flex-direction: column; gap: 8px;
            padding: 12px; border: 1px solid var(--border); border-radius: 12px; background: #fafbfc;
        }
        .license-item .meta { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .mobile-nav {
            display: none;
            position: fixed; left: 12px; right: 12px; bottom: calc(12px + var(--safe-bottom));
            z-index: 50; background: rgba(255,255,255,.94); backdrop-filter: blur(10px);
            border: 1px solid var(--border); border-radius: 18px; box-shadow: var(--shadow);
            padding: 8px; gap: 4px; justify-content: space-between;
        }
        .mobile-nav a {
            flex: 1; text-align: center; text-decoration: none; color: var(--muted);
            font-size: 11px; font-weight: 600; padding: 10px 4px; border-radius: 12px;
        }
        .mobile-nav a.active { background: var(--accent-soft); color: var(--text); }

        @media (max-width: 860px) {
            .grid-2 { grid-template-columns: minmax(0, 1fr); }
            .nav-toggle { display: inline-flex; }
            .topbar { flex-wrap: wrap; }
            .nav {
                display: none; width: 100%; flex-direction: column; align-items: stretch;
                gap: 4px; padding-top: 8px; border-top: 1px solid var(--border); margin-top: 4px;
            }
            .nav a { border-radius: 12px; padding: 12px 14px; white-space: normal; }
            #nav-open:checked ~ .nav { display: flex; }
            #nav-open:checked + .nav-toggle span { background: transparent; }
            #nav-open:checked + .nav-toggle span::before { top: 0; transform: rotate(45deg); }
            #nav-open:checked + .nav-toggle span::after { top: 0; transform: rotate(-45deg); }
            .card { padding: 16px; }
            button, .btn { width: 100%; }
            .btn-sm { width: auto; max-width: 100%; }
            .row .btn, .row button { width: auto; }
            th, td { padding: 10px 6px; font-size: 13px; }
        }

        @media (max-width: 640px) {
            .shell { padding-left: 12px; padding-right: 12px; }
            .mobile-nav.customer-only { display: flex; }
            .topbar .nav a[href="/logout"] { color: var(--danger); }
            .brand { max-width: calc(100% - 56px); }
            .brand-logo { height: 28px; max-width: 140px; }
            .hide-mobile { display: none !important; }
            .table-desktop { display: none !important; }
            button.btn-sm, .btn.btn-sm { width: 100%; }
        }

        @media (min-width: 641px) {
            .table-mobile { display: none !important; }
        }

        @media (min-width: 861px) {
            .shell { padding: 24px 20px 64px; }
            .topbar { border-radius: 999px; padding: 14px 18px; margin-bottom: 28px; }
            .brand { max-width: min(40vw, 320px); flex: 0 1 auto; }
            .brand-logo { height: 34px; max-width: 180px; }
            .mobile-nav { display: none !important; }
        }
    </style>
</head>
<body>
<?php if ($nav === 'customer'): ?>
<div class="shell">
    <div class="topbar">
        <a class="brand" href="/app">
            <img class="brand-logo" src="/assets/logo.png" alt="<?= htmlspecialchars($appName) ?>" width="160" height="40">
        </a>
        <input type="checkbox" id="nav-open" aria-hidden="true">
        <label class="nav-toggle" for="nav-open" aria-label="Abrir menu"><span></span></label>
        <div class="nav">
            <a href="/app" class="<?= ($active ?? '') === 'dashboard' ? 'active' : '' ?>">Início</a>
            <a href="/app/license" class="<?= ($active ?? '') === 'license' ? 'active' : '' ?>">Licença</a>
            <a href="/app/install" class="<?= ($active ?? '') === 'install' ? 'active' : '' ?>">Instalação + Update</a>
            <a href="/app/products" class="<?= ($active ?? '') === 'products' ? 'active' : '' ?>">Produtos</a>
            <a href="/app/docs" class="<?= ($active ?? '') === 'docs' ? 'active' : '' ?>">Documentação</a>
            <a href="/app/account" class="<?= ($active ?? '') === 'account' ? 'active' : '' ?>">Conta</a>
            <form method="post" action="/logout" style="display:inline;margin:0">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\LicenseApi\Security::csrfToken()) ?>">
                <button type="submit" class="btn btn-secondary btn-sm" style="border-radius:999px;padding:8px 12px;min-height:0">Sair</button>
            </form>
        </div>
    </div>
    <?= $content ?>
</div>
<nav class="mobile-nav customer-only" aria-label="Navegação principal">
    <a href="/app" class="<?= ($active ?? '') === 'dashboard' ? 'active' : '' ?>">Início</a>
    <a href="/app/license" class="<?= ($active ?? '') === 'license' ? 'active' : '' ?>">Licença</a>
    <a href="/app/install" class="<?= ($active ?? '') === 'install' ? 'active' : '' ?>">Instalar</a>
    <a href="/app/docs" class="<?= ($active ?? '') === 'docs' ? 'active' : '' ?>">Docs</a>
    <a href="/app/account" class="<?= ($active ?? '') === 'account' ? 'active' : '' ?>">Conta</a>
</nav>
<?php elseif ($nav === 'admin'): ?>
<div class="shell<?= ! empty($wide) ? ' shell-wide' : '' ?>">
    <div class="topbar">
        <a class="brand" href="/admin">
            <img class="brand-logo" src="/assets/logo.png" alt="<?= htmlspecialchars($appName) ?>" width="160" height="40">
            <span class="brand-admin-label">Admin</span>
        </a>
        <input type="checkbox" id="nav-open" aria-hidden="true">
        <label class="nav-toggle" for="nav-open" aria-label="Abrir menu"><span></span></label>
        <div class="nav">
            <a href="/admin">Licenças</a>
            <a href="/admin/releases">Releases</a>
            <a href="/admin/products">Produtos</a>
            <a href="/admin/customers">Clientes</a>
            <a href="/admin/orders">Pedidos</a>
            <a href="/admin/docs">Docs</a>
            <a href="/app/docs" target="_blank" rel="noopener">Guia</a>
            <a href="/admin/webhooks">Webhooks entrada</a>
            <a href="/admin/outbound-webhooks">Webhooks saída</a>
            <a href="/admin/account">Conta</a>
            <a href="/admin/settings">Configurações</a>
            <form method="post" action="/logout" style="display:inline;margin:0">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\LicenseApi\Security::csrfToken()) ?>">
                <button type="submit" class="btn btn-secondary btn-sm" style="border-radius:999px;padding:8px 12px;min-height:0">Sair</button>
            </form>
        </div>
    </div>
    <?= $content ?>
</div>
<?php else: ?>
    <?= $content ?>
<?php endif; ?>
<script src="/assets/app.js" defer></script>
</body>
</html>
