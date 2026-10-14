<?php /** @var array $reviews @var string $status */ $tabs = ['pending' => 'À modérer', 'approved' => 'Publiés', 'rejected' => 'Refusés']; ?>
<h1>Avis clients</h1>
<p class="muted small">Seuls les avis publiés apparaissent sur le site. Ne publiez jamais un avis inventé ou rédigé par l'équipe ; ne modifiez pas le texte d'un client.</p>
<ul class="status-tabs">
  <?php foreach ($tabs as $k => $l): ?><li><a href="/admin/avis?statut=<?= $k ?>"<?= $status === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?></a></li><?php endforeach; ?>
</ul>
<?php if (!$reviews): ?><p class="muted">Aucun avis dans cette liste.</p><?php endif; ?>
<?php foreach ($reviews as $rv): ?>
<article class="card">
  <p><strong><?= e($rv['author_name']) ?></strong> · <?= str_repeat('★', (int) $rv['rating']) . str_repeat('☆', 5 - (int) $rv['rating']) ?> · <a href="/boutique/<?= e($rv['product_slug']) ?>" target="_blank" rel="noopener"><?= e($rv['product_name']) ?></a> · <span class="small muted"><?= e(date_fr($rv['created_at'])) ?><?= (int) $rv['verified_purchase'] ? ' · achat vérifié' : '' ?></span></p>
  <p><?= nl2br(e($rv['comment'])) ?></p>
  <form action="/admin/avis/<?= (int) $rv['id'] ?>" method="post" class="inline-form">
    <?= csrf_field() ?>
    <?php if ($rv['status'] !== 'approved'): ?><button class="btn btn-primary btn-small" type="submit" name="status" value="approved">Publier</button><?php endif; ?>
    <?php if ($rv['status'] !== 'rejected'): ?><button class="btn btn-ghost btn-small" type="submit" name="status" value="rejected">Refuser</button><?php endif; ?>
    <?php if ($rv['status'] !== 'pending'): ?><button class="link-btn" type="submit" name="status" value="pending">Remettre en attente</button><?php endif; ?>
  </form>
</article>
<?php endforeach; ?>
