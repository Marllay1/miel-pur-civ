<?php
$current = $current ?? '';
$items = ['/compte' => 'Tableau de bord', '/compte/commandes' => 'Mes commandes', '/compte/adresses' => 'Mes adresses', '/compte/profil' => 'Mon profil', '/compte/mot-de-passe' => 'Mot de passe'];
?>
<nav class="account-nav" aria-label="Espace client">
  <ul>
    <?php foreach ($items as $href => $label): ?>
    <li><a href="<?= $href ?>"<?= $current === $href ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
    <?php endforeach; ?>
  </ul>
  <form action="/compte/deconnexion" method="post"><?= csrf_field() ?><button class="link-btn" type="submit">Se déconnecter</button></form>
</nav>
