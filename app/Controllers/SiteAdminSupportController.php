<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\SiteAdminTicket;
use Core\Controller;

final class SiteAdminSupportController extends Controller
{
    public function index(): void
    {
        $this->authorize();

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? 'all')),
            'category' => trim((string) ($_GET['category'] ?? 'all')),
            'priority' => (int) ($_GET['priority'] ?? 0),
            'assigned_to_user_id' => trim((string) ($_GET['assigned_to_user_id'] ?? '')),
            'business_id' => (int) ($_GET['business_id'] ?? 0),
        ];

        $this->render('site_admin/support/index', [
            'pageTitle' => 'Site Admin Queue',
            'filters' => $filters,
            'summary' => SiteAdminTicket::summary($filters),
            'tickets' => SiteAdminTicket::adminQueue($filters),
            'categories' => SiteAdminTicket::CATEGORIES,
            'statuses' => SiteAdminTicket::STATUSES,
            'assignees' => SiteAdminTicket::assignableSiteAdmins(),
            'businesses' => \App\Models\Business::search('', 'all'),
        ]);

        clear_old();
    }

    public function show(array $params): void
    {
        $this->authorize();

        $ticketId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($ticketId <= 0) {
            redirect('/site-admin/support');
        }

        $ticket = SiteAdminTicket::findByIdForAdmin($ticketId);
        if (!$ticket) {
            $this->renderNotFound();
            return;
        }

        SiteAdminTicket::markViewedByAdmin($ticketId, (int) (auth_user_id() ?? 0));
        $ticket = SiteAdminTicket::findByIdForAdmin($ticketId) ?: $ticket;

        $this->render('site_admin/support/show', [
            'pageTitle' => 'Support Ticket #' . $ticketId,
            'ticket' => $ticket,
            'notes' => SiteAdminTicket::notes($ticketId, true),
            'categories' => SiteAdminTicket::CATEGORIES,
            'statuses' => SiteAdminTicket::STATUSES,
            'assignees' => SiteAdminTicket::assignableSiteAdmins(),
            'statusLabel' => SiteAdminTicket::labelStatus((string) ($ticket['status'] ?? 'unopened')),
            'categoryLabel' => SiteAdminTicket::labelCategory((string) ($ticket['category'] ?? 'other')),
        ]);

        clear_old();
    }

    public function update(array $params): void
    {
        $this->authorize();

        $ticketId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($ticketId <= 0) {
            redirect('/site-admin/support');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/site-admin/support/' . $ticketId);
        }

        if (!SiteAdminTicket::findByIdForAdmin($ticketId)) {
            $this->renderNotFound();
            return;
        }

        $data = [
            'status' => trim((string) ($_POST['status'] ?? 'unopened')),
            'category' => trim((string) ($_POST['category'] ?? 'question')),
            'priority' => (int) ($_POST['priority'] ?? 3),
            'assigned_to_user_id' => (int) ($_POST['assigned_to_user_id'] ?? 0),
        ];
        SiteAdminTicket::updateFromAdmin($ticketId, $data, (int) (auth_user_id() ?? 0));

        $note = trim((string) ($_POST['note'] ?? ''));
        if ($note !== '') {
            $visibility = !empty($_POST['internal_only']) ? 'internal' : 'customer';
            SiteAdminTicket::addNote($ticketId, (int) (auth_user_id() ?? 0), $note, $visibility, false);
        }

        log_user_action('site_support_ticket_updated', 'site_admin_tickets', $ticketId, 'Updated site support ticket #' . $ticketId . '.');
        flash('success', 'Ticket updated.');
        redirect('/site-admin/support/' . $ticketId);
    }

    public function pickup(array $params): void
    {
        $this->authorize();

        $ticketId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($ticketId <= 0) {
            redirect('/site-admin/support');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/site-admin/support/' . $ticketId);
        }

        SiteAdminTicket::pickUp($ticketId, (int) (auth_user_id() ?? 0));
        log_user_action('site_support_ticket_picked_up', 'site_admin_tickets', $ticketId, 'Picked up site support ticket #' . $ticketId . '.');
        flash('success', 'Ticket assigned to you.');
        redirect('/site-admin/support/' . $ticketId);
    }

    public function addNote(array $params): void
    {
        $this->authorize();

        $ticketId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($ticketId <= 0) {
            redirect('/site-admin/support');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/site-admin/support/' . $ticketId);
        }

        $note = trim((string) ($_POST['note'] ?? ''));
        if ($note === '') {
            flash('error', 'Note is required.');
            redirect('/site-admin/support/' . $ticketId);
        }

        $visibility = !empty($_POST['internal_only']) ? 'internal' : 'customer';
        SiteAdminTicket::addNote($ticketId, (int) (auth_user_id() ?? 0), $note, $visibility, false);
        log_user_action('site_support_ticket_note_added', 'site_admin_tickets', $ticketId, 'Added admin note to site support ticket #' . $ticketId . '.');
        flash('success', 'Note added.');
        redirect('/site-admin/support/' . $ticketId);
    }

    public function convertBug(array $params): void
    {
        $this->authorize();

        $ticketId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($ticketId <= 0) {
            redirect('/site-admin/support');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/site-admin/support/' . $ticketId);
        }

        $bugId = SiteAdminTicket::convertToBug($ticketId, (int) (auth_user_id() ?? 0), [
            'severity' => (int) ($_POST['severity'] ?? 3),
            'environment' => trim((string) ($_POST['environment'] ?? 'both')),
        ]);
        if ($bugId === null || $bugId <= 0) {
            flash('error', 'Unable to convert this ticket to a bug.');
            redirect('/site-admin/support/' . $ticketId);
        }

        log_user_action('site_support_ticket_converted_to_bug', 'site_admin_tickets', $ticketId, 'Converted site support ticket #' . $ticketId . ' to bug #' . $bugId . '.');
        flash('success', 'Converted to bug #' . $bugId . '.');
        redirect('/dev/bugs/' . $bugId);
    }

    private function authorize(): void
    {
        require_role(4);
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
