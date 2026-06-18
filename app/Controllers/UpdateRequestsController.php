<?php

declare(strict_types=1);

namespace App\Controllers;

final class UpdateRequestsController extends CompanySubmissionController
{
    protected function submissionType(): string
    {
        return 'update';
    }

    protected function routePrefix(): string
    {
        return '/admin/update-requests';
    }
}
