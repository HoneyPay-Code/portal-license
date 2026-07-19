<?php /** @var string $appName */ /** @var ?string $error */ /** @var string $csrf */ ?>
<div class="auth-wrap">
    <div class="card auth-card">
        <div class="auth-brand">
            <img class="brand-logo" src="/assets/logo.png" alt="<?= htmlspecialchars($appName ?? 'License Portal') ?>" width="200" height="48">
        </div>
        <h1>Admin</h1>
        <p class="muted">Painel interno do portal de licenças.</p>
        <?php if (! empty($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" required>
            <label for="password">Senha</label>
            <input id="password" type="password" name="password" required>
            <button type="submit">Entrar</button>
        </form>
    </div>
</div>
