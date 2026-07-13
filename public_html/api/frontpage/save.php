<?php
/**
 * Front Page Save API — MySQL Single Source of Truth
 * 
 * POST /api/frontpage/save.php
 * Saves siteConfig and/or doctorContentOverrides to MySQL.
 * Returns the saved data back so the client can update its state.
 * 
 * Request body (JSON):
 *   { 
 *     "siteConfig": { ... },           // optional
 *     "doctorContentOverrides": { ... } // optional
 *   }
 * 
 * Response:
 *   { 
 *     "success": true, 
 *     "data": {
 *       "siteConfig": { ... },
 *       "doctorContentOverrides": { ... },
 *       "updated_at": "2026-07-14T..."
 *     },
 *     "message": "Front page content saved"
 *   }
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../auth/middleware.php';

handleCors();
requireMethod('POST');

// Try to authenticate
$user = getAuthUser();
$userId = $user ? $user['id'] : 0;

$input = getJsonInput();

// Accept either structured input or raw serialized string
if (isset($input['siteConfig']) || isset($input['doctorContentOverrides'])) {
    $allContent = $input;
} elseif (is_string($input)) {
    $parsed = json_decode($input, true);
    $allContent = $parsed ?: ['raw' => $input];
} else {
    $allContent = $input;
}

try {
    $db = Database::getInstance();
    $updatedAt = date('c');
    
    $responseData = [
        'updated_at' => $updatedAt,
    ];
    
    // Save siteConfig if provided
    if (isset($allContent['siteConfig'])) {
        $siteConfigJson = json_encode($allContent['siteConfig'], JSON_UNESCAPED_UNICODE);
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
            ':key' => 'siteConfig',
            ':value' => $siteConfigJson,
            ':group' => 'frontpage',
            ':description' => 'Site configuration for landing page',
            ':user_id' => $userId,
        ]);
        $responseData['siteConfig'] = $allContent['siteConfig'];
    }
    
    // Save doctorContentOverrides if provided
    if (isset($allContent['doctorContentOverrides'])) {
        $overridesJson = json_encode($allContent['doctorContentOverrides'], JSON_UNESCAPED_UNICODE);
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
            ':key' => 'doctorContentOverrides',
            ':value' => $overridesJson,
            ':group' => 'frontpage',
            ':description' => 'Doctor content overrides for landing page',
            ':user_id' => $userId,
        ]);
        $responseData['doctorContentOverrides'] = $allContent['doctorContentOverrides'];
    }
    
    // Save combined frontPageContent
    $combinedJson = json_encode($allContent, JSON_UNESCAPED_UNICODE);
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
        ':value' => $combinedJson,
        ':group' => 'frontpage',
        ':description' => 'Complete front page content (site config + doctor overrides)',
        ':user_id' => $userId,
    ]);
    
    // Log audit
    logAudit($userId, null, 'update', 'frontpage', null, null, [
        'keys_saved' => array_keys($allContent),
    ]);
    
    successResponse($responseData, 'Front page content saved successfully');
} catch (\Exception $e) {
    error_log('Front page save error: ' . $e->getMessage());
    errorResponse('Failed to save front page content: ' . $e->getMessage(), 500);
}
