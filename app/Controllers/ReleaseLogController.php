<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ReleaseLog;
use Core\Controller;

final class ReleaseLogController extends Controller
{
    public function index(): void
    {
        require_auth();

        $releases = ReleaseLog::recent(10);
        $currentVersion = (string) config('app.version', '');

        $this->render('releases/index', [
            'pageTitle' => 'Release History',
            'releases' => $releases,
            'currentVersion' => $currentVersion,
        ]);
    }

    public function show(array $params): void
    {
        require_auth();

        $version = trim((string) ($params['version'] ?? ''));
        $release = ReleaseLog::findByVersion($version);
        if ($release === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('releases/show', [
            'pageTitle' => 'Release ' . (string) ($release['version'] ?? ''),
            'release' => $release,
        ]);
    }
}
