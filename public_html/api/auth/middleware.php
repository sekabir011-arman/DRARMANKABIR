<?php
/**
 * Authentication Middleware
 * 
 * Validates session tokens and provides current user context.
 * Supports both Authorization: Bearer header and HttpOnly cookies.
 * Include this at the top of any protected API endpoint.
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../helpers.php';

/**
 * Get the bearer token from Authorization header or secure cookie.
 * NEVER from URL parameters ($_GET) - that would expose tokens in logs/referrers.
 */
function getBearerToken(): ?string {
    // 1. Check Authorization header (preferred - not auto-sent by browsers)
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
    
    // 2. Fall back to secure HttpOnly cookie (same-domain use only)
    if (isset($_COOKIE['session_token']) && !empty($_COOKIE['session_token'])) {
        return $_COOKIE['session_token'];
    }
    
    return null;
}

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
        
        // Periodic cleanup of expired sessions (1% chance)
        if (mt_rand(1, 100) === 1) {
            cleanupExpiredSessions();
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
 * Permissions are derived server-side from the role - never trust frontend.
 */
function hasPermission(array $user, string $permission): bool {
    // Role-based permissions (server-authoritative, never trust client-supplied role)
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
 * Create a new session for a user.
 * Removed: refresh_token (not used by any endpoint - would be dead security code).
 * Uses cryptographically secure random bytes for the token.
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
 * Destroy a session (logout).
 * Completely removes the session from the database.
 */
function destroySession(string $token): void {
    $db = Database::getInstance();
    $stmt = $db->prepare('DELETE FROM user_sessions WHERE token = :token');
    $stmt->execute([':token' => $token]);
}

/**
 * Clean up expired sessions from the database.
 * Should be called periodically to prevent session table bloat.
 */
function cleanupExpiredSessions(): void {
    $db = Database::getInstance();
    
    // Log expired sessions before deleting
    $expiredStmt = $db->query('SELECT user_id, token FROM user_sessions WHERE expires_at < NOW()');
    $expired = $expiredStmt->fetchAll();
    foreach ($expired as $session) {
        logAudit((int)$session['user_id'], null, 'session_expired', 'session', null, null, [
            'token' => substr($session['token'], , 8) . '...',
        ]);
    }
    
    $db->query('DELETE FROM user_sessions WHERE expires_at < NOW()');
}

// ─── CSRF Protection ─────────────────────────────────────────────────────

/**
 * Generate a CSRF token derived from the session token.
 * Stateless - no DB storage needed. Uses HMAC-SHA256 with server secret.
 */
function generateCsrfToken(string $sessionToken): string {
    return hash_hmac('sha256', 'csrf:' . $sessionToken, JWT_SECRET);
}

/**
 * Validate a CSRF token against the session token.
 * Uses hash_equals() to prevent timing attacks.
 */
function validateCsrfToken(string $sessionToken, string $csrfToken): bool {
    if (empty($sessionToken) || empty($csrfToken)) {
        return false;
    }
    $expected = generateCsrfToken($sessionToken);
    return hash_equals($expected, $csrfToken);
}

/**
 * Require CSRF validation for state-changing requests (POST, PUT, PATCH, DELETE).
 * When using cookie-based auth, browsers auto-send cookies, so CSRF is needed.
 * When using Bearer auth (Authorization header), CSRF is not needed because
 * browsers don't auto-attach Authorization headers cross-origin.
 * 
 * This function checks: if the request came via cookie auth (no Bearer header),
 * then it requires the X-CSRF-Token header to be valid.
 */
function requireCsrfForStateChanging(string $sessionToken): void {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    
    // Only check state-changing methods
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
        return;
    }
    
    // If Bearer header was used, no CSRF needed (browsers don't auto-send Bearer)
    $bearerProvided = false;
    if (isset($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/Bearer\s(\S+)/', $_SERVER['HTTP_AUTHORIZATION'])) {
        $bearerProvided = true;
    }
    if ($bearerProvided) {
        return;
    }
    
    // Cookie-based auth requires CSRF validation
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($headerToken)) {
        errorResponse('CSRF token is required for state-changing requests. Include X-CSRF-Token header.', 403);
    }
    
    if (!validateCsrfToken($sessionToken, $headerToken)) {
        errorResponse('CSRF token validation failed. Please refresh your session and try again.', 403);
    }
}
