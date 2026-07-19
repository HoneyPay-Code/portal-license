<?php
/** @var array $customer */
/** @var string $csrf */
/** @var string|null $flash */
/** @var string|null $error */
$customer = $customer ?? [];
$hasPassword = ! empty($customer['password_hash']);
?>
<?php if (! empty($flash)): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if (! empty($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:16px">
    <h1 style="margin:0">Minha conta</h1>
    <p class="muted" style="margin:6px 0 0">Atualize seus dados e a senha do portal.</p>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Dados do perfil</h2>
        <p class="muted" style="margin-top:0">E-mail de login: <strong><?= htmlspecialchars((string) ($customer['email'] ?? '')) ?></strong></p>
        <p class="muted" style="font-size:13px;margin-top:-6px">O e-mail não pode ser alterado por aqui. Se precisar trocar, fale com o suporte.</p>
        <form method="post" autocomplete="off">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="update_profile">
            <label>Nome</label>
            <input type="text" name="name" required value="<?= htmlspecialchars((string) ($customer['name'] ?? '')) ?>" autocomplete="name">
            <label>Telefone (opcional)</label>
            <input type="text" name="phone" value="<?= htmlspecialchars((string) ($customer['phone'] ?? '')) ?>" autocomplete="tel">
            <button type="submit">Salvar perfil</button>
        </form>
    </div>

    <div class="card">
        <h2>Alterar senha</h2>
        <?php if (! $hasPassword): ?>
            <p class="muted">Sua conta ainda não tem senha. Use <a href="/forgot-password">Esqueci a senha</a> para criar a primeira.</p>
        <?php else: ?>
            <p class="muted" style="margin-top:0">Mínimo 8 caracteres.</p>
            <form method="post" autocomplete="off">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="update_password">
                <label>Senha atual</label>
                <input type="password" name="current_password" required autocomplete="current-password">
                <label>Nova senha</label>
                <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
                <label>Confirmar nova senha</label>
                <input type="password" name="new_password_confirmation" required minlength="8" autocomplete="new-password">
                <button type="submit">Salvar senha</button>
            </form>
        <?php endif; ?>
    </div>
</div>
