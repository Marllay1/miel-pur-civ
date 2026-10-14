<?php
use App\Core\View;
use App\Services\Settings;
/** @var array $errors @var array $old */
$val = static fn (string $k) => e($old[$k] ?? '');
$wa = wa_link();
$phones = \App\Services\SiteConfig::phones();
$emails = \App\Services\SiteConfig::emails();
$address = \App\Services\SiteConfig::address();
$maps = \App\Services\SiteConfig::mapsUrl();
$socials = \App\Services\SiteConfig::socialLinks();
?>
<div class="wrap page-head"><h1><?= e(t('nav.contact')) ?></h1><p class="lead"><?= e(t('contact.lead')) ?></p></div>
<div class="wrap two-cols">
  <form action="/contact" method="post" class="form-card form-grid" novalidate>
    <?= csrf_field() ?><?= spam_fields() ?>
    <div class="field-full"><?= View::partial('errors-summary', ['errors' => $errors]) ?></div>
    <div class="field">
      <label for="f-name"><?= e(t('field.name_short')) ?></label>
      <input id="f-name" name="name" autocomplete="name" maxlength="150" required value="<?= $val('name') ?>"<?= aria_invalid($errors, 'name') ?>>
      <?= field_error($errors, 'name') ?>
    </div>
    <div class="field">
      <label for="f-email"><?= e(t('field.email')) ?></label>
      <input id="f-email" name="email" type="email" autocomplete="email" maxlength="190" required value="<?= $val('email') ?>"<?= aria_invalid($errors, 'email') ?>>
      <?= field_error($errors, 'email') ?>
    </div>
    <div class="field">
      <label for="f-phone"><?= e(t('field.phone_short')) ?> <span class="hint"><?= e(t('form.optional')) ?></span></label>
      <input id="f-phone" name="phone" type="tel" autocomplete="tel" maxlength="20" value="<?= $val('phone') ?>"<?= aria_invalid($errors, 'phone') ?>>
      <?= field_error($errors, 'phone') ?>
    </div>
    <div class="field">
      <label for="f-subject"><?= e(t('field.subject_short')) ?></label>
      <input id="f-subject" name="subject" maxlength="160" required value="<?= $val('subject') ?>"<?= aria_invalid($errors, 'subject') ?>>
      <?= field_error($errors, 'subject') ?>
    </div>
    <div class="field field-full">
      <label for="f-message"><?= e(t('field.message_short')) ?></label>
      <textarea id="f-message" name="message" rows="6" maxlength="5000" required<?= aria_invalid($errors, 'message') ?>><?= $val('message') ?></textarea>
      <?= field_error($errors, 'message') ?>
    </div>
    <label class="check field-full" id="f-consent"><input type="checkbox" name="consent" value="1" required<?= !empty($old['consent']) ? ' checked' : '' ?>> <span><?= e(t('contact.consent')) ?> <a href="/politique-de-confidentialite"><?= e(t('footer.privacy')) ?></a></span></label>
    <div class="field-full"><?= field_error($errors, 'consent') ?></div>
    <div class="field-full"><button class="btn btn-large" type="submit"><?= e(t('contact.send')) ?></button></div>
  </form>
  <aside>
    <div class="action-tiles">
      <?php if ($phones): ?><a class="action-tile" href="<?= e($phones[0]['href']) ?>"><?= icon('phone') ?><?= e(t('product.call')) ?></a><?php endif; ?>
      <?php if ($wa): ?><a class="action-tile" href="<?= e($wa) ?>" rel="noopener" target="_blank" aria-label="<?= e(t('social.whatsapp')) ?>"><?= social_icon('whatsapp', 'icon') ?>WhatsApp</a><?php endif; ?>
      <?php if (isset($socials['facebook'])): ?><a class="action-tile" href="<?= e($socials['facebook'][0]) ?>" rel="noopener" target="_blank" aria-label="<?= e($socials['facebook'][1]) ?>"><?= social_icon('facebook', 'icon') ?>Facebook</a><?php endif; ?>
      <?php if ($emails): ?><a class="action-tile" href="mailto:<?= e(reset($emails)) ?>"><?= icon('mail') ?>E-mail</a><?php endif; ?>
    </div>
    <h2 class="group-title"><?= e(t('contact.details')) ?></h2>
    <div class="group">
      <?php if ($address !== ''): ?><?php if ($maps): ?><a class="cell" href="<?= e($maps) ?>" rel="noopener" target="_blank"><?php else: ?><div class="cell"><?php endif; ?><span class="cell-icon"><?= icon('pin') ?></span><span class="cell-body"><?= e($address) ?></span><?= $maps ? '<span class="cell-chevron">' . icon('chevron') . '</span></a>' : '</div>' ?><?php endif; ?>
      <?php foreach ($phones as $p): ?><a class="cell" href="<?= e($p['href']) ?>"><span class="cell-icon green"><?= icon('phone') ?></span><span class="cell-body"><?= e($p['display']) ?></span><span class="cell-chevron"><?= icon('chevron') ?></span></a><?php endforeach; ?>
      <?php foreach ($emails as $kind => $email): ?><a class="cell" href="mailto:<?= e($email) ?>"><span class="cell-icon gray"><?= icon('mail') ?></span><span class="cell-body"><?= e($email) ?><?php if ($kind === 'sales'): ?><br><span class="cell-sub"><?= e(t('contact.sales_email')) ?></span><?php endif; ?></span><span class="cell-chevron"><?= icon('chevron') ?></span></a><?php endforeach; ?>
      <?php if (setting('opening_hours') !== ''): ?><div class="cell"><span class="cell-icon gray"><?= icon('clock') ?></span><span class="cell-body"><?= e(setting('opening_hours')) ?></span></div><?php endif; ?>
    </div>
  </aside>
</div>
