<?php /** @var array $groups @var array $errors */ ?>
<h1>Réglages</h1>
<?php if ($errors): ?><div class="error-summary" role="alert"><ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form action="/admin/reglages" method="post" novalidate>
  <?= csrf_field() ?>
  <?php foreach ($groups as $group => $items): ?>
  <fieldset class="card settings-group">
    <legend><?= e($group) ?></legend>
    <div class="form-grid">
    <?php foreach ($items as $key => $def): $type = $def['type'] ?? 'string'; $ph = str_contains((string) $def['value'], '[À COMPLÉTER') || str_contains((string) $def['value'], '[À CONFIRMER'); ?>
      <?php if ($type === 'bool'): ?>
      <label class="check field-full"><input type="checkbox" name="<?= e($key) ?>" value="1"<?= $def['value'] === '1' ? ' checked' : '' ?>> <?= e($def['label']) ?></label>
      <?php elseif (isset($def['options'])): ?>
      <div class="field"><label for="s-<?= e($key) ?>"><?= e($def['label']) ?></label><select id="s-<?= e($key) ?>" name="<?= e($key) ?>"><?php foreach ($def['options'] as $o): ?><option value="<?= e($o) ?>"<?= $def['value'] === $o ? ' selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?></select></div>
      <?php else: ?>
      <div class="field<?= $ph ? ' field-todo' : '' ?>">
        <label for="s-<?= e($key) ?>"><?= e($def['label']) ?><?= $ph ? ' <span class="badge">à compléter</span>' : '' ?></label>
        <input id="s-<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($def['value']) ?>"<?= $type === 'int' ? ' type="number" min="0"' : ' maxlength="300"' ?><?= isset($errors[$key]) ? ' aria-invalid="true"' : '' ?>>
        <?= field_error($errors, $key) ?>
      </div>
      <?php endif; ?>
    <?php endforeach; ?>
    </div>
  </fieldset>
  <?php endforeach; ?>
  <button class="btn btn-primary" type="submit">Enregistrer les réglages</button>
</form>
