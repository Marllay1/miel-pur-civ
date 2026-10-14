<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Lang;

/** Blocs de réassurance de l'accueil (table home_highlights), affichés sous le carrousel. */
final class HomeHighlights
{
    /** Icônes proposées (liste fermée : icônes du site). */
    public const ICONS = ['leaf' => 'Feuille (naturel, bio)', 'shield' => 'Bouclier (qualité, sécurité)', 'truck' => 'Camion (livraison)', 'lock' => 'Cadenas (paiement sécurisé)',
        'chat' => 'Bulle (conseil, écoute)', 'phone' => 'Téléphone', 'box' => 'Colis', 'check' => 'Coche', 'pin' => 'Lieu', 'clock' => 'Horloge'];

    public static function all(): array
    {
        return DB::all('SELECT * FROM home_highlights ORDER BY sort_order, id');
    }

    /** Blocs visibles, localisés (texte anglais s'il est renseigné). */
    public static function visible(): array
    {
        $out = [];
        foreach (DB::all('SELECT * FROM home_highlights WHERE is_active = 1 ORDER BY sort_order, id') as $h) {
            $en = Lang::get() === 'en';
            $h['title'] = $en && (string) $h['title_en'] !== '' ? (string) $h['title_en'] : (string) $h['title'];
            $h['body'] = $en && (string) $h['body_en'] !== '' ? (string) $h['body_en'] : (string) $h['body'];
            $out[] = $h;
        }
        return $out;
    }

    public static function renumber(): void
    {
        foreach (self::all() as $i => $h) {
            DB::update('home_highlights', ['sort_order' => $i], 'id = ?', [(int) $h['id']]);
        }
    }
}
