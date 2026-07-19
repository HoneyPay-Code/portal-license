<?php
/** @var array $customer */
/** @var list<array> $licenses */
/** @var list<array> $entitlements */
?>
<div class="card hero-card" style="margin-bottom:16px">
    <h1>Olá, <?= htmlspecialchars((string) $customer['name']) ?></h1>
    <p class="muted">Gerencie sua licença, produtos e aprenda a instalar a plataforma.</p>
</div>
<div class="grid grid-2">
    <div class="card">
        <h2>Suas licenças</h2>
        <?php if ($licenses === []): ?>
            <p class="muted">Nenhuma licença ainda. Assim que a compra for confirmada, ela aparece aqui.</p>
        <?php else: ?>
            <div class="license-list table-mobile">
                <?php foreach ($licenses as $lic): ?>
                    <div class="license-item">
                        <div class="meta">
                            <span class="badge <?= $lic['status'] === 'active' ? 'ok' : 'bad' ?>"><?= htmlspecialchars((string) $lic['status']) ?></span>
                            <span class="muted" style="font-size:13px"><?= htmlspecialchars((string) ($lic['bound_domain'] ?? 'não ativada')) ?></span>
                        </div>
                        <code class="license-key"><?= htmlspecialchars((string) $lic['license_key']) ?></code>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="table-wrap table-desktop">
                <table>
                    <thead><tr><th>Chave</th><th>Status</th><th>Domínio</th></tr></thead>
                    <tbody>
                    <?php foreach ($licenses as $lic): ?>
                        <tr>
                            <td><code><?= htmlspecialchars((string) $lic['license_key']) ?></code></td>
                            <td><span class="badge <?= $lic['status'] === 'active' ? 'ok' : 'bad' ?>"><?= htmlspecialchars((string) $lic['status']) ?></span></td>
                            <td class="muted"><?= htmlspecialchars((string) ($lic['bound_domain'] ?? 'não ativada')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <p style="margin-top:16px"><a class="btn btn-secondary btn-sm" href="/app/license">Ver detalhes</a></p>
    </div>
    <div class="card">
        <h2>Acesso rápido</h2>
        <div class="stack">
            <a class="btn" href="/app/docs">Abrir documentação</a>
            <a class="btn btn-secondary" href="/app/products">Meus produtos (<?= count($entitlements) ?>)</a>
        </div>
    </div>
</div>
