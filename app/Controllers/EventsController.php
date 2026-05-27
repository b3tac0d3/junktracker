<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Event;
use App\Models\EventFeed;
use App\Services\GoogleCalendarSync;
use Core\Controller;

final class EventsController extends Controller
{
    public function index(): void
    {
        require_business_role(['general_user', 'admin', 'punch_only', 'site_admin']);

        $this->render('events/index', [
            'pageTitle' => 'Events',
            'canViewFinancials' => can_view_financials(),
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

    public function create(): void
    {
        require_business_role(['general_user', 'admin', 'site_admin']);

        $at = calendar_slot_prefill_at();
        $type = strtolower(trim((string) ($_GET['type'] ?? 'appointment')));
        if (!in_array($type, ['appointment', 'cancellation', 'reminder', 'note', 'other'], true)) {
            $type = 'appointment';
        }

        $this->render('events/form', [
            'pageTitle' => 'Add Event',
            'mode' => 'create',
            'actionUrl' => url('/events/create'),
            'form' => [
                'title' => '',
                'type' => $type,
                'status' => 'scheduled',
                'start_at' => $at,
                'end_at' => calendar_slot_prefill_end_at($at, 60),
                'all_day' => '0',
                'notes' => '',
            ],
            'errors' => [],
        ]);
    }

    public function storeForm(): void
    {
        require_business_role(['general_user', 'admin', 'site_admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/events/create');
        }

        $businessId = current_business_id();
        $actorId = (int) (auth_user_id() ?? 0);
        $payload = $this->payloadFromPost($_POST);
        $errors = $this->validatePayload($payload);
        if ($errors !== []) {
            $this->render('events/form', [
                'pageTitle' => 'Add Event',
                'mode' => 'create',
                'actionUrl' => url('/events/create'),
                'form' => [
                    'title' => trim((string) ($payload['title'] ?? '')),
                    'type' => trim((string) ($payload['type'] ?? 'appointment')),
                    'status' => trim((string) ($payload['status'] ?? 'scheduled')),
                    'start_at' => trim((string) ($payload['start_at'] ?? '')),
                    'end_at' => trim((string) ($payload['end_at'] ?? '')),
                    'all_day' => trim((string) ($payload['all_day'] ?? '0')),
                    'notes' => trim((string) ($payload['notes'] ?? '')),
                ],
                'errors' => $errors,
            ]);
            return;
        }

        $id = Event::create($businessId, $payload, $actorId);
        if ($id <= 0) {
            flash('error', 'Unable to create event.');
            redirect('/events/create');
        }

        audit('event_created', 'events', $id, ['title' => trim((string) ($payload['title'] ?? ''))]);
        GoogleCalendarSync::syncEvent($actorId, $businessId, $id);
        flash('success', 'Event created.');
        redirect('/events/' . (string) $id);
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
            'canManageEvent' => $this->canManageEvents(),
        ]);
    }

    public function edit(array $params): void
    {
        require_business_role(['general_user', 'admin', 'site_admin']);

        $id = (int) ($params['id'] ?? 0);
        $businessId = current_business_id();
        $event = $id > 0 ? Event::findForBusiness($businessId, $id) : null;
        if ($event === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('events/form', [
            'pageTitle' => 'Edit Event',
            'mode' => 'edit',
            'actionUrl' => url('/events/' . (string) $id . '/edit'),
            'cancelUrl' => url('/events/' . (string) $id),
            'form' => $this->formFromEvent($event),
            'errors' => [],
            'eventId' => $id,
        ]);
    }

    public function updateForm(array $params): void
    {
        require_business_role(['general_user', 'admin', 'site_admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/events');
        }

        $id = (int) ($params['id'] ?? 0);
        $businessId = current_business_id();
        $event = $id > 0 ? Event::findForBusiness($businessId, $id) : null;
        if ($event === null) {
            flash('error', 'Event not found.');
            redirect('/events');
        }

        $actorId = (int) (auth_user_id() ?? 0);
        $payload = $this->payloadFromPost($_POST);
        $errors = $this->validatePayload($payload);
        if ($errors !== []) {
            $this->render('events/form', [
                'pageTitle' => 'Edit Event',
                'mode' => 'edit',
                'actionUrl' => url('/events/' . (string) $id . '/edit'),
                'cancelUrl' => url('/events/' . (string) $id),
                'form' => [
                    'title' => trim((string) ($payload['title'] ?? '')),
                    'type' => trim((string) ($payload['type'] ?? 'appointment')),
                    'status' => trim((string) ($payload['status'] ?? 'scheduled')),
                    'start_at' => trim((string) ($payload['start_at'] ?? '')),
                    'end_at' => trim((string) ($payload['end_at'] ?? '')),
                    'all_day' => trim((string) ($payload['all_day'] ?? '0')),
                    'notes' => trim((string) ($payload['notes'] ?? '')),
                ],
                'errors' => $errors,
                'eventId' => $id,
            ]);
            return;
        }

        Event::update($businessId, $id, $payload, $actorId);
        audit('event_updated', 'events', $id);
        GoogleCalendarSync::syncEvent($actorId, $businessId, $id);
        flash('success', 'Event updated.');
        redirect('/events/' . (string) $id);
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

        audit('event_created', 'events', $id, ['title' => trim((string) ($payload['title'] ?? ''))]);
        GoogleCalendarSync::syncEvent($actorId, $businessId, $id);
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
        audit('event_updated', 'events', $id);
        GoogleCalendarSync::syncEvent($actorId, $businessId, $id);
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
        audit($cancel ? 'event_cancelled' : 'event_restored', 'events', $id);
        GoogleCalendarSync::syncEvent($actorId, $businessId, $id);
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

        audit('event_moved', 'events', $id);
        GoogleCalendarSync::syncEvent($actorId, $businessId, $id);
        $this->sendJson(['ok' => true]);
    }

    public function delete(array $params): void
    {
        require_business_role(['general_user', 'admin', 'site_admin']);

        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            if ($this->wantsJson()) {
                $this->sendJson(['ok' => false, 'error' => 'Invalid event id.'], 422);
            }
            flash('error', 'Invalid event.');
            redirect('/events');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            if ($this->wantsJson()) {
                $this->sendJson(['ok' => false, 'error' => 'Session expired. Please reload and try again.'], 422);
            }
            flash('error', 'Session expired. Please try again.');
            redirect('/events');
        }

        $businessId = current_business_id();
        $actorId = (int) (auth_user_id() ?? 0);
        if (Event::findForBusiness($businessId, $id) === null) {
            if ($this->wantsJson()) {
                $this->sendJson(['ok' => false, 'error' => 'Event not found.'], 404);
            }
            flash('error', 'Event not found.');
            redirect('/events');
        }

        Event::softDelete($businessId, $id, $actorId);
        audit('event_deleted', 'events', $id);
        GoogleCalendarSync::removeEvent($actorId, $id);

        if ($this->wantsJson()) {
            $this->sendJson(['ok' => true]);
        }

        flash('success', 'Event deleted.');
        redirect('/events');
    }

    private function wantsJson(): bool
    {
        $accept = strtolower(trim((string) ($_SERVER['HTTP_ACCEPT'] ?? '')));
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        return strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''))) === 'xmlhttprequest';
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, string>
     */
    private function formFromEvent(array $event): array
    {
        return [
            'title' => trim((string) ($event['title'] ?? '')),
            'type' => trim((string) ($event['type'] ?? 'appointment')),
            'status' => trim((string) ($event['status'] ?? 'scheduled')),
            'start_at' => trim((string) ($event['start_at'] ?? '')),
            'end_at' => trim((string) ($event['end_at'] ?? '')),
            'all_day' => (int) ($event['all_day'] ?? 0) === 1 ? '1' : '0',
            'notes' => trim((string) ($event['notes'] ?? '')),
        ];
    }

    private function canManageEvents(): bool
    {
        $user = auth_user();
        $role = strtolower(trim((string) ($user['role'] ?? '')));

        return in_array($role, ['general_user', 'admin', 'site_admin'], true);
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

