<?php
/**
 * Update User Profile API (modernized)
 *
 * POST /api/auth/update_profile.php
 *
 * Note: This endpoint updates user profile fields only in the central database
 * (phpMyAdmin / cPanel-backed MySQL). No files or sensitive data are stored in
 * local storage or any canister. photo_url/signature_url must be external URLs
 * or served from a central storage service.
 */

require_once __DIR__ . '/../../config_loader.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db_helper.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/middleware.php';

handleCors();
requireMethod('POST');

$user = requireAuth();
$input = getJsonInput();

try {
    $updates = [];
    $params = [':id' => (int)$user['id']];

    // Allowed updatable fields mapping: client key => DB column
    $fields = [
        'fullName' => 'full_name',
        'nameBn' => 'name_bn',
        'specialization' => 'specialization',
        'phone' => 'phone',
    ];

    foreach ($fields as $inputKey => $dbField) {
        if (array_key_exists($inputKey, $input)) {
            $value = $inputKey === 'phone' ? sanitizePhone($input[$inputKey]) : sanitizeString($input[$inputKey]);
            $updates[] = "$dbField = :$dbField";
            $params[":$dbField"] = $value !== '' ? $value : null;
        }
    }

    // photo_url and signature_url must be URLs (no local file persistence)
    if (array_key_exists('photoUrl', $input)) {
        $photoUrl = sanitizeString($input['photoUrl']);
        // Optional simple URL validation
        if (!empty($photoUrl) && !filter_var($photoUrl, FILTER_VALIDATE_URL)) {
            Response::error('Invalid photoUrl provided. Use a fully qualified URL.', [], 400);
        }
        $updates[] = 'photo_url = :photo_url';
        $params[':photo_url'] = $photoUrl !== '' ? $photoUrl : null;
    }

    if (array_key_exists('signatureUrl', $input)) {
        $sigUrl = sanitizeString($input['signatureUrl']);
        if (!empty($sigUrl) && !filter_var($sigUrl, FILTER_VALIDATE_URL)) {
            Response::error('Invalid signatureUrl provided. Use a fully qualified URL.', [], 400);
        }
        $updates[] = 'signature_url = :signature_url';
        $params[':signature_url'] = $sigUrl !== '' ? $sigUrl : null;
    }

    if (empty($updates)) {
        Response::ok('No changes made', ['changed' => false]);
    }

    // Always update the updated_at timestamp
    $updates[] = 'updated_at = NOW()';
    $updateStr = implode(', ', $updates);

    // Use transaction to ensure atomic update and audit
    DB::beginTransaction();

    DB::execute("UPDATE users SET $updateStr WHERE id = :id", $params);

    // Fetch updated user (select specific safe fields)
    $updated = DB::fetchOne('SELECT id, email, full_name, name_bn, role, specialization, phone, photo_url, signature_url, bmdc_registration, is_active, updated_at FROM users WHERE id = :id LIMIT 1', [':id' => (int)$user['id']]);

    // Audit the profile update (old values are not loaded here to avoid extra cost,
    // but the helper could be extended to include before/after snapshots if needed)
    logAudit((int)$user['id'], null, 'update', 'user', (int)$user['id'], null, ['profile_updated' => true]);

    DB::commit();

    Response::ok('Profile updated successfully', ['user' => $updated]);

} catch (\Throwable $e) {
    try { DB::rollback(); } catch (\Throwable $_) {}
    error_log('Update profile error: ' . $e->getMessage());
    Response::error('Failed to update profile', [], 500);
}
