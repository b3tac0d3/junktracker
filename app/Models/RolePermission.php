<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Throwable;

final class RolePermission
{
    private const ACTION_COLUMN = [
        'view' => 'can_view',
        'create' => 'can_create',
        'edit' => 'can_edit',
        'delete' => 'can_delete',
    ];

    private const MODULES = [
        'dashboard' => 'Dashboard',
        'jobs' => 'Jobs',
        'prospects' => 'Prospects',
        'sales' => 'Sales',
        'customers' => 'Customers',
        'clients' => 'Clients',
        'estates' => 'Estates',
        'companies' => 'Companies',
        'client_contacts' => 'Client Contacts',
        'consignors' => 'Consignors',
        'time_tracking' => 'Time Tracking',
        'expenses' => 'Expenses',
        'tasks' => 'Tasks',
        'employees' => 'Employees',
        'admin' => 'Admin',
        'users' => 'Users',
        'expense_categories' => 'Expense Categories',
        'disposal_locations' => 'Disposal Locations',
        'admin_settings' => 'Admin Settings',
        'audit_log' => 'Audit Log',
        'recovery' => 'Recovery',
        'lookups' => 'Lookups',
        'permissions' => 'Permissions',
    ];

    private static ?bool $available = null;
    private static array $cache = [];

    public static function roleOptions(): array
    {
        return [
            1 => 'User',
            2 => 'Manager',
            3 => 'Admin',
            99 => 'Dev',
        ];
    }

    public static function modules(): array
    {
        return self::MODULES;
    }

    public static function isAvailable(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }

        try {
            $schema = (string) config('database.database', '');
            if ($schema === '') {
                self::$available = false;
                return false;
            }

            $stmt = Database::connection()->prepare(
                'SELECT 1
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = :schema
                   AND TABLE_NAME = :table
                 LIMIT 1'
            );
            $stmt->execute([
                'schema' => $schema,
                'table' => 'role_permissions',
            ]);
            self::$available = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            self::$available = false;
        }

        return self::$available;
    }

    public static function allows(int $role, string $module, string $action): bool
    {
        $moduleKey = trim($module);
        $actionKey = trim($action);

        if ($role === 99) {
            return true;
        }

        if ($moduleKey === '' || !isset(self::MODULES[$moduleKey])) {
            return true;
        }

        $column = self::ACTION_COLUMN[$actionKey] ?? self::ACTION_COLUMN['view'];
        if (!self::isAvailable()) {
            return true;
        }

        $cacheKey = $role . '|' . $moduleKey;
        if (!isset(self::$cache[$cacheKey])) {
            self::$cache[$cacheKey] = self::fetchRow($role, $moduleKey);
        }
        $row = self::$cache[$cacheKey];

        if ($row === null) {
            return true;
        }

        return (int) ($row[$column] ?? 0) === 1;
    }

    public static function matrixForRole(int $role): array
    {
        $matrix = [];
        foreach (self::MODULES as $module => $label) {
            $matrix[$module] = [
                'label' => $label,
                'view' => self::allows($role, $module, 'view'),
                'create' => self::allows($role, $module, 'create'),
                'edit' => self::allows($role, $module, 'edit'),
                'delete' => self::allows($role, $module, 'delete'),
            ];
        }

        return $matrix;
    }

    public static function saveRoleMatrix(int $role, array $matrix, ?int $actorId = null): void
    {
        if (!self::isAvailable()) {
            return;
        }

        if (!array_key_exists($role, self::roleOptions())) {
            return;
        }

        $pdo = Database::connection();
        $sql = 'INSERT INTO role_permissions
                    (role_value, module_key, can_view, can_create, can_edit, can_delete, updated_by, updated_at)
                VALUES
                    (:role_value, :module_key, :can_view, :can_create, :can_edit, :can_delete, :updated_by, NOW())
                ON DUPLICATE KEY UPDATE
                    can_view = VALUES(can_view),
                    can_create = VALUES(can_create),
                    can_edit = VALUES(can_edit),
                    can_delete = VALUES(can_delete),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()';
        $stmt = $pdo->prepare($sql);

        foreach (self::MODULES as $module => $_label) {
            $row = is_array($matrix[$module] ?? null) ? $matrix[$module] : [];
            $stmt->execute([
                'role_value' => $role,
                'module_key' => $module,
                'can_view' => !empty($row['view']) ? 1 : 0,
                'can_create' => !empty($row['create']) ? 1 : 0,
                'can_edit' => !empty($row['edit']) ? 1 : 0,
                'can_delete' => !empty($row['delete']) ? 1 : 0,
                'updated_by' => $actorId,
            ]);
            self::$cache[$role . '|' . $module] = [
                'can_view' => !empty($row['view']) ? 1 : 0,
                'can_create' => !empty($row['create']) ? 1 : 0,
                'can_edit' => !empty($row['edit']) ? 1 : 0,
                'can_delete' => !empty($row['delete']) ? 1 : 0,
            ];
        }
    }

    private static function fetchRow(int $role, string $module): ?array
    {
        try {
            $stmt = Database::connection()->prepare(
                'SELECT can_view, can_create, can_edit, can_delete
                 FROM role_permissions
                 WHERE role_value = :role_value
                   AND module_key = :module_key
                 LIMIT 1'
            );
            $stmt->execute([
                'role_value' => $role,
                'module_key' => $module,
            ]);

            $row = $stmt->fetch();
            return $row ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}

