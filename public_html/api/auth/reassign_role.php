<?php
/**
 * Reassign User Role API (modernized)
 *
 * POST /api/auth/reassign_role.php
 * Headers: Authorization: Bearer <admin-token>
 * Body: { "user_id": 123, "role": "consultant_doctor" }
 *
 * Only admins can reassign roles. All changes are persisted to the central
 * MySQL database (phpMyAdmin / cPanel) — no local or canister storage is used.
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

$missing = validateRequired($input, ['user_id', 'role']);
if ($missing) {
    Response::error('Missing required fields', ['missing_fields' => $missing], 400);
}

$targetUserId = (int) ($input['user_id']);
$newRole = trim((string) ($input['role'] ?? ''));

$allowedRoles = [
    'admin', 'consultant_doctor', 'medical_officer', 'intern_doctor',
    'nurse', 'staff', 'reception', 'doctor',
    'assistant_registrar', 'registrar',
    'assistant_professor', 'associate_professor', 'professor'
];
if ($newRole === '' || !in_array($newRole, $allowedRoles, true)) {
    Response::error('Invalid role', [], 400);
}

try {
    DB::beginTransaction();

    $targetUser = DB::fetchOne('SELECT id, email, full_name, role FROM users WHERE id = :id LIMIT 1', [':id' => $targetUserId]);

    if (!$targetUser) {
        DB::rollback();
        Response::error('User not found', [], 404);
    }

    $oldRole = $targetUser['role'] ?? null;

    // No-op if same role
    if ($oldRole === $newRole) {
        DB::rollback();
        Response::ok('No change required', [
            'user_id' => $targetUserId,
            'email' => $targetUser['email'] ?? null,
            'full_name' => $targetUser['full_name'] ?? null,
            'role' => $oldRole,
            'changed' => false,
        ]);
    }

    DB::execute('UPDATE users SET role = :role, updated_at = NOW() WHERE id = :id', [':role' => $newRole, ':id' => $targetUserId]);

    // Audit log
    logAudit((int)$user['id'], null, 'update', 'user', $targetUserId, ['role' => $oldRole], ['role' => $newRole]);

    DB::commit();

    Response::ok('Role reassigned successfully', [
        'user_id' => $targetUserId,
        'email' => $targetUser['email'] ?? null,
        'full_name' => $targetUser['full_name'] ?? null,
        'old_role' => $oldRole,
        'new_role' => $newRole,
        'changed' => true,
    ]);

} catch (\Throwable $e) {
    try { DB::rollback(); } catch (\Throwable $_) {}
    error_log('Reassign role error: ' . $e->getMessage());
    Response::error('Failed to reassign role', [], 500);
}
