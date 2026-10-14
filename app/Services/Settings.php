<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Réglages administrables (table settings) avec valeurs par défaut de app/config/defaults.php.
 */
final class Settings
{
    private static ?array $definitions = null;
    private static ?array $cache = null;

    public static function definitions(): array
    {
        return self::$definitions ??= require APP_PATH . '/config/defaults.php';
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    public static function get(string $key): string
    {
        $defs = self::definitions();
        if (self::$cache === null) {
            self::$cache = [];
            foreach (DB::all('SELECT name, value FROM settings') as $row) {
                self::$cache[$row['name']] = (string) $row['value'];
            }
        }
        return self::$cache[$key] ?? (string) ($defs[$key]['default'] ?? '');
    }

    /** Valeur dans la langue courante : clé « xxx_en » si elle existe et n'est pas vide, sinon la valeur française. */
    public static function localized(string $key): string
    {
        $locale = \App\Core\Lang::get();
        if ($locale !== \App\Core\Lang::DEFAULT && isset(self::definitions()[$key . '_' . $locale])) {
            $v = self::get($key . '_' . $locale);
            if ($v !== '') {
                return $v;
            }
        }
        return self::get($key);
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    public static function bool(string $key): bool
    {
        return self::get($key) === '1';
    }

    /** @throws \InvalidArgumentException si la clé est inconnue ou la valeur invalide */
    public static function set(string $key, string $value): void
    {
        $defs = self::definitions();
        if (!isset($defs[$key])) {
            throw new \InvalidArgumentException("Réglage inconnu : $key");
        }
        $def = $defs[$key];
        $value = trim($value);
        $type = $def['type'] ?? 'string';
        if ($type === 'bool') {
            $value = in_array($value, ['1', 'on', 'true'], true) ? '1' : '0';
        } elseif ($type === 'int') {
            if (!preg_match('/^\d{1,9}$/', $value)) {
                throw new \InvalidArgumentException($def['label'] . ' : nombre entier positif attendu.');
            }
        } elseif (isset($def['options']) && !in_array($value, $def['options'], true)) {
            throw new \InvalidArgumentException($def['label'] . ' : valeur non autorisée.');
        }
        if (mb_strlen($value) > 5000) {
            throw new \InvalidArgumentException($def['label'] . ' : texte trop long.');
        }
        if ($value !== '') {
            $value = self::validateTyped($type, $value, (string) $def['label']);
        }
        $exists = DB::value('SELECT COUNT(*) FROM settings WHERE name = ?', [$key]);
        if ((int) $exists > 0) {
            DB::update('settings', ['value' => $value, 'updated_at' => gmdate('Y-m-d H:i:s')], 'name = ?', [$key]);
        } else {
            DB::insert('settings', ['name' => $key, 'value' => $value, 'updated_at' => gmdate('Y-m-d H:i:s')]);
        }
        self::$cache = null;
    }

    /** Contrôle et normalisation selon le type déclaré dans defaults.php. @throws \InvalidArgumentException */
    private static function validateTyped(string $type, string $value, string $label): string
    {
        switch ($type) {
            case 'url':
                if (!preg_match('#^https://[^\s<>"\']+$#i', $value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
                    throw new \InvalidArgumentException($label . ' : lien complet commençant par https:// attendu.');
                }
                return $value;
            case 'email':
                if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    throw new \InvalidArgumentException($label . ' : adresse e-mail invalide.');
                }
                return mb_strtolower($value);
            case 'phone':
                if (!preg_match('/^\+?[0-9 ().\/-]{6,25}$/', $value) || strlen(preg_replace('/\D+/', '', $value) ?? '') < 8) {
                    throw new \InvalidArgumentException($label . ' : numéro invalide (8 chiffres minimum ; chiffres, espaces, + . - ( ) seulement).');
                }
                return $value;
            case 'dialcode':
                if (!preg_match('/^\+[1-9][0-9]{0,3}$/', $value)) {
                    throw new \InvalidArgumentException($label . ' : indicatif au format +225 attendu.');
                }
                return $value;
            case 'color':
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                    throw new \InvalidArgumentException($label . ' : couleur au format #RRVVBB attendue.');
                }
                return strtoupper($value);
            case 'image':
                if (!preg_match('#^/uploads/brand/[A-Za-z0-9/_.-]+\.(webp|png)$#', $value) || str_contains($value, '..')) {
                    throw new \InvalidArgumentException($label . ' : image invalide.');
                }
                return $value;
        }
        return $value;
    }

    /** Vrai si la valeur contient un marqueur [À COMPLÉTER]/[À CONFIRMER] ou est vide. */
    public static function isPlaceholder(string $key): bool
    {
        $v = self::get($key);
        return $v === '' || str_contains($v, '[À COMPLÉTER') || str_contains($v, '[À CONFIRMER');
    }
}
