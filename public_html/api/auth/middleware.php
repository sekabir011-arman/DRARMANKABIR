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
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../helpers.php';

// Auto-send security headers when middleware is loaded
sendSecurityHeaders();

// ─── Staff User Authentication (users table) ───────────────────────────────

/**
 * Get the authenticated staff user from the session token.
 * Checks user_sessions joined with users table.
 */
function getAuthUser(): ?array {
    $token = getBearerToken();
    if (!$token) return null;
    
    try {
        $db = Database::getInstance();
        
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
            return $user;
        }
        
        return null;
    } catch (\Exception $e) {
        error_log('Auth middleware error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Require staff authentication. Sends 401 if not authenticated.
 */
function requireAuth(): array {
    $user = getAuthUser();
    if (!$user) {
        errorResponse('Authentication required. Please log in.', 401);
    }
    return $user;
}

// ─── Patient Authentication (patient_login + patient_sessions) ─────────────

/**
 * Get the authenticated patient from the session token.
 * Checks patient_sessions joined with patient_login and patients tables.
 */
function getPatientAuthUser(): ?array {
    $token = getBearerToken();
    if (!$token) return null;
    
    try {
        $db = Database::getInstance();
        
        $stmt = $db->prepare('
            SELECT pl.*, p.full_name, p.name_bn, p.gender, p.date_of_birth, 
                   p.register_number, p.photo_url, p.id as patient_record_id,
                   s.token, s.expires_at
            FROM patient_sessions s 
            JOIN patient_login pl ON s.patient_login_id = pl.id
            JOIN patients p ON pl.patient_id = p.id
            WHERE s.token = :token 
              AND s.expires_at > NOW() 
              AND pl.status = "approved"
            LIMIT 1
        ');
        $stmt->execute([':token' => $token]);
        $patient = $stmt->fetch();
        
        if ($patient) {
            // Update last activity
            $updateStmt = $db->prepare('UPDATE patient_sessions SET last_activity = NOW() WHERE token = :token');
            $updateStmt->execute([':token' => $token]);
            return $patient;
        }
        
        return null;
    } catch (\Exception $e) {
        error_log('Patient auth middleware error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Require patient authentication. Sends 401 if not authenticated.
 */
function requirePatientAuth(): array {
    $patient = getPatientAuthUser();
    if (!$patient) {
        errorResponse('Patient authentication required. Please log in.', 401);
    }
    return $patient;
}

// ─── Admin Content Authentication (admin_accounts + admin_sessions) ─────────

/**
 * Create a new admin session (used by admin_login.php).
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
 * Get the authenticated admin content user from the session token.
 * Checks admin_sessions joined with admin_accounts.
 */
function getAdminAuthUser(): ?array {
    $token = getBearerToken();
    if (!$token) return null;
    
    try {
        $db = Database::getInstance();
        
        $stmt = $db->prepare('
            SELECT a.*, s.token, s.expires_at 
            FROM admin_sessions s 
            JOIN admin_accounts a ON s.admin_id = a.id 
            WHERE s.token = :token 
              AND s.expires_at > NOW() 
              AND a.is_active = 1
            LIMIT 1
        ');
        $stmt->execute([':token' => $token]);
        $admin = $stmt->fetch();
        
        if ($admin) {
            // Update last activity
            $updateStmt = $db->prepare('UPDATE admin_sessions SET last_activity = NOW() WHERE token = :token');
            $updateStmt->execute([':token' => $token]);
            return $admin;
        }
        
        return null;
    } catch (\Exception $e) {
        error_log('Admin auth middleware error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Require admin content authentication. Sends 401 if not authenticated.
 */
function requireAdminContent(): array {
    $admin = getAdminAuthUser();
    if (!$admin) {
        errorResponse('Admin authentication required. Please log in.', 401);
    }
    // Ensure the user is an active admin
    if (empty($admin['is_active'])) {
        errorResponse('Admin account is deactivated.', 403);
    }
    return $admin;
}

// ─── Role & Permission Verification (staff users) ──────────────────────────

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
 * Require admin role (hospital system admin).
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
    $user = requireAuth();
    if (!hasPermission($user, $permission)) {
        errorResponse('Access denied. You do not have the "' . $permission . '" permission.', 403);
    }
    return $user;
}

// ─── Token Extraction ──────────────────────────────────────────────────────

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

// ─── Session Management ────────────────────────────────────────────────────

/**
 * Create a new session for a staff user.
 */
function createSession(int $userId): string {
    $db = Database::getInstance();
    
    // Generate secure token (no refresh_token - using rotation via re-auth instead)
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
 * Destroy a session token across all session types (logout).
 * Checks user_sessions, patient_sessions, and admin_sessions.
 */
function destroySession(string $token): void {
    $db = Database::getInstance();
    
    // Try to delete from all session tables
    $tables = ['user_sessions', 'patient_sessions', 'admin_sessions'];
    foreach ($tables as $table) {
        try {
            $stmt = $db->prepare("DELETE FROM $table WHERE token = :token");
            $stmt->execute([':token' => $token]);
        } catch (\Exception $e) {
            // Table might not exist - ignore
            error_log("Session delete error on $table: " . $e->getMessage());
        }
    }
    
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
 * Clean up expired sessions across all session types.
 */
function cleanupExpiredSessions(): void {
    $sessionTables = [
        'user_sessions' => 'user_id',
        'patient_sessions' => 'patient_login_id',
        'admin_sessions' => 'admin_id',
    ];
    
    foreach ($sessionTables as $table => $idColumn) {
        try {
            $db = Database::getInstance();
            $stmt = $db->query("SELECT $idColumn FROM $table WHERE expires_at < NOW()");
            $expired = $stmt->fetchAll();
            
            foreach ($expired as $session) {
                logAudit($session[$idColumn] ?? null, null, 'session_expired', 'session', $session[$idColumn] ?? null);
            }
            
            $db->query("DELETE FROM $table WHERE expires_at < NOW()");
        } catch (\Exception $e) {
            error_log("Session cleanup error on $table: " . $e->getMessage());
        }
    }
}

// ─── CSRF Protection ───────────────────────────────────────────────────────
// NOTE: generateCsrfToken() and validateCsrfToken() are defined in helpers.php

/**
 * Require a valid CSRF token for state-changing requests.
 * Always validates for POST, PUT, PATCH, DELETE methods.
 */
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
        errorResponse('CSRF token validation failed. Please refresh and try again.', 403);
    }
    
    // Invalidate token after use (one-time use)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    unset($_SESSION['csrf_token']);
}

/**
 * Send security headers to protect against common attacks.
 * Called automatically when middleware.php is loaded.
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
