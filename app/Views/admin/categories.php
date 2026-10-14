<?php use App\Core\View; /** @var array $categories @var ?array $edit @var array $old @var array $errors */ $val = static fn (string $k) => e($old[$k] ?? ''); ?>
<h1>Catégories</h1>
<div class="admin-cols">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Nom</th><th>Adresse</th><th class="num">Produits</th><th class="num">Ordre</th><th>État</th></tr></thead>
      <tbody>
      <?php foreach ($categories as $c): ?>
        <tr><td><a href="/admin/categories?id=<?= (int) $c['id'] ?>"><?= e($c['name']) ?></a></td><td><code><?= e($c['slug']) ?></code></td><td class="num"><?= (int) $c['product_count'] ?></td><td class="num"><?= (int) $c['sort_order'] ?></td>
        <td><?= (int) $c['is_active'] ? 'Active' : '<span class="muted">Masquée</span>' ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <form action="/admin/categories" method="post" class="card" novalidate>
    <?= csrf_field() ?>
    <h2><?= $edit ? 'Modifier « ' . e($edit['name']) . ' »' : 'Nouvelle catégorie' ?></h2>
    <?= View::partial('errors-summary', ['errors' => $errors]) ?>
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
    <div class="field"><label for="f-name">Nom</label><input id="f-name" name="name" required maxlength="120" value="<?= $val('name') ?>"></div>
    <div class="field"><label for="f-slug">Adresse <span class="hint">vide = automatique</span></label><input id="f-slug" name="slug" maxlength="140" value="<?= $val('slug') ?>"></div>
    <div class="field"><label for="f-description">Description</label><textarea id="f-description" name="description" rows="3"><?= $val('description') ?></textarea></div>
    <div class="field"><label for="f-sort">Ordre</label><input id="f-sort" name="sort_order" type="number" min="0" value="<?= $val('sort_order') ?: '0' ?>"></div>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= !empty($old['is_active']) ? ' checked' : '' ?>> Active</label>
    <button class="btn btn-primary btn-small" type="submit">Enregistrer</button>
    <?php if ($edit): ?> <a href="/admin/categories">Annuler</a><?php endif; ?>
  </form>
</div>
