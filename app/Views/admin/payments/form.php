<?php
use App\Core\View;
/** @var array $method @var array $old @var array $errors @var array $status @var array $logos @var array $kinds */
$val = static fn (string $k) => e((string) ($old[$k] ?? ''));
$chk = static fn (string $k) => !empty($old[$k]) ? ' checked' : '';
[$state, $label] = $status;
$kind = $method['kind'];
?>
<p><a href="/admin/paiements">← Moyens de paiement</a></p>
<h1><?= e($method['name']) ?></h1>
<p><span class="status <?= $state === 'ok' ? 'status-delivered' : ($state === 'missing' ? 'status-pending_payment' : 'status-cancelled') ?>"><?= e($label) ?></span> · <span class="muted"><?= e($kinds[$kind] ?? $kind) ?></span></p>
<?php if ($kind === 'gateway'): ?>
<p class="notice"><?= icon('info') ?> Paiement en ligne intégré : le client paie sur la page sécurisée du prestataire et le paiement est confirmé automatiquement après vérification auprès du prestataire. Les clés se configurent sur le serveur (.env), pas ici.</p>
<?php elseif ($kind === 'manual'): ?>
<p class="notice"><?= icon('info') ?> Paiement manuel : le client voit ces instructions après sa commande et dans l'e-mail de confirmation. Vérifiez chaque paiement reçu avant de passer la commande à « Payée » (Commandes → changer le statut). Une déclaration du client ne suffit jamais.</p>
<?php endif; ?>

<form action="/admin/paiements/<?= e($method['code']) ?>" method="post" class="card form-grid" novalidate>
  <?= csrf_field() ?>
  <div class="field-full"><?= View::partial('errors-summary', ['errors' => $errors]) ?></div>
  <div class="field-full checks-row">
    <label class="check"><input type="checkbox" name="is_enabled" value="1"<?= $chk('is_enabled') ?>> Activé (proposé lors de la commande s'il est opérationnel)</label>
    <label class="check"><input type="checkbox" name="show_logo" value="1"<?= $chk('show_logo') ?>> Afficher le logo</label>
  </div>
  <div class="field"><label for="f-name">Nom affiché</label><input id="f-name" name="name" required maxlength="80" value="<?= $val('name') ?>"></div>
  <div class="field"><label for="f-name_en">Nom affiché (anglais)</label><input id="f-name_en" name="name_en" maxlength="80" value="<?= $val('name_en') ?>"></div>
  <div class="field"><label for="f-sort">Ordre d'apparition</label><input id="f-sort" name="sort_order" type="number" min="0" max="1000" value="<?= (int) ($old['sort_order'] ?? 0) ?>"></div>
  <div class="field field-full"><label for="f-desc">Courte description (vide = texte standard)</label><input id="f-desc" name="description" maxlength="255" value="<?= $val('description') ?>"></div>
  <div class="field field-full"><label for="f-desc_en">Courte description (anglais)</label><input id="f-desc_en" name="description_en" maxlength="255" value="<?= $val('description_en') ?>"></div>
  <?php if ($kind === 'manual'): ?>
  <div class="field"><label for="f-receiver">Numéro de réception</label><input id="f-receiver" name="receiver_number" maxlength="60" value="<?= $val('receiver_number') ?>"<?= isset($errors['receiver_number']) ? ' aria-invalid="true"' : '' ?>><?= field_error($errors, 'receiver_number') ?></div>
  <div class="field"><label for="f-merchant">Numéro marchand (facultatif)</label><input id="f-merchant" name="merchant_number" maxlength="60" value="<?= $val('merchant_number') ?>"<?= isset($errors['merchant_number']) ? ' aria-invalid="true"' : '' ?>><?= field_error($errors, 'merchant_number') ?></div>
  <div class="field"><label for="f-benef">Nom du bénéficiaire</label><input id="f-benef" name="beneficiary" maxlength="120" value="<?= $val('beneficiary') ?>"></div>
  <div class="field field-full"><label for="f-instr">Instructions pour le client</label><textarea id="f-instr" name="instructions" rows="4" maxlength="3000"><?= $val('instructions') ?></textarea></div>
  <div class="field field-full"><label for="f-instr_en">Instructions (anglais)</label><textarea id="f-instr_en" name="instructions_en" rows="3" maxlength="3000"><?= $val('instructions_en') ?></textarea></div>
  <?php endif; ?>
  <div class="field-full"><button class="btn btn-primary" type="submit">Enregistrer</button></div>
</form>

<section class="card">
  <h2>Logo</h2>
  <?php if ($logos): ?><p class="admin-pay-logos admin-pay-logos-lg"><?php foreach ($logos as $src): ?><img src="<?= e($src) ?>" alt="Logo actuel"><?php endforeach; ?></p>
  <?php else: ?><p class="muted">Aucun logo : le nom est affiché en texte.</p><?php endif; ?>
  <?php if ($kind !== 'gateway' && $kind !== 'cod'): ?>
  <form action="/admin/paiements/<?= e($method['code']) ?>/logo" method="post" enctype="multipart/form-data" class="form-grid">
    <?= csrf_field() ?>
    <div class="field field-full"><label for="f-logo">Logo officiel du moyen de paiement <span class="hint">PNG, JPEG ou WebP, 80 × 24 px minimum ; utilisez le fichier fourni par l'opérateur, sans le modifier</span></label>
      <input id="f-logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" required data-preview="pv-logo"></div>
    <img id="pv-logo" class="image-preview" alt="Aperçu du logo (non enregistré)" hidden>
    <div class="field-full"><button class="btn btn-ghost btn-small" type="submit">Téléverser le logo</button></div>
  </form>
  <?php else: ?>
  <p class="small muted">Logos officiels fournis avec le site (sources dans docs/IDENTITE-VISUELLE.md).</p>
  <?php endif; ?>
</section>

<?php if ($kind === 'manual' && $method['code'] !== 'moov_money'): ?>
<form action="/admin/paiements/<?= e($method['code']) ?>/supprimer" method="post" data-confirm="Supprimer ce moyen de paiement ?"><?= csrf_field() ?><button class="btn btn-danger btn-small" type="submit">Supprimer ce moyen de paiement</button></form>
<?php endif; ?>
