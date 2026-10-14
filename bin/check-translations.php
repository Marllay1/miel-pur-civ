<?php
declare(strict_types=1);

/**
 * Vérifie que chaque clé de traduction utilisée dans le code existe en français et en anglais.
 *   php bin/check-translations.php            liste les clés manquantes (code de sortie 1 s'il en manque)
 *   php bin/check-translations.php --list     affiche toutes les clés utilisées
 * Les clés dynamiques (status.*, pay.*, format.*, sort.*, activity.*, frequency.*, mail.status.*, error.*) sont vérifiées
 * à partir des listes connues ci-dessous.
 */

require __DIR__ . '/_cli.php';

$opts = cli_options($argv);
$keys = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_PATH, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if (!str_ends_with($file->getFilename(), '.php') || str_contains(str_replace('\\', '/', $file->getPathname()), '/lang/')) {
        continue;
    }
    $src = (string) file_get_contents($file->getPathname());
    if (preg_match_all('/\b(?:t|Lang::t)\(\s*\'([a-z0-9_.]+)\'/', $src, $m)) {
        foreach ($m[1] as $k) {
            if (str_ends_with($k, '.')) {
                continue;
            }
            $keys[$k] = true;
        }
    }
}
$dynamic = [
    'status.' => ['pending_confirmation', 'pending_payment', 'paid', 'preparing', 'shipped', 'delivered', 'cancelled', 'refunded'],
    'pstatus.' => ['unpaid', 'pending', 'paid', 'failed', 'refunded'],
    'pay.' => ['cod', 'demo', 'orange_money', 'mtn_momo', 'wave', 'card'],
    'pay.desc.' => ['cod', 'cod_pickup', 'demo', 'orange_money', 'mtn_momo', 'wave', 'card'],
    'pay.result.' => ['paid', 'failed', 'cancelled', 'pending', 'error', 'already'],
    'format.' => array_keys(App\Services\Catalog::FORMATS),
    'sort.' => array_keys(App\Services\Catalog::SORTS),
    'activity.' => array_keys(App\Controllers\PageController::ACTIVITIES),
    'frequency.' => ['ponctuelle', 'mensuelle', 'hebdomadaire', 'autre'],
    'mail.status.' => ['paid', 'preparing', 'shipped', 'delivered', 'cancelled', 'refunded'],
    'error.title.' => ['403', '404', '405', 'csrf', '429', '500', '503', 'default', '400'],
    'error.text.' => ['403', '404', 'csrf', 'default'],
];
foreach ($dynamic as $prefix => $list) {
    foreach ($list as $k) {
        $keys[$prefix . $k] = true;
    }
}
ksort($keys);
if (!empty($opts['list'])) {
    out(implode("\n", array_keys($keys)));
    exit(0);
}
$missing = 0;
foreach (['fr', 'en'] as $locale) {
    $table = require APP_PATH . '/lang/' . $locale . '.php';
    foreach (array_keys($keys) as $k) {
        if (!array_key_exists($k, $table)) {
            out("[$locale] manquante : $k");
            $missing++;
        }
    }
    foreach ($table as $k => $v) {
        if (preg_match_all('/:([a-z]+)/', is_array($v) ? implode(' ', $v) : $v, $m)) {
            $other = (require APP_PATH . '/lang/' . ($locale === 'fr' ? 'en' : 'fr') . '.php')[$k] ?? null;
            if ($other !== null) {
                preg_match_all('/:([a-z]+)/', is_array($other) ? implode(' ', $other) : $other, $o);
                if (array_diff(array_unique($m[1]), array_unique($o[1]))) {
                    out("[$locale] paramètres différents : $k");
                    $missing++;
                }
            }
        }
    }
}
out($missing === 0 ? '✔ ' . count($keys) . ' clés, toutes présentes en fr et en.' : "✘ $missing problème(s).");
exit($missing === 0 ? 0 : 1);
