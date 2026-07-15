    // Check if user is active
    if (!$user['is_active']) {
        errorResponse('Account is deactivated. Contact administrator.', 403);
    }
    
    // Check registration status (for self-registered accounts awaiting approval)
    if (isset($user['registration_status']) && $user['registration_status'] === 'pending') {
        errorResponse('Your account is pending admin approval. Please wait.', 403);
    }
    if (isset($user['registration_status']) && $user['registration_status'] === 'rejected') {
        errorResponse('Your account has been rejected. Please contact the admin or re-register.', 403);
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        errorResponse('Invalid email or password', 401);
    }
    if ($user['registration_status'] === 'rejected') {
        errorResponse('Your account has been rejected. Please contact the admin or re-register.', 403);
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        errorResponse('Invalid email or password', 401);
    }
    
    // Create session
    $token = createSession($user['id']);<?php
/**
 * Login API
 * 
 * POST /api/auth/login.php
 * Body: { "email": "...", "password": "..." }
 * 
 * Returns session token on success.
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/middleware.php';

handleCors();
requireMethod('POST');
checkRateLimit('login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

$input = getJsonInput();

// Validate required fields
$missing = validateRequired($input, ['email', 'password']);
if ($missing) {
    errorResponse('Missing required fields', 400, [
        'missing_fields' => $missing,
    ]);
}

$email = sanitizeEmail($input['email']);
$password = $input['password'];

if (empty($email)) {
    errorResponse('Invalid email address', 400);
}

try {
    $db = Database::getInstance();
    
    // Find user by email
    $stmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        errorResponse('Invalid email or password', 401);
    }
    
    // Check if user is active
    if (!$user['is_active']) {
        errorResponse('Account is deactivated. Contact administrator.', 403);
    }
    
    // Check registration status (for self-registered users)
    if (isset($user['registration_status']) && $user['registration_status'] === 'pending') {
        errorResponse('Your account is pending admin approval. Please wait.', 403);
    }
    if (isset($user['registration_status']) && $user['registration_status'] === 'rejected') {
        errorResponse('Your account has been rejected. Please contact the admin or re-register.', 403);
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        errorResponse('Invalid email or password', 401);
    }
    
    // Create session
    $token = createSession($user['id']);
    
    // Update last login
    $updateStmt = $db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
    $updateStmt->execute([':id' => $user['id']]);
    
    // Log the login
    logAudit($user['id'], null, 'login', 'user', $user['id']);
    
    // Return user info (excluding password hash)
    unset($user['password_hash']);
    
    successResponse([
        'token' => $token,
        'user' => [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'name_bn' => $user['name_bn'],
            'role' => $user['role'],
            'specialization' => $user['specialization'],
            'phone' => $user['phone'],
            'photo_url' => $user['photo_url'],
            'signature_url' => $user['signature_url'],
            'bmdc_registration' => $user['bmdc_registration'],
        ],
    ], 'Login successful');
    
} catch (\Exception $e) {
    error_log('Login error: ' . $e->getMessage());
    errorResponse('Login failed. Please try again.', 500);
}
