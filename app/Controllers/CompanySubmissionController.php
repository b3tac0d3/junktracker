<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Business;
use App\Models\DevTrackerItem;
use App\Models\DevTrackerLog;
use Core\Controller;

abstract class CompanySubmissionController extends Controller
{
    abstract protected function submissionType(): string;

    abstract protected function routePrefix(): string;

    /**
     * @return array<string, string>
     */
    protected function submissionLabels(): array
    {
        if ($this->submissionType() === 'update') {
            return [
                'section' => 'Update Requests',
                'section_singular' => 'Update Request',
                'index_desc' => 'Request product updates for dev review and future releases.',
                'create_title' => 'Request an Update',
                'create_desc' => 'Your request goes to the dev team for review before it is scheduled for a release.',
                'create_button' => 'Submit Request',
                'create_icon' => 'fa-wrench',
                'list_title' => 'Submitted Requests',
                'list_empty' => 'No update requests yet.',
                'notes_label' => 'Requested change',
                'notes_placeholder' => 'Describe the update you want in a future release — workflow, UI, reports, etc.',
                'notes_required' => 'Describe the requested update so devs can review it.',
                'submit_success' => 'Update request submitted for dev review.',
                'log_success' => 'Update added to the request log.',
                'pending_alert' => 'This request is waiting for dev review.',
                'accepted_alert' => 'Accepted by devs and queued for future release work',
                'rejected_alert' => 'This request was reviewed and not accepted for the roadmap.',
            ];
        }

        return [
            'section' => 'Bug Reports',
            'section_singular' => 'Bug Report',
            'index_desc' => 'Submit issues for dev review.',
            'create_title' => 'Report a Bug',
            'create_desc' => 'Your report goes to the dev team for review before it enters the bug tracker.',
            'create_button' => 'Submit for Review',
            'create_icon' => 'fa-bug',
            'list_title' => 'Submitted Reports',
            'list_empty' => 'No bug reports yet.',
            'notes_label' => 'Description',
            'notes_placeholder' => 'What happened, what you expected, and steps to reproduce...',
            'notes_required' => 'Describe the issue so devs can review it.',
            'submit_success' => 'Bug report submitted for dev review.',
            'log_success' => 'Update added to the bug log.',
            'pending_alert' => 'This report is waiting for dev review.',
            'accepted_alert' => 'Accepted by devs and tracked as bug',
            'rejected_alert' => 'This report was reviewed and not accepted as a bug.',
        ];
    }

    public function index(): void
    {
        $this->requireSubmissionAccess();

        $businessId = current_business_id();
        $search = trim((string) ($_GET['q'] ?? ''));
        $perPage = pagination_per_page($_GET['per_page'] ?? null, 25);
        $page = pagination_current_page($_GET['page'] ?? null);
        $type = $this->submissionType();
        $totalRows = DevTrackerItem::indexCountForBusiness($businessId, $search, $type);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);
        $items = DevTrackerItem::indexListForBusiness($businessId, $search, $type, $perPage, $offset);
        $labels = $this->submissionLabels();

        $this->render('company_submissions/index', [
            'pageTitle' => $labels['section'],
            'search' => $search,
            'items' => $items,
            'pagination' => pagination_meta($page, $perPage, $totalRows, count($items)),
            'business' => Business::findById($businessId),
            'labels' => $labels,
            'routePrefix' => $this->routePrefix(),
            'createIcon' => $labels['create_icon'],
        ]);
    }

    public function create(): void
    {
        $this->requireSubmissionAccess();
        $labels = $this->submissionLabels();

        $this->render('company_submissions/form', [
            'pageTitle' => $labels['create_title'],
            'actionUrl' => url($this->routePrefix()),
            'form' => $this->defaultForm(),
            'errors' => [],
            'labels' => $labels,
            'routePrefix' => $this->routePrefix(),
        ]);
    }

    public function store(): void
    {
        $this->requireSubmissionAccess();
        $labels = $this->submissionLabels();
        $createUrl = url($this->routePrefix() . '/create');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect($this->routePrefix() . '/create');
        }

        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form, $labels);
        if ($errors !== []) {
            $this->render('company_submissions/form', [
                'pageTitle' => $labels['create_title'],
                'actionUrl' => url($this->routePrefix()),
                'form' => $form,
                'errors' => $errors,
                'labels' => $labels,
                'routePrefix' => $this->routePrefix(),
            ]);
            return;
        }

        $businessId = current_business_id();
        $actorId = auth_user_id() ?? 0;
        $itemId = DevTrackerItem::createSubmission($form, $businessId, $actorId, $this->submissionType());
        if ($itemId <= 0) {
            flash('error', 'Submissions are not available yet. Run the latest migration.');
            redirect($this->routePrefix() . '/create');
        }

        $screenshot = dev_tracker_store_screenshot($itemId, $_FILES['screenshot'] ?? null);
        if ($screenshot['error'] !== null) {
            flash('error', $screenshot['error']);
            redirect($this->routePrefix() . '/' . (string) $itemId);
        }

        DevTrackerLog::append($itemId, 'created', [
            'body' => $form['notes'] !== '' ? $form['notes'] : $form['title'],
            'screenshot_path' => $screenshot['path'],
        ], $actorId);

        flash('success', $labels['submit_success']);
        redirect($this->routePrefix() . '/' . (string) $itemId);
    }

    public function show(array $params): void
    {
        $this->requireSubmissionAccess();

        $item = $this->itemOr404((int) ($params['id'] ?? 0));
        if ($item === null) {
            return;
        }

        $labels = $this->submissionLabels();

        $this->render('company_submissions/show', [
            'pageTitle' => $labels['section_singular'],
            'item' => $item,
            'logEntries' => DevTrackerLog::forItem((int) ($item['id'] ?? 0)),
            'business' => Business::findById((int) ($item['business_id'] ?? 0)),
            'labels' => $labels,
            'routePrefix' => $this->routePrefix(),
        ]);
    }

    public function addLog(array $params): void
    {
        $this->requireSubmissionAccess();

        $itemId = (int) ($params['id'] ?? 0);
        if ($this->itemOr404($itemId) === null) {
            return;
        }

        flash('error', 'Updates are not available on submitted reports right now.');
        redirect($this->routePrefix() . '/' . (string) $itemId);
    }

    protected function requireSubmissionAccess(): void
    {
        business_context_required();
        if (!can_manage_bug_reports()) {
            \Core\ErrorHandler::renderHttpError(403, 'Access denied', 'Company submissions are limited to workspace admins.');
            exit;
        }
    }

    /**
     * @return array<string, string>
     */
    protected function defaultForm(): array
    {
        return [
            'title' => '',
            'notes' => '',
            'area' => '',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    protected function formFromPost(array $input): array
    {
        return [
            'title' => trim((string) ($input['title'] ?? '')),
            'notes' => trim((string) ($input['notes'] ?? '')),
            'area' => trim((string) ($input['area'] ?? '')),
        ];
    }

    /**
     * @param array<string, string> $form
     * @param array<string, string> $labels
     * @return array<string, string>
     */
    protected function validateForm(array $form, array $labels): array
    {
        $errors = [];

        if ($form['title'] === '') {
            $errors['title'] = 'Title is required.';
        }

        if ($form['notes'] === '') {
            $errors['notes'] = $labels['notes_required'];
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function itemOr404(int $itemId): ?array
    {
        if ($itemId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return null;
        }

        $item = DevTrackerItem::find($itemId);
        if (
            $item === null
            || !DevTrackerItem::belongsToBusiness($item, current_business_id())
            || strtolower(trim((string) ($item['item_type'] ?? ''))) !== $this->submissionType()
        ) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return null;
        }

        return $item;
    }
}
