# DRMS Administrator Guide

This guide covers application administration. Server, database, DNS, TLS, backup, and incident operations require the separately named technical owner.

## 1. Daily Review

- Confirm HTTPS availability, MySQL health, queue worker, scheduler, failed jobs, disk space, and latest backup status.
- Review application errors without copying passwords, tokens, private configuration, or unnecessary personal data.
- Review **Activity Audit** for unusual logins, administration changes, token regeneration, and report/export use.
- Review affected documents' permanent transactions when investigating custody or claims.

## 2. Accounts and Authorization

- Create named users; do not use shared administrator accounts.
- Give Level 1 users one approved active organizational unit.
- Reserve Level 2 for records administrators who require managing-office-wide control.
- Use strong passwords and deactivate access promptly when no longer authorized.
- Level 2 may delete a Level 1 account. The account is removed from access and normal administration lists, while its historical document, custody, claim, token-audit, and activity-audit references remain intact.
- Level 2 accounts cannot be deleted through DRMS.
- Delete an organizational unit only when DRMS offers the action. Units with child units, users, documents, recipient assignments, or receiving boxes remain protected from deletion.
- Never delete an account, unit, or transaction history to conceal past activity.
- Verify authorization on the server by testing direct access, not only by checking whether a menu item is hidden.

## 3. Configuration and Reference Data

- Maintain deployment settings, units, document types, and origins through Level 2 pages.
- Treat tracking prefix and managing-office code as permanent after document registration begins.
- Deactivate obsolete reference records instead of deleting them.
- Follow [PROFILE_MIGRATION.md](PROFILE_MIGRATION.md) for profile or hierarchy changes.

## 4. Documents, Routing, and Corrections

- Confirm document identity and recipient assignments before routing.
- Use the central workflow actions shown for the current state.
- Do not directly edit status or location.
- Review transaction history before a cancellation or correction.
- A recipient with downstream activity cannot be removed; use an auditable correction.
- Investigate duplicate, invalid-transition, or concurrency messages instead of bypassing validation.

## 5. Receiving Boxes and QR Labels

- Maintain at most one active box per active unit.
- Confirm the physical location and unit before printing a label.
- Generate permanent labels only from the final HTTPS domain.
- Regenerate a token when a label/token is exposed or ownership/security requires replacement.
- Destroy obsolete printed labels; verify the old URL fails and the new label works.
- Never treat token possession as authentication.

## 6. Queues, Scheduler, and Notifications

Run a monitored database queue worker and the scheduler. Restart queue workers after deployment. Level 1 notifications are queued only when Level 2 places a recipient record in its receiving box and makes it ready for pickup. Each notification shows the tracking number, document type, and subject. Email stays off until delivery is approved and tested.

On the approved local Windows runtime, run `scripts\run-notification-worker.ps1` through the VS Code queue-worker task. Use `php artisan drms:refresh-local --force` for an explicitly approved destructive local reset. The refresh command pauses the supervised worker while queue/cache tables are recreated, seeds only the local Level 2 administrator, and allows notification processing to resume automatically afterward.

Review failed jobs, correct the root cause, and retry only after confirming that idempotency protections prevent duplicate notifications.

## 7. Reports, Exports, and Audit Review

- Reports and exports are authorization-scoped and date/filter validated.
- CSV values are neutralized against spreadsheet formulas, but the export remains sensitive.
- The Activity Audit is append-only and Level 2-only. It records significant login, configuration, user/unit, reference, document, box, and report/export activity.
- Document transactions remain the authoritative custody/routing/claim history; receiving-box token audits remain the token history.
- Ordinary application actions cannot update or delete these histories.

## 8. Security and Incident Response

- Keep production debug off, enforce HTTPS, use secure session settings, and retain the security headers.
- Keep credentials outside source control and logs.
- Claims, QR lookups, login, and report exports are rate limited.
- On suspected data exposure, token compromise, unauthorized access, or corruption: stop the affected activity, preserve evidence, notify the named owner, invalidate affected credentials/tokens, and follow [ROLLBACK.md](ROLLBACK.md) or [BACKUP_RESTORE.md](BACKUP_RESTORE.md) as authorized.

## 9. Release Control

Use [DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md) for every release. Record the revision, operator, backup, migrations, tests, smoke checks, QR validation, and approve/rollback decision.
