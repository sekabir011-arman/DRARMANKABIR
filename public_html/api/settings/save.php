<?php
/**
 * Settings API - Save
 * 
 * POST /api/settings/save.php
 * Headers: Authorization: Bearer <token>
 * Body: { "key": "clinic_name", "value": "My Clinic" }
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../auth/middleware.php';

handleCors();
requireMethod('POST');

$user = requireAuth();

$input = getJsonInput();
$missing = validateRequired($input, ['key']);
if ($missing) {
    errorResponse('Missing required fields', 400, ['missing_fields' => $missing]);
}

$key = sanitizeString($input['key']);
$value = $input['value'] ?? '';
$group = isset($input['group']) ? sanitizeString($input['group']) : 'general';

// Encode value as JSON if it's an array/object, otherwise store as string
if (is_array($value) || is_object($value)) {
    $valueJson = json_encode($value, JSON_UNESCAPED_UNICODE);
} else {
    // Try to parse as JSON first, otherwise store as plain string
    $decoded = json_decode($value, true);
    $valueJson = ($decoded !== null && $value !== $decoded) ? $value : $value;
}

try {
    $db = Database::getInstance();
    
    // Check if setting exists
    $stmt = $db->prepare('SELECT id FROM site_settings WHERE setting_key = :key LIMIT 1');
    $stmt->execute([':key' => $key]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing
        $stmt = $db->prepare('UPDATE site_settings SET setting_value = :value, setting_group = :sgroup, updated_by = :updated_by, updated_at = NOW() WHERE setting_key = :key');
        $stmt->execute([
            ':value' => is_string($valueJson) ? $valueJson : json_encode($valueJson, JSON_UNESCAPED_UNICODE),
            ':sgroup' => $group,
            ':updated_by' => $user['id'],
            ':key' => $key,
        ]);
    } else {
        // Insert new
        $stmt = $db->prepare('INSERT INTO site_settings (setting_key, setting_value, setting_group, updated_by, created_at, updated_at) VALUES (:key, :value, :sgroup, :updated_by, NOW(), NOW())');
        $stmt->execute([
            ':key' => $key,
            ':value' => is_string($valueJson) ? $valueJson : json_encode($valueJson, JSON_UNESCAPED_UNICODE),
            ':sgroup' => $group,
            ':updated_by' => $user['id'],
        ]);
    }
    
    successResponse([
        'key' => $key,
        'value' => $value,
        'group' => $group,
    ], 'Setting saved successfully');
    
} catch (\Exception $e) {
    error_log('Save settings error: ' . $e->getMessage());
    errorResponse('Failed to save setting', 500);
}
