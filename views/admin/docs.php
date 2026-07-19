<?php
/** @var list<array> $sections */
/** @var list<array> $lessons */
/** @var array|null $editLesson */
/** @var array|null $editSection */
/** @var string $csrf */
/** @var string|null $flash */
/** @var string|null $error */
$editLesson = $editLesson ?? null;
$editSection = $editSection ?? null;
$mode = 'list';
if ($editLesson !== null || (isset($_GET['new']) && $_GET['new'] === 'lesson')) {
    $mode = 'lesson';
} elseif ($editSection !== null || (isset($_GET['new']) && $_GET['new'] === 'section')) {
    $mode = 'section';
}
$isNewLesson = $mode === 'lesson' && $editLesson === null;
$isNewSection = $mode === 'section' && $editSection === null;
?>
<style>
    .docs-admin-top {
        display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-start;
        justify-content: space-between; margin-bottom: 16px;
    }
    .docs-admin-top h1 { margin: 0; }
    .docs-admin-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .docs-admin-meta {
        display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px;
    }
    .docs-admin-meta .field-full { grid-column: 1 / -1; }
    .docs-admin-checks {
        display: flex; flex-wrap: wrap; gap: 16px 24px; align-items: center;
        padding: 12px 14px; background: #f8fafc; border: 1px solid var(--border);
        border-radius: 12px; margin-bottom: 14px;
    }
    .docs-admin-checks label {
        display: inline-flex; align-items: center; gap: 8px; margin: 0;
        color: var(--text); font-size: 14px; font-weight: 500; cursor: pointer;
    }
    .docs-admin-checks input { width: auto; margin: 0; }
    .docs-admin-editor {
        display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 14px;
        min-height: 520px;
    }
    .docs-admin-pane {
        display: flex; flex-direction: column; min-width: 0;
        border: 1px solid var(--border); border-radius: 14px; background: #fff;
        overflow: hidden;
    }
    .docs-admin-pane-head {
        display: flex; align-items: center; justify-content: space-between; gap: 8px;
        padding: 10px 14px; border-bottom: 1px solid var(--border);
        background: #f8fafc; font-size: 13px; font-weight: 600; color: var(--muted);
    }
    .docs-admin-pane textarea {
        flex: 1; min-height: 480px; margin: 0; border: 0; border-radius: 0;
        resize: vertical; font-family: "IBM Plex Mono", ui-monospace, monospace;
        font-size: 13px; line-height: 1.55; padding: 14px;
    }
    .docs-admin-preview {
        flex: 1; min-height: 480px; overflow: auto; padding: 16px 18px;
        background: #fafbfc; line-height: 1.65;
    }
    .docs-admin-preview:empty::before {
        content: "Clique em Atualizar preview ou digite no Markdown.";
        color: var(--muted); font-size: 14px;
    }
    .docs-admin-toolbar {
        position: sticky; bottom: 12px; z-index: 20;
        display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
        padding: 12px 14px; margin-top: 16px;
        background: rgba(255,255,255,.94); backdrop-filter: blur(8px);
        border: 1px solid var(--border); border-radius: 14px; box-shadow: var(--shadow);
    }
    .docs-admin-toolbar .spacer { flex: 1; }
    .docs-admin-hint {
        font-size: 12px; color: var(--muted); margin: 0 0 12px; line-height: 1.45;
    }
    .docs-admin-list-head {
        display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
        justify-content: space-between; margin-bottom: 10px;
    }
    .docs-admin-list-head h2 { margin: 0; }
    .docs-admin-filter {
        max-width: 280px; margin: 0 0 12px !important;
    }
    .docs-admin-section-block { margin-bottom: 18px; }
    .docs-admin-section-block > .title-row {
        display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
        justify-content: space-between; margin-bottom: 8px;
        padding-bottom: 8px; border-bottom: 1px solid var(--border);
    }
    .docs-admin-section-block .title-row h3 {
        margin: 0; font-size: 15px; letter-spacing: -.01em;
    }
    .docs-admin-page-row {
        display: grid;
        grid-template-columns: 48px minmax(0, 1.4fr) minmax(0, .9fr) 100px auto;
        gap: 10px; align-items: center;
        padding: 10px 8px; border-bottom: 1px solid var(--border);
        font-size: 14px;
    }
    .docs-admin-page-row:last-child { border-bottom: 0; }
    .docs-admin-page-row .mono { font-size: 12px; color: var(--muted); }
    .docs-admin-page-row .row-actions { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-end; }
    .docs-admin-empty { padding: 20px; text-align: center; color: var(--muted); }
    @media (max-width: 960px) {
        .docs-admin-editor { grid-template-columns: 1fr; }
        .docs-admin-meta { grid-template-columns: 1fr; }
        .docs-admin-page-row {
            grid-template-columns: 1fr;
            gap: 6px;
        }
        .docs-admin-page-row .row-actions { justify-content: flex-start; }
    }
</style>

<?php if (! empty($flash)): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if (! empty($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($mode === 'lesson'): ?>
    <?php
    $lessonTitle = (string) ($editLesson['title'] ?? '');
    $lessonSlug = (string) ($editLesson['slug'] ?? '');
    ?>
    <div class="docs-admin-top">
        <div>
            <p class="muted" style="margin:0 0 4px"><a href="/admin/docs">← Voltar para a lista</a></p>
            <h1><?= $isNewLesson ? 'Nova página' : 'Editar página' ?></h1>
            <?php if (! $isNewLesson): ?>
                <p class="muted" style="margin:6px 0 0"><?= htmlspecialchars($lessonTitle) ?> · <span class="mono"><?= htmlspecialchars($lessonSlug) ?></span></p>
            <?php else: ?>
                <p class="muted" style="margin:6px 0 0">Preencha os dados e o conteúdo em Markdown.</p>
            <?php endif; ?>
        </div>
        <div class="docs-admin-actions">
            <?php if (! $isNewLesson && ! empty($editLesson['published'])): ?>
                <a class="btn btn-secondary btn-sm" href="/app/docs/<?= htmlspecialchars($lessonSlug) ?>" target="_blank" rel="noopener">Ver no portal</a>
            <?php endif; ?>
            <a class="btn btn-secondary btn-sm" href="/admin/docs">Cancelar</a>
        </div>
    </div>

    <form method="post" id="lesson-form" class="card">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="save_lesson">
        <?php if ($editLesson): ?>
            <input type="hidden" name="id" value="<?= (int) $editLesson['id'] ?>">
        <?php endif; ?>

        <div class="docs-admin-meta">
            <div>
                <label>Título</label>
                <input name="title" id="lesson-title" required value="<?= htmlspecialchars($lessonTitle) ?>" placeholder="Ex.: Entrar no portal">
            </div>
            <div>
                <label>Slug (URL)</label>
                <input name="slug" id="lesson-slug" required value="<?= htmlspecialchars($lessonSlug) ?>" pattern="[a-z0-9\-]+" placeholder="ex.: entrar-portal">
            </div>
            <div>
                <label>Ordem na seção</label>
                <input type="number" name="sort_order" value="<?= (int) ($editLesson['sort_order'] ?? 0) ?>">
            </div>
            <div class="field-full">
                <label>Seção</label>
                <select name="section_id">
                    <option value="">— Sem seção —</option>
                    <?php foreach ($sections as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= ((int) ($editLesson['section_id'] ?? 0) === (int) $s['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $s['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="docs-admin-checks">
            <label><input type="checkbox" name="published" value="1" <?= ! isset($editLesson) || ! empty($editLesson['published']) ? 'checked' : '' ?>> Publicada (visível no guia)</label>
            <label><input type="checkbox" name="docs_public" value="1" <?= ! empty($editLesson['docs_public']) ? 'checked' : '' ?>> Pública sem compra</label>
        </div>

        <p class="docs-admin-hint">
            Markdown: <code># Título</code>, <code>## Subtítulo</code>, listas, tabelas, <code>```código```</code>,
            callouts com <code>&gt; **Dica:**</code> ou <code>&gt; **Atenção:**</code>.
        </p>

        <div class="docs-admin-editor">
            <div class="docs-admin-pane">
                <div class="docs-admin-pane-head">
                    <span>Markdown</span>
                    <button type="button" class="btn btn-secondary btn-sm" id="preview-btn">Atualizar preview</button>
                </div>
                <textarea name="body_markdown" id="body_markdown" required placeholder="# Título da página&#10;&#10;Escreva o conteúdo aqui…"><?= htmlspecialchars((string) ($editLesson['body_markdown'] ?? '')) ?></textarea>
            </div>
            <div class="docs-admin-pane">
                <div class="docs-admin-pane-head"><span>Preview</span></div>
                <div id="preview-panel" class="docs docs-admin-preview"></div>
            </div>
        </div>

        <div class="docs-admin-toolbar">
            <button type="submit"><?= $isNewLesson ? 'Criar página' : 'Salvar alterações' ?></button>
            <button type="button" class="btn btn-secondary" id="preview-btn-2">Atualizar preview</button>
            <span class="spacer"></span>
            <a class="btn btn-secondary" href="/admin/docs">Voltar</a>
        </div>
    </form>

<?php elseif ($mode === 'section'): ?>
    <div class="docs-admin-top">
        <div>
            <p class="muted" style="margin:0 0 4px"><a href="/admin/docs">← Voltar para a lista</a></p>
            <h1><?= $isNewSection ? 'Nova seção' : 'Editar seção' ?></h1>
            <p class="muted" style="margin:6px 0 0">Seções agrupam as páginas no menu lateral do guia.</p>
        </div>
    </div>

    <div class="card" style="max-width:560px">
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="save_section">
            <?php if ($editSection): ?>
                <input type="hidden" name="id" value="<?= (int) $editSection['id'] ?>">
            <?php endif; ?>
            <label>Título</label>
            <input name="title" id="section-title" required value="<?= htmlspecialchars((string) ($editSection['title'] ?? '')) ?>" placeholder="Ex.: Começar">
            <label>Slug</label>
            <input name="slug" id="section-slug" required value="<?= htmlspecialchars((string) ($editSection['slug'] ?? '')) ?>" pattern="[a-z0-9\-]+" placeholder="ex.: comecar">
            <label>Ordem</label>
            <input type="number" name="sort_order" value="<?= (int) ($editSection['sort_order'] ?? count($sections) + 1) ?>">
            <div class="row" style="margin-top:4px">
                <button type="submit"><?= $isNewSection ? 'Criar seção' : 'Salvar seção' ?></button>
                <a class="btn btn-secondary" href="/admin/docs">Cancelar</a>
            </div>
        </form>
    </div>

<?php else: ?>
    <div class="docs-admin-top">
        <div>
            <h1>Documentação</h1>
            <p class="muted" style="margin:6px 0 0">Organize seções e páginas do guia em <code>/app/docs</code>.</p>
        </div>
        <div class="docs-admin-actions">
            <a class="btn btn-secondary btn-sm" href="/app/docs" target="_blank" rel="noopener">Abrir guia</a>
            <a class="btn btn-secondary btn-sm" href="/admin/docs?new=section">Nova seção</a>
            <a class="btn btn-sm" href="/admin/docs?new=lesson">Nova página</a>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="docs-admin-list-head">
            <h2>Seções</h2>
            <a class="btn btn-secondary btn-sm" href="/admin/docs?new=section">+ Seção</a>
        </div>
        <?php if ($sections === []): ?>
            <div class="docs-admin-empty">Nenhuma seção ainda. Crie a primeira para organizar as páginas.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th style="width:70px">Ordem</th><th>Título</th><th>Slug</th><th style="width:180px"></th></tr></thead>
                    <tbody>
                    <?php foreach ($sections as $section): ?>
                        <tr>
                            <td class="mono"><?= (int) $section['sort_order'] ?></td>
                            <td><strong><?= htmlspecialchars((string) $section['title']) ?></strong></td>
                            <td class="mono"><?= htmlspecialchars((string) $section['slug']) ?></td>
                            <td>
                                <div class="row" style="gap:6px;justify-content:flex-end">
                                    <a class="btn btn-secondary btn-sm" href="/admin/docs?edit_section=<?= (int) $section['id'] ?>">Editar</a>
                                    <form method="post" class="js-confirm-delete" style="display:inline;margin:0" data-confirm="Excluir a seção “<?= htmlspecialchars((string) $section['title'], ENT_QUOTES) ?>”? Só é possível se ela estiver vazia.">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                        <input type="hidden" name="action" value="delete_section">
                                        <input type="hidden" name="id" value="<?= (int) $section['id'] ?>">
                                        <button class="btn btn-danger btn-sm" type="submit">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="docs-admin-list-head">
            <h2>Páginas</h2>
            <a class="btn btn-sm" href="/admin/docs?new=lesson">+ Página</a>
        </div>
        <input type="search" class="docs-admin-filter" id="page-filter" placeholder="Filtrar por título, slug ou seção…" autocomplete="off">

        <?php
        $bySection = [];
        foreach ($lessons as $lesson) {
            $sid = (int) ($lesson['section_id'] ?? 0);
            $bySection[$sid][] = $lesson;
        }
        $sectionMeta = [];
        foreach ($sections as $s) {
            $sectionMeta[(int) $s['id']] = $s;
        }
        ?>

        <?php if ($lessons === []): ?>
            <div class="docs-admin-empty">Nenhuma página ainda. Clique em <strong>Nova página</strong> para começar.</div>
        <?php else: ?>
            <?php foreach ($sections as $section): ?>
                <?php $sid = (int) $section['id']; $pages = $bySection[$sid] ?? []; if ($pages === []) continue; ?>
                <div class="docs-admin-section-block" data-block>
                    <div class="title-row">
                        <h3><?= htmlspecialchars((string) $section['title']) ?></h3>
                        <span class="muted" style="font-size:12px"><?= count($pages) ?> página(s)</span>
                    </div>
                    <?php foreach ($pages as $lesson): ?>
                        <div class="docs-admin-page-row" data-page
                             data-search="<?= htmlspecialchars(mb_strtolower(($lesson['title'] ?? '').' '.($lesson['slug'] ?? '').' '.($lesson['section_title'] ?? ''), 'UTF-8')) ?>">
                            <span class="mono"><?= (int) $lesson['sort_order'] ?></span>
                            <div>
                                <strong><?= htmlspecialchars((string) $lesson['title']) ?></strong>
                            </div>
                            <span class="mono"><?= htmlspecialchars((string) $lesson['slug']) ?></span>
                            <div>
                                <?php if (! empty($lesson['published'])): ?>
                                    <span class="badge ok">Publicada</span>
                                <?php else: ?>
                                    <span class="badge">Rascunho</span>
                                <?php endif; ?>
                            </div>
                            <div class="row-actions">
                                <a class="btn btn-secondary btn-sm" href="/admin/docs?edit_lesson=<?= (int) $lesson['id'] ?>">Editar</a>
                                <?php if (! empty($lesson['published'])): ?>
                                    <a class="btn btn-secondary btn-sm" href="/app/docs/<?= htmlspecialchars((string) $lesson['slug']) ?>" target="_blank" rel="noopener">Ver</a>
                                <?php endif; ?>
                                <form method="post" class="js-confirm-delete" style="display:inline;margin:0" data-confirm="Excluir a página “<?= htmlspecialchars((string) $lesson['title'], ENT_QUOTES) ?>”? Esta ação não pode ser desfeita.">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                    <input type="hidden" name="action" value="delete_lesson">
                                    <input type="hidden" name="id" value="<?= (int) $lesson['id'] ?>">
                                    <button class="btn btn-danger btn-sm" type="submit">Excluir</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <?php if (! empty($bySection[0])): ?>
                <div class="docs-admin-section-block" data-block>
                    <div class="title-row"><h3>Sem seção</h3></div>
                    <?php foreach ($bySection[0] as $lesson): ?>
                        <div class="docs-admin-page-row" data-page
                             data-search="<?= htmlspecialchars(mb_strtolower(($lesson['title'] ?? '').' '.($lesson['slug'] ?? ''), 'UTF-8')) ?>">
                            <span class="mono"><?= (int) $lesson['sort_order'] ?></span>
                            <div><strong><?= htmlspecialchars((string) $lesson['title']) ?></strong></div>
                            <span class="mono"><?= htmlspecialchars((string) $lesson['slug']) ?></span>
                            <div>
                                <?php if (! empty($lesson['published'])): ?>
                                    <span class="badge ok">Publicada</span>
                                <?php else: ?>
                                    <span class="badge">Rascunho</span>
                                <?php endif; ?>
                            </div>
                            <div class="row-actions">
                                <a class="btn btn-secondary btn-sm" href="/admin/docs?edit_lesson=<?= (int) $lesson['id'] ?>">Editar</a>
                                <form method="post" class="js-confirm-delete" style="display:inline;margin:0" data-confirm="Excluir a página “<?= htmlspecialchars((string) $lesson['title'], ENT_QUOTES) ?>”? Esta ação não pode ser desfeita.">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                    <input type="hidden" name="action" value="delete_lesson">
                                    <input type="hidden" name="id" value="<?= (int) $lesson['id'] ?>">
                                    <button class="btn btn-danger btn-sm" type="submit">Excluir</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
(function () {
    document.querySelectorAll('form.js-confirm-delete').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var msg = form.getAttribute('data-confirm') || 'Tem certeza que deseja excluir?';
            if (!window.confirm(msg)) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });

    function slugify(text) {
        return (text || '').toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 80);
    }

    function bindSlug(titleId, slugId, onlyIfEmpty) {
        var title = document.getElementById(titleId);
        var slug = document.getElementById(slugId);
        if (!title || !slug) return;
        var touched = slug.value !== '';
        slug.addEventListener('input', function () { touched = true; });
        title.addEventListener('input', function () {
            if (onlyIfEmpty && touched) return;
            if (!onlyIfEmpty || !touched) slug.value = slugify(title.value);
        });
    }

    bindSlug('lesson-title', 'lesson-slug', <?= $isNewLesson ? 'true' : 'false' ?>);
    bindSlug('section-title', 'section-slug', <?= $isNewSection ? 'true' : 'false' ?>);

    var filter = document.getElementById('page-filter');
    if (filter) {
        filter.addEventListener('input', function () {
            var q = (filter.value || '').toLowerCase().trim();
            document.querySelectorAll('[data-page]').forEach(function (row) {
                var hay = row.getAttribute('data-search') || '';
                row.style.display = (!q || hay.indexOf(q) !== -1) ? '' : 'none';
            });
            document.querySelectorAll('[data-block]').forEach(function (block) {
                var any = false;
                block.querySelectorAll('[data-page]').forEach(function (row) {
                    if (row.style.display !== 'none') any = true;
                });
                block.style.display = any ? '' : 'none';
            });
        });
    }

    var area = document.getElementById('body_markdown');
    var panel = document.getElementById('preview-panel');
    if (!area || !panel) return;

    var timer = null;
    function runPreview() {
        var fd = new FormData();
        fd.append('_csrf', <?= json_encode($csrf) ?>);
        fd.append('markdown', area.value);
        panel.innerHTML = '<p class="muted">Atualizando…</p>';
        fetch('/admin/docs/preview', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                panel.innerHTML = data.html || '<p class="muted">Sem conteúdo</p>';
            })
            .catch(function () {
                panel.innerHTML = '<p class="error">Falha no preview</p>';
            });
    }

    function schedulePreview() {
        clearTimeout(timer);
        timer = setTimeout(runPreview, 450);
    }

    ['preview-btn', 'preview-btn-2'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('click', runPreview);
    });
    area.addEventListener('input', schedulePreview);
    if (area.value.trim()) runPreview();
})();
</script>
