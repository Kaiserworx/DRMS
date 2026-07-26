# District Records Management System (DRMS)

## Product Requirements Document

**Status:** MVP requirements baseline  
**Source:** `District_Records_Management_System_Codex_Phase_By_Phase_Work_Prompts.md`  
**Initial deployment profile:** District  
**Product model:** Reusable records-routing platform for district, division, or regional deployments

## 1. Product Overview

The District Records Management System (DRMS) is a web application for registering, routing, tracking, distributing, and acknowledging receipt of physical government records.

The initial deployment supports a district office, its schools, and its upstream division office. The same database and domain design must also support division and regional deployments through configuration, organizational data, and scoped migrations rather than application duplication or additional authorization levels.

DRMS prioritizes:

1. Correct custody information.
2. Server-enforced authorization.
3. Complete transaction history.
4. Organizational-unit data isolation.
5. Secure and auditable receiving-box claims.
6. Portable deployment terminology and hierarchy.
7. Data consistency under repeated or concurrent requests.

## 2. Product Goals

- Give authorized personnel a reliable record of each document's identity, origin, recipients, status, and current physical location.
- Provide a controlled workflow for submission, managing-office receipt, upstream-office routing, distribution, receiving-box placement, and recipient confirmation.
- Support one or many recipient organizational units while tracking each recipient independently.
- Replace arbitrary status changes with authorized, validated, and auditable actions.
- Give organizational-unit users access only to records related to their unit.
- Give records administrators managing-office-wide operational visibility.
- Support permanent receiving-box QR codes without treating possession of a QR token as authorization.
- Prepare the application for secure pilot deployment and user acceptance testing.

## 3. Target Users and Roles

DRMS has exactly two authorization levels.

### 3.1 Level 1 — Unit Encoder

In the district deployment profile, this role may be displayed as **School Encoder**.

A Level 1 user:

- Belongs to one active organizational unit.
- May create outgoing records only for that organizational unit.
- May submit that unit's eligible records.
- May view and search records created by, submitted by, assigned to, placed for, or claimed by that unit.
- May view transaction history available to that unit.
- May access the unit's receiving-box inventory after authentication and unit validation.
- May confirm receipt only for recipient records assigned to that unit.

A Level 1 user may not:

- Create records for another organizational unit.
- Assign recipient organizational units.
- Define or change an official document origin.
- Record managing-office or upstream-office transactions.
- Change managing-office routing information.
- View another organizational unit's private records.
- Edit or delete permanent transaction history.
- Change their own role or organizational-unit assignment.

### 3.2 Level 2 — Records Administrator

In the district deployment profile, this role may be displayed as **District Administrative Officer**.

A Level 2 user:

- May exist without an organizational-unit assignment.
- May manage deployment settings, organizational units, users, document types, origins, receiving boxes, routing, reports, and cancellations.
- May create records for any organizational unit or for records not initialized by an organizational unit.
- Defines official document origins.
- Assigns one or multiple recipient organizational units.
- Records managing-office and upstream-office actions.
- May search all authorized records within the deployment.
- May administer and troubleshoot receiving boxes.

No Level 3 or Level 4 role is permitted for division or regional deployments. The same two roles are scoped to the selected deployment profile.

## 4. Deployment Profiles and Terminology

| Profile | Managing records office | Typical Level 1 units | Typical upstream office | Receiving-box label |
|---|---|---|---|---|
| District | District Office | Schools | Division Office | School Box |
| Division | Division Office | Districts, schools, sections, or functional units | Regional Office | Unit Box |
| Regional | Regional Office | Divisions, sections, or functional units | Central Office or external agency | Unit Box |

The product must:

- Use generic domain names such as organizational unit, managing office, upstream office, and receiving box.
- Resolve district-specific interface labels from validated deployment settings or localization.
- Store a deployment profile, system name, managing-office name and code, office level, upstream-office label, Level 1 unit label, receiving-box label, tracking prefix, and operational status.
- Model organizational units hierarchically with a nullable parent relationship.
- Prevent self-parenting, hierarchy cycles, and invalid parent-child combinations.
- Support organizational expansion through configuration, data, and controlled migrations.

## 5. Core Workflows

### 5.1 Administration and Access

1. A Level 2 user configures the deployment and manages organizational units and users.
2. Active users authenticate.
3. Inactive users are denied access.
4. The application applies role and organizational-unit authorization on the server.

### 5.2 Document Registration

1. A Level 1 user creates a record for their own organizational unit, or a Level 2 user creates a record for an authorized source scenario.
2. The system generates a unique, immutable tracking number.
3. A Level 2 user defines the official origin when applicable.
4. Authorized users can view the document list and detail page.

### 5.3 Recipient Assignment

1. A Level 2 user assigns one or multiple active organizational units as recipients.
2. Each document-recipient-unit pair is unique.
3. Each recipient receives an independent status and pickup record.
4. Invalid bulk assignment rolls back atomically.

### 5.4 Routing and Custody

1. A Level 1 user may submit their unit's eligible record.
2. A Level 2 user records managing-office receipt.
3. A Level 2 user may forward the record to, record receipt at, or record return from the configured upstream office.
4. A Level 2 user marks eligible records for distribution, assigns recipients, and places recipients in receiving boxes.
5. Every successful movement updates status and location and creates exactly one transaction in the same database transaction.
6. Invalid, unauthorized, repeated, or incomplete transitions fail without partially changing the record.

### 5.5 Receiving-Box Placement and Claim

1. Each active organizational unit has at most one active receiving box.
2. A Level 2 user places an eligible recipient record in the correct active box.
3. An authenticated Level 1 user scans the permanent QR code for their unit's box.
4. The system verifies the token, authentication, organizational-unit ownership, recipient state, and document state.
5. The user selects one or multiple ready-for-pickup recipient records and provides the receiver name and position or designation.
6. The system atomically records each claim and prevents repeated or concurrent double claims.
7. The main document becomes partially claimed when applicable and completed only when all required recipients are complete.

### 5.6 Search, Monitoring, and Reporting

1. Level 1 users search and filter only records related to their organizational unit.
2. Level 2 users search the complete authorized deployment dataset.
3. Search suggestions, counts, dashboards, filters, reports, direct record access, and exports apply the same authorization scope.
4. Role-specific dashboards summarize operational states.
5. Approved reports and exports reflect database facts and the user's authorization.

## 6. Functional Requirements

### 6.1 Authentication and User Administration

- Provide secure login and logout.
- Deny login to inactive users.
- Support exactly the Level 1 and Level 2 roles.
- Require an active organizational unit for Level 1 users.
- Permit Level 2 users to have no organizational-unit assignment.
- Restrict deployment settings, organizational units, and user administration to Level 2.
- Provide a profile page with limited self-editing.
- Validate unique login identifiers and strong passwords.

### 6.2 Organizational Units

Each organizational unit must support:

- A nullable parent.
- Unit type, code, name, and optional short name.
- Optional address and contact information.
- Active/inactive status.
- Timestamps.

Inactive organizational units cannot receive new Level 1 users or new recipient assignments unless reactivated. Unit codes must be unique within the configured deployment scope.

### 6.3 Reference Data

DRMS must provide controlled reference data for:

- Document types.
- Document origins.
- User roles.
- Document and recipient statuses.
- Physical locations.
- Priority.
- Origin types.
- Active/inactive states.

Only Level 2 may manage document types and origins. Inactive reference records remain readable for history but cannot be selected for new records. Referenced records must not be hard-deleted.

### 6.4 Document Records

Each document must include at least:

- Database identifier.
- Tracking number.
- Document type.
- Subject and optional description.
- Optional official origin until classified by Level 2.
- Optional origin reference number.
- Optional submitting organizational unit.
- Creating user.
- Priority.
- Current status and location.
- Optional received and due dates.
- Optional remarks.
- Reserved cancellation data.
- Timestamps.

Level 1 submitting-unit identity must be derived from the authenticated user and never trusted from form input.

Document listings for both authorization levels must default to the latest created document first, with a stable newest-first order for records created at the same time.

### 6.5 Tracking Numbers

- Format tracking numbers as `DRMS-{OFFICE_CODE}-YYYY-000001`.
- Read the office code from validated deployment settings.
- Scope sequence allocation to the configured managing office or deployment tenant.
- Enforce uniqueness with a database constraint.
- Keep tracking numbers immutable and never reuse them.
- Allocate sequences safely under concurrency.
- Do not generate sequences by counting existing rows.

### 6.6 Recipients

Each recipient record must include at least:

- Document and recipient organizational unit.
- Optional receiving box until placement.
- Recipient status.
- Assignment date.
- Optional placement and claim dates.
- Optional claiming user.
- Optional receiver name and position.
- Optional remarks.
- Timestamps.

Only Level 2 assigns or removes recipients. Recipient removal must be blocked after downstream transactions exist; later corrections must remain auditable.

### 6.7 Routing Engine and Transactions

Allowed workflow transitions must be defined centrally. Required actions include, when valid for the document context:

- `SUBMIT_BY_UNIT`
- `RECEIVE_AT_MANAGING_OFFICE`
- `FORWARD_TO_UPSTREAM_OFFICE`
- `RECORD_UPSTREAM_RECEIPT`, if retained in the approved status model
- `RETURN_FROM_UPSTREAM_OFFICE`
- `MARK_FOR_DISTRIBUTION`
- `ASSIGN_RECIPIENT_UNIT`
- `PLACE_IN_RECEIVING_BOX`
- Cancellation with a required reason

Each transaction must include at least the document, optional recipient, action, previous and new statuses, origin and destination locations, performing user, transaction date, optional remarks, and optional request/device context.

Status and location must not be freely editable. Controllers and user-interface actions must not bypass the central routing service or equivalent domain mechanism.

### 6.8 Receiving Boxes and QR Codes

- Allow at most one active receiving box per active organizational unit.
- Use a unique, cryptographically secure, random, non-sequential QR token.
- Do not expose sequential organizational-unit identifiers in public box URLs.
- Require authentication before displaying inventory.
- Treat the token only as box identification, not authorization.
- Require a Level 1 user's unit to match the box unit.
- Fail safely for invalid tokens.
- Prevent inactive boxes from accepting placements.
- Make token regeneration invalidate the old token and create an audit record.
- Provide Level 2 box administration, QR image generation, printable labels, physical location, and inventory preview.

### 6.9 Claims

The inventory page must show only non-cancelled recipient records that:

- Belong to the authenticated Level 1 user's organizational unit.
- Belong to the box resolved by the QR token.
- Are ready for pickup.
- Have a placement date.

A claim requires selected recipient records, receiver name, receiver position or designation, and optional remarks. The server derives the unit, claiming user, claim time, recipient identity, and configured request/device information.

One claim submission should be atomic. The system must revalidate and protect each recipient against concurrent or repeated claims.

### 6.10 Notifications

- Store in-system notifications.
- Notify active Level 1 users of the matching organizational unit only after Level 2 places their recipient record in the unit's receiving box and it becomes ready for pickup.
- Do not create Level 1 notifications for recipient assignment alone, ordinary status changes, upstream movement, cancellation, correction, or unclaimed reminders.
- Avoid duplicate notifications for repeated actions.
- Keep notification failures from corrupting document workflow state.
- Scope each user's notification access.
- Keep read and unread notifications in the user's notification list. Opening or marking a notification as read must not delete it.
- Delete a stored notification only through an explicit manual action by its owning user.
- Use queued delivery when supported by the deployment.
- Email delivery is optional; it must either be tested and operational or explicitly feature-flagged and documented.

### 6.11 Search

Authorized users must be able to search or filter by the documented identifiers, classifications, parties, statuses, locations, dates, descriptions, remarks, and receiver information.

Search must be scoped, paginated, and implemented without organizational-unit leakage through results, suggestions, filters, or counts. Direct access to a search result must recheck authorization.

### 6.12 Dashboards, Reports, and Exports

- Provide role-specific dashboard counts for the operational states listed in the source specification.
- Provide the approved operational reports for documents, recipients, transactions, receiving-box inventory, unclaimed and claimed items, cancellations, and monthly movement.
- Define processing-time and pickup-time metrics before implementing averages.
- Apply on-screen authorization scope to exports.
- Validate report filters and date ranges.
- Neutralize spreadsheet formula injection in CSV or spreadsheet exports.
- Queue or stream large exports when appropriate.
- Avoid exposing internal identifiers unnecessarily.

### 6.13 Audit and Corrections

- Create a permanent transaction for every significant routing or claim action.
- Keep cancelled records searchable for authorized users.
- Make corrections traceable.
- Prevent ordinary users from deleting transaction history.
- Record the claiming user, receiver identity, position, date, time, and selected recipients.
- Cover significant administration, reference-data, document, routing, box, claim, cancellation, correction, and required report/export activity.

## 7. Non-Functional Requirements

### 7.1 Security

- Enforce authentication, authorization, and organizational-unit boundaries on the server.
- Protect against cross-site request forgery, cross-site scripting, SQL injection, mass assignment, and unsafe file or export handling.
- Apply suitable session security and rate limiting.
- Avoid secrets and sensitive information in source control, responses, and logs.
- Require HTTPS and secure production configuration for deployment.

### 7.2 Reliability and Consistency

- Use database transactions for operations that update related records.
- Apply database constraints for uniqueness and relationships.
- Prevent partial records after validation or workflow failure.
- Prevent duplicate tracking numbers, recipients, transactions, notifications, and claims.
- Keep workflow state recoverable and auditable after failures.

### 7.3 Performance

- Paginate large result sets.
- Avoid N+1 queries.
- Add only justified indexes.
- Document expected dataset assumptions.
- Queue or stream workloads when required by deployment size.

### 7.4 Maintainability and Portability

- Keep business rules in dedicated policies, services, actions, enums, state-transition classes, or equivalent domain units.
- Avoid scattered magic strings for controlled values.
- Keep deployment-specific terminology out of database and business-layer naming.
- Follow the established framework, package, testing, and naming conventions confirmed during Phase 0.

### 7.5 Compatibility

The intended stack is:

- Laravel 12.
- PHP 8.2 or later, subject to verified Laravel project requirements.
- MySQL.
- FilamentPHP.
- Livewire and Blade.
- The repository's established PHPUnit or Pest test framework.

Exact installed versions and compatibility must be verified in Phase 0 before implementation.

## 8. MVP Scope

The MVP includes:

- Technical foundation and reproducible local/test setup.
- Authentication, the two roles, deployment settings, organizational units, and users.
- Reference data.
- Document registration and tracking numbers.
- Single- and multi-unit recipient assignment.
- Centralized routing and permanent transaction history.
- Receiving boxes and secure permanent QR codes.
- Authenticated single- and multi-document claims.
- In-system notifications and controlled optional email delivery.
- Role-scoped search and filters.
- Role-specific dashboards, approved reports, and secure exports.
- Security hardening, operational readiness, documentation, and backup/restore preparation.
- UAT support and pilot-release readiness assessment.

## 9. Explicit Exclusions

The MVP does not include:

- Authorization roles beyond Level 1 and Level 2.
- Anonymous receiving-box inventory or claim access.
- QR codes that authorize a user by possession alone.
- Hard-coded district-only database or domain structures.
- Features, integrations, dashboards, notifications, analytics, reports, or configuration systems not identified in the source specification.
- Premature work from a later phase before earlier phase acceptance.
- Silent fallback data, fake success responses, or bypasses for failed integrations or validation.
- Production release without the Phase 11 readiness gate and Phase 12 acceptance decision.

## 10. Success and Acceptance Criteria

The MVP is successful when:

- Active users authenticate and inactive users are denied.
- Both roles are enforced through tested server-side authorization.
- Level 1 data remains isolated by organizational unit.
- Tracking numbers are unique, immutable, and safe under concurrency.
- All valid movements and claims are authorized, atomic, and auditable.
- Invalid or repeated actions do not partially change data or duplicate history.
- Permanent QR labels work without exposing sequential identifiers or bypassing authentication.
- Single- and multi-recipient claims produce correct partial and completed states.
- Search, dashboards, reports, counts, and exports agree with authorized database facts.
- Clean migrations, automated tests, prior-phase regressions, formatting, static analysis when configured, and affected frontend builds pass.
- No known critical or high-severity vulnerability remains.
- Backup, restore, rollback, deployment, administrator, and pilot-user documentation is complete.
- UAT has no unresolved critical or high defects, medium defects have documented dispositions, and the release receives an explicit readiness recommendation.
