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

        $preset = trim((string) ($_GET['preset'] ?? 'all'));
        if (!in_array($preset, ['all', 'security', 'financial', 'data_changes'], true)) {
            $preset = 'all';
        }

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'user_id' => (int) ($_GET['user_id'] ?? 0),
            'entity_table' => trim((string) ($_GET['entity_table'] ?? '')),
            'action_key' => trim((string) ($_GET['action_key'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];

        $actions = UserAction::search($filters);
        if ($preset !== 'all') {
            $actions = array_values(array_filter($actions, fn (array $row): bool => $this->matchesPreset($row, $preset)));
        }

        if ((string) ($_GET['export'] ?? '') === 'csv') {
            $this->downloadCsv($actions, $preset);
            return;
        }

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
            'preset' => $preset,
            'actions' => $actions,
            'entityOptions' => $entityOptions,
            'actionOptions' => $actionOptions,
            'userOptions' => $userOptions,
            'isReady' => UserAction::isAvailable(),
            'pageScripts' => $pageScripts,
        ]);
    }

    private function matchesPreset(array $row, string $preset): bool
    {
        $actionKey = strtolower(trim((string) ($row['action_key'] ?? '')));
        $entityTable = strtolower(trim((string) ($row['entity_table'] ?? '')));
        $summary = strtolower(trim((string) ($row['summary'] ?? '')));

        return match ($preset) {
            'security' => str_contains($actionKey, 'login')
                || str_contains($actionKey, 'password')
                || str_contains($actionKey, 'permission')
                || str_contains($actionKey, '2fa')
                || $entityTable === 'user_login_records'
                || $entityTable === 'users',
            'financial' => in_array($entityTable, ['sales', 'expenses', 'jobs', 'job_actions', 'job_disposal_events'], true)
                || str_contains($actionKey, 'payment')
                || str_contains($actionKey, 'billing')
                || str_contains($actionKey, 'expense')
                || str_contains($summary, 'payment')
                || str_contains($summary, 'invoice'),
            'data_changes' => str_contains($actionKey, 'create')
                || str_contains($actionKey, 'update')
                || str_contains($actionKey, 'delete')
                || str_contains($actionKey, 'deactivate')
                || str_contains($actionKey, 'restore'),
            default => true,
        };
    }

    private function downloadCsv(array $actions, string $preset): void
    {
        $filename = 'audit-log-' . ($preset !== 'all' ? $preset . '-' : '') . date('Ymd-His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        if ($output === false) {
            return;
        }

        fputcsv($output, ['When', 'User', 'Action', 'Entity', 'Entity ID', 'Summary', 'Details', 'IP']);
        foreach ($actions as $action) {
            $actorName = trim((string) ($action['actor_name'] ?? ''));
            $actorEmail = trim((string) ($action['actor_email'] ?? ''));
            $actor = $actorName !== '' ? $actorName : ($actorEmail !== '' ? $actorEmail : ('User #' . (string) ($action['user_id'] ?? '')));

            fputcsv($output, [
                format_datetime($action['created_at'] ?? null),
                $actor,
                (string) ($action['action_key'] ?? ''),
                (string) ($action['entity_table'] ?? ''),
                (string) ($action['entity_id'] ?? ''),
                (string) ($action['summary'] ?? ''),
                (string) ($action['details'] ?? ''),
                (string) ($action['ip_address'] ?? ''),
            ]);
        }

        fclose($output);
    }
}
