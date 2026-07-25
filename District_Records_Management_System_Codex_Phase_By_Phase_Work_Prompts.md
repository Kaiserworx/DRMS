# District Records Management System (DRMS) Phase-by-Phase Work Prompts for Codex

## Purpose

This document converts the **District Records Management System (DRMS)** concept into controlled, phase-by-phase work prompts for Codex.

The intended implementation is a web application using:

- Laravel 12
- PHP 8.2 or later, subject to Laravel project requirements
- MySQL
- FilamentPHP
- Livewire and Blade
- Role-based authorization
- Automated feature and unit tests

The prompts are designed to prevent Codex from building the entire application in one uncontrolled pass. Each phase must be analyzed, validated, implemented, tested, documented, and accepted before the next phase begins.

> **Scalability requirement:** The initial implementation may use district-profile labels in the interface, but the database and domain code must remain reusable for Division Office or Regional Office deployment without adding new user levels.

---

# Part I — Mandatory Instructions for Every Codex Phase

Copy the following instructions at the beginning of every phase prompt.

## Universal Codex Guardrails

You are working on the **District Records Management System (DRMS)**.

Follow these rules strictly:

1. **Do not begin coding immediately.**
2. First inspect the existing repository, dependencies, configuration, migrations, models, tests, routes, policies, Filament resources, and relevant documentation.
3. Summarize the current state before proposing changes.
4. Identify ambiguities, conflicts, security risks, migration risks, and missing prerequisites.
5. Present a concise implementation plan for the current phase only.
6. Do not implement features assigned to a later phase.
7. Preserve working code unless a change is required by the current phase.
8. Use database transactions for operations that update multiple related records.
9. Put business rules in dedicated service, action, policy, enum, state-transition, or domain classes rather than duplicating them in controllers or UI components.
10. Enforce authorization on the server side. Hiding UI buttons is not sufficient.
11. Validate every external input.
12. Add or update automated tests before considering the phase complete.
13. Run the relevant test suite, static analysis, formatting, and build checks.
14. Fix failures introduced by the phase.
15. Do not proceed to the next phase automatically.
16. At the end of the phase, stop and provide a completion report for human review.

## Required Pre-Coding Response

Before editing files, report:

- Repository state and relevant files found
- Current framework and package versions
- Existing implementation related to the phase
- Assumptions
- Risks
- Proposed database changes
- Proposed authorization rules
- Proposed test cases
- Exact implementation steps

Then wait for approval when operating interactively. When running in an autonomous coding environment, continue only when the phase requirements are unambiguous, but still record the pre-coding analysis in the work log.

## Required Validation Standard

Every phase must include validation at these levels when applicable:

- Request or form validation
- Database constraints
- Model or domain validation
- Authorization policies
- Workflow or state-transition validation
- Organizational-unit ownership validation
- Duplicate prevention
- Auditability
- Error handling

## Required Testing Standard

Use the project’s established testing framework. For Laravel, prefer PHPUnit or Pest according to the repository configuration.

Tests must cover:

- Happy paths
- Unauthorized access
- Invalid input
- Duplicate data
- Cross-organizational-unit access attempts
- Invalid workflow transitions
- Database rollback or consistency where relevant
- Regression behavior for previously completed phases

## Required Completion Gate

A phase is complete only when all of the following are true:

- Acceptance criteria are satisfied.
- Database migrations run successfully on a clean test database.
- Relevant tests pass.
- Previous phase tests still pass.
- Authorization has been tested.
- Validation has been tested.
- No unresolved critical or high-severity issue remains.
- Code formatting passes.
- Static analysis passes if configured.
- Frontend assets build successfully if affected.
- Documentation is updated.
- A manual verification checklist is provided.

At completion, output:

1. Summary of changes
2. Files created or modified
3. Migrations added
4. Authorization rules implemented
5. Validation rules implemented
6. Tests added or updated
7. Commands executed and results
8. Manual testing steps
9. Known limitations
10. Recommendation: `APPROVE PHASE` or `DO NOT APPROVE PHASE`

Stop after the completion report.

---

# Part II — Project-Wide Domain Rules

## Deployment and Upgrade Architecture

DRMS is a reusable records-routing platform. The initial deployment is for a district office, but the database, authorization scopes, services, routes, and UI configuration must not hard-code a district-only hierarchy.

### Supported Deployment Profiles

| Deployment Profile | Managing Records Office | Level 1 Organizational Units | Typical Upstream Office | Receiving-Box Label |
|---|---|---|---|---|
| District | District Office | Schools | Division Office | School Box |
| Division | Division Office | Districts, schools, sections, or functional units | Regional Office | Unit Box |
| Regional | Regional Office | Divisions, sections, or functional units | Central Office or external agency | Unit Box |

### Architectural Rules

- Use generic domain names such as `organizational_units`, `organizational_unit_id`, `managing_office`, `upstream_office`, and `receiving_boxes` in the database and business layer.
- District-specific labels such as **School**, **District Office**, **Division Office**, and **School Box** must come from deployment configuration or localization, not from hard-coded business logic.
- Store the deployment profile, managing-office name, office code, and display labels in validated configuration or settings.
- Model organizational units hierarchically using a nullable `parent_id` so the same application can represent schools under a district, districts under a division, or divisions under a region.
- Preserve only two authorization levels. Do not add Level 3 or Level 4 roles for Division or Regional deployment. The same Level 1 and Level 2 roles are scoped to the selected deployment.
- Level 1 is the **Unit Encoder**. In the district profile, its display label may be **School Encoder**.
- Level 2 is the **Records Administrator**. In the district profile, its display label may be **District Administrative Officer**.
- All actions involving the managing office or upstream office remain under Level 2 jurisdiction.
- Tracking numbers must include a configurable office code and must remain unique within the deployment. Example formats include `DRMS-CSD-2026-000001`, `DRMS-SDO-TARLAC-2026-000001`, and `DRMS-RO3-2026-000001`.
- Future organizational expansion must be accomplished through configuration, data, and scoped migrations—not by duplicating the application or rewriting the workflow engine.

Codex must preserve these rules throughout all phases.

## Users and Roles

The system has only two roles:

- **Level 1 — Unit Encoder**
- **Level 2 — Records Administrator**

Level 1 users belong to one organizational unit. Level 2 users may have no organizational unit assignment.

## Core Authorization Rules

### Level 1

May:

- Create outgoing records for their own organizational unit.
- View and search records related to their organizational unit.
- View documents assigned to their organizational unit.
- View their organizational unit's transaction history.
- Access their receiving-box page after authentication and organizational unit validation.
- Confirm receipt only for recipients assigned to their organizational unit.

May not:

- Create records for another organizational unit.
- Assign recipient organizational units.
- Define or change the official origin.
- Record Upstream Office transactions.
- Change managing office routing information.
- View another organizational unit's private records.

### Level 2

May:

- Create records for any organizational unit or for documents not initialized by an organizational unit.
- Define official document origins.
- Assign one or multiple recipient organizational units.
- Record all Upstream Office transactions.
- Manage organizational units, users, types, origins, receiving boxes, routing, reports, and cancellations.
- Search all authorized records.

## Tracking Rules

- `Document_ID` is assigned by the database.
- `Tracking_No` is assigned automatically by the system.
- Tracking numbers are unique, immutable, and never reused.
- Recommended format: `DRMS-{OFFICE_CODE}-YYYY-000001`.
- Concurrency must not create duplicate tracking numbers.

## Recipient Rules

- One document may have one or multiple recipient organizational units.
- An organizational unit may appear only once as a recipient of the same document.
- Each recipient has an independent status and pickup record.
- Main document status is derived from the document workflow and recipient completion state.

## QR Receiving-Box Rules

- Each active organizational unit has at most one active receiving box.
- Each receiving box has a unique, random, non-sequential QR token.
- Scanning identifies the box but does not authorize access.
- Authentication is required.
- The authenticated Level 1 user’s organizational unit must match the box organizational unit.
- Level 2 may access receiving boxes for administration and troubleshooting.
- Only documents explicitly placed in the box appear in its inventory.

## Audit Rules

- Every significant routing or claim action creates a transaction record.
- Ordinary users cannot delete transaction history.
- Cancelled documents remain searchable.
- Corrections must be traceable.
- Claims record user, receiver name, position, date, time, and selected recipients.

---

# Part III — Phase Sequence

## Phase 0 — Repository Audit and Technical Foundation

### Codex Prompt

Implement **Phase 0 only: repository audit and technical foundation** for DRMS.

Do not build business modules yet.

### Objectives

- Inspect the repository and confirm the actual technology stack.
- Establish development and testing standards.
- Ensure the application can boot, migrate, test, and build reliably.
- Document architectural decisions before domain development.

### Required Work

1. Inspect:
   - PHP and Composer configuration
   - Laravel version
   - Filament version
   - Livewire version
   - Database configuration
   - Authentication status
   - Testing framework
   - Frontend build configuration
   - Existing roles or permissions packages
2. Produce or update:
   - `README.md`
   - `.env.example`
   - local installation instructions
   - test database instructions
   - coding and naming conventions
   - architecture notes
3. Configure only what is necessary for:
   - application startup
   - migrations
   - automated tests
   - formatting
   - static analysis, if selected
   - frontend build
4. Decide and document:
   - PHPUnit or Pest
   - authorization approach
   - enum strategy
   - service/action class convention
   - audit logging approach
   - database naming convention

### Validation Before Coding

Confirm:

- The project is either a clean Laravel application or identify existing code that must be preserved.
- Package versions are compatible.
- The database driver is available in the test environment.
- No credentials or secrets are committed.

### Tests and Checks

Run at minimum:

```bash
php artisan about
php artisan migrate:fresh --env=testing
php artisan test
npm install
npm run build
```

Also run formatter and static analysis if configured.

### Acceptance Criteria

- Application boots.
- Clean migrations run.
- Baseline tests pass.
- Assets build.
- Setup instructions are reproducible.
- No DRMS business feature beyond technical scaffolding is implemented.

Stop after the Phase 0 completion report.

---

## Phase 1 — Authentication, Roles, Organizational Units, and User Administration

### Codex Prompt

Implement **Phase 1 only: authentication, the two user levels, hierarchical organizational units, deployment settings, and user administration**.

Do not implement document registration, routing, QR inventory, notifications, or reports.

### Objectives

- Establish secure login and logout.
- Create the two-role authorization model.
- Configure the current deployment profile and managing office.
- Manage hierarchical organizational units and users.
- Enforce organizational-unit ownership for Level 1 accounts.

### Required Data Model

#### Deployment Settings

Include or securely configure at least:

- deployment_profile: district, division, or regional
- system_name
- managing_office_name
- managing_office_code
- managing_office_level
- upstream_office_label
- level_1_unit_label
- receiving_box_label
- tracking_prefix
- status

Do not expose secret application configuration through editable settings. Validate and authorize all operational settings.

#### Organizational Units

Use an `organizational_units` table with at least:

- id
- parent_id, nullable self-reference
- unit_type
- unit_code
- unit_name
- short_name, nullable
- address, nullable
- contact_person, nullable
- contact_number, nullable
- email, nullable
- status
- timestamps

The data model must support a district containing schools, a division containing districts or functional units, and a region containing divisions or functional units. Prevent invalid parent-child combinations and organizational cycles.

#### Users

Include at least:

- id
- organizational_unit_id, nullable for Level 2
- full_name
- position, nullable
- email, unique
- username, unique when used
- password
- role
- status
- last_login_at, nullable
- timestamps

### Business Rules

- Only Level 1 users require an active organizational unit.
- Level 2 users may exist without an organizational-unit assignment.
- Only Level 2 can manage deployment settings, organizational units, and users.
- Inactive users cannot log in.
- Inactive organizational units cannot receive new Level 1 users unless explicitly reactivated.
- A Level 1 user cannot change their own organizational unit or role.
- Parent-child unit relationships must comply with the active deployment profile.
- No user role beyond Level 1 and Level 2 may be introduced.

### Required UI

- Login page
- Logout action
- Level-aware dashboard placeholder
- Level 2 deployment-settings page
- Level 2 organizational-unit management
- Level 2 user management
- Profile page with limited self-editing
- Display labels resolved from deployment settings

### Required Validation

- Unique unit code within the configured deployment scope
- Valid unit type for the deployment profile
- Valid parent organizational unit
- No self-parenting or cyclic hierarchy
- Valid email format
- Unique login identifiers
- Strong password rules
- Valid role values
- Organizational unit required for Level 1
- Organizational unit prohibited or optional according to the chosen Level 2 rule
- Active status checks
- Valid and immutable managing-office code after production records exist, unless a controlled migration procedure is used

### Required Authorization Tests

- Level 1 cannot access deployment settings.
- Level 1 cannot access organizational-unit management.
- Level 1 cannot access user management.
- Level 1 cannot create or edit another user.
- Level 2 can manage settings, units, and users.
- Inactive user cannot authenticate.
- Level 1 cannot be assigned to an inactive or nonexistent organizational unit.
- Invalid hierarchy and cycle attempts are rejected.

### Acceptance Criteria

- Both roles can authenticate when active.
- Access is enforced by policies or equivalent server-side authorization.
- Unit-based user assignment is validated.
- Deployment labels can change without altering domain code.
- Tests pass on a clean database.

Stop after the Phase 1 completion report.

---

## Phase 2 — Reference Data: Document Types, Origins, Statuses, and Locations

### Codex Prompt

Implement **Phase 2 only: DRMS reference data and domain enumerations**.

Do not implement full document registration or workflow actions yet.

### Objectives

- Establish controlled reference data.
- Prevent inconsistent free-text classifications.
- Define status and location values used by later phases.

### Required Data

#### Document Types

- name
- description, nullable
- default_workflow, nullable
- status

Seed examples:

- Appointment
- Book Delivery
- Leave Application
- Liquidation
- Memorandum
- Payroll
- Purchase Request
- Service Record
- Transfer Endorsement
- Travel Order
- Others

#### Document Origins

- origin_type
- origin_name
- office_code, nullable
- address, nullable
- status

### Domain Enums or Equivalent

Define and test controlled values for:

- User roles
- Record statuses
- Recipient statuses
- Physical locations
- Priority
- Origin types
- Active/inactive statuses

### Business Rules

- Only Level 2 can create or edit origins.
- Only Level 2 can manage document types.
- Level 1 may view active reference values needed by forms.
- Inactive reference records remain available for historical display but cannot be selected for new records.
- Do not hard-delete reference records that are already in use.

### Required Tests

- Level 1 cannot manage types or origins.
- Level 2 can manage them.
- Duplicate normalized names are rejected when required.
- Inactive references are excluded from new-record selections.
- Historical relationships remain readable.

### Acceptance Criteria

- Reference data is normalized and permission-controlled.
- Domain values are not duplicated as scattered magic strings.
- Tests pass with Phase 1 regression coverage.

Stop after the Phase 2 completion report.

---

## Phase 3 — Core Document Registration and Tracking Number Generation

### Codex Prompt

Implement **Phase 3 only: core document registration and automatic tracking-number generation**.

Do not implement routing transactions, multi-unit claiming, QR receiving-box functions, notifications, or reports beyond a basic document list and detail page.

### Objectives

- Allow Level 1 to create organizational unit-originated draft or submitted records for their own organizational unit.
- Allow Level 2 to create records for any source scenario.
- Generate immutable, concurrency-safe tracking numbers.
- Allow Level 2 to define the official origin.

### Required Document Fields

Include at least:

- id
- tracking_no
- document_type_id
- subject
- description, nullable
- origin_id, nullable until Level 2 classification when appropriate
- origin_reference_no, nullable
- submitting_unit_id, nullable
- created_by
- priority
- current_status
- current_location
- date_received, nullable
- due_date, nullable
- remarks, nullable
- cancellation fields reserved but not yet activated
- timestamps

### Tracking Number Requirements

- Format: `DRMS-{OFFICE_CODE}-YYYY-000001`.
- The office code must come from validated deployment settings, not a hard-coded constant.
- Sequence allocation must be scoped to the configured managing office or deployment tenant.
- Unique database constraint.
- Immutable after creation.
- Safe under concurrent requests.
- Sequence behavior documented.
- Sequence allocation must not rely on counting existing rows.

### Business Rules

- Level 1 may create records only for their own organizational unit.
- Level 1 cannot set or change official origin.
- Level 2 may create for any organizational unit or without a submitting organizational unit.
- Only Level 2 defines official origin.
- Level 1 sees only records related to their organizational unit.
- Level 2 sees all records.

### Required UI

- Document list
- Document creation form appropriate to role
- Document detail page
- Read-only tracking number display
- Basic role-scoped search by tracking number and subject

### Required Validation

- Active document type required.
- Subject required with sensible length limits.
- Priority must be valid.
- Origin must reference an active valid origin when supplied.
- The Level 1 submitting unit must be derived from the authenticated user and never trusted from form input.
- Dates must be valid and logically consistent.

### Required Tests

- Tracking number generated automatically.
- Tracking number uniqueness under repeated creation.
- Tracking number cannot be mass-assigned or edited.
- Level 1 cannot create for another organizational unit.
- Level 1 cannot set official origin.
- Level 2 can set official origin.
- Level 1 cannot view an unrelated document.
- Validation errors do not create partial records.

### Acceptance Criteria

- Core records are secure and searchable by authorized users.
- Tracking generation is tested and safe.
- No routing logic is prematurely implemented.

Stop after the Phase 3 completion report.

---

## Phase 4 — Recipient Assignment and Multi-Unit Documents

### Codex Prompt

Implement **Phase 4 only: organizational-unit recipient assignment and multi-unit document support**.

Do not implement routing transitions, QR claims, notifications, or reports yet.

### Objectives

- Allow Level 2 to assign one or multiple recipient organizational units.
- Give each recipient an independent status record.
- Prevent duplicate recipient organizational units on the same document.

### Required Recipient Model

Include at least:

- id
- document_id
- recipient_unit_id
- receiving_box_id, nullable until box assignment
- recipient_status
- date_assigned
- date_placed, nullable
- date_claimed, nullable
- claimed_by_user_id, nullable
- received_by_name, nullable
- received_by_position, nullable
- remarks, nullable
- timestamps

### Business Rules

- Only Level 2 assigns or removes recipients.
- Each document-recipient-unit pair must be unique.
- Only active organizational units may be newly assigned.
- Recipient removal is blocked after downstream transactions exist; use auditable correction instead.
- Level 1 can view recipient records only for their organizational unit.
- Multi-unit document status derivation must be designed but not fully activated until routing and claims phases.

### Required Tests

- One recipient can be assigned.
- Multiple recipients can be assigned.
- Duplicate recipient is rejected by validation and database constraint.
- Level 1 cannot assign recipients.
- Level 1 sees only their organizational unit's recipient row.
- Recipient creation is rolled back if bulk assignment contains invalid data.

### Acceptance Criteria

- Multi-unit assignment is atomic and auditable.
- Cross-unit visibility is prevented.
- Phase 3 tests remain green.

Stop after the Phase 4 completion report.

---

## Phase 5 — Routing Engine, Status Transitions, and Transaction History

### Codex Prompt

Implement **Phase 5 only: routing actions, valid state transitions, current location, and permanent transaction history**.

Do not implement QR receiving-box claims, notifications, exports, or advanced reports yet.

### Objectives

- Replace arbitrary status editing with controlled actions.
- Record every movement as a transaction.
- Enforce the jurisdiction of Level 2 over all Upstream Office actions.

### Required Transaction Model

Include at least:

- id
- document_id
- recipient_id, nullable
- action
- previous_status
- new_status
- from_location
- to_location
- performed_by
- transaction_date
- remarks, nullable
- ip_address, nullable
- device_info, nullable
- timestamps if project convention requires

### Required Routing Actions

Implement only valid actions appropriate to the document context, including:

- `SUBMIT_BY_UNIT` — display as **Submit by School** in the district profile
- `RECEIVE_AT_MANAGING_OFFICE` — display as **Receive at District** in the district profile
- `FORWARD_TO_UPSTREAM_OFFICE` — display as **Forward to Division** in the district profile
- `RECORD_UPSTREAM_RECEIPT`, if retained as a status
- `RETURN_FROM_UPSTREAM_OFFICE`
- `MARK_FOR_DISTRIBUTION`
- `ASSIGN_RECIPIENT_UNIT`
- `PLACE_IN_RECEIVING_BOX`, prepared for Phase 6 integration
- Cancel with reason

### State Machine Requirements

- Define allowed transitions centrally.
- Prevent direct arbitrary updates to status and location.
- Validate user role for each action.
- Validate document and recipient prerequisites.
- Execute status update and transaction creation in one database transaction.
- Reject invalid or repeated transitions.

### Jurisdiction Rules

- Only Level 2 records receipt by Managing Office.
- Only Level 2 records forwarding to Upstream Office.
- Only Level 2 records receipt by or return from Upstream Office.
- Level 1 may submit their organizational unit's document.
- Level 1 may not simulate Upstream Office or Managing Office actions.

### Required UI

- Action buttons based on current state and authorization
- Transaction timeline on document detail page
- Remarks input where required
- Clear error message for invalid transition

### Required Tests

Test every permitted transition and important prohibited transition, including:

- Correct role succeeds.
- Wrong role is forbidden.
- Invalid state transition fails.
- Status and location remain unchanged after failure.
- Transaction row is created exactly once after success.
- Repeated submission does not duplicate transactions.
- Cancellation requires a reason.
- Transaction history cannot be edited or deleted by Level 1.

### Acceptance Criteria

- Workflow actions are centralized and tested.
- All status/location changes are auditable.
- No controller or Filament action bypasses the domain service.

Stop after the Phase 5 completion report.

---

## Phase 6 — Receiving Boxes and Permanent QR Codes

### Codex Prompt

Implement **Phase 6 only: receiving-box administration and permanent QR code generation**.

Do not implement claim confirmation yet; that is Phase 7.

### Objectives

- Create one secure digital receiving-box record per organizational unit.
- Generate a permanent QR code using a random token.
- Allow Level 2 to place eligible recipient documents in the correct box.

### Required Receiving Box Model

Include at least:

- id
- organizational_unit_id, unique according to active-box rule
- qr_token, unique
- box_location, nullable
- status
- last_qr_generated_at, nullable
- timestamps

### Security Requirements

- Use cryptographically secure random tokens.
- Never expose sequential organizational unit IDs in the public box URL.
- QR route must require authentication before inventory is shown.
- Token lookup must fail safely.
- Inactive boxes cannot accept new placements.
- Token regeneration must invalidate the old token and be auditable.

### Placement Action

Implement `Place in Receiving Box` through the routing service.

It must atomically:

1. Validate Level 2 authorization.
2. Validate recipient and active receiving box.
3. Confirm the recipient is eligible for placement.
4. Assign the box.
5. Record date placed.
6. Set recipient status to `Ready for Pickup`.
7. Update document status/location as appropriate.
8. Create the transaction record.

### Required UI

- Level 2 receiving-box management
- Generate or regenerate QR token
- QR image and printable box label
- Box physical location field
- Managing Office inventory preview

### Required Tests

- One active box per organizational unit rule.
- Token uniqueness.
- Sequential IDs are not used in URLs.
- Anonymous user cannot view inventory.
- A Level 1 user from another organizational unit cannot access the box.
- Level 2 can administer boxes.
- Placement requires eligible recipient.
- Placement creates one transaction and updates recipient atomically.

### Acceptance Criteria

- Permanent QR labels can be printed.
- Secure token routing works.
- Documents can be placed but not yet claimed.
- Previous routing tests remain green.

Stop after the Phase 6 completion report.

---

## Phase 7 — QR Receiving-Box Inventory and Multi-Document Claim Confirmation

### Codex Prompt

Implement **Phase 7 only: authenticated receiving-box inventory and multi-document receipt confirmation**.

Do not implement notifications or reports yet.

### Objectives

- Let a Level 1 user scan their organizational unit's permanent receiving-box QR code.
- Show only documents physically recorded in that box and ready for their organizational unit.
- Allow selection and confirmation of one or multiple received documents.

### Inventory Rules

The page must display only recipient rows where:

- Box token resolves to the authenticated user’s receiving box.
- Recipient unit matches the authenticated Level 1 user’s organizational unit.
- Recipient status is `Ready for Pickup`.
- Date placed is present.
- Document is not cancelled.

### Claim Form

Require:

- Selected recipient records
- Receiver name
- Receiver position or designation
- Optional remarks

Server-derived values:

- Organizational unit
- Claiming user
- Claim timestamp
- Document and recipient identity
- IP/device details when configured

### Claim Processing

For each selected recipient:

- Lock or otherwise protect against concurrent double claims.
- Revalidate organizational unit ownership and current status.
- Set recipient status to `Received by Recipient Unit`.
- Set claim date and claimant data.
- Create a transaction.

After processing:

- If some recipients remain unclaimed, set main document to `Partially Claimed` when applicable.
- If all required recipients are complete, set main document to `Completed`.
- Execute the batch atomically or explicitly define safe partial-failure behavior. Prefer atomic processing for one confirmation submission.

### Required Tests

- The correct organizational unit can view inventory.
- A different organizational unit receives a forbidden response.
- Anonymous user is redirected to login.
- Only ready-for-pickup documents appear.
- One document can be claimed.
- Multiple selected documents can be claimed.
- Empty selection is rejected.
- Already claimed recipient cannot be claimed again.
- Cross-unit manipulated IDs are rejected.
- Concurrent or repeated submission does not duplicate claims.
- Partial-claimed document status is correct.
- Completed status is set only after all recipients finish.
- Audit transaction contains receiver and claimant context.

### Acceptance Criteria

- QR scan-to-claim workflow is mobile-friendly.
- Organizational-unit isolation is demonstrated by tests.
- Claims are atomic, idempotent, and auditable.

Stop after the Phase 7 completion report.

---

## Phase 8 — Notifications and Reminders

### Codex Prompt

Implement **Phase 8 only: in-system notifications and optional email delivery**.

Do not implement advanced reports or analytics yet.

### Objectives

Notify authorized organizational unit users when:

- A document is assigned to their organizational unit.
- A document is placed in their receiving box.
- An organizational-unit-originated document changes significant status.
- A document is returned from Upstream Office.
- A document remains unclaimed beyond the configured threshold.
- A record is cancelled or corrected.

### Requirements

- Use queued notifications when supported by deployment.
- Store in-system notifications.
- Keep notification dispatch outside core database transactions when appropriate, but dispatch only after successful commit.
- Avoid duplicate notifications for repeated actions.
- Notify active designated users only.
- Level 1 sees only their notifications.
- Level 2 may view delivery status or failures when implemented.

### Required Tests

- Correct organizational unit users receive notifications.
- Other organizational unit users do not.
- Notifications are not sent after rolled-back transactions.
- Duplicate action does not create duplicate notification.
- Inactive users are excluded.
- Read/unread behavior works.
- Queueable notification payload contains correct tracking number and safe URL.

### Acceptance Criteria

- In-system notifications work.
- Email is either working and tested or clearly feature-flagged and documented.
- Notification failures do not corrupt document workflow state.

Stop after the Phase 8 completion report.

---

## Phase 9 — Universal Search and Role-Scoped Filters

### Codex Prompt

Implement **Phase 9 only: complete universal search and advanced filters**.

Do not implement exports or analytics yet.

### Objectives

Provide fast search for all users while enforcing role and organizational unit data boundaries.

### Search Fields

Support authorized search by:

- Tracking number
- Subject
- Document type
- Organizational unit
- Origin type and name
- Destination
- Main status
- Recipient status
- Current location
- Priority
- Date created
- Date submitted
- Date received
- Date forwarded
- Date placed
- Date claimed
- Description and remarks
- Receiver name

### Authorization Rules

- Level 1 results include only documents created by, submitted by, assigned to, placed for, or claimed by their organizational unit.
- Level 2 may search all records.
- Search suggestions, counts, filters, and exports must obey the same scope.
- Direct URL access to a result must recheck authorization.

### Performance Requirements

- Add justified indexes.
- Avoid N+1 queries.
- Paginate results.
- Debounce or submit search appropriately.
- Document expected dataset size assumptions.

### Required Tests

- Exact tracking number search.
- Partial subject search.
- Combined filters.
- Date-range validation.
- Level 1 cannot infer another organizational unit's records through counts or filters.
- Level 2 sees all matching records.
- Cancelled records remain searchable when permitted.
- Query count or performance checks where practical.

### Acceptance Criteria

- Search is accurate, scoped, paginated, and performant.
- No unit-level data leakage is found.

Stop after the Phase 9 completion report.

---

## Phase 10 — Dashboards, Reports, and Exports

### Codex Prompt

Implement **Phase 10 only: role-specific dashboards, operational reports, and approved exports**.

### Objectives

Provide actionable monitoring without bypassing existing authorization scopes.

### Level 1 Dashboard

Include:

- Draft documents
- Submitted documents
- Received by Managing Office
- Forwarded to Upstream Office
- Ready for Pickup
- Claimed today
- Completed
- Unread notifications

### Level 2 Dashboard

Include:

- Received today
- Awaiting verification
- Waiting for forwarding
- At Upstream Office
- Returned from Upstream Office
- Waiting for distribution
- In receiving boxes
- Pending confirmation
- Completed
- Overdue or unclaimed
- Receiving-box summary with oldest waiting date

### Reports

Implement approved reports such as:

- Documents by date range
- Documents by organizational unit
- Documents by type
- Documents by origin
- Upstream Office-forwarded and returned documents
- Current receiving-box inventory
- Unclaimed documents
- Claimed documents
- Cancelled documents
- Transactions by user
- Monthly movement summary
- Average processing and pickup time, only after metric definitions are documented

### Export Rules

- Exports must use the same authorization scope as on-screen data.
- Validate date ranges and filters.
- Escape spreadsheet formula injection in CSV/Excel exports.
- Large exports should be queued or streamed when appropriate.
- Do not expose internal IDs unnecessarily.

### Required Tests

- Dashboard counts match seeded records.
- Level 1 dashboard is organizational unit-scoped.
- Level 2 dashboard is managing-office-wide.
- Report filters work.
- Export contents match authorization scope.
- CSV or spreadsheet injection payloads are neutralized.
- Cancelled and completed records are categorized correctly.

### Acceptance Criteria

- Counts are reproducible and documented.
- Reports agree with database facts.
- Exports are secure and permission-aware.

Stop after the Phase 10 completion report.

---

## Phase 11 — Audit Hardening, Security Review, and Operational Readiness

### Codex Prompt

Implement **Phase 11 only: security hardening, audit completeness, backup readiness, and deployment preparation**.

Do not add unrelated features.

### Objectives

- Verify the complete application against DRMS security and accountability requirements.
- Prepare for pilot deployment.

### Required Review Areas

- Authentication and inactive-user handling
- Authorization policies
- Organizational-unit data isolation
- QR token security and regeneration
- CSRF protection
- Rate limiting
- Session security
- Mass-assignment protection
- XSS and output escaping
- SQL injection resistance
- File/export safety
- Audit log coverage
- Transaction immutability
- Error logging without sensitive-data leakage
- Database backup and restore procedure
- HTTPS and production configuration
- Queue and scheduler configuration

### Activity Logging

Verify significant activities are recorded, including:

- Login when appropriate
- User and organizational unit administration
- Reference-data changes
- Document creation and update
- Origin classification
- Recipient assignment
- Routing actions
- Box token regeneration
- Placement and claim
- Cancellation and correction
- Report/export generation when required

### Required Tests and Checks

- Full automated test suite
- Clean database migration and seeding
- Authorization matrix tests
- Security regression tests
- Frontend production build
- Formatter
- Static analysis
- Dependency audit
- Manual mobile QR workflow test
- Backup and restore rehearsal in a non-production environment

### Acceptance Criteria

- No known critical or high-severity vulnerability remains.
- Audit history is sufficient to reconstruct custody and claims.
- Deployment checklist is complete.
- A deployment-profile change and organizational-hierarchy migration procedure is documented.
- Rollback and backup instructions exist.
- Pilot-user manual and administrator guide exist.

Stop after the Phase 11 completion report.

---

## Phase 12 — User Acceptance Testing and Pilot Release

### Codex Prompt

Perform **Phase 12 only: user acceptance testing support and pilot-release preparation**.

Do not add new scope unless a defect prevents acceptance.

### Objectives

- Validate the system against real records-office and organizational-unit workflows.
- Record defects and acceptance evidence.
- Prepare a controlled pilot release.

### UAT Scenarios

At minimum test:

1. Level 1 creates an organizational-unit-originated record.
2. Level 2 reviews and defines the origin.
3. Level 2 receives and forwards a document to Upstream Office.
4. Level 2 records return from Upstream Office.
5. Level 2 creates a Upstream Office-originated appointment record.
6. Level 2 creates a multi-unit book-delivery record.
7. Level 2 assigns recipient organizational units.
8. Level 2 places recipients in receiving boxes.
9. Correct Level 1 user scans the receiving-box QR code assigned to their organizational unit.
10. Wrong-organizational unit user is denied.
11. The recipient unit confirms one document.
12. The recipient unit confirms multiple documents.
13. A multi-unit document status becomes partially claimed.
14. Final recipient claim completes the document.
15. Both roles search for authorized records.
16. Managing Office generates pending-pickup and completed reports.
17. Cancellation remains visible in audit history.

### Defect Rules

- Record each defect with severity, steps, expected result, actual result, evidence, and resolution.
- Fix critical and high defects before release.
- Add a regression test for every fixed business-rule or authorization defect.
- Do not hide unresolved defects.

### Acceptance Criteria

- UAT sign-off checklist is complete.
- Critical and high defects are zero.
- Medium defects have documented disposition.
- Pilot data, users, organizational units, boxes, and QR labels are prepared.
- Support and rollback contacts/processes are documented.

Stop after the Phase 12 completion report and recommend either:

- `READY FOR PILOT RELEASE`, or
- `NOT READY FOR PILOT RELEASE`.

---

# Part IV — Prompt for Fixing Defects Between Phases

Use this prompt when a phase fails testing or review.

## Codex Defect-Fix Prompt

Review the attached phase completion report and the reported defects.

Work only on defects belonging to the current phase or regressions caused by it.

Before editing:

1. Reproduce each defect.
2. Identify the root cause.
3. Identify affected files and prior-phase risks.
4. Propose the smallest safe fix.
5. Define the regression tests to add.

Then:

- Implement the fix.
- Add a failing regression test first when practical.
- Run the affected test group.
- Run the complete prior-phase regression suite.
- Run formatting, static analysis, and build checks as applicable.

Do not add enhancements or proceed to the next phase.

End with a defect-resolution report showing:

- Defect
- Root cause
- Fix
- Tests added
- Commands and results
- Remaining risks
- Recommendation to approve or reject the current phase

---

# Part V — Human Phase Approval Template

Use this checklist before instructing Codex to begin the next phase.

```text
DRMS PHASE APPROVAL

Phase:
Reviewer:
Review Date:

[ ] Scope completed
[ ] Acceptance criteria satisfied
[ ] Automated tests passed
[ ] Previous-phase regression tests passed
[ ] Authorization verified
[ ] Validation verified
[ ] Database constraints verified
[ ] Manual checks completed
[ ] Documentation updated
[ ] No unresolved critical/high issue

Decision:
[ ] APPROVED — Proceed to next phase
[ ] REJECTED — Correct defects and repeat validation

Reviewer Notes:
```

---

# Part VI — Recommended Codex Operating Pattern

For every phase, use this sequence:

```text
1. INSPECT
2. ANALYZE
3. DESIGN
4. PRESENT PLAN
5. IMPLEMENT CURRENT PHASE ONLY
6. ADD OR UPDATE TESTS
7. RUN TARGETED TESTS
8. RUN REGRESSION TESTS
9. RUN QUALITY CHECKS
10. DOCUMENT RESULTS
11. STOP FOR APPROVAL
```

Codex must never interpret a successful code generation step as proof that the feature works. The phase is considered successful only after automated tests, authorization tests, validation checks, and manual acceptance criteria have been completed.

---

# Final Instruction to Codex

The DRMS handles the custody, routing, and receipt of physical government records across configurable organizational levels. Favor correctness, authorization, auditability, portability, and data consistency over rapid feature delivery.

Never bypass a failed validation or test merely to move to the next phase. Stop, report the failure honestly, correct the current phase, rerun the tests, and request phase approval.
