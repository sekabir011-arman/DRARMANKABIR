<?php
/**
 * Authentication Middleware
 * 
 * Validates session tokens and provides current user context.
 * Include this at the top of any protected API endpoint.
 * 
 * Authentication sources (in order):
 * 1. Authorization: Bearer <token> header
 * 2. X-Session-Token header
 * 3. session_token cookie (HttpOnly, Secure)
 * 
 * Supports three session types:
 *   - user_sessions  (main app - doctors, nurses, staff)
 *   - patient_sessions (patient portal)
 *   - admin_sessions   (content management / admin accounts)
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../helpers.php';

/**
 * Get the authenticated user/patient/admin from the session token.
 * Checks all three session tables and returns the appropriate context.
 *
 * @param string|null $type Restrict to a specific session type: 'user', 'patient', 'admin'
 * @return array|null Returns ['type' => 'user'|'patient'|'admin', 'data' => ...] or null
 */
function getAuthUser(?string $type = null): ?array {
    $token = getBearerToken();
    if (!$token) return null;
    
    try {
        $db = Database::getInstance();
        $now = date('Y-m-d H:i:s');
        
        // 1. Check user_sessions (main app users - doctors, nurses, staff, admins)
        if ($type === null || $type === 'user') {
            $stmt = $db->prepare('
                SELECT u.*, s.token, s.expires_at 
                FROM user_sessions s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.token = :token 
                  AND s.expires_at > :now
                  AND u.is_active = 1
                LIMIT 1
            ');
            $stmt->execute([':token' => $token, ':now' => $now]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Update last activity
                $db->prepare('UPDATE user_sessions SET last_activity = NOW() WHERE token = :token')
                   ->execute([':token' => $token]);
                return ['type' => 'user', 'data' => $user];
            }
        }
        
        // 2. Check patient_sessions
        if ($type === null || $type === 'patient') {
            $stmt = $db->prepare('
                SELECT pl.*, p.full_name, p.name_bn, p.gender, p.date_of_birth, 
                       p.register_number, p.photo_url, p.status as patient_status,
                       s.token, s.expires_at
                FROM patient_sessions s 
                JOIN patient_login pl ON s.patient_login_id = pl.id
                JOIN patients p ON pl.patient_id = p.id
                WHERE s.token = :token 
                  AND s.expires_at > :now
                  AND pl.status = "approved"
                LIMIT 1
            ');
            $stmt->execute([':token' => $token, ':now' => $now]);
            $patient = $stmt->fetch();
            
            if ($patient) {
                $db->prepare('UPDATE patient_sessions SET last_activity = NOW() WHERE token = :token')
                   ->execute([':token' => $token]);
                return ['type' => 'patient', 'data' => $patient];
            }
        }
        
        // 3. Check admin_sessions (content management accounts)
        if ($type === null || $type === 'admin') {
            $stmt = $db->prepare('
                SELECT a.*, s.token, s.expires_at 
                FROM admin_sessions s 
                JOIN admin_accounts a ON s.admin_id = a.id 
                WHERE s.token = :token 
                  AND s.expires_at > :now
                  AND a.is_active = 1
                LIMIT 1
            ');
            $stmt->execute([':token' => $token, ':now' => $now]);
            $admin = $stmt->fetch();
            
            if ($admin) {
                $db->prepare('UPDATE admin_sessions SET last_activity = NOW() WHERE token = :token')
                   ->execute([':token' => $token]);
                return ['type' => 'admin', 'data' => $admin];
            }
        }
        
        return null;
    } catch (\Exception $e) {
        error_log('Auth middleware error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get the raw authenticated user array (backward compatible helper).
 * Returns user data array or null if no valid user session.
 */
function getAuthUserData(): ?array {
    $result = getAuthUser('user');
    return $result ? $result['data'] : null;
}

/**
 * Require authentication. Sends 401 if not authenticated.
 * By default accepts all session types.
 */
function requireAuth(): array {
    $result = getAuthUser();
    if (!$result) {
        sendSecurityHeaders();
        errorResponse('Authentication required. Please log in.', 401);
    }
    sendSecurityHeaders();
    return $result['data'];
}

/**
 * Require authentication for a specific session type.
 */
function requireAuthType(string $type): array {
    $result = getAuthUser($type);
    if (!$result) {
        sendSecurityHeaders();
        errorResponse('Authentication required. Please log in.', 401);
    }
    sendSecurityHeaders();
    return $result['data'];
}

/**
 * Require specific role(s) from the main user table.
 * Sends 403 if not authorized.
 */
function requireRole(array $allowedRoles): array {
    $result = getAuthUser('user');
    if (!$result) {
        sendSecurityHeaders();
        errorResponse('Authentication required. Please log in.', 401);
    }
    
    $user = $result['data'];
    if (!in_array($user['role'], $allowedRoles)) {
        sendSecurityHeaders();
        errorResponse('Access denied. Insufficient permissions.', 403, [
            'required_roles' => $allowedRoles,
            'your_role' => $user['role'],
        ]);
    }
    
    sendSecurityHeaders();
    return $user;
}

/**
 * Require admin role from the main user table.
 */
function requireAdmin(): array {
    return requireRole(['admin']);
}

/**
 * Check if user has a specific permission based on role.
 */
function hasPermission(array $user, string $permission): bool {
    // Role-based permissions
    $permissions = [
        'admin' => [
            'manage_users', 'manage_settings', 'view_all_patients',
            'view_all_finances', 'manage_beds', 'view_audit_logs',
            'export_data', 'delete_data',
        ],
        'consultant_doctor' => [
            'view_assigned_patients', 'view_all_patients',
            'create_prescription', 'edit_prescription',
            'view_appointments', 'manage_appointments',
            'create_visit', 'edit_visit',
            'order_investigations', 'view_results',
            'finalize_diagnosis', 'admit_patient', 'discharge_patient',
        ],
        'medical_officer' => [
            'view_assigned_patients', 'view_all_patients',
            'create_prescription', 'edit_prescription',
            'view_appointments',
            'create_visit', 'edit_visit',
            'order_investigations', 'view_results',
            'verify_vitals',
        ],
        'assistant_registrar' => [
            'view_assigned_patients', 'view_all_patients',
            'create_prescription', 'edit_prescription',
            'view_appointments',
            'create_visit',
            'order_investigations', 'view_results',
            'verify_vitals',
        ],
        'registrar' => [
            'view_assigned_patients', 'view_all_patients',
            'create_prescription', 'edit_prescription',
            'view_appointments', 'manage_appointments',
            'create_visit', 'edit_visit',
            'order_investigations', 'view_results',
            'verify_vitals',
        ],
        'assistant_professor' => [
            'view_assigned_patients', 'view_all_patients',
            'create_prescription', 'edit_prescription',
            'view_appointments', 'manage_appointments',
            'create_visit', 'edit_visit',
            'order_investigations', 'view_results',
            'finalize_diagnosis', 'admit_patient', 'discharge_patient',
        ],
        'associate_professor' => [
            'view_assigned_patients', 'view_all_patients',
            'create_prescription', 'edit_prescription',
            'view_appointments', 'manage_appointments',
            'create_visit', 'edit_visit',
            'order_investigations', 'view_results',
            'finalize_diagnosis', 'admit_patient', 'discharge_patient',
        ],
        'professor' => [
            'view_assigned_patients', 'view_all_patients',
            'create_prescription', 'edit_prescription',
            'view_appointments', 'manage_appointments',
            'create_visit', 'edit_visit',
            'order_investigations', 'view_results',
            'finalize_diagnosis', 'admit_patient', 'discharge_patient',
        ],
        'intern_doctor' => [
            'view_assigned_patients',
            'create_prescription',
            'view_appointments',
            'create_visit',
            'record_vitals',
            'view_results',
        ],
        'nurse' => [
            'view_assigned_patients',
            'view_appointments',
            'record_vitals',
            'administer_medication',
            'view_prescriptions',
            'record_mar',
            'create_handover',
        ],
        'staff' => [
            'view_patients',
            'register_patient',
            'view_appointments',
            'manage_appointments',
            'process_payments',
            'view_finances',
        ],
        'reception' => [
            'view_patients',
            'register_patient',
            'view_appointments',
            'manage_appointments',
            'process_payments',
        ],
        'doctor' => [
            'view_assigned_patients', 'view_all_patients',
            'create_prescription', 'edit_prescription',
            'view_appointments', 'manage_appointments',
            'create_visit', 'edit_visit',
            'order_investigations', 'view_results',
            'finalize_diagnosis',
        ],
    ];
    
    return in_array($permission, $permissions[$user['role']] ?? []);
}

/**
 * Require a specific permission.
 */
function requirePermission(string $permission): array {
    $result = getAuthUser('user');
    if (!$result) {
        sendSecurityHeaders();
        errorResponse('Authentication required. Please log in.', 401);
    }
    
    $user = $result['data'];
    if (!hasPermission($user, $permission)) {
        sendSecurityHeaders();
        errorResponse('Access denied. You do not have the "' . $permission . '" permission.', 403);
    }
    
    sendSecurityHeaders();
    return $user;
}

/**
 * Get Bearer token from Authorization header, custom header, or secure cookie.
 * NEVER accepts tokens from URL parameters ($_GET).
 */
function getBearerToken(): ?string {
    // 1. Check Authorization: Bearer header
    $headers = '';
    
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $headers = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $headers = $requestHeaders['Authorization'] ?? $requestHeaders['authorization'] ?? '';
    }
    
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
    }
    
    // 2. Check X-Session-Token custom header
    $sessionHeader = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? '';
    if (!empty($sessionHeader)) {
        return $sessionHeader;
    }
    
    // 3. Check secure HttpOnly cookie
    if (isset($_COOKIE['session_token']) && !empty($_COOKIE['session_token'])) {
        return $_COOKIE['session_token'];
    }
    
    return null;
}

/**
 * Create a new session for a user (main app users).
 */
function createSession(int $userId): string {
    $db = Database::getInstance();
    
    // Generate secure token
    $token = bin2hex(random_bytes(64));
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
    
    $stmt = $db->prepare('
        INSERT INTO user_sessions (user_id, token, ip_address, user_agent, expires_at)
        VALUES (:user_id, :token, :ip_address, :user_agent, :expires_at)
    ');
    $stmt->execute([
        ':user_id' => $userId,
        ':token' => $token,
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ':expires_at' => $expiresAt,
    ]);
    
    return $token;
}

/**
 * Create a new admin session (content management accounts).
 */
function createAdminSession(int $adminId): string {
    $db = Database::getInstance();
    
    $token = bin2hex(random_bytes(64));
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
    
    $stmt = $db->prepare('
        INSERT INTO admin_sessions (admin_id, token, ip_address, user_agent, expires_at)
        VALUES (:admin_id, :token, :ip_address, :user_agent, :expires_at)
    ');
    $stmt->execute([
        ':admin_id' => $adminId,
        ':token' => $token,
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ':expires_at' => $expiresAt,
    ]);
    
    return $token;
}

/**
 * Destroy a session (logout). Handles all session types.
 */
function destroySession(string $token): void {
    if (empty($token)) return;
    
    $db = Database::getInstance();
    
    // Try to delete from all session tables
    $db->prepare('DELETE FROM user_sessions WHERE token = :token')->execute([':token' => $token]);
    $db->prepare('DELETE FROM patient_sessions WHERE token = :token')->execute([':token' => $token]);
    $db->prepare('DELETE FROM admin_sessions WHERE token = :token')->execute([':token' => $token]);
    
    // Also clear the session cookie
    if (isset($_COOKIE['session_token'])) {
        setcookie('session_token', '', [
            'expires' => 1,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE['session_token']);
    }
}

/**
 * Clean up expired sessions from all session tables.
 */
function cleanupExpiredSessions(): void {
    try {
        $db = Database::getInstance();
        
        // Log expired user sessions
        $stmt = $db->query('SELECT user_id FROM user_sessions WHERE expires_at < NOW()');
        $expired = $stmt->fetchAll();
        foreach ($expired as $session) {
            logAudit($session['user_id'], null, 'session_expired', 'session', $session['user_id']);
        }
        
        // Log expired patient sessions
        $stmt = $db->query('SELECT patient_login_id FROM patient_sessions WHERE expires_at < NOW()');
        $expiredPatients = $stmt->fetchAll();
        foreach ($expiredPatients as $session) {
            logAudit(null, $session['patient_login_id'], 'session_expired', 'patient_session', $session['patient_login_id']);
        }
        
        // Log expired admin sessions
        $stmt = $db->query('SELECT admin_id FROM admin_sessions WHERE expires_at < NOW()');
        $expiredAdmins = $stmt->fetchAll();
        foreach ($expiredAdmins as $session) {
            logAudit($session['admin_id'], null, 'session_expired', 'admin_session', $session['admin_id']);
        }
        
        // Delete expired sessions from all tables
        $db->query('DELETE FROM user_sessions WHERE expires_at < NOW()');
        $db->query('DELETE FROM patient_sessions WHERE expires_at < NOW()');
        $db->query('DELETE FROM admin_sessions WHERE expires_at < NOW()');
    } catch (\Exception $e) {
        error_log('Session cleanup error: ' . $e->getMessage());
    }
}

/**
 * Generate and store a CSRF token in the PHP session.
 */
if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }
}

/**
 * Validate a CSRF token against the session token.
 */
if (!function_exists('validateCsrfToken')) {
    function validateCsrfToken(string $token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        return !empty($sessionToken) && hash_equals($sessionToken, $token);
    }
}

/**
 * Require a valid CSRF token for state-changing requests.
 * Always validates for POST, PUT, PATCH, DELETE methods.
 */
if (!function_exists('requireCsrfToken')) {
    function requireCsrfToken(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Only enforce on state-changing methods
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return;
        }
        
        $token = '';
        
        // Check X-CSRF-Token header first
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        
        // Fall back to POST field
        if (empty($token) && isset($_POST['csrf_token'])) {
            $token = $_POST['csrf_token'];
        }
        
        if (empty($token) || !validateCsrfToken($token)) {
            sendSecurityHeaders();
            errorResponse('CSRF token validation failed. Please refresh and try again.', 403);
        }
        
        // Invalidate token after use (one-time use)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['csrf_token']);
    }
}

/**
 * Send security headers to protect against common attacks.
 */
function sendSecurityHeaders(): void {
    // Prevent MIME-type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Permissions policy
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: blob: https: https://maps.gstatic.com https://maps.googleapis.com; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self' https:; frame-src 'self' https://maps.google.com https://www.google.com; media-src 'self' data: blob:; worker-src 'self' blob:;");
    
    // HSTS (6 months) - only over HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=15768000; includeSubDomains');
    }
}
