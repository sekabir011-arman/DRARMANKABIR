<?php
/**
 * Front Page Save API - MySQL Source of Truth
 * 
 * POST /api/frontpage/save.php
 * Saves front page content to MySQL and returns the saved data.
 * 
 * Request body (JSON):
 *   { "siteConfig": { ... }, "doctorContentOverrides": { ... } }
 * 
 * Response on success:
 *   { "success": true, "message": "...", "data": { "siteConfig": {...}, "doctorContentOverrides": {...}, "updated_at": "..." } }
 * 
 * Response on failure:
 *   { "success": false, "message": "..." }
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../auth/middleware.php';

handleCors();
requireMethod('POST');

$user = getAuthUser();
$userId = $user ? $user['id'] : 0;

$input = getJsonInput();

// Extract the content to save
$allContent = [];

if (isset($input['siteConfig'])) {
    $allContent['siteConfig'] = $input['siteConfig'];
}
if (isset($input['doctorContentOverrides'])) {
    $allContent['doctorContentOverrides'] = $input['doctorContentOverrides'];
}

if (empty($allContent)) {
    errorResponse('No content provided. Send siteConfig and/or doctorContentOverrides.', 400);
}

try {
    $db = Database::getInstance();
    $now = date('Y-m-d H:i:s');
    
    // Upsert each key individually
    $stmt = $db->prepare('
        INSERT INTO site_settings (setting_key, setting_value, setting_group, description, updated_by, updated_at)
        VALUES (:key, :value, :group, :description, :user_id, :updated_at)
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            setting_group = VALUES(setting_group),
            updated_by = VALUES(updated_by),
            updated_at = VALUES(updated_at)
    ');
    
    if (isset($allContent['siteConfig'])) {
        $stmt->execute([
            ':key' => 'siteConfig',
            ':value' => json_encode($allContent['siteConfig'], JSON_UNESCAPED_UNICODE),
            ':group' => 'frontpage',
            ':description' => 'Site configuration for landing page',
            ':user_id' => $userId,
            ':updated_at' => $now,
        ]);
    }
    
    if (isset($allContent['doctorContentOverrides'])) {
        $stmt->execute([
            ':key' => 'doctorContentOverrides',
            ':value' => json_encode($allContent['doctorContentOverrides'], JSON_UNESCAPED_UNICODE),
            ':group' => 'frontpage',
            ':description' => 'Doctor content overrides for landing page',
            ':user_id' => $userId,
            ':updated_at' => $now,
        ]);
    }
    
    // Log audit
    logAudit($userId, null, 'update', 'frontpage', null, null, [
        'keys_saved' => array_keys($allContent),
        'timestamp' => $now,
    ]);
    
    // Return the saved data as confirmation
    successResponse([
        'siteConfig' => $allContent['siteConfig'] ?? null,
        'doctorContentOverrides' => $allContent['doctorContentOverrides'] ?? null,
        'updated_at' => $now,
    ], 'Front page content saved successfully');
    
} catch (\Exception $e) {
    error_log('Front page save error: ' . $e->getMessage());
    errorResponse('Failed to save front page content to database. Please try again.', 500);
}
