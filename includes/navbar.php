<?php
/**
 * Ledger - Top Navigation Bar
 */

declare(strict_types=1);

$navProfile = get_profile();
?>
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="brand-link">
            <span class="brand-mark">₹</span>
            <span>Ledger</span>
        </a>

        <ul class="nav-links">
            <li>
                <a href="index.php" class="nav-link <?php echo is_active_nav('index.php'); ?>">
                    <?php echo render_icon('dashboard', 'icon-sm'); ?>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="transactions.php" class="nav-link <?php echo (is_active_nav('transactions.php') || is_active_nav('transaction-add.php') || is_active_nav('transaction-edit.php')); ?>">
                    <?php echo render_icon('transactions', 'icon-sm'); ?>
                    <span>Transactions</span>
                </a>
            </li>
            <li>
                <a href="budgets.php" class="nav-link <?php echo is_active_nav('budgets.php'); ?>">
                    <?php echo render_icon('budgets', 'icon-sm'); ?>
                    <span>Budgets</span>
                </a>
            </li>
            <li>
                <a href="categories.php" class="nav-link <?php echo is_active_nav('categories.php'); ?>">
                    <?php echo render_icon('categories', 'icon-sm'); ?>
                    <span>Categories</span>
                </a>
            </li>
        </ul>

        <div class="nav-actions">
            <button type="button" class="btn btn-secondary btn-sm" id="btnQuickAddTransaction" onclick="openQuickAddModal()">
                <?php echo render_icon('plus', 'icon-sm'); ?>
                <span>New Record</span>
            </button>
            <a href="profile.php" class="nav-profile <?php echo is_active_nav('profile.php'); ?>" title="Profile">
                <span class="avatar"><?php echo e(profile_initials((string)$navProfile['full_name'])); ?></span>
                <span class="nav-profile-name"><?php echo e($navProfile['full_name']); ?></span>
            </a>
        </div>
    </div>
</nav>
