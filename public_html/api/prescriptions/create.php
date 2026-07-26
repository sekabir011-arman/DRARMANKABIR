<?php
/**
 * Create Prescription API
 * 
 * POST /api/prescriptions/create.php
 * Accepts camelCase field names from frontend.
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../auth/middleware.php';

handleCors();
requireMethod('POST');

$user = requireAuth();
$input = getJsonInput();

// Accept both camelCase and snake_case for flexibility
$patientId = isset($input['patientId']) ? (int)$input['patientId'] : (isset($input['patient_id']) ? (int)$input['patient_id'] : );
$visitId = isset($input['visitId']) ? (int)$input['visitId'] : (isset($input['visit_id']) ? (int)$input['visit_id'] : null);
$medications = $input['medications'] ?? [];

$missing = [];
if (!$patientId) $missing[] = 'patientId';
if (empty($medications) || !is_array($medications)) $missing[] = 'medications';
if ($missing) {
    errorResponse('Missing required fields', 400, ['missing_fields' => $missing]);
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
        ':patient_id' => $patientId,
        ':visit_id' => $visitId ?: null,
        ':prescription_date' => $input['prescriptionDate'] ?? $input['prescription_date'] ?? date('Y-m-d'),
        ':diagnosis' => $input['diagnosis'] ?? null,
        ':notes' => $input['notes'] ?? null,
        ':created_by' => $user['id'],
    ]);
    
    $prescriptionId = (int)$db->lastInsertId();
    
    // Insert medications
    if (!empty($medications)) {
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
        
        foreach ($medications as $index => $med) {
            if (empty($med['name'])) continue;
            $medStmt->execute([
                ':prescription_id' => $prescriptionId,
                ':name' => $med['name'] ?? '',
                ':dose' => $med['dose'] ?? null,
                ':frequency' => $med['frequency'] ?? null,
                ':duration' => $med['duration'] ?? null,
                ':instructions' => $med['instructions'] ?? null,
                ':drug_form' => $med['drugForm'] ?? $med['drug_form'] ?? null,
                ':route' => $med['route'] ?? null,
                ':is_prn' => isset($med['isPrn']) ? (int)filter_var($med['isPrn'], FILTER_VALIDATE_BOOLEAN) : (isset($med['is_prn']) ? (int)$med['is_prn'] : ),
                ':prn_condition' => $med['prnCondition'] ?? $med['prn_condition'] ?? null,
                ':iv_im_dose_format' => $med['ivImDoseFormat'] ?? $med['iv_im_dose_format'] ?? null,
                ':loading_dose' => $med['loadingDose'] ?? $med['loading_dose'] ?? null,
                ':maintenance_dose' => $med['maintenanceDose'] ?? $med['maintenance_dose'] ?? null,
                ':infusion_rate' => $med['infusionRate'] ?? $med['infusion_rate'] ?? null,
                ':infusion_unit' => $med['infusionUnit'] ?? $med['infusion_unit'] ?? null,
                ':sort_order' => $index,
            ]);
        }
    }
    
    $db->commit();
    
    logAudit($user['id'], $patientId, 'create', 'prescription', $prescriptionId);
    
    successResponse([
        'id' => $prescriptionId,
        'patient_id' => $patientId,
        'message' => 'Prescription created successfully',
    ]);
    
} catch (\Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Create prescription error: ' . $e->getMessage());
    errorResponse('Failed to create prescription', 500);
}
