<?php
/**
 * Champs de traduction anglaise d'une entité (produit, catégorie, page, FAQ, zone, format).
 * @var string $type @var array $values valeurs actuelles (champ => texte) @var array $labels champ => libellé
 * @var array $long champs affichés en zone de texte @var string $prefix préfixe d'identifiant (unicité dans la page)
 */
$long ??= [];
$prefix ??= 'en';
$filled = count(array_filter($values, static fn ($v) => trim((string) $v) !== ''));
?>
<details class="translation-box"<?= $filled === 0 ? '' : ' open' ?>>
  <summary>Traduction anglaise <span class="small muted">— <?= $filled ?>/<?= count($labels) ?> champ(s) traduit(s) · champ vide = texte français affiché sur /en</span></summary>
  <div class="form-grid">
    <?php foreach ($labels as $field => $label): $id = $prefix . '-' . $field; ?>
    <div class="field<?= in_array($field, $long, true) ? ' field-full' : '' ?>">
      <label for="<?= e($id) ?>"><?= e($label) ?> <span lang="en" class="small muted">(EN)</span></label>
      <?php if (in_array($field, $long, true)): ?>
      <textarea id="<?= e($id) ?>" name="en[<?= e($field) ?>]" rows="4" lang="en" maxlength="20000"><?= e((string) ($values[$field] ?? '')) ?></textarea>
      <?php else: ?>
      <input id="<?= e($id) ?>" name="en[<?= e($field) ?>]" value="<?= e((string) ($values[$field] ?? '')) ?>" lang="en" maxlength="255">
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</details>
