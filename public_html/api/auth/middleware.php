<?php
/**
 * Authentication Middleware
 * 
 * Validates session tokens and provides current user context.
 * Include this at the top of any protected API endpoint.
 * 
 * Supports authentication via:
 *   1. Authorization: Bearer <token> header
 *   2. Secure HttpOnly 'session_token' cookie
 * 
 * Never accepts authentication from URL parameters ($_GET).
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../helpers.php';

/**
 * Get the authenticated user from the session token.
 * Accepts token from Authorization header or secure cookie.
 * Never from URL parameters.
 * Returns user array or null if not authenticated.
 */
function getAuthUser(): ?array {
    $token = getBearerToken();
    if (!$token) {
        $token = $_COOKIE['session_token'] ?? null;
    }
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
 * Never trust frontend role values - always check server-side.
 */
function hasPermission(array $user, string $permission): bool {
    // Role-based permissions - server-side only, never trust client
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
 * Get Bearer token from Authorization header only.
 * Never accepts tokens from URL parameters ($_GET['token']).
 */
function getBearerToken(): ?string {
    $headers = '';
    
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $headers = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $headers = $requestHeaders['Authorization'] ?? $requestHeaders['authorization'] ?? '';
    }
    
    if (empty($headers)) {
        return null;
    }
    
    if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
        return $matches[1];
    }
    
    return null;
}

/**
 * Create a new session for a user.
 * Generates a secure token, stores in user_sessions table.
 * No refresh_token is stored - all tokens are primary session tokens.
 */
function createSession(int $userId): string {
    $db = Database::getInstance();
    
    // Generate secure token (256-bit random)
    $token = bin2hex(random_bytes(32));
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
 * Deletes both the DB session and clears the session cookie.
 */
function destroySession(?string $token = null): void {
    $db = Database::getInstance();
    
    // If no token provided, try cookie
    if (!$token) {
        $token = $_COOKIE['session_token'] ?? null;
    }
    
    if ($token) {
        $stmt = $db->prepare('DELETE FROM user_sessions WHERE token = :token');
        $stmt->execute([':token' => $token]);
    }
    
    // Clear the session cookie regardless
    if (isset($_COOKIE['session_token'])) {
        setcookie('session_token', '', [
            'expires' => time() - 360,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE['session_token']);
    }
    
    // Destroy PHP session if active
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
    }
}

/**
 * Clean up expired sessions from the database.
 * Should be called periodically.
 */
function cleanupExpiredSessions(): void {
    $db = Database::getInstance();
    $stmt = $db->prepare('DELETE FROM user_sessions WHERE expires_at < NOW()');
    $stmt->execute();
    $deleted = $stmt->rowCount();
    
    // Also clean expired patient sessions
    $stmt = $db->prepare('DELETE FROM patient_sessions WHERE expires_at < NOW()');
    $stmt->execute();
    $deletedPatients = $stmt->rowCount();
    
    if ($deleted >  || $deletedPatients > ) {
        error_log("Session cleanup: removed {$deleted} user sessions and {$deletedPatients} patient sessions");
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
    
    // Permissions policy - restrict sensitive APIs
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: blob: https: https://maps.gstatic.com https://maps.googleapis.com; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self' https:; frame-src 'self' https://maps.google.com https://www.google.com; media-src 'self' data: blob:; worker-src 'self' blob:;");
}
