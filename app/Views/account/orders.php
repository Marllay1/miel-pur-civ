<?php use App\Core\View; /** @var array $orders */ ?>
<div class="page-head"><div class="wrap"><h1>Mes commandes</h1></div></div>
<div class="wrap account-layout">
  <?= View::partial('account-nav', ['current' => '/compte/commandes']) ?>
  <div><?= View::render('account/_orders-table', ['orders' => $orders], null) ?></div>
</div>
