<?php
/**
 * API Helper Functions (modernized)
 *
 * Common utilities for all API endpoints. Uses centralized Response and DB helpers.
 */

require_once __DIR__ . '/../config_loader.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/db_helper.php';

// ─── Response Helpers (delegates to Response class) ─────────────────────────
function jsonResponse(mixed $data, int $statusCode = 200): void {
    // Keep for backward compatibility internally — delegate to Response
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function successResponse(mixed $data = null, string $message = 'Success'): void {
    Response::ok($message, $data, 200);
}

function errorResponse(string $message, int $statusCode = 400, mixed $errors = null): void {
    Response::error($message, is_array($errors) ? $errors : [], $statusCode);
}

// ─── Request Helpers ──────────────────────────────────────────────────────
function getJsonInput(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        Response::error('Invalid JSON input', [], 400);
    }
    return $data ?? [];
}

function getParam(string $key, mixed $default = null): mixed {
    if (isset($_GET[$key])) return $_GET[$key];
    if (isset($_POST[$key])) return $_POST[$key];
    static $jsonInput = null;
    if ($jsonInput === null) {
        $jsonInput = getJsonInput();
    }
    return $jsonInput[$key] ?? $default;
}

function getMethod(): string {
    return $_SERVER['REQUEST_METHOD'] ?? 'CLI';
}

function requireMethod(string ...$methods): void {
    $method = getMethod();
    if ($method === 'CLI') return;
    if (!in_array($method, $methods)) {
        Response::error('Method not allowed. Allowed: ' . implode(', ', $methods), [], 405);
    }
}

// ─── Rate Limiting ───────────────────────────────────────────────────────
function checkRateLimit(string $identifier = '', int $maxAttempts = 0, int $windowSeconds = 0): void {
    if (empty($identifier)) {
        $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    if ($maxAttempts <= 0) $maxAttempts = intval(cfg('RATE_LIMIT_MAX', 100));
    if ($windowSeconds <= 0) $windowSeconds = intval(cfg('RATE_LIMIT_WINDOW', 60));

    $rateLimitDir = __DIR__ . '/../../server-data/ratelimit';
    if (!is_dir($rateLimitDir)) {
        @mkdir($rateLimitDir, 0755, true);
    }

    $file = $rateLimitDir . '/' . md5($identifier) . '.json';
    $data = ['count' => 0, 'reset' => time() + $windowSeconds];
    if (file_exists($file)) {
        $existing = @json_decode(@file_get_contents($file), true);
        if ($existing && isset($existing['reset'])) {
            if (time() < $existing['reset']) {
                $data = $existing;
            }
        }
    }

    $data['count']++;

    if ($data['count'] > $maxAttempts) {
        $retryAfter = max(0, $data['reset'] - time());
        header('Retry-After: ' . $retryAfter);
        Response::error('Rate limit exceeded. Try again later.', [
            'retry_after' => $retryAfter,
            'limit' => $maxAttempts,
            'window' => $windowSeconds,
        ], 429);
    }

    @file_put_contents($file, json_encode($data), LOCK_EX);
}

// ─── Input Validation ────────────────────────────────────────────────────
function validateRequired(array $data, array $fields): ?array {
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            $missing[] = $field;
        }
    }
    return empty($missing) ? null : $missing;
}

function sanitizeString(string $value): string {
    $value = strip_tags($value);
    $value = trim($value);
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function sanitizeEmail(string $email): string {
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : '';
}

function sanitizePhone(string $phone): string {
    return preg_replace('/[^0-9+\-() ]/', '', trim($phone));
}

function validatePasswordStrength(string $password): ?string {
    if (strlen($password) < 8) return 'Password must be at least 8 characters long';
    if (!preg_match('/[A-Z]/', $password)) return 'Password must contain at least one uppercase letter';
    if (!preg_match('/[a-z]/', $password)) return 'Password must contain at least one lowercase letter';
    if (!preg_match('/[0-9]/', $password)) return 'Password must contain at least one number';
    if (!preg_match('/[!@#$%^&*()_\-+={}\[\]|:;"\'<>.,?\\/~`]/', $password)) return 'Password must contain at least one special character';
    return null;
}

// ─── Pagination ─────────────────────────────────────────────────────────
function getPaginationParams(): array {
    $page = max(1, (int) getParam('page', 1));
    $limit = min(100, max(1, (int) getParam('limit', 20)));
    $offset = ($page - 1) * $limit;
    return ['page' => $page, 'limit' => $limit, 'offset' => $offset];
}

function paginatedResponse(array $items, int $total, int $page, int $limit): void {
    $totalPages = ($limit > 0) ? (int) ceil($total / $limit) : 0;
    successResponse([
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_more' => $page < $totalPages,
        ],
    ]);
}

// ─── File Upload Helpers ─────────────────────────────────────────────────
function handleFileUpload(string $fieldName, string $subDir = ''): ?string {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $file = $_FILES[$fieldName];
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        Response::error('File too large. Maximum: ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB', [], 413);
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        Response::error('File type not allowed. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS), [], 415);
    }
    $allowedMimes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mimeType, $allowedMimes)) {
        Response::error('Invalid file type (MIME: ' . $mimeType . ')', [], 415);
    }
    $uploadDir = UPLOAD_DIR . '/' . ($subDir ? $subDir . '/' : '');
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    $filename = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $filepath = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        Response::error('Failed to save uploaded file', [], 500);
    }
    return rtrim(UPLOAD_URL, '/') . '/' . $filename;
}

// ─── Audit Logging (uses DB helper; writes audit table) ────────────────────
function logAudit(?int $userId, ?int $patientId, string $action, string $entityType, ?int $entityId = null, $oldValues = null, $newValues = null): void {
    try {
        // Verify user exists
        $safeUserId = null;
        if ($userId !== null && $userId > 0) {
            $chk = DB::fetchOne('SELECT id FROM users WHERE id = :id', [':id' => $userId]);
            if ($chk) $safeUserId = $userId;
        }
        // Verify patient exists
        $safePatientId = null;
        if ($patientId !== null && $patientId > 0) {
            $chk = DB::fetchOne('SELECT id FROM patients WHERE id = :id', [':id' => $patientId]);
            if ($chk) $safePatientId = $patientId;
        }

        $params = [
            ':user_id' => $safeUserId,
            ':patient_id' => $safePatientId,
            ':action' => $action,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':old_values' => $oldValues ? json_encode($oldValues) : null,
            ':new_values' => $newValues ? json_encode($newValues) : null,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? ($_SERVER['SERVER_ADDR'] ?? '127.0.0.1'),
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
        ];

        DB::execute('INSERT INTO audit_logs (user_id, patient_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (:user_id, :patient_id, :action, :entity_type, :entity_id, :old_values, :new_values, :ip_address, :user_agent)', $params);
    } catch (Throwable $e) {
        error_log('Audit log failed: ' . $e->getMessage());
    }
}

// ─── CORS & Pre-flight ───────────────────────────────────────────────────
function handleCors(): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
