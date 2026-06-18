<?php

declare(strict_types=1);

/**
 * Offline stress test for Google Calendar payload address resolution.
 * Does not call Google — only builds payloads via GoogleCalendarSync::previewPayload().
 *
 * Usage:
 *   HTTP_HOST=localhost php scripts/verify-google-calendar-addresses.php [business_id] [days]
 */

$root = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require $root . '/app/bootstrap.php';

use App\Models\ClientDelivery;
use App\Models\EstateSale;
use App\Models\Event;
use App\Models\EventFeed;
use App\Models\Job;
use App\Models\PurchaseQuote;
use App\Models\Quote;
use App\Models\Task;
use App\Services\GoogleCalendarSync;

$businessId = max(1, (int) ($argv[1] ?? 1));
$days = max(1, min(730, (int) ($argv[2] ?? 365)));

$start = date('Y-m-d 00:00:00', strtotime('-' . (string) (int) floor($days / 2) . ' days'));
$end = date('Y-m-d 23:59:59', strtotime('+' . (string) (int) ceil($days / 2) . ' days'));

echo "Google Calendar address payload verification\n";
echo "Business #{$businessId}, range {$start} → {$end}\n";
echo str_repeat('=', 88) . "\n";

$refs = EventFeed::googleSyncItemRefs($businessId, $start, $end);
if ($refs === []) {
    echo "No sync-eligible calendar items in range.\n";
    exit(0);
}

$byType = [];
foreach ($refs as $ref) {
    $type = strtolower(trim((string) ($ref['source_type'] ?? '')));
    $id = (int) ($ref['source_id'] ?? 0);
    if ($type === '' || $id <= 0) {
        continue;
    }
    $byType[$type][] = $id;
}

$loadRecord = static function (string $sourceType, int $sourceId) use ($businessId): ?array {
    return match ($sourceType) {
        'event' => Event::findForBusiness($businessId, $sourceId),
        'job' => Job::findForBusiness($businessId, $sourceId),
        'task' => Task::findForBusiness($businessId, $sourceId),
        'delivery' => ClientDelivery::findForBusiness($businessId, $sourceId),
        'quote' => Quote::findForBusiness($businessId, $sourceId),
        'purchase_quote' => PurchaseQuote::findForBusiness($businessId, $sourceId),
        'estate_sale' => EstateSale::findForBusiness($businessId, $sourceId),
        default => null,
    };
};

$totals = [
    'checked' => 0,
    'with_location' => 0,
    'missing_location' => 0,
    'errors' => 0,
];

foreach ($byType as $sourceType => $ids) {
    $uniqueIds = array_values(array_unique($ids));
    sort($uniqueIds, SORT_NUMERIC);

    $typeChecked = 0;
    $typeWith = 0;
    $typeMissing = 0;
    $missingSamples = [];

    foreach ($uniqueIds as $sourceId) {
        $record = $loadRecord($sourceType, $sourceId);
        if ($record === null) {
            $totals['errors']++;
            continue;
        }

        try {
            $payload = GoogleCalendarSync::previewPayload($sourceType, $record, $businessId);
        } catch (\Throwable $e) {
            $totals['errors']++;
            echo "[ERROR] {$sourceType} #{$sourceId}: {$e->getMessage()}\n";
            continue;
        }

        $typeChecked++;
        $totals['checked']++;
        $location = trim((string) ($payload['location'] ?? ''));
        $expectedMissing = expectsNoAddress($sourceType, $record);
        if ($location !== '') {
            $typeWith++;
            $totals['with_location']++;
        } elseif ($expectedMissing) {
            $typeWith++;
            $totals['with_location']++;
        } else {
            $typeMissing++;
            $totals['missing_location']++;
            if (count($missingSamples) < 8) {
                $missingSamples[] = describeMissingAddress($sourceType, $record);
            }
        }
    }

    $pct = $typeChecked > 0 ? round(100 * $typeWith / $typeChecked, 1) : 0.0;
    printf(
        "%-16s checked=%4d  with_location=%4d  missing=%4d  (%s%%)\n",
        $sourceType,
        $typeChecked,
        $typeWith,
        $typeMissing,
        number_format($pct, 1)
    );

    foreach ($missingSamples as $sample) {
        echo "  - {$sample}\n";
    }
    if ($typeMissing > count($missingSamples)) {
        echo '  - ... and ' . (string) ($typeMissing - count($missingSamples)) . " more\n";
    }
}

echo str_repeat('-', 88) . "\n";
$overallPct = $totals['checked'] > 0
    ? round(100 * $totals['with_location'] / $totals['checked'], 1)
    : 0.0;
printf(
    "TOTAL checked=%d  with_location=%d  missing=%d  errors=%d  (%s%% with address)\n",
    $totals['checked'],
    $totals['with_location'],
    $totals['missing_location'],
    $totals['errors'],
    number_format($overallPct, 1)
);

exit($totals['missing_location'] > 0 ? 2 : 0);

/**
 * @param array<string, mixed> $record
 */
function expectsNoAddress(string $sourceType, array $record): bool
{
    if ($sourceType === 'event') {
        $type = strtolower(trim((string) ($record['type'] ?? '')));
        if (in_array($type, ['personal', 'reminder', 'note'], true)) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<string, mixed> $record
 */
function describeMissingAddress(string $sourceType, array $record): string
{
    $id = (int) ($record['id'] ?? 0);
    $parts = ["{$sourceType} #{$id}"];

    if ($sourceType === 'event' || $sourceType === 'task') {
        $linkType = trim((string) ($record['link_type'] ?? ''));
        $linkId = (int) ($record['link_id'] ?? 0);
        if ($linkType !== '' && $linkId > 0) {
            $parts[] = "link={$linkType}:{$linkId}";
        } else {
            $parts[] = 'no link';
        }
    }

    $clientId = (int) ($record['client_id'] ?? 0);
    if ($clientId > 0) {
        $parts[] = "client_id={$clientId}";
    }

    $hasRecordAddress = trim((string) ($record['address_line1'] ?? '')) !== ''
        || trim((string) ($record['city'] ?? '')) !== '';
    $parts[] = $hasRecordAddress ? 'record address empty after format' : 'no record address';

    return implode(', ', $parts);
}
