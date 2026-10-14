<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Lang;

/**
 * Textes de l'interface publique modifiables en administration (Administration → Textes du site).
 * Les valeurs par défaut restent dans app/lang/*.php ; un texte modifié est enregistré dans text_overrides
 * et prioritaire. Seuls les textes éditoriaux et commerciaux du site public sont proposés (pas les e-mails,
 * messages d'erreur ou de validation, statuts techniques).
 */
final class TextOverrides
{
    /** Rubriques : titre => préfixes de clés. */
    public const GROUPS = [
        'navigation' => ['En-tête et navigation', ['nav.', 'menu.', 'tab.', 'lang.', 'theme.']],
        'accueil' => ['Page d\'accueil', ['home.', 'about.']],
        'pied' => ['Pied de page et réseaux', ['footer.', 'newsletter.', 'social.']],
        'boutique' => ['Boutique (catalogue)', ['shop.', 'sort.', 'format.']],
        'produit' => ['Fiche produit et avis', ['product.', 'review.']],
        'panier' => ['Panier, commande et suivi', ['cart.', 'promo.', 'totals.', 'checkout.', 'order.', 'receipt.', 'tracking.']],
        'contact' => ['Contact, devis et FAQ', ['contact.', 'pro.', 'quote.', 'activity.', 'frequency.', 'faq.']],
        'compte' => ['Espace client', ['account.']],
    ];

    /** Chargé par Lang : textes modifiés d'une langue. */
    public static function load(string $locale): array
    {
        $out = [];
        foreach (DB::all('SELECT lang_key, value FROM text_overrides WHERE locale = ?', [$locale]) as $row) {
            $out[$row['lang_key']] = (string) $row['value'];
        }
        return $out;
    }

    /** @return array<string, array{fr:string, en:string}> textes modifiables d'une rubrique (valeurs par défaut) */
    public static function keys(string $group): array
    {
        $prefixes = self::GROUPS[$group][1] ?? [];
        $fr = require APP_PATH . '/lang/fr.php';
        $en = require APP_PATH . '/lang/en.php';
        $out = [];
        foreach ($fr as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            foreach ($prefixes as $p) {
                if (str_starts_with($key, $p)) {
                    $out[$key] = ['fr' => $value, 'en' => is_string($en[$key] ?? null) ? $en[$key] : ''];
                    break;
                }
            }
        }
        return $out;
    }

    public static function groupOf(string $key): ?string
    {
        foreach (self::GROUPS as $slug => [, $prefixes]) {
            foreach ($prefixes as $p) {
                if (str_starts_with($key, $p)) {
                    return $slug;
                }
            }
        }
        return null;
    }

    /** Paramètres « :nom » d'un texte par défaut, qui doivent rester présents dans le texte modifié. */
    public static function placeholders(string $text): array
    {
        preg_match_all('/:([a-z_]+)/', $text, $m);
        $names = array_unique($m[1]);
        sort($names);
        return $names;
    }

    /** @return ?string message d'erreur, ou null si le texte est acceptable */
    public static function check(string $default, string $value): ?string
    {
        if (mb_strlen($value) > 1000) {
            return '1 000 caractères au maximum.';
        }
        if (preg_match('/[<>]/', $value)) {
            return 'Les balises (< >) ne sont pas autorisées.';
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
            return 'Caractères de contrôle interdits.';
        }
        $missing = array_diff(self::placeholders($default), self::placeholders($value));
        if ($missing) {
            return 'Conservez ' . implode(', ', array_map(static fn ($p) => ':' . $p, $missing)) . ' (remplacé automatiquement sur le site).';
        }
        return null;
    }

    /** Enregistre (ou supprime si vide ou identique à la valeur par défaut) un texte modifié. */
    public static function save(string $key, string $locale, string $value, string $default): void
    {
        DB::run('DELETE FROM text_overrides WHERE lang_key = ? AND locale = ?', [$key, $locale]);
        if ($value !== '' && $value !== $default) {
            DB::insert('text_overrides', ['lang_key' => $key, 'locale' => $locale, 'value' => $value, 'updated_at' => gmdate('Y-m-d H:i:s')]);
        }
        Lang::flushOverrides();
    }
}
