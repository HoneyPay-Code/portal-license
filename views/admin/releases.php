<?php
/** @var list<array> $releases */
/** @var string $csrf */
/** @var ?string $flash */
/** @var ?string $error */
?>
<?php if (! empty($flash)): ?>
<div class="flash"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
<div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <h1>Releases</h1>
        <p class="muted">ZIP do gateway disponível para download do cliente e instalação VPS.</p>
        <table>
            <thead>
                <tr>
                    <th>Versão</th>
                    <th>Arquivo</th>
                    <th>Tamanho</th>
                    <th>Atual</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($releases as $rel): ?>
                <tr>
                    <td class="mono"><?= htmlspecialchars((string) $rel['version']) ?></td>
                    <td class="muted" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars((string) $rel['filename']) ?>">
                        <?= htmlspecialchars((string) $rel['filename']) ?>
                    </td>
                    <td class="muted"><?= number_format(((int) $rel['size_bytes']) / 1048576, 1) ?> MB</td>
                    <td>
                        <?php if (! empty($rel['is_current'])): ?>
                            <span class="badge ok">atual</span>
                        <?php else: ?>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="set_current">
                                <input type="hidden" name="id" value="<?= (int) $rel['id'] ?>">
                                <button class="btn btn-secondary btn-sm" type="submit">Tornar atual</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" style="display:inline" onsubmit="return confirm('Excluir este release?')">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $rel['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Excluir</button>
                        </form>
                    </td>
                </tr>
                <?php if (! empty($rel['notes'])): ?>
                <tr>
                    <td colspan="5" class="muted" style="padding-top:0"><?= htmlspecialchars((string) $rel['notes']) ?></td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($releases === []): ?>
                <tr><td colspan="5" class="muted">Nenhum ZIP enviado ainda.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card">
        <h2>Enviar novo ZIP</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="upload">
            <label for="version">Versão</label>
            <input id="version" name="version" placeholder="2.0.0" required>
            <label for="zip">Arquivo .zip</label>
            <input id="zip" type="file" name="zip" accept=".zip,application/zip" required>
            <label for="notes">Notas (opcional)</label>
            <textarea id="notes" name="notes" rows="3" placeholder="Changelog curto…"></textarea>
            <label><input type="checkbox" name="make_current" value="1" checked> Marcar como release atual</label>
            <button type="submit">Enviar</button>
        </form>
    </div>
</div>
