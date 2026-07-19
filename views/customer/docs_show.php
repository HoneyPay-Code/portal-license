<?php
/** @var array $lesson */
/** @var string $html */
/** @var list<array> $sections */
?>
<div class="grid grid-2">
    <div class="card docs">
        <p class="muted"><a href="/app/docs">← Documentação</a></p>
        <?= $html ?>
    </div>
    <div class="card">
        <h2>Sumário</h2>
        <?php foreach ($sections as $section): ?>
            <p style="margin:12px 0 6px;font-weight:600"><?= htmlspecialchars((string) $section['title']) ?></p>
            <ul style="margin:0;padding-left:18px">
                <?php foreach (($section['lessons'] ?? []) as $item): ?>
                    <li><a href="/app/docs/<?= htmlspecialchars((string) $item['slug']) ?>"><?= htmlspecialchars((string) $item['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </div>
</div>
