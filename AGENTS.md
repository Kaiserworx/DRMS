# AGENTS.md

## 1. Mission

Build the MVP defined by this project accurately, incrementally, and safely.

Prioritize:

1. Correctness
2. Alignment with documented requirements
3. Working and tested functionality
4. Maintainability
5. Simplicity

Do not prioritize speed over correctness.

Do not add features, abstractions, integrations, or architectural changes that are not required for the current MVP.

---

## 2. Governing Project Files

The following markdown files govern all project work:

* `AGENTS.md`
* `PRD.md`
* `PLAN.md`
* `TASKS.md`
* `CONSTRAINTS.md`

Each file has a distinct purpose.

### `AGENTS.md`

Defines how the coding agent must behave, investigate, implement, validate, and report work.

### `PRD.md`

Defines the product requirements, including:

* Product purpose
* Target users
* User roles
* Required workflows
* Functional requirements
* Expected behavior
* Success criteria
* MVP scope
* Out-of-scope features

### `PLAN.md`

Defines the approved technical approach and implementation sequence.

It may contain:

* Architecture decisions
* Development phases
* Dependencies between phases
* Validation gates
* Technical milestones

### `TASKS.md`

Defines the current execution state of the project.

It should identify:

* Completed tasks
* Current tasks
* Blocked tasks
* Pending tasks
* Validation status
* Known issues

### `CONSTRAINTS.md`

Defines non-negotiable project limits and rules, including:

* Required technology stack
* Prohibited technologies
* Hosting limitations
* Security requirements
* Data-handling requirements
* Compatibility requirements
* Budget or infrastructure limits
* Required conventions

---

## 3. Required Reading Before Work

### For every coding, debugging, refactoring, database, configuration, or deployment task

Before changing any file, read:

1. `AGENTS.md`
2. `PRD.md`
3. `CONSTRAINTS.md`
4. `PLAN.md`
5. `TASKS.md`

After reading the project documents, inspect the relevant:

* Source files
* Tests
* Configuration files
* Database schema or migrations
* Routes
* Dependencies
* Existing implementations

Do not begin implementation based only on the user's latest prompt when the project files contain relevant context.

### For documentation-only work

Read:

* `AGENTS.md`
* The document being changed
* Any other governing file affected by that change

---

## 4. Authority and Conflict Resolution

`AGENTS.md` governs agent behavior but does not define product features.

The project files have the following functional authority:

1. `CONSTRAINTS.md` defines non-negotiable limits.
2. `PRD.md` defines what the product must do.
3. `PLAN.md` defines how and in what sequence it will be built.
4. `TASKS.md` defines the current execution state.
5. Existing code shows the current implementation but is not automatically the intended requirement.

The following rules apply:

* `PLAN.md` must not introduce features outside `PRD.md`.
* `TASKS.md` must not introduce features outside `PRD.md` or `PLAN.md`.
* Existing code must not be treated as proof of the intended requirement.
* Comments, function names, and unfinished implementations are not authoritative requirements.
* A newer implementation must not silently override documented requirements.

When two sources conflict:

1. Do not guess which one is correct.
2. Identify the exact conflicting statements.
3. Explain the implementation impact.
4. Stop the affected work until the conflict is resolved.

Do not silently choose the interpretation that is easiest to implement.

A direct user instruction that conflicts with a governing project file must be surfaced as a requirement change. Do not silently modify the implementation or documentation to hide the conflict.

---

## 5. Anti-Hallucination Rules

Never invent or assume the existence of:

* Files
* Folders
* Classes
* Functions
* Components
* Routes
* API endpoints
* Environment variables
* Database tables
* Database columns
* Relationships
* User roles
* Permissions
* Dependencies
* Configuration values
* Test coverage
* External service behavior
* Framework features

Verify repository facts by inspecting the actual project.

Before referring to a file, symbol, route, schema field, dependency, or configuration value, confirm that it exists.

Do not claim that:

* A bug is fixed unless the relevant behavior was validated.
* A test passed unless that test was actually run.
* A build succeeded unless the build command was actually run.
* A feature is complete unless its acceptance criteria were verified.
* A file was updated unless it was actually changed.
* An external API supports something unless verified against reliable documentation for the version being used.

Clearly distinguish between:

* Verified facts
* Documented requirements
* Reasonable technical conclusions
* Unverified assumptions
* Open questions

Do not convert assumptions into implementation without verification when the assumption affects:

* Business rules
* User permissions
* Data structure
* Security
* Workflow
* Calculations
* Required outputs
* MVP scope

If required information is missing, first:

1. Search the repository.
2. Review the governing markdown files.
3. Inspect related code and tests.
4. Review official documentation when an external technology is involved.

If the answer remains unknown, report the missing information instead of fabricating it.

---

## 6. Task Intake and Validation Before Coding

Before implementing a task, determine and record internally:

* The requested outcome
* Relevant requirements from `PRD.md`
* Relevant constraints from `CONSTRAINTS.md`
* The applicable phase in `PLAN.md`
* The corresponding item in `TASKS.md`
* Acceptance criteria
* Files and systems likely to be affected
* Regression risks
* Required validation commands

Do not begin coding when the task:

* Has no clear expected behavior.
* Conflicts with documented requirements.
* Depends on an undefined business rule.
* Requires a prohibited technology.
* Belongs to a later phase whose prerequisites are incomplete.
* Would violate a documented constraint.

For small tasks, this analysis may be brief, but it must still occur.

---

## 7. MVP Scope Control

Keep all work aligned with the current MVP.

Do not add:

* Optional convenience features
* Unrequested dashboards
* Additional user roles
* Future-phase integrations
* Premature abstractions
* Generic framework layers
* Unrequested notifications
* Unrequested analytics
* Unrequested configuration systems
* Hypothetical scalability features

Do not expand the task because an enhancement appears useful.

When discovering a potential enhancement:

1. Do not include it in the current implementation.
2. Record or report it separately as a future consideration.
3. Continue only with the documented MVP requirement.

Do not fix unrelated issues unless they prevent the requested task from working safely.

Report unrelated defects separately.

---

## 8. Phase-by-Phase Execution

Follow the sequence defined in `PLAN.md`.

Do not begin a later phase until:

* Required tasks in the current phase are complete.
* Current-phase acceptance criteria are satisfied.
* Required tests pass.
* Known blocking defects are resolved.
* `TASKS.md` accurately reflects the current state.

Each phase must follow this cycle:

1. Read the phase requirements.
2. Inspect the existing implementation.
3. Confirm prerequisites.
4. Define acceptance criteria.
5. Implement the smallest complete unit.
6. Run targeted validation.
7. Run applicable regression checks.
8. Correct failures.
9. Update execution documentation.
10. Proceed only after the phase gate passes.

A partially working phase must not be marked complete.

---

## 9. Core Engineering Rules

* Prefer simple, explicit solutions.
* Avoid spaghetti code.
* Avoid clever shortcuts that reduce readability.
* Do not introduce unnecessary abstractions.
* Do not overengineer for hypothetical future requirements.
* Keep responsibilities separated.
* Keep business logic outside presentation components when practical.
* Avoid duplicate logic.
* Avoid hidden side effects.
* Preserve existing working functionality.
* Fix root causes rather than masking symptoms.
* Implement the smallest correct change at the appropriate layer.
* Follow the conventions already established by the project unless they conflict with governing documentation.
* Avoid broad rewrites when a focused change is sufficient.

---

## 10. File and Function Standards

Use clear and domain-appropriate naming.

A file should have a clear primary responsibility.

Split a file when:

* It contains unrelated responsibilities.
* Its size makes navigation or testing difficult.
* Business logic and presentation logic are mixed.
* Reusable logic is duplicated.
* Separate units need independent testing.

Keep functions focused and understandable.

Extract helpers only when doing so:

* Removes meaningful duplication.
* Improves readability.
* Improves testability.
* Separates a distinct responsibility.

Do not split files or functions merely to satisfy an arbitrary line count.

---

## 11. Dependency and Technology Rules

Do not change the approved:

* Framework
* Programming language
* Database
* Authentication approach
* Authorization approach
* Package manager
* Build system
* Hosting model
* Deployment strategy

unless the change is explicitly required by the governing documents or approved by the user.

Before adding a dependency:

1. Confirm the requirement cannot be implemented reasonably with the existing stack.
2. Verify compatibility with the installed framework and runtime versions.
3. Review security and maintenance implications.
4. Explain why the dependency is necessary.
5. Add only the minimum required package.

Do not use an external package to avoid understanding or fixing the existing implementation.

Do not upgrade unrelated dependencies as part of a feature or bug fix.

---

## 12. Data and Database Safety

Before changing the database:

* Inspect the existing schema and migrations.
* Confirm the required fields and relationships from the project documents.
* Check how existing code reads and writes the affected data.
* Identify migration and rollback risks.

Do not:

* Invent columns or relationships.
* Rename or remove data fields without confirming all usages.
* Delete production data.
* Reset or reseed existing data without explicit authorization.
* Use destructive migrations when a safe migration is possible.
* Change identifiers, permissions, or ownership rules without verifying their impact.

Database migrations must be reversible when reasonably possible.

Validate:

* Required fields
* Unique constraints
* Foreign keys
* Default values
* Nullability
* Indexes
* Authorization boundaries

Do not use mock or fallback data to hide a broken database or integration.

---

## 13. Security and Authorization

Treat authentication, authorization, and data access as core requirements.

For any protected feature, verify:

* Who can view the data
* Who can create records
* Who can update records
* Who can delete records
* Who can confirm or approve actions
* Whether access is limited by school, district, division, office, or role

Do not infer permissions from UI visibility alone.

Enforce authorization on the server or trusted application layer.

Do not rely only on hidden buttons or frontend checks.

Never expose:

* Secrets
* Passwords
* Tokens
* Private keys
* Sensitive environment values
* Unauthorized records
* Personally identifiable information beyond documented requirements

Do not weaken security to make a feature work.

---

## 14. Bug-Fixing Rules

Before modifying code for a bug:

1. Reproduce or clearly identify the failure.
2. Inspect the relevant execution path.
3. Identify the root cause.
4. Review related requirements and constraints.
5. Determine regression risk.
6. Implement the smallest correct fix.
7. Validate the original failing scenario.
8. Run related regression checks.

Do not:

* Patch only the visible symptom.
* Catch and ignore errors without justification.
* Add arbitrary delays.
* Disable validation.
* Remove authorization checks.
* Hard-code values that should come from configuration or data.
* Return fake success responses.
* Replace a failing integration with silent fallback behavior.

When the root cause cannot be confirmed, report the evidence and remaining uncertainty.

---

## 15. Refactoring Rules

Refactor only when there is a clear reason tied to:

* Correctness
* Maintainability
* Testability
* Removal of meaningful duplication
* Separation of mixed responsibilities
* Safe implementation of a documented requirement

Preserve behavior unless the requirement explicitly changes it.

Do not combine large refactors with feature work unless the refactor is necessary for the feature.

Prefer incremental improvements over disruptive rewrites.

After refactoring, run tests that demonstrate preserved behavior.

---

## 16. Testing and Validation Requirements

Validation is required before declaring work complete.

Use the validation methods supported by the project, such as:

* Automated tests
* Unit tests
* Feature tests
* Integration tests
* Authorization tests
* Validation tests
* Linting
* Static analysis
* Type checking
* Build checks
* Database migration checks
* Manual workflow verification

Run the most targeted checks first, followed by broader checks when practical.

For every changed behavior, validate:

* The expected successful path
* Invalid input
* Unauthorized access
* Relevant edge cases
* Existing behavior that could regress

Do not remove, skip, weaken, or rewrite a failing test merely to obtain a passing result unless the documented requirement changed and the old test is proven outdated.

When a validation command cannot be run:

* State which command was not run.
* Explain why it could not be run.
* Do not claim the related behavior is verified.

A test command that exits successfully is evidence only for what that command actually covers.

---

## 17. Definition of Done

A task is complete only when all applicable conditions are satisfied:

* The implementation matches `PRD.md`.
* The implementation respects `CONSTRAINTS.md`.
* The work follows the applicable phase in `PLAN.md`.
* The corresponding status in `TASKS.md` is accurate.
* Acceptance criteria are satisfied.
* Relevant tests pass.
* The application builds successfully when applicable.
* Authorization and validation are enforced.
* No known regression was introduced.
* No unrelated scope was added.
* Relevant documentation was updated.
* Remaining limitations or unverified items were disclosed.

“Code written” does not mean “task complete.”

“Works on the happy path” does not mean “feature complete.”

Do not mark a task or phase complete when required validation is pending.

---

## 18. Documentation Update Rules

Keep implementation and documentation synchronized.

Update `TASKS.md` when:

* A task starts.
* A task becomes blocked.
* A task is completed and validated.
* A discovered issue affects execution.
* Validation fails and requires follow-up.

Update `PLAN.md` only when:

* The approved implementation sequence changes.
* A technical decision changes.
* A new prerequisite or phase dependency is confirmed.
* The user approves a change to the implementation approach.

Update `PRD.md` only when:

* The user changes a product requirement.
* The user changes MVP scope.
* A product decision is explicitly clarified.

Do not change `PRD.md` merely to make it match an incorrect implementation.

Update `CONSTRAINTS.md` only when:

* The user explicitly changes a project constraint.
* A constraint is clarified and approved.

Do not weaken or remove a constraint to make implementation easier.

When documentation appears outdated, surface the discrepancy before changing product meaning.

---

## 19. Task Status Rules

Use clear task states in `TASKS.md`:

* `Pending`
* `In Progress`
* `Blocked`
* `Completed`
* `Deferred`

A task may be marked `Completed` only after its acceptance criteria and required validations pass.

When a task is blocked, record:

* The blocker
* The affected requirement
* Evidence discovered
* What is needed to continue
* Whether other independent work may proceed

Do not mark incomplete work as completed because most of it is working.

---

## 20. Reporting Rules

After completing work, report only verified information.

Include:

* What was changed
* Why it was changed
* Files affected
* Validation performed
* Validation results
* Documentation updated
* Known limitations
* Remaining risks or blockers

Do not describe intended changes as completed changes.

Do not hide failed commands, incomplete tests, or unresolved issues.

Use exact language:

* “Implemented” only when the code was changed.
* “Verified” only when validation was performed.
* “Not verified” when validation was not possible.
* “Partially implemented” when any required behavior remains incomplete.
* “Blocked” when required information or prerequisites are unavailable.

---

## 21. Stop Conditions

Stop the affected implementation and report the issue when:

* Governing documents conflict.
* A required business rule is undefined.
* A required file or dependency is missing.
* The requested work violates `CONSTRAINTS.md`.
* The task belongs to a phase with incomplete prerequisites.
* A destructive action lacks explicit authorization.
* Required credentials or external access are unavailable.
* The change would require inventing product behavior.
* Validation reveals a blocking regression.
* Continuing would risk data loss, security failure, or unauthorized access.

Do not bypass a stop condition with placeholder logic, fabricated data, fake responses, or silent assumptions.

Independent work that is not affected by the blocker may continue when it remains within the documented scope.

---

## 22. When Unsure

When uncertain:

1. Read the five governing markdown files.
2. Search the repository.
3. Inspect relevant code, tests, schema, and configuration.
4. Check exact installed versions.
5. Consult official documentation when needed.
6. Separate verified facts from assumptions.
7. Report unresolved ambiguity.

Do not invent requirements.

Do not guess hidden business rules.

Do not silently choose between conflicting interpretations.

Accuracy is more important than appearing complete.
