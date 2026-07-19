<?php
/** @var list<array> $sections */
/** @var bool $hasAccess */
?>
<div class="card" style="margin-bottom:16px">
    <h1>Documentação</h1>
    <p class="muted">Aulas práticas de instalação e uso da licença.</p>
</div>
<?php if (! $hasAccess): ?>
<div class="card"><p class="muted">Compre um produto para liberar a documentação completa.</p></div>
<?php else: ?>
<div class="grid grid-2">
    <?php foreach ($sections as $section): ?>
        <div class="card">
            <h2><?= htmlspecialchars((string) $section['title']) ?></h2>
            <ul class="stack" style="list-style:none;padding:0;margin:0">
                <?php foreach (($section['lessons'] ?? []) as $lesson): ?>
                    <li><a href="/app/docs/<?= htmlspecialchars((string) $lesson['slug']) ?>"><?= htmlspecialchars((string) $lesson['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
