<?php
/**
 * Application Configuration (hardened)
 *
 * NOTE: This file must NOT contain production secrets. Set them via cPanel
 * environment variables or an .env file placed outside the web root.
 *
 * This refactor replaced embedded defaults with placeholders and uses the
 * cfg() helper to read environment values. If a required secret is missing,
 * the app will log a warning and endpoints will return a generic error.
 */

require_once __DIR__ . '/config_loader.php';

// Error reporting (do NOT display errors to end users)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
// Ensure logs directory is outside public_html in deployment. Default here is ../logs
if (!ini_get('error_log')) {
    ini_set('error_log', __DIR__ . '/../logs/php-error.log');
}

// Database configuration — read from environment via cfg(); use placeholder defaults
if (!defined('DB_HOST')) define('DB_HOST', cfg('DB_HOST', null));
if (!defined('DB_NAME')) define('DB_NAME', cfg('DB_NAME', null));
if (!defined('DB_USER')) define('DB_USER', cfg('DB_USER', null));
if (!defined('DB_PASS')) define('DB_PASS', cfg('DB_PASS', null));
if (!defined('DB_CHARSET')) define('DB_CHARSET', cfg('DB_CHARSET', 'utf8mb4'));

// Application configuration
if (!defined('APP_NAME')) define('APP_NAME', cfg('APP_NAME', 'Dr. Arman Kabir Care'));
if (!defined('APP_VERSION')) define('APP_VERSION', cfg('APP_VERSION', '2.0.0'));
if (!defined('APP_URL')) define('APP_URL', cfg('APP_URL', null));
if (!defined('API_URL')) define('API_URL', (APP_URL ? APP_URL : '') . '/api');

// Security configuration
if (!defined('JWT_SECRET')) define('JWT_SECRET', cfg('JWT_SECRET', null));
if (!defined('JWT_EXPIRY')) define('JWT_EXPIRY', intval(cfg('JWT_EXPIRY', 86400)));
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', intval(cfg('SESSION_LIFETIME', 86400 * 7)));
if (!defined('CSRF_TOKEN_LIFETIME')) define('CSRF_TOKEN_LIFETIME', intval(cfg('CSRF_TOKEN_LIFETIME', 3600)));

// Uploads
if (!defined('UPLOAD_DIR')) define('UPLOAD_DIR', cfg('UPLOAD_DIR', __DIR__ . '/uploads'));
if (!defined('UPLOAD_URL')) define('UPLOAD_URL', cfg('UPLOAD_URL', '/uploads'));
if (!defined('MAX_UPLOAD_SIZE')) define('MAX_UPLOAD_SIZE', intval(cfg('MAX_UPLOAD_SIZE', 10 * 1024 * 1024)));
if (!defined('ALLOWED_EXTENSIONS')) define('ALLOWED_EXTENSIONS', cfg('ALLOWED_EXTENSIONS', ['jpg','jpeg','png','gif','webp','pdf','doc','docx']));

// Rate limiting
if (!defined('RATE_LIMIT_MAX')) define('RATE_LIMIT_MAX', intval(cfg('RATE_LIMIT_MAX', 100)));
if (!defined('RATE_LIMIT_WINDOW')) define('RATE_LIMIT_WINDOW', intval(cfg('RATE_LIMIT_WINDOW', 60)));

// Timezone
date_default_timezone_set(cfg('TIMEZONE', 'Asia/Dhaka'));

// Minimal runtime checks: if required secrets are missing, log a warning so admins can act.
$required = [
    'DB_HOST' => DB_HOST,
    'DB_NAME' => DB_NAME,
    'DB_USER' => DB_USER,
    'DB_PASS' => DB_PASS,
    'JWT_SECRET' => JWT_SECRET,
];
foreach ($required as $k => $v) {
    if ($v === null || $v === '') {
        error_log("CONFIG WARNING: Required config $k is not set. Set via cPanel env or .env outside webroot.");
    }
}

// End of config.php
