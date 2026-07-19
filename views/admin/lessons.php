<?php
/** @var list<array> $sections */
/** @var list<array> $lessons */
/** @var string $csrf */
?>
<div class="grid grid-2">
    <div class="card">
        <h1>Aulas</h1>
        <table>
            <thead><tr><th>Título</th><th>Seção</th><th>Slug</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($lessons as $lesson): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $lesson['title']) ?></td>
                    <td class="muted"><?= htmlspecialchars((string) ($lesson['section_title'] ?? '—')) ?></td>
                    <td class="mono"><?= htmlspecialchars((string) $lesson['slug']) ?></td>
                    <td>
                        <form method="post" style="display:inline" onsubmit="return confirm('Excluir?')">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="delete_lesson">
                            <input type="hidden" name="id" value="<?= (int) $lesson['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Excluir</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="stack">
        <div class="card">
            <h2>Nova seção</h2>
            <form method="post">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="save_section">
                <label>Título</label>
                <input name="title" required>
                <label>Slug</label>
                <input name="slug" required>
                <label>Ordem</label>
                <input type="number" name="sort_order" value="0">
                <button type="submit">Salvar seção</button>
            </form>
        </div>
        <div class="card">
            <h2>Nova aula</h2>
            <form method="post">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="save_lesson">
                <label>Seção</label>
                <select name="section_id">
                    <option value="">—</option>
                    <?php foreach ($sections as $s): ?>
                        <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars((string) $s['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Título</label>
                <input name="title" required>
                <label>Slug</label>
                <input name="slug" required>
                <label>Ordem</label>
                <input type="number" name="sort_order" value="0">
                <label>Markdown</label>
                <textarea name="body_markdown" rows="8" required></textarea>
                <label><input type="checkbox" name="published" value="1" checked> Publicada</label>
                <label><input type="checkbox" name="docs_public" value="1"> Pública (sem compra)</label>
                <button type="submit">Salvar aula</button>
            </form>
        </div>
    </div>
</div>
