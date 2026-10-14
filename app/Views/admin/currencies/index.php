<?php /** @var array $currencies @var array $errors */ ?>
<h1>Devises d'affichage</h1>
<p class="muted">Les prix et le paiement restent <strong>toujours en francs CFA (XOF)</strong> : Orange Money, MTN, Wave, la carte (CinetPay) et le paiement à la livraison encaissent en FCFA.
Une devise activée affiche seulement un <strong>équivalent indicatif</strong> à côté des prix (fiche produit, panier), calculé avec le taux saisi ici. Il n'y a pas de conversion automatique : mettez à jour les taux variables (dollar…) régulièrement.
L'euro a une parité fixe officielle : 1 € = 655,957 FCFA.</p>
<?php if ($errors): ?><div class="flash flash-error" role="alert"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th>Devise</th><th>Taux : FCFA pour 1 unité</th><th>Symbole</th><th>Décimales</th><th>Ordre</th><th>Affichée</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($currencies as $c): $fid = 'cur-' . (int) $c['id']; ?>
  <tr>
    <td><strong><?= e($c['code']) ?></strong><br><span class="small muted"><?= e($c['name']) ?> · mis à jour le <?= e(date_fr($c['updated_at'], false)) ?></span></td>
    <td><input form="<?= $fid ?>" name="xof_per_unit" inputmode="decimal" aria-label="Taux <?= e($c['code']) ?>" value="<?= $c['xof_per_unit'] !== null ? e(rtrim(rtrim(number_format((float) $c['xof_per_unit'], 4, '.', ''), '0'), '.')) : '' ?>" size="10"></td>
    <td><input form="<?= $fid ?>" name="symbol" aria-label="Symbole <?= e($c['code']) ?>" value="<?= e($c['symbol']) ?>" size="4" maxlength="8"></td>
    <td><input form="<?= $fid ?>" name="decimals" type="number" min="0" max="3" aria-label="Décimales <?= e($c['code']) ?>" value="<?= (int) $c['decimals'] ?>"></td>
    <td><input form="<?= $fid ?>" name="sort_order" type="number" min="0" aria-label="Ordre <?= e($c['code']) ?>" value="<?= (int) $c['sort_order'] ?>"></td>
    <td><label class="check"><input form="<?= $fid ?>" type="checkbox" name="is_enabled" value="1"<?= (int) $c['is_enabled'] ? ' checked' : '' ?>> <span class="sr-only">Afficher <?= e($c['code']) ?></span></label></td>
    <td>
      <form id="<?= $fid ?>" action="/admin/devises/<?= (int) $c['id'] ?>" method="post"><?= csrf_field() ?><button class="btn btn-primary btn-small" type="submit">Enregistrer</button></form>
      <form action="/admin/devises/<?= (int) $c['id'] ?>/supprimer" method="post" data-confirm="Supprimer <?= e($c['code']) ?> ?"><?= csrf_field() ?><button class="link-btn" type="submit">Supprimer</button></form>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<section class="card">
  <h2>Ajouter une devise</h2>
  <form action="/admin/devises" method="post" class="inline-form">
    <?= csrf_field() ?>
    <label class="sr-only" for="c-code">Code</label><input id="c-code" name="code" maxlength="3" size="4" placeholder="GBP" required>
    <label class="sr-only" for="c-name">Nom</label><input id="c-name" name="name" maxlength="40" placeholder="Livre sterling" required>
    <label class="sr-only" for="c-symbol">Symbole</label><input id="c-symbol" name="symbol" maxlength="8" size="4" placeholder="£" required>
    <button class="btn btn-ghost btn-small" type="submit">Ajouter</button>
  </form>
</section>
