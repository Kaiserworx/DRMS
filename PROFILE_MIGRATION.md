# DRMS Deployment-Profile and Hierarchy Migration

Deployment-profile changes alter permitted organizational-unit types and labels. They do not rewrite historical transactions, receiving-box ownership, users, or tracking numbers.

## 1. Supported Rules

The application currently enforces:

| Profile | Allowed organizational-unit types | Allowed parent relationship |
|---|---|---|
| District | School | None |
| Division | District, School, Section, Functional Unit | District → School |
| Regional | Division, Section, Functional Unit | None |

The application validates every existing unit before saving a profile change. Tracking prefix and managing-office code become locked after the first document is registered.

## 2. Pre-Migration

1. Approve the target profile, managing-office labels, and authoritative hierarchy.
2. Export the current settings, units, parent relationships, users, receiving boxes, and active documents for review.
3. Identify every unit type or parent link incompatible with the target profile.
4. Decide the correct target unit and user assignment for each affected real-world unit. Do not guess or silently merge units.
5. Complete and verify a backup under [BACKUP_RESTORE.md](BACKUP_RESTORE.md).
6. Rehearse the exact change with copied non-production data.

## 3. Migration

1. Pause administration and record intake.
2. Correct unit types and parent links in dependency order using Level 2 administration.
3. Reassign Level 1 users only after the destination unit is approved.
4. Confirm each receiving box remains owned by the intended active unit. Regenerate tokens only when a security or ownership change requires it.
5. Open **Deployment Settings**, choose the target profile, and save the approved labels.
6. Do not change tracking prefix or managing-office code after documents exist.

If the save is rejected, use the validation message to locate incompatible hierarchy data. Do not bypass the validator.

## 4. Validation

- Every active Level 1 user has exactly one valid active organizational unit.
- Unit types and parents match the table above; no cycles exist.
- Each active unit has at most one receiving box.
- Level 1 isolation and Level 2 visibility still pass.
- Existing tracking numbers, documents, recipients, transactions, claims, token audits, and activity audits are unchanged.
- Search, dashboards, reports, and exports use the new approved labels and scopes.
- Final-domain QR labels still resolve to the correct unit and do not authorize by possession.

Record the before/after profile, approved mapping, changed units/users, validation evidence, and rollback decision.
