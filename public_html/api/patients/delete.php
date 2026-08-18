<?php
/**
 * Delete Patient API (modernized)
 *
 * POST /api/patients/delete.php
 * Body: { id: 123, force: true }
 *
 * By default this endpoint performs a soft-delete (marks patient inactive and sets status to 'Deleted').
 * If `force` is true and the caller is an admin, it will perform a hard delete and remove related records.
 * All reads/writes use central MySQL (phpMyAdmin / cPanel). No local or canister storage is used.
 */

require_once __DIR__ . '/../../config_loader.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db_helper.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../auth/middleware.php';

handleCors();
requireMethod('POST');

$user = requireAdmin();
$input = getJsonInput();

$id = isset($input['id']) ? (int)$input['id'] : 0;
$force = !empty($input['force']);

if ($id <= 0) {
    Response::error('Missing or invalid patient id', [], 400);
}

try {
    DB::beginTransaction();

    $existing = DB::fetchOne('SELECT id, full_name, register_number, status FROM patients WHERE id = :id LIMIT 1', [':id' => $id]);
    if (!$existing) {
        DB::rollback();
        Response::error('Patient not found', [], 404);
    }

    if ($force) {
        // Hard delete: remove related rows in known tables and then delete patient row.
        $related = [
            'visits', 'prescriptions', 'appointments', 'vital_signs', 'clinical_notes',
            'investigations', 'payments', 'invoices', 'referrals', 'teleconsults',
            'consent_forms', 'patient_consultants', 'patient_documents', 'chat_messages'
        ];

        foreach ($related as $table) {
            // Table names are internal and controlled here, safe to interpolate
            DB::execute("DELETE FROM $table WHERE patient_id = :id", [':id' => $id]);
        }

        DB::execute('DELETE FROM patients WHERE id = :id', [':id' => $id]);

        // Audit the hard delete (store existing summary)
        logAudit((int)$user['id'], $id, 'delete', 'patient', $id, $existing);

        DB::commit();

        Response::ok('Patient and related records permanently deleted', ['patient_id' => $id, 'hard_deleted' => true]);
        return;
    }

    // Soft delete: mark inactive / set status and record deleted_by / deleted_at
    $now = date('Y-m-d H:i:s');
    // Attempt to update commonly used columns; if some columns don't exist in schema this will fail and be caught.
    DB::execute(
        'UPDATE patients SET status = :status, is_active = 0, deleted_at = :deleted_at, deleted_by = :deleted_by, updated_at = NOW() WHERE id = :id',
        [
            ':status' => 'Deleted',
            ':deleted_at' => $now,
            ':deleted_by' => (int)$user['id'],
            ':id' => $id,
        ]
    );

    // Audit the soft delete (store existing summary)
    logAudit((int)$user['id'], $id, 'soft_delete', 'patient', $id, $existing, ['soft_deleted' => true]);

    DB::commit();

    Response::ok('Patient soft-deleted successfully', ['patient_id' => $id, 'hard_deleted' => false]);

} catch (\Throwable $e) {
    try { DB::rollback(); } catch (\Throwable $_) {}
    error_log('Delete patient error: ' . $e->getMessage());
    Response::error('Failed to delete patient', [], 500);
}
