<?php
/** @var array $errors */
if (empty($errors)) {
    return;
}
?>
<div class="error-summary" role="alert" tabindex="-1" data-error-summary>
  <strong><?= e(t('form.fix_errors')) ?></strong>
  <ul>
    <?php foreach ($errors as $field => $message): ?>
    <li><?php if (is_string($field) && $field !== '_' && !preg_match('/^e[0-9]+$/', $field)): ?><a href="#f-<?= e($field) ?>"><?= e($message) ?></a><?php else: ?><?= e($message) ?><?php endif; ?></li>
    <?php endforeach; ?>
  </ul>
</div>
