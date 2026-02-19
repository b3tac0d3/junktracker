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
        'notifications' => 'Notifications',
        'jobs' => 'Jobs',
        'prospects' => 'Prospects',
        'sales' => 'Sales',
        'reports' => 'Reports',
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
        'data_quality' => 'Data Quality',
        'permissions' => 'Permissions',
        'dev_tools' => 'Dev Tools',
    ];

    private static ?bool $available = null;
    private static array $cache = [];
    private static bool $defaultsSeeded = false;

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

        if ($moduleKey === '' || !isset(self::MODULES[$moduleKey])) {
            return true;
        }

        if ($role === 99) {
            return true;
        }

        if (!array_key_exists($role, self::roleOptions())) {
            return false;
        }

        $actionKey = array_key_exists($actionKey, self::ACTION_COLUMN) ? $actionKey : 'view';
        $column = self::ACTION_COLUMN[$actionKey];
        if (!self::isAvailable()) {
            return self::defaultAllows($role, $moduleKey, $actionKey);
        }

        self::seedDefaultsIfNeeded();

        $cacheKey = $role . '|' . $moduleKey;
        if (!isset(self::$cache[$cacheKey])) {
            self::$cache[$cacheKey] = self::fetchRow($role, $moduleKey);
        }
        $row = self::$cache[$cacheKey];

        if ($row === null) {
            return self::defaultAllows($role, $moduleKey, $actionKey);
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

    private static function defaultAllows(int $role, string $module, string $action): bool
    {
        $matrix = self::defaultMatrixForRole($role);
        if (!isset($matrix[$module])) {
            return false;
        }

        return !empty($matrix[$module][$action]);
    }

    private static function defaultMatrixForRole(int $role): array
    {
        $matrix = [];
        foreach (self::MODULES as $module => $_label) {
            $matrix[$module] = [
                'view' => false,
                'create' => false,
                'edit' => false,
                'delete' => false,
            ];
        }

        if ($role === 1) {
            self::allow($matrix, ['dashboard', 'notifications'], ['view']);
            self::allow($matrix, [
                'jobs',
                'prospects',
                'sales',
                'reports',
                'customers',
                'clients',
                'estates',
                'companies',
                'client_contacts',
                'consignors',
                'time_tracking',
                'expenses',
                'tasks',
            ], ['view', 'create', 'edit']);
            self::allow($matrix, ['prospects', 'tasks', 'reports'], ['delete']);
            self::allow($matrix, ['employees'], ['view']);
            return $matrix;
        }

        if ($role === 2) {
            self::allow($matrix, ['dashboard', 'notifications'], ['view']);
            self::allow($matrix, [
                'jobs',
                'prospects',
                'sales',
                'reports',
                'customers',
                'clients',
                'estates',
                'companies',
                'client_contacts',
                'consignors',
                'time_tracking',
                'expenses',
                'tasks',
            ], ['view', 'create', 'edit', 'delete']);
            self::allow($matrix, ['employees'], ['view', 'create', 'edit']);
            self::allow($matrix, ['users', 'expense_categories', 'disposal_locations'], ['view']);
            self::allow($matrix, ['expense_categories', 'disposal_locations'], ['create', 'edit']);
            self::allow($matrix, ['data_quality'], ['view']);
            return $matrix;
        }

        if ($role === 3) {
            $adminModules = array_values(array_filter(array_keys(self::MODULES), static fn (string $module): bool => $module !== 'dev_tools'));
            self::allow($matrix, $adminModules, ['view', 'create', 'edit', 'delete']);
            return $matrix;
        }

        return $matrix;
    }

    private static function allow(array &$matrix, array $modules, array $actions): void
    {
        foreach ($modules as $module) {
            if (!isset($matrix[$module])) {
                continue;
            }
            foreach ($actions as $action) {
                if (!isset($matrix[$module][$action])) {
                    continue;
                }
                $matrix[$module][$action] = true;
            }
        }
    }

    private static function seedDefaultsIfNeeded(): void
    {
        if (self::$defaultsSeeded || !self::isAvailable()) {
            return;
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO role_permissions
                    (role_value, module_key, can_view, can_create, can_edit, can_delete, updated_by, updated_at)
                 VALUES
                    (:role_value, :module_key, :can_view, :can_create, :can_edit, :can_delete, NULL, NOW())'
            );

            foreach ([1, 2, 3] as $role) {
                $matrix = self::defaultMatrixForRole($role);
                foreach ($matrix as $module => $actions) {
                    $stmt->execute([
                        'role_value' => $role,
                        'module_key' => $module,
                        'can_view' => !empty($actions['view']) ? 1 : 0,
                        'can_create' => !empty($actions['create']) ? 1 : 0,
                        'can_edit' => !empty($actions['edit']) ? 1 : 0,
                        'can_delete' => !empty($actions['delete']) ? 1 : 0,
                    ]);
                }
            }
        } catch (Throwable) {
            // Keep permissions usable via in-code defaults when table writes fail.
        }

        self::$defaultsSeeded = true;
    }
}
