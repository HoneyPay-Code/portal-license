<?php
/** @var string $appName */
/** @var ?string $adminEmail */
/** @var list<array<string,mixed>> $licenses */
/** @var ?string $flash */
?>
<div class="wrap">
    <div class="top">
        <div>
            <h1><?= htmlspecialchars($appName) ?></h1>
            <p class="muted">Logado como <?= htmlspecialchars((string) $adminEmail) ?></p>
        </div>
        <a class="btn btn-secondary" href="logout">Sair</a>
    </div>

    <?php if (! empty($flash)): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

    <div class="grid-2">
        <div class="card">
            <h2>Licenças</h2>
            <table>
                <thead>
                <tr>
                    <th>Chave</th>
                    <th>Status</th>
                    <th>Ativações</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($licenses as $license): ?>
                    <tr>
                        <td><code><?= htmlspecialchars((string) $license['license_key']) ?></code>
                            <?php if (! empty($license['customer_note'])): ?>
                                <div class="muted"><?= htmlspecialchars((string) $license['customer_note']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= htmlspecialchars((string) $license['status']) ?>"><?= htmlspecialchars((string) $license['status']) ?></span></td>
                        <td><?= (int) ($license['activation_count'] ?? 0) ?> / <?= (int) $license['max_activations'] ?></td>
                        <td class="row">
                            <a href="licenses/<?= (int) $license['id'] ?>">Ver</a>
                            <?php if ($license['status'] === 'active'): ?>
                                <form method="post" action="licenses/<?= (int) $license['id'] ?>/status">
                                    <input type="hidden" name="status" value="blocked">
                                    <button class="btn btn-danger" type="submit">Bloquear</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="licenses/<?= (int) $license['id'] ?>/status">
                                    <input type="hidden" name="status" value="active">
                                    <button class="btn" type="submit">Reativar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($licenses === []): ?>
                    <tr><td colspan="4" class="muted">Nenhuma licença ainda.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Nova licença</h2>
            <form method="post" action="licenses/create">
                <label for="max_activations">Máx. ativações</label>
                <input id="max_activations" type="number" min="1" name="max_activations" value="1" required>
                <label for="expires_at">Expira em (opcional, ISO)</label>
                <input id="expires_at" type="text" name="expires_at" placeholder="2027-12-31T23:59:59Z">
                <label for="customer_note">Nota</label>
                <textarea id="customer_note" name="customer_note" rows="3" placeholder="Cliente / pedido"></textarea>
                <button type="submit">Criar licença</button>
            </form>
        </div>
    </div>
</div>
