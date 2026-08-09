<?php
/**
 * Logout API (modernized)
 *
 * POST /api/auth/logout.php
 * Headers: Authorization: Bearer <token>
 *
 * Invalidates the current session (all session tables) and clears cookie.
 */

require_once __DIR__ . '/../../config_loader.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db_helper.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/middleware.php';

handleCors();
requireMethod('POST', 'GET');

$user = requireAuth();

try {
    $token = getBearerToken();
    if ($token) {
        destroySession($token);
    }

    logAudit((int)$user['id'], null, 'logout', 'user', (int)$user['id']);

    Response::ok('Logged out successfully', null);
} catch (\Throwable $e) {
    error_log('Logout error: ' . $e->getMessage());
    Response::error('Logout failed', [], 500);
}
