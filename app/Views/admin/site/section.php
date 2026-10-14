<?php
use App\Core\View;
/**
 * @var string $slug @var array $section @var array $sections @var array $fields @var array $images @var array $errors @var array $fonts
 */
$hint = static fn (array $f): string => !empty($f['hint']) ? ' <span class="hint">' . e($f['hint']) . '</span>' : '';
$optionLabels = [
    'font_heading' => array_map(static fn ($f) => $f[0], $fonts), 'font_body' => array_map(static fn ($f) => $f[0], $fonts),
    'lang_default' => ['fr' => 'Français', 'en' => 'English'],
    'analytics_provider' => ['none' => 'Aucune', 'plausible' => 'Plausible', 'ga4' => 'Google Analytics 4'],
    'checkout_geocoder' => ['none' => 'Aucun (coordonnées seules)', 'nominatim' => 'Nominatim (OpenStreetMap)'],
    'deposit_mode' => ['percent' => 'Pourcentage du total', 'fixed' => 'Montant fixe (FCFA)'],
];
?>
<p><a href="/admin/parametres">← Paramètres du site</a></p>
<h1><?= e($section['title']) ?></h1>
<p class="muted"><?= e($section['intro']) ?></p>
<?php if ($slug === 'paiement' && setting('payment_settings_reviewed') !== '1'): ?>
<p class="notice notice-amber" role="status"><span><strong>Valeurs initiales, non validées par l'entreprise.</strong> Montants, pourcentages, délais et conditions sont des réglages de départ : vérifiez-les puis cochez « Ces paramètres ont été vérifiés et validés par l'entreprise ». Aucune coordonnée marchande, aucun frais ni aucune condition commerciale n'a été inventé.</span></p>
<?php endif; ?>

<nav class="site-tabs" aria-label="Sections des paramètres">
  <?php foreach ($sections as $s => $def): ?><a href="/admin/parametres/<?= e($s) ?>"<?= $s === $slug ? ' aria-current="page"' : '' ?>><?= e($def['title']) ?></a><?php endforeach; ?>
</nav>

<?php if (!empty($section['links'])): ?>
<ul class="site-links">
  <?php foreach ($section['links'] as [$href, $label]): ?><li><a href="<?= e($href) ?>"><?= e($label) ?> →</a></li><?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if ($images): ?>
<?php foreach ($images as $key => $img): [$minW, $minH, $square] = $img['rule']; $current = $img['value']; ?>
<section class="card image-setting" id="<?= e($key) ?>">
  <h2><?= e($img['label']) ?></h2>
  <div class="image-setting-row">
    <div class="image-current">
      <?php if ($current !== ''): ?>
      <img src="<?= e($current) ?>" alt="Image actuelle : <?= e($img['label']) ?>">
      <p class="small">Image téléversée.</p>
      <?php else: ?>
      <?php $fallback = match ($key) {
          'brand_logo' => brand_logo_url(), 'brand_logo_dark' => brand_logo_url('dark'), 'brand_logo_footer' => brand_logo_url('stacked'),
          'brand_logo_footer_dark' => brand_logo_url('stacked-dark'), 'brand_favicon' => is_file(BASE_PATH . '/public/assets/img/brand/icon-192.png') ? '/assets/img/brand/icon-192.png' : null,
          'og_image' => '/assets/img/og-default.jpg', default => null,
      }; ?>
      <?php if ($fallback): ?><img src="<?= e($fallback) ?>" alt="Image utilisée par défaut"><p class="small muted">Par défaut (aucune image téléversée).</p><?php else: ?><p class="small muted">Aucune image.</p><?php endif; ?>
      <?php endif; ?>
    </div>
    <div>
      <form action="/admin/parametres/images/<?= e($key) ?>" method="post" enctype="multipart/form-data" class="form-grid">
        <?= csrf_field() ?>
        <div class="field field-full">
          <label for="f-<?= e($key) ?>">Nouvelle image <span class="hint">PNG, JPEG ou WebP, 5 Mo maximum, au moins <?= $minW ?> × <?= $minH ?> px<?= $square ? ', carrée' : '' ?><?= in_array($key, ['brand_logo', 'brand_logo_dark', 'brand_logo_footer', 'brand_logo_footer_dark', 'brand_favicon'], true) ? ' ; fond transparent conseillé (PNG)' : '' ?></span></label>
          <input id="f-<?= e($key) ?>" name="image" type="file" accept="image/png,image/jpeg,image/webp" required data-preview="pv-<?= e($key) ?>">
        </div>
        <img id="pv-<?= e($key) ?>" class="image-preview" alt="Aperçu de la nouvelle image (non enregistrée)" hidden>
        <div class="field-full"><button class="btn btn-primary btn-small" type="submit"><?= $current !== '' ? 'Remplacer' : 'Téléverser' ?></button></div>
      </form>
      <?php if ($current !== ''): ?>
      <form action="/admin/parametres/images/<?= e($key) ?>/supprimer" method="post" data-confirm="Retirer cette image et revenir à la valeur par défaut ?"><?= csrf_field() ?><button class="link-btn" type="submit">Retirer l'image (revenir à la valeur par défaut)</button></form>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($fields): ?>
<form action="/admin/parametres/<?= e($slug) ?>" method="post" class="card form-grid" novalidate>
  <?= csrf_field() ?>
  <div class="field-full"><?= View::partial('errors-summary', ['errors' => $errors]) ?></div>
  <?php foreach ($fields as $key => $f): $type = $f['type'] ?? 'string'; $id = 'f-' . $key; $err = $errors[$key] ?? null; ?>
    <?php if ($type === 'bool'): ?>
    <div class="field field-full">
      <label class="check"><input type="checkbox" name="<?= e($key) ?>" value="1"<?= $f['value'] === '1' ? ' checked' : '' ?>> <?= e($f['label']) ?><?= $hint($f) ?></label>
      <?php if ($err): ?><p class="field-error"><?= e($err) ?></p><?php endif; ?>
    </div>
    <?php elseif (isset($f['options'])): ?>
    <div class="field">
      <label for="<?= e($id) ?>"><?= e($f['label']) ?><?= $hint($f) ?></label>
      <select id="<?= e($id) ?>" name="<?= e($key) ?>"<?= $err ? ' aria-invalid="true"' : '' ?>>
        <?php foreach ($f['options'] as $opt): ?><option value="<?= e($opt) ?>"<?= $f['value'] === $opt ? ' selected' : '' ?>><?= e($optionLabels[$key][$opt] ?? $opt) ?></option><?php endforeach; ?>
      </select>
      <?php if ($err): ?><p class="field-error"><?= e($err) ?></p><?php endif; ?>
    </div>
    <?php elseif ($type === 'color'): ?>
    <div class="field">
      <label for="<?= e($id) ?>"><?= e($f['label']) ?><?= $hint($f) ?></label>
      <span class="color-field"><input id="<?= e($id) ?>" name="<?= e($key) ?>" type="color" value="<?= e($f['value']) ?>"<?= $err ? ' aria-invalid="true"' : '' ?>><code><?= e($f['value']) ?></code></span>
      <?php if ($err): ?><p class="field-error"><?= e($err) ?></p><?php endif; ?>
    </div>
    <?php elseif ($type === 'text'): ?>
    <div class="field field-full">
      <label for="<?= e($id) ?>"><?= e($f['label']) ?><?= $hint($f) ?></label>
      <textarea id="<?= e($id) ?>" name="<?= e($key) ?>" rows="3" maxlength="5000"<?= $err ? ' aria-invalid="true"' : '' ?>><?= e($f['value']) ?></textarea>
      <?php if ($err): ?><p class="field-error"><?= e($err) ?></p><?php endif; ?>
    </div>
    <?php else: ?>
    <div class="field">
      <label for="<?= e($id) ?>"><?= e($f['label']) ?><?= $hint($f) ?><?php if (str_starts_with($key, 'social_') && $type === 'url'): $net = substr($key, 7); ?> <span class="social-preview"><?= social_icon($net === 'other' ? 'link' : $net) ?></span><?php endif; ?></label>
      <input id="<?= e($id) ?>" name="<?= e($key) ?>" value="<?= e($f['value']) ?>"
        type="<?= match ($type) { 'url' => 'url', 'email' => 'email', 'phone', 'dialcode' => 'tel', 'int' => 'number', default => 'text' } ?>"
        <?= $type === 'url' ? 'placeholder="https://"' : '' ?><?= $type === 'int' ? ' min="0"' : '' ?> maxlength="<?= $type === 'url' ? 500 : 255 ?>"<?= $err ? ' aria-invalid="true"' : '' ?>>
      <?php if ($err): ?><p class="field-error"><?= e($err) ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php if ($slug === 'coordonnees'): ?>
  <p class="field-full small muted">Lien WhatsApp actuel : <?= ($wa = wa_link()) !== null ? '<a href="' . e($wa) . '" rel="noopener" target="_blank">' . e($wa) . '</a>' : 'aucun (numéro absent ou WhatsApp désactivé)' ?>.
    Numéro principal affiché : <?= e(\App\Services\SiteConfig::mainPhone() ?: 'aucun') ?>. L'adresse de notification des commandes n'est jamais affichée sur le site.</p>
  <?php endif; ?>
  <div class="field-full btn-row">
    <button class="btn btn-primary" type="submit">Enregistrer</button>
    <?php if ($slug === 'apparence'): ?><button class="btn btn-ghost" type="submit" formaction="/admin/parametres/apparence/apercu" formtarget="_blank">Aperçu (sans enregistrer)</button><?php endif; ?>
  </div>
</form>
<?php endif; ?>
