<?php
/**
 * Approve Pending Registration API (modernized)
 *
 * POST /api/auth/approve.php
 * Headers: Authorization: Bearer <admin-token>
 * Body: { "user_id": 123, "role": "consultant_doctor" }
 *
 * Only admins can approve pending registrations.
 */

require_once __DIR__ . '/../../config_loader.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db_helper.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/middleware.php';

handleCors();
requireMethod('POST');

// Ensure the requester is an admin
$user = requireAdmin();

$input = getJsonInput();
$missing = validateRequired($input, ['user_id']);
if ($missing) {
    Response::error('Missing required fields', ['missing_fields' => $missing], 400);
}

$targetUserId = (int) ($input['user_id']);
$newRole = trim($input['role'] ?? '');

// Validate role if provided
$allowedRoles = [
    'admin', 'consultant_doctor', 'medical_officer', 'intern_doctor',
    'nurse', 'staff', 'reception', 'doctor',
    'assistant_registrar', 'registrar',
    'assistant_professor', 'associate_professor', 'professor'
];
if ($newRole !== '' && !in_array($newRole, $allowedRoles, true)) {
    Response::error('Invalid role', [], 400);
}

try {
    // Begin transaction for update + audit
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

    // Determine role to set
    $roleToSet = $newRole !== '' ? $newRole : ($targetUser['role'] ?? null);

    // Prepare update
    if ($roleToSet !== null && $roleToSet !== '') {
        DB::execute('UPDATE users SET registration_status = :status, approved_by = :approved_by, approved_at = NOW(), role = :new_role WHERE id = :id', [
            ':status' => 'approved',
            ':approved_by' => $user['id'],
            ':new_role' => $roleToSet,
            ':id' => $targetUserId,
        ]);
    } else {
        DB::execute('UPDATE users SET registration_status = :status, approved_by = :approved_by, approved_at = NOW() WHERE id = :id', [
            ':status' => 'approved',
            ':approved_by' => $user['id'],
            ':id' => $targetUserId,
        ]);
    }

    // Audit log
    logAudit((int)$user['id'], null, 'update', 'user', $targetUserId,
        ['registration_status' => $targetUser['registration_status'] ?? null, 'role' => $targetUser['role'] ?? null],
        ['registration_status' => 'approved', 'role' => $roleToSet, 'approved_by' => $user['id']]
    );

    DB::commit();

    Response::ok('Account approved successfully', [
        'user_id' => $targetUserId,
        'email' => $targetUser['email'] ?? null,
        'full_name' => $targetUser['full_name'] ?? null,
        'role' => $roleToSet,
        'status' => 'approved',
    ]);

} catch (\Throwable $e) {
    DB::rollback();
    error_log('Approval error: ' . $e->getMessage());
    Response::error('Failed to approve account', [], 500);
}
