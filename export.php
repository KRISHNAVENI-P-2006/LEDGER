<?php
/**
 * Ledger
 * Filtered CSV Export Streamer
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDBConnection();

// Fetch filter parameters from GET
$search     = trim((string)($_GET['search'] ?? ''));
$type       = trim((string)($_GET['type'] ?? 'all'));
$categoryId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$dateFrom   = trim((string)($_GET['date_from'] ?? ''));
$dateTo     = trim((string)($_GET['date_to'] ?? ''));
$monthYear  = trim((string)($_GET['month'] ?? ''));

if (!empty($monthYear) && preg_match('/^\d{4}-\d{2}$/', $monthYear)) {
    if (empty($dateFrom)) $dateFrom = $monthYear . '-01';
    if (empty($dateTo)) $dateTo = date('Y-m-t', strtotime($monthYear . '-01'));
}

$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = '(t.description LIKE :search OR t.amount LIKE :search_amount)';
    $params['search'] = '%' . $search . '%';
    $params['search_amount'] = '%' . $search . '%';
}

if ($type === 'income' || $type === 'expense') {
    $whereConditions[] = 't.type = :type';
    $params['type'] = $type;
}

if ($categoryId !== null && $categoryId > 0) {
    $whereConditions[] = 't.category_id = :category_id';
    $params['category_id'] = $categoryId;
}

if (!empty($dateFrom)) {
    $whereConditions[] = 't.transaction_date >= :date_from';
    $params['date_from'] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereConditions[] = 't.transaction_date <= :date_to';
    $params['date_to'] = $dateTo;
}

$whereSql = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$exportSql = "
    SELECT 
        t.id,
        t.transaction_date,
        t.type,
        c.name AS category_name,
        t.amount,
        t.description,
        t.created_at
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    $whereSql
    ORDER BY t.transaction_date DESC, t.id DESC
";

$stmt = $pdo->prepare($exportSql);
$stmt->execute($params);

$filename = 'ledger_transactions_inr_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Microsoft Excel
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// CSV Header
fputcsv($output, [
    'Transaction ID',
    'Date (DD-MM-YYYY)',
    'Type',
    'Category',
    'Amount (INR)',
    'Description / Note',
    'Logged Timestamp',
]);

while ($row = $stmt->fetch()) {
    $formattedDate = date('d-m-Y', strtotime($row['transaction_date']));
    $signedAmount = ($row['type'] === 'expense' ? '-' : '+') . number_format((float)$row['amount'], 2, '.', '');

    fputcsv($output, [
        $row['id'],
        $formattedDate,
        strtoupper($row['type']),
        $row['category_name'],
        $signedAmount,
        $row['description'] ?? '',
        $row['created_at'],
    ]);
}

fclose($output);
exit;
