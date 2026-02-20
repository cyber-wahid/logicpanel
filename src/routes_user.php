<?php

declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use LogicPanel\Application\Controllers\AuthController;
use LogicPanel\Application\Controllers\ServiceController;
use LogicPanel\Application\Controllers\DatabaseController;
use LogicPanel\Application\Controllers\FileController;
use LogicPanel\Application\Controllers\BackupController;
use LogicPanel\Application\Controllers\SystemController;
use LogicPanel\Application\Controllers\CronController;
use LogicPanel\Application\Middleware\AuthMiddleware;
use LogicPanel\Application\Middleware\RateLimitMiddleware;
use LogicPanel\Application\Middleware\CorsMiddleware;

return function (App $app) {
    // CORS Middleware
    $app->add(CorsMiddleware::class);

    // Health check
    $app->get('/health', function ($request, $response) {
        $response->getBody()->write(json_encode([
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0.0'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // V1 API Group
    $app->group('/v1', function (RouteCollectorProxy $v1) {

        // Public routes (no authentication required)
        $v1->group('/auth', function (RouteCollectorProxy $group) {
            $group->post('/login', [AuthController::class, 'login']);
            $group->post('/register', [AuthController::class, 'register']);
        });

        // Protected routes (authentication required)
        $v1->group('', function (RouteCollectorProxy $group) {

            // Auth routes
            $group->post('/auth/refresh', [AuthController::class, 'refresh']);
            $group->post('/auth/logout', [AuthController::class, 'logout']);
            $group->get('/auth/me', [AuthController::class, 'me']);
            $group->get('/auth/settings', [AuthController::class, 'getSettings']);
            $group->post('/auth/profile', [AuthController::class, 'updateProfile']);
            $group->post('/auth/password', [AuthController::class, 'changePassword']);

            // Service routes
            $group->get('/services', [ServiceController::class, 'index']);
            $group->post('/services', [ServiceController::class, 'create']);
            $group->get('/services/{id}', [ServiceController::class, 'show']);
            $group->patch('/services/{id}', [ServiceController::class, 'update']);
            $group->put('/services/{id}', [ServiceController::class, 'update']);
            $group->delete('/services/{id}', [ServiceController::class, 'delete']);

            // Consolidating actions into PATCH is handled inside the 'update' method 
            // but we keep these for backward compatibility for now if needed, 
            // OR remove them if we want to be strict. Let's keep them as deprecated.
            $group->post('/services/{id}/start', [ServiceController::class, 'start']);
            $group->post('/services/{id}/stop', [ServiceController::class, 'stop']);
            $group->post('/services/{id}/restart', [ServiceController::class, 'restart']);

            $group->post('/services/{id}/terminal', [ServiceController::class, 'command']);
            $group->get('/services/{id}/terminal/token', [ServiceController::class, 'getTerminalToken']);
            $group->get('/services/{id}/logs', [ServiceController::class, 'logs']);
            $group->get('/services/{id}/stats', [ServiceController::class, 'stats']);

            // Database routes
            $group->get('/databases', [DatabaseController::class, 'index']);
            $group->post('/databases', [DatabaseController::class, 'create']);
            $group->get('/services/{serviceId}/databases', [DatabaseController::class, 'index']);
            $group->post('/services/{serviceId}/databases', [DatabaseController::class, 'create']);
            $group->get('/databases/{id}', [DatabaseController::class, 'show']);
            $group->delete('/databases/{id}', [DatabaseController::class, 'delete']);

            // File routes
            $group->get('/services/{serviceId}/files', [FileController::class, 'list']);
            $group->get('/services/{serviceId}/files/read', [FileController::class, 'read']);
            $group->post('/services/{serviceId}/files/extract', [FileController::class, 'extract']);
            $group->post('/services/{serviceId}/files/copy', [FileController::class, 'copy']);
            $group->post('/services/{serviceId}/files/move', [FileController::class, 'move']);
            $group->post('/services/{serviceId}/files/rename', [FileController::class, 'rename']);
            $group->get('/services/{serviceId}/files/download', [FileController::class, 'download']);
            $group->post('/services/{serviceId}/files/upload', [FileController::class, 'upload']);
            $group->put('/services/{serviceId}/files', [FileController::class, 'update']);
            $group->delete('/services/{serviceId}/files', [FileController::class, 'delete']);
            $group->post('/services/{serviceId}/files/mkdir', [FileController::class, 'mkdir']);
            $group->post('/services/{serviceId}/files/chmod', [FileController::class, 'chmod']);

            // Trash routes
            $group->get('/services/{serviceId}/files/trash', [FileController::class, 'listTrash']);
            $group->post('/services/{serviceId}/files/trash/restore', [FileController::class, 'restoreFromTrash']);
            $group->delete('/services/{serviceId}/files/trash', [FileController::class, 'emptyTrash']);

            // Backup routes
            $group->get('/backups', [BackupController::class, 'index']);
            $group->post('/backups/app', [BackupController::class, 'createAppBackup']);
            $group->post('/backups/db', [BackupController::class, 'createDbBackup']);
            $group->delete('/backups/{filename}', [BackupController::class, 'delete']);
            $group->get('/backups/download/{filename}', [BackupController::class, 'download']);
            $group->post('/backups/restore', [BackupController::class, 'restore']);

            // System stats
            $group->get('/system/stats', [SystemController::class, 'stats']);
            $group->get('/system/container-stats', [SystemController::class, 'containerStats']);

            // Cron Jobs
            $group->get('/cron', [CronController::class, 'index']);
            $group->post('/cron', [CronController::class, 'create']);
            $group->delete('/cron/{id}', [CronController::class, 'delete']);
            $group->post('/cron/{id}/toggle', [CronController::class, 'toggle']);
            $group->post('/cron/{id}/run', [CronController::class, 'run']);

        })->add(AuthMiddleware::class)
            ->add(RateLimitMiddleware::class);

        // WHMCS Integration routes
        $v1->group('/whmcs', function (RouteCollectorProxy $group) {
            $group->post('/create-account', [AuthController::class, 'createAccount']);
            $group->post('/suspend-account', [AuthController::class, 'suspendAccount']);
            $group->post('/terminate-account', [AuthController::class, 'terminateAccount']);
            $group->post('/change-password', [AuthController::class, 'changePassword']);
            $group->get('/account-status', [AuthController::class, 'accountStatus']);
        });
    });

    // Backward compatibility: Handle routes without /v1 prefix by redirecting or proxying
    // For now, we'll keep the top-level groups as duplicates of the v1 group logic, 
    // but the intention is to move everything to /v1.
    $app->redirect('/auth/login', '/v1/auth/login', 301);
    // ... we could add more redirects, but better to keep the old routes working for now 
    // by just not removing them, just marking as legacy in documentation.

    // Legacy support (non-v1 prefix) - copy of logic above without /v1
    $app->group('/auth', function (RouteCollectorProxy $group) {
        $group->post('/login', [AuthController::class, 'login']);
        $group->post('/register', [AuthController::class, 'register']);
    });

    $app->group('', function (RouteCollectorProxy $group) {
        // Auth routes
        $group->get('/auth/me', [AuthController::class, 'me']);
        $group->get('/auth/settings', [AuthController::class, 'getSettings']);
        $group->post('/auth/profile', [AuthController::class, 'updateProfile']);
        $group->post('/auth/password', [AuthController::class, 'changePassword']);
        $group->post('/auth/refresh', [AuthController::class, 'refresh']);
        $group->post('/auth/logout', [AuthController::class, 'logout']);

        // Service routes
        $group->get('/services', [ServiceController::class, 'index']);
        $group->post('/services', [ServiceController::class, 'create']);
        $group->get('/services/{id}', [ServiceController::class, 'show']);
        $group->patch('/services/{id}', [ServiceController::class, 'update']);
        $group->put('/services/{id}', [ServiceController::class, 'update']);
        $group->delete('/services/{id}', [ServiceController::class, 'delete']);

        // Service actions
        $group->post('/services/{id}/start', [ServiceController::class, 'start']);
        $group->post('/services/{id}/stop', [ServiceController::class, 'stop']);
        $group->post('/services/{id}/restart', [ServiceController::class, 'restart']);
        $group->post('/services/{id}/terminal', [ServiceController::class, 'command']);
        $group->get('/services/{id}/terminal/token', [ServiceController::class, 'getTerminalToken']);
        $group->get('/services/{id}/logs', [ServiceController::class, 'logs']);
        $group->get('/services/{id}/stats', [ServiceController::class, 'stats']);

        // Database routes (legacy support)
        $group->get('/databases', [DatabaseController::class, 'index']);
        $group->post('/databases', [DatabaseController::class, 'create']);
        $group->get('/databases/{id}', [DatabaseController::class, 'show']);
        $group->delete('/databases/{id}', [DatabaseController::class, 'delete']);

        // File routes
        $group->get('/services/{serviceId}/files', [FileController::class, 'list']);
        $group->get('/services/{serviceId}/files/read', [FileController::class, 'read']);
        $group->post('/services/{serviceId}/files/upload', [FileController::class, 'upload']);
        $group->put('/services/{serviceId}/files', [FileController::class, 'update']);
        $group->delete('/services/{serviceId}/files', [FileController::class, 'delete']);
        $group->post('/services/{serviceId}/files/mkdir', [FileController::class, 'mkdir']);
        $group->get('/services/{serviceId}/files/download', [FileController::class, 'download']);

        // Cron routes
        $group->get('/cron', [CronController::class, 'index']);
        $group->post('/cron', [CronController::class, 'create']);
        $group->delete('/cron/{id}', [CronController::class, 'delete']);
        $group->post('/cron/{id}/toggle', [CronController::class, 'toggle']);
        $group->post('/cron/{id}/run', [CronController::class, 'run']);

        // Backup routes
        $group->get('/backups', [BackupController::class, 'index']);
        $group->post('/backups/app', [BackupController::class, 'createAppBackup']);
        $group->delete('/backups/{filename}', [BackupController::class, 'delete']);
        $group->get('/backups/download/{filename}', [BackupController::class, 'download']);
        $group->post('/backups/restore', [BackupController::class, 'restore']);

        // ... adding only frequently used ones for legacy support
    })->add(AuthMiddleware::class);
};
