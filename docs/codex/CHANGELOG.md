# Changelog

## 2026-11-17
- Created Laravel 8.83.29 application and locked PHP 7.4-compatible dependencies; preserved supplied prototype and design direction.
- Added normalized schema, six-role authorization, administrative records/map, assignment history, rental rates/agreements, bills and transaction-derived balances.
- Added payment review/posting, exact allocations, draft cancellation, append-only reversals/adjustments, receipt correction lineage and Treasury confirmation.
- Added dashboard, 16 reports/export formats, staff/settings/audit and guarded local demo seeding.
- Hardened repeated financial requests, negative-adjustment retries, stale draft posting, password reset sessions, billed assignment boundaries, immutable financial details and unrelated receipt reuse; regression cases pass.
- Built local assets and verified desktop/tablet map/payment screens. Fixed dynamic map status CSS retention in Tailwind.
- Added isolated tests and opt-in MySQL parallel-payment test, operations/security/backup documentation, ERD, design system and handoff.
- Final SQLite suite: 33 tests, 175 assertions; build, Blade compilation, Composer validation/platform and PHP 7.4 grammar passed. MySQL/Apache deployment deferred by user.

## Default administrator follow-up — 2026-11-17
- Added configurable, opt-in local/testing DefaultUserSeeder with strong Hash::make password, existing-account preservation/warnings, production denial and existing Super Administrator RBAC.
- Registered DatabaseSeeder invocation; added .env.example defaults/config and provisioning documentation without changing private credentials, schema or dependencies.
- Added 19 isolated seeder cases; full suite passed 52 tests / 282 assertions, PHP 7.4 grammar 167 files / zero failures, changed PHP files lint clean. No live MySQL seeding or destructive commands executed.

2026-11-17 Sidebar: centralized permission-aware navigation, six collapsible groups, persistent desktop compact mode and mobile drawer; 56 tests / 334 assertions passed. Details in architecture/NAVIGATION.md.

2026-11-17 Tenant/lease enhancement implemented and isolated tests passed116/641. New migrations/permissions not applied live; Word/PDF visual acceptance pending. See docs/CONTRACT_IMPLEMENTATION_REPORT.md and CONTRACT_TEST_RESULTS.md.

2026-11-18: User manual v1.0 and HelpCenter added;73-page PDF,editableDOCX,6-page quickPDF,45screenshots,10diagrams;125tests789assertions pass.Wordvisualacceptance pending.
