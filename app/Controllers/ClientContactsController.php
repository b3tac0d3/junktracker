<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Prospect;
use App\Models\Task;
use Core\Controller;

final class ClientContactsController extends Controller
{
    public function index(): void
    {
        $this->authorize('view');

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'client_id' => $this->toIntOrNull($_GET['client_id'] ?? null),
            'record_status' => (string) ($_GET['record_status'] ?? 'active'),
        ];

        if (!in_array($filters['record_status'], ['active', 'inactive', 'all'], true)) {
            $filters['record_status'] = 'active';
        }

        $this->render('client_contacts/index', [
            'pageTitle' => 'Client Contacts',
            'contacts' => ClientContact::filter($filters),
            'filters' => $filters,
        ]);
    }

    public function create(): void
    {
        $this->authorize('create');

        $prefillProspectId = $this->toIntOrNull($_GET['prospect_id'] ?? null);
        $prefillProspect = ($prefillProspectId !== null && $prefillProspectId > 0) ? Prospect::findById($prefillProspectId) : null;

        $prefillClientId = $this->toIntOrNull($_GET['client_id'] ?? null);
        if (($prefillClientId === null || $prefillClientId <= 0) && $prefillProspect) {
            $prefillClientId = isset($prefillProspect['client_id']) ? (int) $prefillProspect['client_id'] : null;
        }
        $prefillClient = ($prefillClientId !== null && $prefillClientId > 0) ? Client::findById($prefillClientId) : null;
        $prefillClientLabel = '';
        if ($prefillClient) {
            $prefillClientLabel = trim((string) (($prefillClient['first_name'] ?? '') . ' ' . ($prefillClient['last_name'] ?? '')));
            if ($prefillClientLabel === '') {
                $prefillClientLabel = (string) ($prefillClient['business_name'] ?? ('Client #' . $prefillClientId));
            }
        }

        $prefillLinkType = 'general';
        $prefillLinkId = null;
        $prefillLinkLabel = '';
        if ($prefillProspectId !== null && $prefillProspectId > 0) {
            $prefillLinkType = 'prospect';
            $prefillLinkId = $prefillProspectId;
            $prefillLink = Task::resolveLink('prospect', $prefillProspectId);
            $prefillLinkLabel = (string) ($prefillLink['label'] ?? ('Prospect #' . $prefillProspectId));
        }

        $this->render('client_contacts/create', [
            'pageTitle' => 'Log Client Contact',
            'contact' => [
                'client_id' => $prefillClientId,
                'client_name' => $prefillClientLabel,
                'contact_method' => 'call',
                'direction' => 'outbound',
                'contacted_at' => date('Y-m-d H:i:s'),
                'link_type' => $prefillLinkType,
                'link_id' => $prefillLinkId,
                'link_label' => $prefillLinkLabel,
            ],
            'contactMethods' => ClientContact::CONTACT_METHODS,
            'directions' => ClientContact::DIRECTIONS,
            'linkTypes' => Task::LINK_TYPES,
            'linkTypeLabels' => $this->linkTypeLabels(),
            'pageScripts' => $this->formScripts(),
        ]);

        clear_old();
    }

    public function store(): void
    {
        $this->authorize('create');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/client-contacts/new');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);
        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            $redirectPath = '/client-contacts/new';
            if (!empty($data['client_id'])) {
                $redirectPath .= '?client_id=' . (string) $data['client_id'];
            }
            redirect($redirectPath);
        }

        $contactId = ClientContact::create($data, auth_user_id());
        $this->queueFollowUpTask($contactId, $data);
        log_user_action('client_contact_created', 'client_contacts', $contactId, 'Logged client contact #' . $contactId . '.');
        flash('success', 'Client contact logged.');
        redirect('/client-contacts/' . $contactId);
    }

    public function show(array $params): void
    {
        $this->authorize('view');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/client-contacts');
        }

        $contact = ClientContact::findById($id);
        if (!$contact) {
            http_response_code(404);
            if (class_exists('App\\Controllers\\ErrorController')) {
                (new \App\Controllers\ErrorController())->notFound();
                return;
            }
            echo '404 Not Found';
            return;
        }

        $this->render('client_contacts/show', [
            'pageTitle' => 'Client Contact',
            'contact' => $contact,
        ]);
    }

    private function collectFormData(): array
    {
        $contactMethod = trim((string) ($_POST['contact_method'] ?? 'call'));
        if (!in_array($contactMethod, ClientContact::CONTACT_METHODS, true)) {
            $contactMethod = 'call';
        }

        $direction = trim((string) ($_POST['direction'] ?? 'outbound'));
        if (!in_array($direction, ClientContact::DIRECTIONS, true)) {
            $direction = 'outbound';
        }

        $linkType = trim((string) ($_POST['link_type'] ?? 'general'));
        if (!in_array($linkType, Task::LINK_TYPES, true)) {
            $linkType = 'general';
        }

        $clientId = $this->toIntOrNull($_POST['client_id'] ?? null);
        $linkId = $this->toIntOrNull($_POST['link_id'] ?? null);
        if ($linkType === 'general') {
            $linkId = null;
        } elseif ($linkType === 'client' && ($linkId === null || $linkId <= 0)) {
            $linkId = $clientId;
        }

        return [
            'client_id' => $clientId,
            'link_type' => $linkType,
            'link_id' => $linkId,
            'contact_method' => $contactMethod,
            'direction' => $direction,
            'subject' => trim((string) ($_POST['subject'] ?? '')),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
            'contacted_at' => $this->toDateTimeOrNull($_POST['contacted_at'] ?? null),
            'follow_up_at' => $this->toDateTimeOrNull($_POST['follow_up_at'] ?? null),
        ];
    }

    private function authorize(string $action): void
    {
        require_permission('client_contacts', $action);
    }

    private function validate(array $data): array
    {
        $errors = [];

        if ($data['client_id'] === null || $data['client_id'] <= 0) {
            $errors[] = 'Select a client from the suggestions.';
        } elseif (!ClientContact::clientExists($data['client_id'])) {
            $errors[] = 'Selected client is invalid.';
        }

        if (!in_array($data['contact_method'], ClientContact::CONTACT_METHODS, true)) {
            $errors[] = 'Contact method is invalid.';
        }
        if (!in_array($data['direction'], ClientContact::DIRECTIONS, true)) {
            $errors[] = 'Direction is invalid.';
        }
        if ($data['contacted_at'] === null) {
            $errors[] = 'Contact date and time is required.';
        }

        if ($data['link_type'] !== 'general') {
            if ($data['link_id'] === null || $data['link_id'] <= 0) {
                $errors[] = 'Select a valid linked record.';
            } elseif (!Task::linkExists($data['link_type'], $data['link_id'])) {
                $errors[] = 'Linked record is invalid.';
            }
        }

        return $errors;
    }

    private function formScripts(): string
    {
        return implode("\n", [
            '<script src="' . asset('js/task-link-lookup.js') . '"></script>',
            '<script src="' . asset('js/client-contact-client-lookup.js') . '"></script>',
        ]);
    }

    private function linkTypeLabels(): array
    {
        $labels = [];
        foreach (Task::LINK_TYPES as $type) {
            $labels[$type] = Task::linkTypeLabel($type);
        }
        return $labels;
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

    private function toDateTimeOrNull(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $time = strtotime($raw);
        if ($time === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $time);
    }

    private function queueFollowUpTask(int $contactId, array $contactData): void
    {
        $followUpAt = (string) ($contactData['follow_up_at'] ?? '');
        if ($contactId <= 0 || $followUpAt === '') {
            return;
        }

        $actorId = auth_user_id();
        if ($actorId === null || $actorId <= 0) {
            return;
        }

        $taskLinkType = 'client';
        $taskLinkId = isset($contactData['client_id']) ? (int) $contactData['client_id'] : 0;
        $taskTitle = 'Client Contact Follow-Up';

        $contactLinkType = (string) ($contactData['link_type'] ?? 'general');
        $contactLinkId = isset($contactData['link_id']) ? (int) $contactData['link_id'] : 0;
        if ($contactLinkType === 'prospect' && $contactLinkId > 0) {
            $taskLinkType = 'prospect';
            $taskLinkId = $contactLinkId;
            $taskTitle = 'Prospect Contact Follow-Up';
        }

        if ($taskLinkId <= 0 || !Task::linkExists($taskLinkType, $taskLinkId)) {
            return;
        }

        $subject = trim((string) ($contactData['subject'] ?? ''));
        if ($subject !== '') {
            $taskTitle = $taskTitle . ': ' . $subject;
        }

        $method = ucwords(str_replace('_', ' ', (string) ($contactData['contact_method'] ?? 'call')));
        $direction = ucwords((string) ($contactData['direction'] ?? 'outbound'));
        $bodyLines = [
            'Auto-created from client contact #' . $contactId . '.',
            'Method: ' . $method . ' (' . $direction . ')',
        ];

        $notes = trim((string) ($contactData['notes'] ?? ''));
        if ($notes !== '') {
            $bodyLines[] = '';
            $bodyLines[] = $notes;
        }

        Task::create([
            'title' => substr($taskTitle, 0, 255),
            'body' => implode("\n", $bodyLines),
            'link_type' => $taskLinkType,
            'link_id' => $taskLinkId,
            'assigned_user_id' => $actorId,
            'importance' => 3,
            'status' => 'open',
            'outcome' => '',
            'due_at' => $followUpAt,
            'completed_at' => null,
        ], $actorId);
    }
}
