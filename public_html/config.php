<?php
/**
 * Dr. Arman Kabir Care - Application Configuration
 * 
 * Security: Never commit this file to version control.
 * On cPanel, place this outside public_html or protect with .htaccess.
 */

// ─── Environment Detection ─────────────────────────────────────────────────
// On cPanel, you can set these in .env file outside public_html
// or directly in your cPanel environment variables.
// The priority is: getenv() > .env file > config defaults

$dotenvFile = __DIR__ . '/../.env';
if (file_exists($dotenvFile)) {
    $lines = file($dotenvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!getenv($key)) {
                putenv("$key=$value");
            }
        }
    }
}

// ─── Database Configuration ────────────────────────────────────────────────
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'drarmank_care');
define('DB_USER', getenv('DB_USER') ?: 'drarmank_care_user');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ─── Application Configuration ─────────────────────────────────────────────
define('APP_NAME', 'Dr. Arman Kabir Care');
define('APP_VERSION', '2.0.0');
define('APP_URL', getenv('APP_URL') ?: 'https://drarmankabir.com');
define('API_URL', APP_URL . '/api');

// ─── Security Configuration ────────────────────────────────────────────────
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'change-this-to-a-random-secret-in-production');
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
