<?php
use App\Core\View;
/** @var array $groups @var string $group @var array $keys @var array $current @var array $counts @var string $q @var array $errors @var array $old */
?>
<h1>Textes du site</h1>
<p class="muted">Titres de sections, libellés de boutons et messages affichés sur le site public. Laissez un champ vide pour revenir au texte d'origine (affiché en gris).
  Les mots précédés de « : » (ex. <code>:amount</code>) sont remplacés automatiquement et doivent être conservés. Les textes longs de l'accueil, les pages, la FAQ et les produits ont leur propre écran.</p>
<nav class="site-tabs" aria-label="Rubriques">
  <?php foreach ($groups as $slug => [$title]): ?>
  <a href="<?= e(url('/admin/textes', ['rubrique' => $slug])) ?>"<?= $slug === $group ? ' aria-current="page"' : '' ?>><?= e($title) ?><?= isset($counts[$slug]) ? ' (' . (int) $counts[$slug] . ' modifié' . ($counts[$slug] > 1 ? 's' : '') . ')' : '' ?></a>
  <?php endforeach; ?>
</nav>
<form class="filters inline-form" method="get" action="/admin/textes">
  <input type="hidden" name="rubrique" value="<?= e($group) ?>">
  <label class="sr-only" for="t-q">Rechercher un texte</label><input id="t-q" name="q" type="search" maxlength="80" value="<?= e($q) ?>" placeholder="Rechercher un texte…">
  <button class="btn btn-ghost btn-small" type="submit">Rechercher</button>
</form>
<?php if (!$keys): ?><p class="card muted">Aucun texte.</p><?php else: ?>
<form action="/admin/textes/<?= e($group) ?>" method="post" class="texts-form" novalidate>
  <?= csrf_field() ?>
  <?= View::partial('errors-summary', ['errors' => $errors]) ?>
  <div class="table-wrap"><table class="table texts-table">
    <thead><tr><th>Texte d'origine</th><th>Français (site)</th><th>Anglais (site)</th></tr></thead>
    <tbody>
    <?php foreach ($keys as $key => $def): $id = 'tx-' . preg_replace('/[^a-z0-9]/', '-', $key); ?>
      <tr<?= isset($current[$key]) ? ' class="row-new"' : '' ?>>
        <td class="small"><?= e($def['fr']) ?><br><code class="muted"><?= e($key) ?></code></td>
        <?php foreach (['fr', 'en'] as $locale): $val = $old[$key][$locale] ?? ($current[$key][$locale] ?? ''); ?>
        <td><label class="sr-only" for="<?= e($id . '-' . $locale) ?>"><?= e($def['fr']) ?> (<?= strtoupper($locale) ?>)</label>
          <textarea id="<?= e($id . '-' . $locale) ?>" name="t[<?= e($key) ?>][<?= $locale ?>]" rows="<?= mb_strlen($def[$locale] ?: $def['fr']) > 70 ? 3 : 1 ?>" maxlength="1000"<?= $locale === 'en' ? ' lang="en"' : '' ?>
            placeholder="<?= e($def[$locale] !== '' ? $def[$locale] : $def['fr']) ?>"<?= isset($errors[$key . '.' . $locale]) ? ' aria-invalid="true"' : '' ?>><?= e((string) $val) ?></textarea></td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="btn-row"><button class="btn btn-primary" type="submit">Enregistrer la rubrique</button></p>
</form>
<?php endif; ?>
