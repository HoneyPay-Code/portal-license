<?php
/** @var ?array $currentRelease */
/** @var bool $canDownload */
/** @var string $installCommand */
/** @var string $appUrl */
/** @var ?string $error */
?>
<div class="card" style="margin-bottom:16px">
    <h1>Instalação</h1>
    <p class="muted">Baixe o código-fonte ou instale direto na VPS com Docker. É necessária uma licença ativa.</p>
</div>

<?php if (! empty($error)): ?>
<div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (! $canDownload): ?>
<div class="card">
    <p class="muted">Você precisa de uma licença ativa para baixar o código ou instalar na VPS. Veja a página <a href="/app/license">Licença</a>.</p>
</div>
<?php elseif (! $currentRelease): ?>
<div class="card">
    <p class="muted">Ainda não há um release publicado. Contate o suporte.</p>
</div>
<?php else: ?>
<div class="grid grid-2">
    <div class="card">
        <h2>Baixar código-fonte</h2>
        <p class="muted" style="margin-top:0">Versão atual: <strong class="mono"><?= htmlspecialchars((string) $currentRelease['version']) ?></strong>
            · <?= number_format(((int) $currentRelease['size_bytes']) / 1048576, 1) ?> MB</p>
        <?php if (! empty($currentRelease['notes'])): ?>
            <p class="muted"><?= htmlspecialchars((string) $currentRelease['notes']) ?></p>
        <?php endif; ?>
        <a class="btn" href="/app/install/download">Baixar ZIP</a>
        <?php if (! empty($currentRelease['schema_storage_path'])): ?>
            <a class="btn btn-secondary" href="/app/install/download-schema" style="margin-left:8px">Baixar banco (SQL)</a>
            <p class="muted" style="margin-top:12px;margin-bottom:0;font-size:13px">
                Use o SQL na <strong>hospedagem compartilhada</strong>: importe no phpMyAdmin (banco vazio) antes de concluir o wizard <code>/install</code>.
            </p>
        <?php endif; ?>
    </div>
    <div class="card">
        <h2>Instalar na VPS</h2>
        <p class="muted" style="margin-top:0">Rode na VPS (Ubuntu/Debian). O script pede a chave de licença, valida no portal e sobe Docker <strong>com Caddy</strong> (HTTP + HTTPS).</p>
        <code class="license-key" id="install-cmd"><?= htmlspecialchars($installCommand) ?></code>
        <div class="row" style="margin-top:12px">
            <button type="button" class="btn btn-secondary btn-sm" data-copy="#install-cmd">Copiar comando</button>
        </div>
        <p class="muted" style="margin-top:14px;margin-bottom:0;font-size:13px">
            Opcional: <code>HONEYPAY_LICENSE_KEY=LIC-…</code> para pular o prompt.
        </p>
    </div>
</div>
<?php endif; ?>
