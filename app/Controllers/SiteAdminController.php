<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Business;
use App\Models\SiteAdminTicket;
use App\Models\UserAction;
use Core\Controller;
use Core\Database;
use Throwable;

final class SiteAdminController extends Controller
{
    public function index(): void
    {
        $this->authorize();

        $query = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? 'active'));
        if (!in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }
        $recentLimit = (int) ($_GET['recent_limit'] ?? 10);
        $recentLimitOptions = [10, 25, 50, 100];
        if (!in_array($recentLimit, $recentLimitOptions, true)) {
            $recentLimit = 10;
        }

        $businesses = Business::search($query, $status);
        $activeWorkspaceId = (int) ($_SESSION['active_business_id'] ?? 0);
        $currentBusiness = $activeWorkspaceId > 0 ? Business::findById($activeWorkspaceId) : null;
        $summary = $this->globalSummary($businesses);
        $recentChanges = $this->recentChanges($recentLimit);
        $supportSummary = SiteAdminTicket::summary();

        $this->render('site_admin/index', [
            'pageTitle' => 'Site Admin',
            'businesses' => $businesses,
            'query' => $query,
            'status' => $status,
            'activeWorkspaceId' => $activeWorkspaceId,
            'currentBusiness' => $currentBusiness,
            'summary' => $summary,
            'recentChanges' => $recentChanges,
            'recentLimit' => $recentLimit,
            'recentLimitOptions' => $recentLimitOptions,
            'supportSummary' => $supportSummary,
            'businessTableReady' => Business::isAvailable(),
        ]);

        clear_old();
    }

    public function createBusiness(): void
    {
        $this->authorize();

        $this->render('site_admin/create', [
            'pageTitle' => 'Add Business',
            'formValues' => [],
        ]);

        clear_old();
    }

    public function storeBusiness(): void
    {
        $this->authorize();

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/site-admin/businesses/new');
        }

        if (!Business::isAvailable()) {
            flash('error', 'Businesses table is not available yet. Run the latest migration bundle first.');
            redirect('/site-admin/businesses/new');
        }

        $data = $this->collectBusinessInput($_POST);
        $errors = $this->validateBusinessInput($data);
        $errors = array_merge($errors, $this->applyLogoUpload($data, null));
        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($data);
            redirect('/site-admin/businesses/new');
        }

        $businessId = Business::create($data, auth_user_id());
        if ($businessId <= 0) {
            flash('error', 'Unable to create business right now.');
            redirect('/site-admin/businesses/new');
        }

        if ((int) ($_POST['switch_now'] ?? 0) === 1) {
            set_active_business_id($businessId);
        }

        $name = trim((string) ($data['name'] ?? ''));
        log_user_action(
            'business_created',
            'businesses',
            $businessId,
            'Created business #' . $businessId . ($name !== '' ? ' (' . $name . ')' : '') . '.'
        );

        flash('success', (int) ($_POST['switch_now'] ?? 0) === 1
            ? 'Business created and active context switched.'
            : 'Business created.');
        redirect('/site-admin/businesses/' . $businessId);
    }

    public function editBusiness(array $params): void
    {
        $this->authorize();

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/site-admin');
        }

        $business = Business::findById($id);
        if (!$business) {
            http_response_code(404);
            if (class_exists('App\\Controllers\\ErrorController')) {
                (new \App\Controllers\ErrorController())->notFound();
                return;
            }

            echo '404 Not Found';
            return;
        }

        $this->render('site_admin/edit', [
            'pageTitle' => 'Edit Business',
            'business' => $business,
        ]);

        clear_old();
    }

    public function updateBusiness(array $params): void
    {
        $this->authorize();

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/site-admin');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/site-admin/businesses/' . $id . '/edit');
        }

        $business = Business::findById($id);
        if (!$business) {
            http_response_code(404);
            if (class_exists('App\\Controllers\\ErrorController')) {
                (new \App\Controllers\ErrorController())->notFound();
                return;
            }

            echo '404 Not Found';
            return;
        }

        $data = $this->collectBusinessInput($_POST);
        $errors = $this->validateBusinessInput($data, $id);
        $errors = array_merge($errors, $this->applyLogoUpload($data, $business));
        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($data);
            redirect('/site-admin/businesses/' . $id . '/edit');
        }

        Business::update($id, $data, auth_user_id());
        log_user_action('business_updated', 'businesses', $id, 'Updated business #' . $id . '.');

        // If current workspace is now inactive, force fallback workspace selection.
        if ((int) ($data['is_active'] ?? 1) !== 1 && current_business_id() === $id) {
            set_active_business_id(0);
        }

        flash('success', 'Business updated.');
        redirect('/site-admin/businesses/' . $id);
    }

    public function switchBusiness(): void
    {
        $this->authorize();

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/site-admin');
        }

        $businessId = (int) ($_POST['business_id'] ?? 0);
        if ($businessId <= 0 || !Business::exists($businessId, false)) {
            flash('error', 'Business not found.');
            redirect('/site-admin');
        }

        $business = Business::findById($businessId);
        if (!$business) {
            flash('error', 'Business not found.');
            redirect('/site-admin');
        }

        if ((int) ($business['is_active'] ?? 0) !== 1) {
            flash('error', 'This business is inactive and cannot be selected.');
            redirect('/site-admin');
        }

        set_active_business_id($businessId);
        log_user_action(
            'business_context_switched',
            'businesses',
            $businessId,
            'Switched active business context to #' . $businessId . '.'
        );

        flash('success', 'Active business switched to ' . (string) ($business['name'] ?? ('#' . $businessId)) . '.');
        $next = trim((string) ($_POST['next'] ?? '/admin'));
        if ($next === '' || $next[0] !== '/') {
            $next = '/admin';
        }
        redirect($next);
    }

    public function exitBusinessContext(): void
    {
        $this->authorize();

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/site-admin');
        }

        $activeBusinessId = (int) ($_SESSION['active_business_id'] ?? 0);
        if ($activeBusinessId > 0) {
            log_user_action(
                'business_context_switched',
                'businesses',
                $activeBusinessId,
                'Exited business workspace and returned to global site admin dashboard.'
            );
        }

        set_active_business_id(0);
        flash('success', 'Returned to global site admin dashboard.');
        redirect('/site-admin');
    }

    public function showBusiness(array $params): void
    {
        $this->authorize();

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/site-admin');
        }

        $business = Business::findWithStats($id);
        if (!$business) {
            http_response_code(404);
            if (class_exists('App\\Controllers\\ErrorController')) {
                (new \App\Controllers\ErrorController())->notFound();
                return;
            }

            echo '404 Not Found';
            return;
        }

        $this->render('site_admin/show', [
            'pageTitle' => 'Business Profile',
            'business' => $business,
            'isCurrentWorkspace' => $id === current_business_id(),
        ]);
    }

    private function authorize(): void
    {
        require_role(4);
    }

    private function globalSummary(array $businesses): array
    {
        $totalBusinesses = count($businesses);
        $activeBusinesses = 0;
        foreach ($businesses as $business) {
            if ((int) ($business['is_active'] ?? 0) === 1) {
                $activeBusinesses++;
            }
        }

        $usersTotal = 0;
        $globalAdminsTotal = 0;
        $pendingInvites = 0;

        try {
            $sql = 'SELECT
                        COUNT(*) AS users_total,
                        COALESCE(SUM(CASE WHEN role >= 4 THEN 1 ELSE 0 END), 0) AS global_admins_total,
                        COALESCE(SUM(
                            CASE
                                WHEN is_active = 1
                                 AND password_setup_sent_at IS NOT NULL
                                 AND COALESCE(password_setup_used_at, \'\') = \'\'
                                 AND COALESCE(password_hash, \'\') = \'\'
                                THEN 1
                                ELSE 0
                            END
                        ), 0) AS pending_invites
                    FROM users';
            $row = Database::connection()->query($sql)->fetch();
            if (is_array($row)) {
                $usersTotal = (int) ($row['users_total'] ?? 0);
                $globalAdminsTotal = (int) ($row['global_admins_total'] ?? 0);
                $pendingInvites = (int) ($row['pending_invites'] ?? 0);
            }
        } catch (Throwable) {
            // keep defaults
        }

        return [
            'business_total' => $totalBusinesses,
            'business_active' => $activeBusinesses,
            'business_inactive' => max(0, $totalBusinesses - $activeBusinesses),
            'users_total' => $usersTotal,
            'global_admins_total' => $globalAdminsTotal,
            'pending_invites' => $pendingInvites,
        ];
    }

    private function recentChanges(int $limit = 10): array
    {
        if (!UserAction::isAvailable()) {
            return [];
        }

        $limit = max(1, min(200, $limit));

        try {
            $sql = 'SELECT ua.id,
                           ua.action_key,
                           ua.entity_table,
                           ua.entity_id,
                           ua.summary,
                           ua.created_at,
                           COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u.first_name, u.last_name)), \'\'), u.email, \'System\') AS actor_name
                    FROM user_actions ua
                    LEFT JOIN users u ON u.id = ua.user_id
                    ORDER BY ua.created_at DESC, ua.id DESC
                    LIMIT :limit';
            $stmt = Database::connection()->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    private function validateBusinessInput(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $errors[] = 'Business name is required.';
        } elseif (Business::nameInUse($name, $excludeId)) {
            $errors[] = 'That business name is already in use.';
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Business email must be a valid email address.';
        }

        $website = trim((string) ($data['website'] ?? ''));
        if ($website !== '' && filter_var($website, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'Business website must be a valid URL.';
        }

        $timezone = trim((string) ($data['timezone'] ?? ''));
        if ($timezone !== '') {
            try {
                new \DateTimeZone($timezone);
            } catch (\Throwable) {
                $errors[] = 'Timezone is invalid.';
            }
        }

        $defaultTaxRate = $data['invoice_default_tax_rate'] ?? null;
        if ($defaultTaxRate !== null && (!is_numeric((string) $defaultTaxRate) || (float) $defaultTaxRate < 0 || (float) $defaultTaxRate > 100)) {
            $errors[] = 'Default invoice tax rate must be between 0 and 100.';
        }

        return $errors;
    }

    private function collectBusinessInput(array $source): array
    {
        return [
            'name' => trim((string) ($source['name'] ?? '')),
            'legal_name' => trim((string) ($source['legal_name'] ?? '')),
            'email' => trim((string) ($source['email'] ?? '')),
            'phone' => trim((string) ($source['phone'] ?? '')),
            'website' => trim((string) ($source['website'] ?? '')),
            'address_line1' => trim((string) ($source['address_line1'] ?? '')),
            'address_line2' => trim((string) ($source['address_line2'] ?? '')),
            'city' => trim((string) ($source['city'] ?? '')),
            'state' => trim((string) ($source['state'] ?? '')),
            'postal_code' => trim((string) ($source['postal_code'] ?? '')),
            'country' => trim((string) ($source['country'] ?? 'US')) ?: 'US',
            'tax_id' => trim((string) ($source['tax_id'] ?? '')),
            'invoice_default_tax_rate' => $this->toNullableDecimal($source['invoice_default_tax_rate'] ?? null),
            'timezone' => trim((string) ($source['timezone'] ?? 'America/New_York')) ?: 'America/New_York',
            'logo_path' => trim((string) ($source['logo_path'] ?? '')),
            'logo_mime_type' => trim((string) ($source['logo_mime_type'] ?? '')),
            'is_active' => (int) ($source['is_active'] ?? 1) === 1 ? 1 : 0,
        ];
    }

    private function applyLogoUpload(array &$data, ?array $existingBusiness): array
    {
        $errors = [];
        if (!empty($_POST['remove_logo'])) {
            $this->removeStoredLogo((string) ($existingBusiness['logo_path'] ?? ''));
            $data['logo_path'] = '';
            $data['logo_mime_type'] = '';
            return $errors;
        }

        $upload = $_FILES['logo_file'] ?? null;
        if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $data['logo_path'] = trim((string) ($existingBusiness['logo_path'] ?? ''));
            $data['logo_mime_type'] = trim((string) ($existingBusiness['logo_mime_type'] ?? ''));
            return $errors;
        }

        $errorCode = (int) ($upload['error'] ?? UPLOAD_ERR_OK);
        if ($errorCode !== UPLOAD_ERR_OK) {
            $errors[] = 'Logo upload failed. Please try again.';
            return $errors;
        }

        $tmpPath = (string) ($upload['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            $errors[] = 'Invalid logo upload payload.';
            return $errors;
        }

        $mimeType = trim((string) ($upload['type'] ?? ''));
        if ($mimeType === '' || !str_starts_with($mimeType, 'image/')) {
            $detectedMime = @mime_content_type($tmpPath);
            $mimeType = is_string($detectedMime) ? trim($detectedMime) : '';
        }
        if ($mimeType === '' || !str_starts_with($mimeType, 'image/')) {
            $errors[] = 'Logo must be an image file.';
            return $errors;
        }

        $extension = match ($mimeType) {
            'image/png' => 'png',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => '',
        };
        if ($extension === '') {
            $errors[] = 'Supported logo formats: PNG, JPG, GIF, WEBP.';
            return $errors;
        }

        $size = (int) ($upload['size'] ?? 0);
        if ($size > 4 * 1024 * 1024) {
            $errors[] = 'Logo must be 4MB or smaller.';
            return $errors;
        }

        $storageDir = $this->businessLogoStorageRoot();
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0775, true);
        }
        if (!is_dir($storageDir) || !is_writable($storageDir)) {
            $errors[] = 'Business logo storage is not writable.';
            return $errors;
        }

        try {
            $token = bin2hex(random_bytes(4));
        } catch (\Throwable) {
            $token = (string) mt_rand(1000, 9999);
        }
        $fileName = 'business-logo-' . date('YmdHis') . '-' . $token . '.' . $extension;
        $target = $storageDir . '/' . $fileName;
        if (!@move_uploaded_file($tmpPath, $target)) {
            $errors[] = 'Could not save uploaded logo.';
            return $errors;
        }

        $this->removeStoredLogo((string) ($existingBusiness['logo_path'] ?? ''));
        $data['logo_path'] = 'storage/business_logos/' . $fileName;
        $data['logo_mime_type'] = $mimeType;
        return $errors;
    }

    private function businessLogoStorageRoot(): string
    {
        return BASE_PATH . '/storage/business_logos';
    }

    private function removeStoredLogo(string $relativePath): void
    {
        $relativePath = trim($relativePath);
        if ($relativePath === '') {
            return;
        }

        $absolute = BASE_PATH . '/' . ltrim($relativePath, '/');
        $logoRoot = $this->businessLogoStorageRoot();
        if (!str_starts_with($absolute, $logoRoot . '/')) {
            return;
        }

        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    private function toNullableDecimal(mixed $value): ?float
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $normalized = str_replace([',', '$', '%'], '', $raw);
        if (!is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 4);
    }
}
