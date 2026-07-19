<?php /** @var string $appName */ /** @var ?string $error */ /** @var string $csrf */ ?>
<div class="auth-wrap">
    <div class="card auth-card">
        <div class="auth-brand">
            <img class="brand-logo" src="/assets/logo.png" alt="<?= htmlspecialchars($appName ?? 'License Portal') ?>" width="200" height="48">
        </div>
        <h1>Verificação em 2 etapas</h1>
        <p class="muted">Abra o app autenticador e informe o código de 6 dígitos.</p>
        <?php if (! empty($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <label for="code">Código</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="8" required autofocus>
            <button type="submit">Continuar</button>
        </form>
        <p class="muted" style="margin-top:16px"><a href="/admin/login">← Voltar ao login</a></p>
    </div>
</div>
