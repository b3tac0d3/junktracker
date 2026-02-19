<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\RolePermission;
use Core\Controller;

final class AdminPermissionsController extends Controller
{
    public function index(): void
    {
        require_permission('permissions', 'view');

        $role = (int) ($_GET['role'] ?? 3);
        if (!array_key_exists($role, RolePermission::roleOptions())) {
            $role = 3;
        }

        $this->render('admin/permissions/index', [
            'pageTitle' => 'Permission Matrix',
            'selectedRole' => $role,
            'roleOptions' => RolePermission::roleOptions(),
            'matrix' => RolePermission::matrixForRole($role),
            'isReady' => RolePermission::isAvailable(),
        ]);
    }

    public function update(): void
    {
        require_permission('permissions', 'edit');

        if (!RolePermission::isAvailable()) {
            flash('error', 'Permission matrix table is not available yet.');
            redirect('/admin/permissions');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/admin/permissions');
        }

        $role = (int) ($_POST['role'] ?? 0);
        if (!array_key_exists($role, RolePermission::roleOptions())) {
            flash('error', 'Invalid role selected.');
            redirect('/admin/permissions');
        }

        $incoming = is_array($_POST['matrix'] ?? null) ? $_POST['matrix'] : [];
        $matrix = [];
        foreach (RolePermission::modules() as $module => $_label) {
            $row = is_array($incoming[$module] ?? null) ? $incoming[$module] : [];
            $matrix[$module] = [
                'view' => !empty($row['view']),
                'create' => !empty($row['create']),
                'edit' => !empty($row['edit']),
                'delete' => !empty($row['delete']),
            ];
        }

        RolePermission::saveRoleMatrix($role, $matrix, auth_user_id());
        log_user_action('permissions_updated', 'role_permissions', $role, 'Updated permission matrix for role ' . $role . '.');
        flash('success', 'Permissions updated.');
        redirect('/admin/permissions?role=' . $role);
    }
}
