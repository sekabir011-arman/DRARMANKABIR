<?php
/**
 * Dr. Arman Kabir Care - Server-Side Data Sync API
 * 
 * Provides persistent storage for the app data.
 * Data is stored as JSON files in a secure directory outside public_html.
 * Each user's data is keyed by their email address (hashed).
 */

// Security headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only allow POST and GET
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'GET'])) {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Configuration
$data_dir = '/home/drarmank/server-data';
if (!is_dir($data_dir)) {
    mkdir($data_dir, 0755, true);
}

// Rate limiting: max 100 requests per minute per IP
$rate_limit_file = $data_dir . '/_ratelimit_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$rate_limit_window = 60; // seconds
$rate_limit_max = 100;

// Simple rate limiting
if (file_exists($rate_limit_file)) {
    $rate_data = json_decode(file_get_contents($rate_limit_file), true);
    if ($rate_data && isset($rate_data['count'], $rate_data['reset'])) {
        if (time() < $rate_data['reset']) {
            if ($rate_data['count'] >= $rate_max) {
                http_response_code(429);
                echo json_encode(['error' => 'Too many requests. Try again later.']);
                exit;
            }
            $rate_data['count']++;
        } else {
            $rate_data = ['count' => 1, 'reset' => time() + $rate_limit_window];
        }
    } else {
        $rate_data = ['count' => 1, 'reset' => time() + $rate_limit_window];
    }
} else {
    $rate_data = ['count' => 1, 'reset' => time() + $rate_limit_window];
}
file_put_contents($rate_limit_file, json_encode($rate_data), LOCK_EX);

// Parse request
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_key = $_POST['user_key'] ?? $_GET['user_key'] ?? '';
$payload = $_POST['payload'] ?? '';

// If JSON body, parse it
$raw_body = file_get_contents('php://input');
if ($raw_body) {
    $json = json_decode($raw_body, true);
    if ($json) {
        $action = $json['action'] ?? $action;
        $user_key = $json['user_key'] ?? $user_key;
        $payload = $json['payload'] ?? $payload;
    }
}

// Validate user_key
if (empty($user_key)) {
    http_response_code(400);
    echo json_encode(['error' => 'user_key is required']);
    exit;
}

// Sanitize user_key - only allow email-like or alphanumeric
if (!preg_match('/^[a-zA-Z0-9@._\-+]+$/', $user_key)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user_key format']);
    exit;
}

// Prevent directory traversal
if (strpos($user_key, '..') !== false || strpos($user_key, '/') !== false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user_key']);
    exit;
}

// Hash the user_key for the filename
$file_hash = hash('sha256', $user_key);
$data_file = $data_dir . '/' . $file_hash . '.json';

/**
 * Handle save action
 */
if ($action === 'save') {
    if (empty($payload)) {
        http_response_code(400);
        echo json_encode(['error' => 'payload is required for save']);
        exit;
    }

    // Decode payload if it's a JSON string
    if (is_string($payload)) {
        $data = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON payload']);
            exit;
        }
    } else {
        $data = $payload;
    }

    // Validate max payload size (5MB)
    $payload_size = strlen(json_encode($data));
    if ($payload_size > 5 * 1024 * 1024) {
        http_response_code(413);
        echo json_encode(['error' => 'Payload too large (max 5MB)']);
        exit;
    }

    // Read existing data if any
    $existing = [];
    if (file_exists($data_file)) {
        $existing_content = file_get_contents($data_file);
        if ($existing_content) {
            $existing = json_decode($existing_content, true) ?? [];
        }
    }

    // Merge: existing data merged with new payload
    // If payload contains a key with null value, it means delete that key
    foreach ($data as $key => $value) {
        if ($value === null) {
            unset($existing[$key]);
        } else {
            $existing[$key] = $value;
        }
    }

    // Add metadata
    $existing['_meta'] = [
        'last_saved' => date('c'),
        'user_key' => substr($user_key, 0, 3) . '***' . substr($user_key, -3),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];

    // Write atomically
    $temp_file = $data_file . '.tmp';
    if (file_put_contents($temp_file, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false) {
        rename($temp_file, $data_file);
        chmod($data_file, 0644);
        
        echo json_encode([
            'success' => true,
            'message' => 'Data saved successfully',
            'timestamp' => date('c'),
            'keys_count' => count($existing)
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save data']);
    }
    exit;
}

/**
 * Handle load action
 */
if ($action === 'load') {
    if (!file_exists($data_file)) {
        // No data yet - return empty
        echo json_encode([
            'success' => true,
            'data' => new stdClass(),
            'message' => 'No data found for this user'
        ]);
        exit;
    }

    $content = file_get_contents($data_file);
    if ($content === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to read data']);
        exit;
    }

    $data = json_decode($content, true);
    if ($data === null) {
        http_response_code(500);
        echo json_encode(['error' => 'Corrupted data file']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => $data,
        'message' => 'Data loaded successfully',
        'timestamp' => date('c')
    ]);
    exit;
}

/**
 * Handle delete action
 */
if ($action === 'delete') {
    if (file_exists($data_file)) {
        unlink($data_file);
    }
    echo json_encode([
        'success' => true,
        'message' => 'Data deleted successfully'
    ]);
    exit;
}

/**
 * Handle health check
 */
if ($action === 'health') {
    echo json_encode([
        'status' => 'ok',
        'server_time' => date('c'),
        'php_version' => phpversion(),
        'data_dir_exists' => is_dir($data_dir),
        'data_dir_writable' => is_writable($data_dir)
    ]);
    exit;
}

// If no valid action was matched
http_response_code(400);
echo json_encode(['error' => 'Invalid action. Valid actions: save, load, delete, health']);
