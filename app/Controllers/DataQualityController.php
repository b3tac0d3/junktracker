<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\DataQuality;
use Core\Controller;

final class DataQualityController extends Controller
{
    public function index(): void
    {
        require_permission('data_quality', 'view');

        $limit = $this->toIntOrNull($_GET['limit'] ?? null) ?? 30;
        if ($limit < 5 || $limit > 100) {
            $limit = 30;
        }

        $queue = DataQuality::duplicateQueue($limit);

        $this->render('data_quality/index', [
            'pageTitle' => 'Data Quality',
            'limit' => $limit,
            'queue' => $queue,
        ]);
    }

    public function mergeClient(): void
    {
        require_permission('data_quality', 'edit');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/data-quality');
        }

        $sourceId = $this->toIntOrNull($_POST['source_id'] ?? null) ?? 0;
        $targetId = $this->toIntOrNull($_POST['target_id'] ?? null) ?? 0;
        if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
            flash('error', 'Select different source and target client IDs.');
            redirect('/data-quality');
        }

        $ok = DataQuality::mergeClients($sourceId, $targetId, auth_user_id());
        if (!$ok) {
            flash('error', 'Client merge failed. Review IDs and try again.');
            redirect('/data-quality');
        }

        log_user_action('data_quality_merge_client', 'clients', $targetId, 'Merged client #' . $sourceId . ' into client #' . $targetId . '.');
        flash('success', 'Client merge completed.');
        redirect('/data-quality');
    }

    public function mergeCompany(): void
    {
        require_permission('data_quality', 'edit');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/data-quality');
        }

        $sourceId = $this->toIntOrNull($_POST['source_id'] ?? null) ?? 0;
        $targetId = $this->toIntOrNull($_POST['target_id'] ?? null) ?? 0;
        if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
            flash('error', 'Select different source and target company IDs.');
            redirect('/data-quality');
        }

        $ok = DataQuality::mergeCompanies($sourceId, $targetId, auth_user_id());
        if (!$ok) {
            flash('error', 'Company merge failed. Review IDs and try again.');
            redirect('/data-quality');
        }

        log_user_action('data_quality_merge_company', 'companies', $targetId, 'Merged company #' . $sourceId . ' into company #' . $targetId . '.');
        flash('success', 'Company merge completed.');
        redirect('/data-quality');
    }

    public function mergeJob(): void
    {
        require_permission('data_quality', 'edit');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/data-quality');
        }

        $sourceId = $this->toIntOrNull($_POST['source_id'] ?? null) ?? 0;
        $targetId = $this->toIntOrNull($_POST['target_id'] ?? null) ?? 0;
        if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
            flash('error', 'Select different source and target job IDs.');
            redirect('/data-quality');
        }

        $ok = DataQuality::mergeJobs($sourceId, $targetId, auth_user_id());
        if (!$ok) {
            flash('error', 'Job merge failed. Review IDs and try again.');
            redirect('/data-quality');
        }

        log_user_action('data_quality_merge_job', 'jobs', $targetId, 'Merged job #' . $sourceId . ' into job #' . $targetId . '.');
        flash('success', 'Job merge completed.');
        redirect('/data-quality');
    }

    private function toIntOrNull(mixed $value): ?int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
    }
}
