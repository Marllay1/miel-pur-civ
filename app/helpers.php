<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Env;
use App\Core\Lang;
use App\Core\Response;
use App\Core\View;
use App\Services\Settings;

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Chaîne d'interface traduite (app/lang/fr.php, app/lang/en.php). */
function t(string $key, array $params = []): string
{
    return Lang::t($key, $params);
}

function locale(): string
{
    return Lang::get();
}

function base_url(): string
{
    return rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/');
}

function url(string $path = '/', array $query = []): string
{
    $query = array_filter($query, static fn ($v) => $v !== null && $v !== '' && $v !== false);
    return $path . ($query ? '?' . http_build_query($query) : '');
}

/** URL absolue localisée (e-mails, SEO). */
function abs_url(string $path = '/', ?string $locale = null): string
{
    return base_url() . Lang::path($path, $locale);
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . $path;
    return '/assets/' . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/** Logo officiel (public/assets/img/brand/logo.svg|png|webp, installé par bin/brand.php) ou null tant qu'il n'est pas fourni. */
function brand_logo_url(): ?string
{
    static $url = false;
    if ($url === false) {
        $url = null;
        foreach (['svg', 'webp', 'png'] as $ext) {
            if (is_file(BASE_PATH . '/public/assets/img/brand/logo.' . $ext)) {
                $url = asset('img/brand/logo.' . $ext);
                break;
            }
        }
    }
    return $url;
}

/**
 * Marque affichée dans les en-têtes : logo officiel s'il est installé (proportions conservées),
 * sinon le nom de la boutique en toutes lettres — aucun logo de substitution n'est inventé.
 */
function brand_html(string $sub = ''): string
{
    $name = \App\Services\Settings::get('store_name') ?: 'Miel Pur de Côte d\'Ivoire';
    $logo = brand_logo_url();
    if ($logo !== null) {
        return '<img class="brand-logo" src="' . e($logo) . '" alt="' . e($name) . '" decoding="async">'
            . ($sub !== '' ? '<span class="brand-text"><span class="brand-sub">' . e($sub) . '</span></span>' : '');
    }
    return '<span class="brand-text brand-wordmark"><span class="brand-name">Miel Pur</span><span class="brand-sub">' . e($sub !== '' ? $sub : 'de Côte d\'Ivoire') . '</span></span>';
}

/** @return array<string, array> registre des photographies (app/config/photos.php) */
function photos(): array
{
    static $photos = null;
    return $photos ??= require BASE_PATH . '/app/config/photos.php';
}

/** Clé du registre correspondant à un chemin d'image (ex. image de produit), ou null. */
function photo_key_for_path(string $path): ?string
{
    $file = basename((string) strtok($path, '?'));
    foreach (photos() as $key => $p) {
        if ($p['file'] === $file) {
            return $key;
        }
    }
    return null;
}

/**
 * Balise <img> d'une photo du registre : dimensions intrinsèques (pas de décalage de mise en page),
 * variante légère pour les petits écrans, chargement différé hors du premier écran.
 */
function photo(string $key, string $class = '', string $sizes = '100vw', bool $eager = false): string
{
    $p = photos()[$key] ?? null;
    if ($p === null) {
        return '';
    }
    $src = asset('img/photos/' . $p['file']);
    $srcset = isset($p['small']) ? ' srcset="' . e(asset('img/photos/' . $p['small'])) . ' 800w, ' . e($src) . ' ' . (int) $p['w'] . 'w" sizes="' . e($sizes) . '"' : '';
    return '<img src="' . e($src) . '"' . $srcset . ' alt="' . e($p['alt'][Lang::get()] ?? $p['alt']['fr']) . '" width="' . (int) $p['w'] . '" height="' . (int) $p['h'] . '"'
        . ($class !== '' ? ' class="' . e($class) . '"' : '') . ($eager ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async">';
}

/** Texte alternatif d'une image de produit : version du registre dans la langue courante si la photo y figure. */
function image_alt(array $image): string
{
    $key = photo_key_for_path((string) ($image['path'] ?? ''));
    if ($key === null) {
        return (string) ($image['alt'] ?? '');
    }
    $p = photos()[$key];
    $alt = $p['alt'][Lang::get()] ?? $p['alt']['fr'];
    return $p['origin'] === 'illustration' ? t('photo.illustration') . ' : ' . $alt : $alt;
}

/** Légende d'origine : photo de l'entreprise, ou photo d'illustration avec auteur, licence et source. */
function photo_credit(string $key): string
{
    $p = photos()[$key] ?? null;
    if ($p === null) {
        return '';
    }
    if ($p['origin'] === 'company') {
        return '<span class="photo-credit">' . e(t('photo.company')) . '</span>';
    }
    return '<span class="photo-credit">' . e(t('photo.illustration')) . ' — ' . e($p['author']) . ', <a href="' . e($p['source']) . '" rel="noopener" target="_blank">Wikimedia Commons</a>, '
        . '<a href="' . e($p['license_url']) . '" rel="noopener license" target="_blank">' . e($p['license']) . '</a></span>';
}

/** Balises d'icônes (onglet, écran d'accueil, manifeste) générées par bin/brand.php. */
function favicon_tags(): string
{
    $out = '';
    $tags = [
        ['favicon.ico', '<link rel="icon" href="%s" sizes="48x48">'],
        ['favicon.svg', '<link rel="icon" href="%s" type="image/svg+xml">'],
        ['favicon-32.png', '<link rel="icon" href="%s" type="image/png" sizes="32x32">'],
        ['favicon-16.png', '<link rel="icon" href="%s" type="image/png" sizes="16x16">'],
        ['apple-touch-icon.png', '<link rel="apple-touch-icon" href="%s" sizes="180x180">'],
        ['site.webmanifest', '<link rel="manifest" href="%s">'],
    ];
    foreach ($tags as [$file, $tpl]) {
        if (is_file(BASE_PATH . '/public/assets/img/brand/' . $file)) {
            $out .= sprintf($tpl, e(asset('img/brand/' . $file))) . "\n";
        }
    }
    return $out;
}

/** Montant entier en FCFA : « 12 500 FCFA » (fr) ou « 12,500 FCFA » (en). */
function money(int|string|null $amount): string
{
    $sep = Lang::get() === 'en' ? ',' : "\u{202F}";
    return number_format((int) $amount, 0, '.', $sep) . "\u{00A0}FCFA";
}

function now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function setting(string $key): string
{
    return Settings::localized($key);
}

function view(string $template, array $data = [], int $status = 200, ?string $layout = 'layouts/main'): Response
{
    return Response::html(View::render($template, $data, $layout), $status);
}

function admin_view(string $template, array $data = [], int $status = 200): Response
{
    return view('admin/' . $template, $data, $status, 'layouts/admin');
}

function redirect(string $url): Response
{
    return Response::redirect($url);
}

function random_token(int $bytes = 16): string
{
    return bin2hex(random_bytes($bytes));
}

function slugify(string $text): string
{
    $text = mb_strtolower(trim($text));
    $map = ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
        'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae', "'" => '-', '’' => '-'];
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'element';
}

function excerpt(?string $text, int $length = 160): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)) ?? '');
    $text = str_replace(['## ', '**'], '', $text);
    $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $text) ?? $text;
    return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . '…' : $text;
}

const MONTHS_FR = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
const MONTHS_EN = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/** Date UTC (= heure d'Abidjan, GMT) dans la langue courante. */
function date_fr(?string $datetime, bool $withTime = true): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '—';
    }
    $months = Lang::get() === 'en' ? MONTHS_EN : MONTHS_FR;
    $s = (int) gmdate('j', $ts) . ' ' . $months[(int) gmdate('n', $ts) - 1] . ' ' . gmdate('Y', $ts);
    return $withTime ? $s . (Lang::get() === 'en' ? ', ' : ' à ') . gmdate('H:i', $ts) : $s;
}

function status_label(string $status): string
{
    return Lang::has('status.' . $status) ? t('status.' . $status) : $status;
}

function payment_status_label(string $status): string
{
    return Lang::has('pstatus.' . $status) ? t('pstatus.' . $status) : $status;
}

function payment_method_label(string $method): string
{
    return Lang::has('pay.' . $method) ? t('pay.' . $method) : $method;
}

/** Échappe puis met en forme : **gras** et [lien](/chemin|https://…). */
function rich_inline(string $text): string
{
    $html = e($text);
    $html = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $html) ?? $html;
    $html = preg_replace_callback(
        '/\[([^\]]+)\]\((\/(?![\/\\\\])[^\s)]*|https:\/\/[^\s)]+)\)/u',
        static fn ($m) => '<a href="' . $m[2] . '"' . (str_starts_with($m[2], 'https://') ? ' rel="noopener" target="_blank"' : '') . '>' . $m[1] . '</a>',
        $html
    ) ?? $html;
    return preg_replace('/\[(À (?:CONFIRMER|COMPLÉTER)[^\]]*)\]/u', '<mark class="todo">[$1]</mark>', $html) ?? $html;
}

/**
 * Mise en forme sûre des contenus éditables : paragraphes (ligne vide), « ## » titres, « - » listes.
 * Aucun HTML saisi n'est interprété.
 */
function rich(?string $text): string
{
    $text = trim(str_replace("\r\n", "\n", (string) $text));
    if ($text === '') {
        return '';
    }
    $html = '';
    foreach (preg_split("/\n{2,}/", $text) as $block) {
        $lines = array_values(array_filter(array_map('rtrim', explode("\n", trim($block))), static fn ($l) => $l !== ''));
        $para = [];
        $list = [];
        $flushPara = static function () use (&$para, &$html): void {
            if ($para) {
                $html .= '<p>' . implode('<br>', array_map('rich_inline', $para)) . '</p>';
                $para = [];
            }
        };
        $flushList = static function () use (&$list, &$html): void {
            if ($list) {
                $html .= '<ul>' . implode('', array_map(static fn ($l) => '<li>' . rich_inline($l) . '</li>', $list)) . '</ul>';
                $list = [];
            }
        };
        foreach ($lines as $line) {
            if (str_starts_with($line, '## ') || str_starts_with($line, '### ')) {
                $flushPara();
                $flushList();
                $level = str_starts_with($line, '### ') ? 3 : 2;
                $html .= "<h$level>" . rich_inline(substr($line, $level + 1)) . "</h$level>";
            } elseif (str_starts_with($line, '- ')) {
                $flushPara();
                $list[] = substr($line, 2);
            } else {
                $flushList();
                $para[] = $line;
            }
        }
        $flushPara();
        $flushList();
    }
    return $html;
}

/** Protège une cellule CSV contre l'injection de formules (=, +, -, @, tabulation, retour chariot). */
function csv_safe(mixed $value): string
{
    $value = (string) ($value ?? '');
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $value;
    }
    return $value;
}

function is_demo_mode(): bool
{
    try {
        return Settings::get('demo_mode') === '1';
    } catch (\Throwable) {
        return false;
    }
}

function wa_link(string $text = ''): ?string
{
    $number = preg_replace('/\D+/', '', Settings::get('whatsapp_number')) ?? '';
    if ($number === '') {
        return null;
    }
    return 'https://wa.me/' . $number . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

function tel_link(string $phone): string
{
    return 'tel:' . preg_replace('/[^0-9+]/', '', $phone);
}

function app_key(): string
{
    $key = (string) Env::get('APP_KEY', '');
    if (strlen($key) < 32) {
        throw new \RuntimeException('APP_KEY absente ou trop courte : lancer bin/install.php ou renseigner .env');
    }
    return $key;
}

/** Champ piège + horodatage signé, à placer dans les formulaires publics. */
function spam_fields(): string
{
    $ts = (string) time();
    $sig = hash_hmac('sha256', 'form|' . $ts, app_key());
    return '<div class="hp" aria-hidden="true"><label>' . e(t('form.honeypot')) . ' <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>'
        . '<input type="hidden" name="_ts" value="' . e($ts . '.' . $sig) . '">';
}

/** Renvoie un message d'erreur si la soumission ressemble à du spam, sinon null. */
function spam_check(App\Core\Request $r, bool $requireTiming = true): ?string
{
    if (trim((string) ($r->post['website'] ?? '')) !== '') {
        return t('spam.refused');
    }
    if (!$requireTiming) {
        return null;
    }
    $raw = (string) ($r->post['_ts'] ?? '');
    $parts = explode('.', $raw, 2);
    if (count($parts) !== 2 || !ctype_digit($parts[0]) || !hash_equals(hash_hmac('sha256', 'form|' . $parts[0], app_key()), $parts[1])) {
        return t('spam.invalid');
    }
    $elapsed = time() - (int) $parts[0];
    $min = (int) Env::get('ANTISPAM_MIN_SECONDS', '3');
    if ($elapsed < $min) {
        return t('spam.too_fast');
    }
    if ($elapsed > 86400) {
        return t('spam.expired');
    }
    return null;
}

/** Chemin de retour interne sûr (anti open-redirect). */
function safe_return(?string $path, string $fallback = '/'): string
{
    if (!is_string($path) || !preg_match('#^/(?!/)[A-Za-z0-9/_\-.?=&%]*$#', $path)) {
        return $fallback;
    }
    return $path;
}

function field_error(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<p class="field-error" id="err-' . e($field) . '">' . e($errors[$field]) . '</p>' : '';
}

function aria_invalid(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="err-' . e($field) . '"' : '';
}

/**
 * Icônes au trait (style « symboles » iOS), dessinées à la main, décoratives par défaut.
 */
function icon(string $name, string $class = 'icon'): string
{
    static $paths = [
        'home' => '<path d="M3.5 10.5 12 3.5l8.5 7"/><path d="M5.5 9v10.5h4.5V14h4v5.5h4.5V9"/>',
        'bag' => '<path d="M5 8h14l-1 12.5H6z"/><path d="M8.5 8V6.5a3.5 3.5 0 0 1 7 0V8"/>',
        'grid' => '<rect x="4" y="4" width="6.5" height="6.5" rx="1.8"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1.8"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1.8"/><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.8"/>',
        'person' => '<circle cx="12" cy="8" r="4"/><path d="M4.5 20.5c1.4-3.8 4.2-5.7 7.5-5.7s6.1 1.9 7.5 5.7"/>',
        'gear' => '<circle cx="12" cy="12" r="3"/><path d="M12 2.8v2.4M12 18.8v2.4M2.8 12h2.4M18.8 12h2.4M5.5 5.5l1.7 1.7M16.8 16.8l1.7 1.7M5.5 18.5l1.7-1.7M16.8 7.2l1.7-1.7"/><circle cx="12" cy="12" r="6.2"/>',
        'more' => '<circle cx="5.5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="18.5" cy="12" r="1.4"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5 5"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4"/>',
        'moon' => '<path d="M19.5 14.5A8 8 0 0 1 9.5 4.5a8 8 0 1 0 10 10z"/>',
        'globe' => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c2.5 2.6 3.7 5.4 3.7 8.5s-1.2 5.9-3.7 8.5c-2.5-2.6-3.7-5.4-3.7-8.5s1.2-5.9 3.7-8.5z"/>',
        'chevron' => '<path d="m9.5 5.5 6.5 6.5-6.5 6.5"/>',
        'chevron-left' => '<path d="m14.5 5.5-6.5 6.5 6.5 6.5"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'truck' => '<path d="M3.5 6.5h10v9h-10z"/><path d="M13.5 9.5h4l3 3v3h-7"/><circle cx="7.5" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
        'shield' => '<path d="M12 3.5 19 6v6c0 4.2-2.9 7.5-7 8.5-4.1-1-7-4.3-7-8.5V6z"/><path d="m8.8 12 2.2 2.2 4.2-4.4"/>',
        'leaf' => '<path d="M5 19c0-8 5-13.5 14.5-14.5C19 14 14 19 6 19z"/><path d="M5 19c3-4 6-6.5 9.5-8.5"/>',
        'phone' => '<rect x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M11 18.5h2"/>',
        'chat' => '<path d="M4 12a8 8 0 1 1 3.4 6.5L3.5 20l1.3-3.6A8 8 0 0 1 4 12z"/>',
        'mail' => '<rect x="3.5" y="5.5" width="17" height="13" rx="2.5"/><path d="m4.5 7 7.5 6 7.5-6"/>',
        'pin' => '<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0C18.5 15.4 12 21 12 21z"/><circle cx="12" cy="10" r="2.4"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'card' => '<rect x="3" y="5.5" width="18" height="13" rx="2.5"/><path d="M3 9.5h18M6.5 15h4"/>',
        'cash' => '<rect x="2.5" y="6" width="19" height="12" rx="2.5"/><circle cx="12" cy="12" r="2.6"/><path d="M6 9.5v5M18 9.5v5"/>',
        'box' => '<path d="m12 3 8.5 4.5v9L12 21l-8.5-4.5v-9z"/><path d="M3.5 7.5 12 12l8.5-4.5M12 12v9"/>',
        'star' => '<path d="m12 4 2.4 5 5.4.6-4 3.7 1.1 5.4L12 16l-4.9 2.7 1.1-5.4-4-3.7 5.4-.6z"/>',
        'hex' => '<path d="M12 2.8 20 7.4v9.2l-8 4.6-8-4.6V7.4z"/>',
        'arrow-right' => '<path d="M4.5 12h15M13.5 6l6 6-6 6"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'lock' => '<rect x="5" y="10.5" width="14" height="10" rx="2.5"/><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5"/>',
        'receipt' => '<path d="M6 3.5h12v17l-2.5-1.5-2 1.5-1.5-1.5-1.5 1.5-2-1.5L6 20.5z"/><path d="M9 8h6M9 11.5h6M9 15h3.5"/>',
        'filter' => '<path d="M4 6.5h16M7 12h10M10 17.5h4"/>',
        'logout' => '<path d="M14 4.5H6.5v15H14"/><path d="M10.5 12h10M17 8.5l3.5 3.5-3.5 3.5"/>',
        'info' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5.5M12 7.8v.2"/>',
        'building' => '<path d="M4.5 20.5v-15l8-2v17M12.5 8.5h7v12"/><path d="M7.5 8.5h2M7.5 12h2M7.5 15.5h2M15.5 12h1.5M15.5 15.5h1.5M3 20.5h18"/>',
    ];
    $svg = $paths[$name] ?? $paths['hex'];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $svg . '</svg>';
}

/** Pastille d'un moyen de paiement (couleurs génériques, sans logo de marque). */
function pay_logo(string $code): string
{
    $inner = match ($code) {
        'cod' => icon('cash'),
        'card' => icon('card'),
        'orange_money' => 'OM',
        'mtn_momo' => 'MoMo',
        'wave' => 'W',
        default => icon('lock'),
    };
    return '<span class="pay-logo ' . e($code) . '" aria-hidden="true">' . $inner . '</span>';
}
