<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BusinessMembership;
use App\Models\Digest;
use Core\Controller;
use Core\Database;
use Core\Mailer;

final class CronController extends Controller
{
    public function dailyDigest(): void
    {
        $key = trim((string) ($_GET['key'] ?? ''));
        $expected = trim((string) config('app.cron_key', ''));
        if ($expected === '' || !hash_equals($expected, $key)) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->query(
            'SELECT id FROM businesses WHERE deleted_at IS NULL AND COALESCE(is_active, 1) = 1 ORDER BY id ASC'
        );
        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            $rows = [];
        }

        $sent = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $bid = (int) ($row['id'] ?? 0);
            if ($bid <= 0) {
                continue;
            }
            $emails = BusinessMembership::digestEmailsForBusiness($bid);
            if ($emails === []) {
                continue;
            }
            $body = Digest::buildText($bid);
            $subject = (string) config('mail.daily_digest_subject', 'JunkTracker daily digest');
            foreach ($emails as $to) {
                if (Mailer::send($to, $subject, $body)) {
                    $sent++;
                }
            }
        }

        header('Content-Type: text/plain; charset=UTF-8');
        echo 'OK. Messages attempted: ' . (string) $sent;
    }
}
