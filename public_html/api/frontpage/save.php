<?php
/**
 * Front Page Save API - MySQL Single Source of Truth
 *
 * POST /api/frontpage/save.php
 * Saves front page content (siteConfig + doctorContentOverrides) to MySQL.
 * Returns the saved data with timestamp for conflict resolution.
 *
 * Request body (JSON):
 *   { "siteConfig": { ... }, "doctorContentOverrides": { ... } }
 *
 * Response on success (200):
 *   { "success": true, "data": { "siteConfig": {...}, "doctorContentOverrides": {...}, "updated_at": "..." }, "message": "..." }
 *
 * Response on error:
 *   { "success": false, "message": "...", "error_code": "..." }
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../helpers.php';

handleCors();
requireMethod('POST');

$input = getJsonInput();
if (empty($input)) {
    errorResponse('Request body is empty', 400, null, 'EMPTY_BODY');
}

$allContent = [];

if (isset($input['siteConfig'])) {
    $allContent['siteConfig'] = $input['siteConfig'];
}
if (isset($input['doctorContentOverrides'])) {
    $allContent['doctorContentOverrides'] = $input['doctorContentOverrides'];
}
if (isset($input['raw']) && is_string($input['raw'])) {
    $decoded = json_decode($input['raw'], true);
    if (is_array($decoded)) {
        $allContent = array_merge($allContent, $decoded);
    }
}

if (empty($allContent)) {
    errorResponse('No valid content provided (expected siteConfig and/or doctorContentOverrides)', 400, null, 'INVALID_CONTENT');
}

try {
    $db = Database::getInstance();
    $now = date('c'); // ISO 8601 timestamp for conflict resolution

    // Save siteConfig
    if (isset($allContent['siteConfig'])) {
        $siteConfigJson = json_encode($allContent['siteConfig'], JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare('
            INSERT INTO site_settings (setting_key, setting_value, setting_group, description, updated_by, updated_at)
            VALUES (:key, :value, :group, :desc, 0, NOW())
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                setting_group = VALUES(setting_group),
                updated_at = NOW()
        ');
        $stmt->execute([
            ':key' => 'siteConfig',
            ':value' => $siteConfigJson,
            ':group' => 'frontpage',
            ':desc' => 'Site configuration for landing page',
        ]);
    }

    // Save doctorContentOverrides
    if (isset($allContent['doctorContentOverrides'])) {
        $overridesJson = json_encode($allContent['doctorContentOverrides'], JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare('
            INSERT INTO site_settings (setting_key, setting_value, setting_group, description, updated_by, updated_at)
            VALUES (:key, :value, :group, :desc, 0, NOW())
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                setting_group = VALUES(setting_group),
                updated_at = NOW()
        ');
        $stmt->execute([
            ':key' => 'doctorContentOverrides',
            ':value' => $overridesJson,
            ':group' => 'frontpage',
            ':desc' => 'Doctor content overrides for landing page',
        ]);
    }

    // Return the authoritative data back to the client
    successResponse([
        'siteConfig' => $allContent['siteConfig'] ?? null,
        'doctorContentOverrides' => $allContent['doctorContentOverrides'] ?? null,
        'updated_at' => $now,
    ], 'Front page content saved successfully');

} catch (\Exception $e) {
    error_log('Front page save error: ' . $e->getMessage());
    errorResponse('Failed to save front page content to database', 500, null, 'DB_ERROR');
}
