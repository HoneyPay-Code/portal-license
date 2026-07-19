<?php /** @var list<array> $entitlements */ ?>
<div class="card" style="margin-bottom:16px">
    <h1>Produtos</h1>
    <p class="muted">Itens liberados pelas suas compras.</p>
</div>
<div class="grid">
<?php foreach ($entitlements as $item): ?>
    <div class="card">
        <h2><?= htmlspecialchars((string) $item['product_name']) ?></h2>
        <p class="muted"><?= htmlspecialchars((string) ($item['type'] ?? 'produto')) ?> · <?= htmlspecialchars((string) $item['status']) ?></p>
        <?php
            $checkout = \LicenseApi\SafeUrl::forHref(
                isset($item['checkout_url']) ? (string) $item['checkout_url'] : null,
                true
            );
        ?>
        <?php if ($checkout): ?>
            <a class="btn btn-secondary btn-sm" href="<?= htmlspecialchars($checkout, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Ver oferta</a>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?php if ($entitlements === []): ?>
    <div class="card"><p class="muted">Nenhum produto liberado.</p></div>
<?php endif; ?>
</div>
