# DRMS GitHub Codespaces Demo Runbook

## Purpose and Boundary

This runbook operates a temporary, operator-attended DRMS demonstration. It is not a pilot or production deployment.

Use only fabricated users and records. Never copy the local development database, production-like data, local credentials, or previously generated QR tokens into the Codespace.

## Provisioned Architecture

- Private GitHub repository and a dedicated demo branch.
- A 2-core GitHub Codespace running PHP 8.4 and Node.js 24.
- A private MySQL 8.4 container with a persistent Codespace volume.
- A database queue worker for ready-for-pickup notifications.
- Public forwarding for Laravel port 8000 only.
- Debug and real email delivery disabled.

## Required Codespaces Secrets

Configure these repository Codespaces secrets before creating the Codespace:

- `DRMS_DEMO_PASSWORD`
- `DRMS_DB_PASSWORD`
- `DRMS_DB_ROOT_PASSWORD`

Never place their values in source control, container configuration, screenshots, logs, chat messages, or demonstration records.

## One-Time Provisioning

1. Create the secrets for repository `Kaiserworx/DRMS`.
2. Create a 2-core Codespace from the approved demo branch and `.devcontainer/devcontainer.json`.
3. Wait for `setup-demo.sh` to install locked dependencies, build assets, migrate and seed MySQL, and run the complete validation suite.
4. Confirm `start-demo.sh` starts the Laravel server and queue worker.
5. Change port 8000 visibility from private to public.
6. Run `.devcontainer/smoke-demo.sh`.
7. Open the public HTTPS URL and verify the DRMS login page.

The seeded fabricated accounts are:

- Level 2 username: `admin`
- Level 1 username: `encoder`

Both use the temporary `DRMS_DEMO_PASSWORD` secret.

## Before Every Demonstration

1. Start the existing Codespace at least 20 minutes before the session.
2. Confirm port 8000 is forwarded publicly; visibility returns to private after a restart.
3. Run `.devcontainer/start-demo.sh`.
4. Run `.devcontainer/smoke-demo.sh`.
5. Verify Level 1 unit isolation, Level 2 administration, routing, receiving-box placement, ready-for-pickup notification, and QR inventory access.
6. Generate or display QR output only from the active Codespaces hostname.
7. Share only the public application URL and temporary demo credentials with intended attendees.

## Reset Between Client Groups

Run the following only inside the disposable Codespaces demo:

```bash
php artisan migrate:fresh --seed --force
php artisan optimize
```

This intentionally deletes all Codespaces demo data and recreates only fabricated seed data. Never run it against pilot or production data.

## Stop and Secure the Demo

1. Return port 8000 to private visibility.
2. Stop the Codespace to stop compute usage.
3. Rotate the demo password if it was shared beyond the intended audience.
4. Delete the Codespace after the demonstration campaign ends.
5. Review Codespaces compute and storage usage.

The public URL is not an authorization secret. Anyone who learns it can reach the login page while the Codespace and public port are active.

## Known Availability Limits

- The Codespace stops after its configured idle timeout.
- Port 8000 returns to private visibility after a restart.
- The demo URL exists only while the Codespace is running and the port is forwarded.
- The operator must keep the Codespace active during scheduled demonstrations.
- This profile must not be described as an always-on pilot or production deployment.
