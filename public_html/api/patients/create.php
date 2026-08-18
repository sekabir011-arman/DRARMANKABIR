<?php
/**
 * Create Patient API (modernized)
 *
 * POST /api/patients/create.php
 * Body: { fullName, nameBn, dateOfBirth, gender, phone, email, address, bloodGroup, weight, height, patientType, photo? }
 *
 * All writes are persisted to the central MySQL database (phpMyAdmin / cPanel). No
 * local or canister storage is used. photo must be a fully-qualified URL.
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

// Validate required fields
$missing = validateRequired($input, ['fullName', 'gender']);
if ($missing) {
    Response::error('Missing required fields', ['missing_fields' => $missing], 400);
}

$fullName = sanitizeString($input['fullName']);
$nameBn = isset($input['nameBn']) ? sanitizeString($input['nameBn']) : null;
$dateOfBirth = isset($input['dateOfBirth']) ? trim($input['dateOfBirth']) : null;
$gender = in_array($input['gender'] ?? '', ['male', 'female', 'other'], true) ? $input['gender'] : 'male';
$phone = isset($input['phone']) ? sanitizePhone($input['phone']) : null;
$email = isset($input['email']) ? sanitizeEmail($input['email']) : null;
$address = isset($input['address']) ? sanitizeString($input['address']) : null;
$bloodGroup = isset($input['bloodGroup']) ? sanitizeString($input['bloodGroup']) : null;
$weight = isset($input['weight']) ? floatval($input['weight']) : null;
$height = isset($input['height']) ? floatval($input['height']) : null;
$patientType = in_array($input['patientType'] ?? 'outdoor', ['outdoor', 'indoor', 'emergency', 'admitted'], true) ? $input['patientType'] : 'outdoor';
$photoUrl = isset($input['photo']) ? sanitizeString($input['photo']) : null;

if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    Response::error('Invalid email format', [], 400);
}

if ($photoUrl !== null && $photoUrl !== '' && !filter_var($photoUrl, FILTER_VALIDATE_URL)) {
    Response::error('Invalid photo URL. Use a fully qualified URL.', [], 400);
}

try {
    DB::beginTransaction();

    // Check duplicates
    if ($phone) {
        $exists = DB::fetchOne('SELECT id FROM patients WHERE phone = :phone LIMIT 1', [':phone' => $phone]);
        if ($exists) {
            DB::rollback();
            Response::error('A patient with this phone number already exists', ['field' => 'phone', 'duplicate' => true], 409);
        }
    }

    if ($email) {
        $exists = DB::fetchOne('SELECT id FROM patients WHERE email = :email LIMIT 1', [':email' => $email]);
        if ($exists) {
            DB::rollback();
            Response::error('A patient with this email already exists', ['field' => 'email', 'duplicate' => true], 409);
        }
    }

    // Generate register number (year-month-count)
    $year = date('Y');
    $month = date('m');
    $countRow = DB::fetchOne('SELECT COUNT(*) as cnt FROM patients WHERE YEAR(created_at) = :year', [':year' => $year]);
    $count = ((int)($countRow['cnt'] ?? 0)) + 1;
    $registerNumber = sprintf('REG-%s%s-%04d', $year, $month, $count);

    // Prepare arrays/fields
    $allergies = isset($input['allergies']) && is_array($input['allergies']) ? $input['allergies'] : [];
    $chronicConditions = isset($input['chronicConditions']) && is_array($input['chronicConditions']) ? $input['chronicConditions'] : [];
    $pastSurgicalHistory = isset($input['pastSurgicalHistory']) ? sanitizeString($input['pastSurgicalHistory']) : null;

    DB::execute(
        'INSERT INTO patients (
            register_number, full_name, name_bn, date_of_birth, gender,
            phone, email, address, blood_group, weight, height,
            allergies, chronic_conditions, past_surgical_history,
            patient_type, photo_url, registration_complete, created_by, created_at, updated_at
        ) VALUES (
            :register_number, :full_name, :name_bn, :date_of_birth, :gender,
            :phone, :email, :address, :blood_group, :weight, :height,
            :allergies, :chronic_conditions, :past_surgical_history,
            :patient_type, :photo_url, 1, :created_by, NOW(), NOW()
        )',
        [
            ':register_number' => $registerNumber,
            ':full_name' => $fullName,
            ':name_bn' => $nameBn,
            ':date_of_birth' => $dateOfBirth,
            ':gender' => $gender,
            ':phone' => $phone,
            ':email' => $email,
            ':address' => $address,
            ':blood_group' => $bloodGroup,
            ':weight' => $weight,
            ':height' => $height,
            ':allergies' => json_encode($allergies),
            ':chronic_conditions' => json_encode($chronicConditions),
            ':past_surgical_history' => $pastSurgicalHistory,
            ':patient_type' => $patientType,
            ':photo_url' => $photoUrl,
            ':created_by' => (int)$user['id'],
        ]
    );

    $db = Database::getInstance();
    $patientId = (int)$db->lastInsertId();

    $patient = DB::fetchOne('SELECT * FROM patients WHERE id = :id LIMIT 1', [':id' => $patientId]);

    logAudit((int)$user['id'], $patientId, 'create', 'patient', $patientId, null, $patient);

    DB::commit();

    Response::ok('Patient registered successfully', ['patient' => $patient]);

} catch (\Throwable $e) {
    try { DB::rollback(); } catch (\Throwable $_) {}
    error_log('Create patient error: ' . $e->getMessage());
    Response::error('Failed to register patient', [], 500);
}
