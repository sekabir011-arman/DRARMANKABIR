<?php
/**
 * Create Prescription API
 * 
 * POST /api/prescriptions/create.php
 * Body: { patient_id, visit_id?, diagnosis, medications: [...], notes? }
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../auth/middleware.php';

handleCors();
requireMethod('POST');

$user = requireAuth();
$input = getJsonInput();

$missing = validateRequired($input, ['patient_id', 'medications']);
if ($missing) {
    errorResponse('Missing required fields', 400, ['missing_fields' => $missing]);
}

if (!is_array($input['medications']) || empty($input['medications'])) {
    errorResponse('At least one medication is required', 400);
}

try {
    $db = Database::getInstance();
    $db->beginTransaction();
    
    // Create prescription
    $stmt = $db->prepare('
        INSERT INTO prescriptions (patient_id, visit_id, prescription_date, diagnosis, notes, created_by)
        VALUES (:patient_id, :visit_id, :prescription_date, :diagnosis, :notes, :created_by)
    ');
    $stmt->execute([
        ':patient_id' => (int)$input['patient_id'],
        ':visit_id' => isset($input['visit_id']) ? (int)$input['visit_id'] : null,
        ':prescription_date' => $input['prescription_date'] ?? date('Y-m-d'),
        ':diagnosis' => $input['diagnosis'] ?? null,
        ':notes' => $input['notes'] ?? null,
        ':created_by' => $user['id'],
    ]);
    
    $prescriptionId = (int)$db->lastInsertId();
    
    // Insert medications
    $medStmt = $db->prepare('
        INSERT INTO prescription_medications (
            prescription_id, name, dose, frequency, duration, instructions,
            drug_form, route, is_prn, prn_condition,
            iv_im_dose_format, loading_dose, maintenance_dose,
            infusion_rate, infusion_unit, sort_order
        ) VALUES (
            :prescription_id, :name, :dose, :frequency, :duration, :instructions,
            :drug_form, :route, :is_prn, :prn_condition,
            :iv_im_dose_format, :loading_dose, :maintenance_dose,
            :infusion_rate, :infusion_unit, :sort_order
        )
    ');
    
    foreach ($input['medications'] as $index => $med) {
        $medStmt->execute([
            ':prescription_id' => $prescriptionId,
            ':name' => $med['name'] ?? '',
            ':dose' => $med['dose'] ?? null,
            ':frequency' => $med['frequency'] ?? null,
            ':duration' => $med['duration'] ?? null,
            ':instructions' => $med['instructions'] ?? null,
            ':drug_form' => $med['drug_form'] ?? $med['drugForm'] ?? null,
            ':route' => $med['route'] ?? null,
            ':is_prn' => isset($med['is_prn']) ? (int)(filter_var($med['is_prn'], FILTER_VALIDATE_BOOLEAN)) : 0,
            ':prn_condition' => $med['prn_condition'] ?? $med['prnCondition'] ?? null,
            ':iv_im_dose_format' => $med['iv_im_dose_format'] ?? $med['ivImDoseFormat'] ?? null,
            ':loading_dose' => $med['loading_dose'] ?? $med['loadingDose'] ?? null,
            ':maintenance_dose' => $med['maintenance_dose'] ?? $med['maintenanceDose'] ?? null,
            ':infusion_rate' => $med['infusion_rate'] ?? $med['infusionRate'] ?? null,
            ':infusion_unit' => $med['infusion_unit'] ?? $med['infusionUnit'] ?? null,
            ':sort_order' => $index,
        ]);
    }
    
    $db->commit();
    
    logAudit($user['id'], (int)$input['patient_id'], 'create', 'prescription', $prescriptionId);
    
    successResponse([
        'id' => $prescriptionId,
        'message' => 'Prescription created successfully',
    ]);
    
} catch (\Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Create prescription error: ' . $e->getMessage());
    errorResponse('Failed to create prescription', 500);
}
