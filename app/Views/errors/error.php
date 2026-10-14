<?php /** @var int $status @var string $title @var string $message @var string $kind */ $kind = (string) ($kind ?? $status); ?>
<div class="wrap narrow error-page">
  <p class="error-code" aria-hidden="true"><?= (int) $status ?></p>
  <h1><?= e($title) ?></h1>
  <p class="lead"><?= e($message !== '' ? $message : (\App\Core\Lang::has('error.text.' . $kind) ? t('error.text.' . $kind) : t('error.text.default'))) ?></p>
  <div class="btn-row">
    <?php if ($kind === 'admin_denied'): ?><a class="btn" href="/compte"><?= e(t('nav.account')) ?></a> <a class="btn btn-gray" href="/"><?= e(t('error.home')) ?></a>
    <?php else: ?><a class="btn" href="/"><?= e(t('error.home')) ?></a> <a class="btn btn-gray" href="/boutique"><?= e(t('error.shop')) ?></a><?php endif; ?>
  </div>
</div>
