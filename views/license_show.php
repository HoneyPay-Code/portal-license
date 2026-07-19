<?php
/** @var string $appName */
/** @var ?string $adminEmail */
/** @var array<string,mixed> $license */
/** @var list<array<string,mixed>> $activations */
?>
<div class="wrap">
    <div class="top">
        <div>
            <h1>Licença</h1>
            <p class="muted"><code><?= htmlspecialchars((string) $license['license_key']) ?></code></p>
        </div>
        <div class="row">
            <a class="btn btn-secondary" href="../">Voltar</a>
            <a class="btn btn-secondary" href="../logout">Sair</a>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <p>Status: <span class="badge <?= htmlspecialchars((string) $license['status']) ?>"><?= htmlspecialchars((string) $license['status']) ?></span></p>
        <p class="muted">Máx. ativações: <?= (int) $license['max_activations'] ?></p>
        <?php if (! empty($license['expires_at'])): ?>
            <p class="muted">Expira: <?= htmlspecialchars((string) $license['expires_at']) ?></p>
        <?php endif; ?>
        <?php if (! empty($license['customer_note'])): ?>
            <p><?= htmlspecialchars((string) $license['customer_note']) ?></p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Ativações</h2>
        <table>
            <thead>
            <tr>
                <th>Domínio</th>
                <th>Install ID</th>
                <th>Versão</th>
                <th>Último heartbeat</th>
                <th>IP</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($activations as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $row['domain']) ?></td>
                    <td><code><?= htmlspecialchars((string) $row['install_id']) ?></code></td>
                    <td><?= htmlspecialchars((string) ($row['app_version'] ?? '—')) ?></td>
                    <td><?= htmlspecialchars((string) $row['last_seen_at']) ?></td>
                    <td><?= htmlspecialchars((string) ($row['ip'] ?? '—')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($activations === []): ?>
                <tr><td colspan="5" class="muted">Sem ativações.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
