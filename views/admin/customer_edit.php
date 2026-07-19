<?php
/** @var array $customer */
/** @var list<array> $licenses */
/** @var ?string $flash */
/** @var string $csrf */
?>
<?php if (! empty($flash)): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<div class="card" style="margin-bottom:16px">
    <p class="muted"><a href="/admin/customers">← Clientes</a></p>
    <h1>Editar cliente</h1>
    <p class="muted">#<?= (int) $customer['id'] ?> · criado em <?= htmlspecialchars((string) $customer['created_at']) ?></p>
</div>
<div class="grid grid-2">
    <div class="card">
        <h2>Dados</h2>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="update">
            <label for="name">Nome</label>
            <input id="name" name="name" required value="<?= htmlspecialchars((string) $customer['name']) ?>">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" required value="<?= htmlspecialchars((string) $customer['email']) ?>">
            <label for="phone">Telefone</label>
            <input id="phone" name="phone" value="<?= htmlspecialchars((string) ($customer['phone'] ?? '')) ?>">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="active" <?= ($customer['status'] ?? '') === 'active' ? 'selected' : '' ?>>active</option>
                <option value="blocked" <?= ($customer['status'] ?? '') === 'blocked' ? 'selected' : '' ?>>blocked</option>
            </select>
            <label for="password">Nova senha</label>
            <input id="password" type="password" name="password" minlength="8" placeholder="deixe em branco para não alterar">
            <p class="muted" style="margin-top:-8px;margin-bottom:14px;font-size:13px">
                Senha atual: <?= empty($customer['password_hash']) ? 'não definida' : 'definida' ?>
            </p>
            <button type="submit">Salvar alterações</button>
        </form>
    </div>
    <div class="stack">
        <div class="card">
            <h2>Senha</h2>
            <div class="row">
                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="send_reset">
                    <button class="btn btn-secondary" type="submit">Enviar link de redefinição</button>
                </form>
                <form method="post" onsubmit="return confirm('Remover a senha deste cliente?')">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="clear_password">
                    <button class="btn btn-danger" type="submit">Remover senha</button>
                </form>
            </div>
        </div>
        <div class="card">
            <h2>Licenças</h2>
            <?php if ($licenses === []): ?>
                <p class="muted">Nenhuma licença vinculada.</p>
            <?php else: ?>
                <ul style="padding-left:18px;margin:0">
                    <?php foreach ($licenses as $lic): ?>
                        <li>
                            <a href="/admin/licenses/<?= (int) $lic['id'] ?>"><code><?= htmlspecialchars((string) $lic['license_key']) ?></code></a>
                            <span class="badge <?= $lic['status'] === 'active' ? 'ok' : 'bad' ?>"><?= htmlspecialchars((string) $lic['status']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
