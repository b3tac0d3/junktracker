<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\SiteAdminTicket;
use Core\Controller;

final class SiteSupportController extends Controller
{
    public function index(): void
    {
        $user = $this->requireAuthenticatedUser();
        if ($user === null) {
            return;
        }

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? 'all')),
            'category' => trim((string) ($_GET['category'] ?? 'all')),
        ];

        $this->render('site_support/index', [
            'pageTitle' => 'My Site Requests',
            'filters' => $filters,
            'tickets' => SiteAdminTicket::listForUser((int) ($user['id'] ?? 0), $filters),
            'statuses' => SiteAdminTicket::STATUSES,
            'categories' => SiteAdminTicket::CATEGORIES,
        ]);

        clear_old();
    }

    public function create(): void
    {
        $user = $this->requireAuthenticatedUser();
        if ($user === null) {
            return;
        }

        $defaultEmail = trim((string) ($user['email'] ?? ''));
        $this->render('site_support/create', [
            'pageTitle' => 'Contact Site Admin',
            'defaultEmail' => $defaultEmail,
            'categories' => SiteAdminTicket::CATEGORIES,
        ]);

        clear_old();
    }

    public function store(): void
    {
        $user = $this->requireAuthenticatedUser();
        if ($user === null) {
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/support/new');
        }

        $data = [
            'submitted_by_email' => trim((string) ($_POST['submitted_by_email'] ?? ($user['email'] ?? ''))),
            'category' => trim((string) ($_POST['category'] ?? 'question')),
            'subject' => trim((string) ($_POST['subject'] ?? '')),
            'message' => trim((string) ($_POST['message'] ?? '')),
            'priority' => (int) ($_POST['priority'] ?? 3),
            'business_id' => current_business_id(),
        ];

        $errors = $this->validateCreate($data);
        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/support/new');
        }

        $ticketId = SiteAdminTicket::create($data, (int) ($user['id'] ?? 0), auth_user_id());
        if ($ticketId <= 0) {
            flash('error', 'Unable to submit your request right now.');
            redirect('/support/new');
        }

        log_user_action('site_support_ticket_created', 'site_admin_tickets', $ticketId, 'Submitted site support ticket #' . $ticketId . '.');
        flash('success', 'Your request was submitted to Site Admin.');
        redirect('/support/' . $ticketId);
    }

    public function show(array $params): void
    {
        $user = $this->requireAuthenticatedUser();
        if ($user === null) {
            return;
        }

        $ticketId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($ticketId <= 0) {
            redirect('/support');
        }

        $ticket = SiteAdminTicket::findByIdForUser($ticketId, (int) ($user['id'] ?? 0));
        if (!$ticket) {
            $this->renderNotFound();
            return;
        }

        $notes = SiteAdminTicket::notes($ticketId, false);

        $this->render('site_support/show', [
            'pageTitle' => 'Site Request #' . $ticketId,
            'ticket' => $ticket,
            'notes' => $notes,
            'statusLabel' => SiteAdminTicket::labelStatus((string) ($ticket['status'] ?? 'unopened')),
            'categoryLabel' => SiteAdminTicket::labelCategory((string) ($ticket['category'] ?? 'other')),
        ]);

        clear_old();
    }

    public function addNote(array $params): void
    {
        $user = $this->requireAuthenticatedUser();
        if ($user === null) {
            return;
        }

        $ticketId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($ticketId <= 0) {
            redirect('/support');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/support/' . $ticketId);
        }

        $ticket = SiteAdminTicket::findByIdForUser($ticketId, (int) ($user['id'] ?? 0));
        if (!$ticket) {
            $this->renderNotFound();
            return;
        }

        $note = trim((string) ($_POST['note'] ?? ''));
        if ($note === '') {
            flash('error', 'Note is required.');
            flash_old($_POST);
            redirect('/support/' . $ticketId);
        }

        SiteAdminTicket::addNote($ticketId, (int) ($user['id'] ?? 0), $note, 'customer', true);
        log_user_action('site_support_ticket_note_added', 'site_admin_tickets', $ticketId, 'Added requester note to site support ticket #' . $ticketId . '.');
        flash('success', 'Your update was sent to Site Admin.');
        redirect('/support/' . $ticketId);
    }

    private function requireAuthenticatedUser(): ?array
    {
        $user = auth_user();
        if (!$user) {
            redirect('/login');
            return null;
        }

        return $user;
    }

    private function validateCreate(array $data): array
    {
        $errors = [];

        if (trim((string) ($data['submitted_by_email'] ?? '')) === '' || !filter_var((string) $data['submitted_by_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid reply email is required.';
        }
        if (trim((string) ($data['subject'] ?? '')) === '') {
            $errors[] = 'Subject is required.';
        }
        if (trim((string) ($data['message'] ?? '')) === '') {
            $errors[] = 'Message is required.';
        }
        if ((int) ($data['priority'] ?? 0) < 1 || (int) ($data['priority'] ?? 0) > 5) {
            $errors[] = 'Priority must be between 1 and 5.';
        }
        if (!in_array((string) ($data['category'] ?? ''), SiteAdminTicket::CATEGORIES, true)) {
            $errors[] = 'Category is invalid.';
        }
        if (strlen((string) ($data['subject'] ?? '')) > 255) {
            $errors[] = 'Subject is too long.';
        }

        return $errors;
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
