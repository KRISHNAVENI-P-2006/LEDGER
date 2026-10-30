<?php
/**
 * Ledger
 * Add Transaction Controller & Form
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDBConnection();
$errors = [];

// Form pre-population defaults
$type = $_POST['type'] ?? 'expense';
$amount = $_POST['amount'] ?? '';
$categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
$date = $_POST['transaction_date'] ?? date('Y-m-d');
$description = trim((string)($_POST['description'] ?? ''));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = 'Security token expired or invalid. Please try again.';
    }

    if (!in_array($type, ['income', 'expense'], true)) {
        $errors[] = 'Invalid transaction type selected.';
    }

    $cleanAmount = filter_var($amount, FILTER_VALIDATE_FLOAT);
    if ($cleanAmount === false || $cleanAmount <= 0) {
        $errors[] = 'Please enter a valid monetary amount greater than ₹0.';
    }

    $d = DateTime::createFromFormat('Y-m-d', (string)$date);
    if (!$d || $d->format('Y-m-d') !== $date) {
        $errors[] = 'Please select a valid date (YYYY-MM-DD).';
    }

    if ($categoryId <= 0) {
        $errors[] = 'Please select a category.';
    } else {
        $catStmt = $pdo->prepare('SELECT id, type FROM categories WHERE id = ?');
        $catStmt->execute([$categoryId]);
        $cat = $catStmt->fetch();
        if (!$cat) {
            $errors[] = 'The selected category does not exist.';
        } elseif ($cat['type'] !== $type) {
            $errors[] = 'The selected category does not match the transaction type.';
        }
    }

    if (mb_strlen($description) > 255) {
        $errors[] = 'Description must not exceed 255 characters.';
    }

    if (empty($errors)) {
        $insertStmt = $pdo->prepare('
            INSERT INTO transactions (type, category_id, amount, transaction_date, description)
            VALUES (:type, :category_id, :amount, :transaction_date, :description)
        ');
        $insertStmt->execute([
            'type'             => $type,
            'category_id'      => $categoryId,
            'amount'           => $cleanAmount,
            'transaction_date' => $date,
            'description'      => !empty($description) ? $description : null,
        ]);

        set_flash('success', 'Transaction recorded successfully.');
        redirect('transactions.php');
    }
}

// Fetch categories for dropdown
$categories = get_all_categories($pdo);

$page_title = 'Add Transaction';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Record Transaction</h1>
        <p>Add a new income or expense entry.</p>
    </div>
    <div class="page-actions">
        <a href="transactions.php" class="btn btn-secondary">
            <?php echo render_icon('transactions', 'icon-sm'); ?>
            <span>Back to Ledger</span>
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <div class="alert-content">
            <?php echo render_icon('alert-triangle', 'icon'); ?>
            <div>
                <strong>Please resolve the following issues:</strong>
                <ul style="margin-top: 0.25rem; padding-left: 1.25rem;">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo e($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="panel" style="max-width: 640px; margin: 0 auto;">
    <div class="panel-header">
        <h2>Transaction Details</h2>
    </div>
    <form action="transaction-add.php" method="POST">
        <div class="panel-body">
            <?php echo csrf_field(); ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="type" class="form-label">Transaction Type</label>
                    <select name="type" id="page_type" class="form-control" required onchange="filterCategoriesByType('page_type', 'page_category_id')">
                        <option value="expense" <?php echo $type === 'expense' ? 'selected' : ''; ?>>Expense (-)</option>
                        <option value="income" <?php echo $type === 'income' ? 'selected' : ''; ?>>Income (+)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="amount" class="form-label">Amount (₹)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="amount" class="form-control tabular-nums" placeholder="0.00" value="<?php echo e($amount); ?>" required autofocus>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="page_category_id" class="form-label">Category</label>
                    <select name="category_id" id="page_category_id" class="form-control" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo (int)$cat['id']; ?>" data-type="<?php echo e($cat['type']); ?>" <?php echo $categoryId === (int)$cat['id'] ? 'selected' : ''; ?>>
                                <?php echo e($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="transaction_date" class="form-label">Transaction Date</label>
                    <input type="date" name="transaction_date" id="transaction_date" class="form-control" value="<?php echo e($date); ?>" required>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 0.5rem;">
                <label for="description" class="form-label">Description / Note</label>
                <input type="text" name="description" id="description" class="form-control" placeholder="Salary credit, Swiggy order, metro card recharge" maxlength="255" value="<?php echo e($description); ?>">
                <span class="form-hint">Optional, up to 255 characters.</span>
            </div>
        </div>

        <div class="panel-footer">
            <a href="transactions.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Transaction</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    filterCategoriesByType('page_type', 'page_category_id');
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
