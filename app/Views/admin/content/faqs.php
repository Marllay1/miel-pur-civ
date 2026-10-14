<?php use App\Core\View; /** @var array $faqs @var ?array $edit @var array $old @var array $errors @var array $translation */ $val = static fn (string $k) => e($old[$k] ?? ''); ?>
<h1>FAQ</h1>
<div class="admin-cols">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Rubrique</th><th>Question</th><th class="num">Ordre</th><th>État</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($faqs as $f): ?>
      <tr><td><?= e($f['category']) ?></td><td><a href="/admin/faq?id=<?= (int) $f['id'] ?>"><?= e($f['question']) ?></a></td><td class="num"><?= (int) $f['sort_order'] ?></td>
      <td><?= (int) $f['is_active'] ? 'Publiée' : '<span class="muted">Masquée</span>' ?></td>
      <td><form action="/admin/faq/<?= (int) $f['id'] ?>/supprimer" method="post" data-confirm="Supprimer cette question ?"><?= csrf_field() ?><button class="link-btn" type="submit">Supprimer</button></form></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <form action="/admin/faq" method="post" class="card" novalidate>
    <?= csrf_field() ?>
    <h2><?= $edit ? 'Modifier la question' : 'Nouvelle question' ?></h2>
    <?= View::partial('errors-summary', ['errors' => $errors]) ?>
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
    <div class="field"><label for="f-category">Rubrique</label><input id="f-category" name="category" required maxlength="80" value="<?= $val('category') ?>"></div>
    <div class="field"><label for="f-question">Question</label><input id="f-question" name="question" required maxlength="255" value="<?= $val('question') ?>"></div>
    <div class="field"><label for="f-answer">Réponse</label><textarea id="f-answer" name="answer" rows="6" required><?= $val('answer') ?></textarea></div>
    <div class="field"><label for="f-sort">Ordre</label><input id="f-sort" name="sort_order" type="number" min="0" value="<?= $val('sort_order') ?: '0' ?>"></div>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= !empty($old['is_active']) ? ' checked' : '' ?>> Publiée</label>
    <?= View::partial('admin-translation', ['type' => 'faq', 'values' => $translation, 'prefix' => 'en-faq',
        'labels' => ['question' => 'Question', 'answer' => 'Réponse', 'category' => 'Rubrique'], 'long' => ['answer']]) ?>
    <button class="btn btn-primary btn-small" type="submit">Enregistrer</button>
    <?php if ($edit): ?> <a href="/admin/faq">Annuler</a><?php endif; ?>
  </form>
</div>
