# Erty

Erty is property management software for landlords, agencies and property managers. It covers the work from
setting up a property to collecting rent: units and houses, tenancies and leases, rent and payments, agents and
their commission, leads, maintenance, legal documents and house sales.

Erty is built by [Tandish](https://tandish.com) and runs as a Laravel application on PostgreSQL.

## What it does

- **Properties.** Apartment blocks, estates of houses, commercial buildings, or any mix. Unit types, individual
  units and houses (for rent, for sale or both), commercial spaces that can be subdivided, facilities and parking,
  floor plans, service charges and a property history.
- **Tenants and rent.** Tenancy onboarding, leases, notices, moves between units and move-outs. Rent is billed in
  advance. Every tenant has a running account: payments settle the oldest open invoice first and anything extra
  stays as credit. Late fees and charges, deposits and refunds, statements, and matching payments to the receipts
  tenants send.
- **Agents and commission.** Agents assigned to properties or single units, a commission ledger on rent collected
  (and on sales), versioned commission agreements, payout periods and statements.
- **Leads.** A lead moves through qualification, engagement and agreement, with the follow-up tasks each phase
  needs, until it becomes a tenancy.
- **Maintenance, expenses and legal documents.** Repair requests with who handles them and what they cost,
  property expenses, and a document library in folders.
- **Sales.** Selling houses with a deposit and instalments, receipts and sales commission (optional module).
- **Listings.** One listing per unit type, published through a public read-only API at `GET /api/v1/listings`
  (optional module).
- **Reports.** A business overview with every property side by side, and reports on income and expenses,
  the rent roll and arrears, occupancy and vacancy, leases and notices, agents and leads, sales and
  maintenance, for any period and property, on screen or downloaded as Excel or PDF.
- **Team and access.** Starter roles (property manager, finance officer, caretaker, accountant and others),
  custom roles, and team members limited to the properties assigned to them. The role editor follows the left
  menu and explains every right, and an activity log records who opened, changed or downloaded what, and when.
- **Support.** Team members post support tickets from any page and follow the replies under My support
  tickets; the installation's administrators answer them in the support inbox.

## Requirements

- PHP 8.3 or newer, with the `pdo_pgsql`, `mbstring`, `gd`, `zip`, `xml`, `fileinfo` and `openssl` extensions
- PostgreSQL 14 or newer
- Composer 2
- An email account Erty can send from: sign-in uses a code sent by email

## Quick start

```bash
git clone <repository-url> erty && cd erty
composer install
cp .env.example .env          # then set the database, mail and ADMIN_EMAIL
php artisan erty:install      # tables, reference data, storage link and the first administrator
php artisan serve
```

`erty:install` prints the administrator's one-time password. Sign in with it, enter the code emailed to you,
then choose your own password. While developing, set `MAIL_MAILER=log` in `.env` and the codes are written to
`storage/logs` instead of being sent.

See **[docs/INSTALL.md](docs/INSTALL.md)** for a production setup: web server, scheduler, queue, HTTPS,
backups and upgrades.

## Demo data

To explore Erty with a sample business on a fresh local database:

```bash
php artisan migrate:fresh
php artisan db:seed --class=DemoSeeder
```

It creates three properties, listings, agents with commission agreements, leads with tasks, tenancies with rent
paid, a repair, an expense, a house sale on instalments and one team member for each starter role. It prints the
owner's sign-in. The seeder only runs in the `local` and `testing` environments.

## Useful commands

| Command | What it does |
| --- | --- |
| `php artisan erty:install` | Set up a new installation (safe to run again) |
| `php artisan erty:module listings off` | Switch an optional module (`listings`, `sales`) on or off for the installation, or for one business with `--business=` |
| `php artisan erty:seed-roles` | Offer any new starter roles to businesses that do not have them yet |
| `php artisan erty:access-audit` | Sign in as every team member and check each sees exactly what their role allows; add `--links` to also fail on any link or button shown to someone who may not use it |
| `php artisan activity:prune` | Remove activity log entries older than `ACTIVITY_LOG_KEEP_DAYS` (also runs every night) |
| `php artisan erty:test-team` | Create one test team member per role, with property assignments, to try the access rules (local and testing only) |
| `php artisan tenant:process-billing` | Bill rent that has fallen due (also runs every night) |
| `php artisan commissions:calculate` | Work out last month's period commission, waiting for approval (also runs monthly) |
| `php artisan health:check` | Run the health checks: database, disk space, cache and more |

## Development

```bash
php artisan test    # the test suite (SQLite in memory, no setup needed)
```

The code is organised by area. The business routes are in `routes/business/` (one file per area). Controllers
are in `app/Http/Controllers/Business/<Area>` and services in `app/Services/<Area>`. Who may open what is
decided by `config/business_access.php`: every business route must be listed there, and a test fails when one
is missing.

The database schema is in `database/migrations`, a baseline of one migration per area.
