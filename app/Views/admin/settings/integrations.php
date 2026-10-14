<?php /** @var array $gateways @var array $checks @var array $placeholders @var string $mailDriver @var string $smtpHost @var string $analytics @var bool $whatsapp */
$ok = static fn (bool $v) => $v ? '<span class="check-ok">✔ oui</span>' : '<span class="check-no">✘ non</span>';
?>
<h1>Intégrations et mise en production</h1>

<section class="card">
  <h2>Liste de contrôle avant mise en production</h2>
  <ul class="checklist">
    <?php foreach ($checks as [$label, $pass]): ?><li class="<?= $pass ? 'is-ok' : 'is-no' ?>"><?= $ok($pass) ?> <?= e($label) ?></li><?php endforeach; ?>
  </ul>
  <?php if ($placeholders): ?>
  <p class="small"><strong>Réglages encore à compléter :</strong> <?= e(implode(' · ', $placeholders)) ?> — <a href="/admin/parametres">Paramètres du site</a></p>
  <?php endif; ?>
</section>

<section class="card">
  <h2>Paiement</h2>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Moyen</th><th>État</th><th>Variables d'environnement (.env)</th></tr></thead>
    <tbody>
      <tr><td><strong>Paiement à la livraison</strong></td><td><?= \App\Services\PaymentMethods::isOperational(\App\Services\PaymentMethods::find('cod') ?? ['is_enabled' => 0, 'kind' => 'cod']) ? '<span class="status status-delivered">Actif</span>' : '<span class="status">Désactivé</span>' ?></td><td class="small">Aucune (<a href="/admin/paiements/cod">Moyens de paiement</a> et option par zone)</td></tr>
      <?php foreach ($gateways as $code => $g): ?>
      <tr>
        <td><strong><?= e($g['label']) ?></strong>
          <?php if (!$g['demo']): ?><br><span class="small muted">URL de notification à déclarer chez le prestataire :</span><br><code class="small"><?= e($g['webhook']) ?></code><?php endif; ?></td>
        <td>
          <?php if ($g['demo']): ?>
            <?= $g['enabled'] ? '<span class="status status-pending_payment">Actif — SIMULÉ, aucune somme débitée</span>' : '<span class="status">Désactivé</span>' ?>
          <?php elseif ($g['enabled']): ?>
            <span class="status status-delivered">Actif — proposé aux clients</span>
          <?php elseif ($g['configured']): ?>
            <span class="status">Configuré, désactivé dans <a href="/admin/paiements">Moyens de paiement</a></span>
          <?php else: ?>
            <span class="status status-cancelled">Clés manquantes — non proposé</span>
          <?php endif; ?>
        </td>
        <td class="small"><?php foreach ($g['env'] as $var => $set): ?><code><?= e($var) ?></code> <?= $set ? '✔' : '✘' ?><br><?php endforeach; ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="small muted">Wave, Orange Money, MTN MoMo et la carte (CinetPay) sont intégrés et testés contre des simulateurs locaux reproduisant leurs API. Avant d'ouvrir un moyen de paiement : obtenir le contrat marchand et les clés, faire une transaction réelle de faible montant dans le bac à sable puis en production, et vérifier que la notification arrive bien sur l'URL ci-dessus (HTTPS obligatoire). Le client paie sur la page du prestataire : aucun numéro de carte ni code secret ne transite par ce serveur. Voir docs/INTEGRATIONS.md.</p>
</section>

<section class="card">
  <h2>Autres services</h2>
  <ul class="plain-list">
    <li><strong>E-mails</strong> : pilote « <?= e($mailDriver) ?> »<?php
      if ($mailDriver === 'log'): ?> — les messages sont écrits dans storage/mail, <strong>rien n'est envoyé</strong>.<?php
      elseif ($mailDriver === 'smtp'): ?> — serveur <?= $smtpHost !== '' ? '<code>' . e($smtpHost) . '</code>' : '<strong>non renseigné</strong>' ?>. Vérifier l'envoi avec <code>php tests/smtp-check.php --to=votre@adresse</code>.<?php
      else: ?> — fonction mail() du serveur (un serveur de messagerie doit être configuré sur l'hébergement).<?php endif; ?></li>
    <li><strong>WhatsApp</strong> : <?= $whatsapp ? 'bouton de contact wa.me actif' : 'numéro non renseigné (bouton masqué)' ?>. Pas d'envoi automatisé (API WhatsApp Business non intégrée).</li>
    <li><strong>Mesure d'audience</strong> : <?= e($analytics) ?> — chargée uniquement après consentement du visiteur.</li>
    <li><strong>Sauvegardes</strong> : script <code>bin/backup.php</code> à planifier (cron) — voir la documentation.</li>
    <li><strong>Expiration des paiements</strong> : script <code>bin/expire-orders.php</code> à planifier toutes les 5 à 15 minutes.</li>
  </ul>
</section>
