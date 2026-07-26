# DRMS Rollback Procedure

Rollback is a controlled recovery action, not an automatic database downgrade.

## 1. Trigger

Rollback when a deployment introduces a critical security failure, authorization leak, data corruption, failed migration, broken routing/claim workflow, unavailable application, or another release-blocking regression.

## 2. Immediate Control

1. Stop further deployment activity.
2. Place DRMS in maintenance mode when continued writes could increase impact.
3. Stop queue workers if queued work is unsafe under the failed release.
4. Record the release revision, time, operator, symptoms, affected users/data, and logs.
5. Preserve the failed release and current database before recovery.

## 3. Choose the Recovery Path

- **Code-only rollback:** Deploy the preceding approved Git revision when the database remains backward compatible.
- **Forward correction:** Prefer a tested corrective migration when reversing a migration would lose or reinterpret data.
- **Migration rollback:** Use only when the specific migration is proven reversible and no later data depends on it.
- **Database restore:** Use the pre-deployment backup only with data-owner authorization and a declared recovery point.

Never run a broad reset, fresh migration, or destructive schema command against production.

## 4. Execute and Validate

1. Restore the previous approved code or execute the approved corrective change.
2. Restore the database only when required under [BACKUP_RESTORE.md](BACKUP_RESTORE.md).
3. Rebuild caches with `php artisan optimize`.
4. Restart PHP and run `php artisan queue:restart`; then resume the monitored queue worker and scheduler.
5. Verify HTTPS, login/inactive-user denial, both authorization levels, organizational-unit isolation, registration, routing, receiving-box placement/claim, audit visibility, reports, exports, and notifications.
6. Verify migration state and reconstruct the affected custody history.
7. Exit maintenance mode only after the incident owner approves service restoration.

## 5. Closeout

Record impact, root cause, recovery revision/database point, lost or replayed work, validation evidence, remaining risk, and follow-up tests. A rollback does not erase the requirement to fix the defect and add regression coverage.
