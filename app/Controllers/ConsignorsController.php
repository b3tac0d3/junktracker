<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Consignor;
use App\Models\ConsignorContact;
use App\Models\ConsignorContract;
use App\Models\ConsignorPayout;
use Core\Controller;
use Throwable;

final class ConsignorsController extends Controller
{
    private const CONTACT_METHODS = ['call', 'text', 'email', 'appointment', 'other'];
    private const CONTACT_DIRECTIONS = ['outbound', 'inbound'];
    private const PAYOUT_METHODS = ['cash', 'check', 'ach', 'zelle', 'venmo', 'paypal', 'other'];
    private const PAYOUT_STATUSES = ['paid', 'scheduled', 'pending', 'void'];
    private const PAYMENT_SCHEDULES = ['monthly', 'quarterly', 'yearly'];

    public function index(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        $status = (string) ($_GET['status'] ?? 'active');
        if (!in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/consignors-table.js') . '"></script>',
        ]);

        $this->render('consignors/index', [
            'pageTitle' => 'Consignors',
            'consignors' => Consignor::search($query, $status),
            'query' => $query,
            'status' => $status,
            'pageScripts' => $pageScripts,
        ]);
    }

    public function show(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/consignors');
        }

        $consignor = Consignor::findById($id);
        if (!$consignor) {
            $this->renderNotFound();
            return;
        }

        $this->render('consignors/show', [
            'pageTitle' => 'Consignor Details',
            'consignor' => $consignor,
            'contacts' => ConsignorContact::forConsignor($id),
            'contracts' => ConsignorContract::forConsignor($id),
            'payouts' => ConsignorPayout::forConsignor($id),
            'contactMethods' => self::CONTACT_METHODS,
            'contactDirections' => self::CONTACT_DIRECTIONS,
            'payoutMethods' => self::PAYOUT_METHODS,
            'payoutStatuses' => self::PAYOUT_STATUSES,
            'paymentSchedules' => self::PAYMENT_SCHEDULES,
        ]);

        clear_old();
    }

    public function create(): void
    {
        $this->render('consignors/create', [
            'pageTitle' => 'Add Consignor',
            'consignor' => null,
            'paymentSchedules' => self::PAYMENT_SCHEDULES,
        ]);

        clear_old();
    }

    public function store(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/consignors/new');
        }

        $data = $this->collectConsignorFormData();
        $errors = $this->validateConsignor($data, null);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/consignors/new');
        }

        $consignorId = Consignor::create($data, auth_user_id());
        log_user_action('consignor_created', 'consignors', $consignorId, 'Added consignor #' . $consignorId . '.');

        flash('success', 'Consignor added.');
        redirect('/consignors/' . $consignorId);
    }

    public function edit(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/consignors');
        }

        $consignor = Consignor::findById($id);
        if (!$consignor) {
            $this->renderNotFound();
            return;
        }

        $this->render('consignors/edit', [
            'pageTitle' => 'Edit Consignor',
            'consignor' => $consignor,
            'paymentSchedules' => self::PAYMENT_SCHEDULES,
        ]);

        clear_old();
    }

    public function update(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/consignors');
        }

        $consignor = Consignor::findById($id);
        if (!$consignor) {
            $this->renderNotFound();
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/consignors/' . $id . '/edit');
        }

        $data = $this->collectConsignorFormData();
        $data['active'] = (int) ($consignor['active'] ?? 1);
        $errors = $this->validateConsignor($data, $id);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/consignors/' . $id . '/edit');
        }

        Consignor::update($id, $data, auth_user_id());
        log_user_action('consignor_updated', 'consignors', $id, 'Updated consignor #' . $id . '.');

        flash('success', 'Consignor updated.');
        redirect('/consignors/' . $id);
    }

    public function deactivate(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/consignors');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/consignors/' . $id);
        }

        $consignor = Consignor::findById($id);
        if (!$consignor) {
            $this->renderNotFound();
            return;
        }

        if (!empty($consignor['deleted_at']) || (int) ($consignor['active'] ?? 1) !== 1) {
            flash('success', 'Consignor is already inactive.');
            redirect('/consignors/' . $id);
        }

        Consignor::softDelete($id, auth_user_id());
        log_user_action('consignor_deactivated', 'consignors', $id, 'Deactivated consignor #' . $id . '.');

        flash('success', 'Consignor deactivated.');
        redirect('/consignors');
    }

    public function addContact(array $params): void
    {
        $consignorId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($consignorId <= 0) {
            redirect('/consignors');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/consignors/' . $consignorId);
        }

        $consignor = Consignor::findById($consignorId);
        if (!$consignor) {
            $this->renderNotFound();
            return;
        }

        $method = strtolower(trim((string) ($_POST['contact_method'] ?? 'call')));
        if (!in_array($method, self::CONTACT_METHODS, true)) {
            $method = 'other';
        }

        $direction = strtolower(trim((string) ($_POST['direction'] ?? 'outbound')));
        if (!in_array($direction, self::CONTACT_DIRECTIONS, true)) {
            $direction = 'outbound';
        }

        $contactedAt = $this->toDateTimeOrNull($_POST['contacted_at'] ?? null) ?? date('Y-m-d H:i:s');
        $followUpAt = $this->toDateTimeOrNull($_POST['follow_up_at'] ?? null);

        ConsignorContact::create($consignorId, [
            'link_type' => 'general',
            'link_id' => null,
            'contact_method' => $method,
            'direction' => $direction,
            'subject' => trim((string) ($_POST['subject'] ?? '')),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
            'contacted_at' => $contactedAt,
            'follow_up_at' => $followUpAt,
        ], auth_user_id());

        log_user_action('consignor_contact_created', 'consignor_contacts', null, 'Logged consignor contact for consignor #' . $consignorId . '.');
        flash('success', 'Contact logged.');
        redirect('/consignors/' . $consignorId);
    }

    public function addPayout(array $params): void
    {
        $consignorId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($consignorId <= 0) {
            redirect('/consignors');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/consignors/' . $consignorId);
        }

        $consignor = Consignor::findById($consignorId);
        if (!$consignor) {
            $this->renderNotFound();
            return;
        }

        $payoutDate = $this->toDateOrNull($_POST['payout_date'] ?? null);
        $amount = $this->toDecimalOrNull($_POST['amount'] ?? null);
        $estimateAmount = $this->toDecimalOrNull($_POST['estimate_amount'] ?? null);
        $method = strtolower(trim((string) ($_POST['payout_method'] ?? 'other')));
        $status = strtolower(trim((string) ($_POST['status'] ?? 'paid')));

        $errors = [];
        if ($payoutDate === null) {
            $errors[] = 'Payout date is required.';
        }
        if ($amount === null || $amount <= 0) {
            $errors[] = 'Payout amount must be greater than zero.';
        }
        if ($estimateAmount !== null && $estimateAmount < 0) {
            $errors[] = 'Estimate amount must be zero or greater.';
        }
        if (!in_array($method, self::PAYOUT_METHODS, true)) {
            $errors[] = 'Select a valid payout method.';
        }
        if (!in_array($status, self::PAYOUT_STATUSES, true)) {
            $errors[] = 'Select a valid payout status.';
        }

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/consignors/' . $consignorId);
        }

        ConsignorPayout::create($consignorId, [
            'payout_date' => $payoutDate,
            'amount' => $amount,
            'estimate_amount' => $estimateAmount,
            'payout_method' => $method,
            'reference_no' => trim((string) ($_POST['reference_no'] ?? '')),
            'status' => $status,
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ], auth_user_id());

        log_user_action('consignor_payout_created', 'consignor_payouts', null, 'Logged payout for consignor #' . $consignorId . '.');
        flash('success', 'Payout logged.');
        redirect('/consignors/' . $consignorId);
    }

    public function addContract(array $params): void
    {
        $consignorId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($consignorId <= 0) {
            redirect('/consignors');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/consignors/' . $consignorId);
        }

        $consignor = Consignor::findById($consignorId);
        if (!$consignor) {
            $this->renderNotFound();
            return;
        }

        $title = trim((string) ($_POST['contract_title'] ?? ''));
        if ($title === '') {
            flash('error', 'Contract title is required.');
            redirect('/consignors/' . $consignorId);
        }

        if (!isset($_FILES['contract_file']) || !is_array($_FILES['contract_file'])) {
            flash('error', 'Select a file to upload.');
            redirect('/consignors/' . $consignorId);
        }

        $file = $_FILES['contract_file'];
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            flash('error', 'Upload failed. Please try again.');
            redirect('/consignors/' . $consignorId);
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $originalName = (string) ($file['name'] ?? 'contract');
        $fileSize = isset($file['size']) ? (int) $file['size'] : 0;

        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            flash('error', 'Uploaded file is invalid.');
            redirect('/consignors/' . $consignorId);
        }

        $maxSize = 15 * 1024 * 1024;
        if ($fileSize <= 0 || $fileSize > $maxSize) {
            flash('error', 'File must be between 1 byte and 15 MB.');
            redirect('/consignors/' . $consignorId);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) ($finfo->file($tmpPath) ?: '');

        $allowed = [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];

        if (!isset($allowed[$mimeType])) {
            flash('error', 'Only PDF, DOC, DOCX, JPG, and PNG files are allowed.');
            redirect('/consignors/' . $consignorId);
        }

        $extension = $allowed[$mimeType];
        $storedFileName = bin2hex(random_bytes(20)) . '.' . $extension;

        $storageDir = $this->consignorStorageDirectory();
        if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
            flash('error', 'Unable to initialize secure storage directory.');
            redirect('/consignors/' . $consignorId);
        }

        $destination = $storageDir . DIRECTORY_SEPARATOR . $storedFileName;

        try {
            if (!move_uploaded_file($tmpPath, $destination)) {
                flash('error', 'Could not save uploaded file.');
                redirect('/consignors/' . $consignorId);
            }
        } catch (Throwable) {
            flash('error', 'Could not save uploaded file.');
            redirect('/consignors/' . $consignorId);
        }

        $contractId = ConsignorContract::create($consignorId, [
            'contract_title' => $title,
            'original_file_name' => $this->sanitizeFileName($originalName),
            'stored_file_name' => $storedFileName,
            'storage_path' => 'storage/consignor_contracts/' . $storedFileName,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'contract_signed_at' => $this->toDateOrNull($_POST['contract_signed_at'] ?? null),
            'expires_at' => $this->toDateOrNull($_POST['expires_at'] ?? null),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ], auth_user_id());

        log_user_action('consignor_contract_uploaded', 'consignor_contracts', $contractId, 'Uploaded contract for consignor #' . $consignorId . '.');
        flash('success', 'Contract uploaded.');
        redirect('/consignors/' . $consignorId);
    }

    public function deleteContract(array $params): void
    {
        $consignorId = isset($params['id']) ? (int) $params['id'] : 0;
        $contractId = isset($params['contractId']) ? (int) $params['contractId'] : 0;
        if ($consignorId <= 0 || $contractId <= 0) {
            redirect('/consignors');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/consignors/' . $consignorId);
        }

        $contract = ConsignorContract::findById($contractId);
        if (!$contract || (int) ($contract['consignor_id'] ?? 0) !== $consignorId) {
            $this->renderNotFound();
            return;
        }

        ConsignorContract::softDelete($contractId, auth_user_id());
        log_user_action('consignor_contract_deleted', 'consignor_contracts', $contractId, 'Deleted contract #' . $contractId . '.');

        flash('success', 'Contract deleted.');
        redirect('/consignors/' . $consignorId);
    }

    public function downloadContract(array $params): void
    {
        $consignorId = isset($params['id']) ? (int) $params['id'] : 0;
        $contractId = isset($params['contractId']) ? (int) $params['contractId'] : 0;
        if ($consignorId <= 0 || $contractId <= 0) {
            redirect('/consignors');
        }

        $contract = ConsignorContract::findById($contractId);
        if (
            !$contract
            || (int) ($contract['consignor_id'] ?? 0) !== $consignorId
            || !empty($contract['deleted_at'])
            || (int) ($contract['active'] ?? 1) !== 1
        ) {
            $this->renderNotFound();
            return;
        }

        $relativePath = trim((string) ($contract['storage_path'] ?? ''));
        if ($relativePath === '') {
            flash('error', 'Stored file path is missing.');
            redirect('/consignors/' . $consignorId);
        }

        $fullPath = BASE_PATH . '/' . ltrim($relativePath, '/');
        if (!is_file($fullPath) || !is_readable($fullPath)) {
            flash('error', 'Stored file could not be found.');
            redirect('/consignors/' . $consignorId);
        }

        log_user_action('consignor_contract_downloaded', 'consignor_contracts', $contractId, 'Downloaded contract #' . $contractId . '.');

        $downloadName = trim((string) ($contract['original_file_name'] ?? 'contract'));
        if ($downloadName === '') {
            $downloadName = 'contract';
        }

        $mime = trim((string) ($contract['mime_type'] ?? ''));
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
        header('Content-Length: ' . (string) filesize($fullPath));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($fullPath);
        exit;
    }

    private function collectConsignorFormData(): array
    {
        return [
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'business_name' => trim((string) ($_POST['business_name'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'address_1' => trim((string) ($_POST['address_1'] ?? '')),
            'address_2' => trim((string) ($_POST['address_2'] ?? '')),
            'city' => trim((string) ($_POST['city'] ?? '')),
            'state' => strtoupper(trim((string) ($_POST['state'] ?? ''))),
            'zip' => trim((string) ($_POST['zip'] ?? '')),
            'consignor_number' => trim((string) ($_POST['consignor_number'] ?? '')),
            'consignment_start_date' => $this->toDateOrNull($_POST['consignment_start_date'] ?? null),
            'consignment_end_date' => $this->toDateOrNull($_POST['consignment_end_date'] ?? null),
            'payment_schedule' => strtolower(trim((string) ($_POST['payment_schedule'] ?? 'monthly'))),
            'next_payment_due_date' => $this->toDateOrNull($_POST['next_payment_due_date'] ?? null),
            'inventory_estimate_amount' => $this->toDecimalOrNull($_POST['inventory_estimate_amount'] ?? null),
            'inventory_description' => trim((string) ($_POST['inventory_description'] ?? '')),
            'note' => trim((string) ($_POST['note'] ?? '')),
            'active' => 1,
        ];
    }

    private function validateConsignor(array $data, ?int $currentId = null): array
    {
        $errors = [];

        if ($data['first_name'] === '' && $data['last_name'] === '' && $data['business_name'] === '') {
            $errors[] = 'Provide at least a first name, last name, or business name.';
        }

        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email is invalid.';
        }

        if ($data['state'] !== '' && strlen($data['state']) !== 2) {
            $errors[] = 'State must be a 2-letter value.';
        }

        if ($data['payment_schedule'] !== '' && !in_array($data['payment_schedule'], self::PAYMENT_SCHEDULES, true)) {
            $errors[] = 'Payment schedule must be monthly, quarterly, or yearly.';
        }

        if (
            $data['consignment_start_date'] !== null
            && $data['consignment_end_date'] !== null
            && $data['consignment_end_date'] < $data['consignment_start_date']
        ) {
            $errors[] = 'Potential end date must be on or after consignment start date.';
        }

        if (
            $data['consignment_start_date'] !== null
            && $data['next_payment_due_date'] !== null
            && $data['next_payment_due_date'] < $data['consignment_start_date']
        ) {
            $errors[] = 'Next payment due date must be on or after consignment start date.';
        }

        if ($data['inventory_estimate_amount'] !== null && $data['inventory_estimate_amount'] < 0) {
            $errors[] = 'Inventory estimate must be zero or greater.';
        }

        if ($data['consignor_number'] !== '') {
            $existing = Consignor::findByConsignorNumber($data['consignor_number'], $currentId);
            if ($existing) {
                $errors[] = 'Consignor number already exists.';
            }
        }

        return $errors;
    }

    private function toDateOrNull(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $time = strtotime($raw);
        return $time === false ? null : date('Y-m-d', $time);
    }

    private function toDateTimeOrNull(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $time = strtotime($raw);
        return $time === false ? null : date('Y-m-d H:i:s', $time);
    }

    private function toDecimalOrNull(mixed $value): ?float
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        return is_numeric($raw) ? (float) $raw : null;
    }

    private function consignorStorageDirectory(): string
    {
        return BASE_PATH . '/storage/consignor_contracts';
    }

    private function sanitizeFileName(string $value): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($value));
        $normalized = trim((string) $normalized, '._-');
        if ($normalized === '') {
            $normalized = 'contract';
        }

        return substr($normalized, 0, 180);
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
