<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Journal d'audit des actions sensibles (administration, paiements anormaux).
 */
final class Audit
{
    public static string $ip = '';

    public static function log(string $action, ?string $entityType = null, int|string|null $entityId = null, array $details = [], ?int $userId = null): void
    {
        DB::insert('audit_log', [
            'user_id' => $userId ?? Auth::id(),
            'action' => mb_substr($action, 0, 80),
            'entity_type' => $entityType,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) : null,
            'ip' => self::$ip !== '' ? self::$ip : null,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
