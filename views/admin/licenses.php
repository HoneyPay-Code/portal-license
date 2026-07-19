<?php
/** @var list<array> $licenses */
/** @var ?string $flash */
/** @var string $csrf */
?>
<?php if (! empty($flash)): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<div class="card" style="margin-bottom:16px">
    <h2 style="margin-top:0">Como funciona a licença</h2>
    <p class="muted" style="margin-bottom:0">
        O <strong>cliente</strong> e a <strong>chave</strong> nascem no pedido pago (ou no admin).
        O <strong>domínio de produção</strong> não é digitado pelo cliente: é vinculado automaticamente na
        <strong>primeira ativação real</strong> quando o gateway envia o host de <code>APP_URL</code> + <code>install_id</code>.
        Localhost / <code>*.local</code> / <code>*.test</code> não consomem o slot. Uma chave = um domínio de produção.
    </p>
</div>
<div class="grid grid-2">
    <div class="card">
        <h1>Licenças</h1>
        <table>
            <thead><tr><th>Chave</th><th>Cliente</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($licenses as $license): ?>
                <tr>
                    <td><code><a href="/admin/licenses/<?= (int) $license['id'] ?>"><?= htmlspecialchars((string) $license['license_key']) ?></a></code></td>
                    <td class="muted"><?= htmlspecialchars((string) ($license['customer_email'] ?? '—')) ?></td>
                    <td><span class="badge <?= $license['status'] === 'active' ? 'ok' : 'bad' ?>"><?= htmlspecialchars((string) $license['status']) ?></span></td>
                    <td>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="set_status">
                            <input type="hidden" name="id" value="<?= (int) $license['id'] ?>">
                            <?php if ($license['status'] === 'active'): ?>
                                <input type="hidden" name="status" value="blocked">
                                <button class="btn btn-danger btn-sm" type="submit">Bloquear</button>
                            <?php else: ?>
                                <input type="hidden" name="status" value="active">
                                <button class="btn btn-sm" type="submit">Reativar</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card">
        <h2>Nova licença manual</h2>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="create_license">
            <label>Máx. ativações</label>
            <input type="number" name="max_activations" value="1" min="1">
            <label>Expira (ISO opcional)</label>
            <input type="text" name="expires_at" placeholder="2027-12-31T23:59:59Z">
            <label>Nota</label>
            <textarea name="customer_note" rows="3"></textarea>
            <button type="submit">Criar</button>
        </form>
    </div>
</div>
