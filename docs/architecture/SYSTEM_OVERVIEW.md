# System overview

Blade monolith: routes/web.php -> authentication/active-account middleware -> controller/Form Request/policy -> service -> Eloquent/MySQL. Vanilla JS fetches server-rendered, escaped stall details and confirms consequential forms using local SweetAlert2. Chart.js displays query-derived aggregates. Tailwind CLI generates public/assets/app.css; no CDN runtime is required.

Resources.php describes bounded administrative forms. RecordController validates scope relationships and archives through statuses, retaining linked history. AssignmentService locks tenant then stall, checks dates/rate scope, and uses unique nullable active_stall_id to prevent double occupancy. Transfers lock both stalls in ID order and preserve separate agreements.

BillingService snapshots agreement monthly_amount, enforces assignment+period uniqueness and full-month coverage, generates drafts, explicitly posts and appends authorized adjustments. Adjustments have unique submission IDs and payload hashes. Posted bills are immutable.

PaymentService serializes by tenant, then payment/bills, with database transactions and three deadlock retries. Drafts reserve a unique active receipt reference; posting revalidates outstanding balances. Reversals retain original amounts and release the receipt reservation. Linked corrections may reuse their own receipt history or an unused corrected reference, never unrelated history. Draft voiding changes no balance. Database unique constraints backstop application checks. No SQL locking claim is inferred from SQLite tests.

Ledger and Reports derive balances from posted bills + append-only adjustments - allocations on posted, unreversed payments. Draft/void payments never count. Collected includes pending Treasury reconciliation; confirmed totals remain separately available. Reports filtered by market use allocated amounts for collection aggregates, avoiding multiplication of multi-bill payments. Tenant statements summarize posted bill balances; detailed payment events remain in the tenant/payment screens.

Audit records contain responsible account, action, record identity, time, IP and correction reason, with a small allowlist of nonsecret values/changed field names. Staff management uses predefined roles, protects the last active Super Administrator, hashes passwords and invalidates existing password-hash sessions after reset. This is LGU-wide authorization; per-market staff scoping is not implemented.

See AUTHORIZATION_MATRIX.md, DATABASE_ERD.md, DECISIONS.md and operations docs for policy/runtime boundaries.
