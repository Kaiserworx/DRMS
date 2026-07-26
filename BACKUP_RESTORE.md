# DRMS Backup and Restore Procedure

Backups must be encrypted, access-controlled, stored independently from the application server, and tested by restore. A successful upload alone is not recovery evidence.

## 1. Scope and Safety

- Back up MySQL with transaction-consistent options and include triggers.
- Back up the approved environment configuration and required application storage separately without placing secrets in Git.
- Keep the application database user unable to delete independent backups.
- Never pass a database password directly on the command line or place it in shell history.
- Never restore over production without the authorized data owner, an incident record, and a confirmed rollback point.

The initial retention target in `DEPLOYMENT_PLAN.md` is 14 daily, 8 weekly, 12 monthly, plus a pre-deployment backup before every migration.

## 2. Production Backup

Use an operator-owned MySQL option file with restrictive filesystem permissions:

```ini
[client]
host=127.0.0.1
port=3306
user=drms_backup
password=REDACTED
```

Then create a consistent dump:

```bash
mysqldump --defaults-extra-file=/secure/path/drms-backup.cnf \
  --single-transaction --routines --triggers --no-tablespaces \
  --set-gtid-purged=OFF drms > drms-YYYYMMDD-HHMMSS.sql
sha256sum drms-YYYYMMDD-HHMMSS.sql
```

Encrypt the dump before independent upload. Record the backup timestamp, database, application revision, migration level, byte size, SHA-256 checksum, encryption method, storage object, retention class, and operator. Do not record the encryption key in the same system.

## 3. Restore

1. Declare the recovery point and stop application writes.
2. Preserve the damaged database and logs for investigation.
3. Provision an empty isolated MySQL 8.4 restore database.
4. Download, decrypt, and verify the recorded SHA-256 checksum.
5. Restore using an option file, not an inline password:

   ```bash
   mysql --defaults-extra-file=/secure/path/drms-restore.cnf \
     drms_restore < drms-YYYYMMDD-HHMMSS.sql
   ```

6. Point an isolated DRMS instance at the restored database.
7. Run `php artisan migrate:status`, `php artisan about`, and the approved smoke and authorization checks.
8. Verify users, deployment settings, organizational hierarchy, documents, recipients, document transactions, receiving boxes, token audits, activity audits, notifications, jobs, and migration history.
9. Reconstruct at least one multi-recipient document from registration through routing, placement, and claim.
10. Obtain authorization before promoting the restored database.
11. Record actual recovery time, recovery point, integrity results, exceptions, and approval.

## 4. Repository Rehearsal

The repository includes a guarded Windows rehearsal for the dedicated `drms_test` database:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass `
  -File .\scripts\rehearse-backup-restore.ps1 `
  -PrepareCleanDatabase `
  -ConfirmDestructiveRestore
```

The script refuses non-test targets, reads the restricted local credential without printing it, creates a transaction-consistent dump in a validated temporary directory, removes a known marker, restores the dump, verifies the marker, and deletes the temporary dump. It intentionally replaces only `drms_test`; never adapt its safety check to a production database.

## 5. Failure Handling

If backup, checksum, encryption, upload, restore, migration status, or integrity validation fails, mark the backup unusable, preserve error evidence without secrets, correct the root cause, and repeat the rehearsal. Do not approve pilot readiness based on an unverified backup.
