# District Records Management System (DRMS)

## Approved MVP Implementation Plan

**Status:** Phase 12 technical UAT complete; pilot release blocked pending official pilot assets, named contacts, and acceptance sign-off
**Requirements authority:** `PRD.md`  
**Detailed source:** `District_Records_Management_System_Codex_Phase_By_Phase_Work_Prompts.md`  
**Execution tracker:** `TASKS.md`

## 1. Delivery Strategy

DRMS will be delivered in Phases 0 through 12. Phases are sequential approval gates, not parallel feature tracks.

For every phase:

1. Inspect the repository and previously completed work.
2. Confirm the applicable requirements and prerequisites.
3. Report the current state, assumptions, risks, proposed data changes, authorization rules, tests, and implementation steps.
4. Implement only the current phase.
5. Add or update automated tests.
6. Run targeted tests.
7. Run the complete prior-phase regression suite.
8. Run applicable formatting, static analysis, migration, and build checks.
9. Correct failures caused by the phase.
10. Update documentation and `TASKS.md`.
11. Produce a completion report and stop for human approval.

A later phase must not begin until the current phase's acceptance criteria pass, blocking defects are resolved, required validation is complete, and the phase is approved.

## 2. Technical Direction

The verified foundation uses Laravel 12.64.0, PHP 8.4.23, Composer 2.10.2, Oracle MySQL 8.4.9 LTS, Node.js 24.18.0 LTS, npm 11.18.0, PHPUnit 11.5, Laravel Pint, Vite 7, FilamentPHP 5.7.3, and Livewire 4.3.3. Blade, Laravel policies, and domain feature tests remain the approved application approach.

The implementation will follow these cross-phase rules:

- Use generic organizational-unit, managing-office, upstream-office, and receiving-box domain terminology.
- Resolve district, division, and regional display labels from validated deployment settings or localization.
- Keep exactly two authorization levels.
- Enforce authorization and organizational-unit scoping on the server.
- Centralize controlled values and workflow transitions.
- Use database constraints and transactions to preserve consistency.
- Generate tracking sequences without counting document rows.
- Keep transaction and correction history auditable.
- Use secure random QR tokens; a QR token identifies a box but never authorizes access.
- Avoid later-phase features, unnecessary dependencies, premature abstractions, and unrelated refactors.

## 3. Phase Plan

### Phase 0 — Repository Audit and Technical Foundation

**Objective:** Establish a verified, reproducible foundation without implementing DRMS business modules.

**Prerequisites:**

- Follow the approved `CONSTRAINTS.md`.
- Inspect the complete repository, including any files added after this documentation baseline.

**Deliverables:**

- Verify PHP, Composer, Laravel, Filament, Livewire, database, authentication, testing, and frontend build state.
- Determine whether the repository is a clean Laravel application or contains code that must be preserved.
- Establish startup, migration, testing, formatting, static-analysis when selected, and frontend-build workflows.
- Document installation, test-database setup, coding and naming conventions, and architecture decisions.
- Decide from repository evidence whether PHPUnit or Pest is used and document authorization, enum, service/action, audit-logging, and database naming conventions.
- Update `README.md` and `.env.example` only as required for reproducible setup.

**Gate:**

- Application boots.
- Clean test migrations succeed.
- Baseline tests pass.
- Frontend assets build.
- Setup instructions are reproducible.
- Secrets are not committed.
- No DRMS business module has been implemented.

### Phase 1 — Authentication, Roles, Organizational Units, and User Administration

**Objective:** Implement authentication, deployment settings, hierarchical organizational units, exactly two roles, and Level 2 administration.

**Deliverables:**

- Secure login/logout and inactive-user denial.
- Validated deployment settings and configurable labels.
- Hierarchical organizational units with valid profile-specific parent relationships and cycle prevention.
- Level 1 and Level 2 user data and administration.
- Level-aware dashboard placeholder and limited profile editing.
- Policies or equivalent server-side authorization.

**Gate:**

- Both active roles authenticate.
- Level 1 administrative access is denied.
- Level 1 requires an active organizational unit.
- Invalid hierarchies and role/unit assignments are rejected.
- Deployment labels change without domain-code changes.
- Clean-database tests and Phase 0 regression checks pass.

### Phase 2 — Reference Data, Statuses, and Locations

**Objective:** Establish normalized, permission-controlled document types, origins, and domain values.

**Deliverables:**

- Document-type and document-origin management for Level 2.
- Seeded example document types from the requirements.
- Central controlled values for roles, document and recipient statuses, physical locations, priority, origin types, and active/inactive states.
- Active-only selection for new records with historical readability for inactive values.
- Protection against hard deletion of referenced data.

**Gate:**

- Level 1 can consume active reference values but cannot manage them.
- Duplicate normalized values are rejected where required.
- Inactive values cannot be selected for new records.
- Historical relationships remain readable.
- Phase 1 regressions pass.

### Phase 3 — Core Document Registration and Tracking Numbers

**Objective:** Register authorized documents and generate immutable, concurrency-safe tracking numbers.

**Deliverables:**

- Document model and role-appropriate registration.
- Tracking format `DRMS-{OFFICE_CODE}-YYYY-000001` using validated settings.
- Sequence allocation scoped to the managing office or deployment tenant without counting rows.
- Level 2 official-origin classification.
- Authorized document list, detail page, and basic tracking-number/subject search.

**Gate:**

- Tracking numbers are automatic, unique, immutable, and concurrency-safe.
- Level 1 can create only for their own unit and cannot set official origin.
- Level 1 cannot view unrelated records.
- Invalid input creates no partial record.
- No routing logic has been activated.
- Prior-phase regressions pass.

### Phase 4 — Recipient Assignment and Multi-Unit Documents

**Objective:** Support atomic assignment of one or many recipient organizational units.

**Deliverables:**

- Independent recipient rows with assignment, placement, claim, claimant, receiver, and status fields required by the PRD.
- Level 2 recipient assignment and controlled removal.
- Database and application uniqueness for each document-recipient-unit pair.
- Level 1 visibility restricted to its own recipient row.
- Designed, but not yet fully activated, aggregate document-status behavior.

**Gate:**

- Single and multiple assignments work atomically.
- Duplicate or invalid bulk assignments roll back.
- Level 1 cannot assign recipients or see another unit's recipient data.
- Removal is blocked after downstream activity.
- Phase 3 regressions pass.

### Phase 5 — Routing Engine, State Transitions, and Transaction History

**Objective:** Replace arbitrary status/location editing with authorized domain actions and permanent movement history.

**Deliverables:**

- Central allowed-transition definition and routing service or equivalent domain mechanism.
- Required submission, managing-office, upstream-office, distribution, recipient-assignment, receiving-box preparation, and cancellation actions.
- Atomic state/location update plus exactly one transaction.
- Action buttons based on state and authorization.
- Transaction timeline and required remarks handling.

**Gate:**

- Every permitted and important prohibited transition is tested.
- Wrong-role, invalid-state, repeated, and incomplete actions fail without changing state.
- Cancellation requires a reason.
- Level 1 cannot edit or delete transaction history.
- No controller or Filament action bypasses the domain workflow.
- Prior-phase regressions pass.

### Phase 6 — Receiving Boxes and Permanent QR Codes

**Objective:** Administer one active receiving box per eligible unit and generate secure permanent QR labels.

**Deliverables:**

- Receiving-box records with unit, random unique token, optional physical location, status, and regeneration time.
- Cryptographically secure token generation and auditable regeneration.
- Authenticated token route without sequential unit identifiers.
- Level 2 receiving-box administration, QR image, printable label, and inventory preview.
- Atomic Level 2 placement through the Phase 5 routing service.

**Gate:**

- One-active-box rule and token uniqueness are enforced.
- Invalid or obsolete tokens fail safely.
- Anonymous and cross-unit inventory access is denied.
- Inactive boxes reject placement.
- Placement updates recipient/document state and creates exactly one transaction atomically.
- Claim confirmation remains unimplemented.
- Routing regressions pass.

### Phase 7 — QR Inventory and Multi-Document Claim Confirmation

**Objective:** Allow an authenticated Level 1 user to view and claim eligible records from their unit's box.

**Deliverables:**

- Mobile-friendly authenticated box inventory.
- Server-filtered display of ready, placed, non-cancelled recipients for the matching unit and box.
- Single- and multi-recipient confirmation with receiver name, position/designation, and optional remarks.
- Server-derived claimant, unit, time, recipient identity, and configured request/device context.
- Atomic, idempotent claim processing protected against concurrency.
- Correct partial-claimed and completed aggregate states.

**Gate:**

- Anonymous, wrong-unit, manipulated-ID, empty, repeated, and concurrent claim scenarios are tested.
- Only eligible recipients appear and can be claimed.
- Single and multiple claims create correct transactions and claimant context.
- Cross-unit isolation is demonstrated.
- Prior-phase regressions pass.

### Phase 8 — Notifications and Reminders

**Objective:** Add persistent, scoped ready-for-pickup notifications and controlled optional email delivery.

**Deliverables:**

- One notification event: Level 2 placement of a recipient record into the matching unit's receiving box, making it ready for pickup.
- Active designated recipient selection.
- Dispatch only after successful commit.
- Duplicate prevention, role-scoped read/unread behavior, and persistence until explicit owner deletion.
- Queued delivery when supported.
- Tested email or documented feature flag.

**Gate:**

- Correct unit users are notified only at ready-for-pickup placement; other units and other workflow events are not.
- Rolled-back and repeated actions do not generate incorrect notifications.
- Inactive users are excluded.
- Notification payloads use the tracking number and a safe URL.
- Notification failures do not corrupt workflow state.
- Prior-phase regressions pass.

### Phase 9 — Universal Search and Role-Scoped Filters

**Objective:** Complete authorized search and advanced filters without organizational-unit leakage.

**Deliverables:**

- Search across all fields documented in the PRD and source specification.
- Shared authorization scope for results, suggestions, counts, filters, and direct record access.
- Pagination, justified indexes, N+1 prevention, and documented dataset assumptions.

**Gate:**

- Exact, partial, combined, date-range, and cancelled-record scenarios pass.
- Level 1 cannot infer another unit's data.
- Level 2 sees all authorized matching records.
- Practical query/performance checks pass.
- Prior-phase regressions pass.

### Phase 10 — Dashboards, Reports, and Exports

**Objective:** Provide role-specific operational monitoring and secure authorized exports.

**Deliverables:**

- Level 1 and Level 2 dashboards with the source-defined operational counts.
- Approved date, unit, type, origin, upstream movement, box inventory, claim, cancellation, user transaction, and monthly movement reports.
- Processing/pickup averages only after metric definitions are documented.
- Authorization-scoped exports with validated filters, formula-injection protection, and safe handling of large results.

**Gate:**

- Dashboard counts match seeded database facts.
- Level 1 counts and reports are unit-scoped.
- Level 2 receives managing-office-wide authorized results.
- Report filters and export contents are correct.
- Formula-injection payloads are neutralized.
- Completed and cancelled records are categorized correctly.
- Prior-phase regressions pass.

### Phase 11 — Audit Hardening, Security Review, and Operational Readiness

**Objective:** Verify security and audit completeness and prepare the application for pilot deployment.

**Deliverables:**

- Review and hardening of authentication, authorization, isolation, QR tokens, CSRF, rate limits, sessions, mass assignment, XSS, SQL injection, exports, logs, and transaction immutability.
- Complete significant-activity audit coverage.
- Production HTTPS, queue, scheduler, error logging, and deployment configuration guidance.
- Backup/restore rehearsal and rollback instructions.
- Deployment-profile/hierarchy migration procedure.
- Deployment checklist, pilot-user manual, and administrator guide.

**Gate:**

- Full tests, clean migrations/seeding, authorization matrix, security regressions, frontend build, formatter, static analysis, and dependency audit pass as applicable.
- Manual mobile QR workflow succeeds.
- Non-production backup and restore rehearsal succeeds.
- No known critical or high-severity vulnerability remains.
- Custody and claim history can be reconstructed.

### Phase 12 — User Acceptance Testing and Pilot Release

**Objective:** Validate real records-office and organizational-unit workflows and decide pilot readiness.

**Deliverables:**

- Execute all 17 source-defined UAT scenarios.
- Record each defect with severity, reproduction, expected/actual result, evidence, resolution, and regression coverage.
- Prepare pilot data, users, units, boxes, and QR labels.
- Document support and rollback contacts/processes.
- Complete the UAT sign-off checklist.

**Gate:**

- No unresolved critical or high defect remains.
- Medium defects have documented dispositions.
- Acceptance evidence is complete.
- Issue either `READY FOR PILOT RELEASE` or `NOT READY FOR PILOT RELEASE`.

## 4. Validation and Approval Standard

A phase is complete only when all applicable conditions are verified:

- Requirements and acceptance criteria are satisfied.
- Clean test migrations succeed.
- Targeted tests and the complete prior-phase regression suite pass.
- Authorization, validation, ownership, duplicates, invalid transitions, and rollback behavior are tested.
- Formatting passes.
- Static analysis passes when configured.
- Affected frontend assets build.
- Documentation and `TASKS.md` are current.
- No unresolved critical or high-severity issue remains.
- Manual verification steps and evidence are provided.
- A human reviewer approves the phase.

Each completion report must identify changes, files, migrations, authorization and validation rules, tests, commands and results, manual checks, limitations, and an `APPROVE PHASE` or `DO NOT APPROVE PHASE` recommendation.

## 5. Post-UAT Corrections

Corrections approved after technical UAT must preserve the completed phase requirements and pass focused plus full regression checks.

- Keep database-queued ready-for-pickup notifications operational in the approved local VS Code/XAMPP runtime, including delivery to active matching-unit Level 1 accounts created before placement.
- Configure successful Filament resource create and edit actions to return Level 2 users to the corresponding resource listing.
- Validate the notification backlog and future delivery, global resource redirect configuration, representative create/edit workflows, and existing authorization boundaries.
- Add audited Level 2 deletion of Level 1 accounts using history-preserving soft deletion; keep Level 2 accounts protected from deletion.
- Add audited deletion of organizational units only when no hierarchy, account, document, recipient, or receiving-box reference would be orphaned.
- Include document type and subject in ready-for-pickup notification payloads and validate database-queued rendering.
- Add the matching active receiving-box QR code and authenticated inventory link to the Level 1 dashboard, with unit-isolation and inactive/missing-box validation.
- Add a guarded local database-refresh command and a refresh-aware Windows queue-worker supervisor so notification processing resumes automatically after queue/cache tables are recreated.

## 6. Defect Handling

When a phase fails:

1. Keep the phase open and do not start later work.
2. Reproduce each defect.
3. Identify its root cause and prior-phase regression risk.
4. Implement the smallest in-scope correction.
5. Add a regression test when practical.
6. Rerun targeted, prior-phase, and applicable quality checks.
7. Record remaining risks and repeat the phase approval decision.

Critical and high defects block phase approval and pilot release.
