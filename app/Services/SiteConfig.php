<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Lang;

/**
 * Source centrale des coordonnées et liens publics, construite à partir des réglages (Administration → Paramètres du site).
 * Tous les composants (en-tête, pied de page, contact, fiche produit, panier, e-mails, mentions légales) passent par ici :
 * un numéro modifié une fois est mis à jour partout. Une information vide ou masquée n'est jamais affichée.
 */
final class SiteConfig
{
    /** Réseaux sociaux pris en charge : clé de réglage => [icône, libellé accessible (clé de traduction)]. */
    public const NETWORKS = [
        'facebook' => ['facebook', 'social.facebook'],
        'instagram' => ['instagram', 'social.instagram'],
        'tiktok' => ['tiktok', 'social.tiktok'],
        'youtube' => ['youtube', 'social.youtube'],
        'linkedin' => ['linkedin', 'social.linkedin'],
        'other' => ['link', 'social.other'],
    ];

    /** Numéro au format international lisible : « +225 07 47 09 90 06 » (l'indicatif n'est ajouté que s'il manque). */
    public static function internationalPhone(string $number): string
    {
        $number = trim($number);
        if ($number === '') {
            return '';
        }
        if (str_starts_with($number, '+')) {
            return $number;
        }
        if (str_starts_with($number, '00')) {
            return '+' . substr($number, 2);
        }
        return trim(Settings::get('phone_country_code') . ' ' . $number);
    }

    /** @return array<int, array{display:string, href:string}> numéros affichables (vide si masqués) */
    public static function phones(): array
    {
        if (!Settings::bool('show_phone')) {
            return [];
        }
        $out = [];
        foreach (['store_phone', 'store_phone_2'] as $key) {
            $display = self::internationalPhone(Settings::get($key));
            if ($display !== '') {
                $out[] = ['display' => $display, 'href' => 'tel:' . preg_replace('/[^0-9+]/', '', $display)];
            }
        }
        return $out;
    }

    /** Numéro principal affichable, ou chaîne vide. */
    public static function mainPhone(): string
    {
        return self::phones()[0]['display'] ?? '';
    }

    /** Lien wa.me valide (chiffres uniquement, indicatif compris) ou null si WhatsApp est désactivé ou non renseigné. */
    public static function whatsappLink(?string $text = null): ?string
    {
        if (!Settings::bool('whatsapp_enabled')) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', self::internationalPhone(Settings::get('whatsapp_number'))) ?? '';
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }
        $text ??= Settings::localized('whatsapp_message');
        return 'https://wa.me/' . $digits . ($text !== '' ? '?text=' . rawurlencode($text) : '');
    }

    /** @return array<string, string> e-mails affichables : ['contact' => …, 'sales' => …] */
    public static function emails(): array
    {
        if (!Settings::bool('show_email')) {
            return [];
        }
        return array_filter(['contact' => Settings::get('store_email'), 'sales' => Settings::get('sales_email')], static fn ($v) => $v !== '');
    }

    /** Adresse affichable (« adresse, ville, pays ») ou chaîne vide si la localisation est masquée. */
    public static function address(): string
    {
        if (!Settings::bool('show_location')) {
            return '';
        }
        return implode(', ', array_filter([Settings::get('store_address'), Settings::get('store_city'), Settings::get('store_country')], static fn ($v) => trim($v) !== ''));
    }

    public static function mapsUrl(): ?string
    {
        return Settings::bool('show_location') && Settings::get('maps_url') !== '' ? Settings::get('maps_url') : null;
    }

    /**
     * Réseaux sociaux affichables (lien renseigné ET affichage activé), plus WhatsApp s'il est actif.
     * @return array<string, array{0:string, 1:string, 2:string}> réseau => [url, libellé accessible, icône]
     */
    public static function socialLinks(): array
    {
        $links = [];
        foreach (self::NETWORKS as $network => [$icon, $labelKey]) {
            $url = Settings::get('social_' . $network);
            if ($url === '' || !Settings::bool('social_' . $network . '_enabled')) {
                continue;
            }
            $label = $network === 'other' ? (Settings::get('social_other_label') ?: t('social.website')) . ' — ' . t('social.new_tab') : t($labelKey);
            $links[$network] = [$url, $label, $icon];
            if ($network === 'facebook' && ($wa = self::whatsappLink()) !== null) {
                $links['whatsapp'] = [$wa, t('social.whatsapp'), 'whatsapp'];
            }
        }
        if (!isset($links['whatsapp']) && ($wa = self::whatsappLink()) !== null) {
            $links['whatsapp'] = [$wa, t('social.whatsapp'), 'whatsapp'];
        }
        return $links;
    }

    /** Langues proposées aux visiteurs (le français, langue de référence, l'est toujours). @return array<string,string> */
    public static function languages(): array
    {
        $langs = ['fr' => 'Français'];
        if (Settings::bool('lang_en_enabled')) {
            $langs['en'] = 'English';
        }
        return $langs;
    }

    /** Titre de l'accueil et titre par défaut des pages. */
    public static function seoTitle(): string
    {
        $t = Settings::localized('seo_title');
        return $t !== '' ? $t : Settings::get('store_name') . ' — ' . Settings::localized('store_tagline');
    }

    public static function seoDescription(): string
    {
        $d = Settings::localized('seo_description');
        return $d !== '' ? $d : excerpt(Settings::localized('home_hero_text'), 158);
    }

    public static function isEnglishAllowed(): bool
    {
        return Settings::bool('lang_en_enabled');
    }

    public static function locale(): string
    {
        return Lang::get();
    }
}
