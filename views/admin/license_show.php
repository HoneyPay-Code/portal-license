<?php /** @var array $license */ /** @var list<array> $activations */ ?>
<div class="card" style="margin-bottom:16px">
    <p class="muted"><a href="/admin">← Voltar</a></p>
    <h1><code><?= htmlspecialchars((string) $license['license_key']) ?></code></h1>
    <p>Status: <span class="badge <?= $license['status'] === 'active' ? 'ok' : 'bad' ?>"><?= htmlspecialchars((string) $license['status']) ?></span></p>
    <p class="muted">Cliente: <?= htmlspecialchars((string) ($license['customer_email'] ?? '—')) ?></p>
</div>
<div class="card">
    <h2>Ativações</h2>
    <table>
        <thead><tr><th>Domínio</th><th>Install</th><th>Local</th><th>Último</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($activations as $row): ?>
            <tr>
                <td><?= htmlspecialchars((string) $row['domain']) ?></td>
                <td class="mono"><?= htmlspecialchars((string) $row['install_id']) ?></td>
                <td><?= ! empty($row['is_localhost']) ? 'sim' : 'não' ?></td>
                <td class="muted"><?= htmlspecialchars((string) $row['last_seen_at']) ?></td>
                <td class="muted"><?= htmlspecialchars((string) ($row['ip'] ?? '—')) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($activations === []): ?><tr><td colspan="5" class="muted">Sem ativações</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
