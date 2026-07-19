<?php /** @var string $csrf */ /** @var ?string $flash */ ?>
<div class="auth-wrap">
    <div class="card auth-card">
        <div class="auth-brand">
            <img class="brand-logo" src="/assets/logo.png" alt="<?= htmlspecialchars($appName ?? 'License Portal') ?>" width="200" height="48">
        </div>
        <h1>Recuperar senha</h1>
        <p class="muted">Enviaremos um link se o e-mail estiver cadastrado.</p>
        <?php if (! empty($flash)): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" required>
            <button type="submit">Enviar link</button>
        </form>
        <p class="muted" style="margin-top:16px"><a href="/login">Voltar</a></p>
    </div>
</div>
