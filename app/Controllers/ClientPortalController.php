<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\ClientPortalAccess;
use App\Models\Invoice;
use Core\Controller;

final class ClientPortalController extends Controller
{
    public function show(array $params): void
    {
        $token = trim((string) ($params['token'] ?? ''));
        $ctx = ClientPortalAccess::validateToken($token);
        if ($ctx === null || !ClientPortalAccess::isAvailable()) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found', 'publicPage' => true]);
            return;
        }

        $businessId = (int) $ctx['business_id'];
        $invoiceId = (int) $ctx['invoice_id'];
        $invoice = Invoice::findForBusiness($businessId, $invoiceId);
        if ($invoice === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found', 'publicPage' => true]);
            return;
        }

        $business = Business::findById($businessId);
        $docType = strtolower(trim((string) ($invoice['type'] ?? 'invoice'))) === 'estimate' ? 'estimate' : 'invoice';
        $safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) ($business['name'] ?? 'junktracker')) ?? 'junktracker';
        $filename = $safeName . '-' . $docType . '-' . (trim((string) ($invoice['invoice_number'] ?? '')) !== '' ? trim((string) $invoice['invoice_number']) : (string) $invoiceId) . '.html';
        if (!empty($_GET['download'])) {
            header('Content-Type: text/html; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }

        $items = Invoice::lineItems($businessId, $invoiceId);
        $payments = Invoice::payments($businessId, $invoiceId);

        $_SESSION['portal_csrf'] = bin2hex(random_bytes(16));

        $this->renderDocument('billing/document', [
            'pageTitle' => strtolower(trim((string) ($invoice['type'] ?? ''))) === 'estimate' ? 'Estimate' : 'Invoice',
            'invoice' => $invoice,
            'items' => $items,
            'payments' => $payments,
            'business' => $business,
            'hidePaymentsDetail' => false,
            'portalToken' => $token,
            'portalCsrf' => $_SESSION['portal_csrf'],
            'portalApprove' => strtolower(trim((string) ($invoice['type'] ?? ''))) === 'estimate'
                && strtolower(trim((string) ($invoice['status'] ?? ''))) !== 'approved',
        ]);
    }

    public function approveEstimate(array $params): void
    {
        $token = trim((string) ($params['token'] ?? ''));
        $ctx = ClientPortalAccess::validateToken($token);
        if ($ctx === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found', 'publicPage' => true]);
            return;
        }

        if (!hash_equals((string) ($_SESSION['portal_csrf'] ?? ''), (string) ($_POST['portal_csrf'] ?? ''))) {
            flash('error', 'Session expired. Open the link again.');
            redirect('/portal/' . rawurlencode($token));
        }

        $businessId = (int) $ctx['business_id'];
        $invoiceId = (int) $ctx['invoice_id'];
        $invoice = Invoice::findForBusiness($businessId, $invoiceId);
        if ($invoice === null || strtolower(trim((string) ($invoice['type'] ?? ''))) !== 'estimate') {
            flash('error', 'Estimate not found.');
            redirect('/portal/' . rawurlencode($token));
        }

        $actorUserId = (int) (auth_user_id() ?? 0);
        if ($actorUserId <= 0) {
            $actorUserId = 0;
        }

        Invoice::updateStatus($businessId, $invoiceId, 'estimate', 'approved', $actorUserId);
        AuditLog::write('estimate_portal_approved', 'invoices', $invoiceId, $businessId, null, []);
        flash('success', 'Thank you — this estimate is marked approved.');
        redirect('/portal/' . rawurlencode($token));
    }
}
