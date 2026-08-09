<?php
/**
 * Doctor/Staff Registration API (modernized)
 *
 * POST /api/auth/register.php
 * Body: {
 *   "email": "...",
 *   "password": "...",
 *   "full_name": "...",
 *   "name_bn": "...",
 *   "designation": "...",
 *   "degree": "...",
 *   "hospital_name": "...",
 *   "role": "doctor|nurse|reception|...",
 *   "specialization": "...",
 *   "phone": "...",
 *   "bmdc_registration": "..."
 * }
 *
 * Creates a user account with registration_status='pending'. Admin must approve before the account can log in.
 */

require_once __DIR__ . '/../../config_loader.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db_helper.php';
require_once __DIR__ . '/../helpers.php';

handleCors();
requireMethod('POST');
checkRateLimit('register_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

$input = getJsonInput();

// Validate required fields
$missing = validateRequired($input, ['email', 'password', 'full_name']);
if ($missing) {
    Response::error('Missing required fields', ['missing_fields' => $missing], 400);
}

$email = sanitizeEmail($input['email']);
$password = $input['password'];
$fullName = sanitizeString($input['full_name']);
$nameBn = sanitizeString($input['name_bn'] ?? '');
$role = trim($input['role'] ?? 'doctor');
$specialization = sanitizeString($input['specialization'] ?? '');
$phone = sanitizePhone($input['phone'] ?? '');
$bmdcRegistration = trim($input['bmdc_registration'] ?? '');

// Validate email
if (empty($email)) {
    Response::error('Invalid email address', [], 400);
}

// Validate password strength using helper
$pwError = validatePasswordStrength($password);
if ($pwError !== null) {
    Response::error($pwError, [], 400);
}

// Validate role
$allowedRoles = [
    'admin', 'consultant_doctor', 'medical_officer', 'intern_doctor',
    'nurse', 'staff', 'reception', 'doctor',
    'assistant_registrar', 'registrar',
    'assistant_professor', 'associate_professor', 'professor'
];
if (!in_array($role, $allowedRoles, true)) {
    Response::error('Invalid role specified', [], 400);
}

try {
    DB::beginTransaction();

    // Check if email already exists
    $existing = DB::fetchOne('SELECT id, email, registration_status FROM users WHERE email = :email LIMIT 1', [':email' => $email]);

    if ($existing) {
        if (($existing['registration_status'] ?? '') === 'rejected') {
            // Re-registration after rejection - update the record
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            DB::execute(
                'UPDATE users SET 
                    password_hash = :password_hash,
                    full_name = :full_name,
                    name_bn = :name_bn,
                    role = :role,
                    specialization = :specialization,
                    phone = :phone,
                    bmdc_registration = :bmdc_registration,
                    registration_status = :status,
                    approved_by = NULL,
                    approved_at = NULL,
                    updated_at = NOW()
                 WHERE id = :id',
                [
                    ':password_hash' => $passwordHash,
                    ':full_name' => $fullName,
                    ':name_bn' => $nameBn,
                    ':role' => $role,
                    ':specialization' => $specialization,
                    ':phone' => $phone,
                    ':bmdc_registration' => $bmdcRegistration,
                    ':status' => 'pending',
                    ':id' => (int)$existing['id'],
                ]
            );

            // Log audit
            logAudit(null, null, 'update', 'user', (int)$existing['id'], ['action' => 're-registration'], ['email' => $email, 'role' => $role]);

            DB::commit();

            Response::ok('Registration updated', ['message' => 'Your account has been re-submitted for approval. Please wait for admin approval.', 'status' => 'pending']);
        }

        DB::rollback();
        Response::error('This email is already registered in the system.', [], 409);
    }

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user with pending status
    DB::execute(
        'INSERT INTO users (email, password_hash, full_name, name_bn, role, specialization, phone, bmdc_registration, is_active, registration_status, email_verified_at, created_at, updated_at)
         VALUES (:email, :password_hash, :full_name, :name_bn, :role, :specialization, :phone, :bmdc_registration, :is_active, :registration_status, :email_verified_at, NOW(), NOW())',
        [
            ':email' => $email,
            ':password_hash' => $passwordHash,
            ':full_name' => $fullName,
            ':name_bn' => $nameBn,
            ':role' => $role,
            ':specialization' => $specialization,
            ':phone' => $phone,
            ':bmdc_registration' => $bmdcRegistration,
            ':is_active' => 1,
            ':registration_status' => 'pending',
            ':email_verified_at' => null,
        ]
    );

    // Get inserted ID
    $db = Database::getInstance();
    $userId = (int)$db->lastInsertId();

    // Log audit
    logAudit($userId, null, 'create', 'user', $userId, null, ['email' => $email, 'role' => $role, 'registration_status' => 'pending']);

    DB::commit();

    Response::ok('Registration successful', [
        'user_id' => $userId,
        'email' => $email,
        'full_name' => $fullName,
        'role' => $role,
        'status' => 'pending',
        'message' => 'Account created! Please wait for admin approval before logging in.',
    ]);

} catch (\Throwable $e) {
    DB::rollback();
    error_log('Registration error: ' . $e->getMessage());
    Response::error('Registration failed. Please try again.', [], 500);
}
