<?php
use App\Core\Auth;
$current = $current ?? '';
$u = Auth::user();
$items = [
    ['/compte', 'home', t('account.title')],
    ['/compte/commandes', 'receipt', t('account.orders')],
    ['/compte/adresses', 'pin', t('account.addresses')],
    ['/compte/profil', 'person', t('account.profile')],
    ['/compte/mot-de-passe', 'lock', t('account.password')],
];
?>
<aside>
  <?php if ($u): ?>
  <div class="group profile-card">
    <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($u['first_name'], 0, 1) . mb_substr($u['last_name'], 0, 1))) ?></span>
    <div><strong><?= e($u['first_name'] . ' ' . $u['last_name']) ?></strong><br><span class="muted small"><?= e($u['email']) ?></span></div>
  </div>
  <?php endif; ?>
  <nav class="group" aria-label="<?= e(t('account.nav')) ?>">
    <?php foreach ($items as [$href, $ic, $label]): ?>
    <a class="cell" href="<?= $href ?>"<?= $current === $href ? ' aria-current="page"' : '' ?>>
      <span class="cell-icon<?= $current === $href ? '' : ' gray' ?>"><?= icon($ic) ?></span>
      <span class="cell-body cell-title"><?= e($label) ?></span>
      <span class="cell-chevron"><?= icon('chevron') ?></span>
    </a>
    <?php endforeach; ?>
  </nav>
  <form action="/compte/deconnexion" method="post" class="group">
    <?= csrf_field() ?>
    <button class="cell link-btn danger" type="submit"><span class="cell-icon gray"><?= icon('logout') ?></span><span class="cell-body cell-title"><?= e(t('account.logout')) ?></span></button>
  </form>
</aside>
