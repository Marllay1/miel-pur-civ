<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\AccountService;
use App\Services\Catalog;

final class ShopController
{
    public function index(Request $r): Response
    {
        $tri = $r->str('tri');
        $format = $r->str('format');
        $filters = [
            'q' => mb_substr($r->str('q'), 0, 80),
            'categorie' => preg_match('/^[a-z0-9-]{1,140}$/', $r->str('categorie')) ? $r->str('categorie') : '',
            'format' => isset(Catalog::FORMATS[$format]) ? $format : '',
            'prix_min' => ($v = $r->int('prix_min')) !== null && $v >= 0 ? $v : null,
            'prix_max' => ($v = $r->int('prix_max')) !== null && $v >= 0 ? $v : null,
            'tri' => isset(Catalog::SORTS[$tri]) ? $tri : 'pertinence',
            'en_stock' => $r->str('en_stock') === '1',
        ];
        $result = Catalog::search($filters, max(1, (int) $r->int('page', 1)), 12);
        $categories = Catalog::categories();
        $current = null;
        foreach ($categories as $c) {
            if ($c['slug'] === $filters['categorie']) {
                $current = $c;
            }
        }
        if ($filters['categorie'] !== '' && $current === null) {
            throw new HttpException(404, 'Cette catégorie n\'existe pas.');
        }
        $query = array_filter([
            'q' => $filters['q'], 'categorie' => $filters['categorie'], 'format' => $filters['format'],
            'prix_min' => $filters['prix_min'], 'prix_max' => $filters['prix_max'],
            'tri' => $filters['tri'] !== 'pertinence' ? $filters['tri'] : null, 'en_stock' => $filters['en_stock'] ? '1' : null,
        ], static fn ($v) => $v !== null && $v !== '');
        $filtered = array_diff_key($query, ['categorie' => 1]) !== [];
        return view('shop/index', [
            'result' => $result,
            'filters' => $filters,
            'query' => $query,
            'categories' => $categories,
            'currentCategory' => $current,
            'formats' => Catalog::availableFormats(),
            'pageTitle' => $current ? $current['name'] : ($filters['q'] !== '' ? 'Recherche « ' . $filters['q'] . ' »' : 'Boutique'),
            'metaDescription' => $current ? excerpt($current['description'], 158) : 'Miel et cire d\'abeille de Katiola : découvrez le catalogue, commandez en ligne et payez à la livraison.',
            'canonical' => $current ? '/boutique?categorie=' . $current['slug'] : '/boutique',
            'noindex' => $filtered || $result['page'] > 1,
        ]);
    }

    public function show(Request $r, array $extra = []): Response
    {
        $product = Catalog::findBySlug($r->params['slug']);
        if ($product === null) {
            throw new HttpException(404, 'Ce produit n\'existe pas ou n\'est plus disponible.');
        }
        $variants = Catalog::variants((int) $product['id']);
        $selectedId = $r->int('variante');
        $selected = null;
        foreach ($variants as $v) {
            if ($selectedId !== null && (int) $v['id'] === $selectedId) {
                $selected = $v;
            }
        }
        if ($selected === null) {
            foreach ($variants as $v) {
                if ((int) $v['stock'] > 0) {
                    $selected = $v;
                    break;
                }
            }
            $selected ??= $variants[0] ?? null;
        }
        $images = Catalog::images((int) $product['id']);
        $reviews = Catalog::approvedReviews((int) $product['id']);
        $offers = [];
        foreach ($variants as $v) {
            $offers[] = [
                '@type' => 'Offer',
                'sku' => $v['sku'],
                'name' => $product['name'] . ' – ' . $v['label'],
                'price' => (string) (int) $v['price'],
                'priceCurrency' => 'XOF',
                'availability' => (int) $v['stock'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url' => abs_url('/boutique/' . $product['slug'] . '?variante=' . $v['id']),
            ];
        }
        $ld = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product['name'],
            'description' => excerpt($product['short_description'] ?: $product['description'], 300),
            'image' => array_map(static fn ($i) => abs_url($i['path']), $images),
            'category' => $product['category_name'],
            'brand' => ['@type' => 'Brand', 'name' => \App\Services\Settings::get('store_name')],
            'offers' => $offers,
        ];
        if ($reviews) {
            $ld['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1),
                'reviewCount' => count($reviews),
            ];
        }
        $crumbs = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => base_url() . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Boutique', 'item' => abs_url('/boutique')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $product['name'], 'item' => abs_url('/boutique/' . $product['slug'])],
        ]];
        return view('shop/show', $extra + [
            'product' => $product,
            'variants' => $variants,
            'selected' => $selected,
            'images' => $images,
            'reviews' => $reviews,
            'related' => Catalog::related($product, 4),
            'pageTitle' => $product['meta_title'] ?: $product['name'],
            'metaDescription' => $product['meta_description'] ?: excerpt($product['short_description'] ?: $product['description'], 158),
            'canonical' => '/boutique/' . $product['slug'],
            'ogImage' => $images[0]['path'] ?? null,
            'ogType' => 'product',
            'jsonLd' => [$ld, $crumbs],
            'reviewErrors' => $extra['reviewErrors'] ?? [],
            'reviewOld' => $extra['reviewOld'] ?? [],
        ]);
    }

    public function review(Request $r): Response
    {
        $product = Catalog::findBySlug($r->params['slug']);
        if ($product === null) {
            throw new HttpException(404);
        }
        $v = Validator::make($r->post, [
            'author_name' => 'required|max:100',
            'rating' => 'required|int|min:1|max:5',
            'comment' => 'required|min:10|max:2000',
        ], ['author_name' => 'Votre nom', 'rating' => 'La note', 'comment' => 'Votre avis']);
        $errors = $v->errors;
        if ($spam = spam_check($r)) {
            $errors['_'] = $spam;
        }
        if (!$errors && !RateLimiter::hit('review:' . $r->ip(), 5, 3600)) {
            $errors['_'] = 'Vous avez déjà envoyé plusieurs avis récemment. Réessayez plus tard.';
        }
        if ($errors) {
            $response = $this->show($r, ['reviewErrors' => $errors, 'reviewOld' => $r->post]);
            $response->status = 422;
            return $response;
        }
        $uid = Auth::id();
        DB::insert('reviews', [
            'product_id' => (int) $product['id'],
            'user_id' => $uid,
            'author_name' => $v->data['author_name'],
            'rating' => $v->data['rating'],
            'comment' => $v->data['comment'],
            'status' => 'pending',
            'verified_purchase' => $uid !== null && AccountService::hasPurchased($uid, (int) $product['id']) ? 1 : 0,
            'ip' => $r->ip(),
            'moderated_by' => null,
            'moderated_at' => null,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        Session::flash('success', 'Merci ! Votre avis sera publié après vérification par notre équipe.');
        return redirect('/boutique/' . $product['slug'] . '#avis');
    }
}
