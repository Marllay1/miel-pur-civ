<?php
use App\Core\View;
/** @var ?array $slide @var array $old @var array $errors */
$val = static fn (string $k) => e((string) ($old[$k] ?? ''));
$dt = static fn (?string $v) => $v ? e(str_replace(' ', 'T', substr($v, 0, 16))) : '';
$action = $slide ? '/admin/carrousel/' . (int) $slide['id'] : '/admin/carrousel';
?>
<p><a href="/admin/carrousel">← Carrousel de l'accueil</a></p>
<h1><?= $slide ? 'Modifier la diapositive' : 'Nouvelle diapositive' ?></h1>
<form action="<?= e($action) ?>" method="post" enctype="multipart/form-data" class="card form-grid" novalidate>
  <?= csrf_field() ?>
  <div class="field-full"><?= View::partial('errors-summary', ['errors' => $errors]) ?></div>
  <div class="field field-full">
    <label for="f-image">Image <?= $slide ? '(laisser vide pour conserver l\'actuelle)' : '' ?> <span class="hint">JPEG, PNG ou WebP, 5 Mo maximum, 1200 × 500 px minimum, format paysage (ex. 2400 × 1000) ; le sujet principal au centre</span></label>
    <?php if ($slide): ?><img class="image-preview is-current" src="<?= e($slide['image_path']) ?>" alt="Image actuelle"><?php endif; ?>
    <input id="f-image" name="image" type="file" accept="image/jpeg,image/png,image/webp"<?= $slide ? '' : ' required' ?> data-preview="pv-image"<?= isset($errors['image']) ? ' aria-invalid="true"' : '' ?>>
    <img id="pv-image" class="image-preview" alt="Aperçu de la nouvelle image (non enregistrée)" hidden>
    <?= field_error($errors, 'image') ?>
  </div>
  <div class="field field-full">
    <label for="f-mobile">Image pour téléphones (facultatif) <span class="hint">format portrait, 600 × 700 px minimum ; sinon l'image principale est recadrée</span></label>
    <?php if ($slide && $slide['image_mobile_path']): ?><img class="image-preview is-current" src="<?= e($slide['image_mobile_path']) ?>" alt="Image mobile actuelle"><label class="check"><input type="checkbox" name="remove_mobile" value="1"> Retirer l'image mobile</label><?php endif; ?>
    <input id="f-mobile" name="image_mobile" type="file" accept="image/jpeg,image/png,image/webp" data-preview="pv-mobile"<?= isset($errors['image_mobile']) ? ' aria-invalid="true"' : '' ?>>
    <img id="pv-mobile" class="image-preview" alt="Aperçu de l'image mobile (non enregistrée)" hidden>
    <?= field_error($errors, 'image_mobile') ?>
  </div>
  <div class="field"><label for="f-alt">Description de l'image (texte alternatif)</label><input id="f-alt" name="image_alt" maxlength="200" value="<?= $val('image_alt') ?>"></div>
  <div class="field"><label for="f-alt_en">Description de l'image (anglais)</label><input id="f-alt_en" name="image_alt_en" maxlength="200" value="<?= $val('image_alt_en') ?>"></div>
  <div class="field"><label for="f-title">Titre <span class="hint">court (120 caractères maximum)</span></label><input id="f-title" name="title" required maxlength="120" value="<?= $val('title') ?>"<?= isset($errors['title']) ? ' aria-invalid="true"' : '' ?>><?= field_error($errors, 'title') ?></div>
  <div class="field"><label for="f-title_en">Titre (anglais)</label><input id="f-title_en" name="title_en" maxlength="120" value="<?= $val('title_en') ?>"></div>
  <div class="field"><label for="f-body">Texte <span class="hint">une ou deux phrases</span></label><textarea id="f-body" name="body" rows="2" maxlength="300"><?= $val('body') ?></textarea></div>
  <div class="field"><label for="f-body_en">Texte (anglais)</label><textarea id="f-body_en" name="body_en" rows="2" maxlength="300"><?= $val('body_en') ?></textarea></div>
  <div class="field"><label for="f-btn">Texte du bouton</label><input id="f-btn" name="button_label" maxlength="60" value="<?= $val('button_label') ?>"<?= isset($errors['button_label']) ? ' aria-invalid="true"' : '' ?>><?= field_error($errors, 'button_label') ?></div>
  <div class="field"><label for="f-btn_en">Texte du bouton (anglais)</label><input id="f-btn_en" name="button_label_en" maxlength="60" value="<?= $val('button_label_en') ?>"></div>
  <div class="field field-full"><label for="f-link">Lien du bouton <span class="hint">page du site (ex. /boutique, /boutique/miel-cremeux) ou adresse https://</span></label><input id="f-link" name="link_url" maxlength="255" value="<?= $val('link_url') ?>"<?= isset($errors['link_url']) ? ' aria-invalid="true"' : '' ?>><?= field_error($errors, 'link_url') ?></div>
  <div class="field"><label for="f-start">Début de publication (facultatif)</label><input id="f-start" name="starts_at" type="datetime-local" value="<?= $dt($old['starts_at'] ?? null) ?>"></div>
  <div class="field"><label for="f-end">Fin de publication (facultatif)</label><input id="f-end" name="ends_at" type="datetime-local" value="<?= $dt($old['ends_at'] ?? null) ?>"<?= isset($errors['ends_at']) ? ' aria-invalid="true"' : '' ?>><?= field_error($errors, 'ends_at') ?></div>
  <label class="check field-full"><input type="checkbox" name="is_active" value="1"<?= !empty($old['is_active']) ? ' checked' : '' ?>> Active</label>
  <div class="field-full"><button class="btn btn-primary" type="submit">Enregistrer</button></div>
</form>
