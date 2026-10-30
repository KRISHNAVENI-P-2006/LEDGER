<?php
/**
 * Ledger
 * Transaction Ledger with Multi-criteria Filtering & CSV Export
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

// If a quick month filter is provided (format YYYY-MM) and explicit dates aren't, set bounds
if (!empty($monthYear) && preg_match('/^\d{4}-\d{2}$/', $monthYear)) {
    if (empty($dateFrom)) $dateFrom = $monthYear . '-01';
    if (empty($dateTo)) $dateTo = date('Y-m-t', strtotime($monthYear . '-01'));
}

// Build dynamic secure SQL query with PDO bindings
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

// 1. Fetch filtered summary statistics
$summarySql = "
    SELECT 
        COUNT(*) AS total_count,
        COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) AS filtered_income,
        COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) AS filtered_expense
    FROM transactions t
    $whereSql
";
$stmtSummary = $pdo->prepare($summarySql);
$stmtSummary->execute($params);
$filterSummary = $stmtSummary->fetch();

$totalCount = (int)$filterSummary['total_count'];
$filteredIncome = (float)$filterSummary['filtered_income'];
$filteredExpense = (float)$filterSummary['filtered_expense'];
$filteredNet = $filteredIncome - $filteredExpense;

// 2. Fetch all matching transaction records
$listSql = "
    SELECT 
        t.id,
        t.type,
        t.amount,
        t.transaction_date,
        t.description,
        c.name AS category_name,
        c.color AS category_color,
        c.icon AS category_icon
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    $whereSql
    ORDER BY t.transaction_date DESC, t.id DESC
";
$stmtList = $pdo->prepare($listSql);
$stmtList->execute($params);
$transactions = $stmtList->fetchAll();

// Fetch all categories for filter dropdown
$allCategories = get_all_categories($pdo);

// Build query string for CSV export link
$exportQueryString = http_build_query($_GET);

$page_title = 'Transactions';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="page-header">
    <div class="page-title-group">
        <h1>Transactions</h1>
        <p>All income and expense entries.</p>
    </div>
    <div class="page-actions">
        <a href="export.php?<?php echo e($exportQueryString); ?>" class="btn btn-secondary" title="Export current filtered view to CSV">
            <?php echo render_icon('export', 'icon-sm'); ?>
            <span>Export CSV</span>
        </a>
        <a href="transaction-add.php" class="btn btn-primary">
            <?php echo render_icon('plus', 'icon-sm'); ?>
            <span>Add Transaction</span>
        </a>
    </div>
</div>

<!-- Filter Toolbar -->
<div class="filter-toolbar">
    <form method="GET" action="transactions.php" class="filter-form">
        <!-- Search Keyword -->
        <div class="filter-group search-group">
            <label for="search" class="filter-label">Search</label>
            <input type="text" name="search" id="search" class="form-control" placeholder="Search note or amount" value="<?php echo e($search); ?>">
        </div>

        <!-- Transaction Type -->
        <div class="filter-group" style="max-width: 130px;">
            <label for="type" class="filter-label">Type</label>
            <select name="type" id="type" class="form-control">
                <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All Types</option>
                <option value="expense" <?php echo $type === 'expense' ? 'selected' : ''; ?>>Expense</option>
                <option value="income" <?php echo $type === 'income' ? 'selected' : ''; ?>>Income</option>
            </select>
        </div>

        <!-- Category Dropdown -->
        <div class="filter-group" style="min-width: 190px;">
            <label for="category_id" class="filter-label">Category</label>
            <select name="category_id" id="category_id" class="form-control">
                <option value="">All Categories</option>
                <?php foreach ($allCategories as $cat): ?>
                    <option value="<?php echo (int)$cat['id']; ?>" <?php echo $categoryId === (int)$cat['id'] ? 'selected' : ''; ?>>
                        <?php echo e($cat['name']) . ' (' . ucfirst(e($cat['type'])) . ')'; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Month Quick Selector -->
        <div class="filter-group" style="max-width: 140px;">
            <label for="month" class="filter-label">Month</label>
            <input type="month" name="month" id="month" class="form-control" value="<?php echo e($monthYear); ?>">
        </div>

        <!-- Date Range: From -->
        <div class="filter-group" style="max-width: 140px;">
            <label for="date_from" class="filter-label">From Date</label>
            <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo e($dateFrom); ?>">
        </div>

        <!-- Date Range: To -->
        <div class="filter-group" style="max-width: 140px;">
            <label for="date_to" class="filter-label">To Date</label>
            <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo e($dateTo); ?>">
        </div>

        <!-- Action Buttons -->
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary btn-sm">
                <?php echo render_icon('filter', 'icon-sm'); ?>
                <span>Filter</span>
            </button>
            <?php if (!empty($search) || $type !== 'all' || !empty($categoryId) || !empty($dateFrom) || !empty($dateTo) || !empty($monthYear)): ?>
                <a href="transactions.php" class="btn btn-secondary btn-sm" title="Clear all active filters">
                    <?php echo render_icon('refresh', 'icon-sm'); ?>
                    <span>Reset</span>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Filter summary -->
<div class="filter-summary">
    <div>Entries: <strong style="color: var(--text-primary);"><?php echo $totalCount; ?></strong></div>
    <div>Income: <strong style="color: var(--income-text);"><?php echo format_currency($filteredIncome); ?></strong></div>
    <div>Expenses: <strong style="color: var(--expense-text);"><?php echo format_currency($filteredExpense); ?></strong></div>
    <div>Net: <strong style="color: var(--text-primary);"><?php echo ($filteredNet >= 0 ? '+' : '') . format_currency($filteredNet); ?></strong></div>
</div>

<!-- Ledger Panel -->
<div class="panel">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 130px;">Date</th>
                    <th style="width: 260px;">Category</th>
                    <th>Note</th>
                    <th style="width: 120px;">Type</th>
                    <th class="num" style="width: 160px;">Amount</th>
                    <th class="actions" style="width: 110px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="empty-illustration">
                                    <?php echo render_icon('transactions', 'icon-lg'); ?>
                                </div>
                                <h3 class="empty-title">No transactions found</h3>
                                <p class="empty-desc">
                                    <?php if (!empty($search) || $type !== 'all' || !empty($categoryId) || !empty($dateFrom) || !empty($dateTo) || !empty($monthYear)): ?>
                                        No entries match these filters. Try a different date range or clear the filters.
                                    <?php else: ?>
                                        Nothing has been recorded yet. Add your first income or expense to get started.
                                    <?php endif; ?>
                                </p>
                                <div style="display: flex; gap: 0.75rem; justify-content: center;">
                                    <?php if (!empty($search) || $type !== 'all' || !empty($categoryId) || !empty($dateFrom) || !empty($dateTo) || !empty($monthYear)): ?>
                                        <a href="transactions.php" class="btn btn-secondary btn-sm">Clear Filters</a>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="openQuickAddModal()">Record Transaction</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td style="white-space: nowrap; color: var(--text-secondary);">
                                <?php echo format_date($tx['transaction_date']); ?>
                            </td>
                            <td>
                                <span class="badge badge-category">
                                    <span class="category-dot" style="background-color: <?php echo e($tx['category_color']); ?>;"></span>
                                    <span><?php echo e($tx['category_name']); ?></span>
                                </span>
                            </td>
                            <td>
                                <span><?php echo e($tx['description'] ?: '—'); ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo $tx['type'] === 'income' ? 'badge-income' : 'badge-expense'; ?>">
                                    <?php echo ucfirst(e($tx['type'])); ?>
                                </span>
                            </td>
                            <td class="num tabular-nums <?php echo $tx['type'] === 'income' ? 'amount-positive' : 'amount-negative'; ?>">
                                <?php echo ($tx['type'] === 'income' ? '+' : '-') . format_currency($tx['amount']); ?>
                            </td>
                            <td class="actions">
                                <a href="transaction-edit.php?id=<?php echo (int)$tx['id']; ?>" class="btn-icon" title="Edit transaction">
                                    <?php echo render_icon('edit', 'icon-sm'); ?>
                                </a>
                                <button type="button" class="btn-icon danger" title="Delete record" onclick="openConfirmDelete('transaction-delete.php', <?php echo (int)$tx['id']; ?>, 'Are you sure you want to delete this <?php echo e($tx['type']); ?> record of <?php echo format_currency($tx['amount']); ?> on <?php echo format_date($tx['transaction_date']); ?>?')">
                                    <?php echo render_icon('trash', 'icon-sm'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
