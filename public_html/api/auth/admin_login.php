<?php
/**
 * Admin Content Login API (modernized)
 *
 * POST /api/auth/admin_login.php
 * Body: { "username": "...", "password": "..." }
 *
 * Server-side session stored in admin_sessions. Returns standardized JSON response.
 */

require_once __DIR__ . '/../../config_loader.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db_helper.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/middleware.php';

handleCors();
requireMethod('POST');
checkRateLimit('admin_login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 900); // 10 attempts per 15 min

$input = getJsonInput();

$missing = validateRequired($input, ['username', 'password']);
if ($missing) {
    Response::error('Missing required fields', ['missing_fields' => $missing], 400);
}

$username = trim($input['username']);
$password = $input['password'];

try {
    $admin = DB::fetchOne('SELECT id, username, password_hash, display_name, is_active FROM admin_accounts WHERE username = :username LIMIT 1', [':username' => $username]);

    if (!$admin || empty($admin['is_active'])) {
        logAudit(null, null, 'failed_admin_login', 'admin', $admin['id'] ?? null, null, ['username' => $username, 'reason' => 'not_found_or_inactive']);
        Response::error('Invalid username or password', [], 401);
    }

    if (!password_verify($password, $admin['password_hash'])) {
        logAudit(null, null, 'failed_admin_login', 'admin', (int)$admin['id'], null, ['reason' => 'invalid_password']);
        Response::error('Invalid username or password', [], 401);
    }

    // Rehash if needed
    if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        DB::execute('UPDATE admin_accounts SET password_hash = :hash WHERE id = :id', [':hash' => $newHash, ':id' => (int)$admin['id']]);
    }

    // Update last login
    DB::execute('UPDATE admin_accounts SET last_login_at = NOW() WHERE id = :id', [':id' => (int)$admin['id']]);

    // Create server-side session
    $token = createAdminSession((int)$admin['id']);

    // Audit
    logAudit(null, null, 'admin_login', 'admin', (int)$admin['id']);

    Response::ok('Login successful', [
        'token' => $token,
        'admin' => [
            'id' => (int)$admin['id'],
            'username' => $admin['username'] ?? null,
            'display_name' => $admin['display_name'] ?? null,
        ],
        'is_admin' => true,
    ]);

} catch (\Throwable $e) {
    error_log('Admin login error: ' . $e->getMessage());
    Response::error('Login failed. Please try again.', [], 500);
}
