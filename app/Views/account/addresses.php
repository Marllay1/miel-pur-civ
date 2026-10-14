<?php use App\Core\View; /** @var array $addresses @var array $errors @var array $old */ $val = static fn (string $k) => e($old[$k] ?? ''); ?>
<div class="wrap page-head"><h1><?= e(t('account.addresses')) ?></h1></div>
<div class="wrap account-layout">
  <?= View::partial('account-nav', ['current' => '/compte/adresses']) ?>
  <div>
    <?php if ($addresses): ?>
    <div class="group">
      <?php foreach ($addresses as $a): ?>
      <div class="cell">
        <span class="cell-icon<?= (int) $a['is_default'] ? '' : ' gray' ?>"><?= icon('pin') ?></span>
        <span class="cell-body"><span class="cell-title"><?= e($a['label']) ?><?= (int) $a['is_default'] ? ' · ' . e(t('account.default')) : '' ?></span><br>
          <span class="cell-sub"><?= e($a['recipient_name']) ?> · <?= e($a['phone']) ?> · <?= e($a['address_line']) ?>, <?= e(trim(($a['district'] ? $a['district'] . ', ' : '') . $a['city'])) ?><?= $a['landmark'] ? ' · ' . e($a['landmark']) : '' ?></span></span>
        <form action="/compte/adresses/<?= (int) $a['id'] ?>/supprimer" method="post" data-confirm="<?= e(t('account.delete_address_confirm')) ?>"><?= csrf_field() ?><button class="link-btn danger" type="submit"><?= e(t('account.delete')) ?></button></form>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?><p class="muted"><?= e(t('account.no_addresses')) ?></p><?php endif; ?>

    <h2 class="group-title"><?= e(t('account.add_address')) ?></h2>
    <form action="/compte/adresses" method="post" class="form-card form-grid" novalidate>
      <?= csrf_field() ?>
      <div class="field-full"><?= View::partial('errors-summary', ['errors' => $errors]) ?></div>
      <div class="field"><label for="f-label"><?= e(t('field.label_short')) ?></label><input id="f-label" name="label" maxlength="60" required placeholder="<?= e(t('account.label_placeholder')) ?>" value="<?= $val('label') ?>"<?= aria_invalid($errors, 'label') ?>><?= field_error($errors, 'label') ?></div>
      <div class="field"><label for="f-recipient_name"><?= e(t('field.recipient_short')) ?></label><input id="f-recipient_name" name="recipient_name" maxlength="150" required value="<?= $val('recipient_name') ?>"<?= aria_invalid($errors, 'recipient_name') ?>><?= field_error($errors, 'recipient_name') ?></div>
      <div class="field"><label for="f-phone"><?= e(t('field.phone_short')) ?></label><input id="f-phone" name="phone" type="tel" maxlength="20" required value="<?= $val('phone') ?>"<?= aria_invalid($errors, 'phone') ?>><?= field_error($errors, 'phone') ?></div>
      <div class="field"><label for="f-city"><?= e(t('field.city_short')) ?></label><input id="f-city" name="city" maxlength="100" required value="<?= $val('city') ?>"<?= aria_invalid($errors, 'city') ?>><?= field_error($errors, 'city') ?></div>
      <div class="field"><label for="f-district"><?= e(t('field.district_short')) ?></label><input id="f-district" name="district" maxlength="150" value="<?= $val('district') ?>"></div>
      <div class="field"><label for="f-address_line"><?= e(t('field.address_short')) ?></label><input id="f-address_line" name="address_line" maxlength="255" required value="<?= $val('address_line') ?>"<?= aria_invalid($errors, 'address_line') ?>><?= field_error($errors, 'address_line') ?></div>
      <div class="field field-full"><label for="f-landmark"><?= e(t('field.landmark_short')) ?></label><input id="f-landmark" name="landmark" maxlength="255" value="<?= $val('landmark') ?>"></div>
      <label class="switch field-full"><span><?= e(t('account.make_default')) ?></span><input type="checkbox" name="is_default" value="1"></label>
      <div class="field-full mt"><button class="btn" type="submit"><?= e(t('account.save_address')) ?></button></div>
    </form>
  </div>
</div>
