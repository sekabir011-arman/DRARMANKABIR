<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../auth/middleware.php';
handleCors();
requireMethod('POST');
$user = requireAuth();
$input = getJsonInput();
$id = (int)($input['id'] ?? null);
if (!$id) errorResponse('Visit ID is required', 400);
try {
    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM visits WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch();
    if (!$existing) errorResponse('Visit not found', 404);
    $stmt = $db->prepare('UPDATE visits SET visit_date = :visit_date, visit_type = :visit_type, chief_complaint = :chief_complaint, history_of_present_illness = :hpi, physical_examination = :pe, diagnosis = :diagnosis, notes = :notes, updated_at = NOW() WHERE id = :id');
    $stmt->execute([
        ':visit_date' => $input['visitDate'] ?? $existing['visit_date'],
        ':visit_type' => $input['visitType'] ?? $existing['visit_type'],
        ':chief_complaint' => $input['chiefComplaint'] ?? $existing['chief_complaint'],
        ':hpi' => $input['historyOfPresentIllness'] ?? $existing['history_of_present_illness'],
        ':pe' => $input['physicalExamination'] ?? $existing['physical_examination'],
        ':diagnosis' => $input['diagnosis'] ?? $existing['diagnosis'],
        ':notes' => $input['notes'] ?? $existing['notes'],
        ':id' => $id,
    ]);
    $fetchStmt = $db->prepare('SELECT * FROM visits WHERE id = :id');
    $fetchStmt->execute([':id' => $id]);
    $updated = $fetchStmt->fetch();
    logAudit($user['id'], $existing['patient_id'], 'update', 'visit', $id, $existing, $updated);
    successResponse($updated, 'Visit updated successfully');
} catch (\Exception $e) {
    error_log('Update visit error: ' . $e->getMessage());
    errorResponse('Failed to update visit', 500);
}
