<?php /** @var array $slides @var bool $all */ ?>
<div class="wrap admin-preview-head">
  <p class="notice notice-amber"><strong>Aperçu</strong> — <?= $all ? 'toutes les diapositives, y compris désactivées' : 'diapositives actuellement publiées' ?>. Les boutons sont neutralisés. <a href="/admin/carrousel">Retour à l'administration</a></p>
</div>
<?php if ($slides): ?>
<?= \App\Core\View::partial('carousel', ['slides' => $slides, 'preview' => true]) ?>
<?php else: ?>
<div class="wrap"><p class="notice">Aucune diapositive publiée : l'accueil affiche son visuel d'ouverture.</p></div>
<?php endif; ?>
