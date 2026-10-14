<?php /** @var array $fields @var array $errors */ ?>
<h1>Contenu de l'accueil</h1>
<p class="muted small">Mise en forme : paragraphes séparés par une ligne vide, **gras**, [lien](/chemin). Laissez les marqueurs [À COMPLÉTER] tant que l'information n'est pas vérifiée : ils sont affichés en surbrillance.</p>
<form action="/admin/contenu" method="post" class="card" novalidate>
  <?= csrf_field() ?>
  <?php foreach ($fields as $key => $f): ?>
  <div class="field">
    <label for="f-<?= e($key) ?>"><?= e($f['label']) ?></label>
    <?php if ($f['long']): ?><textarea id="f-<?= e($key) ?>" name="<?= e($key) ?>" rows="5"><?= e($f['value']) ?></textarea>
    <?php else: ?><input id="f-<?= e($key) ?>" name="<?= e($key) ?>" maxlength="300" value="<?= e($f['value']) ?>"><?php endif; ?>
    <?= field_error($errors, $key) ?>
  </div>
  <?php endforeach; ?>
  <button class="btn btn-primary" type="submit">Enregistrer</button>
</form>
