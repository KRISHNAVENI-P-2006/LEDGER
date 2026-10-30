<?php
/**
 * Ledger
 * Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDBConnection();

$currentYear = (int)date('Y');
$currentMonth = (int)date('n');
$currentMonthName = date('F Y');

// 1. Current Month KPI Figures
$stmt = $pdo->prepare('
    SELECT 
        COALESCE(SUM(CASE WHEN type = "income" THEN amount ELSE 0 END), 0) AS month_income,
        COALESCE(SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END), 0) AS month_expense
    FROM transactions
    WHERE YEAR(transaction_date) = :year AND MONTH(transaction_date) = :month
');
$stmt->execute(['year' => $currentYear, 'month' => $currentMonth]);
$monthSummary = $stmt->fetch();

$monthIncome = (float)$monthSummary['month_income'];
$monthExpense = (float)$monthSummary['month_expense'];
$monthSavings = $monthIncome - $monthExpense;
$savingsRate = $monthIncome > 0 ? round(($monthSavings / $monthIncome) * 100, 1) : 0.0;

// All-time Net Balance & Total Transactions Count
$statsStmt = $pdo->query('
    SELECT 
        COUNT(*) AS total_count,
        (COALESCE(SUM(CASE WHEN type = "income" THEN amount ELSE 0 END), 0) - 
         COALESCE(SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END), 0)) AS net_balance
    FROM transactions
');
$overallStats = $statsStmt->fetch();
$totalTransactionsCount = (int)$overallStats['total_count'];
$allTimeBalance = (float)$overallStats['net_balance'];

// 2. Over-budget Alerts for Current Month
$budgetAlertStmt = $pdo->prepare('
    SELECT 
        c.name AS category_name,
        b.amount AS budget_limit,
        COALESCE(SUM(t.amount), 0) AS actual_spent,
        (COALESCE(SUM(t.amount), 0) - b.amount) AS over_amount,
        ROUND((COALESCE(SUM(t.amount), 0) / b.amount) * 100, 1) AS pct_used
    FROM budgets b
    JOIN categories c ON b.category_id = c.id
    LEFT JOIN transactions t ON t.category_id = c.id 
        AND t.type = "expense"
        AND YEAR(t.transaction_date) = b.year 
        AND MONTH(t.transaction_date) = b.month
    WHERE b.year = :year AND b.month = :month
    GROUP BY b.id, c.name, b.amount
    HAVING actual_spent > b.amount
    ORDER BY over_amount DESC
');
$budgetAlertStmt->execute(['year' => $currentYear, 'month' => $currentMonth]);
$overBudgetCategories = $budgetAlertStmt->fetchAll();

// 3. Category Expense Breakdown for Current Month
$catBreakdownStmt = $pdo->prepare('
    SELECT 
        c.name,
        c.color,
        c.icon,
        COALESCE(SUM(t.amount), 0) AS total_spent
    FROM categories c
    JOIN transactions t ON c.id = t.category_id
    WHERE t.type = "expense"
      AND YEAR(t.transaction_date) = :year
      AND MONTH(t.transaction_date) = :month
    GROUP BY c.id, c.name, c.color, c.icon
    ORDER BY total_spent DESC
');
$catBreakdownStmt->execute(['year' => $currentYear, 'month' => $currentMonth]);
$categoryBreakdown = $catBreakdownStmt->fetchAll();

// 4. Past 6 Months Cash Flow Trends (for Chart.js)
$trendsStmt = $pdo->query('
    SELECT 
        DATE_FORMAT(transaction_date, "%Y-%m") AS ym,
        COALESCE(SUM(CASE WHEN type = "income" THEN amount ELSE 0 END), 0) AS total_income,
        COALESCE(SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END), 0) AS total_expense
    FROM transactions
    WHERE transaction_date >= DATE_SUB(DATE_FORMAT(NOW(), "%Y-%m-01"), INTERVAL 5 MONTH)
    GROUP BY ym
    ORDER BY ym ASC
');
$rawTrends = $trendsStmt->fetchAll();

// Contiguous 6-month timeline
$trendMonths = [];
for ($i = 5; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));
    $trendMonths[$ym] = [
        'label' => $label,
        'income' => 0.0,
        'expense' => 0.0,
    ];
}
foreach ($rawTrends as $rt) {
    if (isset($trendMonths[$rt['ym']])) {
        $trendMonths[$rt['ym']]['income'] = (float)$rt['total_income'];
        $trendMonths[$rt['ym']]['expense'] = (float)$rt['total_expense'];
    }
}

$chartLabels = array_column($trendMonths, 'label');
$chartIncome = array_column($trendMonths, 'income');
$chartExpense = array_column($trendMonths, 'expense');

// 5. Recent 6 Transactions
$recentStmt = $pdo->query('
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
    ORDER BY t.transaction_date DESC, t.id DESC
    LIMIT 6
');
$recentTransactions = $recentStmt->fetchAll();

$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="page-header">
    <div class="page-title-group">
        <h1>Financial Overview</h1>
        <p>Cash flow and budget status for <strong><?php echo e($currentMonthName); ?></strong></p>
    </div>
    <div class="page-actions">
        <a href="transactions.php" class="btn btn-secondary">
            <?php echo render_icon('transactions', 'icon-sm'); ?>
            <span>All Transactions</span>
        </a>
        <button type="button" class="btn btn-primary" onclick="openQuickAddModal()">
            <?php echo render_icon('plus', 'icon-sm'); ?>
            <span>Record Transaction</span>
        </button>
    </div>
</div>

<!-- Active Over-budget Warning Banner -->
<?php if (!empty($overBudgetCategories)): ?>
    <div class="alert alert-warning" style="margin-bottom: 1.5rem;">
        <div class="alert-content">
            <?php echo render_icon('alert-triangle', 'icon'); ?>
            <div>
                <strong>Budget Limit Exceeded:</strong>
                <?php 
                $alertItems = [];
                foreach ($overBudgetCategories as $ob) {
                    $alertItems[] = sprintf(
                        '<strong>%s</strong> (%s spent of %s limit, +%s over)',
                        e($ob['category_name']),
                        format_currency($ob['actual_spent']),
                        format_currency($ob['budget_limit']),
                        format_currency($ob['over_amount'])
                    );
                }
                echo implode(' &bull; ', $alertItems);
                ?>
                &mdash; <a href="budgets.php" style="color: inherit; text-decoration: underline; font-weight: 600;">Manage Budgets</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Monthly summary -->
<div class="kpi-grid">
    <!-- Month Income -->
    <div class="kpi-card kpi-income">
        <div class="kpi-header">
            <span class="kpi-label">Month Income</span>
            <div class="kpi-icon-pill income">
                <?php echo render_icon('arrow-down-left', 'icon-sm'); ?>
            </div>
        </div>
        <div class="kpi-value tabular-nums" style="color: var(--income-text);">
            <?php echo format_currency($monthIncome); ?>
        </div>
        <div class="kpi-subtext">
            <span>Received in <?php echo date('M Y'); ?></span>
        </div>
    </div>

    <!-- Month Expenses -->
    <div class="kpi-card kpi-expense">
        <div class="kpi-header">
            <span class="kpi-label">Month Expenses</span>
            <div class="kpi-icon-pill expense">
                <?php echo render_icon('arrow-up-right', 'icon-sm'); ?>
            </div>
        </div>
        <div class="kpi-value tabular-nums">
            <?php echo format_currency($monthExpense); ?>
        </div>
        <div class="kpi-subtext">
            <span>Spent in <?php echo date('M Y'); ?></span>
        </div>
    </div>

    <!-- Net Month Savings -->
    <div class="kpi-card kpi-balance">
        <div class="kpi-header">
            <span class="kpi-label">Net Cash Flow</span>
            <div class="kpi-icon-pill balance">
                <?php echo render_icon('rupee', 'icon-sm'); ?>
            </div>
        </div>
        <div class="kpi-value tabular-nums" style="<?php echo $monthSavings >= 0 ? 'color: var(--income-text);' : 'color: var(--expense-text);'; ?>">
            <?php echo ($monthSavings >= 0 ? '+' : '') . format_currency($monthSavings); ?>
        </div>
        <div class="kpi-subtext">
            <span><?php echo $monthSavings >= 0 ? 'Surplus this month' : 'Deficit this month'; ?></span>
        </div>
    </div>

    <!-- Savings Rate / Net Worth Context -->
    <div class="kpi-card kpi-rate">
        <div class="kpi-header">
            <span class="kpi-label">Savings Rate</span>
            <div class="kpi-icon-pill rate">
                <?php echo render_icon('trending-up', 'icon-sm'); ?>
            </div>
        </div>
        <div class="kpi-value tabular-nums">
            <?php echo $savingsRate; ?>%
        </div>
        <div class="kpi-subtext">
            <span>All-time balance: <?php echo format_currency($allTimeBalance); ?></span>
        </div>
    </div>
</div>

<?php if ($totalTransactionsCount === 0): ?>
    <!-- First-run empty state -->
    <div class="panel">
        <div class="empty-state">
            <div class="empty-illustration">
                <?php echo render_icon('rupee', 'icon-lg'); ?>
            </div>
            <h2 class="empty-title">No transactions yet</h2>
            <p class="empty-desc">
                Add your first income or expense to start seeing totals, charts and budget status here.
            </p>
            <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
                <button type="button" class="btn btn-primary" onclick="openQuickAddModal()">
                    <?php echo render_icon('plus', 'icon-sm'); ?>
                    <span>Record First Transaction</span>
                </button>
                <a href="budgets.php" class="btn btn-secondary">
                    <?php echo render_icon('budgets', 'icon-sm'); ?>
                    <span>Set Monthly Budgets</span>
                </a>
                <a href="categories.php" class="btn btn-secondary">
                    <?php echo render_icon('categories', 'icon-sm'); ?>
                    <span>View Categories</span>
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Analytics Grid: 6-Month Trends & Category Breakdown -->
<div class="dashboard-grid">
    <!-- Trend Chart Panel -->
    <div class="panel">
        <div class="panel-header">
            <h3>Cash Flow, Last 6 Months</h3>
            <span class="badge badge-category">Income vs expenses</span>
        </div>
        <div class="panel-body">
            <div style="position: relative; width: 100%; height: 280px;">
                <canvas id="cashFlowChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Category Breakdown Panel -->
    <div class="panel">
        <div class="panel-header">
            <h3>Expense Distribution</h3>
            <span class="badge badge-category"><?php echo date('M Y'); ?></span>
        </div>
        <div class="panel-body">
            <?php if (empty($categoryBreakdown)): ?>
                <div class="empty-state" style="padding: 2.5rem 1rem;">
                    <p class="empty-title" style="font-size: 1.0625rem;">No expenses this month</p>
                    <p class="empty-desc" style="font-size: 0.9375rem; margin-bottom: 1rem;">Expenses you add will be broken down by category here.</p>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="openQuickAddModal()">Add Expense</button>
                </div>
            <?php else: ?>
                <div class="category-breakdown-list">
                    <?php 
                    $displayed = 0;
                    foreach ($categoryBreakdown as $cat): 
                        if ($displayed++ >= 6) break;
                        $spent = (float)$cat['total_spent'];
                        $pct = $monthExpense > 0 ? round(($spent / $monthExpense) * 100, 1) : 0;
                    ?>
                        <div class="category-breakdown-item">
                            <div class="category-breakdown-header">
                                <span class="category-breakdown-name">
                                    <span class="category-dot" style="background-color: <?php echo e($cat['color']); ?>;"></span>
                                    <span><?php echo e($cat['name']); ?></span>
                                </span>
                                <div class="category-breakdown-stats">
                                    <span class="category-breakdown-amount tabular-nums"><?php echo format_currency($spent); ?></span>
                                    <span class="category-breakdown-pct tabular-nums"><?php echo $pct; ?>%</span>
                                </div>
                            </div>
                            <div class="progress-track" style="margin-top: 0.25rem; height: 5px;">
                                <div class="progress-bar" style="width: <?php echo min(100, $pct); ?>%; background-color: <?php echo e($cat['color']); ?>;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Transactions Panel -->
<div class="panel">
    <div class="panel-header">
        <h3>Recent Transactions</h3>
        <div class="panel-header-actions">
            <a href="transactions.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Type</th>
                    <th class="num">Amount</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentTransactions)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                            No transactions recorded yet. Click <strong>Record Transaction</strong> above to log your first record.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentTransactions as $row): ?>
                        <tr>
                            <td style="white-space: nowrap; color: var(--text-secondary);">
                                <?php echo format_date($row['transaction_date']); ?>
                            </td>
                            <td>
                                <span class="badge badge-category">
                                    <span class="category-dot" style="background-color: <?php echo e($row['category_color']); ?>;"></span>
                                    <?php echo e($row['category_name']); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo e($row['description'] ?: '—'); ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $row['type'] === 'income' ? 'badge-income' : 'badge-expense'; ?>">
                                    <?php echo ucfirst(e($row['type'])); ?>
                                </span>
                            </td>
                            <td class="num tabular-nums <?php echo $row['type'] === 'income' ? 'amount-positive' : 'amount-negative'; ?>">
                                <?php echo ($row['type'] === 'income' ? '+' : '-') . format_currency($row['amount']); ?>
                            </td>
                            <td class="actions">
                                <a href="transaction-edit.php?id=<?php echo (int)$row['id']; ?>" class="btn-icon" title="Edit transaction">
                                    <?php echo render_icon('edit', 'icon-sm'); ?>
                                </a>
                                <button type="button" class="btn-icon danger" title="Delete transaction" onclick="openConfirmDelete('transaction-delete.php', <?php echo (int)$row['id']; ?>, 'Are you sure you want to delete this <?php echo e($row['type']); ?> record of <?php echo format_currency($row['amount']); ?>?')">
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

<!-- Chart initialization script with Indian Rupee formatting -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('cashFlowChart');
    if (!ctx) return;

    const labels = <?php echo json_encode($chartLabels); ?>;
    const incomeData = <?php echo json_encode($chartIncome); ?>;
    const expenseData = <?php echo json_encode($chartExpense); ?>;

    // Helper to format in Indian numbering
    function formatINR(number) {
        return new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: 'INR',
            maximumFractionDigits: 0
        }).format(number);
    }

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Income',
                    data: incomeData,
                    backgroundColor: '#059669',
                    borderRadius: 0,
                    barPercentage: 0.65,
                    categoryPercentage: 0.6
                },
                {
                    label: 'Expenses',
                    data: expenseData,
                    backgroundColor: '#334155',
                    borderRadius: 0,
                    barPercentage: 0.65,
                    categoryPercentage: 0.6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 12,
                        boxHeight: 12,
                        usePointStyle: false,
                        font: { family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif', size: 14, weight: 500 },
                        color: '#475569'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return context.dataset.label + ': ' + formatINR(context.raw);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#64748b', font: { size: 13 } }
                },
                y: {
                    border: { dash: [4, 4] },
                    grid: { color: '#f1f5f9' },
                    ticks: {
                        color: '#64748b',
                        font: { size: 13 },
                        callback: function (value) {
                            return formatINR(value);
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
