<?php
/**
 * Authentication Middleware
 * 
 * Validates session tokens and provides current user context.
 * Include this at the top of any protected API endpoint.
 * 
 * Authentication sources (in order of precedence):
 * 1. Authorization: Bearer <token> header
 * 2. X-Session-Token header
 * 3. session_token cookie (HttpOnly, Secure, SameSite=Lax)
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../helpers.php';

/**
 * Get the authenticated user from the session token.
 * Returns user array or null if not authenticated.
 */
function getAuthUser(): ?array {
    $token = getBearerToken();
    if (!$token) return null;
    
    try {
        $db = Database::getInstance();
        
        // Find valid session
        $stmt = $db->prepare('
            SELECT u.*, s.token, s.expires_at 
            FROM user_sessions s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.token = :token 
              AND s.expires_at > NOW() 
              AND u.is_active = 1
            LIMIT 1
        ');
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Update last activity
            $updateStmt = $db->prepare('UPDATE user_sessions SET last_activity = NOW() WHERE token = :token');
            $updateStmt->execute([':token' => $token]);
        }
        
        return $user ?: null;
    } catch (\Exception $e) {
        error_log('Auth middleware error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Require authentication. Sends 401 if not authenticated.
 */
function requireAuth(): array {
    $user = getAuthUser();
    if (!$user) {
        errorResponse('Authentication required. Please log in.', 401);
    }
    return $user;
}

/**
 * Require specific role(s). Sends 403 if not authorized.
 */
function requireRole(array $allowedRoles): array {
    $user = requireAuth();
    
    if (!in_array($user['role'], $allowedRoles)) {
        errorResponse('Access denied. Insufficient permissions.', 403, [
            'required_roles' => $allowedRoles,
            'your_role' => $user['role'],
        ]);
    }
    
    return $user;
}

/**
 * Require admin role.
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
 * Require a specific permission. Sends 403 if not authorized.
 */
function requirePermission(string $permission): array {
    $user = requireAuth();
    if (!hasPermission($user, $permission)) {
        errorResponse('Access denied. Insufficient permissions.', 403, [
            'required_permission' => $permission,
        ]);
    }
    return $user;
}

/**
 * Get authentication token from request.
 * Checks (in order): Authorization header, X-Session-Token header, session_token cookie.
 * NEVER accepts tokens from URL parameters (GET).
 */
function getBearerToken(): ?string {
    $headers = '';
    
    // 1. Check Authorization header (primary)
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
    
    // 2. Check X-Session-Token header (secondary)
    if (isset($_SERVER['HTTP_X_SESSION_TOKEN'])) {
        return $_SERVER['HTTP_X_SESSION_TOKEN'];
    }
    
    // 3. Check session_token cookie (for cookie-based auth)
    if (isset($_COOKIE['session_token']) && !empty($_COOKIE['session_token'])) {
        return $_COOKIE['session_token'];
    }
    
    // NEVER fall back to $_GET['token'] - that would expose tokens in URLs
    // URLs are logged by servers, shared in referrers, and visible in browser history
    
    return null;
}

/**
 * Create a new session for a user
 */
function createSession(int $userId): string {
    $db = Database::getInstance();
    
    // Generate secure token (64 bytes = 128 hex chars)
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
 * Destroy a session (logout)
 */
function destroySession(string $token): void {
    $db = Database::getInstance();
    $stmt = $db->prepare('DELETE FROM user_sessions WHERE token = :token');
    $stmt->execute([':token' => $token]);
    
    // Also clear the session cookie
    if (isset($_COOKIE['session_token'])) {
        setcookie('session_token', '', [
            'expires' => time() - 360,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

/**
 * Clean up expired sessions
 */
function cleanupExpiredSessions(): void {
    try {
        $db = Database::getInstance();
        
        // Log expired sessions before deleting (batch audit)
        $expiredStmt = $db->query('
            SELECT s.id, s.user_id, u.email 
            FROM user_sessions s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.expires_at < NOW()
        ');
        $expiredSessions = $expiredStmt->fetchAll();
        
        foreach ($expiredSessions as $session) {
            logAudit($session['user_id'], null, 'session_expired', 'session', $session['id']);
        }
        
        $db->query('DELETE FROM user_sessions WHERE expires_at < NOW()');
    } catch (\Exception $e) {
        error_log('Session cleanup error: ' . $e->getMessage());
    }
}

/**
 * Generate a CSRF token and store it in the database session
 */
function generateCsrfToken(): string {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

/**
 * Validate a CSRF token against the session-stored token
 */
function validateCsrfToken(string $token): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($sessionToken)) {
        return false;
    }
    return hash_equals($sessionToken, $token);
}

/**
 * Require a valid CSRF token for state-changing requests.
 * Checks X-CSRF-Token header, then _csrf POST parameter.
 */
function requireCsrfToken(): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    // Only check state-changing methods
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
        return;
    }
    
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '';
    
    if (empty($token) || !validateCsrfToken($token)) {
        errorResponse('Invalid or missing CSRF token.', 403);
    }
}

// ─── Auto-cleanup expired sessions (runs once per request with 1% probability) ───
if (mt_rand(1, 100) === 1) {
    cleanupExpiredSessions();
}
