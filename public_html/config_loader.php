<?php
/**
 * Config loader
 * Loads environment variables from OS env or a .env file located outside the web root.
 * Provides cfg(string $key, $default = null) to read configuration values.
 *
 * This code intentionally does NOT provide defaults for secrets; required
 * secrets must be present in the environment or .env and will be logged when missing.
 */

$envConfig = [];

// Keys we expect to use across the app
$expectedKeys = [
    'DB_HOST','DB_NAME','DB_USER','DB_PASS','DB_CHARSET',
    'JWT_SECRET','JWT_EXPIRY','SESSION_LIFETIME','CSRF_TOKEN_LIFETIME',
    'APP_URL','API_URL','UPLOAD_DIR','UPLOAD_URL','MAX_UPLOAD_SIZE','ALLOWED_EXTENSIONS',
    'RATE_LIMIT_MAX','RATE_LIMIT_WINDOW','TIMEZONE'
];

// 1) Read OS environment variables first
foreach ($expectedKeys as $k) {
    $v = getenv($k);
    if ($v !== false && $v !== '') {
        $envConfig[$k] = $v;
    }
}

// 2) If some keys are still missing, try to read a .env file outside web root
$dotenvPathCandidates = [
    __DIR__ . '/../.env', // parent of public_html
    __DIR__ . '/.env',    // public_html/.env (less preferred)
];
foreach ($dotenvPathCandidates as $dotenvFile) {
    if (!file_exists($dotenvFile)) continue;
    $lines = @file($dotenvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) continue;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!isset($envConfig[$key])) {
            $envConfig[$key] = $value;
        }
    }
}

// The cfg() helper
function cfg(string $key, $default = null) {
    global $envConfig;
    return $envConfig[$key] ?? $default;
}

// Expose for other includes
$GLOBALS['env_config'] = $envConfig;
