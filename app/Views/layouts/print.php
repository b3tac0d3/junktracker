<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title><?= e((string) ($pageTitle ?? 'Print')) ?> - JunkTracker</title>
        <style>
            :root {
                color-scheme: light;
            }

            body {
                margin: 0;
                padding: 24px;
                font-family: "Segoe UI", Tahoma, sans-serif;
                background: #f7f9fc;
                color: #1e293b;
            }

            .sheet {
                max-width: 960px;
                margin: 0 auto;
                background: #fff;
                border: 1px solid #dbe4f0;
                border-radius: 12px;
                box-shadow: 0 8px 20px rgba(16, 24, 40, 0.08);
                overflow: hidden;
            }

            .sheet-header {
                background: linear-gradient(135deg, #0f3d74 0%, #1d6fd6 100%);
                color: #fff;
                padding: 22px 24px;
            }

            .sheet-content {
                padding: 24px;
            }

            .print-actions {
                max-width: 960px;
                margin: 0 auto 16px;
                display: flex;
                justify-content: flex-end;
                gap: 8px;
            }

            .print-actions button,
            .print-actions a {
                border: 1px solid #c8d4e6;
                background: #fff;
                color: #1e3a5f;
                border-radius: 8px;
                padding: 8px 12px;
                text-decoration: none;
                font: inherit;
                cursor: pointer;
            }

            @media print {
                body {
                    background: #fff;
                    padding: 0;
                }

                .print-actions {
                    display: none;
                }

                .sheet {
                    border: 0;
                    border-radius: 0;
                    box-shadow: none;
                    max-width: none;
                }
            }
        </style>
        <?= $pageStyles ?? '' ?>
    </head>
    <body>
        <div class="print-actions">
            <button type="button" onclick="window.print()">Print / Save PDF</button>
            <a href="javascript:history.back()">Back</a>
        </div>

        <div class="sheet">
            <?= $content ?>
        </div>

        <?= $pageScripts ?? '' ?>
    </body>
</html>
