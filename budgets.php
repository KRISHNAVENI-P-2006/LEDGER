<?php
/**
 * Ledger
 * Monthly Category Budgets & Spending Limits
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDBConnection();

// Selected month and year (defaults to current)
$month = !empty($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$year  = !empty($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');

// Month boundaries
if ($month < 1 || $month > 12) $month = (int)date('n');
if ($year < 2000 || $year > 2100) $year = (int)date('Y');

$currentTimestamp = strtotime(sprintf('%04d-%02d-01', $year, $month));
$monthDisplay = date('F Y', $currentTimestamp);

// Previous and next month links
$prevTimestamp = strtotime('-1 month', $currentTimestamp);
$prevMonth = (int)date('n', $prevTimestamp);
$prevYear  = (int)date('Y', $prevTimestamp);

$nextTimestamp = strtotime('+1 month', $currentTimestamp);
$nextMonth = (int)date('n', $nextTimestamp);
$nextYear  = (int)date('Y', $nextTimestamp);

// Fetch all expense categories and join with their budgets and actual spending for this month
$stmt = $pdo->prepare('
    SELECT 
        c.id AS category_id,
        c.name AS category_name,
        c.color AS category_color,
        c.icon AS category_icon,
        b.id AS budget_id,
        b.amount AS budget_limit,
        COALESCE(t.spent, 0) AS actual_spent
    FROM categories c
    LEFT JOIN budgets b ON b.category_id = c.id AND b.month = :month AND b.year = :year
    LEFT JOIN (
        SELECT category_id, SUM(amount) AS spent
        FROM transactions
        WHERE type = "expense"
          AND YEAR(transaction_date) = :t_year
          AND MONTH(transaction_date) = :t_month
        GROUP BY category_id
    ) t ON t.category_id = c.id
    WHERE c.type = "expense"
    ORDER BY (b.amount IS NOT NULL) DESC, c.name ASC
');
$stmt->execute([
    'month'   => $month,
    'year'    => $year,
    't_month' => $month,
    't_year'  => $year,
]);
$budgetRows = $stmt->fetchAll();

// Calculate aggregate totals
$totalBudgeted = 0.0;
$totalSpentOnBudgeted = 0.0;
$totalOverspent = 0.0;
$activeBudgetCount = 0;

$budgetedItems = [];
$unbudgetedItems = [];

foreach ($budgetRows as $row) {
    if ($row['budget_limit'] !== null) {
        $limit = (float)$row['budget_limit'];
        $spent = (float)$row['actual_spent'];
        $totalBudgeted += $limit;
        $totalSpentOnBudgeted += $spent;
        if ($spent > $limit) {
            $totalOverspent += ($spent - $limit);
        }
        $activeBudgetCount++;
        $budgetedItems[] = $row;
    } else {
        $unbudgetedItems[] = $row;
    }
}

$remainingTotal = $totalBudgeted - $totalSpentOnBudgeted;
$overallUtilization = $totalBudgeted > 0 ? round(($totalSpentOnBudgeted / $totalBudgeted) * 100, 1) : 0.0;

// Fetch all expense categories for the modal
$expenseCategories = array_filter(get_all_categories($pdo), fn($c) => $c['type'] === 'expense');

$page_title = 'Monthly Budgets';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="page-header">
    <div class="page-title-group">
        <h1>Monthly Budgets</h1>
        <p>Set a monthly spending limit for each category and track it.</p>
    </div>
    <div class="page-actions">
        <!-- Month Navigator -->
        <div style="display: flex; align-items: center; background: #ffffff; border: 1px solid var(--border-default); border-radius: var(--radius-sm); padding: 2px;">
            <a href="budgets.php?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="btn-icon" title="Previous Month: <?php echo date('M Y', $prevTimestamp); ?>">
                &larr;
            </a>
            <span style="padding: 0 0.875rem; font-weight: 600; font-size: 1rem; white-space: nowrap;">
                <?php echo e($monthDisplay); ?>
            </span>
            <a href="budgets.php?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="btn-icon" title="Next Month: <?php echo date('M Y', $nextTimestamp); ?>">
                &rarr;
            </a>
        </div>

        <button type="button" class="btn btn-primary" onclick="openBudgetModal()">
            <?php echo render_icon('plus', 'icon-sm'); ?>
            <span>Set Category Budget</span>
        </button>
    </div>
</div>

<!-- Month Budget Summary KPIs -->
<div class="kpi-grid">
    <div class="kpi-card kpi-balance">
        <div class="kpi-header">
            <span class="kpi-label">Total Budgeted</span>
            <div class="kpi-icon-pill balance">
                <?php echo render_icon('budgets', 'icon-sm'); ?>
            </div>
        </div>
        <div class="kpi-value tabular-nums">
            <?php echo format_currency($totalBudgeted); ?>
        </div>
        <div class="kpi-subtext">
            <span>Across <?php echo $activeBudgetCount; ?> categories</span>
        </div>
    </div>

    <div class="kpi-card kpi-expense">
        <div class="kpi-header">
            <span class="kpi-label">Spent</span>
            <div class="kpi-icon-pill expense">
                <?php echo render_icon('arrow-up-right', 'icon-sm'); ?>
            </div>
        </div>
        <div class="kpi-value tabular-nums">
            <?php echo format_currency($totalSpentOnBudgeted); ?>
        </div>
        <div class="kpi-subtext">
            <span>Utilization: <?php echo $overallUtilization; ?>%</span>
        </div>
    </div>

    <div class="kpi-card kpi-income">
        <div class="kpi-header">
            <span class="kpi-label">Remaining</span>
            <div class="kpi-icon-pill income">
                <?php echo render_icon('check-circle', 'icon-sm'); ?>
            </div>
        </div>
        <div class="kpi-value tabular-nums" style="<?php echo $remainingTotal >= 0 ? 'color: var(--income-text);' : 'color: var(--expense-text);'; ?>">
            <?php echo ($remainingTotal >= 0 ? '' : '-') . format_currency(abs($remainingTotal)); ?>
        </div>
        <div class="kpi-subtext">
            <span><?php echo $remainingTotal >= 0 ? 'Within limits' : 'Over total budget'; ?></span>
        </div>
    </div>

    <div class="kpi-card kpi-rate">
        <div class="kpi-header">
            <span class="kpi-label">Over Limit</span>
            <div class="kpi-icon-pill" style="color: <?php echo $totalOverspent > 0 ? 'var(--expense-text)' : 'var(--text-muted)'; ?>;">
                <?php echo render_icon('alert-triangle', 'icon-sm'); ?>
            </div>
        </div>
        <div class="kpi-value tabular-nums" style="<?php echo $totalOverspent > 0 ? 'color: var(--expense-text);' : ''; ?>">
            <?php echo format_currency($totalOverspent); ?>
        </div>
        <div class="kpi-subtext">
            <span>Above limits</span>
        </div>
    </div>
</div>

<!-- Active Budgets Grid -->
<div class="panel">
    <div class="panel-header">
        <h3>Budget Limits &amp; Tracking &mdash; <?php echo e($monthDisplay); ?></h3>
        <div class="panel-header-actions">
            <?php if (empty($budgetedItems)): ?>
                <form action="budget-actions.php" method="POST" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="copy_prev">
                    <input type="hidden" name="month" value="<?php echo $month; ?>">
                    <input type="hidden" name="year" value="<?php echo $year; ?>">
                    <button type="submit" class="btn btn-secondary btn-sm" title="Copy categories and allocations from previous month">
                        <?php echo render_icon('refresh', 'icon-sm'); ?>
                        <span>Copy Previous Month</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel-body">
        <?php if (empty($budgetedItems)): ?>
            <div class="empty-state">
                <div class="empty-illustration">
                    <?php echo render_icon('budgets', 'icon-lg'); ?>
                </div>
                <h3 class="empty-title">No spending limits set for <?php echo e($monthDisplay); ?></h3>
                <p class="empty-desc">
                    Set limits for groceries, dining, commute, shopping and utilities to see how the month is going.
                </p>
                <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
                    <form action="budget-actions.php" method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="copy_prev">
                        <input type="hidden" name="month" value="<?php echo $month; ?>">
                        <input type="hidden" name="year" value="<?php echo $year; ?>">
                        <button type="submit" class="btn btn-secondary btn-sm">Copy From <?php echo date('M Y', $prevTimestamp); ?></button>
                    </form>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openBudgetModal()">Set Category Budget</button>
                </div>
            </div>
        <?php else: ?>
            <div class="budget-grid">
                <?php foreach ($budgetedItems as $b): 
                    $limit = (float)$b['budget_limit'];
                    $spent = (float)$b['actual_spent'];
                    $rem   = $limit - $spent;
                    $pct   = $limit > 0 ? round(($spent / $limit) * 100, 1) : 0.0;
                    $isOver = $spent > $limit;
                    $isWarning = (!$isOver && $pct >= 80);

                    $barClass = 'safe';
                    if ($isOver) {
                        $barClass = 'danger';
                    } elseif ($isWarning) {
                        $barClass = 'warning';
                    }
                ?>
                    <div class="budget-card <?php echo $isOver ? 'is-overbudget' : ''; ?>">
                        <div class="budget-card-header">
                            <span class="budget-cat-title">
                                <span class="category-dot" style="background-color: <?php echo e($b['category_color']); ?>;"></span>
                                <span><?php echo e($b['category_name']); ?></span>
                            </span>
                            <?php if ($isOver): ?>
                                <span class="badge badge-danger">
                                    <?php echo render_icon('alert-triangle', 'icon-sm'); ?> Over Budget
                                </span>
                            <?php elseif ($isWarning): ?>
                                <span class="badge badge-warning">Near limit</span>
                            <?php else: ?>
                                <span class="badge badge-income">On Track</span>
                            <?php endif; ?>
                        </div>

                        <div class="budget-figures">
                            <div>
                                <span class="spent tabular-nums"><?php echo format_currency($spent); ?></span>
                                <span> / <?php echo format_currency($limit); ?></span>
                            </div>
                            <div class="tabular-nums" style="font-weight: 600; font-size: 1rem;">
                                <?php echo $pct; ?>%
                            </div>
                        </div>

                        <div class="progress-container">
                            <div class="progress-track">
                                <div class="progress-bar <?php echo $barClass; ?>" style="width: <?php echo min(100, $pct); ?>%;"></div>
                            </div>
                        </div>

                        <div class="budget-footer">
                            <div>
                                <?php if ($isOver): ?>
                                    <span style="color: var(--expense-text); font-weight: 600;">+<?php echo format_currency($spent - $limit); ?> over limit</span>
                                <?php else: ?>
                                    <span style="color: var(--text-secondary);"><?php echo format_currency($rem); ?> remaining</span>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; gap: 0.25rem;">
                                <button type="button" class="btn-icon" title="Edit limit" onclick="editBudgetLimit(<?php echo (int)$b['category_id']; ?>, <?php echo $limit; ?>)">
                                    <?php echo render_icon('edit', 'icon-sm'); ?>
                                </button>
                                <button type="button" class="btn-icon danger" title="Remove budget" onclick="openConfirmDelete('budget-actions.php', <?php echo (int)$b['budget_id']; ?>, 'Remove budget allocation for <?php echo e($b['category_name']); ?>?')">
                                    <?php echo render_icon('trash', 'icon-sm'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Unbudgeted Expense Categories Section -->
<?php if (!empty($unbudgetedItems)): ?>
    <div class="panel">
        <div class="panel-header">
            <h3>Unbudgeted Categories (<?php echo count($unbudgetedItems); ?>)</h3>
            <span class="badge badge-category">No limit set</span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th class="num">Spent this Month</th>
                        <th class="actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($unbudgetedItems as $ub): ?>
                        <tr>
                            <td>
                                <span class="badge badge-category">
                                    <span class="category-dot" style="background-color: <?php echo e($ub['category_color']); ?>;"></span>
                                    <span><?php echo e($ub['category_name']); ?></span>
                                </span>
                            </td>
                            <td class="num tabular-nums amount-negative">
                                <?php echo format_currency($ub['actual_spent']); ?>
                            </td>
                            <td class="actions">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="editBudgetLimit(<?php echo (int)$ub['category_id']; ?>, 0)">
                                    <?php echo render_icon('plus', 'icon-sm'); ?>
                                    <span>Assign Budget</span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Set / Edit Budget Modal -->
<div class="modal-overlay" id="budgetModal" role="dialog" aria-modal="true" aria-labelledby="budgetModalTitle">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="budgetModalTitle">Set Monthly Budget Limit</h3>
            <button type="button" class="btn-icon" onclick="closeBudgetModal()" aria-label="Close modal">
                <?php echo render_icon('x', 'icon'); ?>
            </button>
        </div>
        <form action="budget-actions.php" method="POST">
            <div class="modal-body">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="set_budget">
                <input type="hidden" name="month" value="<?php echo $month; ?>">
                <input type="hidden" name="year" value="<?php echo $year; ?>">

                <div class="form-group">
                    <label for="budget_category_id" class="form-label">Expense Category</label>
                    <select name="category_id" id="budget_category_id" class="form-control" required>
                        <option value="">Select Expense Category</option>
                        <?php foreach ($expenseCategories as $ec): ?>
                            <option value="<?php echo (int)$ec['id']; ?>">
                                <?php echo e($ec['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="budget_amount" class="form-label">Monthly limit (₹)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="budget_amount" class="form-control tabular-nums" placeholder="15000.00" required>
                    <span class="form-hint">Limit for <?php echo e($monthDisplay); ?>.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeBudgetModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Budget</button>
            </div>
        </form>
    </div>
</div>

<script>
function openBudgetModal() {
    const modal = document.getElementById('budgetModal');
    if (modal) {
        modal.classList.add('active');
        setTimeout(() => document.getElementById('budget_amount').focus(), 100);
    }
}

function closeBudgetModal() {
    const modal = document.getElementById('budgetModal');
    if (modal) modal.classList.remove('active');
}

function editBudgetLimit(categoryId, currentAmount) {
    const catSelect = document.getElementById('budget_category_id');
    const amtInput  = document.getElementById('budget_amount');
    if (catSelect) catSelect.value = categoryId;
    if (amtInput && currentAmount > 0) amtInput.value = currentAmount;
    openBudgetModal();
}

// Ensure delete action for budgets is passed in confirm form
document.addEventListener('DOMContentLoaded', () => {
    const confirmForm = document.getElementById('confirmForm');
    if (confirmForm) {
        let actionInput = confirmForm.querySelector('input[name="action"]');
        if (!actionInput) {
            actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_budget';
            confirmForm.appendChild(actionInput);
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
