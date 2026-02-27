<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;

final class BillingController extends Controller
{
    public function index(): void
    {
        require_business_role(['general_user', 'admin']);

        $this->render('placeholders/module', [
            'pageTitle' => 'Billing',
            'title' => 'Billing',
            'message' => 'Phase A scaffold: estimates/invoices/payments module will be implemented in Phase D.',
        ]);
    }
}
