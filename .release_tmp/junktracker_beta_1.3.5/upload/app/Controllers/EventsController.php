<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Event;
use App\Models\EventFeed;
use Core\Controller;

final class EventsController extends Controller
{
    public function index(): void
    {
        require_business_role(['general_user', 'admin', 'punch_only', 'site_admin']);

        $this->render('events/index', [
            'pageTitle' => 'Events',
        ]);
    }

    public function feed(): void
    {
        require_business_role(['general_user', 'admin', 'punch_only', 'site_admin']);

        $businessId = current_business_id();
        $start = isset($_GET['start']) ? (string) $_GET['start'] : '';
        $end = isset($_GET['end']) ? (string) $_GET['end'] : '';
        $sourcesRaw = isset($_GET['sources']) ? (string) $_GET['sources'] : '';
        $typesRaw = isset($_GET['types']) ? (string) $_GET['types'] : '';
        $q = isset($_GET['q']) ? (string) $_GET['q'] : '';

        $events = EventFeed::range($businessId, $start, $end, [
            'sources' => $sourcesRaw,
            'types' => $typesRaw,
            'q' => $q,
        ]);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($events, JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function show(array $params): void
    {
        require_business_role(['general_user', 'admin', 'punch_only', 'site_admin']);

        $id = (int) ($params['id'] ?? 0);
        $businessId = current_business_id();
        $event = $id > 0 ? Event::findForBusiness($businessId, $id) : null;
        if ($event === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('events/show', [
            'pageTitle' => 'Event',
            'event' => $event,
        ]);
    }

    public function json(array $params): void
    {
        require_business_role(['general_user', 'admin', 'punch_only', 'site_admin']);

        $id = (int) ($params['id'] ?? 0);
        $businessId = current_business_id();
        $event = $id > 0 ? Event::findForBusiness($businessId, $id) : null;
        if ($event === null) {
            $this->sendJson(['ok' => false, 'error' => 'Event not found.'], 404);
        }

        $this->sendJson([
            'ok' => true,
            'event' => [
                'id' => (int) ($event['id'] ?? 0),
                'title' => (string) ($event['title'] ?? ''),
                'type' => (string) ($event['type'] ?? 'appointment'),
                'status' => (string) ($event['status'] ?? 'scheduled'),
                'start_at' => (string) ($event['start_at'] ?? ''),
                'end_at' => (string) ($event['end_at'] ?? ''),
                'all_day' => (int) ($event['all_day'] ?? 0) === 1,
                'notes' => (string) ($event['notes'] ?? ''),
                'url' => url('/events/' . (string) $id),
            ],
        ]);
    }

    public function store(): void
    {
        require_business_role(['general_user', 'admin', 'site_admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $this->sendJson(['ok' => false, 'error' => 'Session expired. Please reload and try again.'], 422);
        }

        $businessId = current_business_id();
        $actorId = (int) (auth_user_id() ?? 0);
        $payload = $this->payloadFromPost($_POST);
        $errors = $this->validatePayload($payload);
        if ($errors !== []) {
            $this->sendJson(['ok' => false, 'errors' => $errors], 422);
        }

        $id = Event::create($businessId, $payload, $actorId);
        $event = $id > 0 ? Event::findForBusiness($businessId, $id) : null;
        if ($event === null) {
            $this->sendJson(['ok' => false, 'error' => 'Event created but could not be loaded.'], 500);
        }

        $this->sendJson(['ok' => true, 'id' => $id, 'url' => url('/events/' . (string) $id)], 201);
    }

    public function update(array $params): void
    {
        require_business_role(['general_user', 'admin', 'site_admin']);

        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $this->sendJson(['ok' => false, 'error' => 'Invalid event id.'], 422);
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $this->sendJson(['ok' => false, 'error' => 'Session expired. Please reload and try again.'], 422);
        }

        $businessId = current_business_id();
        if (Event::findForBusiness($businessId, $id) === null) {
            $this->sendJson(['ok' => false, 'error' => 'Event not found.'], 404);
        }

        $actorId = (int) (auth_user_id() ?? 0);
        $payload = $this->payloadFromPost($_POST);
        $errors = $this->validatePayload($payload);
        if ($errors !== []) {
            $this->sendJson(['ok' => false, 'errors' => $errors], 422);
        }

        Event::update($businessId, $id, $payload, $actorId);
        $this->sendJson(['ok' => true, 'id' => $id, 'url' => url('/events/' . (string) $id)]);
    }

    public function cancel(array $params): void
    {
        require_business_role(['general_user', 'admin', 'site_admin']);

        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $this->sendJson(['ok' => false, 'error' => 'Invalid event id.'], 422);
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $this->sendJson(['ok' => false, 'error' => 'Session expired. Please reload and try again.'], 422);
        }

        $cancel = ((string) ($_POST['cancel'] ?? '1')) !== '0';
        $businessId = current_business_id();
        $actorId = (int) (auth_user_id() ?? 0);
        if (Event::findForBusiness($businessId, $id) === null) {
            $this->sendJson(['ok' => false, 'error' => 'Event not found.'], 404);
        }

        Event::setCancelled($businessId, $id, $cancel, $actorId);
        $this->sendJson(['ok' => true]);
    }

    public function move(array $params): void
    {
        require_business_role(['general_user', 'admin', 'site_admin']);

        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $this->sendJson(['ok' => false, 'error' => 'Invalid event id.'], 422);
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $this->sendJson(['ok' => false, 'error' => 'Session expired. Please reload and try again.'], 422);
        }

        $businessId = current_business_id();
        if (Event::findForBusiness($businessId, $id) === null) {
            $this->sendJson(['ok' => false, 'error' => 'Event not found.'], 404);
        }

        $startAt = (string) ($_POST['start_at'] ?? '');
        $endAt = isset($_POST['end_at']) ? (string) $_POST['end_at'] : null;
        $allDay = ((string) ($_POST['all_day'] ?? '0')) === '1';
        $actorId = (int) (auth_user_id() ?? 0);

        if (!Event::move($businessId, $id, $startAt, $endAt, $allDay, $actorId)) {
            $this->sendJson(['ok' => false, 'error' => 'Unable to move event.'], 422);
        }

        $this->sendJson(['ok' => true]);
    }

    public function delete(array $params): void
    {
        require_business_role(['general_user', 'admin', 'site_admin']);

        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $this->sendJson(['ok' => false, 'error' => 'Invalid event id.'], 422);
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $this->sendJson(['ok' => false, 'error' => 'Session expired. Please reload and try again.'], 422);
        }

        $businessId = current_business_id();
        $actorId = (int) (auth_user_id() ?? 0);
        if (Event::findForBusiness($businessId, $id) === null) {
            $this->sendJson(['ok' => false, 'error' => 'Event not found.'], 404);
        }

        Event::softDelete($businessId, $id, $actorId);
        $this->sendJson(['ok' => true]);
    }

    private function sendJson(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function payloadFromPost(array $post): array
    {
        return [
            'title' => trim((string) ($post['title'] ?? '')),
            'type' => trim((string) ($post['type'] ?? 'appointment')),
            'status' => trim((string) ($post['status'] ?? 'scheduled')),
            'start_at' => (string) ($post['start_at'] ?? ''),
            'end_at' => (string) ($post['end_at'] ?? ''),
            'all_day' => (string) ($post['all_day'] ?? '0'),
            'notes' => trim((string) ($post['notes'] ?? '')),
        ];
    }

    private function validatePayload(array $payload): array
    {
        $errors = [];
        if (trim((string) ($payload['title'] ?? '')) === '') {
            $errors['title'] = 'Title is required.';
        }
        if (trim((string) ($payload['start_at'] ?? '')) === '') {
            $errors['start_at'] = 'Start date/time is required.';
        }
        return $errors;
    }
}

