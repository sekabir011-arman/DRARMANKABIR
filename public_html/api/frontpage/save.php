<?php
/**
 * Front Page Save API
 * 
 * POST /api/frontpage/save.php
 * Saves the front page content (siteConfig + doctorContentOverrides)
 * to the site_settings table as a JSON blob.
 * 
 * Request body (JSON):
 *   { "siteConfig": { ... }, "doctorContentOverrides": { ... } }
 *   or just the serialized JSON string
 * 
 * Response:
 *   { "success": true, "message": "Front page content saved" }
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../auth/middleware.php';

handleCors();
requireMethod('POST');

// Try to authenticate, but allow save even without auth for admin content operations
$user = getAuthUser();
$userId = $user ? $user['id'] : 0;

$input = getJsonInput();

// The input can be either a JSON object with siteConfig/doctorContentOverrides keys,
// or a raw serialized JSON string (from the JS saveFrontPageContentWithSync function)
if (isset($input['siteConfig']) || isset($input['doctorContentOverrides'])) {
    // Structured input
    $allContent = $input;
} else {
    // Try to parse as the serialized format from saveFrontPageContentWithSync
    // which sends: JSON.stringify(allContent) where allContent = { siteConfig: ..., doctorContentOverrides: ... }
    $serialized = $input;
    // If the input is a string, parse it
    if (is_string($input)) {
        $allContent = json_decode($input, true);
        if (!$allContent) {
            $allContent = ['raw' => $input];
        }
    } else {
        $allContent = $input;
    }
}

try {
    $db = Database::getInstance();
    
    // Store the complete front page content as a single setting
    $valueJson = json_encode($allContent, JSON_UNESCAPED_UNICODE);
    
    $stmt = $db->prepare('
        INSERT INTO site_settings (setting_key, setting_value, setting_group, description, updated_by, updated_at)
        VALUES (:key, :value, :group, :description, :user_id, NOW())
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            setting_group = VALUES(setting_group),
            updated_by = VALUES(updated_by),
            updated_at = NOW()
    ');
    
    $stmt->execute([
        ':key' => 'frontPageContent',
        ':value' => $valueJson,
        ':group' => 'frontpage',
        ':description' => 'Complete front page content (site config + doctor overrides)',
        ':user_id' => $userId,
    ]);
    
    // Also store siteConfig separately for easy access
    if (isset($allContent['siteConfig'])) {
        $siteConfigJson = json_encode($allContent['siteConfig'], JSON_UNESCAPED_UNICODE);
        $stmt2 = $db->prepare('
            INSERT INTO site_settings (setting_key, setting_value, setting_group, description, updated_by, updated_at)
            VALUES (:key, :value, :group, :description, :user_id, NOW())
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                setting_group = VALUES(setting_group),
                updated_by = VALUES(updated_by),
                updated_at = NOW()
        ');
        $stmt2->execute([
            ':key' => 'siteConfig',
            ':value' => $siteConfigJson,
            ':group' => 'frontpage',
            ':description' => 'Site configuration for landing page',
            ':user_id' => $userId,
        ]);
    }
    
    // Also store doctorContentOverrides separately
    if (isset($allContent['doctorContentOverrides'])) {
        $overridesJson = json_encode($allContent['doctorContentOverrides'], JSON_UNESCAPED_UNICODE);
        $stmt3 = $db->prepare('
            INSERT INTO site_settings (setting_key, setting_value, setting_group, description, updated_by, updated_at)
            VALUES (:key, :value, :group, :description, :user_id, NOW())
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                setting_group = VALUES(setting_group),
                updated_by = VALUES(updated_by),
                updated_at = NOW()
        ');
        $stmt3->execute([
            ':key' => 'doctorContentOverrides',
            ':value' => $overridesJson,
            ':group' => 'frontpage',
            ':description' => 'Doctor content overrides for landing page',
            ':user_id' => $userId,
        ]);
    }
    
    // Log the update
    logAudit($userId, null, 'update', 'frontpage', null, null, [
        'keys_saved' => array_keys($allContent),
    ]);
    
    successResponse(null, 'Front page content saved successfully');
} catch (\Exception $e) {
    error_log('Front page save error: ' . $e->getMessage());
    errorResponse('Failed to save front page content', 500);
}
