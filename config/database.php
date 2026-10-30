<?php
/**
 * Ledger
 * Database configuration and PDO connection
 * 
 * PHP 8+ with MySQL/MariaDB.
 */

declare(strict_types=1);

// Connection settings
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'ledger_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a shared PDO instance configured with strict security and error handling.
 * 
 * @return PDO
 */
function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            renderDatabaseErrorScreen($e->getMessage());
            exit(1);
        }
    }

    return $pdo;
}

/**
 * Shown when the database cannot be reached.
 */
function renderDatabaseErrorScreen(string $errorMessage): void
{
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Connection Required — Ledger</title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                background-color: #0b0f19;
                color: #e2e8f0;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 1.5rem;
            }
            .card {
                background: #111827;
                border: 1px solid #1f2937;
                border-radius: 8px;
                max-width: 620px;
                width: 100%;
                padding: 2.25rem;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
            }
            .brand {
                font-size: 0.8125rem;
                letter-spacing: 0.1em;
                text-transform: uppercase;
                color: #94a3b8;
                margin-bottom: 0.75rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            .brand-badge {
                display: inline-block;
                width: 8px;
                height: 8px;
                background: #ef4444;
                border-radius: 50%;
            }
            h1 {
                font-size: 1.375rem;
                font-weight: 600;
                color: #f8fafc;
                margin-bottom: 1rem;
            }
            p {
                color: #94a3b8;
                font-size: 0.9375rem;
                line-height: 1.6;
                margin-bottom: 1.25rem;
            }
            .error-box {
                background: #1e1b2e;
                border: 1px solid #3730a3;
                border-left: 4px solid #ef4444;
                color: #fca5a5;
                font-family: monospace;
                font-size: 0.8125rem;
                padding: 0.875rem 1rem;
                border-radius: 4px;
                margin-bottom: 1.5rem;
                word-break: break-all;
            }
            .steps {
                background: #0f172a;
                border: 1px solid #1e293b;
                border-radius: 6px;
                padding: 1.25rem;
                margin-bottom: 1.5rem;
            }
            .steps h2 {
                font-size: 0.875rem;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #38bdf8;
                margin-bottom: 0.75rem;
            }
            .steps ol {
                padding-left: 1.25rem;
                color: #cbd5e1;
                font-size: 0.875rem;
                line-height: 1.7;
            }
            .steps code {
                background: #1e293b;
                color: #f1f5f9;
                padding: 0.15rem 0.4rem;
                border-radius: 3px;
                font-size: 0.8125rem;
            }
            .actions { display: flex; gap: 0.75rem; }
            .btn {
                background: #0284c7;
                color: #ffffff;
                text-decoration: none;
                font-size: 0.875rem;
                font-weight: 500;
                padding: 0.625rem 1.25rem;
                border-radius: 4px;
                display: inline-block;
                border: none;
                cursor: pointer;
            }
            .btn:hover { background: #0369a1; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="brand">
                <span class="brand-badge"></span> LEDGER &bull; DATABASE NOTICE
            </div>
            <h1>Unable to Connect to MySQL Database</h1>
            <p>The application could not establish a connection using PDO. Please make sure MySQL is started in XAMPP and the <code>ledger_db</code> database has been imported.</p>
            
            <div class="error-box">
                PDO Error: <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>

            <div class="steps">
                <h2>Quick 2-Step Setup for XAMPP:</h2>
                <ol>
                    <li>Open <strong>XAMPP Control Panel</strong> and click <strong>Start</strong> next to <strong>MySQL</strong> and <strong>Apache</strong>.</li>
                    <li>Open <strong>phpMyAdmin</strong> (<a href="http://localhost/phpmyadmin" target="_blank" style="color: #38bdf8;">http://localhost/phpmyadmin</a>), click <strong>Import</strong>, and select the <code>database.sql</code> file included in this project.</li>
                </ol>
            </div>

            <div class="actions">
                <button onclick="window.location.reload();" class="btn">Retry Connection</button>
            </div>
        </div>
    </body>
    </html>
    <?php
}
