<?php use App\Core\View; /** @var array $zones @var ?array $edit @var array $old @var array $errors */ $val = static fn (string $k) => e($old[$k] ?? ''); $chk = static fn (string $k) => !empty($old[$k]) ? ' checked' : ''; ?>
<h1>Zones de livraison</h1>
<p class="muted small">Les frais sont recalculés par le serveur à chaque commande. Les tarifs de démonstration sont marqués [À CONFIRMER].</p>
<div class="admin-cols">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Zone</th><th class="num">Frais</th><th class="num">Offerte dès</th><th>Délai</th><th>Options</th><th class="num">Cmd.</th></tr></thead>
    <tbody>
    <?php foreach ($zones as $z): ?>
      <tr class="<?= (int) $z['is_active'] ? '' : 'row-off' ?>"><td><a href="/admin/livraison?id=<?= (int) $z['id'] ?>"><?= e($z['name']) ?></a><br><span class="small muted"><?= rich_inline((string) $z['description']) ?></span></td>
      <td class="num"><?= money((int) $z['fee']) ?></td><td class="num"><?= $z['free_threshold'] ? money((int) $z['free_threshold']) : '—' ?></td><td class="small"><?= e($z['delay_text']) ?></td>
      <td class="small"><?= (int) $z['allows_cod'] ? 'Paiement livraison' : 'Pas de paiement livraison' ?><?= (int) $z['is_pickup'] ? '<br>Retrait' : '' ?><?= (int) $z['is_active'] ? '' : '<br><strong>Inactive</strong>' ?></td><td class="num"><?= (int) $z['order_count'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <form action="/admin/livraison" method="post" class="card" novalidate>
    <?= csrf_field() ?>
    <h2><?= $edit ? 'Modifier « ' . e($edit['name']) . ' »' : 'Nouvelle zone' ?></h2>
    <?= View::partial('errors-summary', ['errors' => $errors]) ?>
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
    <div class="field"><label for="z-name">Nom</label><input id="z-name" name="name" required maxlength="120" value="<?= $val('name') ?>"></div>
    <div class="field"><label for="z-desc">Description</label><input id="z-desc" name="description" maxlength="255" value="<?= $val('description') ?>"></div>
    <div class="field"><label for="z-fee">Frais (FCFA)</label><input id="z-fee" name="fee" type="number" min="0" required value="<?= $val('fee') ?: '0' ?>"></div>
    <div class="field"><label for="z-free">Livraison offerte dès (FCFA) <span class="hint">vide = jamais</span></label><input id="z-free" name="free_threshold" type="number" min="0" value="<?= $val('free_threshold') ?>"></div>
    <div class="field"><label for="z-delay">Délai indicatif</label><input id="z-delay" name="delay_text" maxlength="120" value="<?= $val('delay_text') ?>"></div>
    <div class="field"><label for="z-sort">Ordre</label><input id="z-sort" name="sort_order" type="number" min="0" value="<?= $val('sort_order') ?: '0' ?>"></div>
    <label class="check"><input type="checkbox" name="allows_cod" value="1"<?= $chk('allows_cod') ?>> Paiement à la livraison possible</label>
    <label class="check"><input type="checkbox" name="is_pickup" value="1"<?= $chk('is_pickup') ?>> Retrait (pas d'adresse demandée)</label>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= $chk('is_active') ?>> Active</label>
    <button class="btn btn-primary btn-small" type="submit">Enregistrer</button>
    <?php if ($edit): ?> <a href="/admin/livraison">Annuler</a><?php endif; ?>
  </form>
</div>
