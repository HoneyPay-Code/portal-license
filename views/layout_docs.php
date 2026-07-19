<?php
/** @var string $title */
/** @var string $appName */
/** @var string $content */
/** @var list<array> $sections */
/** @var string|null $activeSlug */
/** @var list<array{id:string,text:string,level:int}> $toc */
/** @var array{slug:string,title:string}|null $prev */
/** @var array{slug:string,title:string}|null $next */
/** @var bool $hasAccess */
/** @var bool $viewerIsAdmin */
/** @var string $docsBase */
/** @var string $docsHomeHref */
/** @var string $docsSecondaryHref */
/** @var string $docsSecondaryLabel */
/** @var string $docsPrimaryLabel */
$sections = $sections ?? [];
$activeSlug = $activeSlug ?? null;
$toc = $toc ?? [];
$prev = $prev ?? null;
$next = $next ?? null;
$hasAccess = $hasAccess ?? true;
$viewerIsAdmin = ! empty($viewerIsAdmin);
$docsBase = $docsBase ?? '/app/docs';
$docsHomeHref = $docsHomeHref ?? '/app';
$docsSecondaryHref = $docsSecondaryHref ?? '/app/install';
$docsSecondaryLabel = $docsSecondaryLabel ?? 'Baixar / instalar';
$docsPrimaryLabel = $docsPrimaryLabel ?? 'Minha conta';
$searchIndex = [];
$pageCount = 0;
foreach ($sections as $section) {
    foreach (($section['lessons'] ?? []) as $lesson) {
        $pageCount++;
        $searchIndex[] = [
            'slug' => (string) $lesson['slug'],
            'title' => (string) $lesson['title'],
            'section' => (string) $section['title'],
            'body' => mb_substr(strip_tags((string) ($lesson['body_markdown'] ?? '')), 0, 500),
        ];
    }
}
$activeSectionTitle = null;
foreach ($sections as $section) {
    foreach (($section['lessons'] ?? []) as $item) {
        if ((string) $item['slug'] === (string) $activeSlug) {
            $activeSectionTitle = (string) $section['title'];
            break 2;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= htmlspecialchars(($title ?? '').' · Guia Honey Pay') ?></title>
    <link rel="icon" href="/assets/favicon.png" type="image/png" sizes="any">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,500;0,600;0,700;1,500&family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --docs-bg: #f6f4ef;
            --docs-surface: #ffffff;
            --docs-text: #1a1a1a;
            --docs-muted: #6b7280;
            --docs-border: #e8e4db;
            --docs-accent: #1a1a1a;
            --docs-honey: #fbae0e;
            --docs-honey-soft: #fff6e0;
            --docs-honey-border: #f5d78a;
            --docs-accent-soft: #f3f1eb;
            --docs-link: #9a6b00;
            --docs-sidebar: 300px;
            --docs-toc: 240px;
            --docs-tip: #eef8f1;
            --docs-tip-border: #b7e0c2;
            --docs-warn: #fff8eb;
            --docs-warn-border: #f5d78a;
            --safe-bottom: env(safe-area-inset-bottom, 0px);
            --top-h: 56px;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: "DM Sans", system-ui, sans-serif;
            background:
                radial-gradient(900px 420px at 100% -8%, #fff3d0 0%, transparent 55%),
                radial-gradient(700px 360px at -8% 0%, #f0f7ff 0%, transparent 50%),
                var(--docs-bg);
            color: var(--docs-text);
            min-height: 100vh;
            min-height: 100dvh;
        }
        a { color: inherit; }
        .docs-progress {
            position: fixed; top: 0; left: 0; height: 3px; width: 0;
            background: linear-gradient(90deg, #fbae0e, #f59e0b);
            z-index: 80; pointer-events: none;
        }
        .docs-top {
            position: sticky; top: 0; z-index: 50;
            display: flex; align-items: center; gap: 12px;
            height: var(--top-h);
            padding: 0 16px;
            background: rgba(255,255,255,.88);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--docs-border);
        }
        .docs-brand {
            display: inline-flex; align-items: center; gap: 10px;
            text-decoration: none; min-width: 0;
        }
        .docs-brand img { height: 28px; width: auto; max-width: 140px; object-fit: contain; }
        .docs-brand-label {
            font-size: 12px; font-weight: 700; color: #7a5a00;
            background: var(--docs-honey-soft);
            border: 1px solid var(--docs-honey-border);
            border-radius: 999px; padding: 4px 10px;
            white-space: nowrap;
        }
        .docs-top-links { margin-left: auto; display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .docs-top-links a, .docs-menu-btn {
            text-decoration: none; font-size: 13px; font-weight: 600;
            color: var(--docs-muted); padding: 8px 12px; border-radius: 10px;
            border: 1px solid transparent; background: transparent; cursor: pointer; font: inherit;
        }
        .docs-top-links a:hover, .docs-menu-btn:hover { background: var(--docs-accent-soft); color: var(--docs-text); }
        .docs-menu-btn { display: none; border-color: var(--docs-border); background: #fff; }
        .docs-shell {
            display: grid;
            grid-template-columns: var(--docs-sidebar) minmax(0, 1fr) var(--docs-toc);
            min-height: calc(100vh - var(--top-h));
            min-height: calc(100dvh - var(--top-h));
        }
        .docs-sidebar {
            position: sticky; top: var(--top-h); align-self: start;
            height: calc(100vh - var(--top-h)); height: calc(100dvh - var(--top-h));
            overflow: auto; padding: 18px 14px 40px;
            border-right: 1px solid var(--docs-border);
            background: rgba(255,255,255,.72);
        }
        .docs-search-wrap { position: relative; margin-bottom: 16px; }
        .docs-search {
            width: 100%; padding: 11px 12px 11px 38px; border-radius: 12px;
            border: 1px solid var(--docs-border); font: inherit; font-size: 14px;
            background: #fff;
        }
        .docs-search:focus { outline: 2px solid #f5d78a; border-color: var(--docs-honey); }
        .docs-search-icon {
            position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
            width: 16px; height: 16px; color: var(--docs-muted); pointer-events: none;
        }
        .docs-search-hint {
            font-size: 11px; color: var(--docs-muted); margin: -8px 0 14px; padding-left: 4px;
        }
        .docs-nav-section { margin-bottom: 18px; }
        .docs-nav-section-title {
            font-size: 11px; font-weight: 700; letter-spacing: .07em;
            text-transform: uppercase; color: var(--docs-muted);
            padding: 4px 10px; margin-bottom: 4px;
        }
        .docs-nav-link {
            display: block; text-decoration: none; font-size: 13.5px;
            padding: 8px 10px; border-radius: 10px; color: #374151;
            line-height: 1.35; border: 1px solid transparent;
        }
        .docs-nav-link:hover { background: var(--docs-accent-soft); color: var(--docs-text); }
        .docs-nav-link.active {
            background: var(--docs-honey-soft);
            border-color: var(--docs-honey-border);
            color: #7a5a00; font-weight: 700;
        }
        .docs-nav-link[hidden], .docs-nav-section[hidden] { display: none !important; }
        .docs-main {
            padding: 28px 40px 96px;
            max-width: 760px;
            width: 100%;
            margin: 0 auto;
            min-width: 0;
        }
        .docs-crumb {
            display: flex; flex-wrap: wrap; gap: 6px; align-items: center;
            font-size: 13px; color: var(--docs-muted); margin-bottom: 18px;
        }
        .docs-crumb a { text-decoration: none; color: var(--docs-muted); }
        .docs-crumb a:hover { color: var(--docs-text); }
        .docs-crumb .sep { opacity: .5; }
        .docs-toc {
            position: sticky; top: var(--top-h); align-self: start;
            height: calc(100vh - var(--top-h)); height: calc(100dvh - var(--top-h));
            overflow: auto; padding: 28px 18px;
            border-left: 1px solid var(--docs-border);
            font-size: 13px;
            background: rgba(255,255,255,.4);
        }
        .docs-toc-title {
            font-size: 11px; font-weight: 700; letter-spacing: .07em;
            text-transform: uppercase; color: var(--docs-muted); margin-bottom: 12px;
        }
        .docs-toc a {
            display: block; text-decoration: none; color: var(--docs-muted);
            padding: 5px 0 5px 10px; line-height: 1.4;
            border-left: 2px solid transparent;
        }
        .docs-toc a:hover, .docs-toc a.active { color: var(--docs-text); border-left-color: var(--docs-honey); }
        .docs-toc a.l3 { padding-left: 20px; font-size: 12px; }
        .docs-article { line-height: 1.75; overflow-wrap: anywhere; font-size: 16px; }
        .docs-article h1 {
            font-family: "Plus Jakarta Sans", "DM Sans", system-ui, sans-serif;
            font-size: clamp(26px, 4vw, 34px); font-weight: 700;
            letter-spacing: -.035em; margin: 0 0 14px; line-height: 1.2;
            color: #111827;
        }
        .docs-article h2 {
            font-family: "Plus Jakarta Sans", "DM Sans", system-ui, sans-serif;
            font-size: 20px; font-weight: 700; letter-spacing: -.025em;
            margin: 36px 0 12px; padding-top: 12px;
            border-top: 1px solid var(--docs-border);
            color: #111827;
        }
        .docs-article h2:first-of-type { border-top: 0; padding-top: 0; margin-top: 28px; }
        .docs-article h3 {
            font-family: "Plus Jakarta Sans", "DM Sans", system-ui, sans-serif;
            font-size: 16px; margin: 26px 0 8px; font-weight: 600; letter-spacing: -.02em;
            color: #111827;
        }
        .docs-article p { margin: 0 0 15px; color: #2d2d2d; }
        .docs-article ul, .docs-article ol { margin: 0 0 18px; padding-left: 0; list-style: none; }
        .docs-article ul li, .docs-article ol li {
            position: relative; margin-bottom: 10px; padding-left: 28px;
        }
        .docs-article ul li::before {
            content: ""; position: absolute; left: 8px; top: .65em;
            width: 6px; height: 6px; border-radius: 50%; background: var(--docs-honey);
        }
        .docs-article ol { counter-reset: docs-step; }
        .docs-article ol li { counter-increment: docs-step; }
        .docs-article ol li::before {
            content: counter(docs-step);
            position: absolute; left: 0; top: 0;
            width: 22px; height: 22px; border-radius: 999px;
            background: var(--docs-honey-soft); border: 1px solid var(--docs-honey-border);
            color: #7a5a00; font-size: 12px; font-weight: 700;
            display: inline-flex; align-items: center; justify-content: center;
            line-height: 1;
        }
        .docs-article a { color: var(--docs-link); text-decoration: underline; text-underline-offset: 3px; }
        .docs-article code {
            font-family: "IBM Plex Mono", ui-monospace, monospace;
            font-size: 13px; background: #f3f1eb; padding: 2px 7px; border-radius: 6px;
            border: 1px solid #ebe6db;
        }
        .docs-code-wrap { position: relative; margin: 0 0 18px; }
        .docs-code-copy {
            position: absolute; top: 10px; right: 10px; z-index: 2;
            font: inherit; font-size: 12px; font-weight: 600;
            padding: 6px 10px; border-radius: 8px; cursor: pointer;
            border: 1px solid #334155; background: #1e293b; color: #e2e8f0;
        }
        .docs-code-copy:hover { background: #334155; }
        .docs-article pre.code {
            background: #111827; color: #e5e7eb; padding: 16px 18px; border-radius: 14px;
            overflow-x: auto; margin: 0; -webkit-overflow-scrolling: touch;
            border: 1px solid #1f2937;
        }
        .docs-article pre.code code { background: transparent; padding: 0; color: inherit; font-size: 13px; border: 0; }
        .docs-article hr { border: 0; border-top: 1px solid var(--docs-border); margin: 32px 0; }
        .docs-article .docs-img { max-width: 100%; border-radius: 14px; margin: 8px 0 16px; border: 1px solid var(--docs-border); }
        .docs-table-wrap { overflow-x: auto; margin: 0 0 20px; border: 1px solid var(--docs-border); border-radius: 14px; background: #fff; }
        .docs-table { width: 100%; border-collapse: collapse; font-size: 14.5px; }
        .docs-table th, .docs-table td { padding: 12px 14px; border-bottom: 1px solid var(--docs-border); text-align: left; vertical-align: top; }
        .docs-table th { background: var(--docs-honey-soft); font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #7a5a00; }
        .docs-table tr:last-child td { border-bottom: 0; }
        .docs-callout {
            border: 1px solid var(--docs-border); border-radius: 14px;
            padding: 14px 16px 14px 18px; margin: 0 0 18px;
            background: var(--docs-accent-soft);
            border-left: 4px solid #9ca3af;
        }
        .docs-callout p { margin: 0 0 6px; }
        .docs-callout p:last-child { margin-bottom: 0; }
        .docs-callout-tip { background: var(--docs-tip); border-color: var(--docs-tip-border); border-left-color: #22c55e; }
        .docs-callout-warn { background: var(--docs-warn); border-color: var(--docs-warn-border); border-left-color: #f59e0b; }
        .docs-home-hero {
            padding: 8px 0 8px;
            margin-bottom: 8px;
        }
        .docs-home-hero p.lead {
            font-size: 18px; color: #4b5563; margin-bottom: 18px; max-width: 36em;
        }
        .docs-home-meta {
            display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 28px;
        }
        .docs-chip {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 12px; font-weight: 600; color: #7a5a00;
            background: var(--docs-honey-soft); border: 1px solid var(--docs-honey-border);
            border-radius: 999px; padding: 6px 12px;
        }
        .docs-home-grid { display: grid; gap: 12px; margin: 20px 0 28px; }
        .docs-home-card {
            display: block; text-decoration: none; padding: 16px 18px;
            background: #fff; border: 1px solid var(--docs-border); border-radius: 16px;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .docs-home-card:hover {
            border-color: var(--docs-honey-border);
            box-shadow: 0 8px 24px rgba(251, 174, 14, .12);
        }
        .docs-home-card .kicker {
            font-size: 11px; font-weight: 700; letter-spacing: .06em;
            text-transform: uppercase; color: var(--docs-muted); margin-bottom: 4px;
        }
        .docs-home-card .name { font-weight: 700; font-size: 16px; color: var(--docs-text); margin-bottom: 4px; }
        .docs-home-card .desc { font-size: 13.5px; color: var(--docs-muted); line-height: 1.45; }
        .docs-pager {
            display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
            margin-top: 44px; padding-top: 24px; border-top: 1px solid var(--docs-border);
        }
        .docs-pager a {
            display: block; text-decoration: none; padding: 16px 18px;
            border: 1px solid var(--docs-border); border-radius: 14px; background: #fff;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .docs-pager a:hover {
            border-color: var(--docs-honey-border);
            box-shadow: 0 8px 20px rgba(251, 174, 14, .1);
        }
        .docs-pager .label { display: block; font-size: 12px; color: var(--docs-muted); margin-bottom: 4px; }
        .docs-pager .title { font-weight: 700; font-size: 14px; }
        .docs-pager .next { text-align: right; }
        .docs-locked {
            padding: 48px 20px; text-align: center; color: var(--docs-muted);
            background: #fff; border: 1px solid var(--docs-border); border-radius: 18px;
        }
        .docs-backdrop {
            display: none; position: fixed; inset: 0; background: rgba(26,26,26,.42); z-index: 60;
        }
        .docs-backdrop.open { display: block; }
        @media (max-width: 1100px) {
            .docs-shell { grid-template-columns: var(--docs-sidebar) minmax(0, 1fr); }
            .docs-toc { display: none; }
        }
        @media (max-width: 860px) {
            .docs-menu-btn { display: inline-flex; }
            .docs-shell { grid-template-columns: minmax(0, 1fr); }
            .docs-sidebar {
                position: fixed; left: 0; top: 0; bottom: 0; z-index: 70;
                width: min(90vw, 340px); height: 100%; transform: translateX(-105%);
                transition: transform .2s ease; box-shadow: 8px 0 28px rgba(26,26,26,.14);
                background: #fff;
            }
            .docs-sidebar.open { transform: translateX(0); }
            .docs-main { padding: 20px 16px calc(56px + var(--safe-bottom)); }
            .docs-pager { grid-template-columns: 1fr; }
            .docs-pager .next { text-align: left; }
            .docs-top-links a.hide-mobile { display: none; }
            .docs-article { font-size: 15.5px; }
        }
    </style>
</head>
<body>
<div class="docs-progress" id="docs-progress" aria-hidden="true"></div>
<div class="docs-top">
    <button type="button" class="docs-menu-btn" id="docs-menu-btn" aria-label="Abrir sumário">Sumário</button>
    <a class="docs-brand" href="<?= htmlspecialchars($docsHomeHref) ?>">
        <img src="/assets/logo.png" alt="<?= htmlspecialchars($appName) ?>" width="140" height="32">
        <span class="docs-brand-label"><?= $viewerIsAdmin ? 'Guia · Admin' : 'Guia' ?></span>
    </a>
    <div class="docs-top-links">
        <a class="hide-mobile" href="<?= htmlspecialchars($docsHomeHref) ?>"><?= htmlspecialchars($docsPrimaryLabel) ?></a>
        <a class="hide-mobile" href="<?= htmlspecialchars($docsSecondaryHref) ?>"><?= htmlspecialchars($docsSecondaryLabel) ?></a>
        <a href="<?= htmlspecialchars($docsBase) ?>">Início do guia</a>
    </div>
</div>
<div class="docs-backdrop" id="docs-backdrop" hidden></div>
<div class="docs-shell">
    <aside class="docs-sidebar" id="docs-sidebar" aria-label="Sumário">
        <div class="docs-search-wrap">
            <svg class="docs-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input type="search" class="docs-search" id="docs-search" placeholder="Buscar no guia…" autocomplete="off">
        </div>
        <p class="docs-search-hint"><?= (int) $pageCount ?> páginas · digite para filtrar</p>
        <?php if ($hasAccess): ?>
            <?php foreach ($sections as $section): ?>
                <div class="docs-nav-section" data-section>
                    <div class="docs-nav-section-title"><?= htmlspecialchars((string) $section['title']) ?></div>
                    <?php foreach (($section['lessons'] ?? []) as $item): ?>
                        <?php $slug = (string) $item['slug']; ?>
                        <a class="docs-nav-link <?= $activeSlug === $slug ? 'active' : '' ?>"
                           href="<?= htmlspecialchars($docsBase) ?>/<?= htmlspecialchars($slug) ?>"
                           data-slug="<?= htmlspecialchars($slug) ?>"
                           data-title="<?= htmlspecialchars((string) $item['title']) ?>"
                           data-section="<?= htmlspecialchars((string) $section['title']) ?>">
                            <?= htmlspecialchars((string) $item['title']) ?>
                            <?php if ($viewerIsAdmin && empty($item['published'])): ?>
                                <span style="opacity:.65;font-size:11px;font-weight:600"> · rascunho</span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="padding:10px;color:var(--docs-muted);font-size:14px;line-height:1.5">Compre o produto para liberar este guia completo.</p>
        <?php endif; ?>
    </aside>
    <main class="docs-main">
        <?php if ($activeSlug && $activeSectionTitle): ?>
            <nav class="docs-crumb" aria-label="Caminho">
                <a href="<?= htmlspecialchars($docsBase) ?>">Guia</a>
                <span class="sep">/</span>
                <span><?= htmlspecialchars($activeSectionTitle) ?></span>
            </nav>
        <?php endif; ?>
        <?= $content ?>
        <?php if ($prev || $next): ?>
            <nav class="docs-pager" aria-label="Páginas">
                <div>
                    <?php if ($prev): ?>
                        <a href="<?= htmlspecialchars($docsBase) ?>/<?= htmlspecialchars($prev['slug']) ?>">
                            <span class="label">← Página anterior</span>
                            <span class="title"><?= htmlspecialchars($prev['title']) ?></span>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="next">
                    <?php if ($next): ?>
                        <a href="<?= htmlspecialchars($docsBase) ?>/<?= htmlspecialchars($next['slug']) ?>">
                            <span class="label">Próxima página →</span>
                            <span class="title"><?= htmlspecialchars($next['title']) ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </main>
    <aside class="docs-toc" aria-label="Nesta página">
        <?php if ($toc): ?>
            <div class="docs-toc-title">Nesta página</div>
            <?php foreach ($toc as $item): ?>
                <a class="l<?= (int) $item['level'] ?>" href="#<?= htmlspecialchars($item['id']) ?>"><?= htmlspecialchars($item['text']) ?></a>
            <?php endforeach; ?>
        <?php endif; ?>
    </aside>
</div>
<script>
(function () {
    var index = <?= json_encode($searchIndex, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var search = document.getElementById('docs-search');
    var sidebar = document.getElementById('docs-sidebar');
    var backdrop = document.getElementById('docs-backdrop');
    var menuBtn = document.getElementById('docs-menu-btn');
    var progress = document.getElementById('docs-progress');

    function setOpen(open) {
        sidebar.classList.toggle('open', open);
        backdrop.hidden = !open;
        backdrop.classList.toggle('open', open);
        document.body.style.overflow = open ? 'hidden' : '';
    }
    if (menuBtn) menuBtn.addEventListener('click', function () { setOpen(!sidebar.classList.contains('open')); });
    if (backdrop) backdrop.addEventListener('click', function () { setOpen(false); });

    function onScroll() {
        if (!progress) return;
        var el = document.documentElement;
        var max = el.scrollHeight - el.clientHeight;
        var pct = max > 0 ? (el.scrollTop / max) * 100 : 0;
        progress.style.width = pct + '%';
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    document.querySelectorAll('.docs-article pre.code').forEach(function (pre) {
        var wrap = document.createElement('div');
        wrap.className = 'docs-code-wrap';
        pre.parentNode.insertBefore(wrap, pre);
        wrap.appendChild(pre);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'docs-code-copy';
        btn.textContent = 'Copiar';
        btn.addEventListener('click', function () {
            var text = pre.innerText || '';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () {
                    btn.textContent = 'Copiado!';
                    setTimeout(function () { btn.textContent = 'Copiar'; }, 1500);
                });
            }
        });
        wrap.appendChild(btn);
    });

    if (search) {
        search.addEventListener('input', function () {
            var q = (search.value || '').toLowerCase().trim();
            document.querySelectorAll('.docs-nav-link').forEach(function (link) {
                if (!q) { link.hidden = false; return; }
                var slug = link.getAttribute('data-slug') || '';
                var title = (link.getAttribute('data-title') || '').toLowerCase();
                var section = (link.getAttribute('data-section') || '').toLowerCase();
                var body = '';
                for (var i = 0; i < index.length; i++) {
                    if (index[i].slug === slug) { body = (index[i].body || '').toLowerCase(); break; }
                }
                link.hidden = !(title.indexOf(q) !== -1 || section.indexOf(q) !== -1 || body.indexOf(q) !== -1);
            });
            document.querySelectorAll('[data-section]').forEach(function (sec) {
                var any = false;
                sec.querySelectorAll('.docs-nav-link').forEach(function (l) { if (!l.hidden) any = true; });
                sec.hidden = !any;
            });
        });
    }

    var active = sidebar && sidebar.querySelector('.docs-nav-link.active');
    if (active) active.scrollIntoView({ block: 'nearest' });

    var tocLinks = document.querySelectorAll('.docs-toc a[href^="#"]');
    if (tocLinks.length) {
        var heads = [];
        tocLinks.forEach(function (a) {
            var id = a.getAttribute('href').slice(1);
            var el = document.getElementById(id);
            if (el) heads.push({ el: el, a: a });
        });
        function syncToc() {
            var current = heads[0] && heads[0].a;
            for (var i = 0; i < heads.length; i++) {
                if (heads[i].el.getBoundingClientRect().top <= 90) current = heads[i].a;
            }
            tocLinks.forEach(function (a) { a.classList.remove('active'); });
            if (current) current.classList.add('active');
        }
        window.addEventListener('scroll', syncToc, { passive: true });
        syncToc();
    }
})();
</script>
</body>
</html>
