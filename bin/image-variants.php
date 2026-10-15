<?php
declare(strict_types=1);

/**
 * Génère les copies réduites (480 et 960 px) des images déjà téléversées qui n'en ont pas
 * (produits, photothèque, carrousel). Les nouvelles images en reçoivent automatiquement au téléversement.
 *   php bin/image-variants.php
 */

require __DIR__ . '/_cli.php';

use App\Services\ImageService;

$done = 0;
$skipped = 0;
foreach (['products', 'media', 'slides'] as $dir) {
    foreach (glob(BASE_PATH . '/public/uploads/' . $dir . '/*.webp') ?: [] as $file) {
        if (preg_match('/-\d+\.webp$/', $file)) {
            continue; // copie réduite elle-même
        }
        $missing = array_filter(ImageService::VARIANTS, static fn (int $w) => !is_file(substr($file, 0, -5) . '-' . $w . '.webp'));
        $img = $missing ? @imagecreatefromwebp($file) : null;
        if (!$img) {
            $skipped++;
            continue;
        }
        ImageService::writeVariants($img, $file);
        imagedestroy($img);
        $done++;
        out('✓ ' . $dir . '/' . basename($file));
    }
}
out("$done image(s) traitée(s), $skipped déjà à jour ou illisible(s).");
