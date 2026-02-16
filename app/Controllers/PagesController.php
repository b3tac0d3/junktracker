<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;

final class PagesController extends Controller
{
    public function charts(): void
    {
        $pageScripts = implode("\n", [
            '<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('demo/chart-area-demo.js') . '"></script>',
            '<script src="' . asset('demo/chart-bar-demo.js') . '"></script>',
            '<script src="' . asset('demo/chart-pie-demo.js') . '"></script>',
        ]);

        $this->render('pages/charts', [
            'pageTitle' => 'Charts',
            'pageScripts' => $pageScripts,
        ]);
    }

    public function tables(): void
    {
        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/datatables-simple-demo.js') . '"></script>',
        ]);

        $this->render('pages/tables', [
            'pageTitle' => 'Tables',
            'pageScripts' => $pageScripts,
        ]);
    }
}
