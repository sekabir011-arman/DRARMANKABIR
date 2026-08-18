<?php
/**
 * List Patients API (modernized)
 *
 * GET /api/patients/list.php
 * Query: ?page=1&per_page=25&search=text&type=outdoor&status=Active&consultant_id=123
 *
 * Returns paginated patients. Reads/writes use central MySQL (phpMyAdmin/cPanel).
 */

require_once __DIR__ . '/../../config_loader.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db_helper.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../auth/middleware.php';

handleCors();
requireMethod('GET');

$user = requireAuth();

// Pagination
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? ($_GET['limit'] ?? 25));
$perPage = ($perPage > 0 && $perPage <= 200) ? $perPage : 25;
$offset = ($page - 1) * $perPage;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$consultantId = isset($_GET['consultant_id']) ? trim($_GET['consultant_id']) : '';

try {
    $where = ['1=1'];
    $params = [];

    if ($search !== '') {
        // escape % in user input to avoid unintended wildcards
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
        $like = '%' . $escaped . '%';
        $where[] = '(p.full_name LIKE :search OR p.name_bn LIKE :search OR p.phone LIKE :search OR p.email LIKE :search OR p.register_number LIKE :search)';
        $params[':search'] = $like;
    }

    if ($type !== '' && in_array($type, ['outdoor', 'indoor', 'emergency', 'admitted'], true)) {
        $where[] = 'p.patient_type = :type';
        $params[':type'] = $type;
    }

    if ($status !== '' && in_array($status, ['Active', 'Inactive', 'Deceased'], true)) {
        $where[] = 'p.status = :status';
        $params[':status'] = $status;
    }

    if ($consultantId !== '') {
        // ensure integer
        $consultantId = (int)$consultantId;
        if ($consultantId > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM patient_consultants pc WHERE pc.patient_id = p.id AND pc.consultant_id = :consultant_id AND pc.is_active = 1)';
            $params[':consultant_id'] = $consultantId;
        }
    }

    $whereSql = implode(' AND ', $where);

    // Count total
    $countRow = DB::fetchOne("SELECT COUNT(*) as total FROM patients p WHERE $whereSql", $params);
    $total = (int) ($countRow['total'] ?? 0);

    // Fetch rows with consultants aggregated
    $sql = "SELECT p.*, 
               (SELECT JSON_ARRAYAGG(JSON_OBJECT('consultant_id', pc.consultant_id, 'assigned_at', pc.assigned_at)) 
                FROM patient_consultants pc WHERE pc.patient_id = p.id AND pc.is_active = 1) as consultants
            FROM patients p
            WHERE $whereSql
            ORDER BY p.created_at DESC
            LIMIT :limit OFFSET :offset";

    // Add pagination params
    $params[':limit'] = $perPage;
    $params[':offset'] = $offset;

    $rows = DB::fetchAll($sql, $params);

    // Normalize fields
    foreach ($rows as &$patient) {
        $patient['id'] = isset($patient['id']) ? (int)$patient['id'] : null;
        $patient['allergies'] = json_decode($patient['allergies'] ?? '[]', true) ?: [];
        $patient['chronic_conditions'] = json_decode($patient['chronic_conditions'] ?? '[]', true) ?: [];
        $patient['consultants'] = json_decode($patient['consultants'] ?? '[]', true) ?: [];
        $patient['weight'] = isset($patient['weight']) && $patient['weight'] !== null ? (float)$patient['weight'] : null;
        $patient['height'] = isset($patient['height']) && $patient['height'] !== null ? (float)$patient['height'] : null;
        // Remove any sensitive/internal fields if present
        unset($patient['internal_notes']);
    }

    Response::ok('Patients retrieved', [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'patients' => $rows,
    ]);

} catch (\Throwable $e) {
    error_log('List patients error: ' . $e->getMessage());
    Response::error('Failed to fetch patients', [], 500);
}
