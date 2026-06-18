<?php

declare(strict_types=1);

namespace App\Controllers;

final class BugReportsController extends CompanySubmissionController
{
    protected function submissionType(): string
    {
        return 'bug';
    }

    protected function routePrefix(): string
    {
        return '/admin/bug-reports';
    }
}
