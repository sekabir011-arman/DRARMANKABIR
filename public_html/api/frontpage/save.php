<?php
/**
 * Front Page Save API
 *
 * POST /api/frontpage/save.php
 * Saves the front page content (siteConfig + doctorContentOverrides)
 * to the site_settings table as a JSON blob.
 *
 * MySQL is the single source of truth. On success, returns the saved data
 * with the updated_at timestamp.
 *
 * Request body (JSON):
 *   { "siteConfig": { ... }, "doctorContentOverrides": { ... }, "version": 1234567890 }
 *   version is optional — if provided, server will reject if data has changed since that version.
 *
 * Response on success:
 *   { "success": true, "data": { "siteConfig": {...}, "doctorContentOverrides": {...}, "updated_at": "..." } }
 *
 * Response on conflict:
 *   { "success": false, "error": "conflict", "message": "Data has been modified since your last load", "serverData": {...} }
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

$siteConfig = $input['siteConfig'] ?? null;
$doctorContentOverrides = $input['doctorContentOverrides'] ?? null;
$version = $input['version'] ?? null;

if ($siteConfig === null && $doctorContentOverrides === null) {
    errorResponse('At least one of siteConfig or doctorContentOverrides must be provided', 400);
}

try {
    $db = Database::getInstance();
    $db->beginTransaction();

    $now = date('Y-m-d H:i:s');

    // ── Optimistic locking: check version if provided ──
    if ($version !== null) {
        $stmt = $db->prepare("SELECT setting_value, UNIX_TIMESTAMP(updated_at) as ts FROM site_settings WHERE setting_key = 'siteConfig'");
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $serverTs = (int)$existing['ts'];
            if ($serverTs > $version) {
                $db->rollBack();
                errorResponse('Data has been modified since your last load. Please refresh and try again.', 409, [
                    'conflict' => true,
                    'serverData' => json_decode($existing['setting_value'], true),
                    'serverVersion' => $serverTs,
                ]);
            }
        }
    }

    // ── Save siteConfig ──
    if ($siteConfig !== null) {
        $siteConfigJson = json_encode($siteConfig, JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare('
            INSERT INTO site_settings (setting_key, setting_value, setting_group, description, updated_by, updated_at)
            VALUES (:key, :value, :group, :description, :user_id, :updated_at)
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
            ':description' => 'Site configuration for landing page',
            ':user_id' => $userId,
            ':updated_at' => $now,
        ]);
    }

    // ── Save doctorContentOverrides ──
    if ($doctorContentOverrides !== null) {
        $overridesJson = json_encode($doctorContentOverrides, JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare('
            INSERT INTO site_settings (setting_key, setting_value, setting_group, description, updated_by, updated_at)
            VALUES (:key, :value, :group, :description, :user_id, :updated_at)
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
            ':description' => 'Doctor content overrides for landing page',
            ':user_id' => $userId,
            ':updated_at' => $now,
        ]);
    }

    $db->commit();

    // ── Read back the saved data to return as the authoritative version ──
    $result = [];
    $stmt = $db->query("SELECT setting_key, setting_value, UNIX_TIMESTAMP(updated_at) as ts FROM site_settings WHERE setting_key IN ('siteConfig','doctorContentOverrides')");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $result[$row['setting_key']] = json_decode($row['setting_value'], true);
        $result['updated_at'] = $row['ts'];
    }
    $result['version'] = (int)$result['updated_at'];

    // Log the update
    logAudit($userId, null, 'update', 'frontpage', null, null, [
        'keys_saved' => array_keys(array_filter(['siteConfig' => $siteConfig, 'doctorContentOverrides' => $doctorContentOverrides])),
    ]);

    successResponse($result, 'Front page content saved successfully');
} catch (\Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('Front page save error: ' . $e->getMessage());
    errorResponse('Failed to save front page content', 500);
}
