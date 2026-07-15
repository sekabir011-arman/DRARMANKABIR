<?php
/**
 * Admin Content Login API
 * 
 * POST /api/auth/admin_login.php
 * Body: { "username": "...", "password": "..." }
 * 
 * Replaces the hardcoded ADMIN_ACCOUNTS in the frontend JS.
 * Returns a session token stored in localStorage for admin content management.
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../helpers.php';

handleCors();
requireMethod('POST');
checkRateLimit('admin_login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

$input = getJsonInput();

$missing = validateRequired($input, ['username', 'password']);
if ($missing) {
    errorResponse('Missing required fields', 400, [
        'missing_fields' => $missing,
    ]);
}

$username = trim($input['username']);
$password = $input['password'];

try {
    $db = Database::getInstance();
    
    // Find admin account
    $stmt = $db->prepare('SELECT * FROM admin_accounts WHERE username = :username AND is_active = 1 LIMIT 1');
    $stmt->execute([':username' => $username]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        errorResponse('Invalid username or password', 401);
    }
    
    // Verify password
    if (!password_verify($password, $admin['password_hash'])) {
        errorResponse('Invalid username or password', 401);
    }
    
    // Update last login
    $updateStmt = $db->prepare('UPDATE admin_accounts SET last_login_at = NOW() WHERE id = :id');
    $updateStmt->execute([':id' => $admin['id']]);
    
    // Generate a session token (stored in localStorage by frontend)
    $token = bin2hex(random_bytes(32));
    
    successResponse([
        'token' => $token,
        'admin' => [
            'id' => (int)$admin['id'],
            'username' => $admin['username'],
            'display_name' => $admin['display_name'],
        ],
        'is_admin' => true,
    ], 'Login successful');
    
} catch (\Exception $e) {
    error_log('Admin login error: ' . $e->getMessage());
    errorResponse('Login failed. Please try again.', 500);
}
