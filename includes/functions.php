<?php
/**
 * Ledger
 * Core Application Functions & Security Helpers
 * Configured with Indian Currency (INR ₹) & Numbering System
 */

declare(strict_types=1);

// Ensure session is started safely for CSRF and flash messaging
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escapes output strings safely to prevent Cross-Site Scripting (XSS).
 * 
 * @param mixed $value
 * @return string
 */
function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Generates or retrieves the active session CSRF token.
 * 
 * @return string
 */
function get_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Outputs a hidden CSRF token input field for secure POST forms.
 * 
 * @return string HTML input string
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(get_csrf_token()) . '">';
}

/**
 * Validates the incoming CSRF token in POST requests.
 * 
 * @return bool True if valid, false otherwise.
 */
function validate_csrf(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }
    $token = $_POST['csrf_token'] ?? '';
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Sets a flash notification message stored in the session.
 * 
 * @param string $type 'success' | 'error' | 'warning' | 'info'
 * @param string $message User facing description
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash_message'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Retrieves and clears the flash notification message.
 * 
 * @return array{type: string, message: string}|null
 */
function get_flash(): ?array
{
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

/**
 * Performs a standard HTTP redirect and exits execution.
 * 
 * @param string $url Target location
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Formats a numeric value into the Indian Rupee numbering format (Lakhs & Crores).
 * Example: 150000 -> ₹1,50,000.00
 *          4850   -> ₹4,850.00
 * 
 * @param float|int|string $amount
 * @param string $symbol Default '₹'
 * @return string
 */
function format_currency(float|int|string $amount, string $symbol = '₹'): string
{
    $val = (float)$amount;
    $isNegative = $val < 0;
    $absVal = abs($val);

    // Format with 2 decimal places
    $formatted = number_format($absVal, 2, '.', '');
    [$integerPart, $decimalPart] = explode('.', $formatted);

    // Apply Indian comma grouping (last 3 digits, then pairs of 2)
    if (strlen($integerPart) > 3) {
        $lastThree = substr($integerPart, -3);
        $remaining = substr($integerPart, 0, -3);
        $remainingFormatted = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $remaining);
        $integerPart = $remainingFormatted . ',' . $lastThree;
    }

    return ($isNegative ? '-' : '') . $symbol . $integerPart . '.' . $decimalPart;
}

/**
 * Formats standard date strings into clean, readable Indian display dates.
 * 
 * @param string $date
 * @param string $format Default 'd M, Y' (e.g. '11 Oct, 2026')
 * @return string
 */
function format_date(string $date, string $format = 'd M, Y'): string
{
    if (empty($date)) {
        return '—';
    }
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : e($date);
}

/**
 * Checks if the current page matches the requested navigation route.
 * 
 * @param string $filename
 * @return string 'active' or ''
 */
function is_active_nav(string $filename): string
{
    $current = basename($_SERVER['PHP_SELF'] ?? '');
    return ($current === $filename) ? 'active' : '';
}

/**
 * Fetches all categories, optionally filtered by type.
 * 
 * @param PDO $pdo
 * @param string|null $type 'income' | 'expense' | null
 * @return array
 */
function get_all_categories(PDO $pdo, ?string $type = null): array
{
    if ($type !== null) {
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE type = ? ORDER BY name ASC');
        $stmt->execute([$type]);
    } else {
        $stmt = $pdo->query('SELECT * FROM categories ORDER BY type ASC, name ASC');
    }
    return $stmt->fetchAll();
}

/**
 * Returns inline clean SVG icons (Monochrome feather/heroicons style).
 * 
 * @param string $name
 * @param string $class
 * @return string SVG element markup
 */
function render_icon(string $name, string $class = 'icon'): string
{
    $icons = [
        'dashboard' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect></svg>',
        'transactions' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 16V4M7 4L3 8M7 4L11 8M17 8V20M17 20L21 16M17 20L13 16"></path></svg>',
        'budgets' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>',
        'categories' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>',
        'export' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>',
        'plus' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>',
        'edit' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>',
        'trash' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>',
        'search' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
        'filter' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>',
        'arrow-up-right' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"></line><polyline points="7 7 17 7 17 17"></polyline></svg>',
        'arrow-down-left' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="17" y1="7" x2="7" y2="17"></line><polyline points="17 17 7 17 7 7"></polyline></svg>',
        'alert-triangle' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
        'check-circle' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
        'calendar' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>',
        'x' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>',
        'refresh' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>',
        'rupee' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12M6 8h12M6 13l8.5 8M6 13h3a4 4 0 0 0 0-8H6"></path></svg>',
        'trending-up' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>',
        'briefcase' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>',
        'laptop' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2" ry="2"></rect><line x1="2" y1="20" x2="22" y2="20"></line></svg>',
        'home' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>',
        'shopping-cart' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>',
        'coffee' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg>',
        'zap' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>',
        'truck' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>',
        'heart' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>',
        'monitor' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>',
        'book-open' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>',
        'tag' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>',
        'folder' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>',
    ];

    return $icons[$name] ?? $icons['folder'];
}


/**
 * Returns the account profile row (single-user install, id = 1).
 * Creates the table and a starter row on first use so existing databases keep working.
 *
 * @return array
 */
function get_profile(): array
{
    static $profile = null;
    if ($profile !== null) {
        return $profile;
    }

    $fallback = [
        'id'         => 1,
        'full_name'  => 'Account Holder',
        'email'      => '',
        'phone'      => '',
        'occupation' => '',
        'city'       => '',
        'created_at' => date('Y-m-d H:i:s'),
    ];

    try {
        $pdo = getDBConnection();

        try {
            $row = $pdo->query('SELECT * FROM profile WHERE id = 1')->fetch();
        } catch (PDOException $e) {
            $pdo->exec('
                CREATE TABLE IF NOT EXISTS `profile` (
                    `id` TINYINT UNSIGNED PRIMARY KEY,
                    `full_name` VARCHAR(80) NOT NULL,
                    `email` VARCHAR(120) NULL,
                    `phone` VARCHAR(20) NULL,
                    `occupation` VARCHAR(80) NULL,
                    `city` VARCHAR(60) NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');
            $row = false;
        }

        if (!$row) {
            $pdo->exec("INSERT IGNORE INTO profile (id, full_name) VALUES (1, 'Account Holder')");
            $row = $pdo->query('SELECT * FROM profile WHERE id = 1')->fetch();
        }

        $profile = $row ?: $fallback;
    } catch (Throwable $e) {
        $profile = $fallback;
    }

    return $profile;
}

/**
 * Up to two uppercase initials from a display name.
 */
function profile_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $initials .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $initials !== '' ? $initials : '?';
}
