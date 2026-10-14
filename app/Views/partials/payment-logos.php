<?php
/**
 * Logos des moyens de paiement réellement proposés (PaymentMethods::acceptedLogos()).
 * Moyen sans logo (ex. paiement à la livraison) : nom affiché en texte, jamais de logo imité.
 * @var array $methods
 */
?>
<ul class="pay-logos" role="list">
  <?php foreach ($methods as $m): ?>
  <li class="pay-logo-item">
    <?php if ($m['logos']): ?>
      <?php foreach ($m['logos'] as $i => $src): ?><img src="<?= e($src) ?>" alt="<?= $i === 0 ? e($m['name']) : '' ?>" loading="lazy" decoding="async"><?php endforeach; ?>
    <?php else: ?>
      <span class="pay-logo-text"><?= e($m['name']) ?></span>
    <?php endif; ?>
  </li>
  <?php endforeach; ?>
</ul>
