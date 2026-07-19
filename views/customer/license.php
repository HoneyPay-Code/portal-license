<?php
/** @var list<array> $licenses */
/** @var string $csrf */
?>
<div class="card" style="margin-bottom:16px">
    <h1>Licença</h1>
    <p class="muted">1 instalação de produção por chave. Localhost é livre para testes.</p>
</div>
<?php foreach ($licenses as $lic): ?>
<div class="card" style="margin-bottom:16px">
    <div class="stack" style="gap:10px">
        <div class="row" style="justify-content:space-between;align-items:flex-start">
            <h2 style="margin:0;flex:1 1 auto"><?= htmlspecialchars((string) ($lic['product_name'] ?? 'Produto')) ?></h2>
            <span class="badge <?= $lic['status'] === 'active' ? 'ok' : 'bad' ?>"><?= htmlspecialchars((string) $lic['status']) ?></span>
        </div>
        <code class="license-key"><?= htmlspecialchars((string) $lic['license_key']) ?></code>
        <p class="muted" style="margin:0">Domínio de produção: <strong><?= htmlspecialchars((string) ($lic['bound_domain'] ?? 'ainda não vinculado')) ?></strong></p>
        <?php if (! empty($lic['bound_install_id'])): ?>
            <p class="muted mono" style="margin:0">Install ID: <?= htmlspecialchars((string) $lic['bound_install_id']) ?></p>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="clear_localhost">
            <input type="hidden" name="license_id" value="<?= (int) $lic['id'] ?>">
            <button class="btn btn-secondary btn-sm" type="submit">Limpar ativações localhost</button>
        </form>
    </div>
</div>
<?php endforeach; ?>
<?php if ($licenses === []): ?>
<div class="card"><p class="muted">Sem licenças.</p></div>
<?php endif; ?>
