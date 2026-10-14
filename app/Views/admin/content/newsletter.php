<?php /** @var array $subscribers @var int $count */ ?>
<div class="admin-head"><h1>Newsletter <span class="muted">(<?= $count ?> inscrit<?= $count > 1 ? 's' : '' ?>)</span></h1><a class="btn btn-ghost btn-small" href="/admin/newsletter/export">Exporter les inscrits (CSV)</a></div>
<p class="muted small">Inscriptions avec consentement explicite (case à cocher). L'envoi de campagnes n'est pas intégré : utilisez l'export dans votre outil d'e-mailing.</p>
<div class="table-wrap"><table class="table">
  <thead><tr><th>E-mail</th><th>Statut</th><th>Consentement</th></tr></thead>
  <tbody>
  <?php foreach ($subscribers as $s): ?><tr><td><?= e($s['email']) ?></td><td><?= $s['status'] === 'subscribed' ? 'Inscrit' : '<span class="muted">Désinscrit</span>' ?></td><td class="small"><?= e(date_fr($s['consent_at'])) ?></td></tr><?php endforeach; ?>
  <?php if (!$subscribers): ?><tr><td colspan="3" class="muted">Aucun inscrit.</td></tr><?php endif; ?>
  </tbody>
</table></div>
