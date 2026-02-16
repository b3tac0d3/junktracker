<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;

final class ErrorController extends Controller
{
    public function notFound(): void
    {
        http_response_code(404);
        $this->render('errors/404', [
            'pageTitle' => 'Page Not Found',
        ], 'error');
    }

    public function unauthorized(): void
    {
        http_response_code(401);
        $this->render('errors/401', [
            'pageTitle' => 'Unauthorized',
        ], 'error');
    }

    public function serverError(): void
    {
        http_response_code(500);
        $this->render('errors/500', [
            'pageTitle' => 'Server Error',
        ], 'error');
    }
}
