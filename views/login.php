<?php /** @var string $appName */ /** @var ?string $error */ ?>
<div class="wrap" style="max-width:420px; padding-top:10vh;">
    <div class="card">
        <h1><?= htmlspecialchars($appName) ?></h1>
        <p class="muted">Acesse o painel de licenças.</p>
        <?php if (! empty($error)): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <form method="post">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" required autocomplete="username">
            <label for="password">Senha</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">
            <button type="submit">Entrar</button>
        </form>
    </div>
</div>
