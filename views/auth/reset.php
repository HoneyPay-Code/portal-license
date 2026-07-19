<?php /** @var string $csrf */ /** @var string $token */ /** @var ?string $error */ ?>
<div class="auth-wrap">
    <div class="card auth-card">
        <div class="auth-brand">
            <img class="brand-logo" src="/assets/logo.png" alt="<?= htmlspecialchars($appName ?? 'License Portal') ?>" width="200" height="48">
        </div>
        <h1>Definir senha</h1>
        <p class="muted">Crie uma senha com pelo menos 8 caracteres.</p>
        <?php if (! empty($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post" action="/reset-password/<?= htmlspecialchars($token) ?>">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <label for="password">Nova senha</label>
            <input id="password" type="password" name="password" minlength="8" required>
            <button type="submit">Salvar e entrar</button>
        </form>
    </div>
</div>
