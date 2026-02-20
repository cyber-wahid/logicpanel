<?php

declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use LogicPanel\Application\Middleware\AuthMiddleware;
use LogicPanel\Application\Middleware\CorsMiddleware;
use LogicPanel\Application\Controllers\Master\AccountController;
use LogicPanel\Application\Controllers\Master\PackageController;
use LogicPanel\Application\Controllers\Master\SettingsController;
use LogicPanel\Application\Controllers\AuthController;
use LogicPanel\Application\Middleware\RateLimitMiddleware;

return function (App $app) {
    // CORS Middleware
    $app->add(CorsMiddleware::class);

    // Public routes for Master Panel
    $app->group('/auth', function (RouteCollectorProxy $group) {
        $group->post('/login', [AuthController::class, 'login']);
    });

    // Public routes specifically for Master prefix (if needed by frontend)
    $app->group('/master/auth', function (RouteCollectorProxy $group) {
        $group->post('/login', [AuthController::class, 'login']);
    });

    // Protected Auth routes (Shared)
    $app->group('/auth', function (RouteCollectorProxy $group) {
        $group->get('/me', [AuthController::class, 'me']);
        $group->post('/logout', [AuthController::class, 'logout']);
        $group->post('/profile', [AuthController::class, 'updateProfile']);
        $group->post('/password', [AuthController::class, 'changePassword']);
    })->add(AuthMiddleware::class);

    // Protected routes (Master Panel)
    $app->group('/master', function (RouteCollectorProxy $group) {

        $group->get('/dashboard', function ($request, $response) {
            $response->getBody()->write(json_encode(['message' => 'Welcome to Master Panel']));
            return $response->withHeader('Content-Type', 'application/json');
        });

        // Account Management
        $group->get('/accounts', [AccountController::class, 'index']);
        $group->get('/accounts/{id}', [AccountController::class, 'show']); // Get single account
        $group->put('/accounts/{id}', [AccountController::class, 'update']); // Update account
        $group->post('/accounts', [AccountController::class, 'create']); // Create (WHMCS Create)
        $group->post('/accounts/suspend', [AccountController::class, 'suspend']); // WHMCS Suspend (Body)
        $group->post('/accounts/unsuspend', [AccountController::class, 'unsuspend']); // WHMCS Unsuspend (Body)
        $group->post('/accounts/terminate', [AccountController::class, 'terminate']); // WHMCS Terminate (Body)

        // Panel ID-based Actions
        $group->post('/accounts/{id}/login', [AccountController::class, 'loginAsUser']);
        $group->post('/accounts/{id}/suspend', [AccountController::class, 'suspend']);
        $group->post('/accounts/{id}/unsuspend', [AccountController::class, 'unsuspend']);
        $group->post('/accounts/{id}/unlock', [AccountController::class, 'unlock']);
        $group->post('/accounts/{id}/terminate', [AccountController::class, 'terminate']);

        $group->post('/accounts/changepackage', [AccountController::class, 'changePackage']);
        $group->post('/accounts/changepassword', [AccountController::class, 'changePassword']);

        // Package Management
        $group->get('/packages', [PackageController::class, 'index']);
        $group->get('/packages/{id}', [PackageController::class, 'get']);
        $group->post('/packages', [PackageController::class, 'create']);
        $group->put('/packages/{id}', [PackageController::class, 'update']);
        $group->delete('/packages/{id}', [PackageController::class, 'delete']);

        // System Stats
        $group->get('/system/stats', [\LogicPanel\Application\Controllers\SystemController::class, 'stats']);

        // Settings
        $group->get('/settings', [SettingsController::class, 'get']);
        $group->get('/settings/detect-ip', [SettingsController::class, 'detectIp']);
        $group->get('/settings/terminal/token', [SettingsController::class, 'getRootTerminalToken']);
        $group->post('/settings', [SettingsController::class, 'update']);

        // Database Admin
        $group->get('/databases', [\LogicPanel\Application\Controllers\Master\DatabaseController::class, 'index']);
        $group->delete('/databases/{id}', [\LogicPanel\Application\Controllers\Master\DatabaseController::class, 'delete']);

        // Service/Container Admin (User Containers)
        $group->get('/services', [\LogicPanel\Application\Controllers\Master\ServiceController::class, 'index']);
        $group->post('/services/bulk-action', [\LogicPanel\Application\Controllers\Master\ServiceController::class, 'bulkAction']);
        $group->post('/services/{id}/start', [\LogicPanel\Application\Controllers\Master\ServiceController::class, 'start']);
        $group->post('/services/{id}/stop', [\LogicPanel\Application\Controllers\Master\ServiceController::class, 'stop']);
        $group->post('/services/{id}/restart', [\LogicPanel\Application\Controllers\Master\ServiceController::class, 'restart']);
        $group->delete('/services/{id}', [\LogicPanel\Application\Controllers\Master\ServiceController::class, 'delete']);

        // System Services (Root Admin)
        $group->get('/system/services', [\LogicPanel\Application\Controllers\Master\SystemController::class, 'getServicesStatus']);
        $group->post('/system/services/restart', [\LogicPanel\Application\Controllers\Master\SystemController::class, 'restartService']);

        // Updates
        $group->get('/system/update/check', [\LogicPanel\Application\Controllers\SystemController::class, 'checkUpdate']);
        $group->post('/system/update/perform', [\LogicPanel\Application\Controllers\SystemController::class, 'performUpdate']);
        $group->get('/system/update/progress', [\LogicPanel\Application\Controllers\SystemController::class, 'getUpdateProgress']);

        // Domain Admin
        $group->get('/domains', [\LogicPanel\Application\Controllers\Master\DomainController::class, 'index']);
        $group->post('/domains', [\LogicPanel\Application\Controllers\Master\DomainController::class, 'create']);
        $group->delete('/domains/{id}', [\LogicPanel\Application\Controllers\Master\DomainController::class, 'delete']);

        // API Keys
        $group->get('/api-keys', [\LogicPanel\Application\Controllers\Master\ApiKeyController::class, 'index']);
        $group->post('/api-keys', [\LogicPanel\Application\Controllers\Master\ApiKeyController::class, 'create']);
        $group->delete('/api-keys/{id}', [\LogicPanel\Application\Controllers\Master\ApiKeyController::class, 'delete']);

        // Reseller Stats
        $group->get('/reseller/stats', [\LogicPanel\Application\Controllers\Master\ResellerController::class, 'resourceStats']);
        
        // Reseller Management
        $group->delete('/resellers/{id}', [\LogicPanel\Application\Controllers\Master\ResellerController::class, 'delete']);

    })->add(\LogicPanel\Application\Middleware\MasterAuthMiddleware::class)
        ->add(AuthMiddleware::class)
        ->add(RateLimitMiddleware::class);
};
