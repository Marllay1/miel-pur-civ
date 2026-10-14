<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Téléversement d'images : vérification du type réel, ré-encodage GD en WebP (supprime tout contenu caché),
 * nom aléatoire, stockage dans public/uploads (exécution de scripts interdite par .htaccess).
 */
final class ImageService
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_SIDE = 1600;

    /** @return array{ok:bool, path?:string, error?:string} */
    public static function store(array $file, string $subdir = 'products'): array
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
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
            IMAGETYPE_PNG => @imagecreatefrompng($tmp),
            IMAGETYPE_WEBP => @imagecreatefromwebp($tmp),
        };
        if (!$src) {
            return ['ok' => false, 'error' => 'Image illisible.'];
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, self::MAX_SIDE / max($w, $h));
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
        imagedestroy($src);
        imagedestroy($dst);
        if (!$ok) {
            return ['ok' => false, 'error' => 'Enregistrement impossible.'];
        }
        return ['ok' => true, 'path' => '/uploads/' . $subdir . '/' . $name];
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
    }
}
