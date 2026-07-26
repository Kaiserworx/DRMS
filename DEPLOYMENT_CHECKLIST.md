# DRMS Deployment Checklist

Use this checklist only after the Phase 11 gate passes. A public pilot or production release also requires Phase 12 UAT approval and separate infrastructure authorization.

## 1. Release Inputs

- [ ] Approved Git revision and release owner recorded.
- [ ] Target profile, managing-office identity, hierarchy, domain, timezone, and named administrators approved.
- [ ] PHP 8.4, Composer 2, Node.js 24 LTS, npm, MySQL 8.4 LTS, Nginx, a process monitor, and cron available.
- [ ] MySQL listens only on localhost or an approved private interface.
- [ ] Production database, restricted application user, backup account, and application key are unique to production.
- [ ] Demo users, demo records, local credentials, development QR tokens, and development labels will not be copied.
- [ ] Pre-deployment database backup completed and its checksum recorded.

## 2. Required Production Configuration

Store secrets outside Git. At minimum configure:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://records.example.gov.ph
LOG_CHANNEL=daily
LOG_LEVEL=warning
DB_CONNECTION=mysql
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=database
MAIL_MAILER=log
DRMS_NOTIFICATION_EMAIL_ENABLED=false
```

Email remains disabled until delivery is separately approved and tested. Level 1 in-system notifications are generated only at ready-for-pickup placement. Use the final HTTPS domain before generating receiving-box QR labels.

## 3. Deployment

- [ ] Place the application in maintenance mode when the change requires it.
- [ ] Deploy the approved Git revision.
- [ ] Run `composer install --no-dev --classmap-authoritative --no-interaction`.
- [ ] Run `npm ci` and `npm run build` in the controlled build environment.
- [ ] Run `php artisan migrate --force` only after the backup completes.
- [ ] Run `php artisan optimize`.
- [ ] Ensure `storage` and `bootstrap/cache` are writable only as required by the web service account.
- [ ] Configure a persistent `php artisan queue:work --tries=3` process.
- [ ] Configure cron to run `php artisan schedule:run` every minute.
- [ ] Run `php artisan queue:restart` after each release.
- [ ] Return the application from maintenance mode.

## 4. Security and Functional Smoke Tests

- [ ] HTTPS is valid and HTTP redirects to HTTPS at the web server.
- [ ] `APP_DEBUG` is false and an error page does not expose a stack trace.
- [ ] Secure, HttpOnly, SameSite session cookies are present.
- [ ] Security headers include `nosniff`, `SAMEORIGIN`, the referrer policy, permissions policy, and HSTS over HTTPS.
- [ ] Inactive users cannot log in.
- [ ] Level 1 users see only records related to their organizational unit.
- [ ] Level 2 administration, document routing, audit review, reports, and exports work.
- [ ] Claim and export throttles return HTTP 429 after their configured limits.
- [ ] Queue jobs complete and failed-job monitoring is active.
- [ ] The scheduler runs for any approved scheduled application tasks.
- [ ] A final-domain QR label scans on a mobile device; authentication and unit ownership are still required.
- [ ] An invalid or regenerated old QR token fails safely.
- [ ] A backup-monitor alert path and named incident contact are active.

## 5. Release Evidence

Record the release revision, operator, timestamps, migration result, backup identifier and checksum, smoke-test results, queue/scheduler status, QR evidence, known issues, and the approve/rollback decision. Follow [ROLLBACK.md](ROLLBACK.md) if any critical check fails.
