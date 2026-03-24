<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Invoice;
use App\Models\SchemaInspector;
use Core\Controller;
use Core\Database;

final class AdminExportController extends Controller
{
    public function csv(): void
    {
        require_business_role(['admin']);

        $businessId = current_business_id();

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="junktracker-export-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        if ($out === false) {
            return;
        }

        fputcsv($out, ['section', 'id', 'name_or_number', 'email_or_status', 'amount', 'extra']);

        if (SchemaInspector::hasTable('clients')) {
            $stmt = Database::connection()->prepare(
                'SELECT c.id,
                        COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'), NULLIF(c.company_name, \'\'), CONCAT(\'Client #\', c.id)) AS name,
                        COALESCE(c.email, \'\') AS email
                 FROM clients c
                 WHERE c.business_id = :business_id AND c.deleted_at IS NULL
                 ORDER BY c.id ASC
                 LIMIT 5000'
            );
            $stmt->execute(['business_id' => $businessId]);
            foreach ($stmt->fetchAll() as $row) {
                if (!is_array($row)) {
                    continue;
                }
                fputcsv($out, [
                    'client',
                    (string) ($row['id'] ?? ''),
                    (string) ($row['name'] ?? ''),
                    (string) ($row['email'] ?? ''),
                    '',
                    '',
                ]);
            }
        }

        if (SchemaInspector::hasTable('invoices')) {
            $stmt = Database::connection()->prepare(
                'SELECT i.id, i.invoice_number, i.status, i.type, i.total, i.due_date
                 FROM invoices i
                 WHERE i.business_id = :business_id
                   AND i.deleted_at IS NULL
                   AND LOWER(COALESCE(i.type, \'invoice\')) = \'invoice\'
                   AND LOWER(COALESCE(i.status, \'\')) NOT IN (\'paid_in_full\', \'cancelled\')
                 ORDER BY i.id DESC
                 LIMIT 2000'
            );
            $stmt->execute(['business_id' => $businessId]);
            foreach ($stmt->fetchAll() as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $id = (int) ($row['id'] ?? 0);
                $bal = $id > 0 ? Invoice::remainingBalanceForInvoice($businessId, $id) : 0.0;
                if ($bal <= 0.009) {
                    continue;
                }
                fputcsv($out, [
                    'open_invoice',
                    (string) $id,
                    (string) ($row['invoice_number'] ?? ''),
                    (string) ($row['status'] ?? ''),
                    number_format($bal, 2, '.', ''),
                    (string) ($row['due_date'] ?? ''),
                ]);
            }
        }

        fclose($out);
    }
}
