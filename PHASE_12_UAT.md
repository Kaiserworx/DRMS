# Phase 12 User Acceptance Test Evidence

## 1. Scope and Result

This record maps the 17 Phase 12 scenarios in the source specification to verified application evidence. Technical execution is complete. Records-office acceptance sign-off and pilot preparation remain open.

**Technical result:** 17 of 17 scenarios passed.

**Acceptance result:** Pending records-office sign-off.

**Release recommendation:** `NOT READY FOR PILOT RELEASE`

The release recommendation is not caused by a failed workflow. It is caused by incomplete official pilot data, final-domain QR labels, named support contacts, and acceptance sign-off. See [PILOT_RELEASE_READINESS.md](PILOT_RELEASE_READINESS.md).

## 2. Test Environment

- Local Laravel application at `http://127.0.0.1:8000`
- MySQL 8.4 application database
- Synthetic development users, organizational units, records, receiving box, and QR token
- Automated result: `php artisan test --compact` — 129 tests passed with 633 assertions
- Frontend result: `npm run build` — Vite production build passed
- Formatting result: `php vendor/bin/pint --test` — recorded after the Phase 12 changes

Synthetic development data is evidence of technical behavior only. It is not approved pilot data.

## 3. Scenario Evidence

| # | Required scenario | Result | Evidence |
|---|---|---|---|
| 1 | Level 1 creates an organizational-unit-originated record. | Passed | `DocumentRegistrationTest::test_level_one_registration_derives_own_unit_and_forbids_origin`; previously approved browser registration evidence |
| 2 | Level 2 reviews and defines the origin. | Passed | `OriginClassificationUatTest`; browser classification of `DRMS-DISTRICT-2026-000002`; append-only `Document Origin Classified` activity event |
| 3 | Level 2 receives and forwards a document to Upstream Office. | Passed | `RoutingEngineTest::test_complete_managing_and_upstream_route_records_exactly_one_transaction_per_action` |
| 4 | Level 2 records return from Upstream Office. | Passed | `RoutingEngineTest::test_complete_managing_and_upstream_route_records_exactly_one_transaction_per_action` |
| 5 | Level 2 creates an Upstream Office-originated appointment record. | Passed | `WorkflowAcceptanceUatTest::test_level_two_creates_upstream_appointment_and_multi_unit_book_delivery_records` |
| 6 | Level 2 creates a multi-unit book-delivery record. | Passed | Same dedicated Phase 12 test plus `RecipientAssignmentTest` |
| 7 | Level 2 assigns recipient organizational units. | Passed | `RecipientAssignmentTest`; `RecipientRoutingAuditTest` |
| 8 | Level 2 places recipients in receiving boxes. | Passed | `ReceivingBoxPlacementTest`, including multi-recipient placement and atomic rollback |
| 9 | Correct Level 1 user scans the receiving-box QR code assigned to their organizational unit. | Passed | Previously approved Phase 7 browser claim; `ReceivingBoxClaimInventoryTest::test_correct_unit_sees_claim_form_other_unit_is_forbidden_and_anonymous_is_redirected` |
| 10 | Wrong-organizational-unit user is denied. | Passed | `ReceivingBoxClaimTest::test_cross_unit_manipulation_and_level_two_claims_are_forbidden`; inventory access test |
| 11 | The recipient unit confirms one document. | Passed | `ReceivingBoxClaimTest::test_single_claim_derives_identity_and_records_complete_audit_context` |
| 12 | The recipient unit confirms multiple documents. | Passed | `ReceivingBoxClaimTest::test_one_submission_claims_multiple_documents_atomically` |
| 13 | A multi-unit document status becomes partially claimed. | Passed | `ReceivingBoxClaimTest::test_document_is_partial_until_every_recipient_unit_finishes_then_completed` |
| 14 | Final recipient claim completes the document. | Passed | Same multi-unit aggregate-state test; direct claim mutation and rollback coverage |
| 15 | Both roles search for authorized records. | Passed | `DocumentSearchTest`, including Level 1 scope, Level 2 cancelled-record access, combined filters, date ranges, and leakage denial |
| 16 | Managing Office generates pending-pickup and completed reports. | Passed | `WorkflowAcceptanceUatTest::test_managing_office_generates_pending_pickup_and_completed_reports`; approved Phase 10 reports UI |
| 17 | Cancellation remains visible in audit history. | Passed | `WorkflowAcceptanceUatTest::test_cancellation_remains_in_the_append_only_document_history`; document timeline and Phase 11 immutability coverage |

## 4. Phase 12 Defect Regression

UAT exposed one high-severity defect: Level 2 had no supported workflow for defining the official origin of an unclassified Level 1 record.

The resolved path is:

1. Level 2 opens an unclassified document.
2. Level 2 selects **Define origin**.
3. Only an active origin can be selected.
4. The origin and optional reference number are stored once in a transaction.
5. An immutable `document.origin_classified` activity event is written in the same transaction.
6. Level 1, repeated classification, inactive-origin selection, and direct model mutation are rejected.
7. If the audit write fails, the origin change is rolled back.

See [UAT_DEFECT_LOG.md](UAT_DEFECT_LOG.md) for the complete defect record.

## 5. Sign-off Checklist

- [x] All 17 technical scenarios have traceable evidence.
- [x] The complete automated regression suite passes.
- [x] The production frontend build passes.
- [x] No unresolved critical defect is known.
- [x] No unresolved high-severity defect is known.
- [x] No medium-severity defect requires disposition.
- [ ] Official pilot users and organizational units are approved and loaded.
- [ ] Pilot receiving boxes and locations are approved.
- [ ] Final-domain QR labels are generated and mobile-tested.
- [ ] Named deployment, security, database recovery, DNS, and client-support contacts are recorded.
- [ ] Records-office acceptance owner signs this UAT record.
- [ ] Release owner approves the pilot release.

## 6. Acceptance Signatures

| Responsibility | Name | Decision | Date |
|---|---|---|---|
| Records-office acceptance owner | Not provided | Pending | — |
| Pilot organizational-unit representative | Not provided | Pending | — |
| Technical release owner | Not provided | Pending | — |

Until the open checklist items and signatures are complete, this UAT record does not authorize a pilot deployment.
