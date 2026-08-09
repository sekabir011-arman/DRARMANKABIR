<?php
/**
 * Verify Session API (modernized)
 *
 * GET /api/auth/verify.php
 * Headers: Authorization: Bearer <token>
 *
 * Returns current user info if session is valid. Used on app load to restore session.
 */

require_once __DIR__ . '/../../config_loader.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db_helper.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/middleware.php';

handleCors();
requireMethod('GET');

$user = requireAuth();

// Remove sensitive data
unset($user['password_hash']);
unset($user['token']);
unset($user['expires_at']);

// Normalize output fields
$userOut = [
    'id' => isset($user['id']) ? (int)$user['id'] : null,
    'email' => $user['email'] ?? null,
    'full_name' => $user['full_name'] ?? null,
    'name_bn' => $user['name_bn'] ?? null,
    'role' => $user['role'] ?? null,
    'specialization' => $user['specialization'] ?? null,
    'phone' => $user['phone'] ?? null,
    'photo_url' => $user['photo_url'] ?? null,
    'signature_url' => $user['signature_url'] ?? null,
    'bmdc_registration' => $user['bmdc_registration'] ?? null,
    'is_active' => isset($user['is_active']) ? (bool)$user['is_active'] : null,
    'last_login_at' => $user['last_login_at'] ?? null,
];

Response::ok('Session valid', ['user' => $userOut]);
