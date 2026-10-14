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
        ];
        // Logo officiel uniquement (aucun logo de substitution déclaré aux moteurs de recherche).
        if (brand_logo_url() !== null) {
            $org['logo'] = base_url() . strtok((string) brand_logo_url(), '?');
        }
        if (\App\Services\SiteConfig::address() !== '' && Settings::get('store_city') !== '') {
            $org['address'] = array_filter(['@type' => 'PostalAddress', 'streetAddress' => Settings::get('store_address'), 'addressLocality' => Settings::get('store_city'), 'addressCountry' => 'CI']);
        }
        if (($phone = \App\Services\SiteConfig::mainPhone()) !== '') {
            $org['telephone'] = $phone;
        }
        $sameAs = array_values(array_map(static fn ($l) => $l[0], array_filter(\App\Services\SiteConfig::socialLinks(), static fn ($k) => $k !== 'whatsapp', ARRAY_FILTER_USE_KEY)));
        if ($sameAs) {
            $org['sameAs'] = $sameAs;
        }
        return view('home', [
            'featured' => Catalog::featured(4),
            'categories' => Catalog::categories(),
            'slides' => \App\Services\Slides::live(),
            'faqs' => \App\Services\I18n::localize('faq', DB::all('SELECT * FROM faqs WHERE is_active = 1 ORDER BY sort_order, id LIMIT 3')),
            'canonical' => '/',
            'pageTitle' => '',
            'jsonLd' => [$org, ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => Settings::get('store_name'), 'url' => base_url() . '/',
                'potentialAction' => ['@type' => 'SearchAction', 'target' => base_url() . '/boutique?q={search_term_string}', 'query-input' => 'required name=search_term_string']]],
            'bodyClass' => 'page-home',
        ]);
    }
}
