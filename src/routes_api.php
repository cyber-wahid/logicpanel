<?php

declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use LogicPanel\Application\Controllers\Master\AccountController;
use LogicPanel\Application\Controllers\Master\PackageController;
use LogicPanel\Application\Controllers\Master\ServiceController as MasterServiceController;
use LogicPanel\Application\Controllers\Master\DatabaseController as MasterDatabaseController;
use LogicPanel\Application\Controllers\Master\DomainController;
use LogicPanel\Application\Middleware\ApiTokenMiddleware;

/**
 * RESTful API Routes for WHMCS/Blesta Integration
 * Token-based authentication using X-API-Key header
 * 
 * All routes require a valid API key (generated from Master Panel → API Keys)
 * Without a valid API key, all requests return 401 Unauthorized
 */
return function (App $app) {
    
    // API v1 Group - Token-based authentication
    $app->group('/v1/api', function (RouteCollectorProxy $group) {
        
        // ─── Account Management ───────────────────────────────────
        $group->post('/accounts', [AccountController::class, 'create']);
        $group->get('/accounts/{id}', [AccountController::class, 'show']);
        $group->put('/accounts/{id}', [AccountController::class, 'update']);
        $group->delete('/accounts/{id}', [AccountController::class, 'terminate']);
        $group->post('/accounts/{id}/suspend', [AccountController::class, 'suspend']);
        $group->post('/accounts/{id}/unsuspend', [AccountController::class, 'unsuspend']);
        $group->post('/accounts/{id}/password', [AccountController::class, 'changePasswordById']);
        $group->post('/accounts/{id}/package', [AccountController::class, 'changePackage']);
        $group->post('/accounts/{id}/login', [AccountController::class, 'loginAsUser']); // SSO one-click login
        
        // ─── Package Management ───────────────────────────────────
        $group->get('/packages', [PackageController::class, 'index']);
        $group->get('/packages/{id}', [PackageController::class, 'get']);
        
        // ─── Services by Account ──────────────────────────────────
        $group->get('/accounts/{accountId}/services', [MasterServiceController::class, 'listForAccount']);
        $group->post('/accounts/{accountId}/services', [MasterServiceController::class, 'createForAccount']);
        
        // ─── Databases by Account ─────────────────────────────────
        $group->get('/accounts/{accountId}/databases', [MasterDatabaseController::class, 'listForAccount']);
        $group->post('/accounts/{accountId}/databases', [MasterDatabaseController::class, 'createForAccount']);
        
        // ─── Domains by Account ───────────────────────────────────
        $group->get('/accounts/{accountId}/domains', [DomainController::class, 'listForAccount']);
        $group->post('/accounts/{accountId}/domains', [DomainController::class, 'createForAccount']);
        
    })->add(ApiTokenMiddleware::class);
};
