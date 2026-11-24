# Tenant and lease test results — 2026-11-17

## Executed verification
- Full PHPUnit suite: **116 tests, 641 assertions passed**; 60.925 seconds, 62 MB on installed PHP 8.2.12. Output: .qa/tenant-contract-final-tests.txt. Forced isolated SQLite :memory:, no live MySQL migration or real-data import.
- PHP 7.4 grammar: **210 files parsed, zero failures**. Native PHP lint: **127 source files, zero failures**. Actual execution on PHP 7.4.30 remains pending.
- `artisan view:cache`: success; `npm run build`: success (existing nonfatal stale Browserslist database notice). No dependency installation/upgrades.
- Tenant focused tests: six tests /56 assertions; legacy create/edit/archive behavior, account formatting, names, encrypted/hidden/masked IDs, sensitive permissions, validation-flash exclusion, audit sanitization, helper ownership/max-two/rollback and contract snapshot helper authorization.
- Rental focused tests: 31 cases /47 assertions; specified PHP6300 monthly total and PHP18000 initial amount, explicit day counts, exact decimal arithmetic, rounding, negative/precision/range rejection.
- Document focused tests: 11 tests /169 assertions; original source checksum, all ZIP members outside word/document.xml byte-identical, five tables/sixteen merged-cell spans/224paragraphs retained, all24legal sections and acceptance paragraphs untouched, both control references populated, acknowledgment/checkbox/identity fields, XML escaping, no unresolved placeholders, rejection of changed source/overwritten output and PDF missing/failure cleanup.
- Contract workflow cases cover generated/issued encrypted immutable snapshots and preserved original downloads; checksum/traversal protection; valid dates; duplicate submissions/overlap/closed assignments; configurable unique numbering; staff authorization; visual/policy approval gates; signed copy/activation without occupancy changes; private template screens and history.
- Focused source review found a stale-assignment race; creation now reloads and locks the assignment after acquiring the tenant lock. Actual simultaneous MySQL locking was not executed or claimed.

## Browser observations
Disposable .qa/lease-preview.sqlite, fictional seed only, temporary localhost:8099 server. Signed in as fixture account, opened tenant edit, saved a text account number beginning with zeroes and observed it retained on the profile. Personal/ID/helper/assignment/business/payment/contract sections rendered. Contract draft page showed its approved-template requirement and disabled creation without approval. The 820px tablet form had no horizontal overflow. No captured browser JavaScript errors. Screenshots: .qa/tenant-enhancement.png and .qa/lease-contract-create.png. Viewport reset, server stopped and disposable preview database/init script removed.

No template approval or legal contract issuance was simulated in the browser. Automated tests use synthetic approval references to test mechanics, not real LGU approvals.

## Source integrity and pending document acceptance
DOCX SHA256: 38eb9c85d161d98a7f32f213efbefa3b810e682b72d1a9d4352a70cc13b014fb. Workbook SHA256: 07304919451078da6c73f1948940d8302eec011af0b3ccf2f07b3c68afae2e18. Originals remain in private references.

No installed Word/LibreOffice renderer was found. Successful PDF conversion, Windows converter process timeout behavior, DOCX opening in Microsoft Word and original-versus-generated nine-page visual fidelity were **not verified**. Structural XML tests do not establish pagination, clipping, font rendering or legal acceptance. Before approving templates/documents, authorized reviewers must check every page in Word, including long names/addresses/two helpers, then check any generated PDF against DOCX. Unknown civil-status mappings/signatories or changed source checksums require a reviewed template map. No production-ready or visual-fidelity certification is made.

## Deployment boundary
Two new additive migrations and RoleSeeder must be applied before using enhanced pages on the actual MySQL installation. No destructive commands or production data writes ran. Run the backed-up forward deployment commands in CONTRACT_IMPLEMENTATION_REPORT.md, then live MySQL role/UAT and backup-restore checks. Existing unsupported runtime/dependency findings still apply. No real government ID, tenant inventory data or secret is included in automated fixtures or this report.
