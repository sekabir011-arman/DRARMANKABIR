<?php
/**
 * Front Page Save API
 * 
 * POST /api/frontpage/save.php
 * Saves the front page content (siteConfig + doctorContentOverrides)
 * to the site_settings table and returns the saved data.
 * 
 * Request body (JSON):
 *   { "siteConfig": { ... }, "doctorContentOverrides": { ... } }
 * 
 * Response:
 *   { "success": true, "data": { "siteConfig": {...}, "doctorContentOverrides": {...} }, "updated_at": "..." }
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../auth/middleware.php';

handleCors();
requireMethod('POST');

$user = getAuthUser();
$userId = $user ? $user['id'] : 0;

$input = getJsonInput();

// Build the content to save
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
    
    // Save siteConfig
    if (isset($allContent['siteConfig'])) {
        $siteConfigJson = json_encode($allContent['siteConfig'], JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare('
            INSERT INTO site_settings (setting_key, setting_value, setting_group, description, updated_by, updated_at)
            VALUES (:key, :value, :group, :desc, :uid, :now)
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                setting_group = VALUES(setting_group),
                updated_by = VALUES(updated_by),
                updated_at = VALUES(updated_at)
        ');
        $stmt->execute([
            ':key' => 'siteConfig',
            ':value' => $siteConfigJson,
            ':group' => 'frontpage',
            ':desc' => 'Site configuration for landing page',
            ':uid' => $userId,
            ':now' => $now,
        ]);
    }
    
    // Save doctorContentOverrides
    if (isset($allContent['doctorContentOverrides'])) {
        $overridesJson = json_encode($allContent['doctorContentOverrides'], JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare('
            INSERT INTO site_settings (setting_key, setting_value, setting_group, description, updated_by, updated_at)
            VALUES (:key, :value, :group, :desc, :uid, :now)
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                setting_group = VALUES(setting_group),
                updated_by = VALUES(updated_by),
                updated_at = VALUES(updated_at)
        ');
        $stmt->execute([
            ':key' => 'doctorContentOverrides',
            ':value' => $overridesJson,
            ':group' => 'frontpage',
            ':desc' => 'Doctor content overrides for landing page',
            ':uid' => $userId,
            ':now' => $now,
        ]);
    }

    // Return the saved data exactly as stored, with updated_at timestamp
    successResponse([
        'siteConfig' => $allContent['siteConfig'] ?? null,
        'doctorContentOverrides' => $allContent['doctorContentOverrides'] ?? null,
        'updated_at' => $now,
    ], 'Front page content saved successfully');
    
} catch (\Exception $e) {
    error_log('Front page save error: ' . $e->getMessage());
    errorResponse('Failed to save front page content. Please try again.', 500);
}
