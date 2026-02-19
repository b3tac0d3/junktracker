<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Recovery;
use Core\Controller;

final class AdminRecoveryController extends Controller
{
    public function index(): void
    {
        require_permission('recovery', 'view');

        $entity = trim((string) ($_GET['entity'] ?? 'jobs'));
        $query = trim((string) ($_GET['q'] ?? ''));

        $entities = Recovery::entities();
        if (!array_key_exists($entity, $entities)) {
            $entity = 'jobs';
        }

        $rows = Recovery::deleted($entity, $query);

        $this->render('admin/recovery/index', [
            'pageTitle' => 'Recovery',
            'entities' => $entities,
            'selectedEntity' => $entity,
            'query' => $query,
            'rows' => $rows,
        ]);
    }

    public function restore(array $params): void
    {
        require_permission('recovery', 'edit');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/admin/recovery');
        }

        $entity = trim((string) ($params['entity'] ?? ''));
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            flash('error', 'Invalid restore request.');
            redirect('/admin/recovery');
        }

        if (!Recovery::restore($entity, $id, auth_user_id())) {
            flash('error', 'Could not restore that record.');
            redirect('/admin/recovery?entity=' . urlencode($entity));
        }

        log_user_action('record_restored', $entity, $id, 'Restored deleted ' . $entity . ' #' . $id . '.');
        flash('success', 'Record restored.');
        redirect('/admin/recovery?entity=' . urlencode($entity));
    }
}

