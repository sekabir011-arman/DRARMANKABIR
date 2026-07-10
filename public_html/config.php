<?php
/**
 * Dr. Arman Kabir Care - Application Configuration
 * 
 * Security: Never commit this file to version control.
 * On cPanel, place this outside public_html or protect with .htaccess.
 */

// ─── Database Configuration ────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'drarmank_care');
define('DB_USER', 'drarmank_care_user');
define('DB_PASS', ''); // Set via environment variable or secure config
define('DB_CHARSET', 'utf8mb4');

// ─── Application Configuration ─────────────────────────────────────────────
define('APP_NAME', 'Dr. Arman Kabir Care');
define('APP_VERSION', '2.0.0');
define('APP_URL', 'https://drarmankabir.com');
define('API_URL', APP_URL . '/api');

// ─── Security Configuration ────────────────────────────────────────────────
define('JWT_SECRET', ''); // Generate: bin2hex(random_bytes(32))
define('JWT_EXPIRY', 86400); // 24 hours in seconds
define('SESSION_LIFETIME', 86400 * 7); // 7 days
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hour

// ─── Upload Configuration ──────────────────────────────────────────────────
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

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
