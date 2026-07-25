# District Records Management System (DRMS)

## Approved MVP Constraints

**Status:** Approved for Phase 0 foundation work  
**Approved:** 2026-07-25  
**Authority:** Direct user instruction and the approved requirements baseline

## 1. Required Technology Stack

- Use Laravel 12 for the MVP application.
- Use PHP 8.4 on the supported Laravel 12 range.
- Use MySQL 8.4 LTS as the application database.
- Use FilamentPHP, Livewire, and Blade for the approved application interface.
- Use Composer as the PHP dependency manager.
- Use Node.js 24 LTS and npm for frontend dependencies and builds.
- Use Git for local version control.
- Use the test framework and frontend build system supplied by the Laravel 12 scaffold unless a later approved decision changes them.

## 2. Version and Dependency Rules

- Install supported patch releases within the approved major/minor lines.
- Keep Composer on a release that resolves known high-severity advisories affecting the installed version.
- Do not replace Laravel 12 with Laravel 13 or another framework major version.
- Do not substitute MariaDB, SQLite, PostgreSQL, or another database for the required MySQL runtime.
- Do not add dependencies beyond the Laravel scaffold during Phase 0 unless they are necessary for boot, migration, testing, formatting, static analysis, or the approved frontend build.
- Do not upgrade unrelated global software as part of DRMS work.

## 3. Architecture and Scope Constraints

- Preserve exactly two authorization levels: Level 1 and Level 2.
- Keep database and domain terminology generic for district, division, and regional deployment profiles.
- Resolve deployment-specific labels through validated configuration, settings, or localization.
- Keep business rules outside presentation components when practical.
- Centralize workflow transitions and controlled values.
- Implement only the current approved phase.

## 4. Security and Data Constraints

- Enforce authentication, authorization, and organizational-unit isolation on the server.
- Use database transactions for multi-record workflow operations.
- Do not expose secrets, credentials, tokens, or private data in source control or logs.
- Do not commit `.env` or other secret-bearing local configuration.
- Use secure random, non-sequential receiving-box tokens.
- Keep transaction and correction history auditable and protected from ordinary-user deletion.
- Use a dedicated local development/test database and credentials; do not reuse production data or credentials.

## 5. Development and Validation Constraints

- The application must boot before Phase 0 can be approved.
- Clean migrations must succeed against the approved database.
- The baseline automated test suite must pass.
- The production frontend build must pass.
- Formatting and static analysis must run when configured.
- Previous-phase regression tests must remain green before a later phase is approved.
- Do not mark a task complete when required validation is unavailable or failing.

## 6. Hosting and Deployment Boundaries

- Phase 0 covers local development setup only.
- Production hosting, domain, infrastructure sizing, email delivery, queue topology, backup storage, and deployment credentials remain undecided.
- Do not deploy publicly or provision production infrastructure without separate approval.

