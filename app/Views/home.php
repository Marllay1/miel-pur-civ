<?php
use App\Core\View;
use App\Services\Settings;
?>
<section class="hero">
  <div class="wrap hero-grid">
    <div class="hero-copy">
      <p class="eyebrow"><span class="hex-dot" aria-hidden="true"></span> <?= rich_inline(Settings::get('store_city')) ?> · Côte d'Ivoire</p>
      <h1><?= e(Settings::get('home_hero_title')) ?></h1>
      <p class="lead"><?= rich_inline(Settings::get('home_hero_text')) ?></p>
      <div class="hero-actions">
        <a class="btn btn-primary" href="/boutique">Découvrir la boutique</a>
        <a class="btn btn-ghost" href="/commandes-professionnelles">Espace professionnels</a>
      </div>
      <ul class="hex-list hero-points">
        <?php if (Settings::bool('cod_enabled')): ?><li>Paiement à la livraison</li><?php endif; ?>
        <li>Commande possible sans créer de compte</li>
        <li>Frais de livraison affichés avant validation</li>
      </ul>
    </div>
    <div class="hero-visual">
      <div class="hex-frame">
        <img src="<?= e(asset('img/demo/hero.svg')) ?>" alt="Illustration de démonstration : rayon de miel et pot de miel" width="560" height="560">
      </div>
      <span class="hex-cell hex-cell-a" aria-hidden="true"></span>
      <span class="hex-cell hex-cell-b" aria-hidden="true"></span>
      <span class="hex-cell hex-cell-c" aria-hidden="true"></span>
      <p class="visual-note">Illustration provisoire — à remplacer par une photo des ruchers.</p>
    </div>
  </div>
</section>

<?php if ($featured): ?>
<section class="section" aria-labelledby="h-featured">
  <div class="wrap">
    <div class="section-head">
      <h2 id="h-featured">Nos produits</h2>
      <a class="link-arrow" href="/boutique">Tout le catalogue</a>
    </div>
    <div class="product-grid">
      <?php foreach ($featured as $product): ?><?= View::partial('product-card', ['product' => $product]) ?><?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($categories): ?>
<section class="section section-wax" aria-labelledby="h-cats">
  <div class="wrap">
    <h2 id="h-cats" class="section-title">Par catégorie</h2>
    <ul class="hex-tiles">
      <?php foreach ($categories as $cat): ?>
      <li>
        <a class="hex-tile" href="/boutique?categorie=<?= e($cat['slug']) ?>">
          <span class="hex-tile-name"><?= e($cat['name']) ?></span>
          <span class="hex-tile-count"><?= (int) $cat['product_count'] ?> produit<?= (int) $cat['product_count'] > 1 ? 's' : '' ?></span>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<section class="section" aria-labelledby="h-story">
  <div class="wrap story">
    <div class="story-mark" aria-hidden="true">
      <svg viewBox="0 0 200 230"><g class="comb"><path d="M50 15 85 35v40L50 95 15 75V35z"/><path d="M120 15l35 20v40l-35 20-35-20V35z"/><path d="M85 75l35 20v40l-35 20-35-20V95z"/><path d="M155 75l35 20v40l-35 20-35-20V95z"/><path d="M120 135l35 20v40l-35 20-35-20v-40z"/></g><path class="comb-fill" d="M85 75l35 20v40l-35 20-35-20V95z"/></svg>
    </div>
    <div class="story-text">
      <h2 id="h-story"><?= e(Settings::get('home_story_title')) ?></h2>
      <div class="prose"><?= rich(Settings::get('home_story_text')) ?></div>
      <a class="link-arrow" href="/a-propos">En savoir plus sur l'entreprise</a>
    </div>
  </div>
</section>

<section class="section section-steps" aria-labelledby="h-steps">
  <div class="wrap">
    <h2 id="h-steps" class="section-title">Commander, simplement</h2>
    <ol class="hex-steps">
      <li><span class="hex-num" aria-hidden="true">1</span><strong>Choisissez</strong><span>vos produits et le format adapté.</span></li>
      <li><span class="hex-num" aria-hidden="true">2</span><strong>Indiquez</strong><span>vos coordonnées et votre zone de livraison.</span></li>
      <li><span class="hex-num" aria-hidden="true">3</span><strong>Confirmez</strong><span>la commande, avec les frais affichés à l'avance.</span></li>
      <li><span class="hex-num" aria-hidden="true">4</span><strong>Recevez</strong><span><?= Settings::bool('cod_enabled') ? 'et payez à la livraison.' : 'votre commande.' ?></span></li>
    </ol>
  </div>
</section>

<section class="section section-dark" aria-labelledby="h-pro">
  <div class="wrap pro-cta">
    <div>
      <p class="eyebrow eyebrow-light">Professionnels</p>
      <h2 id="h-pro">Du miel en volume pour votre activité</h2>
      <p><?= rich_inline(Settings::get('home_pro_text')) ?></p>
    </div>
    <a class="btn btn-primary" href="/commandes-professionnelles">Demander un devis</a>
  </div>
</section>

<?php if ($faqs): ?>
<section class="section" aria-labelledby="h-faq">
  <div class="wrap narrow">
    <div class="section-head">
      <h2 id="h-faq">Questions fréquentes</h2>
      <a class="link-arrow" href="/faq">Toutes les questions</a>
    </div>
    <div class="faq-list">
      <?php foreach ($faqs as $f): ?>
      <details class="faq-item">
        <summary><?= e($f['question']) ?></summary>
        <div class="prose"><?= rich($f['answer']) ?></div>
      </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
