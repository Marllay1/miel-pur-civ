<?php
use App\Core\View;
/** @var array $errors @var array $old @var array $activities */
$val = static fn (string $k) => e($old[$k] ?? '');
?>
<div class="wrap page-head">
  <h1><?= e(t('nav.pro')) ?></h1>
  <p class="lead"><?= e(t('pro.lead')) ?></p>
</div>
<div class="wrap two-cols">
  <form action="/commandes-professionnelles" method="post" class="form-card form-grid" novalidate>
    <?= csrf_field() ?><?= spam_fields() ?>
    <div class="field-full"><?= View::partial('errors-summary', ['errors' => $errors]) ?></div>
    <div class="field">
      <label for="f-company"><?= e(t('field.company_short')) ?></label>
      <input id="f-company" name="company" autocomplete="organization" maxlength="160" required value="<?= $val('company') ?>"<?= aria_invalid($errors, 'company') ?>>
      <?= field_error($errors, 'company') ?>
    </div>
    <div class="field">
      <label for="f-activity"><?= e(t('field.activity_short')) ?></label>
      <select id="f-activity" name="activity" required<?= aria_invalid($errors, 'activity') ?>>
        <option value=""><?= e(t('form.choose')) ?></option>
        <?php foreach (array_keys($activities) as $k): ?><option value="<?= e($k) ?>"<?= ($old['activity'] ?? '') === $k ? ' selected' : '' ?>><?= e(t('activity.' . $k)) ?></option><?php endforeach; ?>
      </select>
      <?= field_error($errors, 'activity') ?>
    </div>
    <div class="field">
      <label for="f-contact_name"><?= e(t('field.contact_name_short')) ?></label>
      <input id="f-contact_name" name="contact_name" autocomplete="name" maxlength="150" required value="<?= $val('contact_name') ?>"<?= aria_invalid($errors, 'contact_name') ?>>
      <?= field_error($errors, 'contact_name') ?>
    </div>
    <div class="field">
      <label for="f-phone"><?= e(t('field.phone_short')) ?></label>
      <input id="f-phone" name="phone" type="tel" autocomplete="tel" maxlength="20" required value="<?= $val('phone') ?>"<?= aria_invalid($errors, 'phone') ?>>
      <?= field_error($errors, 'phone') ?>
    </div>
    <div class="field">
      <label for="f-email"><?= e(t('field.email')) ?></label>
      <input id="f-email" name="email" type="email" autocomplete="email" maxlength="190" required value="<?= $val('email') ?>"<?= aria_invalid($errors, 'email') ?>>
      <?= field_error($errors, 'email') ?>
    </div>
    <div class="field">
      <label for="f-city"><?= e(t('field.city_short')) ?></label>
      <input id="f-city" name="city" maxlength="100" value="<?= $val('city') ?>">
    </div>
    <div class="field">
      <label for="f-products_interest"><?= e(t('field.products_interest_short')) ?></label>
      <input id="f-products_interest" name="products_interest" maxlength="255" placeholder="<?= e(t('pro.products_placeholder')) ?>" value="<?= $val('products_interest') ?>">
    </div>
    <div class="field">
      <label for="f-estimated_volume"><?= e(t('field.volume_short')) ?></label>
      <input id="f-estimated_volume" name="estimated_volume" maxlength="120" placeholder="<?= e(t('pro.volume_placeholder')) ?>" value="<?= $val('estimated_volume') ?>">
    </div>
    <div class="field">
      <label for="f-frequency"><?= e(t('field.frequency_short')) ?></label>
      <select id="f-frequency" name="frequency"<?= aria_invalid($errors, 'frequency') ?>>
        <option value="">—</option>
        <?php foreach (['ponctuelle', 'mensuelle', 'hebdomadaire', 'autre'] as $k): ?><option value="<?= $k ?>"<?= ($old['frequency'] ?? '') === $k ? ' selected' : '' ?>><?= e(t('frequency.' . $k)) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field field-full">
      <label for="f-message"><?= e(t('pro.details')) ?></label>
      <textarea id="f-message" name="message" rows="5" maxlength="5000"><?= $val('message') ?></textarea>
    </div>
    <label class="check field-full" id="f-consent"><input type="checkbox" name="consent" value="1" required<?= !empty($old['consent']) ? ' checked' : '' ?>> <span><?= e(t('pro.consent')) ?> <a href="/politique-de-confidentialite"><?= e(t('footer.privacy')) ?></a></span></label>
    <div class="field-full"><?= field_error($errors, 'consent') ?></div>
    <div class="field-full"><button class="btn btn-large" type="submit"><?= e(t('pro.send')) ?></button></div>
  </form>
  <aside>
    <h2 class="group-title"><?= e(t('pro.offer')) ?></h2>
    <div class="group">
      <div class="cell"><span class="cell-icon"><?= icon('box') ?></span><span class="cell-body"><span class="cell-title"><?= e(t('pro.offer1')) ?></span><br><span class="cell-sub"><?= e(t('pro.offer1_sub')) ?></span></span></div>
      <div class="cell"><span class="cell-icon green"><?= icon('leaf') ?></span><span class="cell-body"><span class="cell-title"><?= e(t('pro.offer2')) ?></span><br><span class="cell-sub"><?= e(t('pro.offer2_sub')) ?></span></span></div>
      <div class="cell"><span class="cell-icon gray"><?= icon('truck') ?></span><span class="cell-body"><span class="cell-title"><?= e(t('pro.offer3')) ?></span><br><span class="cell-sub"><?= e(t('pro.offer3_sub')) ?></span></span></div>
    </div>
  </aside>
</div>
