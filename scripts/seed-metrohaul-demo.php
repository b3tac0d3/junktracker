<?php

declare(strict_types=1);

/**
 * Seed rich demo data for Metro Haul Co (business #2 by default).
 *
 * Usage:
 *   HTTP_HOST=localhost php scripts/seed-metrohaul-demo.php
 *   HTTP_HOST=localhost php scripts/seed-metrohaul-demo.php --fresh
 *   HTTP_HOST=localhost php scripts/seed-metrohaul-demo.php --business-id=2
 *
 * After you like the dataset, export it:
 *   HTTP_HOST=localhost php scripts/export-metrohaul-demo.php
 */

$root = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require $root . '/app/bootstrap.php';

use App\Models\Client;
use App\Models\Employee;
use App\Models\Event;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\Purchase;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Task;
use App\Models\TimeEntry;
use Core\Database;

$args = array_slice($argv, 1);
$fresh = in_array('--fresh', $args, true);
$businessId = 2;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--business-id=')) {
        $businessId = max(1, (int) substr($arg, strlen('--business-id=')));
    }
}

$pdo = Database::connection();
$actorUserId = resolveActorUserId($pdo);

echo "Metro Haul demo seed\n";
echo "Business ID: {$businessId}\n";
echo "Actor user: {$actorUserId}\n";

$business = $pdo->prepare('SELECT id, name FROM businesses WHERE id = :id LIMIT 1');
$business->execute(['id' => $businessId]);
$businessRow = $business->fetch(PDO::FETCH_ASSOC);
if (!is_array($businessRow)) {
    fwrite(STDERR, "Business #{$businessId} not found.\n");
    exit(1);
}
echo 'Business: ' . (string) ($businessRow['name'] ?? '') . "\n";

ensureBusinessAccess($pdo, $businessId, $actorUserId);

if ($fresh) {
    echo "Wiping existing Metro Haul operational data...\n";
    wipeBusinessOperationalData($pdo, $businessId);
}

echo "Seeding demo data...\n";

$clientIds = seedClients($businessId, $actorUserId);
$employeeIds = seedEmployees($businessId, $actorUserId);
$quoteIds = seedQuotes($businessId, $actorUserId, $clientIds);
$jobIds = seedJobs($businessId, $actorUserId, $clientIds, $quoteIds, $employeeIds);
seedAppointments($businessId, $actorUserId, $clientIds, $jobIds);
seedBilling($businessId, $actorUserId, $clientIds, $jobIds);
seedSales($businessId, $actorUserId, $clientIds, $jobIds);
seedExpenses($businessId, $actorUserId, $jobIds);
seedPurchases($businessId, $actorUserId, $clientIds, $jobIds);
seedTasks($businessId, $actorUserId, $clientIds, $jobIds);
seedLabor($businessId, $actorUserId, $jobIds, $employeeIds);

echo "\nDone. Summary:\n";
printCounts($pdo, $businessId);
echo "\nExport when ready:\n  HTTP_HOST=localhost php scripts/export-metrohaul-demo.php --business-id={$businessId}\n";

function resolveActorUserId(PDO $pdo): int
{
    $row = $pdo->query("SELECT id FROM users WHERE role = 'site_admin' ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (is_array($row) && (int) ($row['id'] ?? 0) > 0) {
        return (int) $row['id'];
    }
    $row = $pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? max(1, (int) ($row['id'] ?? 1)) : 1;
}

function ensureBusinessAccess(PDO $pdo, int $businessId, int $userId): void
{
    $check = $pdo->prepare(
        'SELECT id FROM business_user_memberships
         WHERE business_id = :business_id AND user_id = :user_id AND deleted_at IS NULL LIMIT 1'
    );
    $check->execute(['business_id' => $businessId, 'user_id' => $userId]);
    if ($check->fetch()) {
        return;
    }

    $pdo->prepare(
        'INSERT INTO business_user_memberships (business_id, user_id, role, is_active, created_at, updated_at)
         VALUES (:business_id, :user_id, :role, 1, NOW(), NOW())'
    )->execute([
        'business_id' => $businessId,
        'user_id' => $userId,
        'role' => 'admin',
    ]);
    echo "Added admin membership for user #{$userId} on business #{$businessId}.\n";
}

function wipeBusinessOperationalData(PDO $pdo, int $businessId): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

    $tables = [
        'payments',
        'invoice_items',
        'employee_time_entries',
        'job_employee_assignments',
        'job_adjustments',
        'estate_sale_customer_visits',
        'estate_sale_customer_memberships',
        'estate_sale_customers',
        'estate_sale_employee_assignments',
        'expenses',
        'sales',
        'purchase_quote_contacts',
        'purchase_quote_offers',
        'purchase_quotes',
        'purchases',
        'invoices',
        'tasks',
        'events',
        'jobs',
        'quotes',
        'client_deliveries',
        'client_contacts',
        'client_family_members',
        'client_bolo_profiles',
        'client_bolo_lines',
        'estate_sales',
        'clients',
        'employees',
        'networking_contacts',
        'activity_logs',
    ];

    foreach ($tables as $table) {
        if (!tableExists($pdo, $table)) {
            continue;
        }
        if (columnExists($pdo, $table, 'business_id')) {
            $pdo->exec("DELETE FROM {$table} WHERE business_id = {$businessId}");
            continue;
        }
        if ($table === 'client_bolo_lines') {
            $pdo->exec(
                "DELETE l FROM client_bolo_lines l
                 INNER JOIN client_bolo_profiles p ON p.id = l.bolo_profile_id
                 WHERE p.business_id = {$businessId}"
            );
        }
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
    $stmt->execute(['table' => $table]);
    return (int) $stmt->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
    );
    $stmt->execute(['table' => $table, 'column' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function dt(int $dayOffset, string $time = '09:00:00'): string
{
    return date('Y-m-d H:i:s', strtotime(sprintf('%+d days %s', $dayOffset, $time)));
}

function d(int $dayOffset): string
{
    return date('Y-m-d', strtotime(sprintf('%+d days', $dayOffset)));
}

/**
 * @return list<int>
 */
function seedClients(int $businessId, int $actorUserId): array
{
    $clients = [
        ['first_name' => 'Logan', 'last_name' => 'Goins', 'phone' => '617-555-2001', 'client_type' => 'client', 'address_line1' => '18 River St', 'city' => 'Boston', 'state' => 'MA', 'postal_code' => '02118', 'notes' => 'Needs COI before service date.'],
        ['first_name' => 'Julia', 'last_name' => 'Nash', 'phone' => '617-555-2002', 'client_type' => 'realtor', 'address_line1' => '42 Garden Ln', 'city' => 'Brookline', 'state' => 'MA', 'postal_code' => '02445', 'notes' => 'Coordinates move-out schedules.'],
        ['company_name' => 'Metro Property Group', 'phone' => '617-555-2003', 'client_type' => 'company', 'address_line1' => '88 Beacon St', 'city' => 'Boston', 'state' => 'MA', 'postal_code' => '02108', 'notes' => 'Monthly hauling contract.'],
        ['first_name' => 'Elena', 'last_name' => 'Morales', 'phone' => '617-555-2010', 'client_type' => 'client', 'address_line1' => '55 Commonwealth Ave', 'city' => 'Boston', 'state' => 'MA', 'postal_code' => '02116', 'notes' => 'Basement cleanout referral from Julia Nash.'],
        ['first_name' => 'Marcus', 'last_name' => 'Whitfield', 'phone' => '617-555-2011', 'client_type' => 'client', 'address_line1' => '12 Porter Rd', 'city' => 'Cambridge', 'state' => 'MA', 'postal_code' => '02140', 'notes' => 'Prefers morning appointments.'],
        ['first_name' => 'Priya', 'last_name' => 'Shah', 'phone' => '617-555-2012', 'client_type' => 'client', 'address_line1' => '701 Boylston St', 'city' => 'Boston', 'state' => 'MA', 'postal_code' => '02116', 'notes' => 'Condo association board contact.'],
        ['company_name' => 'North End Realty Partners', 'phone' => '617-555-2013', 'client_type' => 'realtor', 'address_line1' => '300 Hanover St', 'city' => 'Boston', 'state' => 'MA', 'postal_code' => '02113', 'notes' => 'Books same-week turnover cleanouts.'],
        ['first_name' => 'Tom', 'last_name' => 'Bergeron', 'phone' => '617-555-2014', 'client_type' => 'client', 'address_line1' => '4 Oak Hill Dr', 'city' => 'Newton', 'state' => 'MA', 'postal_code' => '02458', 'notes' => 'Estate cleanout scheduled for June.'],
        ['first_name' => 'Hannah', 'last_name' => 'Cho', 'phone' => '617-555-2015', 'client_type' => 'client', 'address_line1' => '220 Main St', 'city' => 'Somerville', 'state' => 'MA', 'postal_code' => '02145', 'notes' => 'Garage and attic combo job.'],
        ['company_name' => 'Bay State Storage Co', 'phone' => '617-555-2016', 'client_type' => 'company', 'address_line1' => '15 Industrial Way', 'city' => 'Medford', 'state' => 'MA', 'postal_code' => '02155', 'notes' => 'Quarterly unit cleanouts.'],
        ['first_name' => 'Rachel', 'last_name' => 'Donovan', 'phone' => '617-555-2017', 'client_type' => 'realtor', 'address_line1' => '9 Centre St', 'city' => 'Jamaica Plain', 'state' => 'MA', 'postal_code' => '02130', 'notes' => 'Often needs donation pickup coordination.'],
        ['first_name' => 'Devon', 'last_name' => 'Alvarez', 'phone' => '617-555-2018', 'client_type' => 'client', 'address_line1' => '77 Atlantic Ave', 'city' => 'Boston', 'state' => 'MA', 'postal_code' => '02110', 'notes' => 'Restaurant buildout debris removal.'],
    ];

    $ids = [];
    foreach ($clients as $row) {
        $ids[] = Client::create($businessId, $row, $actorUserId);
    }

    echo 'Clients: ' . count($ids) . "\n";
    return $ids;
}

/**
 * @return list<int>
 */
function seedEmployees(int $businessId, int $actorUserId): array
{
    $employees = [
        ['first_name' => 'Mike', 'last_name' => 'Torres', 'email' => 'mike.torres@metrohaul.local', 'phone' => '617-555-2101', 'hourly_rate' => 28.00, 'status' => 'active'],
        ['first_name' => 'Dana', 'last_name' => 'Chen', 'email' => 'dana.chen@metrohaul.local', 'phone' => '617-555-2102', 'hourly_rate' => 26.50, 'status' => 'active'],
        ['first_name' => 'Carlos', 'last_name' => 'Ruiz', 'email' => 'carlos.ruiz@metrohaul.local', 'phone' => '617-555-2103', 'hourly_rate' => 24.00, 'status' => 'active'],
    ];

    $ids = [];
    foreach ($employees as $row) {
        $ids[] = Employee::create($businessId, $row, $actorUserId);
    }

    echo 'Employees: ' . count($ids) . "\n";
    return $ids;
}

/**
 * @param list<int> $clientIds
 * @return array<string, int>
 */
function seedQuotes(int $businessId, int $actorUserId, array $clientIds): array
{
    $c = static fn (int $index): int => $clientIds[$index] ?? $clientIds[0];

    $quotes = [
        ['client_index' => 3, 'title' => 'Basement cleanout — Morales', 'status' => 'won', 'service_type' => 'junk_removal', 'quoted_amount' => 1450, 'next_follow_up_at' => dt(-18, '10:00:00'), 'city' => 'Boston'],
        ['client_index' => 4, 'title' => 'Garage cleanout — Whitfield', 'status' => 'won', 'service_type' => 'junk_removal', 'quoted_amount' => 980, 'next_follow_up_at' => dt(-12, '14:00:00'), 'city' => 'Cambridge'],
        ['client_index' => 5, 'title' => 'Condo association bulk pickup', 'status' => 'sent', 'service_type' => 'junk_removal', 'quoted_amount' => 3200, 'next_follow_up_at' => dt(2, '11:00:00'), 'city' => 'Boston'],
        ['client_index' => 6, 'title' => 'Turnover cleanout — Hanover St', 'status' => 'follow_up', 'service_type' => 'cleanout', 'quoted_amount' => 1750, 'next_follow_up_at' => dt(1, '15:30:00'), 'city' => 'Boston'],
        ['client_index' => 7, 'title' => 'Estate preview — Bergeron', 'status' => 'new', 'service_type' => 'estate_cleanout', 'quoted_amount' => 5400, 'next_follow_up_at' => dt(4, '09:30:00'), 'city' => 'Newton'],
        ['client_index' => 8, 'title' => 'Attic + garage combo', 'status' => 'new', 'service_type' => 'junk_removal', 'quoted_amount' => 1280, 'next_follow_up_at' => dt(3, '13:00:00'), 'city' => 'Somerville'],
        ['client_index' => 9, 'title' => 'Storage unit quarterly sweep', 'status' => 'lost', 'service_type' => 'cleanout', 'quoted_amount' => 890, 'next_follow_up_at' => dt(-25, '10:00:00'), 'city' => 'Medford', 'lost_reason' => 'Went with competitor'],
        ['client_index' => 10, 'title' => 'JP duplex turnover', 'status' => 'expired', 'service_type' => 'cleanout', 'quoted_amount' => 1100, 'next_follow_up_at' => dt(-40, '09:00:00'), 'city' => 'Jamaica Plain'],
    ];

    $out = [];
    foreach ($quotes as $quote) {
        $clientIndex = (int) ($quote['client_index'] ?? 0);
        $clientId = $c($clientIndex);
        $quoteId = Quote::create($businessId, [
            'client_id' => $clientId,
            'title' => (string) $quote['title'],
            'status' => (string) $quote['status'],
            'service_type' => (string) $quote['service_type'],
            'quoted_amount' => (float) $quote['quoted_amount'],
            'next_follow_up_at' => (string) $quote['next_follow_up_at'],
            'lost_reason' => (string) ($quote['lost_reason'] ?? ''),
            'city' => (string) ($quote['city'] ?? 'Boston'),
            'state' => 'MA',
            'source' => 'phone',
            'priority' => 'normal',
        ], $actorUserId);

        $key = strtolower(str_replace(' ', '_', (string) $quote['status'])) . '_' . $quoteId;
        $out[$key] = $quoteId;

        if ((string) $quote['status'] === 'won') {
            $jobId = Quote::convertToJob($businessId, $quoteId, $actorUserId);
            if ($jobId > 0) {
                $out['job_from_quote_' . $quoteId] = $jobId;
            }
        }
    }

    echo 'Quotes: ' . count($quotes) . "\n";
    return $out;
}

/**
 * @param list<int> $clientIds
 * @param array<string, int> $quoteIds
 * @param list<int> $employeeIds
 * @return array<string, int>
 */
function seedJobs(int $businessId, int $actorUserId, array $clientIds, array $quoteIds, array $employeeIds): array
{
    $c = static fn (int $index): int => $clientIds[$index] ?? $clientIds[0];
    $jobs = [];

    foreach ($quoteIds as $key => $jobId) {
        if (str_starts_with($key, 'job_from_quote_') && $jobId > 0) {
            $jobs['converted_' . $jobId] = $jobId;
        }
    }

    $manual = [
        [
            'key' => 'active_south_end',
            'client_index' => 11,
            'title' => 'Restaurant buildout debris — Alvarez',
            'status' => 'active',
            'job_type' => 'junk_removal',
            'scheduled_start_at' => dt(2, '08:00:00'),
            'scheduled_end_at' => dt(2, '12:00:00'),
            'city' => 'Boston',
            'address_line1' => '77 Atlantic Ave',
        ],
        [
            'key' => 'pending_beacon',
            'client_index' => 2,
            'title' => 'Beacon St office cleanout',
            'status' => 'pending',
            'job_type' => 'cleanout',
            'scheduled_start_at' => dt(6, '09:00:00'),
            'scheduled_end_at' => dt(6, '13:00:00'),
            'city' => 'Boston',
            'address_line1' => '88 Beacon St',
        ],
        [
            'key' => 'complete_march',
            'client_index' => 0,
            'title' => 'River St storage purge',
            'status' => 'complete',
            'job_type' => 'junk_removal',
            'scheduled_start_at' => dt(-45, '08:30:00'),
            'scheduled_end_at' => dt(-45, '11:30:00'),
            'actual_start_at' => dt(-45, '08:35:00'),
            'actual_end_at' => dt(-45, '11:40:00'),
            'city' => 'Boston',
            'address_line1' => '18 River St',
        ],
    ];

    foreach ($manual as $row) {
        $jobId = Job::create($businessId, [
            'client_id' => $c((int) $row['client_index']),
            'title' => (string) $row['title'],
            'status' => (string) $row['status'],
            'job_type' => (string) $row['job_type'],
            'scheduled_start_at' => $row['scheduled_start_at'],
            'scheduled_end_at' => $row['scheduled_end_at'],
            'actual_start_at' => $row['actual_start_at'] ?? null,
            'actual_end_at' => $row['actual_end_at'] ?? null,
            'address_line1' => (string) $row['address_line1'],
            'city' => (string) $row['city'],
            'state' => 'MA',
            'notes' => 'Demo seed job.',
        ], $actorUserId);
        $jobs[(string) $row['key']] = $jobId;
    }

    // Mark converted quote jobs complete/active with schedules.
    $converted = array_values(array_filter(
        array_keys($jobs),
        static fn (string $key): bool => str_starts_with($key, 'converted_')
    ));
    if (isset($converted[0])) {
        $jobId = $jobs[$converted[0]];
        Job::update($businessId, $jobId, [
            'client_id' => $c(3),
            'title' => 'Basement cleanout — Morales',
            'status' => 'complete',
            'job_type' => 'junk_removal',
            'scheduled_start_at' => dt(-14, '08:00:00'),
            'scheduled_end_at' => dt(-14, '12:00:00'),
            'actual_start_at' => dt(-14, '08:10:00'),
            'actual_end_at' => dt(-14, '11:50:00'),
            'address_line1' => '55 Commonwealth Ave',
            'city' => 'Boston',
            'state' => 'MA',
            'notes' => 'Completed basement cleanout with donation run.',
        ], $actorUserId);
    }
    if (isset($converted[1])) {
        $jobId = $jobs[$converted[1]];
        Job::update($businessId, $jobId, [
            'client_id' => $c(4),
            'title' => 'Garage cleanout — Whitfield',
            'status' => 'complete',
            'job_type' => 'junk_removal',
            'scheduled_start_at' => dt(-7, '09:00:00'),
            'scheduled_end_at' => dt(-7, '13:00:00'),
            'actual_start_at' => dt(-7, '09:05:00'),
            'actual_end_at' => dt(-7, '12:45:00'),
            'address_line1' => '12 Porter Rd',
            'city' => 'Cambridge',
            'state' => 'MA',
            'notes' => 'Garage cleanout with scrap metal set aside.',
        ], $actorUserId);
    }

    foreach ($employeeIds as $employeeId) {
        if ($employeeId <= 0) {
            continue;
        }
        foreach (array_slice(array_values($jobs), 0, 3) as $jobId) {
            if ($jobId <= 0) {
                continue;
            }
            assignEmployeeToJob($businessId, $jobId, $employeeId, $actorUserId);
        }
    }

    echo 'Jobs: ' . count($jobs) . "\n";
    return $jobs;
}

function assignEmployeeToJob(int $businessId, int $jobId, int $employeeId, int $actorUserId): void
{
    if (!tableExists(Database::connection(), 'job_employee_assignments')) {
        return;
    }
    $pdo = Database::connection();
    $exists = $pdo->prepare(
        'SELECT id FROM job_employee_assignments
         WHERE business_id = :business_id AND job_id = :job_id AND employee_id = :employee_id LIMIT 1'
    );
    $exists->execute(['business_id' => $businessId, 'job_id' => $jobId, 'employee_id' => $employeeId]);
    if ($exists->fetch()) {
        return;
    }
    $pdo->prepare(
        'INSERT INTO job_employee_assignments (business_id, job_id, employee_id, created_by, updated_by, created_at, updated_at)
         VALUES (:business_id, :job_id, :employee_id, :created_by, :updated_by, NOW(), NOW())'
    )->execute([
        'business_id' => $businessId,
        'job_id' => $jobId,
        'employee_id' => $employeeId,
        'created_by' => $actorUserId,
        'updated_by' => $actorUserId,
    ]);
}

/**
 * @param list<int> $clientIds
 * @param array<string, int> $jobIds
 */
function seedAppointments(int $businessId, int $actorUserId, array $clientIds, array $jobIds): void
{
    $c = static fn (int $index): int => $clientIds[$index] ?? $clientIds[0];
    $jobList = array_values($jobIds);

    $events = [
        ['title' => 'Estimate walkthrough — Shah condo', 'type' => 'appointment', 'start' => dt(2, '11:00:00'), 'end' => dt(2, '12:00:00'), 'link_type' => 'client', 'link_id' => $c(5), 'notes' => 'Meet building manager at side entrance.'],
        ['title' => 'Follow-up call — North End Realty', 'type' => 'reminder', 'start' => dt(1, '15:30:00'), 'end' => dt(1, '16:00:00'), 'link_type' => 'client', 'link_id' => $c(6), 'notes' => 'Confirm turnover date.'],
        ['title' => 'Estate preview — Bergeron', 'type' => 'appointment', 'start' => dt(4, '09:30:00'), 'end' => dt(4, '11:00:00'), 'link_type' => 'client', 'link_id' => $c(7), 'notes' => 'Bring donation checklist.'],
        ['title' => 'Site visit — Alvarez buildout', 'type' => 'appointment', 'start' => dt(2, '08:00:00'), 'end' => dt(2, '08:30:00'), 'link_type' => 'job', 'link_id' => $jobList[2] ?? 0, 'notes' => 'Active job kickoff.'],
        ['title' => 'Completed — Morales basement', 'type' => 'appointment', 'start' => dt(-14, '08:00:00'), 'end' => dt(-14, '12:00:00'), 'link_type' => 'job', 'link_id' => $jobList[0] ?? 0, 'notes' => 'Historical appointment.'],
    ];

    foreach ($events as $event) {
        if (($event['link_type'] ?? '') === 'job' && (int) ($event['link_id'] ?? 0) <= 0) {
            continue;
        }
        Event::create($businessId, [
            'title' => (string) $event['title'],
            'type' => (string) $event['type'],
            'status' => 'scheduled',
            'start_at' => (string) $event['start'],
            'end_at' => (string) $event['end'],
            'all_day' => '0',
            'notes' => (string) ($event['notes'] ?? ''),
            'link_type' => (string) ($event['link_type'] ?? ''),
            'link_id' => (int) ($event['link_id'] ?? 0),
        ], $actorUserId);
    }

    echo 'Appointments: ' . count($events) . "\n";
}

/**
 * @param list<int> $clientIds
 * @param array<string, int> $jobIds
 */
function seedBilling(int $businessId, int $actorUserId, array $clientIds, array $jobIds): void
{
    $jobList = array_values($jobIds);
    $completeJobA = $jobList[0] ?? 0;
    $completeJobB = $jobList[1] ?? 0;
    $activeJob = $jobList[2] ?? 0;
    $clientA = $clientIds[3] ?? $clientIds[0];
    $clientB = $clientIds[4] ?? $clientIds[0];
    $clientActive = $clientIds[11] ?? $clientIds[0];

    if ($completeJobA > 0) {
        $invoiceId = Invoice::create($businessId, [
            'client_id' => $clientA,
            'job_id' => $completeJobA,
            'type' => 'invoice',
            'status' => 'sent',
            'issue_date' => d(-13),
            'due_date' => d(-6),
            'subtotal' => 1450.00,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total' => 1450.00,
        ], $actorUserId);
        Invoice::replaceLineItems($businessId, $invoiceId, [
            ['description' => 'Basement cleanout labor', 'quantity' => 1, 'unit_price' => 1150.00, 'line_total' => 1150.00, 'taxable' => 0],
            ['description' => 'Disposal and donation haul', 'quantity' => 1, 'unit_price' => 300.00, 'line_total' => 300.00, 'taxable' => 0],
        ], $actorUserId);
        Invoice::createPayment($businessId, [
            'invoice_id' => $invoiceId,
            'amount' => 1450.00,
            'paid_at' => dt(-10, '14:20:00'),
            'method' => 'check',
            'note' => 'Paid in full',
        ], $actorUserId);
        Invoice::syncInvoicePaymentStatusesForJob($businessId, $completeJobA, $actorUserId);
    }

    if ($completeJobB > 0) {
        $invoiceId = Invoice::create($businessId, [
            'client_id' => $clientB,
            'job_id' => $completeJobB,
            'type' => 'invoice',
            'status' => 'sent',
            'issue_date' => d(-6),
            'due_date' => d(1),
            'subtotal' => 980.00,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total' => 980.00,
        ], $actorUserId);
        Invoice::replaceLineItems($businessId, $invoiceId, [
            ['description' => 'Garage cleanout', 'quantity' => 1, 'unit_price' => 980.00, 'line_total' => 980.00, 'taxable' => 0],
        ], $actorUserId);
        Invoice::createPayment($businessId, [
            'invoice_id' => $invoiceId,
            'amount' => 500.00,
            'paid_at' => dt(-3, '10:00:00'),
            'method' => 'card',
            'note' => 'Deposit received',
        ], $actorUserId);
        Invoice::syncInvoicePaymentStatusesForJob($businessId, $completeJobB, $actorUserId);
    }

    if ($activeJob > 0) {
        $invoiceId = Invoice::create($businessId, [
            'client_id' => $clientActive,
            'job_id' => $activeJob,
            'type' => 'estimate',
            'status' => 'sent',
            'issue_date' => d(-2),
            'due_date' => d(10),
            'subtotal' => 2200.00,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total' => 2200.00,
        ], $actorUserId);
        Invoice::replaceLineItems($businessId, $invoiceId, [
            ['description' => 'Buildout debris removal estimate', 'quantity' => 1, 'unit_price' => 2200.00, 'line_total' => 2200.00, 'taxable' => 0],
        ], $actorUserId);
    }

    // Service invoice paid this month for dashboard MTD.
    $serviceClient = $clientIds[2] ?? $clientIds[0];
    $serviceInvoiceId = Invoice::create($businessId, [
        'client_id' => $serviceClient,
        'job_id' => null,
        'type' => 'invoice',
        'status' => 'sent',
        'issue_date' => d(-4),
        'due_date' => d(10),
        'subtotal' => 850.00,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 850.00,
    ], $actorUserId);
    Invoice::replaceLineItems($businessId, $serviceInvoiceId, [
        ['description' => 'Monthly contract hauling', 'quantity' => 1, 'unit_price' => 850.00, 'line_total' => 850.00, 'taxable' => 0],
    ], $actorUserId);
    Invoice::createPayment($businessId, [
        'invoice_id' => $serviceInvoiceId,
        'amount' => 850.00,
        'paid_at' => dt(-2, '11:15:00'),
        'method' => 'ach',
    ], $actorUserId);

    echo "Billing: invoices + payments seeded\n";
}

/**
 * @param list<int> $clientIds
 * @param array<string, int> $jobIds
 */
function seedSales(int $businessId, int $actorUserId, array $clientIds, array $jobIds): void
{
    $jobList = array_values($jobIds);

    $sales = [
        ['name' => 'Scrap metal — Morales job', 'sale_type' => 'scrap', 'gross_amount' => 420.00, 'net_amount' => 420.00, 'sale_date' => d(-12), 'job_id' => $jobList[0] ?? null, 'client_id' => $clientIds[3] ?? null],
        ['name' => 'eBay lot — tools', 'sale_type' => 'ebay', 'gross_amount' => 285.00, 'net_amount' => 228.00, 'sale_date' => d(-5), 'job_id' => $jobList[1] ?? null, 'client_id' => $clientIds[4] ?? null],
        ['name' => 'Shop sale — appliances', 'sale_type' => 'shop', 'gross_amount' => 640.00, 'net_amount' => 640.00, 'sale_date' => d(-2), 'job_id' => null, 'client_id' => null],
        ['name' => 'B2B pallet pickup resale', 'sale_type' => 'b2b', 'gross_amount' => 1100.00, 'net_amount' => 980.00, 'sale_date' => d(-1), 'job_id' => null, 'client_id' => $clientIds[2] ?? null],
        ['name' => 'March storage purge metals', 'sale_type' => 'scrap', 'gross_amount' => 310.00, 'net_amount' => 310.00, 'sale_date' => d(-44), 'job_id' => $jobList[4] ?? null, 'client_id' => $clientIds[0] ?? null],
    ];

    foreach ($sales as $sale) {
        Sale::create($businessId, [
            'name' => (string) $sale['name'],
            'sale_type' => (string) $sale['sale_type'],
            'gross_amount' => (float) $sale['gross_amount'],
            'net_amount' => (float) $sale['net_amount'],
            'sale_date' => (string) $sale['sale_date'],
            'job_id' => $sale['job_id'],
            'client_id' => $sale['client_id'],
            'notes' => 'Demo seed sale.',
        ], $actorUserId);
    }

    echo 'Sales: ' . count($sales) . "\n";
}

/**
 * @param array<string, int> $jobIds
 */
function seedExpenses(int $businessId, int $actorUserId, array $jobIds): void
{
    $jobList = array_values($jobIds);

    Expense::create($businessId, [
        'name' => 'Transfer station — Morales job',
        'category' => 'disposal',
        'amount' => 185.00,
        'expense_date' => d(-13),
        'payment_method' => 'company_card',
        'note' => 'Job disposal fee',
    ], $actorUserId, $jobList[0] ?? null);

    Expense::create($businessId, [
        'name' => 'Fuel — Cambridge run',
        'category' => 'fuel',
        'amount' => 92.50,
        'expense_date' => d(-7),
        'payment_method' => 'company_card',
        'note' => 'Job fuel',
    ], $actorUserId, $jobList[1] ?? null);

    Expense::create($businessId, [
        'name' => 'Diesel — monthly fill',
        'category' => 'fuel',
        'amount' => 410.00,
        'expense_date' => d(-3),
        'payment_method' => 'company_card',
        'note' => 'General operating fuel',
    ], $actorUserId, null);

    Expense::create($businessId, [
        'name' => 'General liability insurance',
        'category' => 'insurance',
        'amount' => 625.00,
        'expense_date' => d(-8),
        'payment_method' => 'ach',
        'note' => 'Monthly premium',
    ], $actorUserId, null);

    Expense::create($businessId, [
        'name' => 'Google local ads',
        'category' => 'advertising',
        'amount' => 250.00,
        'expense_date' => d(-1),
        'payment_method' => 'company_card',
        'note' => 'Lead generation',
    ], $actorUserId, null);

    echo "Expenses: 5\n";
}

/**
 * @param list<int> $clientIds
 * @param array<string, int> $jobIds
 */
function seedPurchases(int $businessId, int $actorUserId, array $clientIds, array $jobIds): void
{
    $jobList = array_values($jobIds);
    Purchase::create($businessId, [
        'title' => 'Estate dining set — resale inventory',
        'status' => 'complete',
        'purchase_date' => d(-20),
        'purchase_price' => 350.00,
        'client_id' => $clientIds[7] ?? null,
        'job_id' => null,
        'notes' => 'Purchased during estate preview.',
    ], $actorUserId);

    Purchase::create($businessId, [
        'title' => 'Garage tools lot — Whitfield',
        'status' => 'active',
        'purchase_date' => d(-8),
        'purchase_price' => 120.00,
        'client_id' => $clientIds[4] ?? null,
        'notes' => 'Sorted for eBay listing.',
    ], $actorUserId);

    echo "Purchases: 2\n";
}

/**
 * @param list<int> $clientIds
 * @param array<string, int> $jobIds
 */
function seedTasks(int $businessId, int $actorUserId, array $clientIds, array $jobIds): void
{
    $jobList = array_values($jobIds);
    $tasks = [
        ['title' => 'Client Follow-Up', 'body' => 'Confirm JP duplex quote decision.', 'status' => 'open', 'due_at' => dt(2, '17:00:00'), 'link_type' => 'client', 'link_id' => $clientIds[10] ?? 0, 'priority' => 2, 'owner_user_id' => $actorUserId],
        ['title' => 'Send COI to building manager', 'body' => 'Shah condo association needs certificate before walkthrough.', 'status' => 'open', 'due_at' => dt(1, '12:00:00'), 'link_type' => 'client', 'link_id' => $clientIds[5] ?? 0, 'priority' => 1, 'owner_user_id' => $actorUserId],
        ['title' => 'Collect final payment — Whitfield', 'body' => 'Balance remaining on garage job invoice.', 'status' => 'open', 'due_at' => dt(3, '16:00:00'), 'link_type' => 'job', 'link_id' => $jobList[1] ?? 0, 'priority' => 2, 'owner_user_id' => $actorUserId],
        ['title' => 'Prep donation receipt', 'body' => 'Morales basement donation documentation.', 'status' => 'closed', 'due_at' => dt(-11, '15:00:00'), 'link_type' => 'job', 'link_id' => $jobList[0] ?? 0, 'priority' => 3, 'owner_user_id' => $actorUserId],
    ];

    foreach ($tasks as $task) {
        if (($task['link_type'] ?? '') !== '' && (int) ($task['link_id'] ?? 0) <= 0) {
            continue;
        }
        Task::create($businessId, $task, $actorUserId);
    }

    echo 'Tasks: ' . count($tasks) . "\n";
}

/**
 * @param array<string, int> $jobIds
 * @param list<int> $employeeIds
 */
function seedLabor(int $businessId, int $actorUserId, array $jobIds, array $employeeIds): void
{
    $jobList = array_values($jobIds);
    if ($jobList === [] || $employeeIds === []) {
        return;
    }

    $entries = [
        ['job_id' => $jobList[0] ?? 0, 'employee_id' => $employeeIds[0], 'clock_in_at' => dt(-14, '08:10:00'), 'clock_out_at' => dt(-14, '11:50:00')],
        ['job_id' => $jobList[1] ?? 0, 'employee_id' => $employeeIds[1], 'clock_in_at' => dt(-7, '09:05:00'), 'clock_out_at' => dt(-7, '12:45:00')],
        ['job_id' => $jobList[0] ?? 0, 'employee_id' => $employeeIds[2], 'clock_in_at' => dt(-14, '08:10:00'), 'clock_out_at' => dt(-14, '11:50:00')],
    ];

    foreach ($entries as $entry) {
        if ((int) $entry['job_id'] <= 0 || (int) $entry['employee_id'] <= 0) {
            continue;
        }
        TimeEntry::create($businessId, [
            'employee_id' => (int) $entry['employee_id'],
            'job_id' => (int) $entry['job_id'],
            'clock_in_at' => (string) $entry['clock_in_at'],
            'clock_out_at' => (string) $entry['clock_out_at'],
            'notes' => 'Demo seed time entry',
        ], $actorUserId);
    }

    echo 'Time entries: ' . count($entries) . "\n";
}

function printCounts(PDO $pdo, int $businessId): void
{
    $tables = ['clients', 'employees', 'quotes', 'jobs', 'events', 'invoices', 'payments', 'sales', 'expenses', 'purchases', 'tasks', 'employee_time_entries'];
    foreach ($tables as $table) {
        if (!tableExists($pdo, $table) || !columnExists($pdo, $table, 'business_id')) {
            continue;
        }
        $count = (int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE business_id = {$businessId}")->fetchColumn();
        echo str_pad($table . ':', 24) . $count . "\n";
    }
}
