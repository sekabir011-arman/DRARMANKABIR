<?php
/**
 * Create Visit API (modernized)
 *
 * POST /api/visits/create.php
 * Body (camelCase): { patientId, visitType, visitDate?, chiefComplaint?, vitalSigns?, historyOfPresentIllness?, physicalExamination?, diagnosis?, notes? }
 *
 * All reads/writes use central MySQL (phpMyAdmin / cPanel). No local or canister storage is used.
 */

require_once __DIR__ . '/../../config_loader.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db_helper.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../auth/middleware.php';

handleCors();
requireMethod('POST');

$user = requireAuth();
$input = getJsonInput();

$missing = validateRequired($input, ['patientId', 'visitType']);
if ($missing) {
    Response::error('Missing required fields', ['missing_fields' => $missing], 400);
}

$patientId = (int) ($input['patientId'] ?? 0);
if ($patientId <= 0) {
    Response::error('Invalid patientId', [], 400);
}

$allowedVisitTypes = ['outpatient', 'inpatient', 'emergency', 'follow-up', 'admitted'];
$visitType = trim((string) ($input['visitType'] ?? ''));
if ($visitType === '' || !in_array($visitType, $allowedVisitTypes, true)) {
    Response::error('Invalid visit type. Allowed: ' . implode(', ', $allowedVisitTypes), [], 400);
}

$visitDate = isset($input['visitDate']) && $input['visitDate'] !== '' ? sanitizeString($input['visitDate']) : date('Y-m-d');
$chiefComplaint = isset($input['chiefComplaint']) ? sanitizeString($input['chiefComplaint']) : null;
$vitalSigns = isset($input['vitalSigns']) ? $input['vitalSigns'] : null; // expect array or object
$historyOfPresentIllness = isset($input['historyOfPresentIllness']) ? sanitizeString($input['historyOfPresentIllness']) : null;
$physicalExamination = isset($input['physicalExamination']) ? sanitizeString($input['physicalExamination']) : null;
$diagnosis = isset($input['diagnosis']) ? sanitizeString($input['diagnosis']) : null;
$notes = isset($input['notes']) ? sanitizeString($input['notes']) : null;

// sanitize vital signs to JSON
$vitalSignsJson = null;
if ($vitalSigns !== null) {
    if (!is_array($vitalSigns) && !is_object($vitalSigns)) {
        Response::error('Invalid vitalSigns format; expected object/array', [], 400);
    }
    // Optionally sanitize numeric values inside vital signs — here we just JSON-encode safely
    $vitalSignsJson = json_encode($vitalSigns);
}

try {
    DB::beginTransaction();

    // Ensure patient exists
    $patient = DB::fetchOne('SELECT id, full_name FROM patients WHERE id = :id LIMIT 1', [':id' => $patientId]);
    if (!$patient) {
        DB::rollback();
        Response::error('Patient not found', [], 404);
    }

    DB::execute(
        'INSERT INTO visits (patient_id, visit_type, visit_date, chief_complaint, vital_signs, history_of_present_illness, physical_examination, diagnosis, notes, created_by, created_at, updated_at)
         VALUES (:patient_id, :visit_type, :visit_date, :chief_complaint, :vital_signs, :hpi, :pe, :diagnosis, :notes, :created_by, NOW(), NOW())',
        [
            ':patient_id' => $patientId,
            ':visit_type' => $visitType,
            ':visit_date' => $visitDate,
            ':chief_complaint' => $chiefComplaint,
            ':vital_signs' => $vitalSignsJson,
            ':hpi' => $historyOfPresentIllness,
            ':pe' => $physicalExamination,
            ':diagnosis' => $diagnosis,
            ':notes' => $notes,
            ':created_by' => (int)$user['id'],
        ]
    );

    // Get inserted id
    $db = Database::getInstance();
    $visitId = (int) $db->lastInsertId();

    // Fetch created visit with doctor name
    $visit = DB::fetchOne('SELECT v.*, u.full_name as doctor_name FROM visits v LEFT JOIN users u ON v.created_by = u.id WHERE v.id = :id LIMIT 1', [':id' => $visitId]);

    if ($visit && isset($visit['vital_signs']) && $visit['vital_signs'] !== null) {
        $visit['vital_signs'] = json_decode($visit['vital_signs'], true) ?: [];
    } else {
        $visit['vital_signs'] = [];
    }

    // Audit
    logAudit((int)$user['id'], $patientId, 'create', 'visit', $visitId, null, $visit);

    DB::commit();

    Response::ok('Visit created successfully', ['visit' => $visit]);

} catch (\Throwable $e) {
    try { DB::rollback(); } catch (\Throwable $_) {}
    error_log('Create visit error: ' . $e->getMessage());
    Response::error('Failed to create visit', [], 500);
}
