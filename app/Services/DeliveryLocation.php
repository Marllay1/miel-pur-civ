<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\HttpClient;
use App\Core\Log;
use App\Core\RateLimiter;
use App\Core\Session;

/**
 * Localisation de livraison saisie à la commande : adresse écrite (toujours conservée) et, si le client le choisit,
 * position GPS validée sur une carte. Aucune position n'est demandée sans action du client, aucun suivi continu.
 * Coordonnées validées par le serveur ; adresse estimée (géocodage) seulement si un service est configuré.
 */
final class DeliveryLocation
{
    /**
     * Zone géographique acceptée (Côte d'Ivoire, avec une marge) : toutes les zones de livraison actuelles s'y trouvent.
     * À élargir si des zones hors du pays sont créées.
     */
    public const BOUNDS = ['lat_min' => 4.0, 'lat_max' => 11.0, 'lng_min' => -9.0, 'lng_max' => -2.0];

    public static function gpsEnabled(): bool
    {
        return Settings::bool('checkout_gps_enabled');
    }

    public static function mapEnabled(): bool
    {
        return self::gpsEnabled() && Settings::bool('checkout_map_enabled') && self::tileUrl() !== null;
    }

    public static function tileUrl(): ?string
    {
        $url = trim((string) Env::get('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'));
        return preg_match('#^https://[a-z0-9.-]+(:\d+)?/[^\s"\'<>]*\{z\}[^\s"\'<>]*\{x\}[^\s"\'<>]*\{y\}#i', $url) ? $url : null;
    }

    /** Origine des tuiles (pour la politique de sécurité du contenu), ex. https://tile.openstreetmap.org */
    public static function tileOrigin(): ?string
    {
        $url = self::tileUrl();
        if ($url === null) {
            return null;
        }
        $parts = parse_url(str_replace(['{s}', '{z}', '{x}', '{y}', '{r}'], ['a', '0', '0', '0', ''], $url));
        if (!isset($parts['host'])) {
            return null;
        }
        $host = str_contains($url, '{s}') ? '*.' . preg_replace('/^a\./', '', $parts['host']) : $parts['host'];
        return 'https://' . $host . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    public static function tileAttribution(): string
    {
        return (string) Env::get('MAP_TILE_ATTRIBUTION', '© contributeurs OpenStreetMap');
    }

    public static function geocoderEnabled(): bool
    {
        return self::gpsEnabled() && Settings::get('checkout_geocoder') === 'nominatim' && filter_var((string) Env::get('GEOCODER_CONTACT_EMAIL', ''), FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validation des champs de localisation envoyés avec la commande.
     * @return array{0: array{location_method:string, delivery_lat:?string, delivery_lng:?string, delivery_accuracy_m:?int, location_adjusted:int}, 1: array<string,string>}
     */
    public static function validate(array $post, bool $pickup): array
    {
        $none = ['location_method' => 'manual', 'delivery_lat' => null, 'delivery_lng' => null, 'delivery_accuracy_m' => null, 'location_adjusted' => 0];
        $lat = trim((string) ($post['gps_lat'] ?? ''));
        $lng = trim((string) ($post['gps_lng'] ?? ''));
        if ($pickup || !self::gpsEnabled() || ($lat === '' && $lng === '')) {
            return [$none, []];
        }
        $num = '/^-?\d{1,3}(\.\d{1,10})?$/';
        if (!preg_match($num, $lat) || !preg_match($num, $lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            return [$none, ['gps' => t('location.invalid')]];
        }
        $b = self::BOUNDS;
        if ((float) $lat < $b['lat_min'] || (float) $lat > $b['lat_max'] || (float) $lng < $b['lng_min'] || (float) $lng > $b['lng_max']) {
            return [$none, ['gps' => t('location.outside')]];
        }
        $acc = trim((string) ($post['gps_accuracy'] ?? ''));
        return [[
            'location_method' => 'gps',
            'delivery_lat' => number_format((float) $lat, 6, '.', ''),
            'delivery_lng' => number_format((float) $lng, 6, '.', ''),
            'delivery_accuracy_m' => preg_match('/^\d{1,6}(\.\d+)?$/', $acc) ? (int) round((float) $acc) : null,
            'location_adjusted' => ($post['gps_adjusted'] ?? '') === '1' ? 1 : 0,
        ], []];
    }

    /** Lien d'ouverture de la position sur une carte externe (administration). */
    public static function mapLink(string $lat, string $lng): string
    {
        return 'https://www.openstreetmap.org/?mlat=' . rawurlencode($lat) . '&mlon=' . rawurlencode($lng) . '#map=18/' . rawurlencode($lat) . '/' . rawurlencode($lng);
    }

    public static function googleMapsLink(string $lat, string $lng): string
    {
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($lat . ',' . $lng);
    }

    /**
     * Adresse estimée (géocodage inverse, Nominatim) appelée par le serveur, jamais directement par le navigateur :
     * débit limité (1 requête par seconde pour tout le site), résultat mémorisé en session pour être joint à la commande.
     */
    public static function reverseGeocode(string $lat, string $lng): ?string
    {
        if (!self::geocoderEnabled()) {
            return null;
        }
        $key = $lat . ',' . $lng;
        $cache = (array) Session::get('geocode_cache', []);
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        if (!RateLimiter::hit('geocoder:global', 1, 1)) {
            return null;
        }
        $url = rtrim((string) Env::get('GEOCODER_NOMINATIM_URL', 'https://nominatim.openstreetmap.org'), '/')
            . '/reverse?format=jsonv2&zoom=18&addressdetails=0&accept-language=fr&lat=' . rawurlencode($lat) . '&lon=' . rawurlencode($lng);
        try {
            $res = HttpClient::request('GET', $url, ['User-Agent' => 'MielPurCI/1.0 (' . Env::get('GEOCODER_CONTACT_EMAIL') . ')', 'Accept' => 'application/json'], null, 6);
        } catch (\Throwable $e) {
            Log::error('Géocodage indisponible : ' . $e->getMessage());
            return null;
        }
        $data = json_decode((string) ($res['body'] ?? ''), true);
        $address = is_array($data) && isset($data['display_name']) ? mb_substr(trim((string) $data['display_name']), 0, 255) : null;
        if ($address !== null) {
            $cache[$key] = $address;
            Session::set('geocode_cache', array_slice($cache, -10, null, true));
        }
        return $address;
    }

    /** Adresse estimée déjà obtenue pour ces coordonnées pendant la session (jointe à la commande, jamais fournie par le navigateur). */
    public static function cachedAddress(?string $lat, ?string $lng): ?string
    {
        if ($lat === null || $lng === null) {
            return null;
        }
        return ((array) Session::get('geocode_cache', []))[$lat . ',' . $lng] ?? null;
    }
}
