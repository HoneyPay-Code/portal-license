<?php
/** @var list<array> $sections */
/** @var bool $hasAccess */
$firstSlug = null;
if ($hasAccess) {
    foreach ($sections as $section) {
        foreach (($section['lessons'] ?? []) as $lesson) {
            $firstSlug = (string) $lesson['slug'];
            break 2;
        }
    }
}
?>
<?php if (! $hasAccess): ?>
    <div class="docs-locked">
        <h1>Guia Honey Pay</h1>
        <p>Este guia completo fica liberado após a compra do produto.</p>
        <p><a href="/app/products">Ver meus produtos →</a></p>
    </div>
<?php else: ?>
    <article class="docs-article">
        <div class="docs-home-hero">
            <h1>Guia completo Honey Pay</h1>
            <p class="lead">Do zero até o sistema rodando e recebendo pagamentos. Não precisa ser programador.</p>
            <div class="docs-home-meta">
                <span class="docs-chip">Portal oficial: portal.honeypay.tech</span>
                <span class="docs-chip">Passo a passo para iniciantes</span>
            </div>
            <?php if ($firstSlug): ?>
                <p><a href="/app/docs/<?= htmlspecialchars($firstSlug) ?>"><strong>Começar pelo primeiro capítulo →</strong></a></p>
            <?php endif; ?>
        </div>

        <h2>Como usar este guia</h2>
        <ol>
            <li>No celular, toque em <strong>Sumário</strong> para ver os capítulos.</li>
            <li>No computador, o menu fica à esquerda. Use a busca para achar uma palavra (ex.: “cron”, “PIX”, “licença”).</li>
            <li>No final de cada página, avance com <strong>Próxima página</strong>.</li>
            <li>Quando aparecer um quadro amarelo ou verde, leia com atenção — são avisos importantes.</li>
        </ol>

        <h2>Por onde começar?</h2>
        <div class="docs-home-grid">
            <a class="docs-home-card" href="/app/docs/visao-geral">
                <div class="kicker">Nunca usou o sistema</div>
                <div class="name">Começar do zero</div>
                <div class="desc">Entenda o que é o portal, o que é o gateway e quem faz o quê.</div>
            </a>
            <a class="docs-home-card" href="/app/docs/qual-instalacao-escolher">
                <div class="kicker">Vai instalar agora</div>
                <div class="name">Qual instalação escolher?</div>
                <div class="desc">Hospedagem compartilhada (Hostinger, etc.) ou servidor VPS.</div>
            </a>
            <a class="docs-home-card" href="/app/docs/chave-dominio">
                <div class="kicker">Já comprou</div>
                <div class="name">Licença e domínio</div>
                <div class="desc">Onde achar a chave e como funciona a regra de 1 domínio.</div>
            </a>
            <a class="docs-home-card" href="/app/install">
                <div class="kicker">Baixar arquivos</div>
                <div class="name">Página de instalação</div>
                <div class="desc">ZIP para hospedagem e comando pronto para VPS.</div>
            </a>
        </div>

        <h2>Todos os capítulos</h2>
        <?php foreach ($sections as $section): ?>
            <h3 id="<?= htmlspecialchars(\LicenseApi\Markdown::slugify((string) $section['title'])) ?>"><?= htmlspecialchars((string) $section['title']) ?></h3>
            <ul>
                <?php foreach (($section['lessons'] ?? []) as $lesson): ?>
                    <li>
                        <a href="/app/docs/<?= htmlspecialchars((string) $lesson['slug']) ?>">
                            <?= htmlspecialchars((string) $lesson['title']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </article>
<?php endif; ?>
