<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Lang;

/** Diapositives du carrousel de l'accueil (table home_slides, Administration → Carrousel de l'accueil). */
final class Slides
{
    /** @return array<int, array> toutes les diapositives, dans l'ordre */
    public static function all(): array
    {
        return DB::all('SELECT * FROM home_slides ORDER BY sort_order, id');
    }

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM home_slides WHERE id = ?', [$id]);
    }

    /** Publiée maintenant : active et dans sa période de publication éventuelle (dates UTC). */
    public static function isLive(array $s, ?string $now = null): bool
    {
        $now ??= gmdate('Y-m-d H:i:s');
        return (int) $s['is_active'] === 1
            && ($s['starts_at'] === null || $s['starts_at'] === '' || $s['starts_at'] <= $now)
            && ($s['ends_at'] === null || $s['ends_at'] === '' || $s['ends_at'] >= $now);
    }

    /** @return array<int, array> diapositives publiées, champs dans la langue courante */
    public static function live(): array
    {
        return array_values(array_map([self::class, 'localize'], array_filter(self::all(), [self::class, 'isLive'])));
    }

    public static function localize(array $s): array
    {
        if (Lang::get() === 'en') {
            foreach (['title', 'body', 'button_label', 'image_alt'] as $f) {
                if ((string) ($s[$f . '_en'] ?? '') !== '') {
                    $s[$f] = $s[$f . '_en'];
                }
            }
        }
        return $s;
    }

    /** Lien de destination autorisé : chemin interne (/…) ou adresse https. */
    public static function validLink(string $url): bool
    {
        return $url === '' || (bool) preg_match('#^/(?!/)[A-Za-z0-9/_\-.?=&%\#]*$#', $url) || ((bool) preg_match('#^https://#i', $url) && filter_var($url, FILTER_VALIDATE_URL) !== false);
    }

    /** Remet les positions à 0, 1, 2… dans l'ordre actuel. */
    public static function renumber(): void
    {
        foreach (self::all() as $i => $s) {
            DB::update('home_slides', ['sort_order' => $i], 'id = ?', [(int) $s['id']]);
        }
    }
}
