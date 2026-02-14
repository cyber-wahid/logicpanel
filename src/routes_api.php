<?php

declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use LogicDock\Application\Controllers\AuthController;
use LogicDock\Application\Controllers\Master\AccountController;
use LogicDock\Application\Controllers\Master\PackageController;
use LogicDock\Application\Controllers\Master\ServiceController as MasterServiceController;
use LogicDock\Application\Controllers\Master\DatabaseController as MasterDatabaseController;
use LogicDock\Application\Controllers\Master\DomainController;
use LogicDock\Application\Middleware\ApiTokenMiddleware;

/**
 * RESTful API Routes for WHMCS/Blesta Integration
 * Token-based authentication using X-API-Key header
 */
return function (App $app) {
    
    // API v1 Group - Token-based authentication
    $app->group('/v1/api', function (RouteCollectorProxy $group) {
        
        // Account Management
        $group->post('/accounts', [AccountController::class, 'createViaApi']);
        $group->get('/accounts/{id}', [AccountController::class, 'showViaApi']);
        $group->put('/accounts/{id}', [AccountController::class, 'updateViaApi']);
        $group->delete('/accounts/{id}', [AccountController::class, 'deleteViaApi']);
        $group->post('/accounts/{id}/suspend', [AccountController::class, 'suspendViaApi']);
        $group->post('/accounts/{id}/unsuspend', [AccountController::class, 'unsuspendViaApi']);
        $group->post('/accounts/{id}/password', [AccountController::class, 'changePasswordViaApi']);
        
        // Package Management
        $group->get('/packages', [PackageController::class, 'listViaApi']);
        $group->get('/packages/{id}', [PackageController::class, 'showViaApi']);
        
        // Service Management
        $group->get('/accounts/{accountId}/services', [MasterServiceController::class, 'listViaApi']);
        $group->post('/accounts/{accountId}/services', [MasterServiceController::class, 'createViaApi']);
        
        // Database Management
        $group->get('/accounts/{accountId}/databases', [MasterDatabaseController::class, 'listViaApi']);
        $group->post('/accounts/{accountId}/databases', [MasterDatabaseController::class, 'createViaApi']);
        
        // Domain Management
        $group->get('/accounts/{accountId}/domains', [DomainController::class, 'listViaApi']);
        $group->post('/accounts/{accountId}/domains', [DomainController::class, 'createViaApi']);
        
    })->add(ApiTokenMiddleware::class);
};
