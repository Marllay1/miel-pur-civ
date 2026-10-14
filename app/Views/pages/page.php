<?php /** @var array $page */ ?>
<div class="page-head">
  <div class="wrap narrow">
    <h1><?= e($page['title']) ?></h1>
    <?php if ((int) $page['is_legal'] === 1): ?><p class="muted small">Dernière mise à jour : <?= e(date_fr($page['updated_at'], false)) ?></p><?php endif; ?>
  </div>
</div>
<div class="wrap narrow">
  <?php if ((int) $page['is_legal'] === 1 && (int) $page['is_validated'] === 0): ?>
  <p class="notice notice-amber" role="note"><strong>Document provisoire</strong> : ce texte est un modèle en attente de validation juridique. Il ne doit pas être considéré comme définitif.</p>
  <?php endif; ?>
  <div class="prose"><?= rich($page['body']) ?></div>
</div>
