<?php
/** @var list<array> $events */
/** @var list<array> $productsList */
/** @var string $appUrl */
/** @var int|null $revealProductId */
/** @var string $csrf */
/** @var string|null $flash */
/** @var string|null $error */
/** @var bool $acceptTest */

$events = $events ?? [];
$productsList = $productsList ?? [];
$revealProductId = $revealProductId ?? null;
$mask = static function (string $secret): string {
    $len = strlen($secret);
    if ($len <= 8) {
        return str_repeat('•', max($len, 4));
    }

    return substr($secret, 0, 4).str_repeat('•', max(8, $len - 8)).substr($secret, -4);
};
?>
<style>
    .wh-box { background:#f8fafc; border:1px solid var(--border); border-radius:12px; padding:12px 14px; margin-bottom:12px; }
    .wh-box code, .wh-box .license-key { font-size:12px; }
    .wh-label { font-size:12px; font-weight:600; color:var(--muted); margin-bottom:4px; display:block; }
    .wh-actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
    .wh-mono { font-family:"IBM Plex Mono", ui-monospace, monospace; word-break:break-all; }
</style>

<?php if (! empty($flash)): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if (! empty($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:16px">
    <h1 style="margin:0">Webhooks de entrada</h1>
    <p class="muted" style="margin:6px 0 0">
        Configure no checkout externo (gateway, Hotmart, etc.). Em <code>pedido_pago</code> o portal cria/libera o cliente e o produto.
        <?php if (! empty($acceptTest)): ?>
            <span class="badge ok">WEBHOOK_ACCEPT_TEST ligado</span>
        <?php else: ?>
            <span class="badge">Payloads com <code>test:true</code> são ignorados</span>
        <?php endif; ?>
    </p>
</div>

<div class="card" style="margin-bottom:16px">
    <h2>Como autenticar</h2>
    <p class="muted">Cada produto tem URL + secret próprios. Envie <strong>um</strong> destes headers (mesmo secret do produto):</p>
    <div class="wh-box">
        <span class="wh-label">Opção A</span>
        <code class="wh-mono">Authorization: Bearer SEU_SECRET</code>
    </div>
    <div class="wh-box">
        <span class="wh-label">Opção B</span>
        <code class="wh-mono">X-Webhook-Secret: SEU_SECRET</code>
    </div>
    <p class="muted" style="margin:0">Eventos: <code>pedido_pago</code> (libera acesso) e <code>reembolso</code> (revoga).</p>
</div>

<div class="card" style="margin-bottom:16px">
    <h2>Webhook por produto</h2>
    <p class="muted">O pagamento libera <strong>somente</strong> aquele produto. Edite também em <a href="/admin/products">Produtos</a>.</p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>URL / secret</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($productsList as $p): ?>
                <?php
                    $purl = ! empty($p['webhook_token'])
                        ? rtrim($appUrl, '/').'/webhooks/checkout/'.$p['webhook_token']
                        : '';
                    $showSecret = $revealProductId !== null && $revealProductId === (int) $p['id'];
                    $uid = 'p'.(int) $p['id'];
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars((string) $p['name']) ?></strong>
                        <div class="muted" style="font-size:12px"><?= htmlspecialchars((string) ($p['kind'] ?? 'plugin')) ?></div>
                    </td>
                    <td style="min-width:220px">
                        <?php if ($purl === ''): ?>
                            <span class="muted">Sem credenciais</span>
                        <?php else: ?>
                            <div class="wh-label">URL</div>
                            <div class="license-key" id="<?= $uid ?>-url" style="margin-bottom:8px"><?= htmlspecialchars($purl) ?></div>
                            <div class="wh-label">Secret</div>
                            <div class="license-key" id="<?= $uid ?>-secret">
                                <?php if ($showSecret && ! empty($p['webhook_secret'])): ?>
                                    <?= htmlspecialchars((string) $p['webhook_secret']) ?>
                                <?php else: ?>
                                    <?= htmlspecialchars($mask((string) ($p['webhook_secret'] ?? ''))) ?>
                                <?php endif; ?>
                            </div>
                            <div class="wh-actions">
                                <button type="button" class="btn btn-secondary btn-sm" data-copy="<?= $uid ?>-url">URL</button>
                                <?php if ($showSecret): ?>
                                    <button type="button" class="btn btn-secondary btn-sm" data-copy="<?= $uid ?>-secret">Secret</button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" style="margin:0" onsubmit="return confirm('Gerar novas credenciais deste produto?')">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="rotate_product">
                            <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">Gerar</button>
                        </form>
                        <a class="btn btn-secondary btn-sm" style="margin-top:6px;display:inline-block" href="/admin/products?edit=<?= (int) $p['id'] ?>">Editar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($productsList === []): ?>
                <tr><td colspan="3" class="muted">Nenhum produto. Crie em Produtos.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>Últimos eventos</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Evento</th><th>Order</th><th>Resultado</th><th>Quando</th></tr></thead>
            <tbody>
            <?php foreach ($events as $e): ?>
                <tr>
                    <td><?= (int) $e['id'] ?></td>
                    <td><?= htmlspecialchars((string) $e['event_name']) ?></td>
                    <td class="mono"><?= htmlspecialchars((string) ($e['external_order_id'] ?? '—')) ?></td>
                    <td class="muted"><?= htmlspecialchars((string) ($e['process_result'] ?? '—')) ?></td>
                    <td class="muted"><?= htmlspecialchars((string) $e['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($events === []): ?>
                <tr><td colspan="5" class="muted">Nenhum evento ainda.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var el = document.getElementById(btn.getAttribute('data-copy'));
        if (!el) return;
        var text = el.textContent || '';
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text.trim()).then(function () {
                var old = btn.textContent;
                btn.textContent = 'Copiado';
                setTimeout(function () { btn.textContent = old; }, 1200);
            });
        }
    });
});
</script>
