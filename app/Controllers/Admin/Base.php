<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\I18n;

abstract class Base
{
    protected function uid(): int
    {
        return (int) Auth::id();
    }

    /**
     * Enregistre la traduction anglaise envoyée par le formulaire (champs en[...]) ; seuls les champs
     * prévus par I18n::FIELDS sont acceptés. Sans champ en[...] dans la requête, rien n'est modifié.
     */
    protected function saveTranslation(Request $r, string $type, int $id): void
    {
        $input = $r->post['en'] ?? null;
        if (!is_array($input)) {
            return;
        }
        $values = [];
        foreach (I18n::FIELDS[$type] ?? [] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $values[$field] = mb_substr(str_replace("\0", '', $input[$field]), 0, 20000);
            }
        }
        $before = I18n::get($type, $id);
        I18n::save($type, $id, $values);
        $changed = array_keys(array_filter($values, static fn ($v, $k) => trim($v) !== ($before[$k] ?? ''), ARRAY_FILTER_USE_BOTH));
        if ($changed !== []) {
            Audit::log('translation.save', $type, $id, ['locale' => 'en', 'fields' => $changed]);
        }
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
