<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\EventFeed;
use Core\ApiController;

final class EventsController extends ApiController
{
    public function feed(): void
    {
        $this->requireBusinessRole(['punch_only', 'general_user', 'admin']);

        $businessId = current_business_id();
        $start = isset($_GET['start']) ? (string) $_GET['start'] : date('Y-m-d');
        $end = isset($_GET['end']) ? (string) $_GET['end'] : date('Y-m-d', strtotime('+14 days'));
        $sourcesRaw = isset($_GET['sources']) ? (string) $_GET['sources'] : '';
        $typesRaw = isset($_GET['types']) ? (string) $_GET['types'] : '';
        $q = isset($_GET['q']) ? (string) $_GET['q'] : '';

        $events = EventFeed::range($businessId, $start, $end, [
            'sources' => $sourcesRaw,
            'types' => $typesRaw,
            'q' => $q,
        ]);

        $this->ok(['events' => $events]);
    }
}
