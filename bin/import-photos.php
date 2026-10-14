<?php
declare(strict_types=1);

/**
 * Ajoute aux fiches produits les photos libres de droits listées dans database/seeds/stock-photos.json
 * (Wikimedia Commons), par le même traitement qu'un téléversement en administration : image contrôlée et
 * ré-encodée en WebP dans public/uploads/products, chemin et provenance (auteur, licence, source) en base.
 * Les photos apparaissent ensuite dans Administration → Produits → fiche → Photos (ordre, photo principale, suppression).
 *   php bin/import-photos.php [--dry-run]
 * Idempotent : une photo déjà importée (même source) n'est pas ajoutée deux fois. Nécessite un accès Internet.
 */

require __DIR__ . '/_cli.php';

use App\Core\Audit;
use App\Core\DB;
use App\Services\ImageService;

$opts = cli_options($argv);
$manifest = json_decode((string) file_get_contents(BASE_PATH . '/database/seeds/stock-photos.json'), true);
if (!is_array($manifest)) {
    fail('Manifeste illisible : database/seeds/stock-photos.json');
}
$ctx = stream_context_create(['http' => ['header' => "User-Agent: MielPurCI/1.0 (import de photos libres de droits)\r\n", 'timeout' => 60]]);
$added = 0;
// Wikimedia limite le débit : nouvelles tentatives espacées.
$fetch = static function (string $url) use ($ctx): string|false {
    for ($try = 1; $try <= 4; $try++) {
        $res = @file_get_contents($url, false, $ctx);
        if ($res !== false && $res !== '') {
            return $res;
        }
        sleep(2 * $try);
    }
    return false;
};
foreach ($manifest as $slug => $photos) {
    if ($slug === '_comment') {
        continue;
    }
    $product = DB::one('SELECT id, name, review_note FROM products WHERE slug = ? AND deleted_at IS NULL', [$slug]);
    if ($product === null) {
        out("— $slug : produit absent, ignoré.");
        continue;
    }
    foreach ($photos as $p) {
        $api = 'https://commons.wikimedia.org/w/api.php?action=query&format=json&prop=imageinfo&iiprop=url|extmetadata&iiurlwidth=1600&titles=' . rawurlencode($p['title']);
        usleep(500000);
        $data = json_decode((string) $fetch($api), true);
        $page = is_array($data['query']['pages'] ?? null) ? reset($data['query']['pages']) : null;
        $info = $page['imageinfo'][0] ?? null;
        if ($info === null) {
            out("✗ {$p['title']} : introuvable sur Wikimedia Commons.");
            continue;
        }
        $license = (string) ($info['extmetadata']['LicenseShortName']['value'] ?? '');
        $author = trim(html_entity_decode(strip_tags((string) ($info['extmetadata']['Artist']['value'] ?? ''))));
        $source = (string) $info['descriptionurl'];
        if ($license === '' || !preg_match('/^(CC0|CC BY|CC BY-SA|Public domain|No restrictions)/i', $license)) {
            out("✗ {$p['title']} : licence non reconnue ($license), ignorée.");
            continue;
        }
        if (DB::value('SELECT id FROM product_images WHERE product_id = ? AND source_note LIKE ?', [(int) $product['id'], '%' . $source]) !== null) {
            out("= {$p['title']} : déjà importée.");
            continue;
        }
        if (isset($opts['dry-run'])) {
            out("· {$p['title']} → {$product['name']} ($license)");
            continue;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'cm');
        // Vignette 1600 px ; à défaut (image plus petite), fichier d'origine.
        $bin = $fetch((string) ($info['thumburl'] ?? $info['url']));
        if ($bin === false && isset($info['url'])) {
            $bin = $fetch((string) $info['url']);
        }
        if ($bin === false || $bin === '') {
            out("✗ {$p['title']} : téléchargement impossible.");
            continue;
        }
        file_put_contents($tmp, $bin);
        $result = ImageService::store(['tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($bin), 'name' => basename($tmp)], 'products');
        @unlink($tmp);
        if (!$result['ok']) {
            out("✗ {$p['title']} : {$result['error']}");
            continue;
        }
        $note = 'Photo libre de droits · ' . $license . ' · ' . mb_substr($author !== '' ? $author : 'auteur non indiqué', 0, 60) . ' · ' . $source;
        $order = (int) DB::value('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_images WHERE product_id = ?', [(int) $product['id']]);
        $id = DB::insert('product_images', ['product_id' => (int) $product['id'], 'path' => $result['path'], 'alt' => $p['alt'], 'sort_order' => $order,
            'is_demo' => 0, 'source_note' => mb_substr($note, 0, 255), 'created_at' => gmdate('Y-m-d H:i:s')]);
        if (!empty($p['alt_en'])) {
            \App\Services\I18n::save('image', $id, ['alt' => $p['alt_en']]);
        }
        Audit::log('image.import', 'product', (int) $product['id'], ['image' => $id, 'source' => $source, 'license' => $license], null);
        out("✓ {$p['title']} → {$product['name']} ($license)");
        $added++;
        usleep(250000);
    }
    if (!isset($opts['dry-run']) && str_starts_with((string) $product['review_note'], 'Photo à fournir')) {
        DB::update('products', ['review_note' => 'Photos libres de droits en attendant les photos de l\'entreprise (provenance indiquée sur chaque photo).'], 'id = ?', [(int) $product['id']]);
    }
}
out("$added photo(s) ajoutée(s).");
