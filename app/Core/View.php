<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Gabarits PHP. Ne jamais déclarer de fonction nommée dans un gabarit (utiliser des closures).
 */
final class View
{
    public static array $shared = [];

    public static function render(string $template, array $data = [], ?string $layout = 'layouts/main'): string
    {
        $content = self::capture($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::capture($layout, array_merge($data, ['content' => $content]));
    }

    public static function partial(string $template, array $data = []): string
    {
        return self::capture('partials/' . $template, $data);
    }

    private static function capture(string $__template, array $__data): string
    {
        $__file = APP_PATH . '/Views/' . $__template . '.php';
        if (!is_file($__file)) {
            throw new \RuntimeException('Gabarit introuvable : ' . $__template);
        }
        extract(array_merge(self::$shared, $__data), EXTR_SKIP);
        ob_start();
        try {
            include $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
