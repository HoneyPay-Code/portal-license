<?php
/** @var list<array> $products */
/** @var string $csrf */
/** @var string|null $flash */
/** @var string|null $error */
use LicenseApi\ProductService;
$products = $products ?? [];
?>
<?php if (! empty($flash)): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if (! empty($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:16px">
    <div class="row" style="justify-content:space-between;align-items:flex-start">
        <div>
            <h1 style="margin:0">Produtos</h1>
            <p class="muted" style="margin:6px 0 0">Gateway e plugins. Após a compra (webhook), o cliente libera o download.</p>
        </div>
        <a class="btn btn-sm" href="/admin/products?new=1">Novo produto</a>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Ordem</th>
                    <th>Produto</th>
                    <th>Tipo</th>
                    <th>Preço</th>
                    <th>ZIP</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $p): ?>
                <?php
                    $kind = (string) ($p['kind'] ?? 'plugin');
                    $price = isset($p['price']) && $p['price'] !== null ? (float) $p['price'] : null;
                ?>
                <tr>
                    <td class="mono"><?= (int) ($p['sort_order'] ?? 0) ?></td>
                    <td>
                        <strong><?= htmlspecialchars((string) $p['name']) ?></strong>
                        <div class="mono muted" style="font-size:12px"><?= htmlspecialchars((string) $p['slug']) ?></div>
                    </td>
                    <td><span class="badge"><?= $kind === 'gateway' ? 'Gateway' : 'Plugin' ?></span></td>
                    <td><?= htmlspecialchars(ProductService::formatPrice($price, isset($p['currency']) ? (string) $p['currency'] : 'BRL')) ?></td>
                    <td class="muted">
                        <?php if ($kind === 'gateway'): ?>
                            Releases
                        <?php elseif (! empty($p['plugin_zip_path'])): ?>
                            <span class="badge ok">Sim</span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (! empty($p['is_published'])): ?>
                            <span class="badge ok">Publicado</span>
                        <?php else: ?>
                            <span class="badge">Oculto</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="row" style="gap:6px;justify-content:flex-end">
                            <a class="btn btn-secondary btn-sm" href="/admin/products?edit=<?= (int) $p['id'] ?>">Editar</a>
                            <?php if ((string) $p['slug'] !== ProductService::DEFAULT_GATEWAY_SLUG): ?>
                                <form method="post" class="js-confirm-delete" style="display:inline;margin:0"
                                      data-confirm="Excluir “<?= htmlspecialchars((string) $p['name'], ENT_QUOTES) ?>”?">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Excluir</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($products === []): ?>
                <tr><td colspan="7" class="muted">Nenhum produto.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.querySelectorAll('form.js-confirm-delete').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        var msg = form.getAttribute('data-confirm') || 'Excluir?';
        if (!window.confirm(msg)) e.preventDefault();
    });
});
</script>
