<?php
use App\Core\View;
/** @var array $summary @var array $zones @var ?array $zone @var array $quote @var array $methods @var array $methodsByZone @var array $addresses @var array $errors @var array $old @var string $idempotencyKey */
$val = static fn (string $k, string $d = '') => e($old[$k] ?? $d);
$selectedZone = (int) ($old['zone_id'] ?? 0);
$selectedMethod = (string) ($old['payment_method'] ?? '');
?>
<div class="wrap page-head">
  <h1><?= e(t('checkout.title')) ?></h1>
  <ol class="progress-steps" data-steps-nav>
    <li data-step-indicator="1"><?= e(t('checkout.step1')) ?></li>
    <li data-step-indicator="2"><?= e(t('checkout.step2')) ?></li>
    <li data-step-indicator="3"><?= e(t('checkout.step3')) ?></li>
    <li data-step-indicator="4"><?= e(t('checkout.step4')) ?></li>
  </ol>
</div>

<div class="wrap checkout-layout">
  <form class="checkout-form" action="/commande" method="post" novalidate data-checkout
        data-estimate-url="/commande/estimation" data-methods-by-zone="<?= e(json_encode($methodsByZone)) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="idempotency_key" value="<?= e($idempotencyKey) ?>">
    <input type="hidden" name="expected_total" value="<?= e($expectedTotal) ?>" data-expected-total>
    <?= View::partial('errors-summary', ['errors' => $errors]) ?>

    <fieldset class="checkout-step" data-step="1">
      <legend><?= e(t('checkout.contact')) ?></legend>
      <div class="form-card">
        <p class="muted small"><?= e(t('checkout.no_account')) ?> <a href="/compte/connexion?retour=/commande"><?= e(t('checkout.have_account')) ?></a></p>
        <div class="form-grid">
          <div class="field">
            <label for="f-first_name"><?= e(t('field.first_name_short')) ?></label>
            <input id="f-first_name" name="first_name" autocomplete="given-name" maxlength="100" required value="<?= $val('first_name') ?>"<?= aria_invalid($errors, 'first_name') ?>>
            <?= field_error($errors, 'first_name') ?>
          </div>
          <div class="field">
            <label for="f-last_name"><?= e(t('field.last_name_short')) ?></label>
            <input id="f-last_name" name="last_name" autocomplete="family-name" maxlength="100" required value="<?= $val('last_name') ?>"<?= aria_invalid($errors, 'last_name') ?>>
            <?= field_error($errors, 'last_name') ?>
          </div>
          <div class="field">
            <label for="f-phone"><?= e(t('field.phone_short')) ?> <span class="hint"><?= e(t('checkout.phone_hint')) ?></span></label>
            <input id="f-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="20" required placeholder="07 00 00 00 00" value="<?= $val('phone') ?>"<?= aria_invalid($errors, 'phone') ?>>
            <?= field_error($errors, 'phone') ?>
          </div>
          <div class="field">
            <label for="f-email"><?= e(t('field.email')) ?> <span class="hint"><?= e(t('checkout.email_hint')) ?></span></label>
            <input id="f-email" name="email" type="email" autocomplete="email" maxlength="190" required value="<?= $val('email') ?>"<?= aria_invalid($errors, 'email') ?>>
            <?= field_error($errors, 'email') ?>
          </div>
        </div>
      </div>
      <div class="step-actions"><span></span><button class="btn" type="button" data-step-next><?= e(t('checkout.next_delivery')) ?></button></div>
    </fieldset>

    <fieldset class="checkout-step" data-step="2">
      <legend><?= e(t('checkout.delivery')) ?></legend>
      <?php if (setting('delivery_instructions') !== ''): ?><div class="notice"><?= icon('truck') ?><div class="prose"><?= rich(setting('delivery_instructions')) ?></div></div><?php endif; ?>
      <p class="group-title" id="zone-legend"><?= e(t('field.zone_short')) ?></p>
      <div class="group" role="radiogroup" aria-labelledby="zone-legend" id="f-zone_id">
        <?php foreach ($zones as $z): ?>
        <label class="choice">
          <input type="radio" name="zone_id" value="<?= (int) $z['id'] ?>" required data-pickup="<?= (int) $z['is_pickup'] ?>"<?= $selectedZone === (int) $z['id'] ? ' checked' : '' ?>>
          <span class="choice-body">
            <span class="choice-title"><?= e($z['name']) ?></span>
            <span class="choice-meta"><?= (int) $z['fee'] === 0 ? e(t('checkout.free')) : money((int) $z['fee']) ?><?= $z['free_threshold'] ? ' · ' . e(t('checkout.free_from', ['amount' => money((int) $z['free_threshold'])])) : '' ?><?= $z['delay_text'] ? ' · ' . e($z['delay_text']) : '' ?></span>
            <?php if ($z['description']): ?><span class="choice-desc"><?= e($z['description']) ?></span><?php endif; ?>
          </span>
          <span class="choice-check"><?= icon('check') ?></span>
        </label>
        <?php endforeach; ?>
      </div>
      <?= field_error($errors, 'zone_id') ?>

      <div class="form-card mt" data-address-fields>
        <?php if ($addresses): ?>
        <div class="field">
          <label for="saved-address"><?= e(t('checkout.saved_address')) ?></label>
          <select id="saved-address" data-saved-address>
            <option value="">—</option>
            <?php foreach ($addresses as $a): ?>
            <option value="<?= (int) $a['id'] ?>" data-city="<?= e($a['city']) ?>" data-district="<?= e($a['district']) ?>" data-address="<?= e($a['address_line']) ?>" data-landmark="<?= e($a['landmark']) ?>"><?= e($a['label'] . ' — ' . $a['city']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="form-grid">
          <div class="field">
            <label for="f-city"><?= e(t('field.city_short')) ?></label>
            <input id="f-city" name="city" autocomplete="address-level2" maxlength="100" value="<?= $val('city') ?>"<?= aria_invalid($errors, 'city') ?>>
            <?= field_error($errors, 'city') ?>
          </div>
          <div class="field">
            <label for="f-district"><?= e(t('field.district_short')) ?></label>
            <input id="f-district" name="district" maxlength="150" value="<?= $val('district') ?>"<?= aria_invalid($errors, 'district') ?>>
          </div>
          <div class="field field-full">
            <label for="f-address"><?= e(t('field.address_short')) ?> <span class="hint"><?= e(t('checkout.address_hint')) ?></span></label>
            <input id="f-address" name="address" autocomplete="street-address" maxlength="255" value="<?= $val('address') ?>"<?= aria_invalid($errors, 'address') ?>>
            <?= field_error($errors, 'address') ?>
          </div>
          <div class="field field-full">
            <label for="f-landmark"><?= e(t('field.landmark_short')) ?> <span class="hint"><?= e(t('form.optional')) ?></span></label>
            <input id="f-landmark" name="landmark" maxlength="255" placeholder="<?= e(t('checkout.landmark_placeholder')) ?>" value="<?= $val('landmark') ?>">
          </div>
        </div>
      </div>
      <p class="notice notice-green" data-pickup-note hidden><?= icon('info') ?> <?= e(t('checkout.pickup_note')) ?></p>
      <div class="step-actions">
        <button class="btn btn-gray" type="button" data-step-prev><?= e(t('checkout.back')) ?></button>
        <button class="btn" type="button" data-step-next><?= e(t('checkout.next_payment')) ?></button>
      </div>
    </fieldset>

    <fieldset class="checkout-step" data-step="3">
      <legend><?= e(t('checkout.payment')) ?></legend>
      <div class="group" id="f-payment_method">
        <?php if (!$methods): ?><p class="cell"><?= e(t('checkout.no_method')) ?></p><?php endif; ?>
        <?php foreach ($methods as $code => $m): ?>
        <label class="choice" data-method="<?= e($code) ?>">
          <input type="radio" name="payment_method" value="<?= e($code) ?>" required<?= $selectedMethod === $code ? ' checked' : '' ?><?= $m['needs_phone'] ? ' data-needs-phone="1"' : '' ?>>
          <?= !empty($m['logos']) ? '<span class="choice-logos">' . payment_logos($m['logos'], $m['label']) . '</span>' : pay_logo($code) ?>
          <span class="choice-body">
            <span class="choice-title"><?= e($m['label']) ?></span>
            <span class="choice-desc"><?= e($m['description']) ?></span>
          </span>
          <span class="choice-check"><?= icon('check') ?></span>
        </label>
        <?php if ($m['needs_phone']): ?>
        <div class="extra-field" data-phone-for="<?= e($code) ?>"<?= $selectedMethod === $code ? '' : ' hidden' ?>>
          <div class="field">
            <label for="f-payer_phone"><?= e(t('field.payer_phone_short')) ?></label>
            <input id="f-payer_phone" name="payer_phone" type="tel" inputmode="tel" maxlength="20" placeholder="05 00 00 00 00" value="<?= $val('payer_phone') ?>"<?= aria_invalid($errors, 'payer_phone') ?>>
            <?= field_error($errors, 'payer_phone') ?>
            <span class="hint"><?= e(t('checkout.payer_phone_hint')) ?></span>
          </div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?= field_error($errors, 'payment_method') ?>
      <p class="group-footer"><?= icon('lock', 'icon icon-sm') ?> <?= e(t('checkout.secure_providers')) ?></p>
      <div class="step-actions">
        <button class="btn btn-gray" type="button" data-step-prev><?= e(t('checkout.back')) ?></button>
        <button class="btn" type="button" data-step-next><?= e(t('checkout.next_review')) ?></button>
      </div>
    </fieldset>

    <fieldset class="checkout-step" data-step="4">
      <legend><?= e(t('checkout.review')) ?></legend>
      <div class="form-card">
        <div class="field">
          <label for="f-note"><?= e(t('checkout.note')) ?> <span class="hint"><?= e(t('form.optional')) ?></span></label>
          <textarea id="f-note" name="note" rows="3" maxlength="1000"><?= $val('note') ?></textarea>
        </div>
        <label class="check" id="f-cgv">
          <input type="checkbox" name="cgv" value="1" required<?= !empty($old['cgv']) ? ' checked' : '' ?><?= aria_invalid($errors, 'cgv') ?>>
          <span><?= e(t('checkout.accept_before')) ?> <a href="/conditions-generales-de-vente" target="_blank"><?= e(t('footer.terms')) ?></a>.</span>
        </label>
        <?= field_error($errors, 'cgv') ?>
      </div>
      <div class="step-actions">
        <button class="btn btn-gray" type="button" data-step-prev><?= e(t('checkout.back')) ?></button>
        <button class="btn btn-large" type="submit" data-submit-order><?= icon('lock') ?><?= e(t('checkout.confirm')) ?></button>
      </div>
      <p class="group-footer"><?= e(t('checkout.commitment')) ?></p>
    </fieldset>
  </form>

  <aside class="sticky-aside">
    <div class="group summary" aria-labelledby="h-checkout-summary">
      <h2 id="h-checkout-summary"><?= e(t('checkout.your_order')) ?></h2>
      <ul class="mini-items">
        <?php foreach ($summary['items'] as $item): ?>
        <li><?php if (!empty($item['image_path'])): ?><img class="mini-thumb" src="<?= e($item['image_path']) ?>" alt="" width="44" height="44" loading="lazy" decoding="async"><?php endif; ?><span class="mini-name"><?= e($item['product_name']) ?> <span class="muted">· <?= e($item['variant_label']) ?> × <?= (int) $item['quantity'] ?></span></span><span><?= money((int) $item['line_total']) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <dl class="totals" aria-live="polite">
        <div><dt><?= e(t('totals.subtotal')) ?></dt><dd data-total-subtotal><?= money($quote['subtotal']) ?></dd></div>
        <?php if ($quote['discount'] > 0): ?><div class="discount"><dt><?= e(t('totals.discount')) ?></dt><dd data-total-discount>−<?= money($quote['discount']) ?></dd></div><?php endif; ?>
        <div><dt><?= e(t('totals.shipping')) ?></dt><dd data-total-shipping><?= $quote['shipping'] === null ? e(t('checkout.choose_zone')) : ($quote['shipping'] === 0 ? e(t('checkout.free')) : money($quote['shipping'])) ?></dd></div>
        <div class="grand-total"><dt><?= e(t('totals.total')) ?></dt><dd data-total-total><?= money($quote['total']) ?></dd></div>
      </dl>
      <p class="small muted"><?= e(t('checkout.server_note')) ?> <a href="/panier"><?= e(t('checkout.edit_cart')) ?></a></p>
    </div>
  </aside>
</div>
