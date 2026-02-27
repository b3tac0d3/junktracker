<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\AdminController;
use App\Controllers\BillingController;
use App\Controllers\ClientsController;
use App\Controllers\HomeController;
use App\Controllers\JobsController;
use App\Controllers\SiteAdminController;
use App\Controllers\TasksController;
use App\Controllers\TimeTrackingController;
use Core\Router;

$router = new Router();

$router->get('/', [HomeController::class, 'index']);
$router->get('/login', [AuthController::class, 'login']);
$router->post('/login', [AuthController::class, 'authenticate']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/site-admin/businesses', [SiteAdminController::class, 'businesses']);
$router->post('/site-admin/switch-business', [SiteAdminController::class, 'switchBusiness']);
$router->post('/site-admin/exit-workspace', [SiteAdminController::class, 'exitWorkspace']);

$router->get('/clients', [ClientsController::class, 'index']);
$router->get('/clients/{id}', [ClientsController::class, 'show']);
$router->get('/jobs', [JobsController::class, 'index']);
$router->get('/tasks', [TasksController::class, 'index']);
$router->get('/time-tracking', [TimeTrackingController::class, 'index']);
$router->get('/billing', [BillingController::class, 'index']);
$router->get('/admin', [AdminController::class, 'index']);

return $router;
