<?php
/**
 * Ledger - Shared footer and modal dialogs
 */

declare(strict_types=1);

// Safely obtain categories for the quick-add modal
try {
    $footerPdo = getDBConnection();
    $modalCategories = get_all_categories($footerPdo);
} catch (Exception $e) {
    $modalCategories = [];
}
?>

</main> <!-- end .app-container -->

<footer class="app-footer">
    <div class="footer-container">
        <span>&copy; <?php echo date('Y'); ?> Ledger</span>
        <span></span>
    </div>
</footer>

<!-- Quick add transaction modal -->
<div class="modal-overlay" id="quickAddModal" role="dialog" aria-modal="true" aria-labelledby="quickAddTitle">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="quickAddTitle">Record Transaction</h3>
            <button type="button" class="btn-icon" onclick="closeQuickAddModal()" aria-label="Close modal">
                <?php echo render_icon('x', 'icon'); ?>
            </button>
        </div>
        <form action="transaction-add.php" method="POST" id="quickAddForm">
            <div class="modal-body">
                <?php echo csrf_field(); ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="quick_type" class="form-label">Type</label>
                        <select name="type" id="quick_type" class="form-control" required onchange="filterCategoriesByType('quick_type', 'quick_category_id')">
                            <option value="expense" selected>Expense (-)</option>
                            <option value="income">Income (+)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="quick_amount" class="form-label">Amount (₹)</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="quick_amount" class="form-control tabular-nums" placeholder="0.00" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="quick_category_id" class="form-label">Category</label>
                        <select name="category_id" id="quick_category_id" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php foreach ($modalCategories as $cat): ?>
                                <option value="<?php echo (int)$cat['id']; ?>" data-type="<?php echo e($cat['type']); ?>">
                                    <?php echo e($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="quick_date" class="form-label">Date</label>
                        <input type="date" name="transaction_date" id="quick_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="quick_description" class="form-label">Note (optional)</label>
                    <input type="text" name="description" id="quick_description" class="form-control" placeholder="Salary, Swiggy order, electricity bill" maxlength="255">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeQuickAddModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Transaction</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete confirmation modal -->
<div class="modal-overlay" id="confirmModal" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
    <div class="modal-card" style="max-width: 440px;">
        <div class="modal-header">
            <h3 id="confirmTitle">Confirm Delete</h3>
            <button type="button" class="btn-icon" onclick="closeConfirmModal()" aria-label="Close modal">
                <?php echo render_icon('x', 'icon'); ?>
            </button>
        </div>
        <form method="POST" id="confirmForm">
            <div class="modal-body">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" id="confirmId" value="">
                <p id="confirmMessage" style="color: var(--text-secondary); line-height: 1.5; font-size: 0.9375rem;">
                    Are you sure you want to delete this record? This cannot be undone.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeConfirmModal()">Cancel</button>
                <button type="submit" class="btn btn-danger-solid" id="confirmSubmitBtn">Delete</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/main.js"></script>
</body>
</html>
