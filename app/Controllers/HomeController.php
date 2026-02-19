<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Dashboard;
use App\Models\Employee;
use App\Models\Job;
use App\Models\TimeEntry;
use Core\Controller;

final class HomeController extends Controller
{
    public function index(): void
    {
        require_permission('dashboard', 'view');

        $overview = Dashboard::overview();
        $selfPunch = $this->selfPunchData();

        $this->render('home/index', [
            'pageTitle' => 'Dashboard',
            'overview' => $overview,
            'selfPunch' => $selfPunch,
        ]);
    }

    public function punchInSelf(): void
    {
        require_permission('time_tracking', 'create');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/');
        }

        $employee = $this->resolveSelfEmployee();
        if (!$employee) {
            redirect('/');
        }

        $employeeId = (int) ($employee['id'] ?? 0);
        if ($employeeId <= 0) {
            flash('error', 'Unable to determine your employee profile.');
            redirect('/');
        }

        $openEntry = TimeEntry::findOpenForEmployee($employeeId);
        if ($openEntry) {
            $jobId = (int) ($openEntry['job_id'] ?? 0);
            $jobLabel = $jobId > 0 ? ('Job #' . $jobId) : 'Non-Job Time';
            flash('error', 'You are already punched in on ' . $jobLabel . '.');
            redirect('/');
        }

        $entryId = TimeEntry::create([
            'employee_id' => $employeeId,
            'job_id' => null,
            'work_date' => date('Y-m-d'),
            'start_time' => date('H:i:s'),
            'end_time' => null,
            'minutes_worked' => null,
            'pay_rate' => TimeEntry::employeeRate($employeeId) ?? null,
            'total_paid' => null,
            'note' => 'Self punch in from dashboard.',
        ], auth_user_id());

        $employeeName = (string) ($employee['name'] ?? ('Employee #' . $employeeId));
        log_user_action('time_punched_in', 'employee_time_entries', $entryId, $employeeName . ' punched in from dashboard.');
        flash('success', 'You are punched in (Non-Job Time).');
        redirect('/');
    }

    public function punchOutSelf(): void
    {
        require_permission('time_tracking', 'edit');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/');
        }

        $employee = $this->resolveSelfEmployee();
        if (!$employee) {
            redirect('/');
        }

        $employeeId = (int) ($employee['id'] ?? 0);
        if ($employeeId <= 0) {
            flash('error', 'Unable to determine your employee profile.');
            redirect('/');
        }

        $openEntry = TimeEntry::findOpenForEmployee($employeeId);
        if (!$openEntry) {
            flash('error', 'You are not currently punched in.');
            redirect('/');
        }

        $entryId = (int) ($openEntry['id'] ?? 0);
        if ($entryId <= 0) {
            flash('error', 'Unable to locate your active time entry.');
            redirect('/');
        }

        $entry = TimeEntry::findById($entryId);
        if (
            !$entry
            || !empty($entry['deleted_at'])
            || (int) ($entry['active'] ?? 1) !== 1
            || empty($entry['start_time'])
            || !empty($entry['end_time'])
        ) {
            flash('error', 'Your active time entry is not available for punch out.');
            redirect('/');
        }

        $minutesWorked = $this->calculateOpenMinutes(
            (string) ($entry['work_date'] ?? date('Y-m-d')),
            (string) ($entry['start_time'] ?? date('H:i:s'))
        );
        $payRate = isset($entry['pay_rate']) && $entry['pay_rate'] !== null
            ? (float) $entry['pay_rate']
            : (TimeEntry::employeeRate($employeeId) ?? 0.0);
        $totalPaid = round(($payRate * $minutesWorked) / 60, 2);

        TimeEntry::punchOut($entryId, [
            'end_time' => date('H:i:s'),
            'minutes_worked' => $minutesWorked,
            'pay_rate' => $payRate,
            'total_paid' => $totalPaid,
            'note' => (string) ($entry['note'] ?? ''),
        ], auth_user_id());

        $jobId = (int) ($entry['job_id'] ?? 0);
        if ($jobId > 0) {
            Job::createAction($jobId, [
                'action_type' => 'time_punched_out',
                'action_at' => date('Y-m-d H:i:s'),
                'amount' => $totalPaid,
                'ref_table' => 'employee_time_entries',
                'ref_id' => $entryId,
                'note' => 'Employee punched out from dashboard (' . $this->formatDuration($minutesWorked) . ').',
            ], auth_user_id());
        }

        $employeeName = (string) ($employee['name'] ?? ('Employee #' . $employeeId));
        log_user_action('time_punched_out', 'employee_time_entries', $entryId, $employeeName . ' punched out from dashboard.');

        flash('success', 'You are punched out.');
        redirect('/');
    }

    private function selfPunchData(): array
    {
        $user = auth_user();
        $canPunchIn = can_access('time_tracking', 'create');
        $canPunchOut = can_access('time_tracking', 'edit');
        $canManage = $canPunchIn || $canPunchOut;

        $data = [
            'can_manage' => $canManage,
            'can_punch_in' => false,
            'can_punch_out' => false,
            'employee' => null,
            'open_entry' => null,
            'open_label' => null,
            'message' => null,
        ];

        if (!$canManage || !$user) {
            return $data;
        }

        $employee = Employee::findForUser($user);
        if (!$employee) {
            $data['message'] = 'No linked employee profile found for your user account.';
            return $data;
        }

        $employeeId = (int) ($employee['id'] ?? 0);
        if ($employeeId <= 0) {
            $data['message'] = 'Unable to determine your employee profile.';
            return $data;
        }

        $openEntry = TimeEntry::findOpenForEmployee($employeeId);
        $data['employee'] = $employee;
        $data['open_entry'] = $openEntry;

        if ($openEntry) {
            $jobId = (int) ($openEntry['job_id'] ?? 0);
            $jobName = trim((string) ($openEntry['job_name'] ?? ''));
            $data['open_label'] = $jobId > 0 ? ($jobName !== '' ? $jobName : ('Job #' . $jobId)) : 'Non-Job Time';
            $data['can_punch_out'] = $canPunchOut;
        } else {
            $data['can_punch_in'] = $canPunchIn;
        }

        return $data;
    }

    private function resolveSelfEmployee(): ?array
    {
        $user = auth_user();
        if (!$user) {
            flash('error', 'You must be logged in to punch in or out.');
            return null;
        }

        $employee = Employee::findForUser($user);
        if (!$employee) {
            flash('error', 'No linked employee profile found for your user account.');
            return null;
        }

        return $employee;
    }

    private function calculateOpenMinutes(string $workDate, string $startTime): int
    {
        $start = strtotime($workDate . ' ' . $startTime);
        $now = time();
        if ($start === false) {
            return 1;
        }

        if ($now < $start) {
            $now += 86400;
        }

        $minutes = (int) floor(($now - $start) / 60);
        return max(1, $minutes);
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
}
