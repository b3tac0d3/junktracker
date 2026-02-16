<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Client;
use App\Models\Prospect;
use Core\Controller;

final class ProspectsController extends Controller
{
    private const STATUSES = ['active', 'converted', 'closed'];
    private const NEXT_STEPS = ['follow_up', 'call', 'text', 'send_quote', 'make_appointment', 'other'];
    private const PRIORITIES = [1, 2, 3, 4];

    public function index(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => (string) ($_GET['status'] ?? 'all'),
            'record_status' => (string) ($_GET['record_status'] ?? 'active'),
        ];

        if (!in_array($filters['status'], array_merge(['all'], self::STATUSES), true)) {
            $filters['status'] = 'all';
        }
        if (!in_array($filters['record_status'], ['active', 'inactive', 'all'], true)) {
            $filters['record_status'] = 'active';
        }

        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/prospects-table.js') . '"></script>',
        ]);

        $this->render('prospects/index', [
            'pageTitle' => 'Prospects',
            'prospects' => Prospect::filter($filters),
            'filters' => $filters,
            'pageScripts' => $pageScripts,
        ]);
    }

    public function show(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/prospects');
        }

        $prospect = Prospect::findById($id);
        if (!$prospect) {
            $this->renderNotFound();
            return;
        }

        $this->render('prospects/show', [
            'pageTitle' => 'Prospect Details',
            'prospect' => $prospect,
            'priorityLabels' => $this->priorityLabels(),
        ]);
    }

    public function create(): void
    {
        $this->render('prospects/create', [
            'pageTitle' => 'Add Prospect',
            'prospect' => null,
            'statuses' => self::STATUSES,
            'nextSteps' => self::NEXT_STEPS,
            'priorities' => self::PRIORITIES,
            'priorityLabels' => $this->priorityLabels(),
            'pageScripts' => $this->formScripts(),
        ]);

        clear_old();
    }

    public function store(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/prospects/new');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/prospects/new');
        }

        $prospectId = Prospect::create($data, auth_user_id());
        flash('success', 'Prospect added.');
        redirect('/prospects/' . $prospectId);
    }

    public function edit(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/prospects');
        }

        $prospect = Prospect::findById($id);
        if (!$prospect) {
            $this->renderNotFound();
            return;
        }

        $this->render('prospects/edit', [
            'pageTitle' => 'Edit Prospect',
            'prospect' => $prospect,
            'statuses' => self::STATUSES,
            'nextSteps' => self::NEXT_STEPS,
            'priorities' => self::PRIORITIES,
            'priorityLabels' => $this->priorityLabels(),
            'pageScripts' => $this->formScripts(),
        ]);

        clear_old();
    }

    public function update(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/prospects');
        }

        $prospect = Prospect::findById($id);
        if (!$prospect) {
            $this->renderNotFound();
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/prospects/' . $id . '/edit');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/prospects/' . $id . '/edit');
        }

        Prospect::update($id, $data, auth_user_id());
        flash('success', 'Prospect updated.');
        redirect('/prospects/' . $id);
    }

    public function delete(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/prospects');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/prospects/' . $id);
        }

        $prospect = Prospect::findById($id);
        if (!$prospect) {
            $this->renderNotFound();
            return;
        }

        if (empty($prospect['deleted_at']) && !empty($prospect['active'])) {
            Prospect::softDelete($id, auth_user_id());
            flash('success', 'Prospect deleted.');
        } else {
            flash('success', 'Prospect is already inactive.');
        }

        redirect('/prospects');
    }

    public function clientLookup(): void
    {
        $term = trim((string) ($_GET['q'] ?? ''));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(Client::lookupByName($term));
    }

    public function convert(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/prospects');
        }

        $prospect = Prospect::findById($id);
        if (!$prospect) {
            $this->renderNotFound();
            return;
        }

        if (!empty($prospect['deleted_at']) || empty($prospect['active'])) {
            flash('error', 'This prospect is already inactive.');
            redirect('/prospects/' . $id);
        }

        redirect('/jobs/new?from_prospect=' . $id);
    }

    private function collectFormData(): array
    {
        $status = trim((string) ($_POST['status'] ?? 'active'));
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'active';
        }

        $nextStep = trim((string) ($_POST['next_step'] ?? ''));
        if ($nextStep !== '' && !in_array($nextStep, self::NEXT_STEPS, true)) {
            $nextStep = '';
        }

        $priority = $this->toIntOrNull($_POST['priority_rating'] ?? null);
        if ($priority === null || !in_array($priority, self::PRIORITIES, true)) {
            $priority = 2;
        }

        return [
            'client_id' => $this->toIntOrNull($_POST['client_id'] ?? null),
            'contacted_on' => $this->toDateOrNull($_POST['contacted_on'] ?? null),
            'follow_up_on' => $this->toDateOrNull($_POST['follow_up_on'] ?? null),
            'status' => $status,
            'priority_rating' => $priority,
            'next_step' => $nextStep !== '' ? $nextStep : null,
            'note' => trim((string) ($_POST['note'] ?? '')),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];

        if ($data['client_id'] === null) {
            $errors[] = 'Select a client from the suggestions.';
        } elseif (!Prospect::clientExists($data['client_id'])) {
            $errors[] = 'Selected client is invalid.';
        }

        $clientSearch = trim((string) ($_POST['client_search'] ?? ''));
        if ($clientSearch !== '' && $data['client_id'] === null) {
            $errors[] = 'Select a client from the suggestions.';
        }

        if ($data['contacted_on'] !== null && $data['follow_up_on'] !== null && $data['follow_up_on'] < $data['contacted_on']) {
            $errors[] = 'Follow up date must be on or after contact date.';
        }

        return $errors;
    }

    private function priorityLabels(): array
    {
        return [
            1 => 'Low',
            2 => 'Normal',
            3 => 'High',
            4 => 'Urgent',
        ];
    }

    private function formScripts(): string
    {
        return '<script src="' . asset('js/prospect-client-lookup.js') . '"></script>';
    }

    private function toIntOrNull(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
    }

    private function toDateOrNull(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function renderNotFound(): void
    {
        http_response_code(404);
        if (class_exists('App\\Controllers\\ErrorController')) {
            (new \App\Controllers\ErrorController())->notFound();
            return;
        }

        echo '404 Not Found';
    }
}
