# MarketHub Implementation Plan
Goal: persistent login -> map -> tenant -> assignment -> billing -> payment -> updated balance, followed by administration and reports.

Architecture: Blade monolith; Form Requests and policies protect controllers; transaction services own occupancy and financial invariants. Normalized Eloquent models use MySQL. Exact decimal strings and append-only reversals preserve accounting history.

Stack: PHP 7.4.30 compatibility, Laravel 8.83, Tailwind 3, Spatie Permission 5, PHPUnit 9.6. Spec: docs/MASTER_SPEC.md. Execution is inline in the explicitly requested empty workspace. No Git repository/worktree exists. User directed immediate autonomous implementation.

## Tasks and checks
- [x] Foundation/auth: locked dependencies, session login/logout, six roles, audit. tests/Feature/WorkflowTest.php asserts guest redirect, login, disabled-user denial, logout, viewer mutation denial.
- [x] Administration: migrations/models, RecordController/Form Request, Blade forms, persistent map. Test CRUD, scope validation, archive safety, PII restrictions and map position persistence.
- [x] AssignmentService::assign/end/transfer: lock stalls and tenants, preserve history, prevent overlapping assignments. Test overlap, dates, inactive stalls and business ownership.
- [x] BillingService::generate/post: agreement/rate snapshot, unique assignment+period, draft preview and explicit posting. Test duplicates, historical rates and scheduler draft-only behavior.
- [x] PaymentService::createDraft/post/reverse/reconcile: exact allocations, idempotency, append-only reversal. Test partial/full payments, cross-tenant bills, overpayment rejection and repeated requests.
- [x] Dashboard/report queries and CSV/XLSX/PDF: transaction-derived totals, filters, pagination and permissions. Test reconciliation and spreadsheet formula injection.
- [x] Development seed, Apache public-only configuration, operating docs and handoff. Compile assets, lint, full suite, independent review.

## Review focus
Cross-tenant allocation, simultaneous writes, effective-date boundaries, report PII and reversal totals. SQLite does not verify MySQL locking; run separate MySQL integration/concurrency checks when credentials are available.

## Pending LGU policy
Due dates, proration, penalties, credits, backdating, reconciliation and multi-stall limits need approval. Require explicit dates; do not auto-post; reject unsupported credits/proration.

## Checkpoint
Implemented application tasks above passed isolated verification on 2026-11-17. See VERIFICATION.md for exact evidence. MySQL deployment/concurrency, actual PHP 7.4 runtime and LGU policy acceptance remain open; checked tasks do not certify those deployment gates.
