<?php
/** @var list<array> $hooks */
/** @var list<array> $deliveries */
/** @var ?string $flash */
/** @var string $csrf */
?>
<?php if (! empty($flash)): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<div class="grid grid-2">
    <div class="card">
        <h1>Webhooks de saída</h1>
        <p class="muted">Disparados após <code>pedido_pago</code> / <code>reembolso</code>. Use em n8n, Make ou bot de WhatsApp. Falhas não interrompem o webhook de entrada.</p>
        <table>
            <thead><tr><th>Nome</th><th>URL</th><th>Eventos</th><th>Ativo</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($hooks as $h): ?>
                <?php
                $ev = json_decode((string) ($h['events'] ?? '[]'), true);
                $ev = is_array($ev) ? $ev : [];
                ?>
                <tr>
                    <td colspan="5">
                        <form method="post" style="display:grid;gap:8px;margin-bottom:12px">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
                            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                                <strong><?= htmlspecialchars((string) $h['name']) ?></strong>
                                <span class="badge <?= ! empty($h['enabled']) ? 'ok' : 'bad' ?>"><?= ! empty($h['enabled']) ? 'ativo' : 'off' ?></span>
                                <code class="muted" style="font-size:12px"><?= htmlspecialchars(implode(', ', $ev)) ?></code>
                            </div>
                            <label>Nome</label>
                            <input type="text" name="name" value="<?= htmlspecialchars((string) $h['name']) ?>" required>
                            <label>URL</label>
                            <input type="url" name="url" value="<?= htmlspecialchars((string) $h['url']) ?>" required>
                            <label>Bearer token (deixe em branco para manter)</label>
                            <input type="password" name="bearer_token" value="" placeholder="<?= ! empty($h['bearer_token']) ? '••••••••' : '' ?>" autocomplete="new-password">
                            <label><input type="checkbox" name="event_order_paid" value="1" <?= in_array('order.paid', $ev, true) ? 'checked' : '' ?>> order.paid</label>
                            <label><input type="checkbox" name="event_order_refunded" value="1" <?= in_array('order.refunded', $ev, true) ? 'checked' : '' ?>> order.refunded</label>
                            <label><input type="checkbox" name="enabled" value="1" <?= ! empty($h['enabled']) ? 'checked' : '' ?>> Habilitado</label>
                            <div style="display:flex;gap:8px;flex-wrap:wrap">
                                <button type="submit" name="action" value="update">Salvar</button>
                                <button type="submit" name="action" value="test" class="btn btn-sm">Enviar teste</button>
                                <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm" onclick="return confirm('Remover?')">Excluir</button>
                            </div>
                        </form>
                        <hr>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($hooks === []): ?>
                <tr><td colspan="5" class="muted">Nenhum webhook cadastrado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card">
        <h2>Novo webhook</h2>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="create">
            <label>Nome</label>
            <input type="text" name="name" required placeholder="WhatsApp / n8n">
            <label>URL</label>
            <input type="url" name="url" required placeholder="https://hooks.exemplo.com/...">
            <label>Bearer token (opcional)</label>
            <input type="password" name="bearer_token" autocomplete="new-password">
            <label><input type="checkbox" name="event_order_paid" value="1" checked> order.paid</label>
            <label><input type="checkbox" name="event_order_refunded" value="1"> order.refunded</label>
            <label><input type="checkbox" name="enabled" value="1" checked> Habilitado</label>
            <button type="submit">Criar</button>
        </form>
    </div>
</div>
<div class="card" style="margin-top:16px">
    <h2>Últimas entregas</h2>
    <table>
        <thead><tr><th>ID</th><th>Webhook</th><th>Evento</th><th>HTTP</th><th>OK</th><th>Quando</th></tr></thead>
        <tbody>
        <?php foreach ($deliveries as $d): ?>
            <tr>
                <td><?= (int) $d['id'] ?></td>
                <td><?= htmlspecialchars((string) ($d['webhook_name'] ?? '#')) ?></td>
                <td><?= htmlspecialchars((string) $d['event_name']) ?></td>
                <td class="mono"><?= htmlspecialchars((string) ($d['response_status'] ?? '—')) ?></td>
                <td><span class="badge <?= ! empty($d['ok']) ? 'ok' : 'bad' ?>"><?= ! empty($d['ok']) ? 'sim' : 'não' ?></span></td>
                <td class="muted"><?= htmlspecialchars((string) $d['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($deliveries === []): ?>
            <tr><td colspan="6" class="muted">Sem entregas ainda.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
