<?php
/** @var array{id:int,email:string} $admin */
/** @var list<array{id:int,email:string,totp_enabled:int,created_at:string}> $admins */
/** @var string $csrf */
/** @var string|null $flash */
/** @var string|null $error */
$admin = $admin ?? ['id' => 0, 'email' => ''];
$admins = $admins ?? [];
?>
<?php if (! empty($flash)): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if (! empty($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:16px">
    <h1 style="margin:0">Minha conta (admin)</h1>
    <p class="muted" style="margin:6px 0 0">Altere e-mail e senha com segurança, e gerencie outros administradores.</p>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>E-mail de login</h2>
        <p class="muted" style="margin-top:0">Atual: <strong><?= htmlspecialchars((string) $admin['email']) ?></strong></p>
        <form method="post" autocomplete="off">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="update_email">
            <label>Novo e-mail</label>
            <input type="email" name="email" required value="<?= htmlspecialchars((string) $admin['email']) ?>" autocomplete="username">
            <label>Senha atual (confirmação)</label>
            <input type="password" name="current_password" required autocomplete="current-password">
            <button type="submit">Salvar e-mail</button>
        </form>
    </div>

    <div class="card">
        <h2>Alterar senha</h2>
        <p class="muted" style="margin-top:0">Mínimo 10 caracteres, com letras e números.</p>
        <form method="post" autocomplete="off">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="update_password">
            <label>Senha atual</label>
            <input type="password" name="current_password" required autocomplete="current-password">
            <label>Nova senha</label>
            <input type="password" name="new_password" required minlength="10" autocomplete="new-password">
            <label>Confirmar nova senha</label>
            <input type="password" name="new_password_confirmation" required minlength="10" autocomplete="new-password">
            <button type="submit">Salvar senha</button>
        </form>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <h2>Administradores</h2>
    <p class="muted">Quem pode entrar em <code>/admin/login</code>. Cada um deve ativar 2FA em Configurações.</p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>E-mail</th>
                    <th>2FA</th>
                    <th>Criado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($admins as $row): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($row['email']) ?>
                        <?php if ((int) $row['id'] === (int) $admin['id']): ?>
                            <span class="badge">Você</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (! empty($row['totp_enabled'])): ?>
                            <span class="badge ok">Ativo</span>
                        <?php else: ?>
                            <span class="badge">Off</span>
                        <?php endif; ?>
                    </td>
                    <td class="muted" style="font-size:13px"><?= htmlspecialchars($row['created_at'] !== '' ? substr($row['created_at'], 0, 10) : '—') ?></td>
                    <td>
                        <?php if ((int) $row['id'] !== (int) $admin['id']): ?>
                            <form method="post" class="js-confirm-delete" style="display:inline;margin:0"
                                  data-confirm="Remover o admin “<?= htmlspecialchars($row['email'], ENT_QUOTES) ?>”?">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="delete_admin">
                                <input type="hidden" name="admin_id" value="<?= (int) $row['id'] ?>">
                                <label style="display:inline;margin:0;font-size:12px;color:var(--muted)">Sua senha</label>
                                <input type="password" name="current_password" required autocomplete="current-password"
                                       style="width:140px;display:inline-block;margin:0 6px;padding:8px 10px;min-height:36px;font-size:13px">
                                <button type="submit" class="btn btn-danger btn-sm">Excluir</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="margin-top:16px;max-width:520px">
    <h2>Criar novo admin</h2>
    <p class="muted">Use um e-mail real da equipe. Confirme com a sua senha atual.</p>
    <form method="post" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="create_admin">
        <label>E-mail do novo admin</label>
        <input type="email" name="email" required autocomplete="off">
        <label>Senha inicial</label>
        <input type="password" name="password" required minlength="10" autocomplete="new-password">
        <label>Confirmar senha inicial</label>
        <input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password">
        <label>Sua senha atual (confirmação)</label>
        <input type="password" name="current_password" required autocomplete="current-password">
        <button type="submit">Criar admin</button>
    </form>
</div>

<p class="muted" style="margin-top:16px">2FA (app autenticador): <a href="/admin/settings">Configurações → Segurança</a></p>

<script>
document.querySelectorAll('form.js-confirm-delete').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        var msg = form.getAttribute('data-confirm') || 'Tem certeza?';
        if (!window.confirm(msg)) {
            e.preventDefault();
        }
    });
});
</script>
