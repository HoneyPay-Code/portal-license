<?php
/** @var array|null $product */
/** @var bool $isNew */
/** @var string $csrf */
/** @var string|null $flash */
/** @var string|null $error */
use LicenseApi\ProductService;
$product = $product ?? null;
$isNew = $isNew ?? true;
$isGatewayDefault = $product && (string) ($product['slug'] ?? '') === ProductService::DEFAULT_GATEWAY_SLUG;
?>
<?php if (! empty($flash)): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if (! empty($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:16px">
    <p class="muted" style="margin:0 0 4px"><a href="/admin/products">← Voltar</a></p>
    <h1 style="margin:0"><?= $isNew ? 'Novo produto' : 'Editar produto' ?></h1>
    <p class="muted" style="margin:6px 0 0">
        <?= $isNew
            ? 'Plugins precisam de imagem, preço, checkout e ZIP (liberado após a compra).'
            : 'Atualize os dados. O ZIP do plugin só baixa quem tem entitlement ativo.' ?>
    </p>
</div>

<form method="post" enctype="multipart/form-data" class="card" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="action" value="<?= $isNew ? 'create' : 'update' ?>">
    <?php if (! $isNew): ?>
        <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
    <?php endif; ?>

    <div class="grid grid-2">
        <div>
            <label>Título</label>
            <input name="name" required value="<?= htmlspecialchars((string) ($product['name'] ?? '')) ?>" placeholder="Ex.: Plugin Integração X">

            <label>Slug (URL)</label>
            <input name="slug" value="<?= htmlspecialchars((string) ($product['slug'] ?? '')) ?>"
                   pattern="[a-z0-9\-]+" <?= $isGatewayDefault ? 'readonly' : '' ?>
                   placeholder="ex.: plugin-integracao-x">

            <label>Tipo</label>
            <select name="kind" <?= $isGatewayDefault ? 'disabled' : '' ?>>
                <?php $kind = (string) ($product['kind'] ?? 'plugin'); ?>
                <option value="plugin" <?= $kind === 'plugin' ? 'selected' : '' ?>>Plugin</option>
                <option value="gateway" <?= $kind === 'gateway' ? 'selected' : '' ?>>Gateway</option>
            </select>
            <?php if ($isGatewayDefault): ?>
                <input type="hidden" name="kind" value="gateway">
            <?php endif; ?>

            <label>Preço (R$)</label>
            <input type="number" name="price" step="0.01" min="0"
                   value="<?= isset($product['price']) && $product['price'] !== null ? htmlspecialchars((string) $product['price']) : '' ?>"
                   placeholder="Ex.: 497.00">

            <label>Moeda</label>
            <input name="currency" value="<?= htmlspecialchars((string) ($product['currency'] ?? 'BRL')) ?>">
        </div>
        <div>
            <label>Link do checkout</label>
            <input type="url" name="checkout_url" value="<?= htmlspecialchars((string) ($product['checkout_url'] ?? '')) ?>"
                   placeholder="https://…">

            <label>ID externo do produto (webhook)</label>
            <input name="external_product_id" value="<?= htmlspecialchars((string) ($product['external_product_id'] ?? '')) ?>"
                   placeholder="Opcional — para casar com o checkout">

            <label>ID externo da oferta (webhook)</label>
            <input name="external_offer_id" value="<?= htmlspecialchars((string) ($product['external_offer_id'] ?? '')) ?>"
                   placeholder="Opcional">

            <label>Ordem</label>
            <input type="number" name="sort_order" value="<?= (int) ($product['sort_order'] ?? 100) ?>">

            <label style="display:flex;align-items:center;gap:8px;margin-top:8px">
                <input type="checkbox" name="is_published" value="1" style="width:auto;margin:0"
                    <?= $isNew || ! empty($product['is_published']) ? 'checked' : '' ?>>
                Publicado no catálogo do cliente
            </label>
        </div>
    </div>

    <label>Descrição</label>
    <textarea name="description" rows="5" placeholder="O que o cliente recebe…"><?= htmlspecialchars((string) ($product['description'] ?? '')) ?></textarea>

    <div class="row" style="margin-top:8px">
        <button type="submit"><?= $isNew ? 'Criar produto' : 'Salvar alterações' ?></button>
        <a class="btn btn-secondary" href="/admin/products">Cancelar</a>
    </div>
</form>

<?php if (! $isNew && $product): ?>
    <div class="grid grid-2" style="margin-top:16px">
        <div class="card">
            <h2>Imagem</h2>
            <?php if (! empty($product['image_path'])): ?>
                <p><img src="/admin/products/image/<?= (int) $product['id'] ?>" alt="" style="max-width:100%;border-radius:12px;border:1px solid var(--border)"></p>
            <?php else: ?>
                <p class="muted">Nenhuma imagem ainda.</p>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="upload_image">
                <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                <label>Enviar imagem (JPG/PNG/WEBP, máx. 5 MB)</label>
                <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/*" required>
                <button type="submit">Salvar imagem</button>
            </form>
        </div>

        <div class="card">
            <h2>ZIP do plugin</h2>
            <?php if (($product['kind'] ?? '') === 'gateway'): ?>
                <p class="muted">O gateway usa <a href="/admin/releases">Releases</a> (ZIP oficial do sistema), não este campo.</p>
            <?php else: ?>
                <?php if (! empty($product['plugin_zip_path'])): ?>
                    <p>
                        <span class="badge ok">ZIP enviado</span>
                        <span class="mono muted"><?= htmlspecialchars((string) ($product['plugin_zip_filename'] ?? '')) ?></span>
                    </p>
                    <p class="muted" style="font-size:13px">
                        <?= number_format(((int) ($product['plugin_zip_size'] ?? 0)) / 1048576, 2) ?> MB
                        <?php if (! empty($product['plugin_zip_sha256'])): ?>
                            · sha256 <?= htmlspecialchars(substr((string) $product['plugin_zip_sha256'], 0, 12)) ?>…
                        <?php endif; ?>
                    </p>
                    <form method="post" class="js-confirm-delete" data-confirm="Remover o ZIP deste plugin?" style="margin-bottom:12px">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="clear_zip">
                        <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Remover ZIP</button>
                    </form>
                <?php else: ?>
                    <p class="muted">Nenhum ZIP. Quem comprar não conseguirá baixar até você enviar.</p>
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="upload_zip">
                    <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                    <label>Enviar / substituir ZIP (máx. 512 MB)</label>
                    <input type="file" name="zip" accept=".zip,application/zip" required>
                    <button type="submit">Salvar ZIP</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="margin-top:16px">
        <h2>Webhook deste produto</h2>
        <p class="muted">Configure no checkout externo. Em <code>pedido_pago</code>, libera só este produto.</p>
        <?php
            $appUrl = $appUrl ?? '';
            $revealWebhook = ! empty($revealWebhook);
            $whUrl = ! empty($product['webhook_token'])
                ? rtrim((string) $appUrl, '/').'/webhooks/checkout/'.$product['webhook_token']
                : '';
            $whSecret = (string) ($product['webhook_secret'] ?? '');
            $maskSecret = static function (string $secret): string {
                $len = strlen($secret);
                if ($len <= 8) {
                    return str_repeat('•', max($len, 4));
                }

                return substr($secret, 0, 4).str_repeat('•', max(8, $len - 8)).substr($secret, -4);
            };
        ?>
        <?php if ($whUrl === ''): ?>
            <p class="muted">Credenciais ainda não geradas.</p>
        <?php else: ?>
            <label>URL</label>
            <div class="license-key" id="product-wh-url"><?= htmlspecialchars($whUrl) ?></div>
            <label style="margin-top:12px">Secret</label>
            <div class="license-key" id="product-wh-secret">
                <?= htmlspecialchars($revealWebhook ? $whSecret : $maskSecret($whSecret)) ?>
            </div>
            <p class="muted" style="font-size:13px;margin-top:8px">
                Header: <code>Authorization: Bearer …</code> ou <code>X-Webhook-Secret: …</code>
            </p>
            <div class="row" style="margin-top:8px">
                <button type="button" class="btn btn-secondary btn-sm" id="copy-wh-url">Copiar URL</button>
                <?php if ($revealWebhook): ?>
                    <button type="button" class="btn btn-secondary btn-sm" id="copy-wh-secret">Copiar secret</button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <form method="post" style="margin-top:14px" onsubmit="return confirm('Gerar novas credenciais? O checkout antigo para de funcionar até atualizar.')">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="rotate_webhook">
            <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
            <button type="submit">Gerar / rotacionar autenticação</button>
        </form>
    </div>
<?php endif; ?>

<script>
document.querySelectorAll('form.js-confirm-delete').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        var msg = form.getAttribute('data-confirm') || 'Confirmar?';
        if (!window.confirm(msg)) e.preventDefault();
    });
});
function copyId(id, btn) {
    var el = document.getElementById(id);
    if (!el || !navigator.clipboard) return;
    navigator.clipboard.writeText((el.textContent || '').trim()).then(function () {
        if (!btn) return;
        var old = btn.textContent;
        btn.textContent = 'Copiado';
        setTimeout(function () { btn.textContent = old; }, 1200);
    });
}
var copyUrl = document.getElementById('copy-wh-url');
if (copyUrl) copyUrl.addEventListener('click', function () { copyId('product-wh-url', copyUrl); });
var copySecret = document.getElementById('copy-wh-secret');
if (copySecret) copySecret.addEventListener('click', function () { copyId('product-wh-secret', copySecret); });
</script>
