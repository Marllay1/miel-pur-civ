<?php
declare(strict_types=1);

/**
 * Identité visuelle : installe le logo officiel et génère les icônes du site (onglet, écran d'accueil, manifeste).
 *
 *   php bin/brand.php --logo=chemin/logo.png [--icon=chemin/embleme.png] [--background=#FFFFFF]
 *       --logo : logo officiel (PNG, WebP ou JPEG ≥ 400 px de large, ou SVG). Affiché dans les en-têtes, proportions conservées.
 *       --icon : version carrée/emblème du logo pour les favicons (recommandé : un logo avec texte devient illisible en 16 px).
 *                À défaut, les favicons sont produits à partir de --logo (image raster), centrée sans déformation.
 *       --background : fond de l'icône écran d'accueil iOS (qui n'accepte pas la transparence). Par défaut : blanc.
 *       --logo-dark, --logo-stacked, --logo-stacked-dark : déclinaisons facultatives (texte clair pour le thème sombre ;
 *       version empilée emblème + texte pour le pied de page et les pages de connexion).
 *   php bin/brand.php --placeholder [--font=chemin/police.ttf]
 *       Icônes PROVISOIRES neutres (monogramme « M ») en attendant le logo officiel ; aucun logo n'est installé.
 *   php bin/brand.php --remove-logo
 *       Retire le logo installé (retour au nom en toutes lettres).
 *
 * Fichiers produits dans public/assets/img/brand/ : logo.*, favicon.ico (16/32/48), favicon-16.png, favicon-32.png,
 * favicon.svg (si fourni ou provisoire), apple-touch-icon.png (180), icon-192.png, icon-512.png, site.webmanifest ;
 * copie de favicon.ico à la racine publique (/favicon.ico, demandé automatiquement par les navigateurs).
 */

require __DIR__ . '/_cli.php';

use App\Services\Settings;

if (!extension_loaded('gd')) {
    fail('Extension GD requise (php.ini : extension=gd).');
}
$opts = getopt('', ['logo:', 'logo-dark:', 'logo-stacked:', 'logo-stacked-dark:', 'icon:', 'background:', 'placeholder', 'font:', 'remove-logo']);
$dir = BASE_PATH . '/public/assets/img/brand';
if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    fail("Impossible de créer $dir");
}

/** Charge une image raster (PNG, WebP, JPEG) en conservant la transparence. */
function load_image(string $path): \GdImage
{
    if (!is_file($path)) {
        fail("Fichier introuvable : $path");
    }
    $info = @getimagesize($path);
    $img = match ($info['mime'] ?? '') {
        'image/png' => @imagecreatefrompng($path),
        'image/webp' => @imagecreatefromwebp($path),
        'image/jpeg' => @imagecreatefromjpeg($path),
        default => false,
    };
    if (!$img instanceof \GdImage) {
        fail("Format non pris en charge (PNG, WebP ou JPEG attendu) : $path");
    }
    imagepalettetotruecolor($img);
    imagealphablending($img, true);
    imagesavealpha($img, true);
    return $img;
}

/** Retire les marges transparentes ou unies autour du dessin (le logo occupe alors toute l'icône). */
function trim_image(\GdImage $img): \GdImage
{
    $cropped = @imagecropauto($img, IMG_CROP_TRANSPARENT) ?: @imagecropauto($img, IMG_CROP_SIDES);
    $result = $cropped instanceof \GdImage ? $cropped : $img;
    // L'image recadrée est une nouvelle ressource : sans ces réglages, sa transparence serait perdue (fond noir).
    imagealphablending($result, false);
    imagesavealpha($result, true);
    return $result;
}

/** Image carrée de $size px : $src centrée, proportions conservées, marge $pad (fraction), fond $bg (null = transparent). */
function square(\GdImage $src, int $size, float $pad = 0.08, ?array $bg = null): \GdImage
{
    $out = imagecreatetruecolor($size, $size);
    imagesavealpha($out, true);
    imagealphablending($out, false);
    $fill = $bg === null ? imagecolorallocatealpha($out, 0, 0, 0, 127) : imagecolorallocate($out, $bg[0], $bg[1], $bg[2]);
    imagefill($out, 0, 0, $fill);
    imagealphablending($out, true);
    $box = (int) round($size * (1 - 2 * $pad));
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min($box / $w, $box / $h);
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    imagecopyresampled($out, $src, (int) (($size - $nw) / 2), (int) (($size - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
    return $out;
}

function png_bytes(\GdImage $img): string
{
    ob_start();
    imagepng($img, null, 9);
    return (string) ob_get_clean();
}

/** Fichier ICO contenant des images PNG (format accepté par tous les navigateurs actuels et Windows Vista+). */
function write_ico(string $path, array $pngs): void
{
    $count = count($pngs);
    $head = pack('vvv', 0, 1, $count);
    $dirEntries = '';
    $data = '';
    $offset = 6 + 16 * $count;
    foreach ($pngs as $size => $bytes) {
        $dirEntries .= pack('CCCCvvVV', $size >= 256 ? 0 : $size, $size >= 256 ? 0 : $size, 0, 0, 1, 32, strlen($bytes), $offset);
        $data .= $bytes;
        $offset += strlen($bytes);
    }
    file_put_contents($path, $head . $dirEntries . $data);
}

function hex_color(string $hex): array
{
    if (!preg_match('/^#?([0-9a-f]{6})$/i', $hex, $m)) {
        fail("Couleur invalide : $hex (format #RRGGBB)");
    }
    return array_map('hexdec', str_split($m[1], 2));
}

/** Génère l'ensemble des icônes à partir d'une image source carrée ou non. */
function write_icons(string $dir, \GdImage $src, array $bg, string $themeColor): void
{
    try {
        $name = Settings::get('store_name') ?: "Miel Pur de Côte d'Ivoire";
    } catch (\Throwable) {
        $name = "Miel Pur de Côte d'Ivoire"; // base indisponible : nom par défaut
    }
    \App\Services\BrandIcons::generate($src, $dir, '/assets/img/brand', $bg, $themeColor, $name);
}

if (isset($opts['remove-logo'])) {
    foreach (array_merge(glob($dir . '/logo.*') ?: [], glob($dir . '/logo-*.png') ?: []) as $f) {
        unlink($f);
        out('✔ Retiré : ' . basename($f));
    }
    exit(0);
}

if (isset($opts['placeholder'])) {
    $font = $opts['font'] ?? null;
    foreach ([$font, 'C:/Windows/Fonts/segoeuib.ttf', 'C:/Windows/Fonts/arialbd.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'] as $candidate) {
        if (is_string($candidate) && is_file($candidate)) {
            $font = $candidate;
            break;
        }
    }
    if (!is_string($font) || !is_file($font)) {
        fail('Police TrueType introuvable : indiquez --font=chemin/police.ttf');
    }
    $size = 512;
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);
    imagealphablending($img, false);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
    imagealphablending($img, true);
    imagefilledellipse($img, 256, 256, 508, 508, imagecolorallocate($img, 0xE6, 0xA9, 0x3A));
    $dark = imagecolorallocate($img, 0x1A, 0x12, 0x06);
    $box = imagettfbbox(300, 0, $font, 'M');
    $tw = $box[2] - $box[0];
    $th = $box[1] - $box[7];
    imagettftext($img, 300, 0, (int) (($size - $tw) / 2 - $box[0]), (int) (($size + $th) / 2), $dark, $font, 'M');
    write_icons($dir, $img, [0xE6, 0xA9, 0x3A], '#E6A93A');
    file_put_contents($dir . '/favicon.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><circle cx="32" cy="32" r="32" fill="#E6A93A"/>'
        . '<text x="32" y="45" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-weight="700" font-size="38" fill="#1A1206">M</text></svg>');
    file_put_contents($dir . '/PROVISOIRE.txt', "Icônes provisoires (monogramme « M ») générées le " . gmdate('Y-m-d') . " en attendant le logo officiel.\nRemplacer avec : php bin/brand.php --logo=logo.png --icon=embleme.png\n");
    out('✔ Icônes PROVISOIRES générées (monogramme neutre, aucun logo installé). Remplacez-les dès réception du logo officiel.');
    exit(0);
}

if (!isset($opts['logo'])) {
    fwrite(STDERR, "Usage : php bin/brand.php --logo=logo.png [--icon=embleme.png] [--background=#FFFFFF] | --placeholder | --remove-logo\n");
    exit(2);
}

$logoPath = (string) $opts['logo'];
$bg = hex_color((string) ($opts['background'] ?? '#FFFFFF'));
foreach (glob($dir . '/logo.*') ?: [] as $f) {
    unlink($f);
}
$iconSource = null;
if (str_ends_with(strtolower($logoPath), '.svg')) {
    $svg = (string) @file_get_contents($logoPath);
    if (!str_contains($svg, '<svg') || preg_match('/<script|on[a-z]+\s*=|javascript:/i', $svg)) {
        fail('SVG invalide ou contenant du script : refusé.');
    }
    file_put_contents($dir . '/logo.svg', $svg);
    out('✔ Logo SVG installé : public/assets/img/brand/logo.svg');
} else {
    $logo = trim_image(load_image($logoPath));
    if (imagesx($logo) < 200) {
        out('⚠ Logo de petite taille (' . imagesx($logo) . ' px de large) : une version d\'au moins 400 px est recommandée.');
    }
    // Largeur limitée à 800 px (affiché à 40-64 px de haut, écrans haute densité compris), proportions conservées.
    if (imagesx($logo) > 800) {
        $logo = imagescale($logo, 800, (int) round(imagesy($logo) * 800 / imagesx($logo)), IMG_BICUBIC);
        imagesavealpha($logo, true);
    }
    imagepng($logo, $dir . '/logo.png', 9);
    out('✔ Logo installé : public/assets/img/brand/logo.png (' . imagesx($logo) . '×' . imagesy($logo) . ' px)');
    $iconSource = $logo;
}
// Déclinaisons facultatives : texte clair pour le thème sombre, version empilée (pied de page, connexion).
foreach (['logo-dark', 'logo-stacked', 'logo-stacked-dark'] as $variant) {
    @unlink($dir . '/' . $variant . '.png');
    if (!isset($opts[$variant])) {
        continue;
    }
    $img = trim_image(load_image((string) $opts[$variant]));
    if (imagesx($img) > 800) {
        $img = imagescale($img, 800, (int) round(imagesy($img) * 800 / imagesx($img)), IMG_BICUBIC);
        imagesavealpha($img, true);
    }
    imagepng($img, $dir . '/' . $variant . '.png', 9);
    out('✔ Déclinaison installée : ' . $variant . '.png (' . imagesx($img) . '×' . imagesy($img) . ' px)');
}
if (isset($opts['icon'])) {
    $iconSource = load_image((string) $opts['icon']);
}
if ($iconSource === null) {
    out('⚠ Logo SVG sans --icon : favicons non régénérés. Fournissez --icon=embleme.png (PNG carré ≥ 512 px).');
    exit(0);
}
@unlink($dir . '/favicon.svg');
@unlink($dir . '/PROVISOIRE.txt');
write_icons($dir, $iconSource, $bg, '#184626');
out('✔ Favicons générés : favicon.ico (16/32/48), PNG 16/32, apple-touch-icon 180, icônes 192/512 et manifeste.');
out('  Vérifiez l\'onglet du navigateur (rechargement forcé : Ctrl+F5) et l\'icône d\'écran d\'accueil sur mobile.');
