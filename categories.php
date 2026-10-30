<?php
/**
 * Ledger
 * Category Management & Allocation Guardrails
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDBConnection();
$errors = [];

// Handle POST actions (Create Category or Safe Delete Category)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('error', 'Security verification failed. Please try again.');
        redirect('categories.php');
    }

    $action = $_POST['action'] ?? '';

    // Action: Create Category
    if ($action === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        $type = trim((string)($_POST['type'] ?? 'expense'));
        $color = trim((string)($_POST['color'] ?? '#64748b'));

        if (empty($name)) {
            $errors[] = 'Category name cannot be empty.';
        } elseif (mb_strlen($name) > 50) {
            $errors[] = 'Category name must not exceed 50 characters.';
        }

        if (!in_array($type, ['income', 'expense'], true)) {
            $errors[] = 'Invalid category type.';
        }

        if (!preg_match('/^#[a-fA-F0-9]{6}$/', $color)) {
            $color = '#64748b';
        }

        if (empty($errors)) {
            $checkStmt = $pdo->prepare('SELECT id FROM categories WHERE name = ? AND type = ?');
            $checkStmt->execute([$name, $type]);
            if ($checkStmt->fetch()) {
                $errors[] = "A {$type} category named '{$name}' already exists.";
            } else {
                $insertStmt = $pdo->prepare('
                    INSERT INTO categories (name, type, color, icon)
                    VALUES (?, ?, ?, "tag")
                ');
                $insertStmt->execute([$name, $type, $color]);
                set_flash('success', "Category '{$name}' created successfully.");
                redirect('categories.php');
            }
        }
    }

    // Action: Safe Delete Category
    if ($action === 'delete') {
        $catId = !empty($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($catId <= 0) {
            set_flash('error', 'Invalid category specified.');
            redirect('categories.php');
        }

        $catStmt = $pdo->prepare('SELECT name FROM categories WHERE id = ?');
        $catStmt->execute([$catId]);
        $cat = $catStmt->fetch();

        if (!$cat) {
            set_flash('error', 'Category not found.');
            redirect('categories.php');
        }

        // Referential integrity check: Are transactions attached?
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE category_id = ?');
        $countStmt->execute([$catId]);
        $txCount = (int)$countStmt->fetchColumn();

        if ($txCount > 0) {
            set_flash('error', "Cannot delete category '{$cat['name']}': {$txCount} transaction(s) are linked to it. Reassign or remove these transactions first to maintain accounting integrity.");
            redirect('categories.php');
        }

        try {
            $delStmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
            $delStmt->execute([$catId]);
            set_flash('success', "Category '{$cat['name']}' was removed.");
        } catch (PDOException $e) {
            set_flash('error', 'Database integrity check prevented category deletion.');
        }

        redirect('categories.php');
    }
}

// Fetch all categories with transaction count and total volume
$categoriesQuery = '
    SELECT 
        c.id,
        c.name,
        c.type,
        c.color,
        c.icon,
        COUNT(t.id) AS transaction_count,
        COALESCE(SUM(t.amount), 0) AS total_volume
    FROM categories c
    LEFT JOIN transactions t ON c.id = t.category_id
    GROUP BY c.id, c.name, c.type, c.color, c.icon
    ORDER BY c.type DESC, c.name ASC
';
$categories = $pdo->query($categoriesQuery)->fetchAll();

// Group categories by type
$incomeCategories = array_filter($categories, fn($c) => $c['type'] === 'income');
$expenseCategories = array_filter($categories, fn($c) => $c['type'] === 'expense');

$page_title = 'Categories';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Categories</h1>
        <p>Groups used to sort income and expenses.</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <div class="alert-content">
            <?php echo render_icon('alert-triangle', 'icon'); ?>
            <div>
                <strong>Unable to save category:</strong>
                <ul style="margin-top: 0.25rem; padding-left: 1.25rem;">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo e($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="dashboard-grid" style="grid-template-columns: 1.8fr 1fr;">
    <!-- Left: Categories Listing -->
    <div>
        <!-- Expense Categories Panel -->
        <div class="panel">
            <div class="panel-header">
                <h3>Expense Categories</h3>
                <span class="badge badge-expense"><?php echo count($expenseCategories); ?> categories</span>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Transactions</th>
                            <th class="num">Total Spent</th>
                            <th class="actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenseCategories as $ec): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-category">
                                        <span class="category-dot" style="background-color: <?php echo e($ec['color']); ?>;"></span>
                                        <span><?php echo e($ec['name']); ?></span>
                                    </span>
                                </td>
                                <td style="color: var(--text-secondary);">
                                    <?php echo (int)$ec['transaction_count']; ?>
                                </td>
                                <td class="num tabular-nums amount-negative">
                                    <?php echo format_currency($ec['total_volume']); ?>
                                </td>
                                <td class="actions">
                                    <button type="button" class="btn-icon danger" title="Delete Category" onclick="openConfirmDelete('categories.php', <?php echo (int)$ec['id']; ?>, 'Are you sure you want to delete category \'<?php echo e($ec['name']); ?>\'?')">
                                        <?php echo render_icon('trash', 'icon-sm'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Income Categories Panel -->
        <div class="panel">
            <div class="panel-header">
                <h3>Income Categories</h3>
                <span class="badge badge-income"><?php echo count($incomeCategories); ?> categories</span>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Transactions</th>
                            <th class="num">Total Received</th>
                            <th class="actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($incomeCategories as $ic): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-category">
                                        <span class="category-dot" style="background-color: <?php echo e($ic['color']); ?>;"></span>
                                        <span><?php echo e($ic['name']); ?></span>
                                    </span>
                                </td>
                                <td style="color: var(--text-secondary);">
                                    <?php echo (int)$ic['transaction_count']; ?>
                                </td>
                                <td class="num tabular-nums amount-positive">
                                    <?php echo format_currency($ic['total_volume']); ?>
                                </td>
                                <td class="actions">
                                    <button type="button" class="btn-icon danger" title="Delete Category" onclick="openConfirmDelete('categories.php', <?php echo (int)$ic['id']; ?>, 'Are you sure you want to delete category \'<?php echo e($ic['name']); ?>\'?')">
                                        <?php echo render_icon('trash', 'icon-sm'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right: Create Category Panel -->
    <div>
        <div class="panel">
            <div class="panel-header">
                <h3>New Category</h3>
            </div>
            <form action="categories.php" method="POST">
                <div class="panel-body">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="create">

                    <div class="form-group">
                        <label for="cat_name" class="form-label">Name</label>
                        <input type="text" name="name" id="cat_name" class="form-control" placeholder="Gym, Pet care" maxlength="50" required>
                    </div>

                    <div class="form-group">
                        <label for="cat_type" class="form-label">Type</label>
                        <select name="type" id="cat_type" class="form-control" required>
                            <option value="expense" selected>Expense</option>
                            <option value="income">Income</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="cat_color" class="form-label">Colour</label>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <input type="color" name="color" id="cat_color" value="#0284c7" style="height: 40px; width: 52px; padding: 2px; border: 1px solid var(--border-default); border-radius: var(--radius-xs); cursor: pointer; background: #fff;">
                            <span class="form-hint" style="margin: 0;">Shown next to the category name.</span>
                        </div>
                    </div>
                </div>

                <div class="panel-footer">
                    <button type="submit" class="btn btn-primary">Create Category</button>
                </div>
            </form>
        </div>

        <div class="note-box">
            <strong>Deleting categories</strong>
            A category that has transactions cannot be deleted. Move or remove those transactions first.</div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const confirmForm = document.getElementById('confirmForm');
    if (confirmForm) {
        let actionInput = confirmForm.querySelector('input[name="action"]');
        if (!actionInput) {
            actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';
            confirmForm.appendChild(actionInput);
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
