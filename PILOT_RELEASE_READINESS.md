# Pilot Release Readiness

## 1. Decision

**`NOT READY FOR PILOT RELEASE`**

The Phase 12 technical workflows pass, and no critical or high-severity defect remains open. Release authorization is still blocked because official pilot data, final-domain QR labels, named operational contacts, and acceptance signatures have not been supplied or verified.

This decision does not authorize a public deployment. A public deployment remains subject to separate user approval and the production controls in `CONSTRAINTS.md`.

## 2. Gate Status

| Gate | Status | Evidence or blocker |
|---|---|---|
| 17 technical UAT scenarios | Passed | [PHASE_12_UAT.md](PHASE_12_UAT.md) |
| Critical defects | Passed | Zero open |
| High-severity defects | Passed | UAT-001 resolved and regression-tested |
| Medium-defect disposition | Passed | No medium defect recorded |
| Complete automated regression | Passed | 129 tests, 633 assertions |
| Frontend production build | Passed | Vite build completed |
| Official pilot users | Blocked | Only synthetic local users are verified |
| Official organizational units | Blocked | Data owner has not supplied or approved the pilot roster |
| Pilot receiving boxes and locations | Blocked | Final pilot inventory is not approved |
| Permanent QR labels | Blocked | Final HTTPS domain is not approved or verified; development tokens and labels must not be promoted |
| Named operational contacts | Blocked | Deployment, security, database recovery, DNS, and client-support owners are not named |
| Records-office UAT sign-off | Blocked | Acceptance owner and pilot representative have not signed |
| Host operating-system lifecycle | Risk | Current Windows 10 installation does not prove active ESU coverage |

## 3. Pilot Data Preparation

Do not copy the development database, demo credentials, application key, QR tokens, or printed labels into a pilot environment.

The authorized data owner must provide and approve:

- pilot organizational-unit names, codes, types, hierarchy, status, and physical receiving-box locations;
- one accountable Level 1 user per approved pilot unit, with unique identity and contact data;
- the named Level 2 records administrators;
- approved document types and official origins;
- initial pilot records, if any, with data-handling authorization; and
- the final pilot retention and access rules.

The application currently contains synthetic local evidence such as `Pilot Elementary School`, a local administrator, a local encoder, and development records. These are not approved pilot assets.

## 4. Receiving Boxes and QR Labels

For each approved pilot unit:

1. Confirm the unit and receiving-box location.
2. Create exactly one active receiving box.
3. Verify the final HTTPS application domain.
4. Generate the permanent QR label in the pilot environment.
5. Print and attach the label to the correct box.
6. Scan it on a mobile device while signed in as the correct Level 1 user.
7. Confirm that another unit is denied.
8. Record the label operator, scan evidence, and approval.

Development QR tokens must not be copied or disclosed. If a token or label is exposed, regenerate it and replace the label.

## 5. Required Named Contacts

| Responsibility | Required name and contact | Status |
|---|---|---|
| Release owner | Not provided | Blocking |
| Records-office acceptance owner | Not provided | Blocking |
| Pilot client-support contact | Not provided | Blocking |
| Application operations owner | Not provided | Blocking |
| Security incident owner | Not provided | Blocking |
| Database backup and recovery owner | Not provided | Blocking |
| Domain, DNS, and TLS owner | Not provided | Blocking |

## 6. Support and Rollback Processes

The required processes are documented:

- user incident reporting: [PILOT_USER_GUIDE.md](PILOT_USER_GUIDE.md);
- application administration and incident response: [ADMINISTRATOR_GUIDE.md](ADMINISTRATOR_GUIDE.md);
- deployment controls: [DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md);
- backup and restore: [BACKUP_RESTORE.md](BACKUP_RESTORE.md); and
- rollback: [ROLLBACK.md](ROLLBACK.md).

The processes cannot be activated for pilot use until their named owners and escalation contacts are recorded.

## 7. Conditions to Change the Decision to Ready

The release owner may change the recommendation to `READY FOR PILOT RELEASE` only after:

1. all blocked data, user, box, label, and contact gates are completed;
2. the final HTTPS environment passes the deployment checklist;
3. backup and restore evidence is recorded for the pilot environment;
4. the final-domain QR workflow passes on a mobile device;
5. the records-office owner and pilot representative sign [PHASE_12_UAT.md](PHASE_12_UAT.md);
6. no new critical or high-severity defect is open; and
7. the release revision and rollback point are recorded.
