<?php
/**
 * Get Patient API (modernized)
 *
 * GET /api/patients/get.php?id=123
 * Headers: Authorization: Bearer <token>
 *
 * Returns a single patient record (safe fields only). All reads come from the
 * central MySQL database (phpMyAdmin / cPanel). No local or canister storage is used.
 */

require_once __DIR__ . '/../../config_loader.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db_helper.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../auth/middleware.php';

handleCors();
requireMethod('GET');

$user = requireAuth();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    Response::error('Missing or invalid patient id', [], 400);
}

try {
    $patient = DB::fetchOne('SELECT id, register_number, full_name, name_bn, date_of_birth, gender, phone, email, address, blood_group, weight, height, allergies, chronic_conditions, past_surgical_history, patient_type, photo_url, status, created_by, created_at, updated_at FROM patients WHERE id = :id LIMIT 1', [':id' => $id]);

    if (!$patient) {
        Response::error('Patient not found', [], 404);
    }

    // Normalize and sanitize output
    $patientOut = [];
    $patientOut['id'] = isset($patient['id']) ? (int)$patient['id'] : null;
    $patientOut['register_number'] = $patient['register_number'] ?? null;
    $patientOut['full_name'] = $patient['full_name'] ?? null;
    $patientOut['name_bn'] = $patient['name_bn'] ?? null;
    $patientOut['date_of_birth'] = $patient['date_of_birth'] ?? null;
    $patientOut['gender'] = $patient['gender'] ?? null;
    $patientOut['phone'] = $patient['phone'] ?? null;
    $patientOut['email'] = $patient['email'] ?? null;
    $patientOut['address'] = $patient['address'] ?? null;
    $patientOut['blood_group'] = $patient['blood_group'] ?? null;
    $patientOut['weight'] = isset($patient['weight']) && $patient['weight'] !== null ? (float)$patient['weight'] : null;
    $patientOut['height'] = isset($patient['height']) && $patient['height'] !== null ? (float)$patient['height'] : null;
    $patientOut['allergies'] = json_decode($patient['allergies'] ?? '[]', true) ?: [];
    $patientOut['chronic_conditions'] = json_decode($patient['chronic_conditions'] ?? '[]', true) ?: [];
    $patientOut['past_surgical_history'] = $patient['past_surgical_history'] ?? null;
    $patientOut['patient_type'] = $patient['patient_type'] ?? null;
    $patientOut['photo_url'] = $patient['photo_url'] ?? null;
    $patientOut['status'] = $patient['status'] ?? null;
    $patientOut['created_by'] = isset($patient['created_by']) ? (int)$patient['created_by'] : null;
    $patientOut['created_at'] = $patient['created_at'] ?? null;
    $patientOut['updated_at'] = $patient['updated_at'] ?? null;

    // Remove any sensitive/internal fields if present
    // (e.g., internal_notes, billing_info)

    Response::ok('Patient retrieved', ['patient' => $patientOut]);

} catch (\Throwable $e) {
    error_log('Get patient error: ' . $e->getMessage());
    Response::error('Failed to fetch patient', [], 500);
}
