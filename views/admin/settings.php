<?php
/** @var array<string, string|null> $mail */
/** @var bool $password_set */
/** @var bool $totp_enabled */
/** @var ?array{secret:string,otpauth:string,qr_svg:string} $totp_setup */
/** @var ?string $flash */
/** @var string $csrf */
?>
<?php if (! empty($flash)): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<div class="grid grid-2">
    <div class="card">
        <h1>SMTP</h1>
        <p class="muted">Configuração dos e-mails. Sem host SMTP, só metadados vão para <code>storage/mail.log</code> (sem corpo/tokens).</p>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="save">
            <label>From</label>
            <input type="text" name="mail_from" value="<?= htmlspecialchars((string) ($mail['mail_from'] ?? '')) ?>" placeholder="noreply@seudominio.com">
            <label>Host</label>
            <input type="text" name="mail_host" value="<?= htmlspecialchars((string) ($mail['mail_host'] ?? '')) ?>" placeholder="smtp.seudominio.com">
            <label>Porta</label>
            <input type="text" name="mail_port" value="<?= htmlspecialchars((string) ($mail['mail_port'] ?? '587')) ?>">
            <label>Usuário</label>
            <input type="text" name="mail_username" value="<?= htmlspecialchars((string) ($mail['mail_username'] ?? '')) ?>" autocomplete="off">
            <label>Senha<?= $password_set ? ' (deixe em branco para manter)' : '' ?></label>
            <input type="password" name="mail_password" value="" placeholder="<?= $password_set ? '••••••••' : '' ?>" autocomplete="new-password">
            <label>Criptografia</label>
            <select name="mail_encryption">
                <?php $enc = (string) ($mail['mail_encryption'] ?? 'tls'); ?>
                <option value="tls" <?= $enc === 'tls' ? 'selected' : '' ?>>TLS (STARTTLS)</option>
                <option value="ssl" <?= $enc === 'ssl' ? 'selected' : '' ?>>SSL</option>
                <option value="" <?= $enc === '' ? 'selected' : '' ?>>Nenhuma</option>
            </select>
            <button type="submit">Salvar</button>
        </form>
    </div>
    <div class="card">
        <h2>Enviar e-mail de prova</h2>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="test">
            <label>Para</label>
            <input type="email" name="test_to" required placeholder="voce@email.com">
            <button type="submit">Enviar teste</button>
        </form>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <h2>Segurança — 2FA (app autenticador)</h2>
    <p class="muted">Opcional. Com 2FA ativo, o login admin exige senha + código do Google Authenticator, Authy, etc.</p>
    <?php if (! empty($totp_enabled)): ?>
        <p><span class="badge ok">2FA ativo</span></p>
        <form method="post" class="stack" style="max-width:420px;margin-top:12px">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="totp_disable">
            <label>Senha atual</label>
            <input type="password" name="password" required autocomplete="current-password">
            <label>Código do app</label>
            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required>
            <button type="submit" class="btn btn-danger">Desativar 2FA</button>
        </form>
    <?php elseif (! empty($totp_setup)): ?>
        <div class="grid grid-2" style="margin-top:12px">
            <div>
                <p class="muted">Escaneie o QR no app autenticador:</p>
                <div style="max-width:220px"><?= $totp_setup['qr_svg'] ?></div>
            </div>
            <div>
                <p class="muted">Ou digite a chave manualmente:</p>
                <code class="license-key"><?= htmlspecialchars($totp_setup['secret']) ?></code>
                <p class="muted" style="font-size:12px;margin-top:8px;word-break:break-all"><?= htmlspecialchars($totp_setup['otpauth']) ?></p>
                <form method="post" style="margin-top:12px">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="totp_confirm">
                    <label>Código de confirmação</label>
                    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required>
                    <button type="submit">Confirmar e ativar</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <form method="post" style="margin-top:12px">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="totp_begin">
            <button type="submit">Ativar 2FA</button>
        </form>
    <?php endif; ?>
</div>
