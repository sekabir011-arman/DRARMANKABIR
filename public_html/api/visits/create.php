<?php
/**
 * Create Visit API
 * 
 * POST /api/visits/create.php
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../auth/middleware.php';

handleCors();
requireMethod('POST');

$user = requireAuth();
$input = getJsonInput();

$missing = validateRequired($input, ['patient_id', 'visit_type']);
if ($missing) {
    errorResponse('Missing required fields', 400, ['missing_fields' => $missing]);
}

try {
    $db = Database::getInstance();
    
    $stmt = $db->prepare('
        INSERT INTO visits (patient_id, visit_type, visit_date, chief_complaint, history_of_present_illness, physical_examination, diagnosis, notes, created_by)
        VALUES (:patient_id, :visit_type, :visit_date, :chief_complaint, :hpI, :pe, :diagnosis, :notes, :created_by)
    ');
    
    $stmt->execute([
        ':patient_id' => (int)$input['patient_id'],
        ':visit_type' => $input['visit_type'],
        ':visit_date' => $input['visit_date'] ?? date('Y-m-d'),
        ':chief_complaint' => $input['chief_complaint'] ?? null,
        ':hpI' => $input['history_of_present_illness'] ?? null,
        ':pe' => $input['physical_examination'] ?? null,
        ':diagnosis' => $input['diagnosis'] ?? null,
        ':notes' => $input['notes'] ?? null,
        ':created_by' => $user['id'],
    ]);
    
    $visitId = (int)$db->lastInsertId();
    
    // Fetch created visit
    $fetchStmt = $db->prepare('SELECT v.*, u.full_name as doctor_name FROM visits v LEFT JOIN users u ON v.created_by = u.id WHERE v.id = :id');
    $fetchStmt->execute([':id' => $visitId]);
    $visit = $fetchStmt->fetch();
    
    logAudit($user['id'], $visit['patient_id'], 'create', 'visit', $visitId);
    
    successResponse($visit, 'Visit created successfully');
    
} catch (\Exception $e) {
    error_log('Create visit error: ' . $e->getMessage());
    errorResponse('Failed to create visit', 500);
}
