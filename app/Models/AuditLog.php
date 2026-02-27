<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class AuditLog
{
    public static function write(
        string $action,
        string $entity,
        ?int $entityId,
        ?int $businessId,
        ?int $userId,
        array $meta = []
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO activity_logs (
                business_id, user_id, action, entity, entity_id, metadata_json, created_at
             ) VALUES (
                :business_id, :user_id, :action, :entity, :entity_id, :metadata_json, NOW()
             )'
        );

        $stmt->execute([
            'business_id' => $businessId,
            'user_id' => $userId,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'metadata_json' => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
