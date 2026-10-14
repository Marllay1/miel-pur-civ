<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Services\Catalog;
use App\Services\Settings;

final class HomeController
{
    public function index(Request $r): Response
    {
        $org = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => Settings::get('store_name'),
            'url' => base_url() . '/',
            'logo' => abs_url('/assets/img/favicon.svg'),
        ];
        $address = Settings::get('store_city');
        if (!Settings::isPlaceholder('store_city')) {
            $org['address'] = ['@type' => 'PostalAddress', 'addressLocality' => $address, 'addressCountry' => 'CI'];
        }
        if (!Settings::isPlaceholder('store_phone')) {
            $org['telephone'] = Settings::get('store_phone');
        }
        $sameAs = array_values(array_filter([Settings::get('social_facebook'), Settings::get('social_instagram'), Settings::get('social_tiktok')]));
        if ($sameAs) {
            $org['sameAs'] = $sameAs;
        }
        return view('home', [
            'featured' => Catalog::featured(4),
            'categories' => Catalog::categories(),
            'faqs' => \App\Services\I18n::localize('faq', DB::all('SELECT * FROM faqs WHERE is_active = 1 ORDER BY sort_order, id LIMIT 3')),
            'canonical' => '/',
            'pageTitle' => '',
            'jsonLd' => [$org, ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => Settings::get('store_name'), 'url' => base_url() . '/',
                'potentialAction' => ['@type' => 'SearchAction', 'target' => base_url() . '/boutique?q={search_term_string}', 'query-input' => 'required name=search_term_string']]],
            'bodyClass' => 'page-home',
        ]);
    }
}
