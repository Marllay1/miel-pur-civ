<?php
/** @var int $page @var int $pages @var string $base @var array $query */
if ($pages <= 1) {
    return;
}
$link = static fn (int $p) => url($base, array_merge($query, ['page' => $p > 1 ? $p : null]));
?>
<nav class="pagination" aria-label="<?= e(t('pagination.label')) ?>">
  <?php if ($page > 1): ?><a href="<?= e($link($page - 1)) ?>" rel="prev" aria-label="<?= e(t('pagination.prev')) ?>"><?= icon('chevron-left', 'icon icon-sm') ?></a><?php endif; ?>
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <?php if ($i === $page): ?><span aria-current="page"><?= $i ?></span><?php else: ?><a href="<?= e($link($i)) ?>"><?= $i ?></a><?php endif; ?>
  <?php endfor; ?>
  <?php if ($page < $pages): ?><a href="<?= e($link($page + 1)) ?>" rel="next" aria-label="<?= e(t('pagination.next')) ?>"><?= icon('chevron', 'icon icon-sm') ?></a><?php endif; ?>
</nav>
