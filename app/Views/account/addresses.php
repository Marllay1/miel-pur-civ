<?php use App\Core\View; /** @var array $addresses @var array $errors @var array $old */ $val = static fn (string $k) => e($old[$k] ?? ''); ?>
<div class="page-head"><div class="wrap"><h1>Mes adresses</h1></div></div>
<div class="wrap account-layout">
  <?= View::partial('account-nav', ['current' => '/compte/adresses']) ?>
  <div>
    <?php if ($addresses): ?>
    <ul class="address-list">
      <?php foreach ($addresses as $a): ?>
      <li class="card">
        <p><strong><?= e($a['label']) ?></strong><?= (int) $a['is_default'] ? ' <span class="badge">Par défaut</span>' : '' ?></p>
        <p><?= e($a['recipient_name']) ?> · <?= e($a['phone']) ?><br><?= e($a['address_line']) ?><br><?= e(trim(($a['district'] ? $a['district'] . ', ' : '') . $a['city'])) ?><?php if ($a['landmark']): ?><br>Repère : <?= e($a['landmark']) ?><?php endif; ?></p>
        <form action="/compte/adresses/<?= (int) $a['id'] ?>/supprimer" method="post" data-confirm="Supprimer cette adresse ?"><?= csrf_field() ?><button class="link-btn" type="submit">Supprimer</button></form>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?><p class="muted">Aucune adresse enregistrée.</p><?php endif; ?>

    <h2>Ajouter une adresse</h2>
    <form action="/compte/adresses" method="post" class="card form-grid" novalidate>
      <?= csrf_field() ?>
      <div class="field-full"><?= View::partial('errors-summary', ['errors' => $errors]) ?></div>
      <div class="field"><label for="f-label">Libellé</label><input id="f-label" name="label" maxlength="60" required placeholder="Maison, bureau…" value="<?= $val('label') ?>"<?= aria_invalid($errors, 'label') ?>><?= field_error($errors, 'label') ?></div>
      <div class="field"><label for="f-recipient_name">Destinataire</label><input id="f-recipient_name" name="recipient_name" maxlength="150" required value="<?= $val('recipient_name') ?>"<?= aria_invalid($errors, 'recipient_name') ?>><?= field_error($errors, 'recipient_name') ?></div>
      <div class="field"><label for="f-phone">Téléphone</label><input id="f-phone" name="phone" type="tel" maxlength="20" required value="<?= $val('phone') ?>"<?= aria_invalid($errors, 'phone') ?>><?= field_error($errors, 'phone') ?></div>
      <div class="field"><label for="f-city">Ville</label><input id="f-city" name="city" maxlength="100" required value="<?= $val('city') ?>"<?= aria_invalid($errors, 'city') ?>><?= field_error($errors, 'city') ?></div>
      <div class="field"><label for="f-district">Commune / quartier</label><input id="f-district" name="district" maxlength="150" value="<?= $val('district') ?>"></div>
      <div class="field"><label for="f-address_line">Adresse</label><input id="f-address_line" name="address_line" maxlength="255" required value="<?= $val('address_line') ?>"<?= aria_invalid($errors, 'address_line') ?>><?= field_error($errors, 'address_line') ?></div>
      <div class="field field-full"><label for="f-landmark">Point de repère</label><input id="f-landmark" name="landmark" maxlength="255" value="<?= $val('landmark') ?>"></div>
      <label class="check field-full"><input type="checkbox" name="is_default" value="1"> Adresse par défaut</label>
      <div class="field-full"><button class="btn btn-primary" type="submit">Enregistrer l'adresse</button></div>
    </form>
  </div>
</div>
