<?php use App\Core\View; /** @var array $orders */ ?>
<div class="wrap page-head"><h1><?= e(t('account.orders')) ?></h1></div>
<div class="wrap account-layout">
  <?= View::partial('account-nav', ['current' => '/compte/commandes']) ?>
  <div><?= View::render('account/_orders-table', ['orders' => $orders], null) ?></div>
</div>
