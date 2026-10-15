<?php /** @var array $slides @var array $errors @var bool $autoplay @var int $interval */ ?>
<div class="admin-head">
  <h1>Carrousel de l'accueil</h1>
  <p class="btn-row"><a class="btn btn-primary btn-small" href="/admin/carrousel/nouvelle">Ajouter une diapositive</a> <a class="btn btn-ghost btn-small" href="/admin/carrousel/apercu?toutes=1">Aperçu (toutes)</a> <a class="btn btn-ghost btn-small" href="/admin/carrousel/apercu">Aperçu (publiées)</a></p>
</div>
<p class="muted">Les diapositives publiées s'affichent sous l'en-tête de la page d'accueil, dans cet ordre. Sans diapositive publiée, l'accueil affiche son visuel d'ouverture. N'annoncez que des offres réellement disponibles.</p>

<?php if (!$slides): ?>
<p class="card muted">Aucune diapositive.</p>
<?php else: ?>
<ol class="slide-list">
  <?php foreach ($slides as $i => $s): ?>
  <li class="card slide-row">
    <img class="slide-thumb" src="<?= e(\App\Services\ImageService::small((string) $s['image_path'])) ?>" alt="">
    <div class="slide-info">
      <p><strong><?= e($s['title']) ?></strong> <?= $s['live'] ? '<span class="status status-delivered">Publiée</span>' : ((int) $s['is_active'] ? '<span class="status status-pending_payment">Hors période de publication</span>' : '<span class="status status-cancelled">Désactivée</span>') ?></p>
      <p class="small muted"><?= e((string) $s['body']) ?></p>
      <p class="small"><?= $s['button_label'] ? 'Bouton « ' . e($s['button_label']) . ' » → <code>' . e($s['link_url']) . '</code>' : 'Sans bouton' ?><?= $s['image_mobile_path'] ? ' · image mobile dédiée' : '' ?>
        <?= $s['starts_at'] || $s['ends_at'] ? ' · publication : ' . e($s['starts_at'] ? date_fr($s['starts_at']) : '…') . ' → ' . e($s['ends_at'] ? date_fr($s['ends_at']) : '…') : '' ?></p>
    </div>
    <div class="slide-actions">
      <a class="btn btn-ghost btn-small" href="/admin/carrousel/<?= (int) $s['id'] ?>">Modifier</a>
      <form action="/admin/carrousel/<?= (int) $s['id'] ?>/basculer" method="post"><?= csrf_field() ?><button class="btn btn-ghost btn-small" type="submit"><?= (int) $s['is_active'] ? 'Désactiver' : 'Activer' ?></button></form>
      <?php if ($i > 0): ?><form action="/admin/carrousel/<?= (int) $s['id'] ?>/deplacer/haut" method="post"><?= csrf_field() ?><button class="btn btn-ghost btn-small" type="submit" aria-label="Monter « <?= e($s['title']) ?> »">↑</button></form><?php endif; ?>
      <?php if ($i < count($slides) - 1): ?><form action="/admin/carrousel/<?= (int) $s['id'] ?>/deplacer/bas" method="post"><?= csrf_field() ?><button class="btn btn-ghost btn-small" type="submit" aria-label="Descendre « <?= e($s['title']) ?> »">↓</button></form><?php endif; ?>
      <form action="/admin/carrousel/<?= (int) $s['id'] ?>/supprimer" method="post" data-confirm="Supprimer la diapositive « <?= e($s['title']) ?> » ?"><?= csrf_field() ?><button class="link-btn" type="submit">Supprimer</button></form>
    </div>
  </li>
  <?php endforeach; ?>
</ol>
<?php endif; ?>

<section class="card">
  <h2>Défilement</h2>
  <form action="/admin/carrousel/reglages" method="post" class="form-grid" novalidate>
    <?= csrf_field() ?>
    <label class="check field-full"><input type="checkbox" name="carousel_autoplay" value="1"<?= $autoplay ? ' checked' : '' ?>> Défilement automatique (mis en pause au survol et désactivé pour les visiteurs qui réduisent les animations)</label>
    <div class="field"><label for="f-int">Durée d'affichage d'une diapositive (secondes, 4 à 15)</label><input id="f-int" name="carousel_interval" type="number" min="4" max="15" value="<?= (int) $interval ?>"<?= isset($errors['carousel_interval']) ? ' aria-invalid="true"' : '' ?>><?= field_error($errors, 'carousel_interval') ?></div>
    <div class="field-full"><button class="btn btn-primary btn-small" type="submit">Enregistrer</button></div>
  </form>
</section>
