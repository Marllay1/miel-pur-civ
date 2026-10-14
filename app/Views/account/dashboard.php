<?php use App\Core\View; /** @var array $user @var array $orders @var int $orderCount @var int $addressCount */ ?>
<div class="wrap page-head"><h1><?= e(t('account.hello', ['name' => $user['first_name']])) ?></h1></div>
<div class="wrap account-layout">
  <?= View::partial('account-nav', ['current' => '/compte']) ?>
  <div>
    <div class="stat-row">
      <div class="stat"><span class="stat-value"><?= $orderCount ?></span><span class="stat-label"><?= e(t('account.orders_count', ['count' => $orderCount])) ?></span></div>
      <div class="stat"><span class="stat-value"><?= $addressCount ?></span><span class="stat-label"><?= e(t('account.addresses_count', ['count' => $addressCount])) ?></span></div>
    </div>
    <h2 class="group-title"><?= e(t('account.recent_orders')) ?></h2>
    <?= View::render('account/_orders-table', ['orders' => $orders], null) ?>
    <?php if ($orderCount > count($orders)): ?><p class="mt"><a class="see-all" href="/compte/commandes"><?= e(t('account.all_orders')) ?><?= icon('chevron') ?></a></p><?php endif; ?>
  </div>
</div>
