<?php /** @var int $status @var string $title @var string $message */ ?>
<div class="wrap narrow error-page">
  <span class="hex-outline hex-outline-lg" aria-hidden="true"><?= (int) $status ?></span>
  <h1><?= e($title) ?></h1>
  <p class="lead"><?= e($message !== '' ? $message : match ($status) {
      404 => 'La page demandée n\'existe pas ou a été déplacée.',
      403 => 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.',
      419 => 'Le formulaire a expiré. Rechargez la page et réessayez.',
      default => 'Une erreur est survenue. Réessayez dans quelques instants.',
  }) ?></p>
  <p><a class="btn btn-primary" href="/">Retour à l'accueil</a> <a class="btn btn-ghost" href="/boutique">Voir la boutique</a></p>
</div>
