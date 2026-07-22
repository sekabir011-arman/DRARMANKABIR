<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../auth/middleware.php';
handleCors();
requireMethod('POST');
$user = requireAuth();
$input = getJsonInput();
$id = (int)($input['id'] ?? null);
if (!$id) errorResponse('Prescription ID is required', 400);
try {
    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM prescriptions WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch();
    if (!$existing) errorResponse('Prescription not found', 404);
    $stmt = $db->prepare('UPDATE prescriptions SET medication_name = :medication_name, dosage = :dosage, frequency = :frequency, duration = :duration, instructions = :instructions, status = :status, updated_at = NOW() WHERE id = :id');
    $stmt->execute([
        ':medication_name' => $input['medicationName'] ?? $existing['medication_name'],
        ':dosage' => $input['dosage'] ?? $existing['dosage'],
        ':frequency' => $input['frequency'] ?? $existing['frequency'],
        ':duration' => $input['duration'] ?? $existing['duration'],
        ':instructions' => $input['instructions'] ?? $existing['instructions'],
        ':status' => $input['status'] ?? $existing['status'],
        ':id' => $id,
    ]);
    $fetchStmt = $db->prepare('SELECT * FROM prescriptions WHERE id = :id');
    $fetchStmt->execute([':id' => $id]);
    $updated = $fetchStmt->fetch();
    logAudit($user['id'], $existing['patient_id'], 'update', 'prescription', $id, $existing, $updated);
    successResponse($updated, 'Prescription updated successfully');
} catch (\Exception $e) {
    error_log('Update prescription error: ' . $e->getMessage());
    errorResponse('Failed to update prescription', 500);
}
