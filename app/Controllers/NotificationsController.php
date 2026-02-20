<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\NotificationCenter;
use Core\Controller;

final class NotificationsController extends Controller
{
    public function index(): void
    {
        require_permission('notifications', 'view');

        $userId = auth_user_id() ?? 0;
        if ($userId <= 0) {
            redirect('/login');
        }

        $scope = strtolower(trim((string) ($_GET['scope'] ?? 'open')));
        if (!in_array($scope, ['open', 'unread', 'dismissed', 'all'], true)) {
            $scope = 'open';
        }

        $this->render('notifications/index', [
            'pageTitle' => 'Notifications',
            'scope' => $scope,
            'summary' => NotificationCenter::summaryForUser($userId),
            'notifications' => NotificationCenter::listForUser($userId, $scope),
        ]);
    }

    public function read(): void
    {
        require_permission('notifications', 'view');

        $userId = auth_user_id() ?? 0;
        if ($userId <= 0) {
            redirect('/login');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $this->respondError('Your session expired. Please try again.', '/notifications');
            return;
        }

        $notificationKey = trim((string) ($_POST['notification_key'] ?? ''));
        if ($notificationKey === '') {
            $this->respondError('Notification key is missing.', '/notifications');
            return;
        }

        $markRead = (string) ($_POST['is_read'] ?? '1') !== '0';
        NotificationCenter::markRead($userId, $notificationKey, $markRead);

        $message = $markRead ? 'Notification marked read.' : 'Notification marked unread.';
        if (expects_json_response()) {
            json_response([
                'ok' => true,
                'message' => $message,
            ]);
            return;
        }

        flash('success', $message);
        redirect($this->returnPath('/notifications'));
    }

    public function dismiss(): void
    {
        require_permission('notifications', 'view');

        $userId = auth_user_id() ?? 0;
        if ($userId <= 0) {
            redirect('/login');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $this->respondError('Your session expired. Please try again.', '/notifications');
            return;
        }

        $notificationKey = trim((string) ($_POST['notification_key'] ?? ''));
        if ($notificationKey === '') {
            $this->respondError('Notification key is missing.', '/notifications');
            return;
        }

        $dismiss = (string) ($_POST['dismiss'] ?? '1') !== '0';
        NotificationCenter::dismiss($userId, $notificationKey, $dismiss);

        $message = $dismiss ? 'Notification dismissed.' : 'Notification restored.';
        if (expects_json_response()) {
            json_response([
                'ok' => true,
                'message' => $message,
            ]);
            return;
        }

        flash('success', $message);
        redirect($this->returnPath('/notifications'));
    }

    private function returnPath(string $fallback): string
    {
        $returnTo = trim((string) ($_POST['return_to'] ?? ''));
        if ($returnTo === '' || !str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            return $fallback;
        }

        return $returnTo;
    }

    private function respondError(string $message, string $fallbackPath): void
    {
        if (expects_json_response()) {
            json_response([
                'ok' => false,
                'message' => $message,
            ], 422);
            return;
        }

        flash('error', $message);
        redirect($fallbackPath);
    }
}
