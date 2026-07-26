# GitHub Live Demo Hosting Research

**Prepared:** 2026-07-26
**Repository:** `https://github.com/Kaiserworx/DRMS.git`
**Local branch inspected:** `main`
**Status:** Codespaces implementation in progress on the approved demo branch

## 1. Executive Finding

GitHub Pages cannot host the working DRMS application. It serves static HTML, CSS, and JavaScript, while DRMS requires PHP 8.4, Laravel 12, MySQL 8.4, authentication, database sessions, queues, server-side authorization, and dynamic QR inventory routes.

The only practical GitHub-native option for a temporary, fully interactive client demonstration is:

> **GitHub Codespaces + MySQL 8.4 service + a public forwarded Laravel port**

This can give clients an HTTPS `app.github.dev` URL and the real DRMS login, registration, routing, receiving-box, QR, and claim workflows. It is appropriate for scheduled demonstrations with fabricated data. It is not suitable for 24/7 pilot or production hosting because Codespaces stops after inactivity, public port visibility resets after restart, and usage becomes expensive when kept running for long periods.

## 2. What Each GitHub Product Can Do

| GitHub product | Can run full DRMS? | Role |
|---|---|---|
| GitHub repository | No | Stores and versions the source code |
| GitHub Pages | No | Static presentation site only |
| GitHub Actions | No | Tests, builds, audits, and deploys elsewhere |
| GitHub Container Registry | No | Stores container images but does not execute them |
| GitHub Codespaces | Yes, temporarily | Runs Laravel, PHP, MySQL, queue worker, and a publicly forwarded demo port |

GitHub describes Pages as a static hosting service that publishes HTML, CSS, and JavaScript from a repository. It cannot execute Laravel or MySQL.

Source: [What is GitHub Pages?](https://docs.github.com/en/pages/getting-started-with-github-pages/what-is-github-pages)

GitHub-hosted Actions runners are temporary job environments. A hosted job has a six-hour execution limit and each job starts in a fresh runner environment. Actions should validate or package DRMS, not act as its web server.

Sources:

- [GitHub Actions limits](https://docs.github.com/en/actions/reference/limits)
- [Choosing a GitHub-hosted runner](https://docs.github.com/en/actions/how-tos/write-workflows/choose-where-workflows-run/choose-the-runner-for-a-job)

## 3. Recommended GitHub Codespaces Demo

### 3.1 Proposed Architecture

```text
Client browser or mobile phone
        |
        | HTTPS
        v
https://<codespace>-8000.app.github.dev
        |
        | GitHub public forwarded port 8000
        v
Laravel 12 / PHP 8.4 application container
        |
        +---- MySQL 8.4 container on a private container network
        |
        +---- database queue worker
        |
        +---- Laravel scheduler for demo-required tasks
```

Only port 8000 should be forwarded publicly. MySQL port 3306 must remain internal and must never be public.

GitHub documents that a public forwarded port is accessible to anyone who knows its URL without GitHub authentication. The DRMS login and server-side authorization remain the application security boundary.

Source: [Forwarding ports in a codespace](https://docs.github.com/en/codespaces/developing-in-a-codespace/forwarding-ports-in-your-codespace)

### 3.2 Implemented Files

| File | Purpose |
|---|---|
| `.devcontainer/devcontainer.json` | Define the PHP 8.4 development container, forwarded port, lifecycle commands, and Codespaces settings |
| `.devcontainer/compose.yaml` | Run the application container and private MySQL 8.4 service |
| `.devcontainer/Dockerfile` | Install only the required PHP extensions and system packages |
| `.devcontainer/setup-demo.sh` | Install locked dependencies, wait for MySQL, migrate, seed, and build |
| `.env.codespaces.example` | Document non-secret Codespaces demo configuration |
| `CODESPACES_DEMO_RUNBOOK.md` | Start, share, smoke-test, reset, and stop procedures |

The implementation should pin PHP 8.4 and MySQL 8.4 rather than use floating major versions. It should use the existing `composer.lock` and `package-lock.json`.

### 3.3 Demo Environment Configuration

Recommended Codespaces-only settings:

```dotenv
APP_NAME=DRMS
APP_ENV=local
APP_DEBUG=false
APP_URL=https://<codespace-name>-8000.app.github.dev

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=drms_demo
DB_USERNAME=drms_demo
DB_PASSWORD=<Codespaces secret>

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=log
DRMS_NOTIFICATION_EMAIL_ENABLED=false
DRMS_DEMO_PASSWORD=<Codespaces secret>
```

The current seeder creates demonstration users only in the `local` or `testing` environment and only when `DRMS_DEMO_PASSWORD` is provided. Therefore, the Codespaces demo should use `APP_ENV=local` with `APP_DEBUG=false`, not `APP_ENV=production`.

Passwords and the application key must be GitHub Codespaces secrets or generated inside the Codespace. They must not be committed to `.env.codespaces.example`, the repository, container image, workflow output, or application logs.

GitHub supports Codespaces secrets at user, repository, and organization scope.

Source: [Managing Codespaces secrets](https://docs.github.com/en/codespaces/managing-your-codespaces/managing-your-account-specific-secrets-for-github-codespaces)

## 4. Expected User Experience

While the Codespace is running and port 8000 is public, the client receives an HTTPS URL similar to:

```text
https://<codespace-name>-8000.app.github.dev
```

The client can:

- Sign in with a temporary Level 1 or Level 2 demo account
- Register and search fabricated documents
- Route fabricated records through implemented states
- View transaction history
- Use receiving-box administration
- Open a receiving-box inventory from a mobile device
- Scan a QR code generated from the live Codespaces hostname
- Confirm fabricated claims when the approved phase includes that behavior

The Codespaces hostname normally remains tied to that Codespace and forwarded port. If the Codespace is deleted and recreated, the hostname changes. Permanent demonstration QR labels must therefore not be printed. Generate QR labels only from the currently active Codespace URL.

## 5. Availability Limits

### 5.1 Idle Timeout

GitHub Codespaces stops after inactivity:

- Default timeout: 30 minutes
- Configurable range: 5 minutes to 4 hours
- User interaction or terminal activity resets the timer
- Web requests reset the timer only when they generate terminal output

A client opening pages does not guarantee the Codespace will remain active unless the requests produce terminal output. The operator should keep the Codespace open during the scheduled demonstration.

Source: [Setting the Codespaces idle timeout](https://docs.github.com/en/codespaces/setting-your-user-preferences/setting-your-timeout-period-for-github-codespaces)

### 5.2 Restart Behavior

When the Codespace restarts:

- The application and MySQL services must be started or automatically restored.
- The public forwarded port reverts to private and must be made public again.
- A new Codespaces session token is generated.
- Application data stored in the Codespace volume remains until the Codespace is deleted.

Source: [Security in GitHub Codespaces](https://docs.github.com/en/codespaces/reference/security-in-github-codespaces)

### 5.3 Retention

Stopped Codespaces are deleted after their retention period:

- Default stopped retention: 30 days
- Configurable range: 0 to 30 days
- Storage usage continues while a stopped Codespace is retained

Source: [Codespaces automatic deletion](https://docs.github.com/en/codespaces/setting-your-user-preferences/configuring-automatic-deletion-of-your-codespaces)

## 6. Cost Analysis

### 6.1 Personal GitHub Account

GitHub currently includes the following monthly Codespaces allowance for personal accounts:

| Plan | Included compute | Included storage |
|---|---:|---:|
| GitHub Free personal | 120 core-hours/month | 15 GB-month/month |
| GitHub Pro personal | 180 core-hours/month | 20 GB-month/month |

A 2-core Codespace consumes two core-hours for every wall-clock hour. The GitHub Free personal allowance therefore supports approximately:

```text
120 core-hours / 2 cores = 60 running hours per month
```

That is enough for scheduled demonstrations, for example:

- 15 demonstrations × 2 hours = 30 running hours/month
- Setup and rehearsal allowance = 20 hours/month
- Remaining buffer = approximately 10 hours/month

Source: [GitHub Codespaces billing](https://docs.github.com/en/billing/concepts/product-billing/github-codespaces)

### 6.2 Overage

Current published Codespaces rates:

| Resource | Price |
|---|---:|
| 2-core Codespace | $0.18 per running hour |
| Storage beyond allowance | $0.07 per GB-month |

Example:

| Usage | Calculation | Estimated compute cost |
|---|---:|---:|
| 50 running hours/month | Within approximately 60-hour personal free allowance | $0 |
| 80 running hours/month | 20 excess hours × $0.18 | $3.60/month |
| 160 running hours/month | 100 excess hours × $0.18 | $18.00/month |
| 24/7 for a 30-day month | Approximately 660 excess hours × $0.18 | Approximately $118.80/month |

The 24/7 example excludes storage and is not operationally recommended. It shows that Codespaces becomes substantially more expensive than a small VPS when misused as continuous hosting.

### 6.3 Organization-Owned Repository Warning

GitHub's included free Codespaces quota applies to personal accounts. GitHub Free organizations, Team, and Enterprise accounts do not receive a personal-style included quota unless the individual user is the billing owner under GitHub's rules.

The local repository remote verifies `Kaiserworx/DRMS`, but authenticated GitHub metadata was unavailable during this research. Before implementation, confirm in GitHub:

1. Whether `Kaiserworx` is the personal billing owner or an organization
2. Whether Codespaces is enabled for the repository
3. Whether public forwarded ports are permitted
4. Which account will be charged
5. The current Codespaces budget and stop-spending setting

If the repository is organization-billed, do not assume the demo is free.

## 7. Security and Data Rules

This is a public internet demonstration endpoint. Apply all of the following:

1. Use fabricated records, names, offices, contact details, and attachments only.
2. Do not import the local development database or any production-like data.
3. Keep the repository private unless the user explicitly approves public source release.
4. Make only the Laravel port public.
5. Never forward MySQL, SSH, Vite, or debugging ports publicly.
6. Keep `APP_DEBUG=false`.
7. Disable real email delivery.
8. Use strong temporary passwords stored as Codespaces secrets.
9. Reset the database before each client group.
10. Stop the Codespace and return the port to private immediately after the demonstration.
11. Remove client-entered data after the session.
12. Delete the Codespace when the demo campaign is complete.

Because a public forwarded port is reachable by anyone who obtains the URL, the URL itself must not be treated as an access-control secret.

## 8. Proposed Start and Stop Runbook

### 8.1 One-Time Setup

1. Confirm repository ownership, Codespaces entitlement, and billing owner.
2. Set a Codespaces spending budget that stops usage at the chosen limit.
3. Add Codespaces secrets for the demo password and database credentials.
4. Add and review the proposed `.devcontainer` files.
5. Add automated tests for the Codespaces setup where practical.
6. Push the reviewed configuration to a dedicated demo branch.
7. Create a 2-core Codespace from that branch.
8. Complete clean MySQL migrations and demo seeding.
9. Run the complete automated suite and frontend build.

### 8.2 Before Each Client Session

1. Start the existing Codespace at least 20 minutes before the meeting.
2. Pull the approved demo revision.
3. Reset and seed the fabricated demo database.
4. Start the Laravel application on `0.0.0.0:8000`.
5. Start one database queue worker.
6. Start the scheduler only if the demonstrated functionality requires it.
7. Forward port 8000.
8. Change port 8000 visibility from private to public.
9. Set `APP_URL` to the forwarded `app.github.dev` URL and rebuild Laravel configuration cache.
10. Run smoke tests for login, authorization, routing, receiving-box inventory, and QR scan.
11. Generate fresh QR demonstration output using the active Codespaces URL.
12. Share only the application URL and temporary DRMS credentials with the client.

### 8.3 After Each Client Session

1. Return port 8000 to private visibility.
2. Stop the Laravel process, worker, and scheduler.
3. Reset or remove client-entered demonstration data.
4. Rotate temporary demo passwords if they were shared outside the intended group.
5. Stop the Codespace to stop compute consumption.
6. Review monthly Codespaces usage and remaining free allowance.

## 9. Validation Required Before Sharing the URL

- Codespace builds reproducibly from the repository
- PHP reports version 8.4
- MySQL reports version 8.4
- Clean migration and seed pass
- Full automated tests pass
- Formatter, Composer audit, npm audit, and production frontend build pass
- Debug mode is disabled
- Only port 8000 is public
- MySQL is not reachable publicly
- Level 1 organizational-unit isolation passes
- Level 2 administration passes
- QR scan works from a separate mobile network
- Email delivery remains disabled
- No real or sensitive data is present

## 10. Final Recommendation

Use GitHub Codespaces for a **scheduled, operator-attended live demo**, not for an always-on public deployment.

It gives the client the closest possible experience to a deployed system while keeping the initial hosting cost at $0 when:

- the Codespace is billed to an eligible personal GitHub account,
- the 2-core Codespace remains within approximately 60 running hours per month,
- storage remains within 15 GB-month,
- and the environment is stopped after every demonstration.

Do not use GitHub Pages, GitHub Actions, or GitHub Container Registry as the application host. Move to the separately budgeted VPS or managed Laravel host when continuous client access or pilot use is approved.
