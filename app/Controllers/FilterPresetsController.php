<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserFilterPreset;
use Core\Controller;

final class FilterPresetsController extends Controller
{
    public function save(): void
    {
        if (!is_authenticated()) {
            redirect('/login');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect($this->resolveReturnPath('/'));
        }

        $userId = auth_user_id() ?? 0;
        $moduleKey = trim((string) ($_POST['module_key'] ?? ''));
        if ($userId <= 0 || !UserFilterPreset::isSupportedModule($moduleKey)) {
            flash('error', 'Invalid preset target.');
            redirect($this->resolveReturnPath('/'));
        }

        require_permission($moduleKey, 'view');

        $presetName = trim((string) ($_POST['preset_name'] ?? ''));
        if ($presetName === '') {
            flash('error', 'Preset name is required.');
            redirect($this->resolveReturnPath($this->moduleUrl($moduleKey)));
        }

        $filtersJson = trim((string) ($_POST['filters_json'] ?? ''));
        $filters = json_decode($filtersJson, true);
        if (!is_array($filters)) {
            $filters = [];
        }

        $presetId = UserFilterPreset::save($userId, $moduleKey, $presetName, $filters);
        if ($presetId <= 0) {
            flash('error', 'Unable to save filter preset.');
            redirect($this->resolveReturnPath($this->moduleUrl($moduleKey)));
        }

        flash('success', 'Filter preset saved.');
        redirect($this->resolveReturnPath($this->moduleUrl($moduleKey)));
    }

    public function delete(array $params): void
    {
        if (!is_authenticated()) {
            redirect('/login');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect($this->resolveReturnPath('/'));
        }

        $presetId = isset($params['id']) ? (int) $params['id'] : 0;
        $userId = auth_user_id() ?? 0;
        $moduleKey = trim((string) ($_POST['module_key'] ?? ''));

        if ($presetId <= 0 || $userId <= 0 || !UserFilterPreset::isSupportedModule($moduleKey)) {
            flash('error', 'Invalid preset request.');
            redirect($this->resolveReturnPath('/'));
        }

        require_permission($moduleKey, 'view');

        $deleted = UserFilterPreset::delete($presetId, $userId, $moduleKey);
        if ($deleted) {
            flash('success', 'Filter preset deleted.');
        } else {
            flash('error', 'Preset not found.');
        }

        redirect($this->resolveReturnPath($this->moduleUrl($moduleKey)));
    }

    private function resolveReturnPath(string $fallback): string
    {
        $returnTo = trim((string) ($_POST['return_to'] ?? ''));
        if ($returnTo === '') {
            return $fallback;
        }

        if (preg_match('#^https?://#i', $returnTo)) {
            return $fallback;
        }
        if (!str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            return $fallback;
        }

        return $returnTo;
    }

    private function moduleUrl(string $moduleKey): string
    {
        return match ($moduleKey) {
            'jobs' => '/jobs',
            'tasks' => '/tasks',
            'time_tracking' => '/time-tracking',
            'expenses' => '/expenses',
            default => '/',
        };
    }
}
