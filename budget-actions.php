<?php
/**
 * Ledger
 * Budget Mutation Controller (Set, Update, Delete, Copy)
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    set_flash('error', 'Invalid request method.');
    redirect('budgets.php');
}

if (!validate_csrf()) {
    set_flash('error', 'Security verification failed. Please try again.');
    redirect('budgets.php');
}

$pdo = getDBConnection();
$action = $_POST['action'] ?? '';
$month = (int)($_POST['month'] ?? date('n'));
$year = (int)($_POST['year'] ?? date('Y'));

if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
    set_flash('error', 'Invalid month or year specified.');
    redirect('budgets.php');
}

$redirectUrl = sprintf('budgets.php?month=%d&year=%d', $month, $year);

// 1. Action: Set / Update Category Budget Limit
if ($action === 'set_budget') {
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT);

    if ($categoryId <= 0) {
        set_flash('error', 'Please select an expense category.');
        redirect($redirectUrl);
    }

    if ($amount === false || $amount <= 0) {
        set_flash('error', 'Please enter a valid budget amount greater than ₹0.');
        redirect($redirectUrl);
    }

    $catCheck = $pdo->prepare('SELECT name, type FROM categories WHERE id = ?');
    $catCheck->execute([$categoryId]);
    $cat = $catCheck->fetch();

    if (!$cat || $cat['type'] !== 'expense') {
        set_flash('error', 'Budgets can only be set for expense categories.');
        redirect($redirectUrl);
    }

    try {
        $stmt = $pdo->prepare('
            INSERT INTO budgets (category_id, month, year, amount)
            VALUES (:category_id, :month, :year, :amount)
            ON DUPLICATE KEY UPDATE amount = VALUES(amount), updated_at = CURRENT_TIMESTAMP
        ');
        $stmt->execute([
            'category_id' => $categoryId,
            'month'       => $month,
            'year'        => $year,
            'amount'      => $amount,
        ]);

        set_flash('success', sprintf('Budget limit for %s set to %s.', $cat['name'], format_currency($amount)));
    } catch (PDOException $e) {
        set_flash('error', 'Database error while saving budget limit.');
    }

    redirect($redirectUrl);
}

// 2. Action: Delete a Budget Allocation
if ($action === 'delete_budget') {
    $budgetId = (int)($_POST['id'] ?? 0);

    if ($budgetId <= 0) {
        set_flash('error', 'Invalid budget identifier.');
        redirect($redirectUrl);
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM budgets WHERE id = ?');
        $stmt->execute([$budgetId]);
        set_flash('success', 'Budget allocation was removed.');
    } catch (PDOException $e) {
        set_flash('error', 'Database error while removing budget.');
    }

    redirect($redirectUrl);
}

// 3. Action: Copy Budgets from Previous Month
if ($action === 'copy_prev') {
    $prevDate = strtotime(sprintf('%04d-%02d-01 -1 month', $year, $month));
    $prevMonth = (int)date('n', $prevDate);
    $prevYear = (int)date('Y', $prevDate);

    $prevStmt = $pdo->prepare('SELECT category_id, amount FROM budgets WHERE month = ? AND year = ?');
    $prevStmt->execute([$prevMonth, $prevYear]);
    $prevBudgets = $prevStmt->fetchAll();

    if (empty($prevBudgets)) {
        set_flash('warning', sprintf('No budgets were found in previous month (%s) to copy.', date('F Y', $prevDate)));
        redirect($redirectUrl);
    }

    $copiedCount = 0;
    $insertStmt = $pdo->prepare('
        INSERT INTO budgets (category_id, month, year, amount)
        VALUES (:category_id, :month, :year, :amount)
        ON DUPLICATE KEY UPDATE amount = VALUES(amount)
    ');

    foreach ($prevBudgets as $pb) {
        $insertStmt->execute([
            'category_id' => (int)$pb['category_id'],
            'month'       => $month,
            'year'        => $year,
            'amount'      => (float)$pb['amount'],
        ]);
        $copiedCount++;
    }

    set_flash('success', sprintf('Copied %d budget allocation(s) from %s.', $copiedCount, date('F Y', $prevDate)));
    redirect($redirectUrl);
}

redirect($redirectUrl);
