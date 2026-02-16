<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Employee;
use App\Models\TimeEntry;
use Core\Controller;

final class EmployeesController extends Controller
{
    public function index(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        $status = (string) ($_GET['status'] ?? 'active');

        if (!in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/employees-table.js') . '"></script>',
        ]);

        $this->render('employees/index', [
            'pageTitle' => 'Employees',
            'employees' => Employee::search($query, $status),
            'query' => $query,
            'status' => $status,
            'pageScripts' => $pageScripts,
        ]);
    }

    public function show(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/employees');
        }

        $employee = Employee::findById($id);
        if (!$employee) {
            $this->renderNotFound();
            return;
        }

        $today = new \DateTimeImmutable('today');
        $weekStart = $today->modify('-' . $today->format('w') . ' days');
        $monthStart = $today->modify('first day of this month');
        $yearStart = $today->setDate((int) $today->format('Y'), 1, 1);
        $endDate = $today->format('Y-m-d');

        $laborSummary = [
            'wtd' => [
                'label' => 'WTD',
                'range' => $weekStart->format('m/d/Y') . ' - ' . $today->format('m/d/Y'),
                'data' => TimeEntry::summaryForEmployeeBetween($id, $weekStart->format('Y-m-d'), $endDate),
            ],
            'mtd' => [
                'label' => 'MTD',
                'range' => $monthStart->format('m/d/Y') . ' - ' . $today->format('m/d/Y'),
                'data' => TimeEntry::summaryForEmployeeBetween($id, $monthStart->format('Y-m-d'), $endDate),
            ],
            'ytd' => [
                'label' => 'YTD',
                'range' => $yearStart->format('m/d/Y') . ' - ' . $today->format('m/d/Y'),
                'data' => TimeEntry::summaryForEmployeeBetween($id, $yearStart->format('Y-m-d'), $endDate),
            ],
        ];

        $this->render('employees/show', [
            'pageTitle' => 'Employee Details',
            'employee' => $employee,
            'laborSummary' => $laborSummary,
        ]);
    }

    public function create(): void
    {
        $this->render('employees/create', [
            'pageTitle' => 'Add Employee',
            'employee' => null,
        ]);

        clear_old();
    }

    public function store(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/employees/new');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/employees/new');
        }

        $employeeId = Employee::create($data, auth_user_id());
        flash('success', 'Employee added.');
        redirect('/employees/' . $employeeId);
    }

    public function edit(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/employees');
        }

        $employee = Employee::findById($id);
        if (!$employee) {
            $this->renderNotFound();
            return;
        }

        $this->render('employees/edit', [
            'pageTitle' => 'Edit Employee',
            'employee' => $employee,
        ]);

        clear_old();
    }

    public function update(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/employees');
        }

        $employee = Employee::findById($id);
        if (!$employee) {
            $this->renderNotFound();
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/employees/' . $id . '/edit');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/employees/' . $id . '/edit');
        }

        Employee::update($id, $data, auth_user_id());
        flash('success', 'Employee updated.');
        redirect('/employees/' . $id);
    }

    private function collectFormData(): array
    {
        $wageType = trim((string) ($_POST['wage_type'] ?? 'hourly'));
        if (!in_array($wageType, ['hourly', 'salary'], true)) {
            $wageType = 'hourly';
        }

        $active = isset($_POST['active']) ? 1 : 0;
        $deletedAt = null;
        if ($active === 0) {
            $deletedAt = date('Y-m-d H:i:s');
        }

        return [
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'hire_date' => $this->toDateOrNull($_POST['hire_date'] ?? null),
            'fire_date' => $this->toDateOrNull($_POST['fire_date'] ?? null),
            'wage_type' => $wageType,
            'pay_rate' => $this->toDecimalOrNull($_POST['pay_rate'] ?? null),
            'note' => trim((string) ($_POST['note'] ?? '')),
            'active' => $active,
            'deleted_at' => $deletedAt,
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];

        if ($data['first_name'] === '' && $data['last_name'] === '') {
            $errors[] = 'Provide at least a first or last name.';
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email is invalid.';
        }
        if ($data['pay_rate'] !== null && $data['pay_rate'] < 0) {
            $errors[] = 'Pay rate must be a positive number.';
        }
        if ($data['hire_date'] !== null && $data['fire_date'] !== null && $data['fire_date'] < $data['hire_date']) {
            $errors[] = 'Fire date must be on or after hire date.';
        }

        return $errors;
    }

    private function toDateOrNull(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $time = strtotime($raw);
        return $time === false ? null : date('Y-m-d', $time);
    }

    private function toDecimalOrNull(mixed $value): ?float
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        return is_numeric($raw) ? (float) $raw : null;
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
