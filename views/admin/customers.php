<?php
/** @var list<array> $customers */
/** @var ?string $flash */
/** @var string $csrf */
?>
<?php if (! empty($flash)): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<div class="grid grid-2">
    <div class="card">
        <h1>Clientes</h1>
        <table>
            <thead><tr><th>Nome</th><th>E-mail</th><th>Status</th><th>Senha</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $c['name']) ?></td>
                    <td><?= htmlspecialchars((string) $c['email']) ?></td>
                    <td><span class="badge <?= ($c['status'] ?? '') === 'active' ? 'ok' : 'bad' ?>"><?= htmlspecialchars((string) $c['status']) ?></span></td>
                    <td><?= empty($c['password_hash']) ? 'pendente' : 'ok' ?></td>
                    <td><a class="btn btn-secondary btn-sm" href="/admin/customers/<?= (int) $c['id'] ?>">Editar</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($customers === []): ?>
                <tr><td colspan="5" class="muted">Nenhum cliente.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card">
        <h2>Novo cliente</h2>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="create">
            <label for="name">Nome</label>
            <input id="name" name="name" required>
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" required>
            <label for="phone">Telefone</label>
            <input id="phone" name="phone" placeholder="opcional">
            <label for="password">Senha</label>
            <input id="password" type="password" name="password" minlength="8" placeholder="opcional (mín. 8)">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="active">active</option>
                <option value="blocked">blocked</option>
            </select>
            <button type="submit">Criar cliente</button>
        </form>
    </div>
</div>
