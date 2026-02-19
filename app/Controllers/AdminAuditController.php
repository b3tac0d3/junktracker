<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Models\UserAction;
use Core\Controller;

final class AdminAuditController extends Controller
{
    public function index(): void
    {
        require_permission('audit_log', 'view');

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'user_id' => (int) ($_GET['user_id'] ?? 0),
            'entity_table' => trim((string) ($_GET['entity_table'] ?? '')),
            'action_key' => trim((string) ($_GET['action_key'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];

        $actions = UserAction::search($filters);

        $entityOptions = UserAction::entityOptions();
        $actionOptions = UserAction::actionOptions();

        $userOptions = [];
        foreach (User::search('', 'all') as $user) {
            $id = (int) ($user['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $label = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
            if ($label === '') {
                $label = (string) ($user['email'] ?? ('User #' . $id));
            }
            $userOptions[] = ['id' => $id, 'label' => $label];
        }

        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/user-activity-table.js') . '"></script>',
        ]);

        $this->render('admin/audit/index', [
            'pageTitle' => 'Audit Log',
            'filters' => $filters,
            'actions' => $actions,
            'entityOptions' => $entityOptions,
            'actionOptions' => $actionOptions,
            'userOptions' => $userOptions,
            'isReady' => UserAction::isAvailable(),
            'pageScripts' => $pageScripts,
        ]);
    }
}

