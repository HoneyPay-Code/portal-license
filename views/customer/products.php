<?php
/** @var list<array> $entitlements */
/** @var list<array> $catalog */
use LicenseApi\ProductService;
use LicenseApi\SafeUrl;

$entitlements = $entitlements ?? [];
$catalog = $catalog ?? [];
?>
<style>
    .prod-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }
    .prod-card { display: flex; flex-direction: column; overflow: hidden; padding: 0; }
    .prod-card .thumb {
        aspect-ratio: 16/10; background: #f1f5f9; display: flex; align-items: center; justify-content: center;
        overflow: hidden; border-bottom: 1px solid var(--border);
    }
    .prod-card .thumb img { width: 100%; height: 100%; object-fit: cover; }
    .prod-card .body { padding: 16px; display: flex; flex-direction: column; gap: 8px; flex: 1; }
    .prod-card h2 { margin: 0; font-size: 17px; }
    .prod-card .desc { margin: 0; font-size: 13.5px; color: var(--muted); line-height: 1.45; flex: 1; }
    .prod-card .price { font-weight: 700; font-size: 15px; }
    .prod-card .actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px; }
    .prod-section { margin-bottom: 28px; }
</style>

<div class="card" style="margin-bottom:16px">
    <h1 style="margin:0">Produtos</h1>
    <p class="muted" style="margin:6px 0 0">Seus itens liberados e o catálogo para comprar.</p>
</div>

<div class="prod-section">
    <h2 style="margin:0 0 12px;font-size:18px">Meus produtos</h2>
    <?php if ($entitlements === []): ?>
        <div class="card"><p class="muted" style="margin:0">Você ainda não tem produtos liberados. Compre abaixo ou aguarde a confirmação do pagamento.</p></div>
    <?php else: ?>
        <div class="prod-grid">
            <?php foreach ($entitlements as $item): ?>
                <?php
                    $kind = (string) ($item['kind'] ?? 'plugin');
                    $slug = (string) ($item['slug'] ?? '');
                    $hasZip = ! empty($item['plugin_zip_path']);
                    $hasImage = ! empty($item['image_path']);
                    $price = isset($item['price']) && $item['price'] !== null ? (float) $item['price'] : null;
                ?>
                <div class="card prod-card">
                    <div class="thumb">
                        <?php if ($hasImage): ?>
                            <img src="/app/products/<?= htmlspecialchars($slug) ?>/image" alt="">
                        <?php else: ?>
                            <span class="muted" style="font-size:13px"><?= $kind === 'gateway' ? 'Gateway' : 'Plugin' ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="body">
                        <div>
                            <span class="badge <?= $kind === 'gateway' ? 'ok' : '' ?>"><?= $kind === 'gateway' ? 'Gateway' : 'Plugin' ?></span>
                        </div>
                        <h2><?= htmlspecialchars((string) $item['product_name']) ?></h2>
                        <?php if (! empty($item['description'])): ?>
                            <p class="desc"><?= htmlspecialchars((string) $item['description']) ?></p>
                        <?php else: ?>
                            <p class="desc">Liberado na sua conta.</p>
                        <?php endif; ?>
                        <div class="actions">
                            <?php if ($kind === 'gateway'): ?>
                                <a class="btn btn-sm" href="/app/install">Instalar / baixar</a>
                                <a class="btn btn-secondary btn-sm" href="/app/docs">Documentação</a>
                            <?php elseif ($hasZip): ?>
                                <a class="btn btn-sm" href="/app/products/<?= htmlspecialchars($slug) ?>/download">Baixar ZIP</a>
                            <?php else: ?>
                                <span class="muted" style="font-size:13px">ZIP ainda não disponível</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="prod-section">
    <h2 style="margin:0 0 12px;font-size:18px">Disponíveis para compra</h2>
    <?php if ($catalog === []): ?>
        <div class="card"><p class="muted" style="margin:0">Nenhum outro produto no catálogo no momento.</p></div>
    <?php else: ?>
        <div class="prod-grid">
            <?php foreach ($catalog as $item): ?>
                <?php
                    $kind = (string) ($item['kind'] ?? 'plugin');
                    $slug = (string) ($item['slug'] ?? '');
                    $hasImage = ! empty($item['image_path']);
                    $price = isset($item['price']) && $item['price'] !== null ? (float) $item['price'] : null;
                    $checkout = SafeUrl::forHref(isset($item['checkout_url']) ? (string) $item['checkout_url'] : null, true);
                ?>
                <div class="card prod-card">
                    <div class="thumb">
                        <?php if ($hasImage): ?>
                            <img src="/app/products/<?= htmlspecialchars($slug) ?>/image" alt="">
                        <?php else: ?>
                            <span class="muted" style="font-size:13px"><?= $kind === 'gateway' ? 'Gateway' : 'Plugin' ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="body">
                        <div>
                            <span class="badge"><?= $kind === 'gateway' ? 'Gateway' : 'Plugin' ?></span>
                        </div>
                        <h2><?= htmlspecialchars((string) $item['name']) ?></h2>
                        <?php if (! empty($item['description'])): ?>
                            <p class="desc"><?= htmlspecialchars((string) $item['description']) ?></p>
                        <?php endif; ?>
                        <p class="price"><?= htmlspecialchars(ProductService::formatPrice($price, isset($item['currency']) ? (string) $item['currency'] : 'BRL')) ?></p>
                        <div class="actions">
                            <?php if ($checkout): ?>
                                <a class="btn btn-sm" href="<?= htmlspecialchars($checkout, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Comprar</a>
                            <?php else: ?>
                                <span class="muted" style="font-size:13px">Checkout em breve</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
