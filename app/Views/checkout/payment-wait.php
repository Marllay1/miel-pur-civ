<?php /** @var array $order @var array $payment @var string $token */ ?>
<div class="wrap narrow">
  <div class="group waiting" data-poll-url="<?= e(url('/commande/' . $order['number'] . '/paiement/statut', ['t' => $token])) ?>">
    <div class="spinner" aria-hidden="true"></div>
    <h1><?= e(t('pay.wait_title')) ?></h1>
    <p class="lead"><?= e(t('pay.wait_text', ['amount' => money((int) $payment['amount']), 'phone' => (string) $payment['payer_phone']])) ?></p>
    <p class="muted small" data-poll-status role="status" aria-live="polite"><?= e(t('pay.wait_checking')) ?></p>
    <div class="btn-row center">
      <a class="btn btn-gray" href="<?= e(url('/commande/' . $order['number'], ['t' => $token])) ?>"><?= e(t('pay.wait_back')) ?></a>
    </div>
  </div>
</div>
<div class="section"></div>
