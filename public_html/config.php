<?php
/**
 * Dr. Arman Kabir Care - Application Configuration
 * 
 * Security: Never commit this file to version control.
 * On cPanel, place this outside public_html or protect with .htaccess.
 *
 * NOTE: putenv() is disabled on this server. We read .env directly and
 * define constants. For maximum security, set real env vars via cPanel.
 */

// ─── Environment Detection ─────────────────────────────────────────────────
// First check OS-level environment variables (set via cPanel)
// Then fall back to .env file outside public_html
// Finally use hardcoded defaults (only for development)

$envConfig = [];

// 1. Check OS environment variables first
$envVars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'JWT_SECRET', 'APP_URL'];
foreach ($envVars as $var) {
    $val = getenv($var);
    if ($val !== false && $val !== '') {
        $envConfig[$var] = $val;
    }
}

// 2. Load from .env file if OS env vars are not set
$dotenvFile = __DIR__ . '/../.env';
if (file_exists($dotenvFile) && empty($envConfig)) {
    $lines = file($dotenvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            $envConfig[$key] = $value;
        }
    }
}

// ─── Database Configuration ────────────────────────────────────────────────
define('DB_HOST', $envConfig['DB_HOST'] ?? '127.0.0.1');
define('DB_NAME', $envConfig['DB_NAME'] ?? 'drarmank_drarmank_care');
define('DB_USER', $envConfig['DB_USER'] ?? 'drarmank_drarmank_care_user');
define('DB_PASS', $envConfig['DB_PASS'] ?? 'zosid01197247219');
define('DB_CHARSET', 'utf8mb4');
function env(string $key, mixed $default = null): mixed {
    // Check $_ENV first (populated from .env file)
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    // Check getenv() as fallback (for system-level env vars)
    $val = getenv($key);
    if ($val !== false && $val !== '') {
        return $val;
    }
    return $default;
}

// ─── Database Configuration ────────────────────────────────────────────────
define('DB_HOST', env('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', env('DB_NAME') ?: 'drarmank_drarmank_care');
define('DB_USER', env('DB_USER') ?: 'drarmank_drarmank_care_user');
define('DB_PASS', env('DB_PASS') ?: 'zosid01197247219');
define('DB_CHARSET', 'utf8mb4');

// ─── Application Configuration ─────────────────────────────────────────────
define('APP_NAME', 'Dr. Arman Kabir Care');
define('APP_VERSION', '2.0.0');
define('APP_URL', env('APP_URL') ?: 'https://drarmankabir.com');
define('API_URL', APP_URL . '/api');

// ─── Security Configuration ────────────────────────────────────────────────
define('JWT_SECRET', env('JWT_SECRET') ?: 'change-this-to-a-random-secret-in-production');
define('JWT_EXPIRY', 86400); // 24 hours in seconds
define('SESSION_LIFETIME', 86400 * 7); // 7 days
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hour

// ─── Upload Configuration ──────────────────────────────────────────────────
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('UPLOAD_URL', '/uploads');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx']);

// ─── Rate Limiting ─────────────────────────────────────────────────────────
define('RATE_LIMIT_MAX', 100);
define('RATE_LIMIT_WINDOW', 60); // seconds

// ─── Error Reporting ───────────────────────────────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php-error.log');

// ─── Timezone ──────────────────────────────────────────────────────────────
date_default_timezone_set('Asia/Dhaka');
