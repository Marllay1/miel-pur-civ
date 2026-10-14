<?php
use App\Core\Lang;
use App\Services\Settings;
/** @var ?string $theme */
$links = [
    ['/a-propos', 'leaf', t('nav.about')],
    ['/commandes-professionnelles', 'building', t('nav.pro')],
    ['/suivi-commande', 'truck', t('footer.tracking')],
    ['/faq', 'info', t('nav.faq')],
    ['/contact', 'chat', t('nav.contact')],
];
$legal = [
    ['/conditions-generales-de-vente', t('footer.terms')], ['/politique-de-livraison', t('footer.delivery')],
    ['/retours-et-remboursements', t('footer.returns')], ['/politique-de-confidentialite', t('footer.privacy')], ['/mentions-legales', t('footer.legal')],
];
?>
<div class="wrap narrow page-head"><h1><?= e(t('menu.title')) ?></h1></div>
<div class="wrap narrow">
  <nav class="group" aria-label="<?= e(t('menu.title')) ?>">
    <?php foreach ($links as [$href, $ic, $label]): ?>
    <a class="cell" href="<?= $href ?>"><span class="cell-icon"><?= icon($ic) ?></span><span class="cell-body cell-title"><?= e($label) ?></span><span class="cell-chevron"><?= icon('chevron') ?></span></a>
    <?php endforeach; ?>
  </nav>

  <?php if (\App\Core\Auth::isStaff()): ?>
  <h2 class="group-title"><?= e(t('menu.team')) ?></h2>
  <div class="group">
    <a class="cell" href="/admin"><span class="cell-icon"><?= icon('gear') ?></span><span class="cell-body cell-title"><?= e(t('nav.admin')) ?></span><span class="cell-chevron"><?= icon('chevron') ?></span></a>
  </div>
  <?php endif; ?>

  <h2 class="group-title"><?= e(t('menu.language')) ?></h2>
  <div class="group"><div class="cell no-icon">
    <div class="segmented">
      <a href="<?= e(abs_url('/menu', 'fr')) ?>" hreflang="fr" lang="fr"<?= Lang::get() === 'fr' ? ' aria-current="page"' : '' ?>>Français</a>
      <a href="<?= e(abs_url('/menu', 'en')) ?>" hreflang="en" lang="en"<?= Lang::get() === 'en' ? ' aria-current="page"' : '' ?>>English</a>
    </div>
  </div></div>

  <h2 class="group-title"><?= e(t('menu.appearance')) ?></h2>
  <form action="/preferences/theme" method="post" class="group">
    <?= csrf_field() ?><input type="hidden" name="retour" value="<?= e(Lang::path('/menu')) ?>">
    <div class="cell no-icon">
      <div class="segmented" role="group" aria-label="<?= e(t('menu.appearance')) ?>">
        <button type="submit" name="theme" value="auto" aria-pressed="<?= $theme === null ? 'true' : 'false' ?>" data-theme-set="auto"><?= e(t('theme.auto')) ?></button>
        <button type="submit" name="theme" value="light" aria-pressed="<?= $theme === 'light' ? 'true' : 'false' ?>" data-theme-set="light"><?= e(t('theme.light')) ?></button>
        <button type="submit" name="theme" value="dark" aria-pressed="<?= $theme === 'dark' ? 'true' : 'false' ?>" data-theme-set="dark"><?= e(t('theme.dark')) ?></button>
      </div>
    </div>
  </form>
  <p class="group-footer"><?= e(t('menu.appearance_hint')) ?></p>

  <h2 class="group-title"><?= e(t('footer.info')) ?></h2>
  <div class="group">
    <?php foreach ($legal as [$href, $label]): ?>
    <a class="cell no-icon" href="<?= $href ?>"><span class="cell-body cell-title"><?= e($label) ?></span><span class="cell-chevron"><?= icon('chevron') ?></span></a>
    <?php endforeach; ?>
  </div>
  <?php if (Settings::get('store_phone') !== ''): ?>
  <p class="group-footer"><?= e(Settings::get('store_name')) ?> · <a href="<?= e(tel_link(Settings::get('store_phone'))) ?>"><?= e(Settings::get('store_phone')) ?></a></p>
  <?php endif; ?>
</div>
<div class="section"></div>
