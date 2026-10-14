<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Apparence administrable : couleurs et polices (Paramètres du site → Apparence), traduites en variables CSS
 * servies par /theme.css. Seules des valeurs contrôlées sont acceptées (couleurs #RRVVBB, polices d'une liste fermée) :
 * aucun CSS libre ne peut être injecté. Les contrastes sont vérifiés avant l'enregistrement (WCAG 2.1).
 */
final class ThemeService
{
    public const KEYS = ['color_primary', 'color_secondary', 'color_accent', 'color_background', 'font_heading', 'font_body'];

    /** Polices proposées : clé => [libellé, pile CSS]. Young Serif et Figtree sont hébergées par le site. */
    public const FONTS = [
        'young-serif' => ['Young Serif (titres de la marque)', '"Young Serif", Georgia, "Times New Roman", serif'],
        'figtree' => ['Figtree (texte de la marque)', '"Figtree", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, system-ui, sans-serif'],
        'georgia' => ['Georgia (classique)', 'Georgia, "Times New Roman", serif'],
        'palatino' => ['Palatino (élégante)', '"Palatino Linotype", Palatino, "Book Antiqua", Georgia, serif'],
        'system' => ['Police du système', 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'],
        'verdana' => ['Verdana (très lisible)', 'Verdana, Geneva, Tahoma, sans-serif'],
    ];

    private const TEXT = '#1D1A16';      // couleur du texte courant (thème clair)
    private const ON_GOLD = '#1A1206';   // texte posé sur la couleur secondaire

    /** @return array<string,string> valeurs enregistrées */
    public static function current(): array
    {
        $out = [];
        foreach (self::KEYS as $k) {
            $out[$k] = Settings::get($k);
        }
        return $out;
    }

    /** Valeurs proposées (aperçu) : seules les valeurs valides sont reprises, les autres gardent la valeur enregistrée. */
    public static function sanitize(array $input): array
    {
        $values = self::current();
        foreach (['color_primary', 'color_secondary', 'color_accent', 'color_background'] as $k) {
            if (isset($input[$k]) && is_string($input[$k]) && preg_match('/^#[0-9A-Fa-f]{6}$/', $input[$k])) {
                $values[$k] = strtoupper($input[$k]);
            }
        }
        foreach (['font_heading', 'font_body'] as $k) {
            if (isset($input[$k]) && is_string($input[$k]) && isset(self::FONTS[$input[$k]])) {
                $values[$k] = $input[$k];
            }
        }
        return $values;
    }

    /** Variables CSS du thème clair (le thème sombre garde sa palette, conçue pour la lisibilité de nuit). */
    public static function css(array $v): string
    {
        $heading = self::FONTS[$v['font_heading']][1] ?? self::FONTS['young-serif'][1];
        $body = self::FONTS[$v['font_body']][1] ?? self::FONTS['figtree'][1];
        return "/* Thème généré à partir des Paramètres du site → Apparence. */\n"
            . ":root {\n"
            . "  --tint: {$v['color_primary']};\n  --tint-text: {$v['color_primary']};\n  --green: {$v['color_primary']};\n"
            . "  --tint-soft: color-mix(in srgb, {$v['color_primary']} 12%, #FFFFFF);\n  --green-soft: color-mix(in srgb, {$v['color_primary']} 12%, #FFFFFF);\n"
            . "  --honey-2: {$v['color_secondary']};\n  --orange: {$v['color_accent']};\n"
            . "  --bg: {$v['color_background']};\n  --bg-2: color-mix(in srgb, {$v['color_background']} 94%, #6B5A3A);\n  --hero-bg: {$v['color_background']};\n"
            . "  --font-display: {$heading};\n  --font: {$body};\n"
            . "}\n"
            . ".pro-card { background: {$v['color_primary']}; }\n"
            . ".pro-card .btn { --b-bg: {$v['color_secondary']}; }\n";
    }

    /** Luminance relative WCAG d'une couleur #RRVVBB. */
    public static function luminance(string $hex): float
    {
        $c = array_map(static function (string $h): float {
            $v = hexdec($h) / 255;
            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * @return array<string,string> problèmes de lisibilité (vide = combinaison acceptée).
     * La couleur d'accent n'est utilisée qu'en décoration (jamais pour du texte) : elle n'est pas contrôlée.
     */
    public static function contrastProblems(array $v): array
    {
        $problems = [];
        $checks = [
            ['color_primary', $v['color_primary'], '#FFFFFF', 4.5, 'texte blanc des boutons sur la couleur principale'],
            ['color_primary', $v['color_primary'], $v['color_background'], 4.5, 'liens et titres (couleur principale) sur le fond'],
            ['color_background', self::TEXT, $v['color_background'], 4.5, 'texte courant sur la couleur de fond'],
            ['color_secondary', self::ON_GOLD, $v['color_secondary'], 4.5, 'texte foncé sur la couleur secondaire'],
        ];
        foreach ($checks as [$key, $fg, $bg, $min, $what]) {
            $ratio = self::contrast($fg, $bg);
            if ($ratio < $min && !isset($problems[$key])) {
                $problems[$key] = sprintf('Contraste insuffisant pour le %s : %.1f:1 (minimum %.1f:1).', $what, $ratio, $min);
            }
        }
        return $problems;
    }

    /** Version du thème (cache des navigateurs invalidé à chaque modification). */
    public static function version(): string
    {
        return substr(hash('sha256', implode('|', self::current())), 0, 12);
    }
}
