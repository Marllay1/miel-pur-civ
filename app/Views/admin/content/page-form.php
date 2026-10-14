<?php use App\Core\View; /** @var array $page @var array $old @var array $errors @var array $translation */ ?>
<p><a href="/admin/pages">← Pages</a></p>
<h1><?= e($page['title']) ?></h1>
<form action="/admin/pages/<?= (int) $page['id'] ?>" method="post" class="card" novalidate>
  <?= csrf_field() ?>
  <?= View::partial('errors-summary', ['errors' => $errors]) ?>
  <div class="field"><label for="f-title">Titre</label><input id="f-title" name="title" required maxlength="160" value="<?= e($old['title'] ?? '') ?>"></div>
  <div class="field"><label for="f-body">Contenu <span class="hint">« ## » titre · « - » liste · **gras** · [lien](/chemin) · ligne vide = paragraphe</span></label><textarea id="f-body" name="body" rows="22" class="mono"><?= e($old['body'] ?? '') ?></textarea></div>
  <div class="field"><label for="f-meta">Description SEO</label><input id="f-meta" name="meta_description" maxlength="300" value="<?= e($old['meta_description'] ?? '') ?>"></div>
  <?php if ((int) $page['is_legal']): ?>
  <label class="check"><input type="checkbox" name="is_validated" value="1"<?= !empty($old['is_validated']) ? ' checked' : '' ?>> Texte relu et validé juridiquement (suivi interne, exigé par la liste de contrôle de mise en production)</label>
  <?php endif; ?>
  <?= View::partial('admin-translation', ['type' => 'page', 'values' => $translation, 'prefix' => 'en-page',
      'labels' => ['title' => 'Titre', 'body' => 'Contenu', 'meta_description' => 'Description SEO'], 'long' => ['body']]) ?>
  <button class="btn btn-primary" type="submit">Enregistrer</button>
  <a class="btn btn-ghost" href="/<?= e($page['slug']) ?>" target="_blank" rel="noopener">Voir la page</a>
</form>
