<?php
use App\Core\View;
use App\Services\PromoService;
/** @var array $promos @var ?array $edit @var array $old @var array $errors */
$val = static fn (string $k) => e($old[$k] ?? '');
$dt = static fn (?string $v) => $v ? e(str_replace(' ', 'T', substr($v, 0, 16))) : '';
?>
<h1>Codes promo</h1>
<div class="admin-cols">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Code</th><th>Remise</th><th>Conditions</th><th class="num">Utilisations</th><th>État</th></tr></thead>
      <tbody>
      <?php foreach ($promos as $p): ?>
        <tr>
          <td><a href="/admin/promotions?id=<?= (int) $p['id'] ?>"><strong><?= e($p['code']) ?></strong></a><br><span class="small muted"><?= e($p['description']) ?></span></td>
          <td><?= $p['type'] === 'percent' ? (int) $p['value'] . ' %' : ($p['type'] === 'fixed' ? money((int) $p['value']) : 'Livraison offerte') ?></td>
          <td class="small"><?= (int) $p['min_subtotal'] > 0 ? 'dès ' . money((int) $p['min_subtotal']) . '<br>' : '' ?><?= $p['starts_at'] ? 'du ' . e(date_fr($p['starts_at'])) . '<br>' : '' ?><?= $p['ends_at'] ? 'au ' . e(date_fr($p['ends_at'])) : '' ?></td>
          <td class="num"><?= (int) $p['used_count'] ?><?= $p['max_uses'] !== null ? ' / ' . (int) $p['max_uses'] : '' ?></td>
          <td><?= (int) $p['is_active'] ? 'Actif' : '<span class="muted">Inactif</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$promos): ?><tr><td colspan="5" class="muted">Aucun code.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <form action="/admin/promotions" method="post" class="card" novalidate>
    <?= csrf_field() ?>
    <h2><?= $edit ? 'Modifier ' . e($edit['code']) : 'Nouveau code' ?></h2>
    <?= View::partial('errors-summary', ['errors' => $errors]) ?>
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
    <div class="field"><label for="f-code">Code</label><input id="f-code" name="code" required maxlength="40" value="<?= $val('code') ?>"></div>
    <div class="field"><label for="f-description">Description interne</label><input id="f-description" name="description" maxlength="255" value="<?= $val('description') ?>"></div>
    <div class="field"><label for="f-type">Type</label><select id="f-type" name="type"><?php foreach (PromoService::TYPES as $k => $l): ?><option value="<?= $k ?>"<?= ($old['type'] ?? '') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="f-value">Valeur <span class="hint">(% ou FCFA)</span></label><input id="f-value" name="value" type="number" min="0" value="<?= $val('value') ?>"><?= field_error($errors, 'value') ?></div>
    <div class="field"><label for="f-min">Panier minimum (FCFA)</label><input id="f-min" name="min_subtotal" type="number" min="0" value="<?= $val('min_subtotal') ?: '0' ?>"></div>
    <div class="field"><label for="f-max">Utilisations maximum <span class="hint">vide = illimité</span></label><input id="f-max" name="max_uses" type="number" min="1" value="<?= $val('max_uses') ?>"></div>
    <div class="field"><label for="f-start">Début <span class="hint">(heure d'Abidjan)</span></label><input id="f-start" name="starts_at" type="datetime-local" value="<?= $dt($old['starts_at'] ?? null) ?>"></div>
    <div class="field"><label for="f-end">Fin</label><input id="f-end" name="ends_at" type="datetime-local" value="<?= $dt($old['ends_at'] ?? null) ?>"><?= field_error($errors, 'ends_at') ?></div>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= !empty($old['is_active']) ? ' checked' : '' ?>> Actif</label>
    <button class="btn btn-primary btn-small" type="submit">Enregistrer</button>
    <?php if ($edit): ?> <a href="/admin/promotions">Annuler</a><?php endif; ?>
  </form>
</div>
