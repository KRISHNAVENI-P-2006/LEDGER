<?php
/**
 * Ledger
 * Safe Transaction Deletion Handler (POST Only)
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Disallow GET requests to prevent accidental or malicious deletion
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    set_flash('error', 'Method not allowed. Deletion must be submitted via POST.');
    redirect('transactions.php');
}

// Validate CSRF token
if (!validate_csrf()) {
    http_response_code(403);
    set_flash('error', 'Security verification failed. Please try again.');
    redirect('transactions.php');
}

$id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    set_flash('error', 'Invalid transaction identifier provided.');
    redirect('transactions.php');
}

$pdo = getDBConnection();

try {
    $stmt = $pdo->prepare('DELETE FROM transactions WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        set_flash('success', 'Transaction was successfully removed.');
    } else {
        set_flash('warning', 'Transaction not found or already deleted.');
    }
} catch (PDOException $e) {
    set_flash('error', 'Database error occurred while deleting transaction.');
}

redirect('transactions.php');
