# Module 20 — Payments

- **Status:** ⬜ Not started
- **Phase:** Phase 4 — Finance
- **Completed on:** —
- **Owner agent:** `module-builder`

> Records fee payments against invoices inside a single atomic DB transaction — create payment, advance the invoice's paid amount, recompute due, issue a receipt, and write an audit log — with rollback on any failure, idempotency against double-submit, and audited refunds.

## Scope

Includes (spec §18, §55): payment capture (full or partial) against a `fee_invoice`, payment-method catalogue (cash/bank/bKash/Nagad/Rocket/online), receipt generation, payment history, and refunds. The write path is one DB transaction (spec §55): create payment → update invoice `paid` → recompute `due` and `status` → generate `receipt_no` → write audit log; any failed step rolls back the whole operation. An idempotency key prevents a double-submitted request from double-charging.

Excludes: invoice generation and fee structures (Module 19); income/expense ledger aggregation (Module 21 reads these payments as income); live online-gateway integration (V2, spec §66). The paid `amount` is always recomputed/validated against the invoice server-side — never trusted from the client (spec §51).

## Dependencies

`19 — Fees Management` (payments settle `fee_invoices` and their line items).

## Database (tables owned / referenced)

- `payments` — id, invoice_id (FK), student_id (FK), amount `decimal(15,2)`, payment_method_id (FK), payment_date, receipt_no (unique), idempotency_key (unique), received_by (FK users), status enum (completed/refunded), notes, timestamps, soft deletes. Indexes: composite (student_id, payment_date) per spec §54; unique receipt_no; unique idempotency_key.
- `payment_methods` — id, name, code enum (cash/bank/bkash/nagad/rocket/online), is_active, timestamps.
- References: `fee_invoices` (updates paid/due/status), `students`, `users`, and the audit log table (spec §43).

## Permissions

`fee.payment` (record a payment), `fee.refund` (reverse one). Two-layer authorization (spec §52): permission middleware plus a policy — e.g. only accountant/admin roles post payments; a refund additionally requires the payment to belong to a settleable invoice.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /payments | fee.view | paginated; filter student/date/method |
| POST | /payments | fee.payment | idempotency-key header; transactional |
| GET | /payments/{id} | fee.view | policy-scoped |
| GET | /payments/{id}/receipt | fee.view | receipt (PDF/printable) |
| GET | /invoices/{id}/payments | fee.view | payment history for an invoice |
| POST | /payments/{id}/refund | fee.refund | transactional reversal; audited |
| GET | /payment-methods | fee.view | active methods |

## Backend tasks

- [ ] Migrations + models (FKs, unique receipt_no + idempotency_key, composite (student_id, payment_date) index, soft deletes)
- [ ] Form Requests (validation + `authorize()`); reject amount > outstanding due; recompute against invoice
- [ ] Policies: who may post/refund; invoice-state guards
- [ ] `RecordPaymentAction` wrapped in `DB::transaction` — payment → invoice paid/due/status → receipt_no → audit log; rollback on any exception (spec §55)
- [ ] `RefundPaymentAction` (transactional): reverse invoice paid/due, mark payment refunded, write audit log
- [ ] Idempotency handling (unique key) so a replay returns the original payment, not a new one
- [ ] Receipt generation (queued for PDF, spec §61); API Resources; routes under `/api` with JWT + `permission:` middleware

## Frontend tasks

- [ ] Admin `admin/fees/` payment form (method, amount ≤ due), history, receipt view/print; send an idempotency key per submit and disable the button while pending
- [ ] Typed `paymentService` in `services/` (record, list, receipt, refund, methods)
- [ ] Parent/student read-only payment history + receipts scoped to own records

## Acceptance criteria

- [ ] A payment updates the invoice `paid` and `due` atomically
- [ ] A failure in any step rolls back the entire operation (no orphan payment or half-updated invoice)
- [ ] A double-submitted request (same idempotency key) does not double-pay
- [ ] A receipt with a unique `receipt_no` is generated per payment
- [ ] A refund reverses the invoice amounts correctly and writes an audit log entry
- [ ] The paid amount is recomputed/validated against the invoice, never taken as trusted from the client
- [ ] Tests written and passing (atomicity/rollback, idempotency, partial payment, refund, audit entry)
- [ ] Security review passed (transaction safety, audit logging, no float money, two-layer auth)

## Notes

Every payment and refund writes an audit log entry (spec §43) — user, action, record, old/new value, IP, timestamp. Amount is validated against the invoice's outstanding due; over-payment is rejected. Partial payments set invoice status `partial`; a fully-settled invoice becomes `paid`. Online-gateway capture is V2 (spec §66).
