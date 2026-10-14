<?php /** @var array $groups */ ?>
<div class="page-head"><div class="wrap narrow"><h1>Questions fréquentes</h1></div></div>
<div class="wrap narrow">
  <?php if (!$groups): ?><p class="muted">Aucune question pour le moment.</p><?php endif; ?>
  <?php foreach ($groups as $category => $faqs): ?>
  <section class="faq-group" aria-labelledby="faq-<?= e(slugify($category)) ?>">
    <h2 id="faq-<?= e(slugify($category)) ?>"><?= e($category) ?></h2>
    <div class="faq-list">
      <?php foreach ($faqs as $f): ?>
      <details class="faq-item">
        <summary><?= e($f['question']) ?></summary>
        <div class="prose"><?= rich($f['answer']) ?></div>
      </details>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>
  <p class="notice">Vous ne trouvez pas votre réponse ? <a href="/contact">Écrivez-nous</a>.</p>
</div>
