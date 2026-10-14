<?php /** @var array $sections */ ?>
<h1>Paramètres du site</h1>
<p class="muted">Les informations courantes de l'entreprise se gèrent ici, sans modifier le code. Chaque valeur est enregistrée dans la base de données et appliquée automatiquement à toutes les pages concernées.</p>
<div class="site-sections">
  <?php foreach ($sections as $slug => $s): ?>
  <a class="card site-section-card" href="/admin/parametres/<?= e($slug) ?>">
    <h2><?= e($s['title']) ?></h2>
    <p class="small muted"><?= e($s['intro']) ?></p>
  </a>
  <?php endforeach; ?>
  <a class="card site-section-card" href="/admin/paiements"><h2>Moyens de paiement</h2><p class="small muted">Activation, logos, ordre, instructions de paiement ; ajout de moyens de paiement manuels.</p></a>
  <a class="card site-section-card" href="/admin/livraison"><h2>Zones de livraison</h2><p class="small muted">Zones desservies, frais, seuils de gratuité, délais indicatifs.</p></a>
  <a class="card site-section-card" href="/admin/devises"><h2>Devises d'affichage</h2><p class="small muted">Équivalents indicatifs en euro, dollar… (le paiement reste en francs CFA).</p></a>
  <a class="card site-section-card" href="/admin/carrousel"><h2>Carrousel de l'accueil</h2><p class="small muted">Diapositives sous l'en-tête de la page d'accueil.</p></a>
</div>
