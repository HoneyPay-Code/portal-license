<?php
/** @var list<array> $entitlements */
/** @var list<array> $catalog */
/** @var string $csrf */
/** @var string|null $flash */
/** @var string|null $error */
use LicenseApi\ProductService;
use LicenseApi\SafeUrl;

$entitlements = $entitlements ?? [];
$catalog = $catalog ?? [];
$csrf = $csrf ?? '';
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
    .prod-card .meta { margin: 0; font-size: 12.5px; color: var(--muted); line-height: 1.4; }
    .prod-card .actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px; }
    .prod-section { margin-bottom: 28px; }
    .btn-refund {
        background: transparent; color: #b91c1c; border: 1px solid #fecaca;
    }
    .btn-refund:hover { background: #fef2f2; }
    .refund-modal {
        display: none; position: fixed; inset: 0; z-index: 1000;
        align-items: center; justify-content: center; padding: 16px;
        background: rgba(15, 23, 42, 0.45);
    }
    .refund-modal.open { display: flex; }
    .refund-modal .panel {
        background: #fff; border-radius: 14px; width: 100%; max-width: 420px;
        padding: 22px; box-shadow: 0 20px 50px rgba(15, 23, 42, 0.2);
    }
    .refund-modal h3 { margin: 0 0 8px; font-size: 18px; }
    .refund-modal textarea { width: 100%; min-height: 110px; resize: vertical; }
    .refund-modal .modal-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 14px; }
</style>

<?php if (! empty($flash)): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if (! empty($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

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
                    $orderAmount = isset($item['order_amount']) && $item['order_amount'] !== null
                        ? (float) $item['order_amount']
                        : (isset($item['price']) && $item['price'] !== null ? (float) $item['price'] : null);
                    $orderCurrency = (string) ($item['order_currency'] ?? $item['currency'] ?? 'BRL');
                    $paymentMethod = (string) ($item['order_payment_method'] ?? '');
                    $orderCreated = (string) ($item['order_created_at'] ?? '');
                    $orderRowId = isset($item['order_row_id']) ? (int) $item['order_row_id'] : 0;
                    $refundEligible = ! empty($item['refund_eligible']) && $orderRowId > 0;
                    $methodLabel = match (mb_strtolower($paymentMethod, 'UTF-8')) {
                        'pix' => 'Pix',
                        'card', 'credit_card', 'creditcard', 'cartao', 'cartão' => 'Cartão',
                        'debit_card', 'debitcard' => 'Cartão (débito)',
                        'boleto' => 'Boleto',
                        '' => '',
                        default => ucfirst($paymentMethod),
                    };
                    $dateLabel = '';
                    if ($orderCreated !== '') {
                        $ts = strtotime($orderCreated);
                        if ($ts !== false) {
                            $dateLabel = date('d/m/Y', $ts);
                        }
                    }
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
                        <?php if ($orderAmount !== null || $methodLabel !== '' || $dateLabel !== ''): ?>
                            <p class="meta">
                                <?php if ($orderAmount !== null): ?>
                                    <strong><?= htmlspecialchars(ProductService::formatPrice($orderAmount, $orderCurrency)) ?></strong>
                                <?php endif; ?>
                                <?php if ($methodLabel !== ''): ?>
                                    · <?= htmlspecialchars($methodLabel) ?>
                                <?php endif; ?>
                                <?php if ($dateLabel !== ''): ?>
                                    · <?= htmlspecialchars($dateLabel) ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                        <div class="actions">
                            <?php if ($kind === 'gateway'): ?>
                                <a class="btn btn-sm" href="/app/install">Instalar / atualizar</a>
                                <a class="btn btn-secondary btn-sm" href="/app/docs">Documentação</a>
                            <?php elseif ($hasZip): ?>
                                <a class="btn btn-sm" href="/app/products/<?= htmlspecialchars($slug) ?>/download">Baixar ZIP</a>
                            <?php else: ?>
                                <span class="muted" style="font-size:13px">ZIP ainda não disponível</span>
                            <?php endif; ?>
                            <?php if ($refundEligible): ?>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-refund"
                                    data-refund-open
                                    data-order-id="<?= $orderRowId ?>"
                                    data-product-name="<?= htmlspecialchars((string) $item['product_name'], ENT_QUOTES, 'UTF-8') ?>"
                                >Solicitar reembolso</button>
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

<div class="refund-modal" id="refund-modal" role="dialog" aria-modal="true" aria-labelledby="refund-modal-title" hidden>
    <div class="panel">
        <h3 id="refund-modal-title">Solicitar reembolso</h3>
        <p class="muted" style="margin:0 0 12px;font-size:13.5px" id="refund-modal-product"></p>
        <form method="post" action="/app/products/refund" id="refund-form">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="order_id" id="refund-order-id" value="">
            <label for="refund-reason">Motivo do reembolso</label>
            <textarea id="refund-reason" name="reason" required minlength="10" maxlength="2000" placeholder="Descreva o motivo (mínimo 10 caracteres)"></textarea>
            <p class="muted" style="font-size:12.5px;margin:8px 0 0">
                Ao confirmar, seu acesso será revogado imediatamente. O valor pode levar até 30 dias para aparecer na fatura do cartão.
            </p>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-refund-close>Cancelar</button>
                <button type="submit" class="btn">Confirmar reembolso</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('refund-modal');
    var orderInput = document.getElementById('refund-order-id');
    var productLabel = document.getElementById('refund-modal-product');
    var reason = document.getElementById('refund-reason');
    if (!modal) return;

    function openModal(orderId, productName) {
        orderInput.value = String(orderId);
        productLabel.textContent = productName || '';
        reason.value = '';
        modal.hidden = false;
        modal.classList.add('open');
        reason.focus();
    }
    function closeModal() {
        modal.classList.remove('open');
        modal.hidden = true;
    }

    document.querySelectorAll('[data-refund-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal(btn.getAttribute('data-order-id'), btn.getAttribute('data-product-name'));
        });
    });
    document.querySelectorAll('[data-refund-close]').forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
    });
})();
</script>
