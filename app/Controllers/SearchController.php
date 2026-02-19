<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\GlobalSearch;
use Core\Controller;

final class SearchController extends Controller
{
    public function index(): void
    {
        require_permission('dashboard', 'view');

        $query = trim((string) ($_GET['q'] ?? ''));
        $results = GlobalSearch::search($query, 8);

        $this->render('search/index', [
            'pageTitle' => 'Search',
            'query' => $query,
            'sections' => $results['sections'] ?? [],
            'totalResults' => (int) ($results['total'] ?? 0),
        ]);
    }
}
