<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ThemeService;

/** Feuille de thème générée depuis Paramètres du site → Apparence (variables CSS uniquement, valeurs contrôlées). */
final class ThemeController
{
    public function css(Request $r): Response
    {
        $versioned = isset($r->query['v']) && $r->query['v'] === ThemeService::version();
        return new Response(ThemeService::css(ThemeService::current()), 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            // Adresse versionnée : mise en cache longue ; sans version : revalidation.
            'Cache-Control' => $versioned ? 'public, max-age=31536000, immutable' : 'no-cache',
        ]);
    }
}
