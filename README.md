# District Records Management System (DRMS)

## Operational documentation

- [Deployment checklist](DEPLOYMENT_CHECKLIST.md)
- [Backup and restore](BACKUP_RESTORE.md)
- [Rollback](ROLLBACK.md)
- [Deployment-profile and hierarchy migration](PROFILE_MIGRATION.md)
- [Pilot user guide](PILOT_USER_GUIDE.md)
- [Administrator guide](ADMINISTRATOR_GUIDE.md)
- [Deployment and cost plan](DEPLOYMENT_PLAN.md)

DRMS is currently in **Phase 11 — Audit Hardening, Security Review, and Operational Readiness**. Phases 0–10 are approved. Phase 12 UAT has not started.

The governing files are `AGENTS.md`, `CONSTRAINTS.md`, `PRD.md`, `PLAN.md`, and `TASKS.md`.

## Verified Foundation

| Component | Verified version or state |
|---|---|
| PHP | 8.4.23, x64 |
| Composer | 2.10.2 |
| Laravel application | Laravel 12.64.0 from `laravel/laravel` 12.12.2 |
| Node.js | 24.18.0 LTS |
| npm | 11.18.0 |
| Database | Oracle MySQL 8.4.9 LTS |
| Test framework | PHPUnit 11.5 |
| Formatter | Laravel Pint |
| Frontend build | Vite 7 |
| Git | Repository initialized on `main` |
| Authentication | Filament panel login by email or username; inactive users are denied |
| Authorization | Laravel policies with exactly Level 1 and Level 2 |
| FilamentPHP | 5.7.3 |
| Livewire | 4.3.3 |
| Static analysis | Not selected or configured in Phase 0 |

## Local Prerequisites

- Open a new terminal after installation so the machine `PATH` resolves PHP 8.4 before the preserved XAMPP PHP installation.
- Verify `php --version`, `composer --version`, `node --version`, and `npm --version` before setup.
- The dedicated MySQL Windows service is `DRMSMySQL84`. It listens only on `127.0.0.1:3307`.
- The local development database is `drms`; the clean migration-test database is `drms_test`.
- Application and root database passwords are local secrets. They must never be placed in tracked files.

## Installation

From the project root:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Set `DB_PASSWORD` in `.env` to the password provisioned for the restricted local `drms` database user. Then run:

```powershell
npm install
php artisan migrate --seed
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000/admin`.

The normal seed creates the default deployment settings, one example organizational unit, and the 11 required example document types. To create local demonstration users, set a strong, non-production `DRMS_DEMO_PASSWORD` in the untracked `.env` before running the seed. This optionally creates:

- `admin` / `admin@drms.local` as Level 2.
- `encoder` / `encoder@drms.local` as Level 1.

No default password is committed. Demo users are never seeded in production.

## Notification Delivery

In-system notifications are stored and delivered through Laravel's configured queue. Run a queue worker in every environment that requires notification delivery:

```powershell
php artisan queue:work --queue=notifications,default
```

Level 1 receives a notification only when Level 2 places that unit's recipient record in its receiving box and makes it ready for pickup. Read notifications remain in the list until the owning user explicitly deletes them.

Email is disabled by default because production email infrastructure is not yet approved. Enable it only after the configured mailer has been tested:

```dotenv
DRMS_NOTIFICATION_EMAIL_ENABLED=true
```

## Test Database

The dedicated MySQL database `drms_test` is separate from `drms`. To validate migrations against MySQL:

```powershell
Copy-Item .env.example .env.testing
```

In the untracked `.env.testing`, set:

```dotenv
APP_ENV=testing
DB_DATABASE=drms_test
DB_PASSWORD=your-local-drms-user-password
CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
```

Then run:

```powershell
php artisan migrate:fresh --env=testing
```

`phpunit.xml` retains Laravel's scaffolded in-memory SQLite configuration for fast baseline unit and feature tests. MySQL migration compatibility is checked separately against `drms_test`; SQLite is not used for application data.

## Validation Commands

```powershell
php artisan about
php artisan migrate:fresh --env=testing
php artisan test
php vendor/bin/pint --test
composer audit
npm install
npm audit
npm run build
```

## Phase 9 Search Capacity Assumption

The Phase 9 indexes and bounded-query checks target the initial operational deployment at up to approximately 100,000 documents, five recipient rows per document, and fifteen transaction rows per document. This is a documented planning assumption, not a product limit. Query plans and index selectivity must be rechecked with production-like data before a larger rollout; full-text or external search infrastructure is not part of the current MVP.

## Phase 10 Dashboard and Time-Metric Definitions

Dashboard and report values are calculated from the same server-side document visibility scope used by search and direct access.

- Document-state cards count distinct authorized documents in the named current status.
- `Ready for Pickup`, `Claimed today`, `In receiving boxes`, and `Pending confirmation` are derived from authorized recipient rows. The first three document-oriented cards count distinct documents; `Pending confirmation` counts recipient-unit confirmations still outstanding.
- `Overdue or unclaimed` counts overdue, non-completed, non-cancelled documents plus ready recipient items whose document is not already counted as overdue.
- `Average processing time` is the elapsed time from the first unit-submission transaction to the transaction that completes the document.
- `Average pickup time` is the elapsed time from recipient placement in a receiving box to confirmed recipient claim.
- Incomplete intervals are excluded from averages, and the report displays the completed observation count.

On-screen reports are paginated at 25 rows. CSV exports reuse the same validated filters and authorization scope, stream rows in bounded batches, exclude internal identifiers, and prefix spreadsheet-formula payloads with an apostrophe.

## Phase 2 Architecture and Naming Decisions

- **Testing:** Use the repository's configured PHPUnit test framework. Add feature tests for authorization and workflows and unit tests for isolated domain rules.
- **Authorization:** Use Laravel policies and gates with server-side organizational-unit scoping. UI visibility is never the authorization boundary. No third-party roles package is selected.
- **Controlled values:** Use string-backed PHP enums for closed domain values where appropriate. Store stable, deployment-neutral values rather than display labels.
- **Reference names:** Preserve a whitespace-normalized display name and a lowercase normalized key. Document-type names are unique; document-origin names are unique within an origin type.
- **Reference lifecycle:** New-record selectors use active records only. Inactive records remain readable, and reference records cannot be hard-deleted through application authorization.
- **Workflow reservation:** `default_workflow` is stored but remains read-only and null until the approved routing phase defines controlled workflows.
- **Actions and services:** Put a single application use case in an `app/Actions` class. Put a shared multi-step domain workflow in an `app/Services` class. Controllers, Livewire components, and Filament actions remain thin callers.
- **Audit history:** Persist required custody and claim events as append-only domain transaction records created atomically with state changes. Framework logs do not substitute for the permanent transaction history. No audit package is selected in Phase 0.
- **Database naming:** Use plural `snake_case` table names, `snake_case` columns, and conventional singular `<model>_id` foreign keys. Names must use generic organizational-unit and managing-office terminology rather than deployment-specific hierarchy labels.
- **Dependencies:** Add packages only when required by the current approved phase and after compatibility and security review.

### Phase 2 Controlled Values

- **Document statuses:** Draft, Submitted, Received at Managing Office, Forwarded to Upstream Office, Received at Upstream Office, Returned from Upstream Office, For Distribution, Ready for Pickup, Partially Claimed, Completed, and Cancelled.
- **Recipient statuses:** Assigned, Ready for Pickup, and Received by Recipient Unit.
- **Physical locations:** Organizational Unit, Managing Office, Upstream Office, Receiving Box, and Recipient Unit.
- **Priorities:** Normal and Urgent.
- **Origin types:** Organizational Unit, Managing Office, Upstream Office, and External Organization.
- **Operational status:** Active and Inactive.

Do not begin Phase 11 until the Phase 10 gate is reviewed and approved in `TASKS.md`.
