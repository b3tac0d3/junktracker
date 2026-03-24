<?php
declare(strict_types=1);
$pageTitle = isset($pageTitle) ? (string) $pageTitle : 'Document';
$viewFile = isset($viewFile) ? (string) $viewFile : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= e($pageTitle) ?> - JunkTracker</title>
    <link href="<?= e(asset('css/jt-theme.css')) ?>" rel="stylesheet" />
    <style>
        /* Scoped print / PDF document — modern, minimal */
        body.jt-doc-shell {
            margin: 0;
            padding: 1.5rem 1.25rem 2rem;
            background: #f1f5f9;
            color: #0f172a;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 15px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }
        .jt-doc-wrap {
            max-width: 920px;
            margin: 0 auto;
            background: #fff;
            padding: 2rem 2rem 2.25rem;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
        }
        .jt-doc-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1.75rem 2rem;
            padding-bottom: 1.75rem;
            margin-bottom: 1.75rem;
            border-bottom: 1px solid #e8ecf1;
        }
        .jt-doc-header__left {
            flex: 1 1 280px;
            min-width: 0;
        }
        .jt-doc-header__brand {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }
        .jt-doc-logo {
            max-height: 72px;
            max-width: 200px;
            width: auto;
            height: auto;
            object-fit: contain;
            flex-shrink: 0;
        }
        .jt-doc-company-name {
            font-size: 1.125rem;
            font-weight: 650;
            letter-spacing: -0.02em;
            color: #0f172a;
            margin: 0 0 0.35rem;
            line-height: 1.25;
        }
        .jt-doc-company-meta {
            font-size: 0.8125rem;
            color: #64748b;
            line-height: 1.45;
        }
        .jt-doc-company-meta p {
            margin: 0 0 0.2rem;
        }
        .jt-doc-header__right {
            flex: 0 1 280px;
            text-align: right;
        }
        .jt-doc-kind {
            display: inline-block;
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 0.35rem;
        }
        .jt-doc-number {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            color: #0f172a;
            line-height: 1.2;
            margin-bottom: 0.75rem;
        }
        .jt-doc-meta-grid {
            display: grid;
            gap: 0.35rem 1rem;
            justify-items: end;
            font-size: 0.8125rem;
        }
        .jt-doc-meta-row {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        .jt-doc-meta-row dt {
            margin: 0;
            color: #94a3b8;
            font-weight: 500;
        }
        .jt-doc-meta-row dd {
            margin: 0;
            color: #334155;
            font-weight: 600;
        }
        .jt-doc-parties {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem 2.5rem;
            margin-bottom: 2rem;
        }
        @media (max-width: 640px) {
            .jt-doc-parties {
                grid-template-columns: 1fr;
            }
            .jt-doc-header__right {
                text-align: left;
            }
            .jt-doc-meta-grid {
                justify-items: start;
            }
            .jt-doc-meta-row {
                justify-content: flex-start;
            }
        }
        .jt-doc-party h3 {
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #94a3b8;
            margin: 0 0 0.5rem;
        }
        .jt-doc-party .jt-doc-party-title {
            font-size: 1rem;
            font-weight: 650;
            color: #0f172a;
            margin: 0 0 0.35rem;
            line-height: 1.35;
        }
        .jt-doc-party .jt-doc-party-lines {
            font-size: 0.875rem;
            color: #475569;
            line-height: 1.5;
        }
        .jt-doc-party .jt-doc-party-lines p {
            margin: 0 0 0.2rem;
        }
        .jt-doc-lines {
            margin-bottom: 1.75rem;
        }
        .jt-doc-table-wrap {
            width: 100%;
            overflow-x: auto;
            margin: 0 -0.25rem;
            padding: 0 0.25rem;
        }
        table.jt-doc-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
            background: transparent;
        }
        table.jt-doc-table thead th {
            text-align: left;
            font-weight: 600;
            font-size: 0.6875rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #64748b;
            padding: 0.65rem 0.75rem 0.5rem;
            border: none;
            border-bottom: 1px solid #e2e8f0;
            background: transparent;
        }
        table.jt-doc-table thead th.text-end {
            text-align: right;
        }
        table.jt-doc-table thead th.text-center {
            text-align: center;
        }
        table.jt-doc-table tbody td {
            padding: 0.65rem 0.75rem;
            border: none;
            color: #334155;
            vertical-align: top;
        }
        table.jt-doc-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }
        table.jt-doc-table tbody tr:nth-child(odd) {
            background: transparent;
        }
        table.jt-doc-table .text-end {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        table.jt-doc-table .text-center {
            text-align: center;
        }
        table.jt-doc-table td.jt-doc-empty {
            font-style: italic;
            padding: 1rem 0.75rem;
            color: #94a3b8;
        }
        .jt-doc-totals {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
            padding-top: 1rem;
            margin-top: 0.5rem;
            border-top: 1px solid #e8ecf1;
        }
        .jt-doc-totals-row {
            display: flex;
            justify-content: flex-end;
            gap: 2rem;
            min-width: min(100%, 320px);
            font-size: 0.875rem;
        }
        .jt-doc-totals-row dt {
            margin: 0;
            color: #64748b;
            font-weight: 500;
            flex: 0 0 auto;
        }
        .jt-doc-totals-row dd {
            margin: 0;
            font-weight: 600;
            color: #0f172a;
            font-variant-numeric: tabular-nums;
            text-align: right;
            min-width: 6.5rem;
        }
        .jt-doc-totals-row--grand dd {
            font-size: 1.0625rem;
            font-weight: 700;
        }
        .jt-doc-totals-sub {
            font-size: 0.75rem;
            color: #94a3b8;
            font-weight: 400;
            margin-top: 0.15rem;
        }
        .jt-doc-notes {
            margin-top: 1.75rem;
            padding-top: 1.25rem;
            border-top: 1px solid #f1f5f9;
        }
        .jt-doc-notes h3 {
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #94a3b8;
            margin: 0 0 0.5rem;
        }
        .jt-doc-notes .jt-doc-notes-body {
            font-size: 0.875rem;
            color: #475569;
            line-height: 1.55;
            white-space: pre-wrap;
        }
        .jt-doc-payments {
            margin-top: 1.75rem;
            padding-top: 1.25rem;
            border-top: 1px solid #f1f5f9;
        }
        .jt-doc-payments h3 {
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #94a3b8;
            margin: 0 0 0.65rem;
        }
        .jt-doc-payments ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .jt-doc-payments li {
            font-size: 0.875rem;
            color: #475569;
            padding: 0.35rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .jt-doc-payments li:last-child {
            border-bottom: none;
        }
        @media print {
            body.jt-doc-shell {
                background: #fff;
                padding: 0;
            }
            .jt-doc-wrap {
                max-width: none;
                box-shadow: none;
                padding: 0.5rem 0 0;
            }
            .jt-doc-header {
                border-bottom-color: #ddd;
            }
            table.jt-doc-table tbody tr {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .jt-no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body class="jt-doc-shell">
<?php if ($success = flash('success')): ?><div class="alert alert-success jt-no-print"><?= e($success) ?></div><?php endif; ?>
<?php if ($error = flash('error')): ?><div class="alert alert-danger jt-no-print"><?= e($error) ?></div><?php endif; ?>
<?php
if ($viewFile !== '' && is_file($viewFile)) {
    require $viewFile;
}
?>
</body>
</html>
