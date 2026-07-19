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
        <p class="muted">ZIP do gateway + SQL opcional (hospedagem compartilhada) para o cliente baixar.</p>
        <table>
            <thead>
                <tr>
                    <th>Versão</th>
                    <th>Arquivos</th>
                    <th>Atual</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($releases as $rel): ?>
                <?php
                    $hasSchema = ! empty($rel['schema_storage_path']);
                    $schemaKb = $hasSchema ? max(1, (int) round(((int) ($rel['schema_size_bytes'] ?? 0)) / 1024)) : 0;
                ?>
                <tr>
                    <td class="mono"><?= htmlspecialchars((string) $rel['version']) ?></td>
                    <td class="muted" style="max-width:260px">
                        <div title="<?= htmlspecialchars((string) $rel['filename']) ?>">
                            ZIP · <?= number_format(((int) $rel['size_bytes']) / 1048576, 1) ?> MB
                        </div>
                        <?php if ($hasSchema): ?>
                            <div style="margin-top:4px">
                                <span class="badge ok">SQL</span>
                                <?= htmlspecialchars((string) ($rel['schema_filename'] ?? 'database.sql')) ?>
                                · <?= (int) $schemaKb ?> KB
                            </div>
                        <?php else: ?>
                            <div style="margin-top:4px" class="muted">Sem dump SQL</div>
                        <?php endif; ?>
                    </td>
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
                <tr>
                    <td colspan="4" style="padding-top:0">
                        <?php if (! empty($rel['notes'])): ?>
                            <p class="muted" style="margin:0 0 8px"><?= htmlspecialchars((string) $rel['notes']) ?></p>
                        <?php endif; ?>
                        <form method="post" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="attach_schema">
                            <input type="hidden" name="id" value="<?= (int) $rel['id'] ?>">
                            <input type="file" name="schema" accept=".sql,text/plain,application/sql" required style="max-width:220px">
                            <button class="btn btn-secondary btn-sm" type="submit"><?= $hasSchema ? 'Trocar SQL' : 'Anexar SQL' ?></button>
                        </form>
                        <?php if ($hasSchema): ?>
                            <form method="post" style="display:inline;margin-left:8px" onsubmit="return confirm('Remover o dump SQL deste release?')">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="remove_schema">
                                <input type="hidden" name="id" value="<?= (int) $rel['id'] ?>">
                                <button class="btn btn-secondary btn-sm" type="submit">Remover SQL</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($releases === []): ?>
                <tr><td colspan="4" class="muted">Nenhum ZIP enviado ainda.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card">
        <h2>Enviar novo release</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="upload">
            <label for="version">Versão</label>
            <input id="version" name="version" placeholder="2.0.0" required>
            <label for="zip">Arquivo .zip (obrigatório, até 512 MB)</label>
            <input id="zip" type="file" name="zip" accept=".zip,application/zip" required>
            <label for="schema">Dump SQL limpo (opcional, até 64 MB)</label>
            <input id="schema" type="file" name="schema" accept=".sql,text/plain,application/sql">
            <p class="muted" style="font-size:12px;margin-top:-6px">Para hospedagem compartilhada: <code>public/install/database.sql</code> gerado com <code>php artisan getfy:export-shared-schema</code>.</p>
            <label for="notes">Notas (opcional)</label>
            <textarea id="notes" name="notes" rows="3" placeholder="Changelog curto…"></textarea>
            <label><input type="checkbox" name="make_current" value="1" checked> Marcar como release atual</label>
            <button type="submit">Enviar</button>
        </form>
    </div>
</div>
