# Ledger

Personal budget and expense tracker. Record income and expenses, set monthly category budgets, and see where money goes. Built with PHP 8+, MySQL (PDO), HTML, CSS and vanilla JavaScript. Amounts are in Indian Rupees with lakh/crore grouping (e.g. `₹1,50,000.00`).

## Features

- **Dashboard**: monthly income, expenses, net cash flow and savings rate; 6-month income vs expense chart; expense breakdown by category; over-budget alerts; recent transactions.
- **Transactions**: add, edit, delete. Filter by keyword, type, category, month or date range. Export the filtered view to CSV.
- **Budgets**: monthly spending limit per expense category with progress tracking. Copy limits from the previous month.
- **Categories**: default Indian income and expense categories, plus custom ones. Categories that have transactions cannot be deleted.
- **Profile**: name, email, phone, occupation and city, with a summary of account activity.

## Setup

1. Place the `ledger` folder in your web root (for XAMPP: `C:\xampp\htdocs\ledger`).
2. Start Apache and MySQL.
3. Import `database.sql` in phpMyAdmin (this creates the `ledger_db` database).
4. Open `http://localhost/ledger/`.

Without Apache you can run `php -S localhost:8000` inside the folder instead.

Database credentials are in `config/database.php`. If you already have data, do not re-import `database.sql` (it recreates the tables). The profile table is created automatically on first visit.

## Database

| Table | Purpose |
|---|---|
| `categories` | name, type (income/expense), colour |
| `transactions` | type, category, amount `DECIMAL(12,2)`, date, note |
| `budgets` | category, month, year, amount (unique per category per month) |
| `profile` | account holder details (single row) |

InnoDB with foreign keys. Deleting a category that has transactions is blocked (`ON DELETE RESTRICT`).

## Implementation notes

- All queries use PDO prepared statements with emulation turned off.
- Output is escaped with `htmlspecialchars` through the `e()` helper.
- State-changing requests are POST-only and protected by a session CSRF token.
- `format_currency()` in `includes/functions.php` handles Indian digit grouping without the `intl` extension.
- CSV export includes a UTF-8 BOM so Excel opens it correctly.
