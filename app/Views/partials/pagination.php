<?php
/** @var int $page @var int $pages @var string $base @var array $query */
if ($pages <= 1) {
    return;
}
$link = static fn (int $p) => url($base, array_merge($query, ['page' => $p > 1 ? $p : null]));
?>
<nav class="pagination" aria-label="Pagination">
  <?php if ($page > 1): ?><a href="<?= e($link($page - 1)) ?>" rel="prev">← Précédent</a><?php endif; ?>
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <?php if ($i === $page): ?><span aria-current="page"><?= $i ?></span><?php else: ?><a href="<?= e($link($i)) ?>"><?= $i ?></a><?php endif; ?>
  <?php endfor; ?>
  <?php if ($page < $pages): ?><a href="<?= e($link($page + 1)) ?>" rel="next">Suivant →</a><?php endif; ?>
</nav>
