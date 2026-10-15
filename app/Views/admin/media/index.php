<?php /** @var array $media @var string $facebook */ ?>
<h1>Photos de l'entreprise</h1>
<p class="muted">Photothèque utilisée par les galeries de l'accueil et de la page À propos, et pour la photo de l'histoire (accueil). <strong>Uniquement des photographies de l'entreprise</strong> :
  publiées sur sa page Facebook officielle<?php if ($facebook !== ''): ?> (<a href="<?= e($facebook) ?>" rel="noopener" target="_blank">ouvrir la page</a>)<?php endif; ?> ou fournies par l'entreprise. Aucune photo de banque d'images, d'un concurrent ou générée artificiellement.</p>
<details class="card">
  <summary><strong>Récupérer une photo depuis Facebook</strong></summary>
  <ol class="small">
    <li>Sur la page Facebook de l'entreprise, ouvrez la photo puis choisissez « Télécharger » (menu « … »), connecté avec un compte administrateur de la page.</li>
    <li>Ajoutez-la ci-dessous en indiquant sa provenance (ex. « Page Facebook, publication du 12/03/2025 »).</li>
    <li>Pour un produit, ajoutez-la depuis la fiche du produit (section Photos), uniquement si la photo montre bien ce produit.</li>
  </ol>
  <p class="small muted">La récupération automatique depuis Facebook n'est pas possible : la page n'est consultable qu'en partie sans compte et Facebook interdit l'extraction automatisée.</p>
</details>
<section class="card">
  <h2>Ajouter une photo</h2>
  <form action="/admin/photos" method="post" enctype="multipart/form-data" class="form-grid">
    <?= csrf_field() ?>
    <div class="field"><label for="m-file">Fichier (JPEG, PNG ou WebP, 5 Mo max., 400 × 300 px minimum)</label><input id="m-file" name="image" type="file" accept="image/jpeg,image/png,image/webp" required data-preview="m-preview"></div>
    <img id="m-preview" class="image-preview" alt="" hidden>
    <div class="field"><label for="m-alt">Description (texte alternatif)</label><input id="m-alt" name="alt" required maxlength="200"></div>
    <div class="field"><label for="m-alt-en">Description <span lang="en" class="small muted">(EN)</span></label><input id="m-alt-en" name="alt_en" maxlength="200" lang="en" placeholder="vide = français"></div>
    <div class="field"><label for="m-source">Provenance</label><input id="m-source" name="source_note" required maxlength="255" placeholder="Page Facebook, publication du …"></div>
    <div class="field-full checks-row">
      <label class="check"><input type="checkbox" name="in_home_gallery" value="1"> Galerie de l'accueil</label>
      <label class="check"><input type="checkbox" name="in_about_gallery" value="1"> Galerie « À propos »</label>
      <label class="check"><input type="checkbox" name="is_story" value="1"> Photo de l'histoire (accueil)</label>
    </div>
    <label class="check field-full"><input type="checkbox" name="source_confirm" value="1" required> Je confirme que cette photo provient des ressources de l'entreprise.</label>
    <div class="field-full"><button class="btn btn-primary btn-small" type="submit">Ajouter</button></div>
  </form>
</section>
<?php if (!$media): ?><p class="card muted">Aucune photo : les galeries ne s'affichent pas.</p><?php endif; ?>
<ol class="image-admin-list">
  <?php foreach ($media as $i => $m): $mid = (int) $m['id']; ?>
  <li class="card image-admin-row" id="photo-<?= $mid ?>">
    <img src="<?= e(\App\Services\ImageService::small((string) $m['path'])) ?>" alt="" width="160" height="120" loading="lazy">
    <div>
      <form action="/admin/photos/<?= $mid ?>" method="post" class="form-grid">
        <?= csrf_field() ?>
        <div class="field"><label for="ma-<?= $mid ?>">Description</label><input id="ma-<?= $mid ?>" name="alt" required maxlength="200" value="<?= e($m['alt']) ?>"></div>
        <div class="field"><label for="me-<?= $mid ?>">Description <span lang="en" class="small muted">(EN)</span></label><input id="me-<?= $mid ?>" name="alt_en" maxlength="200" lang="en" value="<?= e((string) $m['alt_en']) ?>"></div>
        <div class="field field-full"><label for="ms-<?= $mid ?>">Provenance</label><input id="ms-<?= $mid ?>" name="source_note" required maxlength="255" value="<?= e($m['source_note']) ?>"></div>
        <div class="field-full checks-row">
          <label class="check"><input type="checkbox" name="in_home_gallery" value="1"<?= (int) $m['in_home_gallery'] ? ' checked' : '' ?>> Galerie de l'accueil</label>
          <label class="check"><input type="checkbox" name="in_about_gallery" value="1"<?= (int) $m['in_about_gallery'] ? ' checked' : '' ?>> Galerie « À propos »</label>
          <label class="check"><input type="checkbox" name="is_story" value="1"<?= (int) $m['is_story'] ? ' checked' : '' ?>> Photo de l'histoire</label>
        </div>
        <div class="field-full"><button class="btn btn-ghost btn-small" type="submit">Enregistrer</button></div>
      </form>
      <div class="btn-row">
        <?php if ($i > 0): ?><form action="/admin/photos/<?= $mid ?>/deplacer/haut" method="post"><?= csrf_field() ?><button class="btn btn-ghost btn-small" type="submit" aria-label="Monter">↑</button></form><?php endif; ?>
        <?php if ($i < count($media) - 1): ?><form action="/admin/photos/<?= $mid ?>/deplacer/bas" method="post"><?= csrf_field() ?><button class="btn btn-ghost btn-small" type="submit" aria-label="Descendre">↓</button></form><?php endif; ?>
        <form action="/admin/photos/<?= $mid ?>/supprimer" method="post" data-confirm="Retirer cette photo de la photothèque ?"><?= csrf_field() ?><button class="link-btn" type="submit">Retirer</button></form>
      </div>
    </div>
  </li>
  <?php endforeach; ?>
</ol>
