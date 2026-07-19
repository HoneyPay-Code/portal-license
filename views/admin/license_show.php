<?php
/** @var array $license */
/** @var list<array> $activations */
/** @var string $csrf */
/** @var ?string $flash */
/** @var ?string $error */
?>
<?php if (! empty($flash)): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if (! empty($error)): ?><div class="flash" style="background:#fef2f2;color:#991b1b;border-color:#fecaca"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:16px">
    <p class="muted"><a href="/admin">← Voltar</a></p>
    <h1><code><?= htmlspecialchars((string) $license['license_key']) ?></code></h1>
    <p>Status: <span class="badge <?= $license['status'] === 'active' ? 'ok' : 'bad' ?>"><?= htmlspecialchars((string) $license['status']) ?></span></p>
    <p class="muted">Cliente: <?= htmlspecialchars((string) ($license['customer_email'] ?? '—')) ?></p>
    <p class="muted" style="margin-bottom:0">
        Se o domínio ficou preso no IP (setup errado), altere aqui para o hostname real.
        Depois peça ao cliente para atualizar <code>APP_URL</code> e clicar em Verificar no gateway.
    </p>
</div>

<div class="card">
    <h2>Ativações</h2>
    <?php if ($activations === []): ?>
        <p class="muted">Sem ativações.</p>
    <?php else: ?>
        <?php foreach ($activations as $row): ?>
            <div style="border:1px solid var(--border, #e4e4e7); border-radius:10px; padding:14px; margin-bottom:12px">
                <div class="grid grid-2" style="gap:12px; margin-bottom:10px">
                    <div>
                        <div class="muted" style="font-size:12px">Install ID</div>
                        <code class="mono"><?= htmlspecialchars((string) $row['install_id']) ?></code>
                    </div>
                    <div>
                        <div class="muted" style="font-size:12px">Local / provisional</div>
                        <?= ! empty($row['is_localhost']) ? 'sim' : 'não' ?>
                        · último: <?= htmlspecialchars((string) $row['last_seen_at']) ?>
                        · IP: <?= htmlspecialchars((string) ($row['ip'] ?? '—')) ?>
                    </div>
                </div>
                <form method="post" style="display:flex; flex-wrap:wrap; gap:8px; align-items:end">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="set_activation_domain">
                    <input type="hidden" name="activation_id" value="<?= (int) $row['id'] ?>">
                    <div style="flex:1; min-width:220px">
                        <label style="display:block; font-size:12px; margin-bottom:4px">Domínio</label>
                        <input
                            type="text"
                            name="domain"
                            value="<?= htmlspecialchars((string) $row['domain']) ?>"
                            required
                            placeholder="loja.seudominio.com"
                            style="width:100%"
                        >
                    </div>
                    <button class="btn btn-sm" type="submit">Salvar domínio</button>
                </form>
                <form method="post" style="margin-top:8px" onsubmit="return confirm('Remover esta ativação? O cliente precisará ativar de novo.');">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="delete_activation">
                    <input type="hidden" name="activation_id" value="<?= (int) $row['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit">Remover ativação</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
