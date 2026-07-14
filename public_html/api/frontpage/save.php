<?php
/**
 * Front Page Save API - MySQL Single Source of Truth
 * 
 * POST /api/frontpage/save.php
 * 
 * Saves the front page content (siteConfig + doctorContentOverrides)
 * to the site_settings table. Returns the saved record with updated_at timestamp.
 * 
 * Request body (JSON):
 *   {
 *     "siteConfig": { ... },
 *     "doctorContentOverrides": { ... },
 *     "overwrite": true  // optional, force overwrite even if newer on server
 *   }
 * 
 * Response on success:
 *   {
 *     "success": true,
 *     "message": "Front page content saved successfully",
 *     "data": {
 *       "siteConfig": { ... },
 *       "doctorContentOverrides": { ... },
 *       "saved_at": "2026-07-14T01:00:00+06:00",
 *       "version": 3
 *     }
 *   }
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../auth/middleware.php';

handleCors();
requireMethod('POST');

$user = getAuthUser();
$userId = $user ? $user['id'] : 0;

$input = getJsonInput();

// Extract content from request
$siteConfig = $input['siteConfig'] ?? null;
$doctorContentOverrides = $input['doctorContentOverrides'] ?? null;
$overwrite = !empty($input['overwrite']);

if (!$siteConfig && !$doctorContentOverrides) {
    errorResponse('At least one of siteConfig or doctorContentOverrides is required.', 400);
}

try {
    $db = Database::getInstance();
    $savedAt = date('c');
    $result = [
        'saved_at' => $savedAt,
    ];

    if ($siteConfig !== null) {
        $siteConfigJson = json_encode($siteConfig, JSON_UNESCAPED_UNICODE);
        
        // Increment version number for conflict resolution
        $versionStmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'siteConfig_version'");
        $versionStmt->execute();
        $currentVersion = (int)$versionStmt->fetchColumn();
        $newVersion = $currentVersion + 1;
        
        // Check conflict: if client sent a version, compare
        $clientVersion = isset($input['_version']) ? (int)$input['_version'] : 0;
        if ($clientVersion > 0 && $clientVersion < $currentVersion && !$overwrite) {
            errorResponse('Conflict: data has been modified by another session. Refresh and try again.', 409, [
                'server_version' => $currentVersion,
                'your_version' => $clientVersion,
            ]);
        }
        
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
        
        // Update version
        $stmtVer = $db->prepare('
            INSERT INTO site_settings (setting_key, setting_value, setting_group, description, updated_by, updated_at)
            VALUES (:key, :value, :group, :description, :user_id, NOW())
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_at = NOW()
        ');
        $stmtVer->execute([
            ':key' => 'siteConfig_version',
            ':value' => (string)$newVersion,
            ':group' => '_internal',
            ':description' => 'Version counter for siteConfig conflict resolution',
            ':user_id' => $userId,
        ]);
        
        $result['siteConfig'] = $siteConfig;
        $result['siteConfig_version'] = $newVersion;
    }

    if ($doctorContentOverrides !== null) {
        $overridesJson = json_encode($doctorContentOverrides, JSON_UNESCAPED_UNICODE);
        
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
            ':key' => 'doctorContentOverrides',
            ':value' => $overridesJson,
            ':group' => 'frontpage',
            ':description' => 'Doctor content overrides for landing page',
            ':user_id' => $userId,
        ]);
        
        $result['doctorContentOverrides'] = $doctorContentOverrides;
    }

    // Log the update
    logAudit($userId, null, 'update', 'frontpage', null, null, [
        'keys_saved' => array_keys($input),
        'version' => $newVersion ?? null,
    ]);

    successResponse($result, 'Front page content saved successfully');
} catch (\Exception $e) {
    error_log('Front page save error: ' . $e->getMessage());
    errorResponse('Failed to save front page content', 500);
}
