<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Services\Catalog;

final class SeoController
{
    public function sitemap(Request $r): Response
    {
        $urls = [];
        foreach (['/', '/boutique', '/a-propos', '/commandes-professionnelles', '/faq', '/contact'] as $p) {
            $urls[] = ['loc' => abs_url($p), 'lastmod' => null];
        }
        foreach (DB::all('SELECT slug, updated_at FROM pages WHERE is_legal = 1') as $p) {
            $urls[] = ['loc' => abs_url('/' . $p['slug']), 'lastmod' => $p['updated_at']];
        }
        foreach (Catalog::categories() as $c) {
            if ((int) $c['product_count'] > 0) {
                $urls[] = ['loc' => abs_url('/boutique?categorie=' . $c['slug']), 'lastmod' => $c['updated_at']];
            }
        }
        $all = Catalog::search([], 1, 1000);
        foreach ($all['items'] as $p) {
            $urls[] = ['loc' => abs_url('/boutique/' . $p['slug']), 'lastmod' => $p['updated_at']];
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= '  <url><loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>'
                . ($u['lastmod'] ? '<lastmod>' . gmdate('Y-m-d', strtotime($u['lastmod'])) . '</lastmod>' : '') . "</url>\n";
        }
        return Response::text($xml . "</urlset>\n", 200, 'application/xml');
    }

    public function robots(Request $r): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /compte',
            'Disallow: /panier',
            'Disallow: /commande$',
            'Disallow: /commande/',
            'Disallow: /paiement',
            'Disallow: /suivi-commande',
            'Disallow: /webhooks',
            '',
            'Sitemap: ' . abs_url('/sitemap.xml'),
        ];
        return Response::text(implode("\n", $lines) . "\n");
    }
}
