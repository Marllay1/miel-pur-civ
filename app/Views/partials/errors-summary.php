<?php
/** @var array $errors */
if (empty($errors)) {
    return;
}
?>
<div class="error-summary" role="alert" tabindex="-1" data-error-summary>
  <p><strong>Veuillez corriger les points suivants :</strong></p>
  <ul>
    <?php foreach ($errors as $field => $message): ?>
    <li><?php if (is_string($field)): ?><a href="#f-<?= e($field) ?>"><?= e($message) ?></a><?php else: ?><?= e($message) ?><?php endif; ?></li>
    <?php endforeach; ?>
  </ul>
</div>
