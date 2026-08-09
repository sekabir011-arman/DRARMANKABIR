<?php
/**
 * Reject Pending Registration API (modernized)
 *
 * POST /api/auth/reject.php
 * Headers: Authorization: Bearer <admin-token>
 * Body: { "user_id": 123 }
 *
 * Only admins can reject pending registrations. All writes go to the central MySQL
 * database (phpMyAdmin / cPanel). No local or canister storage is used.
 */

require_once __DIR__ . '/../../config_loader.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db_helper.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/middleware.php';

handleCors();
requireMethod('POST');

$user = requireAdmin();

$input = getJsonInput();
$missing = validateRequired($input, ['user_id']);
if ($missing) {
    Response::error('Missing required fields', ['missing_fields' => $missing], 400);
}

$targetUserId = (int) ($input['user_id']);

try {
    DB::beginTransaction();

    $targetUser = DB::fetchOne('SELECT id, email, full_name, role, registration_status FROM users WHERE id = :id LIMIT 1', [':id' => $targetUserId]);

    if (!$targetUser) {
        DB::rollback();
        Response::error('User not found', [], 404);
    }

    if (($targetUser['registration_status'] ?? '') !== 'pending') {
        DB::rollback();
        Response::error('User is not in pending status. Current status: ' . ($targetUser['registration_status'] ?? 'unknown'), [], 400);
    }

    DB::execute('UPDATE users SET registration_status = :status, approved_by = :actor_id, approved_at = NOW(), updated_at = NOW() WHERE id = :id', [
        ':status' => 'rejected',
        ':actor_id' => (int)$user['id'],
        ':id' => $targetUserId,
    ]);

    // Audit log
    logAudit((int)$user['id'], null, 'update', 'user', $targetUserId,
        ['registration_status' => $targetUser['registration_status'] ?? null],
        ['registration_status' => 'rejected', 'rejected_by' => (int)$user['id']]
    );

    DB::commit();

    Response::ok('Account rejected', [
        'user_id' => $targetUserId,
        'email' => $targetUser['email'] ?? null,
        'full_name' => $targetUser['full_name'] ?? null,
        'status' => 'rejected',
    ]);

} catch (\Throwable $e) {
    try { DB::rollback(); } catch (\Throwable $_) {}
    error_log('Rejection error: ' . $e->getMessage());
    Response::error('Failed to reject account', [], 500);
}
