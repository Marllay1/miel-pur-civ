<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Génération des icônes du site (favicon.ico 16/32/48, PNG 16/32, icône iOS 180, icônes 192/512, icône « maskable »,
 * manifeste) à partir d'un emblème, sans déformation (image centrée, proportions conservées).
 * Utilisé par bin/brand.php et par Administration → Paramètres du site → Logos et images.
 */
final class BrandIcons
{
    /** Retire les marges transparentes ou unies ; conserve la transparence. */
    public static function trim(\GdImage $img): \GdImage
    {
        $cropped = @imagecropauto($img, IMG_CROP_TRANSPARENT) ?: @imagecropauto($img, IMG_CROP_SIDES);
        $result = $cropped instanceof \GdImage ? $cropped : $img;
        imagealphablending($result, false);
        imagesavealpha($result, true);
        return $result;
    }

    /** Carré de $size px : image centrée, marge $pad (fraction), fond $bg [r,g,b] ou transparent. */
    public static function square(\GdImage $src, int $size, float $pad = 0.08, ?array $bg = null): \GdImage
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

    public static function png(\GdImage $img): string
    {
        ob_start();
        imagepng($img, null, 9);
        return (string) ob_get_clean();
    }

    /** ICO contenant des images PNG (format accepté par tous les navigateurs actuels). */
    public static function writeIco(string $path, array $pngs): void
    {
        $count = count($pngs);
        $dirEntries = '';
        $data = '';
        $offset = 6 + 16 * $count;
        foreach ($pngs as $size => $bytes) {
            $dirEntries .= pack('CCCCvvVV', $size >= 256 ? 0 : $size, $size >= 256 ? 0 : $size, 0, 0, 1, 32, strlen($bytes), $offset);
            $data .= $bytes;
            $offset += strlen($bytes);
        }
        file_put_contents($path, pack('vvv', 0, 1, $count) . $dirEntries . $data);
    }

    /**
     * Écrit toutes les icônes dans $dir (URL publique $urlBase) ; copie favicon.ico à la racine publique si $rootIco.
     * @param array{0:int,1:int,2:int} $bg fond des icônes opaques (iOS, Android « maskable »)
     */
    public static function generate(\GdImage $src, string $dir, string $urlBase, array $bg, string $themeColor, string $name, bool $rootIco = true): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $src = self::trim($src);
        $pngs = [];
        foreach ([16, 32, 48] as $s) {
            $pngs[$s] = self::png(self::square($src, $s, $s <= 16 ? 0.02 : 0.05));
        }
        self::writeIco($dir . '/favicon.ico', $pngs);
        if ($rootIco) {
            @copy($dir . '/favicon.ico', BASE_PATH . '/public/favicon.ico');
        }
        file_put_contents($dir . '/favicon-16.png', $pngs[16]);
        file_put_contents($dir . '/favicon-32.png', $pngs[32]);
        imagepng(self::square($src, 180, 0.1, $bg), $dir . '/apple-touch-icon.png', 9);
        imagepng(self::square($src, 192, 0.1), $dir . '/icon-192.png', 9);
        imagepng(self::square($src, 512, 0.1), $dir . '/icon-512.png', 9);
        imagepng(self::square($src, 512, 0.2, $bg), $dir . '/icon-maskable-512.png', 9);
        file_put_contents($dir . '/site.webmanifest', json_encode([
            'name' => $name,
            'short_name' => mb_substr($name, 0, 12),
            'start_url' => '/',
            'display' => 'browser',
            'background_color' => '#FFFFFF',
            'theme_color' => $themeColor,
            'icons' => [
                ['src' => $urlBase . '/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => $urlBase . '/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => $urlBase . '/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** Charge une image raster téléversée (PNG, WebP, JPEG) avec sa transparence. */
    public static function load(string $path): ?\GdImage
    {
        $info = @getimagesize($path);
        $img = match ($info['mime'] ?? '') {
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/jpeg' => @imagecreatefromjpeg($path),
            default => false,
        };
        if (!$img instanceof \GdImage) {
            return null;
        }
        imagepalettetotruecolor($img);
        imagealphablending($img, true);
        imagesavealpha($img, true);
        return $img;
    }
}
