<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Lang;

/**
 * Photothèque de l'entreprise (table media) : photos issues des ressources de l'entreprise (page Facebook officielle,
 * photos fournies), choisies dans l'administration pour les galeries de l'accueil et de la page À propos et pour l'histoire.
 */
final class Media
{
    public static function all(): array
    {
        return DB::all('SELECT * FROM media ORDER BY sort_order, id');
    }

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM media WHERE id = ?', [$id]);
    }

    private static function localize(array $rows): array
    {
        foreach ($rows as &$m) {
            $m['alt_text'] = Lang::get() === 'en' && (string) $m['alt_en'] !== '' ? (string) $m['alt_en'] : (string) $m['alt'];
        }
        unset($m);
        return $rows;
    }

    public static function homeGallery(): array
    {
        return self::localize(DB::all('SELECT * FROM media WHERE in_home_gallery = 1 ORDER BY sort_order, id'));
    }

    public static function aboutGallery(): array
    {
        return self::localize(DB::all('SELECT * FROM media WHERE in_about_gallery = 1 ORDER BY sort_order, id'));
    }

    public static function story(): ?array
    {
        $rows = self::localize(DB::all('SELECT * FROM media WHERE is_story = 1 ORDER BY sort_order, id LIMIT 1'));
        return $rows[0] ?? null;
    }

    /** Balise <img> d'une photo de la photothèque (dimensions connues : pas de décalage de mise en page). */
    public static function img(array $m, string $class = '', bool $eager = false): string
    {
        $size = $m['width'] && $m['height'] ? ' width="' . (int) $m['width'] . '" height="' . (int) $m['height'] . '"' : '';
        return '<img src="' . e((string) $m['path']) . '" alt="' . e((string) ($m['alt_text'] ?? $m['alt'])) . '"' . $size
            . ($class !== '' ? ' class="' . e($class) . '"' : '') . ($eager ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async">';
    }

    public static function renumber(): void
    {
        foreach (DB::all('SELECT id FROM media ORDER BY sort_order, id') as $i => $row) {
            DB::update('media', ['sort_order' => $i], 'id = ?', [(int) $row['id']]);
        }
    }
}
