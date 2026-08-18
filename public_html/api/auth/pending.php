<?php
/**
 * Get Pending Registrations API (modernized)
 *
 * GET /api/auth/pending.php
 * Headers: Authorization: Bearer <admin-token>
 * Query params: page (int), per_page (int), role (string), q (search term)
 *
 * Returns paginated list of users with registration_status='pending'. Only admins
 * can view pending registrations. All data is read from the central MySQL
 * database (phpMyAdmin / cPanel) — no local or canister storage is used.
 */

require_once __DIR__ . '/../../config_loader.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db_helper.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/middleware.php';

handleCors();
requireMethod('GET');

$user = requireAdmin();

// Pagination and filters
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 25);
$perPage = ($perPage > 0 && $perPage <= 200) ? $perPage : 25;
$offset = ($page - 1) * $perPage;

$roleFilter = isset($_GET['role']) ? trim($_GET['role']) : null;
$search = isset($_GET['q']) ? trim($_GET['q']) : null;

try {
    // Build where clauses and params safely
    $where = ['registration_status = :status'];
    $params = [':status' => 'pending'];

    if ($roleFilter !== null && $roleFilter !== '') {
        // Optional role whitelist check to avoid arbitrary column injection
        $allowedRoles = [
            'admin','consultant_doctor','medical_officer','intern_doctor',
            'nurse','staff','reception','doctor',
            'assistant_registrar','registrar',
            'assistant_professor','associate_professor','professor'
        ];
        if (!in_array($roleFilter, $allowedRoles, true)) {
            Response::error('Invalid role filter', [], 400);
        }
        $where[] = 'role = :role';
        $params[':role'] = $roleFilter;
    }

    if ($search !== null && $search !== '') {
        // simple search across name and email
        $where[] = '(email LIKE :search OR full_name LIKE :search OR name_bn LIKE :search)';
        $params[':search'] = '%' . str_replace('%', '\\%', $search) . '%';
    }

    $whereSql = implode(' AND ', $where);

    // Total count
    $countSql = "SELECT COUNT(*) as total FROM users WHERE $whereSql";
    $countRow = DB::fetchOne($countSql, $params);
    $total = (int) ($countRow['total'] ?? 0);

    // Fetch page
    $sql = "SELECT id, email, full_name, name_bn, role, specialization, phone, bmdc_registration, registration_status, created_at
            FROM users
            WHERE $whereSql
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset";

    // Add limit/offset as params (use integer binding)
    $params[':limit'] = $perPage;
    $params[':offset'] = $offset;

    $rows = DB::fetchAll($sql, $params);

    Response::ok('Pending registrations retrieved', [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'users' => $rows,
    ]);

} catch (\Throwable $e) {
    error_log('Pending list error: ' . $e->getMessage());
    Response::error('Failed to fetch pending registrations', [], 500);
}
