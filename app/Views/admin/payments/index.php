<?php /** @var array $methods @var array $kinds */ ?>
<h1>Moyens de paiement</h1>
<p class="muted">Seuls les moyens <strong>proposés</strong> apparaissent au client (étape de paiement, panier, pied de page, accueil). Un logo n'est jamais affiché pour un moyen non disponible.
Un prestataire en ligne n'est proposé que si ses clés sont configurées sur le serveur (fichier .env, voir docs/INTEGRATIONS.md) ; un paiement manuel n'est proposé que si ses instructions sont renseignées, et l'équipe confirme chaque paiement reçu dans la commande.</p>

<div class="table-wrap"><table class="table">
  <thead><tr><th>Ordre</th><th>Moyen</th><th>Type</th><th>État</th><th>Logo</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($methods as $code => $m): [$state, $label] = $m['status']; ?>
    <tr>
      <td class="num"><?= (int) $m['sort_order'] ?></td>
      <td><a href="/admin/paiements/<?= e($code) ?>"><strong><?= e($m['name']) ?></strong></a><br><code class="small"><?= e($code) ?></code></td>
      <td class="small"><?= e($kinds[$m['kind']] ?? $m['kind']) ?></td>
      <td><span class="status <?= $state === 'ok' ? 'status-delivered' : ($state === 'missing' ? 'status-pending_payment' : 'status-cancelled') ?>"><?= e($label) ?></span>
        <?php if ($m['env']): ?><br><span class="small muted"><?php foreach ($m['env'] as $var => $set): ?><code><?= e($var) ?></code> <?= $set ? '✔' : '✘' ?> <?php endforeach; ?></span><?php endif; ?></td>
      <td><?php if ($m['logos']): ?><span class="admin-pay-logos"><?php foreach ($m['logos'] as $src): ?><img src="<?= e($src) ?>" alt=""><?php endforeach; ?></span><?= (int) $m['show_logo'] ? '' : '<br><span class="small muted">masqué</span>' ?><?php else: ?><span class="small muted">—</span><?php endif; ?></td>
      <td>
        <form action="/admin/paiements/<?= e($code) ?>/basculer" method="post"><?= csrf_field() ?><button class="btn btn-small <?= (int) $m['is_enabled'] ? 'btn-ghost' : 'btn-primary' ?>" type="submit"><?= (int) $m['is_enabled'] ? 'Désactiver' : 'Activer' ?></button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<section class="card">
  <h2>Ajouter un moyen de paiement manuel</h2>
  <p class="small muted">Pour un moyen sans intégration technique (ex. Moov Money, virement bancaire) : le client paie hors du site en suivant vos instructions ; la commande reste « en attente de paiement » jusqu'à ce que l'équipe confirme la réception. Pour un paiement en ligne automatique, une intégration du prestataire est nécessaire.</p>
  <form action="/admin/paiements" method="post" class="inline-form">
    <?= csrf_field() ?>
    <label class="sr-only" for="pm-name">Nom du moyen de paiement</label>
    <input id="pm-name" name="name" required maxlength="80" placeholder="Nom (ex. Virement bancaire)">
    <button class="btn btn-primary btn-small" type="submit">Ajouter</button>
  </form>
</section>
