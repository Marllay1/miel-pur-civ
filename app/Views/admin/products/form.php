<?php
use App\Core\View;
use App\Services\Catalog;
/** @var ?array $product @var array $old @var array $errors @var array $categories @var array $variants @var array $images @var array $translation @var array $variantTranslations */
$val = static fn (string $k) => e($old[$k] ?? '');
$chk = static fn (string $k) => !empty($old[$k]) ? ' checked' : '';
$action = $product ? '/admin/produits/' . $product['id'] : '/admin/produits';
?>
<div class="admin-head">
  <h1><?= $product ? e($product['name']) : 'Nouveau produit' ?></h1>
  <?php if ($product && (int) $product['is_active']): ?><a class="btn btn-ghost btn-small" href="/boutique/<?= e($product['slug']) ?>" target="_blank" rel="noopener">Voir en ligne</a><?php endif; ?>
</div>
<p><a href="/admin/produits">← Tous les produits</a></p>

<form action="<?= e($action) ?>" method="post" class="card form-grid" novalidate>
  <?= csrf_field() ?>
  <div class="field-full"><?= View::partial('errors-summary', ['errors' => $errors]) ?></div>
  <div class="field"><label for="f-name">Nom</label><input id="f-name" name="name" required maxlength="160" value="<?= $val('name') ?>"<?= aria_invalid($errors, 'name') ?>><?= field_error($errors, 'name') ?></div>
  <div class="field"><label for="f-slug">Adresse (slug) <span class="hint">vide = générée depuis le nom</span></label><input id="f-slug" name="slug" maxlength="180" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= $val('slug') ?>"<?= aria_invalid($errors, 'slug') ?>><?= field_error($errors, 'slug') ?></div>
  <div class="field">
    <label for="f-category_id">Catégorie</label>
    <select id="f-category_id" name="category_id"><option value="">— Aucune —</option>
      <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (string) ($old['category_id'] ?? '') === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select><?= field_error($errors, 'category_id') ?>
  </div>
  <div class="field"><label for="f-short_description">Résumé (300 caractères)</label><input id="f-short_description" name="short_description" maxlength="300" value="<?= $val('short_description') ?>"></div>
  <div class="field field-full"><label for="f-description">Description <span class="hint">paragraphes séparés par une ligne vide · « ## » titre · « - » liste · **gras**</span></label><textarea id="f-description" name="description" rows="7"><?= $val('description') ?></textarea></div>
  <div class="field"><label for="f-origin">Origine</label><textarea id="f-origin" name="origin" rows="3"><?= $val('origin') ?></textarea></div>
  <div class="field"><label for="f-usage_tips">Utilisations</label><textarea id="f-usage_tips" name="usage_tips" rows="3"><?= $val('usage_tips') ?></textarea></div>
  <div class="field"><label for="f-storage_tips">Conservation</label><textarea id="f-storage_tips" name="storage_tips" rows="3"><?= $val('storage_tips') ?></textarea></div>
  <div class="field">
    <label for="f-meta_title">Titre SEO <span class="hint">(facultatif)</span></label><input id="f-meta_title" name="meta_title" maxlength="160" value="<?= $val('meta_title') ?>">
    <label for="f-meta_description">Description SEO <span class="hint">(facultatif)</span></label><input id="f-meta_description" name="meta_description" maxlength="300" value="<?= $val('meta_description') ?>">
  </div>
  <div class="field-full checks-row">
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= $chk('is_active') ?>> En ligne</label>
    <label class="check"><input type="checkbox" name="is_featured" value="1"<?= $chk('is_featured') ?>> À la une (accueil)</label>
    <label class="check"><input type="checkbox" name="is_demo" value="1"<?= $chk('is_demo') ?>> Produit de démonstration (badge « Démo »)</label>
  </div>
  <div class="field-full"><?= View::partial('admin-translation', [
      'type' => 'product', 'values' => $translation, 'prefix' => 'en-product',
      'labels' => ['name' => 'Nom', 'short_description' => 'Résumé', 'description' => 'Description', 'origin' => 'Origine', 'usage_tips' => 'Utilisations',
          'storage_tips' => 'Conservation', 'meta_title' => 'Titre SEO', 'meta_description' => 'Description SEO'],
      'long' => ['description', 'origin', 'usage_tips', 'storage_tips'],
  ]) ?></div>
  <div class="field-full"><button class="btn btn-primary" type="submit"><?= $product ? 'Enregistrer' : 'Créer le produit' ?></button></div>
</form>

<?php if ($product): ?>
<section class="card" id="variantes">
  <h2>Variantes (formats, prix, stock)</h2>
  <p class="muted small">Le prix est en FCFA entiers. Le stock se modifie depuis la page <a href="/admin/stock">Stock</a> (avec motif et historique).</p>
  <?php foreach ($variants as $v): ?>
  <form action="/admin/variantes/<?= (int) $v['id'] ?>" method="post" class="variant-row">
    <?= csrf_field() ?>
    <div class="field"><label for="v<?= (int) $v['id'] ?>-label">Libellé</label><input id="v<?= (int) $v['id'] ?>-label" name="label" required maxlength="100" value="<?= e($v['label']) ?>"></div>
    <div class="field"><label for="v<?= (int) $v['id'] ?>-label-en">Libellé <span lang="en" class="small muted">(EN)</span></label><input id="v<?= (int) $v['id'] ?>-label-en" name="en[label]" lang="en" maxlength="100" value="<?= e($variantTranslations[(int) $v['id']] ?? '') ?>" placeholder="vide = français"></div>
    <div class="field"><label for="v<?= (int) $v['id'] ?>-format">Format</label><select id="v<?= (int) $v['id'] ?>-format" name="format"><?php foreach (Catalog::FORMATS as $k => $l): ?><option value="<?= e($k) ?>"<?= $v['format'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="v<?= (int) $v['id'] ?>-sku">Réf. (SKU)</label><input id="v<?= (int) $v['id'] ?>-sku" name="sku" required maxlength="64" value="<?= e($v['sku']) ?>"></div>
    <div class="field"><label for="v<?= (int) $v['id'] ?>-weight">Poids (g)</label><input id="v<?= (int) $v['id'] ?>-weight" name="weight_grams" type="number" min="0" value="<?= e($v['weight_grams']) ?>"></div>
    <div class="field"><label for="v<?= (int) $v['id'] ?>-price">Prix</label><input id="v<?= (int) $v['id'] ?>-price" name="price" type="number" min="0" required value="<?= (int) $v['price'] ?>"></div>
    <div class="field"><label for="v<?= (int) $v['id'] ?>-cmp">Ancien prix</label><input id="v<?= (int) $v['id'] ?>-cmp" name="compare_at_price" type="number" min="0" value="<?= e($v['compare_at_price']) ?>"></div>
    <div class="field"><label for="v<?= (int) $v['id'] ?>-sort">Ordre</label><input id="v<?= (int) $v['id'] ?>-sort" name="sort_order" type="number" min="0" value="<?= (int) $v['sort_order'] ?>"></div>
    <div class="field"><span class="field-label">Stock</span><span class="stock-value"><?= (int) $v['stock'] ?></span></div>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= (int) $v['is_active'] ? ' checked' : '' ?>> Active</label>
    <div class="variant-actions"><button class="btn btn-ghost btn-small" type="submit">Enregistrer</button></div>
  </form>
  <form action="/admin/variantes/<?= (int) $v['id'] ?>/supprimer" method="post" class="variant-delete" data-confirm="Supprimer la variante <?= e($v['label']) ?> ?"><?= csrf_field() ?><button class="link-btn" type="submit">Supprimer « <?= e($v['label']) ?> »</button></form>
  <?php endforeach; ?>

  <h3>Ajouter une variante</h3>
  <form action="/admin/produits/<?= (int) $product['id'] ?>/variantes" method="post" class="variant-row variant-new">
    <?= csrf_field() ?>
    <div class="field"><label for="nv-label">Libellé</label><input id="nv-label" name="label" required maxlength="100" placeholder="500 g"></div>
    <div class="field"><label for="nv-label-en">Libellé <span lang="en" class="small muted">(EN)</span></label><input id="nv-label-en" name="en[label]" lang="en" maxlength="100" placeholder="vide = français"></div>
    <div class="field"><label for="nv-format">Format</label><select id="nv-format" name="format"><?php foreach (Catalog::FORMATS as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="nv-sku">Réf. (SKU)</label><input id="nv-sku" name="sku" required maxlength="64"></div>
    <div class="field"><label for="nv-weight">Poids (g)</label><input id="nv-weight" name="weight_grams" type="number" min="0"></div>
    <div class="field"><label for="nv-price">Prix</label><input id="nv-price" name="price" type="number" min="0" required></div>
    <div class="field"><label for="nv-cmp">Ancien prix</label><input id="nv-cmp" name="compare_at_price" type="number" min="0"></div>
    <div class="field"><label for="nv-sort">Ordre</label><input id="nv-sort" name="sort_order" type="number" min="0" value="<?= count($variants) ?>"></div>
    <div class="field"><label for="nv-stock">Stock initial</label><input id="nv-stock" name="stock" type="number" min="0" required value="0"></div>
    <label class="check"><input type="checkbox" name="is_active" value="1" checked> Active</label>
    <div class="variant-actions"><button class="btn btn-primary btn-small" type="submit">Ajouter</button></div>
  </form>
</section>

<section class="card" id="images">
  <h2>Images</h2>
  <?php if ($images): ?>
  <ul class="image-list">
    <?php foreach ($images as $img): ?>
    <li>
      <img src="<?= e($img['path']) ?>" alt="<?= e($img['alt']) ?>" width="120" height="120" loading="lazy">
      <p class="small"><?= e($img['alt']) ?><?= (int) $img['is_demo'] ? ' <span class="badge badge-demo">Démo</span>' : '' ?></p>
      <form action="/admin/images/<?= (int) $img['id'] ?>/supprimer" method="post" data-confirm="Supprimer cette image ?"><?= csrf_field() ?><button class="link-btn" type="submit">Supprimer</button></form>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?><p class="muted">Aucune image.</p><?php endif; ?>
  <form action="/admin/produits/<?= (int) $product['id'] ?>/images" method="post" enctype="multipart/form-data" class="form-grid">
    <?= csrf_field() ?>
    <div class="field"><label for="img-file">Fichier (JPEG, PNG ou WebP, 5 Mo max.)</label><input id="img-file" name="image" type="file" accept="image/jpeg,image/png,image/webp" required></div>
    <div class="field"><label for="img-alt">Texte alternatif (description de l'image)</label><input id="img-alt" name="alt" required maxlength="200"></div>
    <label class="check field-full"><input type="checkbox" name="first" value="1"> Image principale</label>
    <div class="field-full"><button class="btn btn-ghost btn-small" type="submit">Téléverser</button> <span class="hint">L'image est ré-encodée en WebP (1600 px max.).</span></div>
  </form>
</section>

<section class="card danger-zone">
  <h2>Supprimer le produit</h2>
  <p class="small">Le produit est archivé : il disparaît du site, mais les commandes passées conservent leurs lignes.</p>
  <form action="/admin/produits/<?= (int) $product['id'] ?>/supprimer" method="post" data-confirm="Retirer « <?= e($product['name']) ?> » du catalogue (archivage) ?"><?= csrf_field() ?><button class="btn btn-danger btn-small" type="submit">Supprimer ce produit</button></form>
</section>
<?php endif; ?>
