# Module status — 2026-11-17

“Implemented/tested” below means source plus isolated SQLite tests; it does not imply MySQL deployment acceptance.

| Module | Status and scope |
| --- | --- |
| Foundation/auth | Implemented/tested: session authentication, throttling, six roles, policy enforcement, disabled accounts, password-change session invalidation. |
| Markets/stalls/map | Implemented/tested: CRUD/status archival, scope validation, layout grid position/dimensions, details, history. Desktop/tablet browser QA completed. |
| Tenants/businesses | Implemented/tested: searchable records, related profiles and permissions guarding PII. |
| Assignments | Implemented/tested: assign, end, transfer, renewal/history and occupancy uniqueness. Real MySQL contention pending. |
| Rental rates/billing | Implemented/tested: effective rates, agreement snapshot, monthly draft, explicit post, adjustments, balances, draft-only command. Partial months/penalties/advance credit await policy. |
| Payments | Implemented/tested: exact allocations, draft review/post/void, idempotency, reversal/correction lineage, Treasury confirmation. SQLite stale-draft checks pass; real parallel MySQL worker test provided but not run. |
| Reports/dashboard | Implemented/tested: 16 report types, filters/pagination, CSV/XLSX/PDF/print, transaction-derived summaries and receivables. Statement is a bill-balance summary, not a chronological running-balance ledger. |
| Administration | Implemented/tested: predefined role account administration, last-Super-Admin protection, settings and audit. No per-market staff restrictions or custom role designer. |
| Development seed | Implemented/tested: guarded local/testing only, 48 stalls, 28 fictional tenants, sample historical bills/payments, user-supplied strong password. |
| Deployment | Documentation/config prepared; Apache syntax passed. MySQL migration, actual PHP 7.4 execution, Apache activation, restore rehearsal and LGU UAT pending. |

Evidence: VERIFICATION.md. No module is certified for internet-facing production on this unsupported stack.

Default local administrator provisioning: implemented/tested via DefaultUserSeeder and config/default_admin.php; explicit opt-in, strong supplied secret, production guard, preservation of existing accounts. Final suite now 52 tests / 282 assertions. No schema change or live MySQL seed. See operations/DEFAULT_ADMIN.md.

2026-11-17 Tenant/lease enhancement implemented and isolated tests passed116/641. New migrations/permissions not applied live; Word/PDF visual acceptance pending. See docs/CONTRACT_IMPLEMENTATION_REPORT.md and CONTRACT_TEST_RESULTS.md.
