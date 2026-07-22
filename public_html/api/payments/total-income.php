<?php
require_once __DIR__ . "/../database.php";
require_once __DIR__ . "/../helpers.php";
require_once __DIR__ . "/../auth/middleware.php";
handleCors();
requireMethod("GET");
$user = requireAuth();
try {
    $db = Database::getInstance();
    $from = getParam("from", "");
    $to = getParam("to", "");
    $where = ["p.status = " . $db->quote("completed")];
    $params = [];
    if ($from) { $where[] = "p.payment_date >= :from_date"; $params[":from_date"] = $from; }
    if ($to) { $where[] = "p.payment_date <= :to_date"; $params[":to_date"] = $to; }
    $whereClause = "WHERE " . implode(" AND ", $where);
    $stmt = $db->prepare("SELECT COUNT(*) as total_transactions, IFNULL(SUM(p.amount), ) as total_income, IFNULL(AVG(p.amount), ) as average_amount FROM payments p $whereClause");
    $stmt->execute($params);
    $stats = $stmt->fetch();
    successResponse($stats);
} catch (\\Exception $e) {
    error_log("Total income error: " . $e->getMessage());
    errorResponse("Failed to calculate total income", 500);
}