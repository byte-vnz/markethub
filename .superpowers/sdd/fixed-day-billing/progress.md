# SDD ledger — plan: docs/superpowers/plans/2026-11-25-fixed-day-billing.md

User authorized preparation/testing of separate weekly bills on 25 November 2026, default seven adjustable days, CUSA record-only, no live database access or migration. The earlier required-policy complaint is resolved for ordinary new fixed-day assignments; approved category rates and concurrency authority remain required. Feature is default-disabled.

## Current recovery point
- Schema agent completed migration and preservation tests. MySQL migration prepared only. SQLite migration/finance subset passed 25 tests / 145 assertions before the additional concurrency snapshot test.
- Root finance implemented fixed-day snapshots, aligned interval generation/posting/end/renewal, CUSA exclusion and legacy guards. Unit/assessment/fixed subset passed 60 tests / 151 assertions before extra regression cases.
- UI agent implemented requests, preview/draft/confirm, native billing mode selection, period labels, reports, map/profile and fixed-template/override explanations. Focused HTTP suite passed 3 tests / 88 assertions. Running fictional browser checks now.
- Docs agent completed v1.4 dated26November:96-page PDF, editableDOCX, six-page quick guide,12 workflows,115-entry editable archive, final screenshots67–70. PDF visual/bounds/link/bookmark checks passed. Actual Help downloads/pages/images passed1test46assertions. DOCX structural checks passed; visual render unavailable because soffice.exe absent.
- Review fixes addressed: scheduler settings retained, monthly batch skips fixed assignments, dashboard includes date-key bills and terminal-year upper bound, no unused advance overflow, blank intervals default consistently, preview mode flags top-level, monthly renewal uses old basis, concurrency policy authority retained in new fixed snapshot.
- Final full suite passed 277 tests / 2,230 assertions, 04:27.626, 82MB on PHP8.2.12; XML .qa/fixed-day-billing/phpunit-final.xml. Native lint44 files and best-effort PHP7.4 scan323 files passed; actual PHP7.4 not available.
- Default runtime untouched: no .env changes, no live database connection, migration, seeding, backfill or account change.

## Final verification and limits
- Independent final review completed; one switch-off renewal boundary finding was fixed with red/green regression and re-reviewed clean. No remaining code findings in the reviewed scope.
- Chrome152 verification completed: forty page/viewport plus four populated preview checks, no overflow/errors/unexpected responses; keyboard, no-script generation, two weekly bills, explicit posting and printed PDF. Screenshots67–70 are finalized; Figure68 refreshed after copy correction.
- PHP and Chrome test processes stopped. On26November root verified exact runtime absolute path and removed only .qa/fixed-day-billing/runtime (SQLite and generated credentials). Preserved browser-billing-print.pdf by verified hash and all scripts, browser.json and tests. No live database was touched.
- Documentation verification is complete within available renderers. Evidence .qa/fixed-day-docs/help-publication.xml and word-render.log; source and archive integrity documented in docs/user-manual/VERIFICATION.md. All implementation-plan tasks are complete. No additional code work remains in this approved preparation scope.
- No deployment/activation: operator must rehearse protected MySQL copy and target PHP7.4, then separately apply migration and opt in. Fixed-day legal template/overrides remain outside this scope; historical originals remain.
