<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class ClientFollowUpReminder
{
    public const STATUS_OPEN = 'open';
    public const STATUS_COMPLETED = 'completed';
    public const TYPE_ADD_CLIENT_DATA = 'add_client_data';

    /**
     * @return array<string, string>
     */
    public static function reminderTypeOptions(): array
    {
        return [
            'add_client_data' => 'Add client data',
            'call_back_to_schedule' => 'Call back to schedule',
            'add_job_details' => 'Add job details',
        ];
    }

    public static function isAvailable(): bool
    {
        return SchemaInspector::hasTable('client_follow_up_reminders');
    }

    public static function labelForType(string $type): string
    {
        $type = strtolower(trim($type));
        $options = self::reminderTypeOptions();

        return $options[$type] ?? ucwords(str_replace('_', ' ', $type));
    }

    public static function actionUrlForType(string $type, int $clientId): string
    {
        if ($clientId <= 0) {
            return url('/clients');
        }

        return match (strtolower(trim($type))) {
            self::TYPE_ADD_CLIENT_DATA => url('/clients/' . (string) $clientId . '/edit'),
            'call_back_to_schedule' => url('/clients/' . (string) $clientId . '/contacts/create'),
            'add_job_details' => url('/jobs/create?client_id=' . (string) $clientId),
            default => url('/clients/' . (string) $clientId),
        };
    }

    /**
     * @param list<string> $types
     */
    public static function createManyForClient(
        int $businessId,
        int $clientId,
        array $types,
        int $ownerUserId,
        int $actorUserId
    ): int {
        if (!self::isAvailable() || $businessId <= 0 || $clientId <= 0 || $types === []) {
            return 0;
        }

        $created = 0;
        $allowed = array_keys(self::reminderTypeOptions());
        foreach ($types as $type) {
            $type = strtolower(trim((string) $type));
            if (!in_array($type, $allowed, true)) {
                continue;
            }
            if (self::create($businessId, $clientId, $type, $ownerUserId, $actorUserId) > 0) {
                $created++;
            }
        }

        return $created;
    }

    public static function create(
        int $businessId,
        int $clientId,
        string $reminderType,
        int $ownerUserId,
        int $actorUserId
    ): int {
        if (!self::isAvailable() || $businessId <= 0 || $clientId <= 0) {
            return 0;
        }

        $reminderType = strtolower(trim($reminderType));
        if (!array_key_exists($reminderType, self::reminderTypeOptions())) {
            return 0;
        }

        $sql = 'INSERT INTO client_follow_up_reminders (
                    business_id,
                    client_id,
                    reminder_type,
                    status,
                    owner_user_id,
                    created_by,
                    created_at,
                    updated_at
                ) VALUES (
                    :business_id,
                    :client_id,
                    :reminder_type,
                    :status,
                    :owner_user_id,
                    :created_by,
                    NOW(),
                    NOW()
                )';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'business_id' => $businessId,
            'client_id' => $clientId,
            'reminder_type' => $reminderType,
            'status' => self::STATUS_OPEN,
            'owner_user_id' => $ownerUserId > 0 ? $ownerUserId : null,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function openListForOwner(int $businessId, int $ownerUserId, int $limit = 20): array
    {
        if (!self::isAvailable() || $businessId <= 0) {
            return [];
        }

        $companySql = SchemaInspector::hasColumn('clients', 'company_name') ? 'c.company_name' : "''";
        $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF({$companySql}, ''), CONCAT('Client #', c.id))";
        $clientDeleted = SchemaInspector::hasColumn('clients', 'deleted_at') ? 'AND c.deleted_at IS NULL' : '';

        $ownerWhere = '1=1';
        if ($ownerUserId > 0 && SchemaInspector::hasColumn('client_follow_up_reminders', 'owner_user_id')) {
            $ownerWhere = '(r.owner_user_id = :owner_user_id OR r.owner_user_id IS NULL)';
        }

        $sql = "SELECT
                    r.id,
                    r.client_id,
                    r.reminder_type,
                    r.status,
                    r.owner_user_id,
                    r.created_at,
                    {$clientNameSql} AS client_name
                FROM client_follow_up_reminders r
                INNER JOIN clients c ON c.id = r.client_id AND c.business_id = r.business_id {$clientDeleted}
                WHERE r.business_id = :business_id
                  AND r.deleted_at IS NULL
                  AND LOWER(r.status) = 'open'
                  AND {$ownerWhere}
                ORDER BY r.created_at ASC, r.id ASC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        if ($ownerUserId > 0 && SchemaInspector::hasColumn('client_follow_up_reminders', 'owner_user_id')) {
            $stmt->bindValue(':owner_user_id', $ownerUserId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 50)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public static function complete(int $businessId, int $reminderId, int $actorUserId): bool
    {
        if (!self::isAvailable() || $businessId <= 0 || $reminderId <= 0) {
            return false;
        }

        $sql = 'UPDATE client_follow_up_reminders
                SET status = :status,
                    completed_by = :completed_by,
                    completed_at = NOW(),
                    updated_at = NOW()
                WHERE business_id = :business_id
                  AND id = :id
                  AND deleted_at IS NULL
                  AND LOWER(status) = :open_status';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'status' => self::STATUS_COMPLETED,
            'completed_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'id' => $reminderId,
            'open_status' => self::STATUS_OPEN,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function openForClientByType(int $businessId, int $clientId, string $reminderType): ?array
    {
        if (!self::isAvailable() || $businessId <= 0 || $clientId <= 0) {
            return null;
        }

        $reminderType = strtolower(trim($reminderType));
        if (!array_key_exists($reminderType, self::reminderTypeOptions())) {
            return null;
        }

        $sql = 'SELECT id, client_id, reminder_type, status, owner_user_id, created_at, complete_prompt_dismissed_at
                FROM client_follow_up_reminders
                WHERE business_id = :business_id
                  AND client_id = :client_id
                  AND reminder_type = :reminder_type
                  AND deleted_at IS NULL
                  AND LOWER(status) = :open_status
                ORDER BY id ASC
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'business_id' => $businessId,
            'client_id' => $clientId,
            'reminder_type' => $reminderType,
            'open_status' => self::STATUS_OPEN,
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Auto-check the complete checkbox until the user saves an edit with it unchecked.
     *
     * @param array<string, mixed>|null $reminder
     */
    public static function shouldAutoCheckCompleteOnEdit(int $businessId, int $clientId, ?array $reminder = null): bool
    {
        if ($businessId <= 0 || $clientId <= 0) {
            return false;
        }

        $reminder ??= self::openForClientByType($businessId, $clientId, self::TYPE_ADD_CLIENT_DATA);
        if ($reminder === null) {
            return false;
        }

        if (!SchemaInspector::hasColumn('client_follow_up_reminders', 'complete_prompt_dismissed_at')) {
            return true;
        }

        return trim((string) ($reminder['complete_prompt_dismissed_at'] ?? '')) === '';
    }

    public static function dismissCompletePromptForClientByType(
        int $businessId,
        int $clientId,
        string $reminderType
    ): bool {
        if (!self::isAvailable() || $businessId <= 0 || $clientId <= 0) {
            return false;
        }

        if (!SchemaInspector::hasColumn('client_follow_up_reminders', 'complete_prompt_dismissed_at')) {
            return false;
        }

        $reminderType = strtolower(trim($reminderType));
        if (!array_key_exists($reminderType, self::reminderTypeOptions())) {
            return false;
        }

        $sql = 'UPDATE client_follow_up_reminders
                SET complete_prompt_dismissed_at = NOW(),
                    updated_at = NOW()
                WHERE business_id = :business_id
                  AND client_id = :client_id
                  AND reminder_type = :reminder_type
                  AND deleted_at IS NULL
                  AND LOWER(status) = :open_status
                  AND complete_prompt_dismissed_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'business_id' => $businessId,
            'client_id' => $clientId,
            'reminder_type' => $reminderType,
            'open_status' => self::STATUS_OPEN,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function completeOpenForClientByType(
        int $businessId,
        int $clientId,
        string $reminderType,
        int $actorUserId
    ): bool {
        if (!self::isAvailable() || $businessId <= 0 || $clientId <= 0) {
            return false;
        }

        $reminderType = strtolower(trim($reminderType));
        if (!array_key_exists($reminderType, self::reminderTypeOptions())) {
            return false;
        }

        $sql = 'UPDATE client_follow_up_reminders
                SET status = :status,
                    completed_by = :completed_by,
                    completed_at = NOW(),
                    updated_at = NOW()
                WHERE business_id = :business_id
                  AND client_id = :client_id
                  AND reminder_type = :reminder_type
                  AND deleted_at IS NULL
                  AND LOWER(status) = :open_status';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'status' => self::STATUS_COMPLETED,
            'completed_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'client_id' => $clientId,
            'reminder_type' => $reminderType,
            'open_status' => self::STATUS_OPEN,
        ]);

        return $stmt->rowCount() > 0;
    }
}
