<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Business;
use Core\Controller;

final class AdminBusinessDetailsController extends Controller
{
    public function edit(): void
    {
        require_business_role(['admin']);

        $businessId = current_business_id();
        if ($businessId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $business = Business::findById($businessId);
        if ($business === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('admin/business_details/form', [
            'pageTitle' => 'Business Details',
            'actionUrl' => url('/admin/business-details/update'),
            'form' => $this->formFromBusiness($business),
            'errors' => [],
        ]);
    }

    public function update(): void
    {
        require_business_role(['admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/admin/business-details');
        }

        $businessId = current_business_id();
        if ($businessId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $business = Business::findById($businessId);
        if ($business === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form);
        if ($errors !== []) {
            $this->render('admin/business_details/form', [
                'pageTitle' => 'Business Details',
                'actionUrl' => url('/admin/business-details/update'),
                'form' => $form,
                'errors' => $errors,
            ]);
            return;
        }

        if ($form['mailing_same_as_physical'] === '1') {
            $form['mailing_address_line1'] = $form['address_line1'];
            $form['mailing_address_line2'] = $form['address_line2'];
            $form['mailing_city'] = $form['city'];
            $form['mailing_state'] = $form['state'];
            $form['mailing_postal_code'] = $form['postal_code'];
        }

        Business::updateDetails($businessId, [
            'name' => $form['name'],
            'legal_name' => $form['legal_name'],
            'phone' => $form['phone'],
            'address_line1' => $form['address_line1'],
            'address_line2' => $form['address_line2'],
            'city' => $form['city'],
            'state' => $form['state'],
            'postal_code' => $form['postal_code'],
            'primary_contact_name' => $form['primary_contact_name'],
            'website_url' => $form['website_url'],
            'ein_number' => $form['ein_number'],
            'mailing_same_as_physical' => (int) ($form['mailing_same_as_physical'] === '1'),
            'mailing_address_line1' => $form['mailing_address_line1'],
            'mailing_address_line2' => $form['mailing_address_line2'],
            'mailing_city' => $form['mailing_city'],
            'mailing_state' => $form['mailing_state'],
            'mailing_postal_code' => $form['mailing_postal_code'],
        ], (int) (auth_user_id() ?? 0));

        flash('success', 'Business details updated.');
        redirect('/admin/business-details');
    }

    private function formFromBusiness(array $business): array
    {
        return [
            'name' => trim((string) ($business['name'] ?? '')),
            'legal_name' => trim((string) ($business['legal_name'] ?? '')),
            'phone' => trim((string) ($business['phone'] ?? '')),
            'address_line1' => trim((string) ($business['address_line1'] ?? '')),
            'address_line2' => trim((string) ($business['address_line2'] ?? '')),
            'city' => trim((string) ($business['city'] ?? '')),
            'state' => trim((string) ($business['state'] ?? '')),
            'postal_code' => trim((string) ($business['postal_code'] ?? '')),
            'primary_contact_name' => trim((string) ($business['primary_contact_name'] ?? '')),
            'website_url' => trim((string) ($business['website_url'] ?? '')),
            'ein_number' => trim((string) ($business['ein_number'] ?? '')),
            'mailing_same_as_physical' => ((int) ($business['mailing_same_as_physical'] ?? 1)) === 1 ? '1' : '0',
            'mailing_address_line1' => trim((string) ($business['mailing_address_line1'] ?? '')),
            'mailing_address_line2' => trim((string) ($business['mailing_address_line2'] ?? '')),
            'mailing_city' => trim((string) ($business['mailing_city'] ?? '')),
            'mailing_state' => trim((string) ($business['mailing_state'] ?? '')),
            'mailing_postal_code' => trim((string) ($business['mailing_postal_code'] ?? '')),
        ];
    }

    private function formFromPost(array $input): array
    {
        return [
            'name' => trim((string) ($input['name'] ?? '')),
            'legal_name' => trim((string) ($input['legal_name'] ?? '')),
            'phone' => trim((string) ($input['phone'] ?? '')),
            'address_line1' => trim((string) ($input['address_line1'] ?? '')),
            'address_line2' => trim((string) ($input['address_line2'] ?? '')),
            'city' => trim((string) ($input['city'] ?? '')),
            'state' => trim((string) ($input['state'] ?? '')),
            'postal_code' => trim((string) ($input['postal_code'] ?? '')),
            'primary_contact_name' => trim((string) ($input['primary_contact_name'] ?? '')),
            'website_url' => trim((string) ($input['website_url'] ?? '')),
            'ein_number' => trim((string) ($input['ein_number'] ?? '')),
            'mailing_same_as_physical' => ((string) ($input['mailing_same_as_physical'] ?? '1')) === '1' ? '1' : '0',
            'mailing_address_line1' => trim((string) ($input['mailing_address_line1'] ?? '')),
            'mailing_address_line2' => trim((string) ($input['mailing_address_line2'] ?? '')),
            'mailing_city' => trim((string) ($input['mailing_city'] ?? '')),
            'mailing_state' => trim((string) ($input['mailing_state'] ?? '')),
            'mailing_postal_code' => trim((string) ($input['mailing_postal_code'] ?? '')),
        ];
    }

    private function validateForm(array $form): array
    {
        $errors = [];

        if ($form['name'] === '') {
            $errors['name'] = 'Company name is required.';
        }

        if ($form['website_url'] !== '') {
            $web = $form['website_url'];
            $isValid = false;
            if (filter_var($web, FILTER_VALIDATE_URL) !== false) {
                $isValid = true;
            } elseif (preg_match('/^(?:[a-z0-9-]+\.)+[a-z]{2,}(?:\/.*)?$/i', $web) === 1) {
                $isValid = true;
            }
            if (!$isValid) {
                $errors['website_url'] = 'Enter a valid web address.';
            }
        }

        if ($form['mailing_same_as_physical'] !== '1' && $form['mailing_address_line1'] === '') {
            $errors['mailing_address_line1'] = 'Mailing address line 1 is required when mailing address differs.';
        }

        return $errors;
    }
}
