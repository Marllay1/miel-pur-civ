<?php
use App\Core\View;
/** @var array $summary @var array $zones @var ?array $zone @var array $quote @var array $methods @var array $methodsByZone @var array $addresses @var array $errors @var array $old @var string $idempotencyKey */
$val = static fn (string $k, string $d = '') => e($old[$k] ?? $d);
$selectedZone = (int) ($old['zone_id'] ?? 0);
$selectedMethod = (string) ($old['payment_method'] ?? '');
?>
<div class="page-head">
  <div class="wrap">
    <h1>Finaliser la commande</h1>
    <ol class="checkout-steps" data-steps-nav>
      <li data-step-indicator="1"><span class="hex-num" aria-hidden="true">1</span> Coordonnées</li>
      <li data-step-indicator="2"><span class="hex-num" aria-hidden="true">2</span> Livraison</li>
      <li data-step-indicator="3"><span class="hex-num" aria-hidden="true">3</span> Paiement</li>
      <li data-step-indicator="4"><span class="hex-num" aria-hidden="true">4</span> Confirmation</li>
    </ol>
  </div>
</div>

<div class="wrap checkout-layout">
  <form class="checkout-form" action="/commande" method="post" novalidate data-checkout
        data-estimate-url="/commande/estimation" data-methods-by-zone="<?= e(json_encode($methodsByZone)) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="idempotency_key" value="<?= e($idempotencyKey) ?>">
    <input type="hidden" name="expected_total" value="<?= e($expectedTotal) ?>" data-expected-total>
    <?= View::partial('errors-summary', ['errors' => $errors]) ?>

    <fieldset class="checkout-step" data-step="1">
      <legend><span class="step-num">1</span> Vos coordonnées</legend>
      <p class="muted small">Pas besoin de compte. <a href="/compte/connexion?retour=/commande">Déjà client ? Connectez-vous</a>.</p>
      <div class="form-grid">
        <div class="field">
          <label for="f-first_name">Prénom</label>
          <input id="f-first_name" name="first_name" autocomplete="given-name" maxlength="100" required value="<?= $val('first_name') ?>"<?= aria_invalid($errors, 'first_name') ?>>
          <?= field_error($errors, 'first_name') ?>
        </div>
        <div class="field">
          <label for="f-last_name">Nom</label>
          <input id="f-last_name" name="last_name" autocomplete="family-name" maxlength="100" required value="<?= $val('last_name') ?>"<?= aria_invalid($errors, 'last_name') ?>>
          <?= field_error($errors, 'last_name') ?>
        </div>
        <div class="field">
          <label for="f-phone">Téléphone <span class="hint">(pour la confirmation)</span></label>
          <input id="f-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="20" required placeholder="07 00 00 00 00" value="<?= $val('phone') ?>"<?= aria_invalid($errors, 'phone') ?>>
          <?= field_error($errors, 'phone') ?>
        </div>
        <div class="field">
          <label for="f-email">E-mail <span class="hint">(confirmation et suivi)</span></label>
          <input id="f-email" name="email" type="email" autocomplete="email" maxlength="190" required value="<?= $val('email') ?>"<?= aria_invalid($errors, 'email') ?>>
          <?= field_error($errors, 'email') ?>
        </div>
      </div>
      <div class="step-actions"><button class="btn btn-primary" type="button" data-step-next>Continuer vers la livraison</button></div>
    </fieldset>

    <fieldset class="checkout-step" data-step="2">
      <legend><span class="step-num">2</span> Livraison</legend>
      <div class="zone-options" role="radiogroup" aria-labelledby="zone-legend" id="f-zone_id">
        <p id="zone-legend" class="field-label">Zone de livraison</p>
        <?php foreach ($zones as $z): ?>
        <label class="option-card">
          <input type="radio" name="zone_id" value="<?= (int) $z['id'] ?>" required data-pickup="<?= (int) $z['is_pickup'] ?>"<?= $selectedZone === (int) $z['id'] ? ' checked' : '' ?>>
          <span class="option-body">
            <span class="option-title"><?= e($z['name']) ?></span>
            <span class="option-meta"><?= (int) $z['fee'] === 0 ? 'Gratuit' : money((int) $z['fee']) ?><?= $z['free_threshold'] ? ' · offerte dès ' . money((int) $z['free_threshold']) : '' ?><?= $z['delay_text'] ? ' · ' . e($z['delay_text']) : '' ?></span>
            <?php if ($z['description']): ?><span class="option-desc"><?= rich_inline($z['description']) ?></span><?php endif; ?>
            <?php if (!(int) $z['allows_cod']): ?><span class="option-desc">Paiement à la livraison non disponible pour cette zone.</span><?php endif; ?>
          </span>
        </label>
        <?php endforeach; ?>
        <?= field_error($errors, 'zone_id') ?>
      </div>

      <?php if ($addresses): ?>
      <div class="field">
        <label for="saved-address">Utiliser une adresse enregistrée</label>
        <select id="saved-address" data-saved-address>
          <option value="">—</option>
          <?php foreach ($addresses as $a): ?>
          <option value="<?= (int) $a['id'] ?>" data-city="<?= e($a['city']) ?>" data-district="<?= e($a['district']) ?>" data-address="<?= e($a['address_line']) ?>" data-landmark="<?= e($a['landmark']) ?>" data-phone="<?= e($a['phone']) ?>"><?= e($a['label'] . ' — ' . $a['city']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <div class="form-grid" data-address-fields>
        <div class="field">
          <label for="f-city">Ville</label>
          <input id="f-city" name="city" autocomplete="address-level2" maxlength="100" value="<?= $val('city') ?>"<?= aria_invalid($errors, 'city') ?>>
          <?= field_error($errors, 'city') ?>
        </div>
        <div class="field">
          <label for="f-district">Commune / quartier</label>
          <input id="f-district" name="district" maxlength="150" value="<?= $val('district') ?>"<?= aria_invalid($errors, 'district') ?>>
          <?= field_error($errors, 'district') ?>
        </div>
        <div class="field field-full">
          <label for="f-address">Adresse <span class="hint">(rue, lot, immeuble…)</span></label>
          <input id="f-address" name="address" autocomplete="street-address" maxlength="255" value="<?= $val('address') ?>"<?= aria_invalid($errors, 'address') ?>>
          <?= field_error($errors, 'address') ?>
        </div>
        <div class="field field-full">
          <label for="f-landmark">Point de repère <span class="hint">(facultatif)</span></label>
          <input id="f-landmark" name="landmark" maxlength="255" placeholder="Ex. : près de la pharmacie…" value="<?= $val('landmark') ?>"<?= aria_invalid($errors, 'landmark') ?>>
        </div>
      </div>
      <p class="muted small" data-pickup-note hidden>Retrait : nous vous appellerons pour convenir du rendez-vous. Aucune adresse n'est nécessaire.</p>
      <div class="step-actions">
        <button class="btn btn-ghost" type="button" data-step-prev>Retour</button>
        <button class="btn btn-primary" type="button" data-step-next>Continuer vers le paiement</button>
      </div>
    </fieldset>

    <fieldset class="checkout-step" data-step="3">
      <legend><span class="step-num">3</span> Paiement</legend>
      <div class="payment-options" id="f-payment_method">
        <?php if (!$methods): ?>
        <p class="flash flash-error">Aucun moyen de paiement n'est disponible actuellement. Contactez-nous.</p>
        <?php endif; ?>
        <?php foreach ($methods as $code => $m): ?>
        <label class="option-card<?= $m['online'] ? ' option-demo' : '' ?>" data-method="<?= e($code) ?>">
          <input type="radio" name="payment_method" value="<?= e($code) ?>" required<?= $selectedMethod === $code ? ' checked' : '' ?>>
          <span class="option-body">
            <span class="option-title"><?= e($m['label']) ?><?php if ($code === 'demo'): ?> <span class="badge badge-demo">SIMULÉ</span><?php endif; ?></span>
            <span class="option-desc"><?= e($m['description']) ?></span>
          </span>
        </label>
        <?php endforeach; ?>
        <?= field_error($errors, 'payment_method') ?>
      </div>
      <p class="muted small">Les paiements Orange Money, MTN Mobile Money, Wave et carte bancaire ne sont pas encore disponibles sur ce site.</p>
      <div class="step-actions">
        <button class="btn btn-ghost" type="button" data-step-prev>Retour</button>
        <button class="btn btn-primary" type="button" data-step-next>Vérifier la commande</button>
      </div>
    </fieldset>

    <fieldset class="checkout-step" data-step="4">
      <legend><span class="step-num">4</span> Vérification et confirmation</legend>
      <div class="field">
        <label for="f-note">Instructions pour la livraison <span class="hint">(facultatif)</span></label>
        <textarea id="f-note" name="note" rows="3" maxlength="1000"><?= $val('note') ?></textarea>
      </div>
      <label class="check" id="f-cgv">
        <input type="checkbox" name="cgv" value="1" required<?= !empty($old['cgv']) ? ' checked' : '' ?><?= aria_invalid($errors, 'cgv') ?>>
        J'ai lu et j'accepte les <a href="/conditions-generales-de-vente" target="_blank">conditions générales de vente</a>.
      </label>
      <?= field_error($errors, 'cgv') ?>
      <div class="step-actions">
        <button class="btn btn-ghost" type="button" data-step-prev>Retour</button>
        <button class="btn btn-primary btn-large" type="submit" data-submit-order>Confirmer la commande</button>
      </div>
      <p class="muted small">En confirmant, vous vous engagez à régler le montant total indiqué.</p>
    </fieldset>
  </form>

  <aside class="checkout-summary" aria-labelledby="h-checkout-summary">
    <h2 id="h-checkout-summary">Votre commande</h2>
    <ul class="mini-items">
      <?php foreach ($summary['items'] as $item): ?>
      <li><span><?= e($item['product_name']) ?> <span class="muted">– <?= e($item['variant_label']) ?> × <?= (int) $item['quantity'] ?></span></span><span><?= money((int) $item['line_total']) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <dl class="totals" aria-live="polite">
      <div><dt>Sous-total</dt><dd data-total-subtotal><?= money($quote['subtotal']) ?></dd></div>
      <?php if ($quote['discount'] > 0): ?><div class="discount"><dt>Remise</dt><dd data-total-discount>−<?= money($quote['discount']) ?></dd></div><?php endif; ?>
      <div><dt>Livraison</dt><dd data-total-shipping><?= $quote['shipping'] === null ? 'Choisissez une zone' : ($quote['shipping'] === 0 ? 'Offerte' : money($quote['shipping'])) ?></dd></div>
      <div class="grand-total"><dt>Total</dt><dd data-total-total><?= money($quote['total']) ?></dd></div>
    </dl>
    <p class="muted small">Montants recalculés par nos serveurs à la validation. <a href="/panier">Modifier le panier</a></p>
  </aside>
</div>
