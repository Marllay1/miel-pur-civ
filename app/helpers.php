<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Env;
use App\Core\Response;
use App\Core\View;
use App\Services\Settings;

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

function abs_url(string $path = '/'): string
{
    return base_url() . $path;
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . $path;
    return '/assets/' . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/** Montant entier en FCFA, ex. « 12 500 FCFA ». */
function money(int|string|null $amount): string
{
    return number_format((int) $amount, 0, ',', "\u{202F}") . "\u{00A0}FCFA";
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
    return Settings::get($key);
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
    return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . '…' : $text;
}

const MONTHS_FR = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

/** Date UTC (= heure d'Abidjan, GMT) au format français. */
function date_fr(?string $datetime, bool $withTime = true): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '—';
    }
    $s = (int) gmdate('j', $ts) . ' ' . MONTHS_FR[(int) gmdate('n', $ts) - 1] . ' ' . gmdate('Y', $ts);
    return $withTime ? $s . ' à ' . gmdate('H:i', $ts) : $s;
}

function status_label(string $status): string
{
    return [
        'pending_confirmation' => 'En attente de confirmation',
        'pending_payment' => 'En attente de paiement',
        'paid' => 'Payée',
        'preparing' => 'En préparation',
        'shipped' => 'Expédiée',
        'delivered' => 'Livrée',
        'cancelled' => 'Annulée',
        'refunded' => 'Remboursée',
    ][$status] ?? $status;
}

function payment_status_label(string $status): string
{
    return [
        'unpaid' => 'Non payée',
        'pending' => 'Paiement en attente',
        'paid' => 'Payée',
        'failed' => 'Paiement échoué',
        'refunded' => 'Remboursée',
    ][$status] ?? $status;
}

function payment_method_label(string $method): string
{
    return [
        'cod' => 'Paiement à la livraison',
        'demo' => 'Paiement SIMULÉ (démonstration)',
        'orange_money' => 'Orange Money',
        'mtn_momo' => 'MTN Mobile Money',
        'wave' => 'Wave',
        'card' => 'Carte bancaire',
    ][$method] ?? $method;
}

/** Échappe puis met en forme : **gras**, [lien](/chemin|https://…) et marqueurs [À CONFIRMER]. */
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
    return '<div class="hp" aria-hidden="true"><label>Ne pas remplir <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>'
        . '<input type="hidden" name="_ts" value="' . e($ts . '.' . $sig) . '">';
}

/** Renvoie un message d'erreur si la soumission ressemble à du spam, sinon null. */
function spam_check(App\Core\Request $r, bool $requireTiming = true): ?string
{
    if (trim((string) ($r->post['website'] ?? '')) !== '') {
        return 'Envoi refusé.';
    }
    if (!$requireTiming) {
        return null;
    }
    $raw = (string) ($r->post['_ts'] ?? '');
    $parts = explode('.', $raw, 2);
    if (count($parts) !== 2 || !ctype_digit($parts[0]) || !hash_equals(hash_hmac('sha256', 'form|' . $parts[0], app_key()), $parts[1])) {
        return 'Formulaire invalide. Rechargez la page.';
    }
    $elapsed = time() - (int) $parts[0];
    $min = (int) Env::get('ANTISPAM_MIN_SECONDS', '3');
    if ($elapsed < $min) {
        return 'Envoi trop rapide. Patientez quelques secondes puis réessayez.';
    }
    if ($elapsed > 86400) {
        return 'Le formulaire a expiré. Rechargez la page.';
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
