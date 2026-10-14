<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Langue courante (fr par défaut, en sous le préfixe /en) et chaînes d'interface (app/lang/*.php).
 * Une clé inconnue est renvoyée telle quelle : l'administration passe directement du texte français.
 */
final class Lang
{
    public const SUPPORTED = ['fr', 'en'];
    public const DEFAULT = 'fr';

    private static string $locale = self::DEFAULT;
    private static array $strings = [];

    public static function set(string $locale): void
    {
        self::$locale = in_array($locale, self::SUPPORTED, true) ? $locale : self::DEFAULT;
    }

    public static function get(): string
    {
        return self::$locale;
    }

    public static function isDefault(): bool
    {
        return self::$locale === self::DEFAULT;
    }

    private static function table(string $locale): array
    {
        if (!isset(self::$strings[$locale])) {
            $file = APP_PATH . '/lang/' . $locale . '.php';
            self::$strings[$locale] = is_file($file) ? require $file : [];
        }
        return self::$strings[$locale];
    }

    public static function has(string $key, ?string $locale = null): bool
    {
        return isset(self::table($locale ?? self::$locale)[$key]);
    }

    public static function t(string $key, array $params = []): string
    {
        $text = self::table(self::$locale)[$key] ?? self::table(self::DEFAULT)[$key] ?? $key;
        if (is_array($text)) {
            $count = (int) ($params['count'] ?? 0);
            $text = $count > 1 ? $text[1] : $text[0];
        }
        foreach ($params as $name => $value) {
            $text = str_replace(':' . $name, (string) $value, $text);
        }
        return $text;
    }

    /** Préfixe de chemin pour la langue courante ('' en français, '/en' en anglais). */
    public static function prefix(?string $locale = null): string
    {
        $locale ??= self::$locale;
        return $locale === self::DEFAULT ? '' : '/' . $locale;
    }

    /** Chemin interne localisé : /boutique → /en/boutique. */
    public static function path(string $path, ?string $locale = null): string
    {
        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || self::isUnlocalized($path)) {
            return $path;
        }
        $prefix = self::prefix($locale);
        if ($prefix === '') {
            return $path;
        }
        return $path === '/' ? $prefix : $prefix . $path;
    }

    /** Chemins jamais préfixés (fichiers, administration, intégrations). */
    public static function isUnlocalized(string $path): bool
    {
        return (bool) preg_match('#^/(assets|uploads|admin|webhooks|preferences|langue|theme\.css|sitemap\.xml|robots\.txt)(/|$|\?)#', $path)
            || preg_match('#^/en(/|$|\?)#', $path);
    }

    /**
     * Préfixe les liens internes (href, action, Location) d'une page générée en anglais.
     * Les vues écrivent des chemins français canoniques ; ce filtre unique évite d'oublier un lien.
     */
    public static function localizeHtml(string $html): string
    {
        if (self::isDefault()) {
            return $html;
        }
        $prefix = self::prefix();
        return preg_replace_callback(
            '#\b(href|action|formaction|data-[a-z-]*url)="(/[^"]*)"#',
            static fn ($m) => $m[1] . '="' . (self::isUnlocalized(html_entity_decode($m[2])) || str_starts_with($m[2], '//') ? $m[2] : ($m[2] === '/' ? $prefix : $prefix . $m[2])) . '"',
            $html
        ) ?? $html;
    }
}
