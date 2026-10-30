<?php
/**
 * Ledger - Account Profile
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDBConnection();
$profile = get_profile();
$errors = [];

$form = [
    'full_name'  => (string)$profile['full_name'],
    'email'      => (string)($profile['email'] ?? ''),
    'phone'      => (string)($profile['phone'] ?? ''),
    'occupation' => (string)($profile['occupation'] ?? ''),
    'city'       => (string)($profile['city'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $_) {
        $form[$key] = trim((string)($_POST[$key] ?? ''));
    }

    if (!validate_csrf()) {
        $errors[] = 'Your session expired. Please submit the form again.';
    }

    if ($form['full_name'] === '') {
        $errors[] = 'Full name is required.';
    } elseif (mb_strlen($form['full_name']) > 80) {
        $errors[] = 'Full name must not exceed 80 characters.';
    }

    if ($form['email'] !== '') {
        if (mb_strlen($form['email']) > 120 || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
    }

    if ($form['phone'] !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $form['phone'])) {
        $errors[] = 'Enter a valid phone number.';
    }

    if (mb_strlen($form['occupation']) > 80) {
        $errors[] = 'Occupation must not exceed 80 characters.';
    }

    if (mb_strlen($form['city']) > 60) {
        $errors[] = 'City must not exceed 60 characters.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('
            UPDATE profile
            SET full_name = :full_name, email = :email, phone = :phone,
                occupation = :occupation, city = :city
            WHERE id = 1
        ');
        $stmt->execute([
            'full_name'  => $form['full_name'],
            'email'      => $form['email'] !== '' ? $form['email'] : null,
            'phone'      => $form['phone'] !== '' ? $form['phone'] : null,
            'occupation' => $form['occupation'] !== '' ? $form['occupation'] : null,
            'city'       => $form['city'] !== '' ? $form['city'] : null,
        ]);

        set_flash('success', 'Profile updated.');
        redirect('profile.php');
    }
}

// Account activity summary
$activity = $pdo->query('
    SELECT
        COUNT(*) AS tx_count,
        MIN(transaction_date) AS first_date,
        MAX(transaction_date) AS last_date
    FROM transactions
')->fetch();
$categoryCount = (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$budgetCount = (int)$pdo->query('SELECT COUNT(*) FROM budgets')->fetchColumn();

$page_title = 'Profile';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Profile</h1>
        <p>Your account details.</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <div class="alert-content">
            <?php echo render_icon('alert-triangle', 'icon'); ?>
            <div>
                <strong>Profile could not be saved:</strong>
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
    <div class="panel">
        <div class="panel-header">
            <h2>Personal Details</h2>
        </div>
        <form action="profile.php" method="POST">
            <div class="panel-body">
                <?php echo csrf_field(); ?>

                <div class="form-group">
                    <label for="full_name" class="form-label">Full name</label>
                    <input type="text" name="full_name" id="full_name" class="form-control" maxlength="80" value="<?php echo e($form['full_name']); ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" name="email" id="email" class="form-control" maxlength="120" value="<?php echo e($form['email']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" name="phone" id="phone" class="form-control" maxlength="20" placeholder="+91 98765 43210" value="<?php echo e($form['phone']); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="occupation" class="form-label">Occupation</label>
                        <input type="text" name="occupation" id="occupation" class="form-control" maxlength="80" value="<?php echo e($form['occupation']); ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="city" class="form-label">City</label>
                        <input type="text" name="city" id="city" class="form-control" maxlength="60" value="<?php echo e($form['city']); ?>">
                    </div>
                </div>
            </div>
            <div class="panel-footer">
                <a href="index.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    <div>
        <div class="panel">
            <div class="panel-body profile-card">
                <span class="avatar avatar-lg"><?php echo e(profile_initials($profile['full_name'])); ?></span>
                <div>
                    <div class="profile-name"><?php echo e($profile['full_name']); ?></div>
                    <div class="profile-meta">
                        <?php
                        $meta = array_filter([(string)($profile['occupation'] ?? ''), (string)($profile['city'] ?? '')]);
                        echo $meta ? e(implode(', ', $meta)) : 'No occupation or city added';
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h3>Account Summary</h3>
            </div>
            <dl class="summary-list">
                <div>
                    <dt>Account created</dt>
                    <dd><?php echo format_date((string)$profile['created_at']); ?></dd>
                </div>
                <div>
                    <dt>Transactions</dt>
                    <dd class="tabular-nums"><?php echo (int)$activity['tx_count']; ?></dd>
                </div>
                <div>
                    <dt>First entry</dt>
                    <dd><?php echo $activity['first_date'] ? format_date($activity['first_date']) : '—'; ?></dd>
                </div>
                <div>
                    <dt>Latest entry</dt>
                    <dd><?php echo $activity['last_date'] ? format_date($activity['last_date']) : '—'; ?></dd>
                </div>
                <div>
                    <dt>Categories</dt>
                    <dd class="tabular-nums"><?php echo $categoryCount; ?></dd>
                </div>
                <div>
                    <dt>Budgets set</dt>
                    <dd class="tabular-nums"><?php echo $budgetCount; ?></dd>
                </div>
                <div>
                    <dt>Currency</dt>
                    <dd>Indian Rupee (₹)</dd>
                </div>
            </dl>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
