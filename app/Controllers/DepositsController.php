<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\BankDeposit;
use Core\Controller;

final class DepositsController extends Controller
{
    public function index(): void
    {
        require_business_role(['general_user', 'admin']);

        if (!BankDeposit::isAvailable()) {
            flash('error', 'Deposits require the latest database migration.');
            redirect('/billing');
        }

        $businessId = current_business_id();
        $deposits = BankDeposit::indexList($businessId);

        $this->render('billing/deposits_index', [
            'pageTitle' => 'Bank deposits',
            'deposits' => $deposits,
        ]);
    }

    public function create(): void
    {
        require_business_role(['general_user', 'admin']);

        if (!BankDeposit::isAvailable()) {
            flash('error', 'Deposits require the latest database migration.');
            redirect('/billing');
        }

        $this->render('billing/deposit_form', [
            'pageTitle' => 'Add deposit',
            'form' => [
                'deposit_date' => date('Y-m-d'),
                'amount' => '',
                'note' => '',
            ],
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        require_business_role(['general_user', 'admin']);

        if (!BankDeposit::isAvailable() || !verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired or deposits unavailable.');
            redirect('/billing/deposits');
        }

        $businessId = current_business_id();
        $amount = round((float) str_replace(',', '', (string) ($_POST['amount'] ?? '0')), 2);
        $date = trim((string) ($_POST['deposit_date'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));
        $errors = [];
        if ($amount <= 0) {
            $errors['amount'] = 'Enter a positive amount.';
        }
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errors['deposit_date'] = 'Valid date required.';
        }
        if ($errors !== []) {
            $this->render('billing/deposit_form', [
                'pageTitle' => 'Add deposit',
                'form' => [
                    'deposit_date' => $date,
                    'amount' => (string) ($_POST['amount'] ?? ''),
                    'note' => $note,
                ],
                'errors' => $errors,
            ]);
            return;
        }

        $actor = (int) (auth_user_id() ?? 0);
        $id = BankDeposit::create($businessId, [
            'deposit_date' => $date,
            'amount' => $amount,
            'note' => $note !== '' ? $note : null,
        ], $actor);
        AuditLog::write('bank_deposit_created', 'bank_deposits', $id, $businessId, $actor, ['amount' => $amount]);
        flash('success', 'Deposit recorded.');
        redirect('/billing/deposits/' . (string) $id);
    }

    public function show(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $id = (int) ($params['id'] ?? 0);
        $businessId = current_business_id();
        if (!BankDeposit::isAvailable() || $id <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $deposit = BankDeposit::findForBusiness($businessId, $id);
        if ($deposit === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $linked = BankDeposit::linkedPayments($businessId, $id);
        $unassigned = BankDeposit::unassignedPayments($businessId);

        $this->render('billing/deposit_show', [
            'pageTitle' => 'Deposit',
            'deposit' => $deposit,
            'linkedPayments' => $linked,
            'unassignedPayments' => $unassigned,
            'linkedTotal' => BankDeposit::totalLinkedAmount($businessId, $id),
        ]);
    }

    public function linkPayment(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired.');
            redirect('/billing/deposits');
        }

        $depositId = (int) ($params['id'] ?? 0);
        $paymentId = (int) ($_POST['payment_id'] ?? 0);
        $businessId = current_business_id();

        if (!BankDeposit::isAvailable() || $depositId <= 0 || $paymentId <= 0) {
            flash('error', 'Invalid request.');
            redirect('/billing/deposits');
        }

        if (BankDeposit::linkPayment($businessId, $depositId, $paymentId)) {
            AuditLog::write('bank_deposit_payment_linked', 'bank_deposits', $depositId, $businessId, (int) (auth_user_id() ?? 0), ['payment_id' => $paymentId]);
            flash('success', 'Payment linked to deposit.');
        } else {
            flash('error', 'Could not link payment.');
        }
        redirect('/billing/deposits/' . (string) $depositId);
    }

    public function unlinkPayment(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired.');
            redirect('/billing/deposits');
        }

        $depositId = (int) ($params['id'] ?? 0);
        $paymentId = (int) ($_POST['payment_id'] ?? 0);
        $businessId = current_business_id();

        if (!BankDeposit::isAvailable() || $depositId <= 0 || $paymentId <= 0) {
            flash('error', 'Invalid request.');
            redirect('/billing/deposits');
        }

        if (BankDeposit::unlinkPayment($businessId, $depositId, $paymentId)) {
            AuditLog::write('bank_deposit_payment_unlinked', 'bank_deposits', $depositId, $businessId, (int) (auth_user_id() ?? 0), ['payment_id' => $paymentId]);
            flash('success', 'Payment unlinked.');
        } else {
            flash('error', 'Could not unlink.');
        }
        redirect('/billing/deposits/' . (string) $depositId);
    }
}
