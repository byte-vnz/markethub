# Unified management verification — 19 November 2026

## Automated results

Final command: `php vendor/phpunit/phpunit/phpunit --configuration phpunit.xml --colors=never`.

**182 tests, 1,277 assertions passed**, 79.537 seconds, 66 MB, PHP 8.2.12. Configuration forces `APP_ENV=testing` and disposable SQLite `:memory:`. Evidence: `.qa/unified-full-final.txt`. No live MySQL migration, reset, seed or backfill was executed. No dependency was upgraded.

Coverage includes exact daily examples 210.40/268.80/174.20/200.00/309.70, fractional half-up/overflow, all21 draft categories and seed repeatability, date overlap/approval fingerprints, ownership/permissions/IDOR, location/area, encrypted large Unicode drafts, stale drafts, durable duplicate-submit protection, complete registration rollback, concurrent occupancy policies, historical schedule/basis preservation, calendar/leap/fixed billing, separate CUSA items, immutable bills/contracts, legacy billing, current first-generation ID/helpers, additive role upgrades including interruption rollback, map privacy, report page/export filters and Help authorization/allowlists. Existing default-account/authentication, tenant, payment/reversal, contract and navigation suites remain included.

Focused regression red/green evidence is retained in `.qa/unified-*.txt`; full-suite test isolation was corrected by inserting pre-upgrade assignment fixtures through the database builder rather than priming Eloquent's schema cache before migration. No production workaround was added.

## Build and syntax

| Check | Result | Evidence |
| --- | --- | --- |
| Native PHP lint | 158 source files, zero failures | .qa/unified-native-lint-final.txt |
| PHP 7.4 grammar | 220 files, zero failures after final Blade cache rebuild | .qa/unified-php74-final.txt |
| Blade cache | Passed | .qa/unified-blade-final.txt |
| Local asset build | Passed; nonfatal old Browserslist data notice | .qa/unified-build-final.txt |
| JavaScript syntax | Passed | node --check for app/registration source |
| Manual self-check | Passed | .qa/unified-doc-self-check.txt |

Earlier grammar run included 276 files with stale compiled cache entries, also zero failures. Grammar parsing does not establish PHP7.4 runtime compatibility.

## Actual browser evidence

An isolated SQLite preview used ordinary login/CSRF and clearly fictional training data. Browser actions covered approved fictional Vegetables schedule/policy, Inner-to-Outer dynamic preview, leading-zero accounts, encrypted save-draft/resume, final confirmation, helper retention, tenant tabs, nested business, map details, bill draft/review/post, payment posting, statement, contract original generation/void history, Help/search, 390px mobile and820px tablet. Missing rate confirmation rolled back safely; ending inside a billed month was rejected. No extra lease was created by registration. Captured console warnings/errors: none.

Outer10sqm ×26.88 =268.80 daily; November30days =8064.00 base +300.00 CUSA =8364.00 bill. Posted payment2000.00 leaves6364.00. Advance16128.00 +deposit8064.00 =24192.00 is a displayed authorized calculation, not new deposit/advance accounting. A separate100.00 illustration was never posted and its draft was voided. The generated contract was never legally approved/issued/signed/activated; void preserved original file/history and assignment occupancy.

Aggregate browser evidence: `user-manual/verification/unified-browser-evidence.json`; screenshots and captions: `user-manual/SCREENSHOT_INDEX.md`. Temporary preview server, SQLite database, scripts and generated login/key files were removed; no credential appears in published evidence. The browser download-event wait was not used as proof; authenticated download/preview restrictions pass HTTP tests.

## Documents and retained sources

Manual v1.1:79-page PDF, editable DOCX,6-page quick PDF,52actual screenshot files,10editable workflows. Source cross-check:14chapters,41permission rows,6roles,19reports,51unique manual figure paths; zero errors. All PDF contact sheets and representative full pages inspected; zero text outside print bounds. DOCX XML/bookmarks/relationships are structurally checked. Separate Word render failed because LibreOffice is absent; no Word pagination equivalence is claimed.

Actual app report PDF checks: rental-assessment report2landscape pages; lease registry1page. Wide reports now use readable per-record field/value layouts, preserving every selected field; CSV/XLSX remain row based. No words fall outside page bounds. Original XLSX and authoritative DOCX SHA256 hashes remain unchanged; values recorded in implementation report.

## Unverified operational acceptance

Live feature migration/UAT, native MySQL ALTER/locking/contention, actual PHP7.4 execution, Word/manual/contract pagination including nine-page fidelity, successful contract PDF conversion, printer output and LGU/legal/policy approval remain pending. The existing legacy-stack support/advisory limits remain. No production-readiness certification is made.
