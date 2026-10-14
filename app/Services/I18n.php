<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Lang;

/**
 * Traductions des contenus (produits, catégories, pages, FAQ, zones).
 * Le français est stocké dans les tables d'origine ; les autres langues dans `translations`.
 * Si une traduction manque, le texte français est conservé.
 */
final class I18n
{
    public const FIELDS = [
        'product' => ['name', 'short_description', 'description', 'origin', 'usage_tips', 'storage_tips', 'meta_title', 'meta_description'],
        'category' => ['name', 'description'],
        'variant' => ['label'],
        'page' => ['title', 'body', 'meta_description'],
        'faq' => ['question', 'answer', 'category'],
        'zone' => ['name', 'description', 'delay_text'],
    ];

    /** Applique les traductions de la langue courante à une liste de lignes (clé d'identifiant configurable). */
    public static function localize(string $type, array $rows, string $idKey = 'id', array $map = []): array
    {
        if (Lang::isDefault() || $rows === []) {
            return $rows;
        }
        $ids = array_values(array_unique(array_filter(array_map(static fn ($r) => (int) ($r[$idKey] ?? 0), $rows))));
        if ($ids === []) {
            return $rows;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $found = [];
        foreach (DB::all("SELECT entity_id, field, value FROM translations WHERE entity_type = ? AND locale = ? AND entity_id IN ($placeholders)", array_merge([$type, Lang::get()], $ids)) as $t) {
            $found[(int) $t['entity_id']][$t['field']] = $t['value'];
        }
        foreach ($rows as &$row) {
            $tr = $found[(int) ($row[$idKey] ?? 0)] ?? [];
            foreach ($tr as $field => $value) {
                $target = $map[$field] ?? $field;
                if ($value !== '' && array_key_exists($target, $row)) {
                    $row[$target] = $value;
                }
            }
        }
        return $rows;
    }

    public static function localizeOne(string $type, ?array $row, string $idKey = 'id', array $map = []): ?array
    {
        return $row === null ? null : self::localize($type, [$row], $idKey, $map)[0];
    }

    /** @return array<string,string> traductions existantes d'une entité */
    public static function get(string $type, int $id, string $locale = 'en'): array
    {
        $out = [];
        foreach (DB::all('SELECT field, value FROM translations WHERE entity_type = ? AND entity_id = ? AND locale = ?', [$type, $id, $locale]) as $t) {
            $out[$t['field']] = $t['value'];
        }
        return $out;
    }

    /** Enregistre (ou supprime si vide) les traductions d'une entité. */
    public static function save(string $type, int $id, array $values, string $locale = 'en'): void
    {
        $now = gmdate('Y-m-d H:i:s');
        foreach (self::FIELDS[$type] ?? [] as $field) {
            if (!array_key_exists($field, $values)) {
                continue;
            }
            $value = trim((string) $values[$field]);
            DB::run('DELETE FROM translations WHERE entity_type = ? AND entity_id = ? AND locale = ? AND field = ?', [$type, $id, $locale, $field]);
            if ($value !== '') {
                DB::insert('translations', ['entity_type' => $type, 'entity_id' => $id, 'locale' => $locale, 'field' => $field, 'value' => $value, 'updated_at' => $now]);
            }
        }
    }
}
