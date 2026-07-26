# Phase 12 UAT Defect Log

## UAT-001 — Level 2 Cannot Define the Origin of a Level 1 Record

| Field | Record |
|---|---|
| Severity | High |
| Status | Resolved and regression-tested |
| Affected scenario | Phase 12 scenario 2 |
| Requirement | Level 2 reviews a Level 1-created record and defines its official origin |
| Detected | 2026-07-26 |

### Reproduction

1. Sign in as a Level 1 user assigned to an organizational unit.
2. Create and submit an organizational-unit-originated record.
3. Sign in as Level 2.
4. Open the record detail page.
5. Attempt to define the official document origin.

### Expected Result

Level 2 can select one active official origin and optionally record the origin reference number. The change is authorized on the server and recorded in immutable activity history.

### Actual Result

The record detail page displayed no action or form for defining the missing origin. The origin remained blank.

### Evidence

The issue was reproduced on `DRMS-DISTRICT-2026-000002`. Before the fix, the Level 2 detail page showed routing and cancellation actions but no origin-classification action.

### Root Cause

Registration correctly prevented Level 1 from selecting an official origin, but no later Level 2 service, policy method, or interface action existed to complete the classification workflow.

### Resolution

- Added a Level 2-only origin-classification policy and service.
- Added a **Define origin** action to the document detail page.
- Restricted choices to active origins.
- Made classification single-use for an unclassified document.
- Protected origin fields from direct model updates.
- Wrote a dedicated `document.origin_classified` event atomically with the data change.
- Preserved rollback if the audit write fails.

### Regression Evidence

`tests/Feature/PhaseTwelve/OriginClassificationUatTest.php` verifies:

- successful Level 2 classification and audit creation;
- Level 1 denial;
- inactive-origin rejection;
- repeated-classification rejection;
- direct model-mutation rejection; and
- atomic rollback on audit failure.

The focused origin-classification regression suite passed 3 tests with 8 assertions. The complete suite passed 129 tests with 633 assertions. Browser validation confirmed the stored origin, stored reference number, hidden repeat action, success notification, and visible `Document Origin Classified` activity event.

## Defect Summary

| Severity | Open | Resolved |
|---|---:|---:|
| Critical | 0 | 0 |
| High | 0 | 1 |
| Medium | 0 | 0 |
| Low | 0 | 0 |

No unresolved defect is being hidden or deferred.
