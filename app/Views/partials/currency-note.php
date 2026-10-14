<?php
/** Conversions indicatives d'un montant en FCFA (devises activées par l'administrateur). @var int $amount */
$conversions = \App\Services\Currencies::conversions((int) $amount);
?>
<?php if ($conversions): ?>
<p class="currency-note"><span><?= e(implode(' · ', $conversions)) ?></span> <span class="muted"><?= e(t('currency.indicative')) ?></span></p>
<?php endif; ?>
