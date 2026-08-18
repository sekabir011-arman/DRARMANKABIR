<?php
/**
 * Update Patient API (modernized)
 *
 * POST /api/patients/update.php
 * Body: { id, fullName?, nameBn?, dateOfBirth?, gender?, phone?, email?, address?, bloodGroup?, weight?, height?, patientType?, photo? }
 *
 * All writes persist to the central MySQL (phpMyAdmin / cPanel). No local or canister
 * storage is used. photo must be a fully-qualified URL when provided.
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

// id is required
$id = isset($input['id']) ? (int)$input['id'] : 0;
if ($id <= 0) {
    Response::error('Missing or invalid patient id', [], 400);
}

// Allowed updatable fields mapping: client key => db column
$allowed = [
    'fullName' => 'full_name',
    'nameBn' => 'name_bn',
    'dateOfBirth' => 'date_of_birth',
    'gender' => 'gender',
    'phone' => 'phone',
    'email' => 'email',
    'address' => 'address',
    'bloodGroup' => 'blood_group',
    'weight' => 'weight',
    'height' => 'height',
    'patientType' => 'patient_type',
];

$updates = [];
$params = [':id' => $id];

foreach ($allowed as $inputKey => $dbField) {
    if (array_key_exists($inputKey, $input)) {
        $value = $input[$inputKey];
        if ($inputKey === 'phone') {
            $value = $value !== null ? sanitizePhone($value) : null;
        } elseif ($inputKey === 'email') {
            $value = $value !== null ? sanitizeEmail($value) : null;
            if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                Response::error('Invalid email format', [], 400);
            }
        } elseif (in_array($inputKey, ['weight','height'], true)) {
            $value = $value !== null && $value !== '' ? floatval($value) : null;
        } else {
            $value = $value !== null ? sanitizeString($value) : null;
        }

        $updates[] = "$dbField = :$dbField";
        $params[":$dbField"] = $value;
    }
}

// photo URL
if (array_key_exists('photo', $input)) {
    $photo = $input['photo'] !== null ? sanitizeString($input['photo']) : null;
    if ($photo !== null && $photo !== '' && !filter_var($photo, FILTER_VALIDATE_URL)) {
        Response::error('Invalid photo URL. Use a fully qualified URL.', [], 400);
    }
    $updates[] = 'photo_url = :photo_url';
    $params[':photo_url'] = $photo;
}

// allergies / chronicConditions / pastSurgicalHistory
if (array_key_exists('allergies', $input)) {
    $allergies = is_array($input['allergies']) ? $input['allergies'] : [];
    $updates[] = 'allergies = :allergies';
    $params[':allergies'] = json_encode($allergies);
}
if (array_key_exists('chronicConditions', $input)) {
    $chronic = is_array($input['chronicConditions']) ? $input['chronicConditions'] : [];
    $updates[] = 'chronic_conditions = :chronic_conditions';
    $params[':chronic_conditions'] = json_encode($chronic);
}
if (array_key_exists('pastSurgicalHistory', $input)) {
    $psh = $input['pastSurgicalHistory'] !== null ? sanitizeString($input['pastSurgicalHistory']) : null;
    $updates[] = 'past_surgical_history = :past_surgical_history';
    $params[':past_surgical_history'] = $psh;
}

if (empty($updates)) {
    Response::ok('No changes made', ['changed' => false]);
}

$updates[] = 'updated_at = NOW()';
$updateSql = 'UPDATE patients SET ' . implode(', ', $updates) . ' WHERE id = :id';

try {
    DB::beginTransaction();

    // Ensure patient exists
    $existing = DB::fetchOne('SELECT id FROM patients WHERE id = :id LIMIT 1', [':id' => $id]);
    if (!$existing) {
        DB::rollback();
        Response::error('Patient not found', [], 404);
    }

    DB::execute($updateSql, $params);

    $patient = DB::fetchOne('SELECT * FROM patients WHERE id = :id LIMIT 1', [':id' => $id]);

    // Audit log (record the actor and that profile changed)
    logAudit((int)$user['id'], $id, 'update', 'patient', $id, null, ['changed_fields' => array_keys($params)]);

    DB::commit();

    Response::ok('Patient updated successfully', ['patient' => $patient]);

} catch (\Throwable $e) {
    try { DB::rollback(); } catch (\Throwable $_) {}
    error_log('Update patient error: ' . $e->getMessage());
    Response::error('Failed to update patient', [], 500);
}
