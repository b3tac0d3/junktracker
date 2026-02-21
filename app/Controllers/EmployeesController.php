<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Contact;
use App\Models\Employee;
use App\Models\Job;
use App\Models\TimeEntry;
use Core\Controller;

final class EmployeesController extends Controller
{
    public function index(): void
    {
        require_permission('employees', 'view');

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
        require_permission('employees', 'view');

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

        $openClockEntry = TimeEntry::findOpenForEmployee($id);
        $openClockElapsed = null;
        if ($openClockEntry && isset($openClockEntry['work_date'], $openClockEntry['start_time'])) {
            $openClockElapsed = $this->formatDuration($this->calculateOpenMinutes(
                (string) $openClockEntry['work_date'],
                (string) $openClockEntry['start_time']
            ));
        }

        $pageScripts = '<script src="' . asset('js/employee-punch-form.js') . '?v=' . rawurlencode((string) config('app.version', '')) . '"></script>';

        $this->render('employees/show', [
            'pageTitle' => 'Employee Details',
            'employee' => $employee,
            'laborSummary' => $laborSummary,
            'openClockEntry' => $openClockEntry,
            'openClockElapsed' => $openClockElapsed,
            'pageScripts' => $pageScripts,
        ]);
    }

    public function punchIn(array $params): void
    {
        require_permission('employees', 'edit');

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
            redirect('/employees/' . $id);
        }

        if (!empty($employee['deleted_at']) || (int) ($employee['active'] ?? 1) !== 1) {
            flash('error', 'Inactive employees cannot be punched in.');
            redirect('/employees/' . $id);
        }

        $openEntry = TimeEntry::findOpenForEmployee($id);
        if ($openEntry) {
            $openJobId = (int) ($openEntry['job_id'] ?? 0);
            $openJobLabel = $openJobId > 0
                ? ('Job #' . $openJobId)
                : 'Non-Job Time';
            flash('error', 'This employee is already punched in on ' . $openJobLabel . '.');
            redirect('/employees/' . $id);
        }

        $jobId = $this->toIntOrNull($_POST['job_id'] ?? null);
        if ($jobId !== null && $jobId > 0 && !$this->jobExists($jobId)) {
            flash('error', 'Select a valid job before punching in.');
            redirect('/employees/' . $id);
        }
        if ($jobId !== null && $jobId <= 0) {
            $jobId = null;
        }

        $payRate = TimeEntry::employeeRate($id) ?? 0.0;
        $entryId = TimeEntry::create([
            'employee_id' => $id,
            'job_id' => $jobId,
            'work_date' => date('Y-m-d'),
            'start_time' => date('H:i:s'),
            'end_time' => null,
            'minutes_worked' => null,
            'pay_rate' => $payRate,
            'total_paid' => null,
            'note' => null,
        ], auth_user_id());

        $employeeName = $this->employeeDisplayName($employee, $id);
        if (($jobId ?? 0) > 0) {
            Job::createAction((int) $jobId, [
                'action_type' => 'time_punched_in',
                'action_at' => date('Y-m-d H:i:s'),
                'amount' => null,
                'ref_table' => 'employee_time_entries',
                'ref_id' => $entryId,
                'note' => $employeeName . ' punched in from employee details.',
            ], auth_user_id());
        }
        $jobLabel = ($jobId ?? 0) > 0 ? ('job #' . $jobId) : 'non-job time';
        log_user_action('time_punched_in', 'employee_time_entries', $entryId, $employeeName . ' punched in on ' . $jobLabel . '.');

        flash('success', $employeeName . ' punched in.');
        redirect('/employees/' . $id);
    }

    public function punchOut(array $params): void
    {
        require_permission('employees', 'edit');

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
            redirect('/employees/' . $id);
        }

        $entry = TimeEntry::findOpenForEmployee($id);
        if (
            !$entry
            || empty($entry['start_time'])
            || !empty($entry['end_time'])
            || !empty($entry['deleted_at'])
            || (int) ($entry['active'] ?? 1) !== 1
        ) {
            flash('error', 'This employee is not currently punched in.');
            redirect('/employees/' . $id);
        }

        $entryId = (int) ($entry['id'] ?? 0);
        if ($entryId <= 0) {
            flash('error', 'Unable to locate the active time entry.');
            redirect('/employees/' . $id);
        }

        $minutesWorked = $this->calculateOpenMinutes(
            (string) ($entry['work_date'] ?? date('Y-m-d')),
            (string) ($entry['start_time'] ?? date('H:i:s'))
        );
        $payRate = isset($entry['pay_rate']) && $entry['pay_rate'] !== null
            ? (float) $entry['pay_rate']
            : (TimeEntry::employeeRate($id) ?? 0.0);
        $totalPaid = round(($payRate * $minutesWorked) / 60, 2);

        TimeEntry::punchOut($entryId, [
            'end_time' => date('H:i:s'),
            'minutes_worked' => $minutesWorked,
            'pay_rate' => $payRate,
            'total_paid' => $totalPaid,
        ], auth_user_id());

        $employeeName = $this->employeeDisplayName($employee, $id);
        $jobId = (int) ($entry['job_id'] ?? 0);
        if ($jobId > 0) {
            Job::createAction($jobId, [
                'action_type' => 'time_punched_out',
                'action_at' => date('Y-m-d H:i:s'),
                'amount' => $totalPaid,
                'ref_table' => 'employee_time_entries',
                'ref_id' => $entryId,
                'note' => $employeeName . ' punched out (' . $this->formatDuration($minutesWorked) . ').',
            ], auth_user_id());
        }
        log_user_action('time_punched_out', 'employee_time_entries', $entryId, $employeeName . ' punched out.');

        flash('success', $employeeName . ' punched out.');
        redirect('/employees/' . $id);
    }

    public function create(): void
    {
        require_permission('employees', 'create');

        $this->render('employees/create', [
            'pageTitle' => 'Add Employee',
            'employee' => null,
        ]);

        clear_old();
    }

    public function store(): void
    {
        require_permission('employees', 'create');

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
        $employee = Employee::findById($employeeId);
        if ($employee) {
            Contact::upsertFromEmployee($employee, auth_user_id());
        }
        flash('success', 'Employee added.');
        redirect('/employees/' . $employeeId);
    }

    public function edit(array $params): void
    {
        require_permission('employees', 'edit');

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
        require_permission('employees', 'edit');

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
        $updatedEmployee = Employee::findById($id);
        if ($updatedEmployee) {
            Contact::upsertFromEmployee($updatedEmployee, auth_user_id());
        }
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

    private function toIntOrNull(mixed $value): ?int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
    }

    private function jobExists(int $jobId): bool
    {
        if ($jobId <= 0) {
            return false;
        }

        foreach (TimeEntry::jobs() as $job) {
            if ((int) ($job['id'] ?? 0) === $jobId) {
                return true;
            }
        }

        return false;
    }

    private function calculateOpenMinutes(string $workDate, string $startTime): int
    {
        $start = strtotime($workDate . ' ' . $startTime);
        if ($start === false) {
            return 0;
        }

        $minutes = (int) floor((time() - $start) / 60);
        return $minutes > 0 ? $minutes : 0;
    }

    private function formatDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0h 00m';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $hours . 'h ' . str_pad((string) $remaining, 2, '0', STR_PAD_LEFT) . 'm';
    }

    private function employeeDisplayName(array $employee, int $id): string
    {
        $name = trim(((string) ($employee['first_name'] ?? '')) . ' ' . ((string) ($employee['last_name'] ?? '')));
        return $name !== '' ? $name : ('Employee #' . $id);
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
