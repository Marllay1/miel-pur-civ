<?php
/** Aperçu de l'apparence avant publication (rien n'est enregistré). @var array $values @var array $problems @var string $cssUrl */
?><!doctype html>
<html lang="fr" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Aperçu de l'apparence — Administration</title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e($cssUrl) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="site preview-page">
<div class="preview-banner"><strong>Aperçu — non publié.</strong> Fermez cet onglet et cliquez sur « Enregistrer » pour appliquer ces réglages.</div>
<?php if ($problems): ?>
<div class="wrap"><div class="notice notice-amber" role="alert"><p><strong>Ces couleurs seront refusées à l'enregistrement :</strong></p><ul><?php foreach ($problems as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul></div></div>
<?php endif; ?>
<header class="navbar"><div class="wrap navbar-inner"><span class="brand"><?= brand_html() ?></span><nav><ul class="nav-links"><li><a href="#" aria-current="page">Boutique</a></li><li><a href="#">Notre histoire</a></li><li><a href="#">Contact</a></li></ul></nav></div></header>
<main class="wrap section">
  <h1 class="display">Le miel pur de Katiola</h1>
  <p class="lead">Exemple de paragraphe d'introduction avec un <a href="#">lien</a>, pour juger de la lisibilité du texte sur la couleur de fond.</p>
  <p class="btn-row"><a class="btn btn-large" href="#">Bouton principal</a> <a class="btn btn-large btn-tinted" href="#">Bouton secondaire</a></p>
  <div class="pro-card"><div><h2>Bloc professionnels</h2><p>Couleur principale en fond, bouton de la couleur secondaire.</p></div><a class="btn btn-large" href="#">Demander un devis</a></div>
  <p class="mt"><span class="status status-delivered">Pastille</span> <span class="preview-swatch" data-color="accent">Accent</span></p>
  <dl class="preview-values">
    <?php foreach ($values as $k => $v): ?><div><dt><?= e($k) ?></dt><dd><code><?= e($v) ?></code></dd></div><?php endforeach; ?>
  </dl>
</main>
</body>
</html>
