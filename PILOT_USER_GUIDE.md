# DRMS Pilot User Guide

DRMS has two authorization levels. Never share an account, password, authenticated browser session, report export, or receiving-box QR token outside the approved work process.

## 1. Sign In

Open the approved HTTPS DRMS address and sign in with your email address or username. Inactive accounts are denied. Sign out when leaving a shared device.

## 2. Level 1 — Organizational-Unit Encoder

Level 1 users work only with records related to their assigned unit.

### Register and submit a document

1. Open **Documents** and choose **New document**.
2. Enter the required type, subject, origin, submitting unit, priority, and dates.
3. Save the draft and review the generated tracking number.
4. Submit only when the record is complete. Status and location change through approved actions, not free-form editing.

### Search and monitor

Use document search and approved filters. Cancelled records remain searchable when your unit is authorized. Dashboard and report counts are scoped to your unit.

### Claim from a receiving box

1. Scan the permanent QR label for your own unit.
2. Sign in if prompted.
3. Verify the box name and ready-for-pickup list.
4. Select the document recipients actually received.
5. Enter the receiver's name and position/designation; add remarks when needed.
6. Confirm once and wait for the success message.

The QR token identifies a box but does not grant access. A Level 1 user cannot view or claim another unit's box. Report an invalid label, unexpected unit, missing document, or repeated-claim message to a Level 2 administrator.

## 3. Level 2 — Records Administrator

Level 2 users manage configuration, units, users, reference data, documents, recipients, routing, receiving boxes, reports, and activity review.

- Use only the action valid for the document's current state.
- Assign recipients before downstream activity; do not try to remove a recipient after routing or claim history exists.
- Place a recipient only in the active box owned by that recipient's unit.
- Regenerate a QR token only for a recorded security or label-replacement reason; the old token becomes invalid.
- Review **Activity Audit** for login, administration, document, and report/export activity. Review each document's transaction history for custody and claims.
- Use corrections or cancellation actions so history remains traceable.

## 4. Notifications and Reports

Database notifications appear in the top bar when Level 2 places a document in the Level 1 user's receiving box and makes it ready for pickup. Opening or marking a notification as read keeps it in the list; only the owning user may explicitly delete it. Reports and CSV exports use the same authorization scope as the screen. CSV files may contain operationally sensitive data; store and share them only through approved channels.

## 5. Report an Issue

Record the time, tracking number when applicable, action attempted, expected result, actual message, and a screenshot that does not reveal passwords or secrets. Do not repeatedly retry a claim, change data to hide a defect, or send credentials with a support report.
