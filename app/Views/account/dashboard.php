<?php use App\Core\View; /** @var array $user @var array $orders @var int $orderCount @var int $addressCount */ ?>
<div class="page-head"><div class="wrap"><h1>Bonjour <?= e($user['first_name']) ?></h1></div></div>
<div class="wrap account-layout">
  <?= View::partial('account-nav', ['current' => '/compte']) ?>
  <div>
    <div class="stat-row">
      <div class="stat"><span class="stat-value"><?= $orderCount ?></span><span class="stat-label">commande(s)</span></div>
      <div class="stat"><span class="stat-value"><?= $addressCount ?></span><span class="stat-label">adresse(s)</span></div>
    </div>
    <h2>Dernières commandes</h2>
    <?= View::render('account/_orders-table', ['orders' => $orders], null) ?>
    <?php if ($orderCount > count($orders)): ?><p><a class="link-arrow" href="/compte/commandes">Toutes mes commandes</a></p><?php endif; ?>
  </div>
</div>
