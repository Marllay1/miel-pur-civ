<?php /** @var array $groups */ ?>
<div class="wrap narrow page-head"><h1><?= e(t('faq.title')) ?></h1><p class="lead"><?= e(t('faq.lead')) ?></p></div>
<div class="wrap narrow">
  <?php if (!$groups): ?><p class="muted"><?= e(t('faq.none')) ?></p><?php endif; ?>
  <?php foreach ($groups as $category => $faqs): ?>
  <section aria-labelledby="faq-<?= e(slugify($category)) ?>">
    <h2 class="group-title" id="faq-<?= e(slugify($category)) ?>"><?= e($category) ?></h2>
    <div class="disclosure">
      <?php foreach ($faqs as $f): ?>
      <details>
        <summary><?= e($f['question']) ?><?= icon('chevron') ?></summary>
        <div class="prose"><?= rich($f['answer']) ?></div>
      </details>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>
  <div class="notice mt"><?= icon('chat') ?><p><?= e(t('faq.more')) ?> <a href="/contact"><?= e(t('faq.write_us')) ?></a></p></div>
</div>
<div class="section"></div>
