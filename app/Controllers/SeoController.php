<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Services\Catalog;

final class SeoController
{
    /** Plan du site bilingue : chaque URL française et anglaise, avec ses alternatives hreflang. */
    public function sitemap(Request $r): Response
    {
        $paths = [];
        foreach (['/', '/boutique', '/a-propos', '/commandes-professionnelles', '/faq', '/contact'] as $p) {
            $paths[$p] = null;
        }
        foreach (DB::all('SELECT slug, updated_at FROM pages WHERE is_legal = 1') as $p) {
            $paths['/' . $p['slug']] = $p['updated_at'];
        }
        $previous = Lang::get();
        Lang::set('fr');
        foreach (Catalog::categories() as $c) {
            if ((int) $c['product_count'] > 0) {
                $paths['/boutique?categorie=' . $c['slug']] = $c['updated_at'];
            }
        }
        foreach (Catalog::search([], 1, 1000)['items'] as $p) {
            $paths['/boutique/' . $p['slug']] = $p['updated_at'];
        }
        Lang::set($previous);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        foreach ($paths as $path => $lastmod) {
            foreach (Lang::SUPPORTED as $locale) {
                $xml .= '  <url><loc>' . htmlspecialchars(abs_url($path, $locale), ENT_XML1) . '</loc>';
                foreach (Lang::SUPPORTED as $alt) {
                    $xml .= '<xhtml:link rel="alternate" hreflang="' . $alt . '" href="' . htmlspecialchars(abs_url($path, $alt), ENT_XML1) . '"/>';
                }
                $xml .= ($lastmod ? '<lastmod>' . gmdate('Y-m-d', strtotime($lastmod)) . '</lastmod>' : '') . "</url>\n";
            }
        }
        return Response::text($xml . "</urlset>\n", 200, 'application/xml');
    }

    public function robots(Request $r): Response
    {
        $lines = ['User-agent: *'];
        foreach (['', '/en'] as $prefix) {
            foreach (['/compte', '/panier', '/commande$', '/commande/', '/paiement', '/suivi-commande', '/menu'] as $p) {
                $lines[] = 'Disallow: ' . $prefix . $p;
            }
        }
        array_push($lines, 'Disallow: /admin', 'Disallow: /webhooks', 'Disallow: /preferences', '', 'Sitemap: ' . base_url() . '/sitemap.xml');
        return Response::text(implode("\n", $lines) . "\n");
    }
}
