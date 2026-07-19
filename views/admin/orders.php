<?php /** @var list<array> $orders */ ?>
<div class="card">
    <h1>Pedidos</h1>
    <table>
        <thead><tr><th>Order</th><th>Cliente</th><th>Produto</th><th>Status</th><th>Valor</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td class="mono"><?= htmlspecialchars((string) $o['external_order_id']) ?></td>
                <td><?= htmlspecialchars((string) ($o['customer_email'] ?? '—')) ?></td>
                <td><?= htmlspecialchars((string) ($o['product_name'] ?? '—')) ?></td>
                <td><span class="badge <?= $o['status'] === 'completed' ? 'ok' : 'bad' ?>"><?= htmlspecialchars((string) $o['status']) ?></span></td>
                <td class="muted"><?= htmlspecialchars((string) ($o['amount'] ?? '—')) ?> <?= htmlspecialchars((string) ($o['currency'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
