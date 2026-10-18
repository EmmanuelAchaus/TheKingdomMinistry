# The Kingdom Ministry and Kingdom Sons SHG Portal

The public ministry website and the Kingdom Sons Self Help Group management portal share this PHP/MySQL project. The chama portal provides member registration and approval, monthly contributions, offline payment claims, configurable loans, repayments, opening entries, notifications, and an audit trail.

## Requirements

- PHP 8.1+ with PDO MySQL and OpenSSL
- MySQL 8+ or MariaDB 10.6+
- Node.js 18+ only when rebuilding Tailwind assets

## Setup

1. Copy `.env.example` values into your web server environment. Generate a long random `APP_ENCRYPTION_KEY`; changing it later makes protected identity fields unreadable.
2. Create the database and import `admin/includes/schema.sql` for a new installation. Existing installations should keep their current content tables.
3. Run `php scripts/migrate.php` to apply the additive chama migration.
4. Run `php scripts/seed_admin.php` and enter the initial administrator's name, phone, and temporary password. The account is also created as an active member and must change its password after sign-in.
5. Point the web server document root at this repository. Set `APP_BASE_PATH` to the URL path containing the project, or leave it empty when hosted at the domain root.

Visit `/portal/register.php` for member applications, `/portal/login.php` for sign-in, and `/admin/chama/` for chama administration.

## Operational sequence

After the first administrator signs in:

1. Configure the monthly contribution amount, due day, and effective month.
2. Configure and enable the loan product if the group is accepting applications.
3. Add existing members and opening balances under **Opening entries**, or approve new membership requests.
4. Review member-submitted payment claims before they affect balances.

All corrections to posted payments are reversals. Do not delete financial rows manually.

## Tests

Run:

```bash
php tests/finance_test.php
find . -name '*.php' -not -path './node_modules/*' -print0 | xargs -0 -n1 php -l
```

For database integration coverage, create a disposable database whose name contains `test`, run the migration against it, then execute `php tests/integration_test.php` with `DB_NAME` pointing to that database. The test clears chama tables in that test database before running.

The first release intentionally leaves payment APIs, receipt uploads, guarantors, scheduled reminders, full bookkeeping, and PDF reports for a later phase.
