<?php

declare(strict_types=1);

/**
 * Seed sample dev tracker bugs/updates across statuses for testing review workflow.
 *
 * Usage:
 *   HTTP_HOST=localhost php scripts/seed-dev-tracker-samples.php
 *   HTTP_HOST=localhost php scripts/seed-dev-tracker-samples.php --fresh
 */

$root = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require $root . '/app/bootstrap.php';

use App\Models\DevTrackerItem;
use App\Models\DevTrackerLog;
use Core\Database;

const SAMPLE_PREFIX = '[SAMPLE]';

$args = array_slice($argv, 1);
$fresh = in_array('--fresh', $args, true);
$pdo = Database::connection();

echo "Dev tracker sample seed\n";

if (!DevTrackerItem::hasSubmissionColumns()) {
    fwrite(STDERR, "Submission columns missing. Run database/migrations/2026-06-14_dev_tracker_submissions_log.sql first.\n");
    exit(1);
}

if (!DevTrackerLog::isAvailable()) {
    fwrite(STDERR, "dev_tracker_log_entries table missing. Run the submission migration first.\n");
    exit(1);
}

$siteAdminId = resolveActorUserId($pdo);
echo "Site admin actor: {$siteAdminId}\n";

if ($fresh) {
    echo "Removing previous sample items...\n";
    wipeSampleItems($pdo);
}

$businesses = fetchBusinesses($pdo, 3);
if ($businesses === []) {
    fwrite(STDERR, "No active businesses found.\n");
    exit(1);
}

$seeded = 0;

$seeded += seedSubmission($pdo, [
    'item_type' => 'bug',
    'title' => SAMPLE_PREFIX . ' Invoice PDF cuts off footer on mobile',
    'notes' => 'When printing or saving invoice PDFs from a phone, the footer totals are clipped.',
    'status' => 'pending_review',
    'review_status' => 'pending',
    'priority' => 'high',
    'area' => 'Billing',
    'business' => $businesses[0] ?? null,
    'log_body' => 'Reported from billing screen on iPhone. Happens on every invoice over one page.',
]);

$seeded += seedSubmission($pdo, [
    'item_type' => 'bug',
    'title' => SAMPLE_PREFIX . ' Dispatch board loads blank after refresh',
    'notes' => 'Hard refresh on the dispatch board shows a blank grid until logout/login.',
    'status' => 'pending_review',
    'review_status' => 'pending',
    'priority' => 'normal',
    'area' => 'Operations',
    'business' => $businesses[1] ?? $businesses[0] ?? null,
    'log_body' => 'Reproduced in Chrome on Windows after morning login.',
]);

$seeded += seedSubmission($pdo, [
    'item_type' => 'update',
    'title' => SAMPLE_PREFIX . ' Export completed jobs to CSV',
    'notes' => 'Need a bulk export of completed jobs with customer, date, and revenue columns.',
    'status' => 'pending_review',
    'review_status' => 'pending',
    'priority' => 'normal',
    'area' => 'Jobs',
    'business' => $businesses[2] ?? $businesses[0] ?? null,
    'log_body' => 'Requested for month-end reporting workflow.',
]);

$seeded += seedSubmission($pdo, [
    'item_type' => 'bug',
    'title' => SAMPLE_PREFIX . ' Duplicate client records after merge',
    'notes' => 'Merging two clients sometimes leaves duplicate phone numbers on the surviving record.',
    'status' => 'triage',
    'review_status' => 'accepted',
    'priority' => 'high',
    'area' => 'Clients',
    'business' => $businesses[0] ?? null,
    'log_body' => 'Accepted for triage after reproducing with two test clients.',
    'extra_logs' => [
        ['entry_type' => 'accepted', 'body' => 'Accepted and queued for triage.', 'status_from' => 'pending_review', 'status_to' => 'triage'],
    ],
]);

$seeded += seedDevItem($pdo, $siteAdminId, [
    'item_type' => 'bug',
    'title' => SAMPLE_PREFIX . ' Slow search on large quote lists',
    'notes' => 'Quote search takes 4-5 seconds once a company has 500+ quotes.',
    'status' => 'in_progress',
    'priority' => 'normal',
    'area' => 'Quotes',
]);

$seeded += seedDevItem($pdo, $siteAdminId, [
    'item_type' => 'bug',
    'title' => SAMPLE_PREFIX . ' Calendar drag-and-drop off by one hour',
    'notes' => 'Moving events in week view shifts them one hour earlier after save.',
    'status' => 'testing',
    'priority' => 'urgent',
    'area' => 'Calendar',
]);

echo "\nDone. Seeded {$seeded} dev tracker sample item(s).\n";
echo "Review pending items at /dev?status=pending_review or on the Platform dashboard.\n";

function resolveActorUserId(PDO $pdo): int
{
    $row = $pdo->query("SELECT id FROM users WHERE role = 'site_admin' ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (is_array($row) && (int) ($row['id'] ?? 0) > 0) {
        return (int) $row['id'];
    }

    $row = $pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? max(1, (int) ($row['id'] ?? 1)) : 1;
}

/**
 * @return list<array{id: int, name: string, submitter_id: int}>
 */
function fetchBusinesses(PDO $pdo, int $limit): array
{
    $stmt = $pdo->prepare(
        "SELECT b.id, b.name
         FROM businesses b
         WHERE b.deleted_at IS NULL
           AND COALESCE(b.is_active, 1) = 1
         ORDER BY b.id ASC
         LIMIT :row_limit"
    );
    $stmt->bindValue(':row_limit', max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($rows)) {
        return [];
    }

    $businesses = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $businessId = (int) ($row['id'] ?? 0);
        if ($businessId <= 0) {
            continue;
        }
        $businesses[] = [
            'id' => $businessId,
            'name' => trim((string) ($row['name'] ?? '')),
            'submitter_id' => resolveBusinessSubmitter($pdo, $businessId),
        ];
    }

    return $businesses;
}

function resolveBusinessSubmitter(PDO $pdo, int $businessId): int
{
    $stmt = $pdo->prepare(
        "SELECT u.id
         FROM users u
         INNER JOIN business_user_memberships m
            ON m.user_id = u.id
           AND m.business_id = :business_id
           AND m.deleted_at IS NULL
           AND COALESCE(m.is_active, 1) = 1
         WHERE u.deleted_at IS NULL
           AND COALESCE(u.is_active, 1) = 1
           AND u.role <> 'site_admin'
         ORDER BY m.id ASC
         LIMIT 1"
    );
    $stmt->execute(['business_id' => $businessId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($row) && (int) ($row['id'] ?? 0) > 0) {
        return (int) $row['id'];
    }

    return resolveActorUserId($pdo);
}

function wipeSampleItems(PDO $pdo): void
{
    $stmt = $pdo->prepare(
        "SELECT id
         FROM dev_tracker_items
         WHERE deleted_at IS NULL
           AND title LIKE :sample_prefix"
    );
    $stmt->execute(['sample_prefix' => SAMPLE_PREFIX . '%']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($rows) || $rows === []) {
        return;
    }

    $ids = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $itemId = (int) ($row['id'] ?? 0);
        if ($itemId > 0) {
            $ids[] = $itemId;
        }
    }

    if ($ids === []) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("DELETE FROM dev_tracker_log_entries WHERE dev_tracker_item_id IN ({$placeholders})")->execute($ids);
    $pdo->prepare("DELETE FROM dev_tracker_items WHERE id IN ({$placeholders})")->execute($ids);
}

/**
 * @param array<string, mixed> $business
 * @param array<string, mixed> $data
 */
function seedSubmission(PDO $pdo, array $data): int
{
    $business = is_array($data['business'] ?? null) ? $data['business'] : null;
    if ($business === null) {
        echo "Skipped submission (no business): " . (string) ($data['title'] ?? '') . "\n";
        return 0;
    }

    $businessId = (int) ($business['id'] ?? 0);
    $submitterId = (int) ($business['submitter_id'] ?? 0);
    if ($businessId <= 0) {
        return 0;
    }

    if (sampleExists($pdo, (string) ($data['title'] ?? ''))) {
        echo 'Already exists: ' . (string) ($data['title'] ?? '') . "\n";
        return 0;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO dev_tracker_items (
            item_type, title, notes, status, priority, area,
            business_id, review_status, submitted_by,
            created_by, updated_by, created_at, updated_at
         ) VALUES (
            :item_type, :title, :notes, :status, :priority, :area,
            :business_id, :review_status, :submitted_by,
            :created_by, :updated_by, NOW(), NOW()
         )'
    );
    $stmt->execute([
        'item_type' => (string) ($data['item_type'] ?? 'bug'),
        'title' => (string) ($data['title'] ?? ''),
        'notes' => (string) ($data['notes'] ?? ''),
        'status' => (string) ($data['status'] ?? 'pending_review'),
        'priority' => (string) ($data['priority'] ?? 'normal'),
        'area' => (string) ($data['area'] ?? ''),
        'business_id' => $businessId,
        'review_status' => (string) ($data['review_status'] ?? 'pending'),
        'submitted_by' => $submitterId > 0 ? $submitterId : null,
        'created_by' => $submitterId > 0 ? $submitterId : null,
        'updated_by' => $submitterId > 0 ? $submitterId : null,
    ]);
    $itemId = (int) $pdo->lastInsertId();

    DevTrackerLog::append($itemId, 'created', [
        'body' => (string) ($data['log_body'] ?? $data['notes'] ?? $data['title'] ?? ''),
    ], $submitterId);

    $extraLogs = is_array($data['extra_logs'] ?? null) ? $data['extra_logs'] : [];
    foreach ($extraLogs as $logRow) {
        if (!is_array($logRow)) {
            continue;
        }
        DevTrackerLog::append($itemId, (string) ($logRow['entry_type'] ?? 'comment'), [
            'body' => (string) ($logRow['body'] ?? ''),
            'status_from' => (string) ($logRow['status_from'] ?? ''),
            'status_to' => (string) ($logRow['status_to'] ?? ''),
        ], resolveActorUserId($pdo));
    }

    echo 'Seeded submission #' . (string) $itemId . ': ' . (string) ($data['title'] ?? '') . ' [' . (string) ($data['status'] ?? '') . "]\n";

    return 1;
}

/**
 * @param array<string, mixed> $data
 */
function seedDevItem(PDO $pdo, int $actorUserId, array $data): int
{
    if (sampleExists($pdo, (string) ($data['title'] ?? ''))) {
        echo 'Already exists: ' . (string) ($data['title'] ?? '') . "\n";
        return 0;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO dev_tracker_items (
            item_type, title, notes, status, priority, area,
            created_by, updated_by, created_at, updated_at
         ) VALUES (
            :item_type, :title, :notes, :status, :priority, :area,
            :created_by, :updated_by, NOW(), NOW()
         )'
    );
    $stmt->execute([
        'item_type' => (string) ($data['item_type'] ?? 'bug'),
        'title' => (string) ($data['title'] ?? ''),
        'notes' => (string) ($data['notes'] ?? ''),
        'status' => (string) ($data['status'] ?? 'backlog'),
        'priority' => (string) ($data['priority'] ?? 'normal'),
        'area' => (string) ($data['area'] ?? ''),
        'created_by' => $actorUserId,
        'updated_by' => $actorUserId,
    ]);
    $itemId = (int) $pdo->lastInsertId();

    echo 'Seeded dev item #' . (string) $itemId . ': ' . (string) ($data['title'] ?? '') . ' [' . (string) ($data['status'] ?? '') . "]\n";

    return 1;
}

function sampleExists(PDO $pdo, string $title): bool
{
    if ($title === '') {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM dev_tracker_items
         WHERE deleted_at IS NULL
           AND title = :title
         LIMIT 1'
    );
    $stmt->execute(['title' => $title]);

    return (bool) $stmt->fetchColumn();
}
