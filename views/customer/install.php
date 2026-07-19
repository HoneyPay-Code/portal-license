<?php
/** @var ?array $currentRelease */
/** @var bool $canDownload */
/** @var string $installCommand */
/** @var string $updateCommand */
/** @var string $appUrl */
/** @var ?string $error */
?>
<div class="card" style="margin-bottom:16px">
    <h1>Instalação + Update</h1>
    <p class="muted">Instale na VPS ou atualize quando houver release nova. É necessária uma licença ativa.</p>
</div>

<?php if (! empty($error)): ?>
<div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (! $canDownload): ?>
<div class="card">
    <p class="muted">Você precisa de uma licença ativa para baixar, instalar ou atualizar. Veja a página <a href="/app/license">Licença</a>.</p>
</div>
<?php elseif (! $currentRelease): ?>
<div class="card">
    <p class="muted">Ainda não há um release publicado. Contate o suporte.</p>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:16px">
    <p style="margin:0">
        Versão publicada:
        <strong class="mono"><?= htmlspecialchars((string) $currentRelease['version']) ?></strong>
        · <?= number_format(((int) $currentRelease['size_bytes']) / 1048576, 1) ?> MB
    </p>
    <?php if (! empty($currentRelease['notes'])): ?>
        <p class="muted" style="margin:8px 0 0"><?= htmlspecialchars((string) $currentRelease['notes']) ?></p>
    <?php endif; ?>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Instalar na VPS (primeira vez)</h2>
        <p class="muted" style="margin-top:0">Rode na VPS (Ubuntu/Debian). O script pede licença + domínio, valida no portal e sobe Docker <strong>com Caddy</strong>.</p>
        <code class="license-key" id="install-cmd"><?= htmlspecialchars($installCommand) ?></code>
        <div class="row" style="margin-top:12px">
            <button type="button" class="btn btn-secondary btn-sm" data-copy="#install-cmd">Copiar comando</button>
        </div>
        <p class="muted" style="margin-top:12px;margin-bottom:0;font-size:13px">
            Copie a chave <code>LIC-...</code> em <a href="/app/license">Licença</a> antes de colar no terminal.
        </p>
    </div>

    <div class="card">
        <h2>Atualizar VPS (release nova)</h2>
        <p class="muted" style="margin-top:0">
            Já tem o Honey Pay em <code>/opt/getfy</code>? Use este comando.
            Baixa o release oficial, atualiza o código e reinicia a stack — <strong>sem apagar banco nem storage</strong>.
        </p>
        <code class="license-key" id="update-cmd"><?= htmlspecialchars($updateCommand) ?></code>
        <div class="row" style="margin-top:12px">
            <button type="button" class="btn btn-secondary btn-sm" data-copy="#update-cmd">Copiar comando</button>
        </div>
        <p class="muted" style="margin-top:12px;margin-bottom:0;font-size:13px">
            Recomendado: backup do banco antes. O script pede a chave <code>LIC-...</code> (ou usa a gravada na instalação).
        </p>
    </div>
</div>

<div class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <h2>Baixar código-fonte</h2>
        <p class="muted" style="margin-top:0">ZIP da versão <strong class="mono"><?= htmlspecialchars((string) $currentRelease['version']) ?></strong> para hospedagem compartilhada ou revisão local.</p>
        <a class="btn" href="/app/install/download">Baixar ZIP</a>
        <?php if (! empty($currentRelease['schema_storage_path'])): ?>
            <a class="btn btn-secondary" href="/app/install/download-schema" style="margin-left:8px">Baixar banco (SQL)</a>
            <p class="muted" style="margin-top:12px;margin-bottom:0;font-size:13px">
                Shared hosting: importe o SQL no phpMyAdmin (banco vazio) antes do wizard <code>/install</code>.
            </p>
        <?php endif; ?>
    </div>
    <div class="card">
        <h2>Hospedagem compartilhada — update</h2>
        <p class="muted" style="margin-top:0">
            Baixe o ZIP novo, faça backup, substitua os arquivos <strong>exceto</strong>
            <code>.env</code> e pasta <code>storage</code>, depois rode as migrações (quando o painel/admin indicar) ou o fluxo do assistente.
        </p>
        <p class="muted" style="margin-bottom:0;font-size:13px">
            Prefira VPS + comando de update acima quando possível — é o fluxo mais seguro.
        </p>
    </div>
</div>
<?php endif; ?>
