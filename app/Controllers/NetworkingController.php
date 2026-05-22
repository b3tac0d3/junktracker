<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\NetworkingContact;
use Core\Controller;

final class NetworkingController extends Controller
{
    public function index(): void
    {
        require_business_role(['general_user', 'admin']);

        $search = trim((string) ($_GET['q'] ?? ''));
        $type = strtolower(trim((string) ($_GET['type'] ?? '')));
        $businessId = current_business_id();
        $typeOptions = NetworkingContact::typeOptions($businessId);
        if ($type !== '' && !in_array($type, $typeOptions, true)) {
            $type = '';
        }

        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = NetworkingContact::indexCount($businessId, $search, $type);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);
        $contacts = NetworkingContact::indexList($businessId, $search, $type, $perPage, $offset);
        $pagination = pagination_meta($page, $perPage, $totalRows, count($contacts));

        $this->render('networking/index', [
            'pageTitle' => 'Networking',
            'search' => $search,
            'type' => $type,
            'typeOptions' => $typeOptions,
            'contacts' => $contacts,
            'pagination' => $pagination,
        ]);
    }

    public function create(): void
    {
        require_business_role(['general_user', 'admin']);

        $businessId = current_business_id();
        $this->render('networking/form', [
            'pageTitle' => 'Add Networking Contact',
            'mode' => 'create',
            'actionUrl' => url('/networking'),
            'form' => $this->defaultForm(),
            'errors' => [],
            'typeOptions' => NetworkingContact::typeOptions($businessId),
        ]);
    }

    public function store(): void
    {
        require_business_role(['general_user', 'admin']);
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/networking/create');
        }

        $businessId = current_business_id();
        $form = $this->formFromPost($_POST);
        $errors = NetworkingContact::validate($form, $businessId);
        if ($errors !== []) {
            $this->render('networking/form', [
                'pageTitle' => 'Add Networking Contact',
                'mode' => 'create',
                'actionUrl' => url('/networking'),
                'form' => $form,
                'errors' => $errors,
                'typeOptions' => NetworkingContact::typeOptions($businessId),
            ]);
            return;
        }

        $id = NetworkingContact::create($businessId, $form, (int) (auth_user_id() ?? 0));
        if ($id <= 0) {
            flash('error', 'Unable to create networking contact. Run latest migrations if needed.');
            redirect('/networking/create');
        }
        audit('networking_contact_created', 'networking_contacts', $id);
        flash('success', 'Networking contact created.');
        redirect('/networking/' . (string) $id);
    }

    public function show(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $contactId = (int) ($params['id'] ?? 0);
        $contact = NetworkingContact::findForBusiness(current_business_id(), $contactId);
        if ($contact === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('networking/show', [
            'pageTitle' => 'Networking Contact',
            'contact' => $contact,
        ]);
    }

    public function edit(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $businessId = current_business_id();
        $contactId = (int) ($params['id'] ?? 0);
        $contact = NetworkingContact::findForBusiness($businessId, $contactId);
        if ($contact === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('networking/form', [
            'pageTitle' => 'Edit Networking Contact',
            'mode' => 'edit',
            'actionUrl' => url('/networking/' . (string) $contactId . '/update'),
            'form' => $this->formFromModel($contact),
            'errors' => [],
            'typeOptions' => NetworkingContact::typeOptions($businessId),
        ]);
    }

    public function update(array $params): void
    {
        require_business_role(['general_user', 'admin']);
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/networking');
        }

        $businessId = current_business_id();
        $contactId = (int) ($params['id'] ?? 0);
        $existing = NetworkingContact::findForBusiness($businessId, $contactId);
        if ($existing === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->formFromPost($_POST);
        $errors = NetworkingContact::validate($form, $businessId);
        if ($errors !== []) {
            $this->render('networking/form', [
                'pageTitle' => 'Edit Networking Contact',
                'mode' => 'edit',
                'actionUrl' => url('/networking/' . (string) $contactId . '/update'),
                'form' => $form,
                'errors' => $errors,
                'typeOptions' => NetworkingContact::typeOptions($businessId),
            ]);
            return;
        }

        NetworkingContact::update($businessId, $contactId, $form, (int) (auth_user_id() ?? 0));
        audit('networking_contact_updated', 'networking_contacts', $contactId);
        flash('success', 'Networking contact updated.');
        redirect('/networking/' . (string) $contactId);
    }

    public function delete(array $params): void
    {
        require_business_role(['general_user', 'admin']);
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/networking');
        }

        $businessId = current_business_id();
        $contactId = (int) ($params['id'] ?? 0);
        $contact = NetworkingContact::findForBusiness($businessId, $contactId);
        if ($contact === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $deleted = NetworkingContact::softDelete($businessId, $contactId, (int) (auth_user_id() ?? 0));
        if ($deleted) {
            audit('networking_contact_deleted', 'networking_contacts', $contactId);
            flash('success', 'Networking contact deleted.');
            redirect('/networking');
        }

        flash('error', 'Unable to delete networking contact.');
        redirect('/networking/' . (string) $contactId);
    }

    private function defaultForm(): array
    {
        return [
            'first_name' => '',
            'last_name' => '',
            'company' => '',
            'contact_type' => '',
            'phone' => '',
            'email' => '',
            'notes' => '',
        ];
    }

    private function formFromPost(array $input): array
    {
        return [
            'first_name' => trim((string) ($input['first_name'] ?? '')),
            'last_name' => trim((string) ($input['last_name'] ?? '')),
            'company' => trim((string) ($input['company'] ?? '')),
            'contact_type' => strtolower(trim((string) ($input['contact_type'] ?? ''))),
            'phone' => trim((string) ($input['phone'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'notes' => trim((string) ($input['notes'] ?? '')),
        ];
    }

    private function formFromModel(array $contact): array
    {
        $firstName = trim((string) ($contact['first_name'] ?? ''));
        $lastName = trim((string) ($contact['last_name'] ?? ''));
        if ($firstName === '' && $lastName === '') {
            $legacyName = trim((string) ($contact['name'] ?? ''));
            if ($legacyName !== '') {
                $parts = preg_split('/\s+/', $legacyName) ?: [];
                $firstName = trim((string) ($parts[0] ?? ''));
                $lastName = trim(implode(' ', array_slice($parts, 1)));
            }
        }

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company' => trim((string) ($contact['company'] ?? '')),
            'contact_type' => strtolower(trim((string) ($contact['contact_type'] ?? ''))),
            'phone' => trim((string) ($contact['phone'] ?? '')),
            'email' => trim((string) ($contact['email'] ?? '')),
            'notes' => trim((string) ($contact['notes'] ?? '')),
        ];
    }
}
