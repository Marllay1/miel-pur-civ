<?php /** @var array $gateways @var array $checks @var array $placeholders @var string $mailDriver @var string $analytics @var bool $whatsapp */
$ok = static fn (bool $v) => $v ? '<span class="check-ok">✔ oui</span>' : '<span class="check-no">✘ non</span>';
?>
<h1>Intégrations et mise en production</h1>

<section class="card">
  <h2>Liste de contrôle avant mise en production</h2>
  <ul class="checklist">
    <?php foreach ($checks as [$label, $pass]): ?><li class="<?= $pass ? 'is-ok' : 'is-no' ?>"><?= $ok($pass) ?> <?= e($label) ?></li><?php endforeach; ?>
  </ul>
  <?php if ($placeholders): ?>
  <p class="small"><strong>Réglages encore à compléter :</strong> <?= e(implode(' · ', $placeholders)) ?> — <a href="/admin/reglages">Réglages</a></p>
  <?php endif; ?>
</section>

<section class="card">
  <h2>Paiement</h2>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Moyen</th><th>État</th><th>Variables d'environnement attendues</th></tr></thead>
    <tbody>
      <tr><td><strong>Paiement à la livraison</strong></td><td><?= \App\Services\Settings::bool('cod_enabled') ? '<span class="status status-delivered">Opérationnel</span>' : '<span class="status">Désactivé</span>' ?></td><td class="small">Aucune (réglage « cod_enabled » et option par zone)</td></tr>
      <?php foreach ($gateways as $code => $g): ?>
      <tr>
        <td><strong><?= e($g['label']) ?></strong><br><span class="small muted"><?= e($g['notes']) ?></span></td>
        <td><?php if ($g['demo']): ?><?= $g['enabled'] ? '<span class="status status-pending_payment">Actif — SIMULÉ</span>' : '<span class="status">Désactivé</span>' ?>
            <?php else: ?><span class="status status-cancelled">Non intégré — jamais proposé</span><?php endif; ?></td>
        <td class="small"><?php foreach ($g['env'] as $var => $set): ?><code><?= e($var) ?></code> <?= $set ? '✔' : '✘' ?><br><?php endforeach; ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="small muted">Orange Money, MTN MoMo, Wave et carte nécessitent un contrat marchand, la documentation officielle et un accès bac à sable. Une interface <code>Gateway</code> est prête ; l'intégration doit être développée et testée avant toute activation. Aucun numéro de carte ne doit transiter par ce serveur.</p>
</section>

<section class="card">
  <h2>Autres services</h2>
  <ul class="plain-list">
    <li><strong>E-mails</strong> : pilote « <?= e($mailDriver) ?> »<?= $mailDriver === 'log' ? ' — les messages sont écrits dans storage/mail, rien n\'est envoyé.' : ' — fonction mail() du serveur (MTA requis).' ?> SMTP authentifié : à intégrer.</li>
    <li><strong>WhatsApp</strong> : <?= $whatsapp ? 'bouton de contact wa.me actif' : 'numéro non renseigné (bouton masqué)' ?>. Pas d'envoi automatisé (API WhatsApp Business non intégrée).</li>
    <li><strong>Mesure d'audience</strong> : <?= e($analytics) ?> — chargée uniquement après consentement du visiteur.</li>
    <li><strong>Sauvegardes</strong> : script <code>bin/backup.php</code> à planifier (cron) — voir la documentation.</li>
    <li><strong>Expiration des paiements</strong> : script <code>bin/expire-orders.php</code> à planifier toutes les 5 à 15 minutes.</li>
  </ul>
</section>
