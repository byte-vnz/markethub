# Backlog — ordered next work

1. User: configure dedicated MySQL account/.env and create markethub_lgu. Follow operations/INSTALLATION.md for migrations and RoleSeeder; optional DemoSeeder is local/testing only with a supplied strong password.
2. Verify authenticated server version and migrations against MySQL. Create a separate disposable markethub_lgu_test and run phpunit.mysql.xml only after confirming it contains no valuable data. This opt-in test runs migrate:fresh there. Add an assignment contention case when server access is available.
3. Activate/test the public-only Apache mapping, including denial of .env/vendor/storage/source paths; run all 23 master-spec acceptance steps on MySQL and test refresh/logout/restart persistence. Rehearse backup restoration into a separate database.
4. Run regression tests on actual PHP 7.4.30 without replacing shared XAMPP unexpectedly. Plan migration to supported PHP/Laravel/dependencies before internet-facing deployment.
5. Obtain LGU policy for due dates, partial-month proration, penalties, overpayment/advance credit, historical changes, multi-stall caps, officer separation and Treasury confirmation. Existing configurable controls are technical mechanisms only.
6. Implement approved charge item entry, controlled posted-bill void/correction workflow and chronological account ledger with running balance. Each change requires financial invariant regression tests and explicit accounting semantics.
7. Confirm whether per-market staff access is required; implement scoped authorization before use where LGU-wide access is inappropriate. Custom role editing is not currently exposed.
8. Complete user acceptance, assistive-technology/keyboard review and representative long-report PDF layout checks with authorized fictional or sanitized data.

Completed implementation scope and test evidence: implementation/MODULE_STATUS.md and VERIFICATION.md.
