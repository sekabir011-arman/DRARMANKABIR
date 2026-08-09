<?php
/**
 * Login API (modernized)
 *
 * POST /api/auth/login.php
 * Body: { "email": "...", "password": "..." }
 *
 * Validates credentials and creates a session. Sets secure HttpOnly cookie and returns session token.
 */

require_once __DIR__ . '/../../config_loader.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db_helper.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/middleware.php';

handleCors();
requireMethod('POST');
checkRateLimit('login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 5, 900); // 5 attempts per 15 minutes

$input = getJsonInput();

// Validate required fields
$missing = validateRequired($input, ['email', 'password']);
if ($missing) {
    Response::error('Missing required fields', ['missing_fields' => $missing], 400);
}

$email = sanitizeEmail($input['email']);
$password = $input['password'];

if (empty($email)) {
    Response::error('Invalid email address', [], 400);
}

try {
    // Fetch user by email
    $user = DB::fetchOne('SELECT id, email, password_hash, full_name, name_bn, role, specialization, phone, photo_url, signature_url, bmdc_registration, is_active, registration_status FROM users WHERE email = :email LIMIT 1', [':email' => $email]);

    if (!$user) {
        logAudit(null, null, 'failed_login', 'user', null, null, ['email' => $email, 'reason' => 'user_not_found']);
        Response::error('Invalid email or password', [], 401);
    }

    if (empty($user['is_active'])) {
        logAudit((int)$user['id'], null, 'failed_login', 'user', (int)$user['id'], null, ['reason' => 'account_deactivated']);
        Response::error('Account is deactivated. Contact administrator.', [], 403);
    }

    if (($user['registration_status'] ?? '') === 'pending') {
        logAudit((int)$user['id'], null, 'failed_login', 'user', (int)$user['id'], null, ['reason' => 'pending_approval']);
        Response::error('Your account is pending admin approval. Please wait.', [], 403);
    }

    if (($user['registration_status'] ?? '') === 'rejected') {
        logAudit((int)$user['id'], null, 'failed_login', 'user', (int)$user['id'], null, ['reason' => 'account_rejected']);
        Response::error('Your account has been rejected. Please contact the admin or re-register.', [], 403);
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        logAudit((int)$user['id'], null, 'failed_login', 'user', (int)$user['id'], null, ['reason' => 'invalid_password']);
        Response::error('Invalid email or password', [], 401);
    }

    // Regenerate PHP session ID to prevent session fixation
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    // Create session and token
    $token = createSession((int)$user['id']);

    // Set secure HttpOnly cookie
    $cookieExpires = time() + intval(cfg('SESSION_LIFETIME', SESSION_LIFETIME ?? 604800));
    setcookie('session_token', $token, [
        'expires' => $cookieExpires,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // Update last login
    DB::execute('UPDATE users SET last_login_at = NOW() WHERE id = :id', [':id' => (int)$user['id']]);

    // Log successful login
    logAudit((int)$user['id'], null, 'login', 'user', (int)$user['id']);

    // Return user info (excluding password hash)
    unset($user['password_hash']);

    Response::ok('Login successful', [
        'token' => $token,
        'user' => [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'full_name' => $user['full_name'] ?? null,
            'name_bn' => $user['name_bn'] ?? null,
            'role' => $user['role'] ?? null,
            'specialization' => $user['specialization'] ?? null,
            'phone' => $user['phone'] ?? null,
            'photo_url' => $user['photo_url'] ?? null,
            'signature_url' => $user['signature_url'] ?? null,
            'bmdc_registration' => $user['bmdc_registration'] ?? null,
        ],
    ]);

} catch (\Throwable $e) {
    error_log('Login error: ' . $e->getMessage());
    Response::error('Login failed. Please try again.', [], 500);
}
