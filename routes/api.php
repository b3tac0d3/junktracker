<?php

declare(strict_types=1);

use App\Controllers\Api\AuthController;
use App\Controllers\Api\DashboardController;
use App\Controllers\Api\DeviceTokensController;
use App\Controllers\Api\EventsController;
use App\Controllers\Api\JobsController;
use App\Controllers\Api\NotificationsController;
use App\Controllers\Api\SearchController;
use App\Controllers\Api\TimeTrackingController;
use Core\Router;

$router = new Router();

$router->post('/api/v1/auth/login', [AuthController::class, 'login']);
$router->post('/api/v1/auth/refresh', [AuthController::class, 'refresh']);
$router->post('/api/v1/auth/logout', [AuthController::class, 'logout']);
$router->get('/api/v1/auth/me', [AuthController::class, 'me']);

$router->get('/api/v1/dashboard/today', [DashboardController::class, 'today']);

$router->get('/api/v1/punch-board', [TimeTrackingController::class, 'punchBoard']);
$router->post('/api/v1/punch/in', [TimeTrackingController::class, 'punchIn']);
$router->post('/api/v1/punch/out', [TimeTrackingController::class, 'punchOut']);
$router->post('/api/v1/punch/switch', [TimeTrackingController::class, 'switchJob']);

$router->get('/api/v1/jobs', [JobsController::class, 'index']);
$router->get('/api/v1/jobs/{id}', [JobsController::class, 'show']);
$router->post('/api/v1/jobs/{id}/status', [JobsController::class, 'quickStatus']);

$router->get('/api/v1/events/feed', [EventsController::class, 'feed']);

$router->get('/api/v1/notifications', [NotificationsController::class, 'index']);

$router->get('/api/v1/search/jobs', [SearchController::class, 'jobs']);
$router->get('/api/v1/search/clients', [SearchController::class, 'clients']);

$router->post('/api/v1/device-tokens/register', [DeviceTokensController::class, 'register']);
$router->post('/api/v1/device-tokens/unregister', [DeviceTokensController::class, 'unregister']);

return $router;
