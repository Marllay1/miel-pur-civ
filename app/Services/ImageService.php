<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Téléversement d'images : vérification du type réel, ré-encodage GD en WebP (supprime tout contenu caché),
 * nom aléatoire, stockage dans public/uploads (exécution de scripts interdite par .htaccess).
 * Copies réduites (480 et 960 px de large, suffixes -480 et -960) servies aux vignettes et aux petits écrans (srcset).
 */
final class ImageService
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_SIDE = 1600;
    /** Largeurs des copies réduites générées au téléversement. */
    public const VARIANTS = [480, 960];

    /** @return array{ok:bool, path?:string, error?:string} */
    /**
     * @param int $maxSide plus grand côté conservé (images de carrousel : 2400 ; logos et produits : 1600)
     * @param array{minWidth?:int, minHeight?:int} $constraints dimensions minimales exigées
     */
    public static function store(array $file, string $subdir = 'products', int $maxSide = self::MAX_SIDE, array $constraints = []): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['ok' => false, 'error' => 'Fichier invalide.'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => $file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE ? 'Fichier trop volumineux.' : 'Échec du téléversement.'];
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'Fichier trop volumineux (5 Mo maximum).'];
        }
        $tmp = (string) $file['tmp_name'];
        if (!is_file($tmp) || (PHP_SAPI !== 'cli' && !is_uploaded_file($tmp))) {
            return ['ok' => false, 'error' => 'Fichier invalide.'];
        }
        $info = @getimagesize($tmp);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return ['ok' => false, 'error' => 'Formats acceptés : JPEG, PNG, WebP.'];
        }
        if ($info[0] > 8000 || $info[1] > 8000) {
            return ['ok' => false, 'error' => 'Image trop grande (8000 px maximum).'];
        }
        if ($info[0] < ($constraints['minWidth'] ?? 0) || $info[1] < ($constraints['minHeight'] ?? 0)) {
            return ['ok' => false, 'error' => sprintf('Image trop petite (%d × %d px) : %d × %d px minimum.', $info[0], $info[1], $constraints['minWidth'] ?? 0, $constraints['minHeight'] ?? 0)];
        }
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
            IMAGETYPE_PNG => @imagecreatefrompng($tmp),
            IMAGETYPE_WEBP => @imagecreatefromwebp($tmp),
        };
        if (!$src) {
            return ['ok' => false, 'error' => 'Image illisible.'];
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, $maxSide / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $dir = BASE_PATH . '/public/uploads/' . $subdir;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return ['ok' => false, 'error' => 'Dossier de destination indisponible.'];
        }
        $name = gmdate('Ym') . '-' . bin2hex(random_bytes(10)) . '.webp';
        $ok = imagewebp($dst, $dir . '/' . $name, 82);
        if ($ok) {
            self::writeVariants($dst, $dir . '/' . $name);
        }
        imagedestroy($src);
        imagedestroy($dst);
        if (!$ok) {
            return ['ok' => false, 'error' => 'Enregistrement impossible.'];
        }
        return ['ok' => true, 'path' => '/uploads/' . $subdir . '/' . $name, 'width' => $nw, 'height' => $nh];
    }

    public static function delete(string $path): void
    {
        if (!str_starts_with($path, '/uploads/') || str_contains($path, '..')) {
            return;
        }
        $file = BASE_PATH . '/public' . $path;
        if (is_file($file)) {
            @unlink($file);
        }
        foreach (self::VARIANTS as $w) {
            $variant = self::variantFile($file, $w);
            if ($variant !== null && is_file($variant)) {
                @unlink($variant);
            }
        }
    }

    /** Fichier de la copie réduite d'une image WebP (ex. photo.webp → photo-480.webp). */
    private static function variantFile(string $file, int $width): ?string
    {
        return str_ends_with($file, '.webp') ? substr($file, 0, -5) . '-' . $width . '.webp' : null;
    }

    /** Écrit les copies réduites plus étroites que l'image (les copies plus larges que l'original seraient inutiles). */
    public static function writeVariants(\GdImage $img, string $file): void
    {
        [$w, $h] = [imagesx($img), imagesy($img)];
        foreach (self::VARIANTS as $vw) {
            $target = self::variantFile($file, $vw);
            if ($target === null || $vw >= $w) {
                continue;
            }
            $copy = imagecreatetruecolor($vw, max(1, (int) round($h * $vw / $w)));
            imagealphablending($copy, false);
            imagesavealpha($copy, true);
            imagecopyresampled($copy, $img, 0, 0, 0, 0, $vw, imagesy($copy), $w, $h);
            imagewebp($copy, $target, 78);
            imagedestroy($copy);
        }
    }

    /** Copie réduite existante d'une image téléversée (chemin public), sinon l'image d'origine. */
    public static function small(string $path, int $width = 480): string
    {
        if (!str_starts_with($path, '/uploads/') || str_contains($path, '..')) {
            return $path;
        }
        $file = self::variantFile(BASE_PATH . '/public' . $path, $width);
        return $file !== null && is_file($file) ? substr($path, 0, -5) . '-' . $width . '.webp' : $path;
    }

    /** Valeur srcset (copies réduites + original) d'une image téléversée ; chaîne vide si aucune copie n'existe. */
    public static function srcset(string $path): string
    {
        if (!str_starts_with($path, '/uploads/') || str_contains($path, '..') || !str_ends_with($path, '.webp')) {
            return '';
        }
        $file = BASE_PATH . '/public' . $path;
        $parts = [];
        foreach (self::VARIANTS as $w) {
            if (is_file((string) self::variantFile($file, $w))) {
                $parts[] = substr($path, 0, -5) . '-' . $w . '.webp ' . $w . 'w';
            }
        }
        if ($parts === [] || !is_file($file) || ($size = @getimagesize($file)) === false) {
            return '';
        }
        $parts[] = $path . ' ' . $size[0] . 'w';
        return implode(', ', $parts);
    }
}
