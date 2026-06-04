<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\FormSelectValue;
use App\Models\PurchaseQuote;
use App\Models\PurchaseQuoteContact;
use App\Models\PurchaseQuoteOffer;
use Core\Controller;

final class PurchaseQuotesController extends Controller
{
    public function index(): void
    {
        require_financial_access();

        if (!PurchaseQuote::isAvailable()) {
            flash('error', 'Purchase quotes are not available yet. Run the database migration.');
            redirect('/');
        }

        $search = trim((string) ($_GET['q'] ?? ''));
        $status = strtolower(trim((string) ($_GET['status'] ?? 'dispatch')));
        $businessId = current_business_id();
        $statusOptions = PurchaseQuote::statusOptions();
        $allowed = array_merge(['dispatch', ''], $statusOptions);
        if (!in_array($status, $allowed, true)) {
            $status = 'dispatch';
        }

        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = PurchaseQuote::indexCount($businessId, $search, $status);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);
        $purchaseQuotes = PurchaseQuote::indexList($businessId, $search, $status, $perPage, $offset);
        $pagination = pagination_meta($page, $perPage, $totalRows, count($purchaseQuotes));

        $this->render('purchase-quotes/index', [
            'pageTitle' => 'Purchase Quotes',
            'search' => $search,
            'status' => $status,
            'statusOptions' => $statusOptions,
            'purchaseQuotes' => $purchaseQuotes,
            'pagination' => $pagination,
        ]);
    }

    public function create(): void
    {
        require_financial_access();
        if (!PurchaseQuote::isAvailable()) {
            flash('error', 'Purchase quotes are not available yet. Run the database migration.');
            redirect('/purchases');
        }

        $form = $this->defaultForm();
        $prefillAt = calendar_slot_prefill_at();
        if ($prefillAt !== '') {
            $form['next_follow_up_at'] = $prefillAt;
        }
        $businessId = current_business_id();
        $requestedClientId = (int) ($_GET['client_id'] ?? 0);
        if ($requestedClientId > 0) {
            $client = Client::findForBusiness($businessId, $requestedClientId);
            if ($client !== null) {
                $form['client_id'] = (string) $requestedClientId;
                $form['client_name'] = Client::displayName($client);
            }
        }

        $this->render('purchase-quotes/form', [
            'pageTitle' => 'Add Purchase Quote',
            'mode' => 'create',
            'actionUrl' => url('/purchase-quotes'),
            'form' => $form,
            'errors' => [],
            'statusOptions' => PurchaseQuote::statusOptions(),
            'clientTypeOptions' => FormSelectValue::optionsForSection($businessId, 'client_type'),
        ]);
    }

    public function store(): void
    {
        require_financial_access();
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/purchase-quotes/create');
        }
        if (!PurchaseQuote::isAvailable()) {
            flash('error', 'Purchase quotes are not available yet.');
            redirect('/purchases');
        }

        $businessId = current_business_id();
        $form = $this->formFromPost($_POST);
        $errors = PurchaseQuote::validate($form, $businessId);
        if ($errors !== []) {
            $this->render('purchase-quotes/form', [
                'pageTitle' => 'Add Purchase Quote',
                'mode' => 'create',
                'actionUrl' => url('/purchase-quotes'),
                'form' => $form,
                'errors' => $errors,
                'statusOptions' => PurchaseQuote::statusOptions(),
                'clientTypeOptions' => FormSelectValue::optionsForSection($businessId, 'client_type'),
            ]);
            return;
        }

        $actorUserId = (int) (auth_user_id() ?? 0);
        $purchaseQuoteId = PurchaseQuote::create($businessId, $form, $actorUserId);
        if ($purchaseQuoteId <= 0) {
            flash('error', 'Unable to create purchase quote.');
            redirect('/purchase-quotes/create');
        }
        AuditLog::write('purchase_quote_created', 'purchase_quotes', $purchaseQuoteId, $businessId, $actorUserId, []);
        flash('success', 'Purchase quote created.');
        redirect_to_detail('/purchase-quotes/' . (string) $purchaseQuoteId, request_detail_tab(['details', 'offers', 'contacts']));
    }

    public function show(array $params): void
    {
        require_financial_access();
        if (!PurchaseQuote::isAvailable()) {
            flash('error', 'Purchase quotes are not available yet.');
            redirect('/purchases');
        }

        $purchaseQuoteId = (int) ($params['id'] ?? 0);
        $businessId = current_business_id();
        $purchaseQuote = PurchaseQuote::findForBusiness($businessId, $purchaseQuoteId);
        if ($purchaseQuote === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $statusOptions = PurchaseQuote::statusOptions();
        $currentStatus = strtolower(trim((string) ($purchaseQuote['status'] ?? 'new')));
        if ($currentStatus !== '' && !in_array($currentStatus, $statusOptions, true)) {
            $statusOptions = array_values(array_unique(array_merge([$currentStatus], $statusOptions)));
        }

        $activeTab = strtolower(trim((string) ($_GET['tab'] ?? 'details')));
        $allowedTabs = ['details', 'offers', 'contacts'];
        if (!in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'details';
        }

        $this->render('purchase-quotes/show', [
            'pageTitle' => 'Purchase Quote',
            'purchaseQuote' => $purchaseQuote,
            'statusOptions' => $statusOptions,
            'offers' => PurchaseQuoteOffer::forPurchaseQuote($businessId, $purchaseQuoteId),
            'contacts' => PurchaseQuoteContact::forPurchaseQuote($businessId, $purchaseQuoteId),
            'offerTypeOptions' => PurchaseQuoteOffer::typeOptions(),
            'contactTypeOptions' => PurchaseQuoteContact::typeOptions(),
            'activeTab' => $activeTab,
            'detailsTabActive' => $activeTab === 'details',
            'offersTabActive' => $activeTab === 'offers',
            'contactsTabActive' => $activeTab === 'contacts',
        ]);
    }

    public function edit(array $params): void
    {
        require_financial_access();
        if (!PurchaseQuote::isAvailable()) {
            flash('error', 'Purchase quotes are not available yet.');
            redirect('/purchases');
        }

        $purchaseQuoteId = (int) ($params['id'] ?? 0);
        $businessId = current_business_id();
        $purchaseQuote = PurchaseQuote::findForBusiness($businessId, $purchaseQuoteId);
        if ($purchaseQuote === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->formFromModel($purchaseQuote);
        $clientIdForName = (int) ($form['client_id'] ?? 0);
        if ($clientIdForName > 0) {
            $clientRow = Client::findForBusiness($businessId, $clientIdForName);
            if ($clientRow !== null) {
                $form['client_name'] = Client::displayName($clientRow);
            }
        }

        $this->render('purchase-quotes/form', [
            'pageTitle' => 'Edit Purchase Quote',
            'mode' => 'edit',
            'actionUrl' => url('/purchase-quotes/' . (string) $purchaseQuoteId . '/update'),
            'form' => $form,
            'errors' => [],
            'statusOptions' => PurchaseQuote::statusOptions(),
            'clientTypeOptions' => FormSelectValue::optionsForSection($businessId, 'client_type'),
            'purchaseQuoteId' => $purchaseQuoteId,
        ]);
    }

    public function update(array $params): void
    {
        require_financial_access();
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/purchase-quotes');
        }
        if (!PurchaseQuote::isAvailable()) {
            flash('error', 'Purchase quotes are not available yet.');
            redirect('/purchases');
        }

        $purchaseQuoteId = (int) ($params['id'] ?? 0);
        $businessId = current_business_id();
        $purchaseQuote = PurchaseQuote::findForBusiness($businessId, $purchaseQuoteId);
        if ($purchaseQuote === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->formFromPost($_POST);
        if (PurchaseQuote::hasConversion($purchaseQuote)) {
            $form['converted_purchase_id'] = (string) ((int) ($purchaseQuote['converted_purchase_id'] ?? 0));
        }
        $errors = PurchaseQuote::validate($form, $businessId);
        if ($errors !== []) {
            $this->render('purchase-quotes/form', [
                'pageTitle' => 'Edit Purchase Quote',
                'mode' => 'edit',
                'actionUrl' => url('/purchase-quotes/' . (string) $purchaseQuoteId . '/update'),
                'form' => $form,
                'errors' => $errors,
                'statusOptions' => PurchaseQuote::statusOptions(),
                'clientTypeOptions' => FormSelectValue::optionsForSection($businessId, 'client_type'),
                'purchaseQuoteId' => $purchaseQuoteId,
            ]);
            return;
        }

        $actorUserId = (int) (auth_user_id() ?? 0);
        PurchaseQuote::update($businessId, $purchaseQuoteId, $form, $actorUserId);
        AuditLog::write('purchase_quote_updated', 'purchase_quotes', $purchaseQuoteId, $businessId, $actorUserId, []);
        flash('success', 'Purchase quote updated.');
        redirect_to_detail('/purchase-quotes/' . (string) $purchaseQuoteId, request_detail_tab(['details', 'offers', 'contacts']));
    }

    public function quickStatus(array $params): void
    {
        require_financial_access();
        $purchaseQuoteId = (int) ($params['id'] ?? 0);
        if ($purchaseQuoteId <= 0) {
            flash('error', 'Purchase quote not found.');
            redirect('/purchase-quotes');
        }
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect_to_detail('/purchase-quotes/' . (string) $purchaseQuoteId, request_detail_tab(['details', 'offers', 'contacts']));
        }

        $businessId = current_business_id();
        $purchaseQuote = PurchaseQuote::findForBusiness($businessId, $purchaseQuoteId);
        if ($purchaseQuote === null) {
            flash('error', 'Purchase quote not found.');
            redirect('/purchase-quotes');
        }

        $status = strtolower(trim((string) ($_POST['status'] ?? '')));
        if (!in_array($status, PurchaseQuote::statusOptions(), true)) {
            flash('error', 'Choose a valid status.');
            redirect_to_detail('/purchase-quotes/' . (string) $purchaseQuoteId, request_detail_tab(['details', 'offers', 'contacts']));
        }

        $current = strtolower(trim((string) ($purchaseQuote['status'] ?? 'new')));
        if ($status !== $current) {
            $actor = (int) (auth_user_id() ?? 0);
            if (PurchaseQuote::updateStatus($businessId, $purchaseQuoteId, $status, $actor)) {
                AuditLog::write('purchase_quote_status_updated', 'purchase_quotes', $purchaseQuoteId, $businessId, $actor, [
                    'from_status' => $current,
                    'to_status' => $status,
                ]);
                flash('success', 'Status updated.');
            }
        }

        redirect_to_detail('/purchase-quotes/' . (string) $purchaseQuoteId, request_detail_tab(['details', 'offers', 'contacts']));
    }

    public function convertToPurchase(array $params): void
    {
        require_financial_access();
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/purchase-quotes');
        }

        $purchaseQuoteId = (int) ($params['id'] ?? 0);
        $businessId = current_business_id();
        $actorUserId = (int) (auth_user_id() ?? 0);
        $purchaseId = PurchaseQuote::convertToPurchase($businessId, $purchaseQuoteId, $actorUserId);
        if ($purchaseId <= 0) {
            flash('error', 'Unable to convert to purchase.');
            redirect_to_detail('/purchase-quotes/' . (string) $purchaseQuoteId, request_detail_tab(['details', 'offers', 'contacts']));
        }
        AuditLog::write('purchase_quote_converted', 'purchase_quotes', $purchaseQuoteId, $businessId, $actorUserId, [
            'purchase_id' => $purchaseId,
        ]);
        flash('success', 'Converted to purchase.');
        redirect('/purchases/' . (string) $purchaseId);
    }

    public function markLost(array $params): void
    {
        require_financial_access();
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/purchase-quotes');
        }

        $purchaseQuoteId = (int) ($params['id'] ?? 0);
        $businessId = current_business_id();
        $actorUserId = (int) (auth_user_id() ?? 0);
        $lostReason = trim((string) ($_POST['lost_reason'] ?? ''));

        if (!PurchaseQuote::markLost($businessId, $purchaseQuoteId, $lostReason, $actorUserId)) {
            flash('error', 'Unable to mark as lost.');
            redirect_to_detail('/purchase-quotes/' . (string) $purchaseQuoteId, request_detail_tab(['details', 'offers', 'contacts']));
        }
        AuditLog::write('purchase_quote_marked_lost', 'purchase_quotes', $purchaseQuoteId, $businessId, $actorUserId, [
            'lost_reason' => $lostReason,
        ]);
        flash('success', 'Marked as lost.');
        redirect_to_detail('/purchase-quotes/' . (string) $purchaseQuoteId, request_detail_tab(['details', 'offers', 'contacts']));
    }

    public function storeOffer(array $params): void
    {
        require_financial_access();
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/purchase-quotes');
        }

        $purchaseQuoteId = (int) ($params['id'] ?? 0);
        $businessId = current_business_id();
        $purchaseQuote = PurchaseQuote::findForBusiness($businessId, $purchaseQuoteId);
        if ($purchaseQuote === null) {
            flash('error', 'Purchase quote not found.');
            redirect('/purchase-quotes');
        }

        $offerData = [
            'offer_type' => trim((string) ($_POST['offer_type'] ?? 'our_offer')),
            'amount' => trim((string) ($_POST['amount'] ?? '')),
            'note' => trim((string) ($_POST['note'] ?? '')),
            'offered_at' => trim((string) ($_POST['offered_at'] ?? '')),
        ];
        $errors = PurchaseQuoteOffer::validate($offerData);
        if ($errors !== []) {
            flash('error', implode(' ', $errors));
            redirect('/purchase-quotes/' . (string) $purchaseQuoteId . '?tab=offers');
        }

        $actorUserId = (int) (auth_user_id() ?? 0);
        $offerId = PurchaseQuoteOffer::create($businessId, $purchaseQuoteId, $offerData, $actorUserId);
        if ($offerId <= 0) {
            flash('error', 'Unable to add offer.');
        } else {
            AuditLog::write('purchase_quote_offer_added', 'purchase_quotes', $purchaseQuoteId, $businessId, $actorUserId, [
                'offer_id' => $offerId,
            ]);
            flash('success', 'Offer recorded.');
        }
        redirect('/purchase-quotes/' . (string) $purchaseQuoteId . '?tab=offers');
    }

    public function storeContact(array $params): void
    {
        require_financial_access();
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/purchase-quotes');
        }

        $purchaseQuoteId = (int) ($params['id'] ?? 0);
        $businessId = current_business_id();
        $purchaseQuote = PurchaseQuote::findForBusiness($businessId, $purchaseQuoteId);
        if ($purchaseQuote === null) {
            flash('error', 'Purchase quote not found.');
            redirect('/purchase-quotes');
        }

        $contactData = [
            'contact_type' => trim((string) ($_POST['contact_type'] ?? 'phone')),
            'contacted_at' => trim((string) ($_POST['contacted_at'] ?? '')),
            'note' => trim((string) ($_POST['note'] ?? '')),
        ];
        $errors = PurchaseQuoteContact::validate($contactData);
        if ($errors !== []) {
            flash('error', implode(' ', $errors));
            redirect('/purchase-quotes/' . (string) $purchaseQuoteId . '?tab=contacts');
        }

        $clientId = (int) ($purchaseQuote['client_id'] ?? 0);
        $actorUserId = (int) (auth_user_id() ?? 0);
        $contactId = PurchaseQuoteContact::create($businessId, $purchaseQuoteId, $clientId, $contactData, $actorUserId);
        if ($contactId <= 0) {
            flash('error', 'Unable to log contact.');
        } else {
            AuditLog::write('purchase_quote_contact_logged', 'purchase_quotes', $purchaseQuoteId, $businessId, $actorUserId, [
                'contact_id' => $contactId,
            ]);
            flash('success', 'Contact logged.');
        }
        redirect('/purchase-quotes/' . (string) $purchaseQuoteId . '?tab=contacts');
    }

    private function defaultForm(): array
    {
        return [
            'client_id' => '',
            'client_name' => '',
            'title' => '',
            'status' => 'new',
            'contact_date' => date('Y-m-d'),
            'next_follow_up_at' => '',
            'notes' => '',
            'lost_reason' => '',
            'converted_purchase_id' => '',
        ];
    }

    private function formFromPost(array $input): array
    {
        return [
            'client_id' => trim((string) ($input['client_id'] ?? '')),
            'client_name' => trim((string) ($input['client_name'] ?? '')),
            'title' => trim((string) ($input['title'] ?? '')),
            'status' => strtolower(trim((string) ($input['status'] ?? 'new'))),
            'contact_date' => trim((string) ($input['contact_date'] ?? '')),
            'next_follow_up_at' => trim((string) ($input['next_follow_up_at'] ?? '')),
            'notes' => trim((string) ($input['notes'] ?? '')),
            'lost_reason' => trim((string) ($input['lost_reason'] ?? '')),
            'converted_purchase_id' => trim((string) ($input['converted_purchase_id'] ?? '')),
        ];
    }

    private function formFromModel(array $purchaseQuote): array
    {
        return [
            'client_id' => (string) ((int) ($purchaseQuote['client_id'] ?? 0)),
            'title' => trim((string) ($purchaseQuote['title'] ?? '')),
            'status' => strtolower(trim((string) ($purchaseQuote['status'] ?? 'new'))),
            'contact_date' => $this->toInputDate((string) ($purchaseQuote['contact_date'] ?? '')),
            'next_follow_up_at' => $this->toInputDateTimeLocal((string) ($purchaseQuote['next_follow_up_at'] ?? '')),
            'notes' => trim((string) ($purchaseQuote['notes'] ?? '')),
            'lost_reason' => trim((string) ($purchaseQuote['lost_reason'] ?? '')),
            'converted_purchase_id' => (string) ((int) ($purchaseQuote['converted_purchase_id'] ?? 0)),
        ];
    }

    private function toInputDateTimeLocal(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $ts = strtotime($value);
        return $ts === false ? '' : date('Y-m-d\TH:i', $ts);
    }

    private function toInputDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $ts = strtotime($value);
        return $ts === false ? '' : date('Y-m-d', $ts);
    }
}
