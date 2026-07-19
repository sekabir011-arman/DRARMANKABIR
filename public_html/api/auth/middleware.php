<?php
/**
 * Authentication Middleware
 * 
 * Validates session tokens and provides current user context.
 * Include this at the top of any protected API endpoint.
 * 
 * Accepts authentication via:
 * 1. Authorization: Bearer <token> header (primary)
 * 2. X-Session-Token header (alternative)
 * 3. session_token cookie (for SPA on same domain)
 * 
 * NEVER accepts tokens from URL parameters ($_GET).
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
            // Update last activity (throttled: once per minute max)
            // We avoid writing on every request for performance
            // Session expiry is checked on each request via the SQL above
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
 * Require specific permission. Sends 403 if not authorized.
 */
function requirePermission(string $permission): array {
    $user = requireAuth();
    
    if (!hasPermission($user, $permission)) {
        errorResponse('Access denied. Missing permission: ' . $permission, 403);
    }
    
    return $user;
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
 * Get Bearer token from Authorization header, X-Session-Token header, or secure cookie.
 * NEVER reads from URL parameters ($_GET).
 */
function getBearerToken(): ?string {
    // 1. Check Authorization header (primary)
    $headers = '';
    
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $headers = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $headers = $requestHeaders['Authorization'] ?? $requestHeaders['authorization'] ?? '';
    }
    
    if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
        return $matches[1];
    }
    
    // 2. Check X-Session-Token header (alternative for clients that can't set Authorization)
    if (isset($_SERVER['HTTP_X_SESSION_TOKEN'])) {
        return $_SERVER['HTTP_X_SESSION_TOKEN'];
    }
    
    // 3. Check session_token cookie (for SPA on same domain)
    if (isset($_COOKIE['session_token'])) {
        return $_COOKIE['session_token'];
    }
    
    // 4. Check POST body for token (used in some legacy flows)
    $input = getJsonInput();
    if (isset($input['token'])) {
        return $input['token'];
    }
    
    return null;
}

/**
 * Create a new session for a user.
 * Returns the session token string.
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
}

/**
 * Destroy all sessions for a user (force logout everywhere)
 */
function destroyAllUserSessions(int $userId): void {
    $db = Database::getInstance();
    $stmt = $db->prepare('DELETE FROM user_sessions WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $userId]);
}

/**
 * Clean up expired sessions
 */
function cleanupExpiredSessions(): void {
    $db = Database::getInstance();
    $stmt = $db->query('DELETE FROM user_sessions WHERE expires_at < NOW()');
    $count = $stmt->rowCount();
    if ($count > ) {
        error_log("Cleaned up {$count} expired user sessions");
    }
}

/**
 * Clean up expired patient sessions
 */
function cleanupExpiredPatientSessions(): void {
    $db = Database::getInstance();
    $stmt = $db->query('DELETE FROM patient_sessions WHERE expires_at < NOW()');
    $count = $stmt->rowCount();
    if ($count > ) {
        error_log("Cleaned up {$count} expired patient sessions");
    }
}

/**
 * Generate a CSRF token and store it in the session.
 * Returns the token string.
 */
function generateCsrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

/**
 * Validate a CSRF token against the stored session token.
 */
function validateCsrfToken(string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($sessionToken)) {
        return false;
    }
    return hash_equals($sessionToken, $token);
}

/**
 * Require a valid CSRF token for state-changing requests.
 * Call this on POST, PUT, PATCH, DELETE endpoints.
 * Token can come from X-CSRF-Token header or _csrf body field.
 */
function requireCsrfToken(): void {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    
    // Only enforce for state-changing methods
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
        return;
    }
    
    // Get token from header or body
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token)) {
        $input = getJsonInput();
        $token = $input['_csrf'] ?? '';
    }
    
    if (empty($token) || !validateCsrfToken($token)) {
        errorResponse('CSRF token validation failed.', 403);
    }
}

/**
 * Send security headers as a backup to .htaccess.
 * Called automatically at the start of each API request.
 */
function sendSecurityHeaders(): void {
    // Prevent MIME-type sniffing
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header('X-XSS-Protection: 1; mode=block');
    }
}

// Auto-send security headers on include
sendSecurityHeaders();
