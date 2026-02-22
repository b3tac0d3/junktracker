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

        $viewerUser = auth_user() ?? [];
        $viewerUserId = (int) ($viewerUser['id'] ?? 0);
        if ($viewerUserId <= 0) {
            redirect('/login');
        }
        $viewerRole = (int) ($viewerUser['role'] ?? 0);

        $scope = strtolower(trim((string) ($_GET['scope'] ?? 'open')));
        if (!in_array($scope, ['open', 'unread', 'dismissed', 'all'], true)) {
            $scope = 'open';
        }

        $subjectUserId = $this->resolveSubjectUserId($viewerUserId, $viewerRole, $_GET['user_id'] ?? null);

        $this->render('notifications/index', [
            'pageTitle' => 'Notifications',
            'scope' => $scope,
            'summary' => NotificationCenter::summaryForUser($subjectUserId, $viewerUserId, $viewerRole),
            'notifications' => NotificationCenter::listForUser($subjectUserId, $scope, $viewerUserId, $viewerRole),
            'viewerRole' => $viewerRole,
            'viewerUserId' => $viewerUserId,
            'subjectUserId' => $subjectUserId,
            'userOptions' => NotificationCenter::userOptionsForViewer($viewerUserId, $viewerRole),
        ]);
    }

    public function read(): void
    {
        require_permission('notifications', 'view');

        $viewerUser = auth_user() ?? [];
        $viewerUserId = (int) ($viewerUser['id'] ?? 0);
        if ($viewerUserId <= 0) {
            redirect('/login');
        }
        $viewerRole = (int) ($viewerUser['role'] ?? 0);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $this->respondError('Your session expired. Please try again.', '/notifications');
            return;
        }

        $subjectUserId = $this->resolveSubjectUserId($viewerUserId, $viewerRole, $_POST['user_id'] ?? null);
        if (!NotificationCenter::canViewSubject($viewerUserId, $subjectUserId, $viewerRole)) {
            $this->respondError('You do not have access to update notifications for this user.', '/notifications');
            return;
        }

        $notificationKey = trim((string) ($_POST['notification_key'] ?? ''));
        if ($notificationKey === '') {
            $this->respondError('Notification key is missing.', '/notifications');
            return;
        }

        $markRead = (string) ($_POST['is_read'] ?? '1') !== '0';
        NotificationCenter::markRead($subjectUserId, $notificationKey, $markRead);

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

        $viewerUser = auth_user() ?? [];
        $viewerUserId = (int) ($viewerUser['id'] ?? 0);
        if ($viewerUserId <= 0) {
            redirect('/login');
        }
        $viewerRole = (int) ($viewerUser['role'] ?? 0);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $this->respondError('Your session expired. Please try again.', '/notifications');
            return;
        }

        $subjectUserId = $this->resolveSubjectUserId($viewerUserId, $viewerRole, $_POST['user_id'] ?? null);
        if (!NotificationCenter::canViewSubject($viewerUserId, $subjectUserId, $viewerRole)) {
            $this->respondError('You do not have access to update notifications for this user.', '/notifications');
            return;
        }

        $notificationKey = trim((string) ($_POST['notification_key'] ?? ''));
        if ($notificationKey === '') {
            $this->respondError('Notification key is missing.', '/notifications');
            return;
        }

        $dismiss = (string) ($_POST['dismiss'] ?? '1') !== '0';
        NotificationCenter::dismiss($subjectUserId, $notificationKey, $dismiss);

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

    private function resolveSubjectUserId(int $viewerUserId, int $viewerRole, mixed $requestedUserId): int
    {
        if ($viewerUserId <= 0) {
            return 0;
        }

        $subjectUserId = $viewerUserId;
        if (($viewerRole === 99 || $viewerRole >= 2) && is_scalar($requestedUserId)) {
            $requested = (int) trim((string) $requestedUserId);
            if ($requested > 0) {
                $subjectUserId = $requested;
            }
        }

        if (!NotificationCenter::canViewSubject($viewerUserId, $subjectUserId, $viewerRole)) {
            return $viewerUserId;
        }

        return $subjectUserId;
    }
}
