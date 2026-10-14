<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Response;

abstract class Base
{
    protected function uid(): int
    {
        return (int) Auth::id();
    }

    /** Valeur de formulaire « datetime-local » → « Y-m-d H:i:s » (UTC = heure d'Abidjan) ou null. */
    protected function datetime(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime(str_replace('T', ' ', $value));
        return $ts === false ? null : gmdate('Y-m-d H:i:s', $ts);
    }

    protected function csv(string $filename, array $header, iterable $rows): Response
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $header, ';', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($out, array_map('csv_safe', $row), ';', '"', '\\');
        }
        rewind($out);
        $body = (string) stream_get_contents($out);
        fclose($out);
        return new Response($body, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    protected function page(\App\Core\Request $r): int
    {
        return max(1, (int) $r->int('page', 1));
    }
}
