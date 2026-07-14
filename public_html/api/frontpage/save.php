<?php
/**
 * Front Page Save API — MySQL as Source of Truth
 * 
 * POST /api/frontpage/save.php
 * Saves front page content. Returns the saved data with updated_at timestamp.
 * 
 * Request body (JSON):
 *   { "siteConfig": { ... }, "doctorContentOverrides": { ... } }
 * 
 * Response (201):
 *   { "success": true, "data": { "siteConfig": {...}, "doctorContentOverrides": {...}, "updated_at": "..." } }
 * 
 * Error Response (4xx/5xx):
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

// Extract siteConfig and doctorContentOverrides from input
$siteConfig = isset($input['siteConfig']) ? $input['siteConfig'] : null;
$doctorContentOverrides = isset($input['doctorContentOverrides']) ? $input['doctorContentOverrides'] : null;

if ($siteConfig === null && $doctorContentOverrides === null) {
    errorResponse('At least one of siteConfig or doctorContentOverrides is required.', 400);
}

try {
    $db = Database::getInstance();
    $now = date('Y-m-d H:i:s');
    $updatedAt = $now;

    if ($siteConfig !== null) {
        $stmt = $db->prepare('
            INSERT INTO site_settings (setting_key, setting_value, setting_group, description, updated_by, updated_at)
            VALUES (:key, :value, :group, :desc, :uid, :ts)
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                setting_group = VALUES(setting_group),
                updated_by = VALUES(updated_by),
                updated_at = VALUES(updated_at)
        ');
        $stmt->execute([
            ':key' => 'siteConfig',
            ':value' => json_encode($siteConfig, JSON_UNESCAPED_UNICODE),
            ':group' => 'frontpage',
            ':desc' => 'Site configuration for landing page',
            ':uid' => $userId,
            ':ts' => $now,
        ]);
    }

    if ($doctorContentOverrides !== null) {
        $stmt = $db->prepare('
            INSERT INTO site_settings (setting_key, setting_value, setting_group, description, updated_by, updated_at)
            VALUES (:key, :value, :group, :desc, :uid, :ts)
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                setting_group = VALUES(setting_group),
                updated_by = VALUES(updated_by),
                updated_at = VALUES(updated_at)
        ');
        $stmt->execute([
            ':key' => 'doctorContentOverrides',
            ':value' => json_encode($doctorContentOverrides, JSON_UNESCAPED_UNICODE),
            ':group' => 'frontpage',
            ':desc' => 'Doctor content overrides for landing page',
            ':uid' => $userId,
            ':ts' => $now,
        ]);
    }

    // Fetch the updated_at from the database to ensure accuracy
    $stmt = $db->prepare("SELECT updated_at FROM site_settings WHERE setting_key = 'siteConfig' OR setting_key = 'doctorContentOverrides' ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute();
    $dbUpdatedAt = $stmt->fetchColumn();
    if ($dbUpdatedAt) {
        $updatedAt = $dbUpdatedAt;
    }

    // Log audit
    logAudit($userId, null, 'update', 'frontpage', null, null, [
        'siteConfig_saved' => $siteConfig !== null,
        'doctorContentOverrides_saved' => $doctorContentOverrides !== null,
    ]);

    // Return saved data
    $responseData = [];
    if ($siteConfig !== null) {
        $responseData['siteConfig'] = $siteConfig;
    }
    if ($doctorContentOverrides !== null) {
        $responseData['doctorContentOverrides'] = $doctorContentOverrides;
    }
    $responseData['updated_at'] = $updatedAt;

    http_response_code(201);
    successResponse($responseData, 'Front page content saved successfully');

} catch (\Exception $e) {
    error_log('Front page save error: ' . $e->getMessage());
    errorResponse('Failed to save front page content. Please try again.', 500);
}
