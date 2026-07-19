<?php
/** @var list<array> $refunds */
/** @var string $csrf */
/** @var string|null $flash */
/** @var string|null $error */
use LicenseApi\ProductService;

$refunds = $refunds ?? [];
$csrf = $csrf ?? '';
?>
<?php if (! empty($flash)): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if (! empty($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
    <h1>Reembolsos</h1>
    <p class="muted" style="margin-top:0">Fila de solicitações do cliente. Faça o estorno manualmente no gateway e marque como concluído.</p>
    <?php if ($refunds === []): ?>
        <p class="muted">Nenhuma solicitação ainda.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Cliente</th>
                    <th>Produto</th>
                    <th>Pedido</th>
                    <th>Valor</th>
                    <th>Método</th>
                    <th>Gateway TX</th>
                    <th>Motivo</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($refunds as $r): ?>
                <?php
                    $status = (string) ($r['status'] ?? 'pending');
                    $created = (string) ($r['created_at'] ?? '');
                    $dateLabel = $created;
                    $ts = strtotime($created);
                    if ($ts !== false) {
                        $dateLabel = date('d/m/Y H:i', $ts);
                    }
                    $amount = isset($r['amount']) && $r['amount'] !== null ? (float) $r['amount'] : null;
                    $currency = (string) ($r['currency'] ?? 'BRL');
                    $method = (string) ($r['payment_method'] ?? '—');
                ?>
                <tr>
                    <td class="muted"><?= htmlspecialchars($dateLabel) ?></td>
                    <td>
                        <?= htmlspecialchars((string) ($r['customer_name'] ?? '—')) ?><br>
                        <span class="muted" style="font-size:12px"><?= htmlspecialchars((string) ($r['customer_email'] ?? '')) ?></span>
                    </td>
                    <td><?= htmlspecialchars((string) ($r['product_name'] ?? '—')) ?></td>
                    <td class="mono"><?= htmlspecialchars((string) ($r['external_order_id'] ?? '—')) ?></td>
                    <td><?= $amount !== null ? htmlspecialchars(ProductService::formatPrice($amount, $currency)) : '—' ?></td>
                    <td><?= htmlspecialchars($method) ?></td>
                    <td class="mono muted" style="font-size:12px"><?= htmlspecialchars((string) ($r['gateway_transaction_id'] ?? '—')) ?></td>
                    <td style="max-width:220px;white-space:pre-wrap;font-size:13px"><?= htmlspecialchars((string) ($r['reason'] ?? '')) ?></td>
                    <td>
                        <span class="badge <?= $status === 'completed' ? 'ok' : 'bad' ?>">
                            <?= $status === 'completed' ? 'concluído' : 'pendente' ?>
                        </span>
                        <?php if (! empty($r['admin_notes'])): ?>
                            <div class="muted" style="font-size:12px;margin-top:4px"><?= htmlspecialchars((string) $r['admin_notes']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($status === 'pending'): ?>
                            <form method="post" style="display:flex;flex-direction:column;gap:6px;min-width:160px">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="complete">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <input type="text" name="admin_notes" placeholder="Notas (opcional)" style="margin:0;font-size:13px">
                                <button type="submit" class="btn btn-sm">Marcar como reembolsado</button>
                            </form>
                        <?php else: ?>
                            <span class="muted" style="font-size:12px">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
