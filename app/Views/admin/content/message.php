<?php
/** @var array $message @var array $replies @var ?array $customer @var array $statuses @var array $reasons @var bool $canEmail */
$m = $message;
$mailto = 'mailto:' . rawurlencode((string) $m['email']) . '?subject=' . rawurlencode('Re : ' . $m['subject']);
?>
<p><a href="/admin/messages">← Messages</a></p>
<div class="admin-head">
  <h1><?= e($m['subject']) ?></h1>
  <span class="status status-msg-<?= e($m['status']) ?>"><?= e($statuses[$m['status']] ?? $m['status']) ?></span>
</div>
<div class="admin-cols">
  <div>
    <article class="card">
      <p class="small muted">Reçu le <?= e(date_fr($m['created_at'])) ?> · motif : <?= e($reasons[$m['reason']] ?? $m['reason']) ?></p>
      <div class="message-body"><?= nl2br(e($m['message'])) ?></div>
    </article>
    <?php foreach ($replies as $x): ?>
    <article class="card reply-card">
      <p class="small muted">Réponse du <?= e(date_fr($x['created_at'])) ?><?= $x['first_name'] ? ' par ' . e($x['first_name'] . ' ' . $x['last_name']) : '' ?><?= (int) $x['sent_by_email'] ? ' · envoyée par e-mail' : '' ?></p>
      <div class="message-body"><?= nl2br(e($x['body'])) ?></div>
    </article>
    <?php endforeach; ?>
    <section class="card">
      <h2>Répondre</h2>
      <?php if ($canEmail): ?>
      <form action="/admin/messages/<?= (int) $m['id'] ?>/reponse" method="post">
        <?= csrf_field() ?>
        <div class="field"><label for="r-body">Réponse (envoyée à <?= e($m['email']) ?>)</label><textarea id="r-body" name="body" rows="6" maxlength="5000" required></textarea></div>
        <button class="btn btn-primary btn-small" type="submit">Envoyer la réponse par e-mail</button>
        <a class="btn btn-ghost btn-small" href="<?= e($mailto) ?>">Répondre avec ma messagerie</a>
      </form>
      <?php else: ?>
      <p class="notice notice-amber small">L'envoi d'e-mails n'est pas configuré sur le serveur : la réponse directe depuis l'administration n'est pas disponible. Répondez avec votre messagerie habituelle, puis changez le statut du message.</p>
      <p><a class="btn btn-primary btn-small" href="<?= e($mailto) ?>">Répondre avec ma messagerie</a></p>
      <?php endif; ?>
    </section>
  </div>
  <div>
    <section class="card">
      <h2>Expéditeur</h2>
      <p><strong><?= e($m['name']) ?></strong><br><a href="<?= e($mailto) ?>"><?= e($m['email']) ?></a><?php if ($m['phone']): ?><br><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $m['phone'])) ?>"><?= e($m['phone']) ?></a><?php endif; ?></p>
      <p class="small"><?= $customer ? '<a href="/admin/clients/' . (int) $customer['id'] . '">Compte client</a>' : 'Visiteur sans compte' ?></p>
    </section>
    <section class="card">
      <h2>Statut</h2>
      <form action="/admin/messages/<?= (int) $m['id'] ?>" method="post" class="btn-row">
        <?= csrf_field() ?>
        <?php foreach ($statuses as $k => $l): if ($k === $m['status']) { continue; } ?>
        <button class="btn btn-ghost btn-small" type="submit" name="status" value="<?= e($k) ?>">Marquer « <?= e($l) ?> »</button>
        <?php endforeach; ?>
      </form>
    </section>
    <?php if (in_array($m['status'], ['resolved', 'archived'], true)): ?>
    <section class="card danger-zone">
      <h2>Supprimer</h2>
      <p class="small">Suppression définitive du message et de ses réponses (données personnelles).</p>
      <form action="/admin/messages/<?= (int) $m['id'] ?>/supprimer" method="post" data-confirm="Supprimer définitivement ce message ?"><?= csrf_field() ?><button class="btn btn-danger btn-small" type="submit">Supprimer</button></form>
    </section>
    <?php endif; ?>
  </div>
</div>
