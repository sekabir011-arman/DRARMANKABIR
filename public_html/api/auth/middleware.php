<?php
/**
 * Authentication Middleware
 * 
 * Validates session tokens and provides current user context.
 * Include this at the top of any protected API endpoint.
 * 
 * Authentication sources (checked in order):
 * 1. Authorization: Bearer <token> header
 * 2. session_token cookie (secure, HttpOnly)
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
 * Get Bearer token from Authorization header or secure cookie.
 * NEVER accepts tokens from URL parameters ($_GET).
 */
function getBearerToken(): ?string {
    $headers = '';
    
    // Check Authorization header first
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
    
    // Fall back to secure HttpOnly cookie (for cookie-based auth)
    if (isset($_COOKIE['session_token']) && !empty($_COOKIE['session_token'])) {
        return $_COOKIE['session_token'];
    }
    
    return null;
}

/**
 * Create a new session for a user
 */
function createSession(int $userId): string {
    $db = Database::getInstance();
    
    // Generate secure token (256-bit random)
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
 * Clean up expired sessions
 */
function cleanupExpiredSessions(): void {
    $db = Database::getInstance();
    $db->query('DELETE FROM user_sessions WHERE expires_at < NOW()');
}

/**
 * Generate and store a CSRF token.
 * Token is stored in the database session record.
 */
function generateCsrfToken(): string {
    $token = bin2hex(random_bytes(32));
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['csrf_token'] = $token;
    return $token;
}

/**
 * Validate a CSRF token.
 * Checks the token against the session token in the user's session.
 */
function validateCsrfToken(string $token): bool {
    if (empty($token)) return false;
    
    // Check session-stored token
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!empty($sessionToken) && hash_equals($sessionToken, $token)) {
        return true;
    }
    
    // Also check X-CSRF-Token header or request body
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_XSRF_TOKEN'] ?? '';
    if (!empty($headerToken) && hash_equals($sessionToken, $headerToken)) {
        return true;
    }
    
    return false;
}

/**
 * Enforce CSRF validation for state-changing requests.
 * Call this at the start of POST/PUT/PATCH/DELETE handlers.
 */
function enforceCsrf(): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    
    // Only enforce for state-changing methods
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
        return;
    }
    
    // Get CSRF token from header or body
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_XSRF_TOKEN'] ?? '';
    if (empty($token)) {
        $input = getJsonInput();
        $token = $input['csrf_token'] ?? '';
    }
    
    if (!validateCsrfToken($token)) {
        errorResponse('CSRF validation failed. Invalid or missing CSRF token.', 403);
    }
}
