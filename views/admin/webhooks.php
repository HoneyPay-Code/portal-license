<?php /** @var list<array> $events */ ?>
<div class="card">
    <h1>Webhooks</h1>
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
        </tbody>
    </table>
</div>
