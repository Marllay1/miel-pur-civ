<?php
use App\Core\View;
/** @var array $items @var array $icons @var array $errors @var array $old @var ?int $editId @var bool $shown */
$form = static function (array $v, string $prefix, array $icons, array $errors) {
    ob_start(); ?>
    <div class="field"><label for="<?= $prefix ?>-title">Titre</label><input id="<?= $prefix ?>-title" name="title" required maxlength="80" value="<?= e((string) ($v['title'] ?? '')) ?>"<?= aria_invalid($errors, 'title') ?>><?= field_error($errors, 'title') ?></div>
    <div class="field"><label for="<?= $prefix ?>-title-en">Titre <span class="small muted" lang="en">(EN)</span></label><input id="<?= $prefix ?>-title-en" name="title_en" maxlength="80" lang="en" value="<?= e((string) ($v['title_en'] ?? '')) ?>" placeholder="vide = français"></div>
    <div class="field"><label for="<?= $prefix ?>-body">Texte <span class="hint">facultatif, 200 caractères</span></label><input id="<?= $prefix ?>-body" name="body" maxlength="200" value="<?= e((string) ($v['body'] ?? '')) ?>"></div>
    <div class="field"><label for="<?= $prefix ?>-body-en">Texte <span class="small muted" lang="en">(EN)</span></label><input id="<?= $prefix ?>-body-en" name="body_en" maxlength="200" lang="en" value="<?= e((string) ($v['body_en'] ?? '')) ?>" placeholder="vide = français"></div>
    <div class="field"><label for="<?= $prefix ?>-icon">Icône</label><select id="<?= $prefix ?>-icon" name="icon"><?php foreach ($icons as $k => $l): ?><option value="<?= e($k) ?>"<?= ($v['icon'] ?? 'leaf') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="<?= $prefix ?>-link">Lien <span class="hint">facultatif : /contact ou https://…</span></label><input id="<?= $prefix ?>-link" name="link_url" maxlength="255" value="<?= e((string) ($v['link_url'] ?? '')) ?>"<?= aria_invalid($errors, 'link_url') ?>><?= field_error($errors, 'link_url') ?></div>
    <label class="check field-full"><input type="checkbox" name="is_active" value="1"<?= !empty($v['is_active']) ? ' checked' : '' ?>> Visible sur l'accueil</label>
    <?php return (string) ob_get_clean();
};
?>
<h1>Blocs de réassurance</h1>
<p class="muted">Engagements affichés sous le carrousel de l'accueil. N'annoncez que des services réellement assurés (destinations desservies, moyens de paiement configurés, service client).
  <?= $shown ? '' : '<strong>Affichage désactivé</strong> dans <a href="/admin/parametres/accueil">Page d\'accueil</a>.' ?></p>
<?= View::partial('errors-summary', ['errors' => $errors]) ?>
<?php if (!$items): ?><p class="card muted">Aucun bloc : la bande de réassurance n'est pas affichée.</p><?php endif; ?>
<ol class="slide-list">
  <?php foreach ($items as $i => $h): $hid = (int) $h['id']; $v = $editId === $hid ? $old + $h : $h; ?>
  <li class="card" id="bloc-<?= $hid ?>">
    <div class="admin-head"><h2><span class="highlight-icon" aria-hidden="true"><?= icon((string) $h['icon']) ?></span> <?= e($h['title']) ?></h2>
      <span class="status <?= (int) $h['is_active'] ? 'status-delivered' : 'status-cancelled' ?>"><?= (int) $h['is_active'] ? 'Visible' : 'Masqué' ?></span></div>
    <form action="/admin/reassurance/<?= $hid ?>" method="post" class="form-grid" novalidate><?= csrf_field() ?><?= $form($v, 'h' . $hid, $icons, $editId === $hid ? $errors : []) ?>
      <div class="field-full btn-row"><button class="btn btn-ghost btn-small" type="submit">Enregistrer</button></div></form>
    <div class="btn-row">
      <?php if ($i > 0): ?><form action="/admin/reassurance/<?= $hid ?>/deplacer/haut" method="post"><?= csrf_field() ?><button class="btn btn-ghost btn-small" type="submit" aria-label="Monter « <?= e($h['title']) ?> »">↑</button></form><?php endif; ?>
      <?php if ($i < count($items) - 1): ?><form action="/admin/reassurance/<?= $hid ?>/deplacer/bas" method="post"><?= csrf_field() ?><button class="btn btn-ghost btn-small" type="submit" aria-label="Descendre « <?= e($h['title']) ?> »">↓</button></form><?php endif; ?>
      <form action="/admin/reassurance/<?= $hid ?>/supprimer" method="post" data-confirm="Supprimer le bloc « <?= e($h['title']) ?> » ?"><?= csrf_field() ?><button class="link-btn" type="submit">Supprimer</button></form>
    </div>
  </li>
  <?php endforeach; ?>
</ol>
<section class="card">
  <h2>Ajouter un bloc</h2>
  <form action="/admin/reassurance" method="post" class="form-grid" novalidate><?= csrf_field() ?><?= $form($editId === null ? $old + ['is_active' => '1'] : ['is_active' => '1'], 'hn', $icons, $editId === null ? $errors : []) ?>
    <div class="field-full"><button class="btn btn-primary btn-small" type="submit">Ajouter</button></div></form>
</section>
