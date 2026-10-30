<?php
/**
 * Ledger - Shared HTML head and page header
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Safe page title
$title = isset($page_title) ? e($page_title) . ' — Ledger' : 'Ledger — Personal Finance';
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Track income, expenses and monthly budgets in one place.">
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <!-- Chart.js for data visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>

<?php require_once __DIR__ . '/navbar.php'; ?>

<main class="app-container">

<?php if ($flash): ?>
    <div class="alert alert-<?php echo e($flash['type']); ?>" id="flashAlert">
        <div class="alert-content">
            <?php if ($flash['type'] === 'success'): ?>
                <?php echo render_icon('check-circle', 'icon'); ?>
            <?php elseif ($flash['type'] === 'warning'): ?>
                <?php echo render_icon('alert-triangle', 'icon'); ?>
            <?php elseif ($flash['type'] === 'error'): ?>
                <?php echo render_icon('alert-triangle', 'icon'); ?>
            <?php else: ?>
                <?php echo render_icon('info', 'icon'); ?>
            <?php endif; ?>
            <span><?php echo e($flash['message']); ?></span>
        </div>
        <button type="button" class="alert-close" onclick="document.getElementById('flashAlert').remove();" aria-label="Close notification">
            <?php echo render_icon('x', 'icon'); ?>
        </button>
    </div>
<?php endif; ?>
