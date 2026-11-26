# Project memory
MarketHub implements the attached master specification in docs/MASTER_SPEC.md. Target is a local LGU Laravel 8.83/PHP 7.4-compatible MySQL application at C:/xampp/htdocs/market-stall, timezone Asia/Manila, PHP currency. No production deployment is approved.

Original prototype: C:/Users/vnz/Desktop/proto/index.html, preserved byte-for-byte at prototype/index.html (SHA256 051ECD81F2D4084C096ED8603E6E23BDB6DBEF85E89C06E4C5A6CA25F5C9BE5D). Preserve navy sidebar, light panels, blue actions, grid map/details composition. Replace browser-only persistence and demo authentication. Occupancy and payment status are separate.

User will configure MySQL and run migrations later. Do not create/migrate a development MySQL database in this session. Automated tests use SQLite :memory: with forced testing environment. MySQL concurrency remains unverified until an isolated server test is run.

Services own financial/occupancy invariants. All amounts DECIMAL(14,2), calculations BCMath strings. Roles and permissions are explicit, no global superadmin bypass. Posted history is immutable through app workflows, with append-only reversals/adjustments and audit actors/reasons. Database administrators remain a separate trust boundary.

Module sequence: auth -> property/tenant -> assignment/agreement -> billing -> payments -> reporting. Financial policy gates: no automatic posting, no advance credits, no penalty/proration guesses. Settings must not pretend to establish LGU authority.

DefaultUserSeeder (2026-11-17): optional active System Administrator / admin@marketstall.local, mapped to existing Super Administrator role. config/default_admin.php reads SEED_DEFAULT_ADMIN and DEFAULT_ADMIN_* variables; disabled by default, local/testing only, strong 16-72 byte Hash::make password required. Existing accounts are never reset/reactivated/promoted. Username and first-login forced change are unsupported. DatabaseSeeder registers it after RoleSeeder; no schema/dependency change. See operations/DEFAULT_ADMIN.md.

Apache 403 resolution: live XAMPP httpd.conf now includes the scoped public-only MarketHub Alias, and public/.htaccess has RewriteBase /market-stall/. Current local URL http://localhost/market-stall/. Apache root deny remains. Backup/verification/rollback: operations/APACHE_403_FIX.md. MySQL migrations now report Yes and server 8.4.7; the default admin account is absent. This was observed read-only, not migrated by Codex.

Sidebar redesign: config/navigation.php declares ordered groups/items, permissions, icons and active patterns; App\Support\Navigation filters for the user. Shared Blade sidebar has persisted disclosures, desktop icon rail and mobile/tablet focus-managed drawer. Preferences only use localStorage markethub.sidebar.v1; records remain server-backed. No missing account/role pages were invented. Architecture: ../architecture/NAVIGATION.md.

Tenant/lease enhancement adds structured profiles, encrypted ID fields, assignment helpers and source references without splitting legacy names. Contracts use existing active assignments, immutable generated DOCX/snapshots, explicit approval/issuance/signed/activation/closure transitions and private checksum-verified downloads. Source workbook and DOCX in private/references are never modified/imported. No package added; targeted ZipArchive/DOM preserves template XML. Details: docs/CONTRACT_IMPLEMENTATION_REPORT.md and mapping/setup/test documents.

Documentation v1.0 is maintained in docs/user-manual:14chaptermanual,40permissionmatrix,16reports,19-step lifecycle,45realfictional captures and10editablediagrams. PrebuiltPDF/DOCX/quickPDF downloads are served by authenticated HelpController allowlists, using existing dashboard.view; no production authoring dependency/schema. Word visual review remains pending because renderer unavailable. See README/BUILD/VERIFICATION and COVERAGE_MATRIX.

Unified tenant/business request (2026-11-18): new user brief supersedes separate Business Management and manual rate selection for new assessments. Phase 0 discovery and fresh baseline completed (125 tests/789 assertions). The written design at docs/superpowers/specs/2026-11-18-unified-tenant-management-design.md passed a builder review and awaits explicitly required user approval before code or detailed implementation plan. Existing MySQL inspection was read-only: tenant/business/stall/assignment/agreement/rate/bill/payment/contract counts all zero, earlier enhanced schema present. See docs/TENANT_BUSINESS_MIGRATION.md. No feature schema/data/application changes yet; old manual remains the currently implemented behavior. After approval invoke writing-plans, then implement with isolated TDD/subagent review and regenerate affected manuals.


Unified enhancement approval update (2026-11-18): user approved the written design and implementation. Plan: docs/superpowers/plans/2026-11-18-unified-tenant-management.md; recovery ledger: .superpowers/sdd/unified-tenant/progress.md. Implementation is in progress with bounded category, financial and tenant workers. No second approval is needed for implementation. Live migrations/backfills remain unexecuted; previous approval-pending notes are historical checkpoints.

## Unified enhancement completed in source — 2026-11-19

The user approved the technical design and implementation on 2026-11-18. All eight implementation-plan tasks are complete, including independent reviews and corrected findings. Earlier approval-pending/in-progress entries are historical checkpoints. No live deployment or backfill was performed.

Delivered normalized nested tenant/business management; encrypted resumable registration drafts and persistent submission idempotency; 21 category schedules seeded as unapproved undated drafts; explicit fingerprint-bound rate/policy approval; Inner=min/Outer=max automatic exact-decimal calculation; immutable assessments shared by new assignments, bills and original-template contracts; legacy compatibility, map/reports/navigation and read-only reconciliation. Role upgrades preserve existing grants/revocations. Original contracts, reference files and posted financial history remain retained.

Final isolated SQLite suite: **182 tests, 1,277 assertions passed**, 79.537 seconds, PHP 8.2.12. Final native lint: 158 files, zero failures. PHP 7.4 grammar: 220 files after clearing stale compiled views, zero failures (earlier cache-inclusive run: 276). Blade cache and local asset build passed. Actual browser desktop/tablet/mobile workflows passed with fictional data and no captured console errors. Full details: docs/UNIFIED_IMPLEMENTATION_REPORT.md and docs/UNIFIED_TEST_RESULTS.md.

Documentation v1.1: 79-page PDF, editable Word manual, 6-page quick PDF, 52 actual image assets, 10 editable workflows, 41-permission/6-role matrix and 19 reports. All PDF contact sheets and representative full pages inspected; automated page bounds have zero issues. Source cross-check has zero errors. Word rendering and nine-page contract fidelity remain unverified; no renderer was installed.

Latest live MySQL aggregate reconciliation at 2026-11-19 00:28 Asia/Manila still shows zero operational records; new tables are absent. The isolated PHP preview server was stopped and temporary database/scripts/credentials removed. Next exact task is operator deployment using the report: protected backups, forward migrations, additive role/category seeding, reconcile, actual approved settings/classifications/mappings, then MySQL/UAT and Word review. Actual PHP 7.4 execution and MySQL locking/concurrency remain pending. Software approval never approves LGU financial policy.

## Section management and rental overrides — design checkpoint, 2026-11-19

New user brief requires enhancing existing Sections and assignment-specific approved rental overrides; it explicitly requires design approval before implementation. Proposed design: docs/superpowers/specs/2026-11-19-sections-rental-overrides-design.md. Section discovery/reconciliation: docs/SECTION_DATA_MIGRATION.md. No feature code/migration/backfill has been implemented at this checkpoint. Detailed TDD plan follows approval through writing-plans.

Fresh isolated baseline passed182tests/1277assertions in81.741seconds onPHP8.2.12; .qa/sections-overrides-baseline.txt. Read-only liveMySQL inspection at2026-11-19 01:10 found0sections/stalls/layoutpositions/orphan/mismatched/duplicategroups and zero operational/financial controls. Prior unified tables are now installed with21categories/21rates (external change since earlier handoff),0policies/assessments. Codex executed no live migration/seed/backfill. Reuse market_sections, market_section_id and existing section routes/navigation; retain scoped code uniqueness and names/coordinates.

Proposed choices awaiting approval: prospective full-month override boundaries, separate requester/approver, immutable existing billing/contract obligations, explicit default-disabled out-of-range/exemption policy, and block inconsistent equivalent-rate DOCX generation. Automatic remains default; previous manual-override prohibition is superseded as a requirement by this new brief, but remains current product behavior until approved implementation. Next step: complete builder check, obtain approval of written design, then invoke writing-plans immediately and implement all phases with TDD/reviews. No live deployment/backfill authorization is implied.

## Section/override implementation authorized — 2026-11-19
The user approved the written design with “Approve the design and implement”. No further design/plan approval is needed. Plan: docs/superpowers/plans/2026-11-19-sections-rental-overrides.md. Recovery ledger: .superpowers/sdd/sections-rental-overrides/progress.md. New protected checkpoint contains234source files. Task1 section enhancement is underway; financial overrides/integration/manual QA follow independent review. Live migrations/backfills remain unexecuted and unauthorized. Earlier pending-approval checkpoints are historical.

## Usage-limit recovery — 2026-11-19
Recovered the existing section/override task, not a new project. Git status/diff/staged/log/branch inspection confirms no Git repository; protected file snapshots/diffs are the recovery authority. Task1 Sections completed with clean independent review after one fix round; do not regenerate it. Task2 financial source and migration000002 exist; saved latest focused result12tests83assertions and contract3tests11assertions were green before interruption, but full financial/regression verification and report were unfinished. Original financial implementer resumed from that source. Task3 overrideHTTP/UI/map/report, Task4browser/manual/export and Task5finalreview/verification remain pending. No live migrations/seeds/backfills/destructive commands run. Exact next step: finish Task2 migration/policy/obligation checks and isolated fullsuite, freeze diff for independent review, then implement Task3. Recovery snapshot .qa/sections-overrides-checkpoint/after-task-2-recovery.zip retains partialfinancialwork. Ledger .superpowers/sdd/sections-rental-overrides/progress.md.

## Financial review checkpoint
Task1 Sections remains complete and reviewed. Task2 financial implementation passed the fresh full isolated SQLite suite (212 tests,1,555 assertions) and syntax checks, then independent review identified four gaps: audit-page/report override-reason visibility, committed draft replay after eligibility dates change, missing billing/contract cross-purpose snapshot comparison, and blank optional expiry normalization. Original financial implementer is fixing all four with regression tests; no Task2 completion claim yet. Review base is .qa/sections-overrides-checkpoint/after-task-2.zip and findings/decisions are in the SDD ledger. Exact next step: inspect fix evidence, capture scoped diff, obtain re-review, then proceed to Task3 HTTP/UI/report integration. No live writes or deployment.

## Resume checkpoint — 19 November 2026, 15:04
Continue the existing approved section/override plan. Task1 Sections is complete and independently reviewed; the supplemental Section List description fix also passed review and10sectiontests/149assertions. Task2 financial overrides is complete with clean scoped re-review after four fixes; final pre-fix full suite212tests/1555assertions, post-fix targeted11tests/107assertions and related105tests/618assertions. No further Task1/2 regeneration is needed.
Task3 was interrupted: nested routes/controller/forms, profile and map integration exist; override report/filter/navigation work and green HTTP evidence/report are unfinished. Its original implementer resumed at this checkpoint. Task4 maintained manuals/screenshots/exports and Task5 final independent review/fullverification remain pending.
Browser QA uses only .qa/sections-preview.sqlite on127.0.0.1:8099. Login and Section create/rename/reorder succeeded; occupied-section deactivation was rejected. Current training SectionA is Fictional Fresh Produce Hall; SectionB was created through UI. Layout baseline hash/evidence saved. Sensitive temporary env/login files exist under .qa and must be removed after the verified preview server stops. Do not print those files. Captures: .qa/sections-captures. Browser verification of overrides and remaining Stall flows is pending.
Recovery file snapshot: .qa/sections-overrides-checkpoint/after-task-3-recovery.zip. No Git repository, no live migration/seed/backfill or destructive command. Exact next step: finish Task3 report and HTTP/export tests, review its scoped diff, then complete browser/manual/final checks and cleanup. Detailed task ownership and decisions: .superpowers/sdd/sections-rental-overrides/progress.md.

## Market Sections and rental overrides — current handoff, 2026-11-19

The 19 November approved Section and rental override enhancement is implemented and verified in the workspace. The existing section master remains canonical. Stall Management searches active market sections, and the map separates current section labels from saved assessed labels. Assignment-specific manual rent requires a different approver, full prospective months, applicable policy authority and validation against saved obligations. Automatic is the default. Existing assessments, bills, contract snapshots, layout coordinates and generated originals retain their data. The previous blanket prohibition on manual rental overrides is superseded only for this approved workflow.

Full isolated run: 237 tests and 1,903 assertions. Browser and Help checks used fictional data, and v1.2 documentation was rebuilt. See docs/SECTION_OVERRIDE_IMPLEMENTATION_REPORT.md, docs/SECTION_OVERRIDE_TEST_RESULTS.md and docs/SECTION_OVERRIDE_FILE_INVENTORY.md. The temporary preview and credentials were removed. Live reconciliation was read-only. No live migrations, seeding or backfill were run.
Use docs/user-manual/README.md for the manual package. The source snapshot ledger is .superpowers/sdd/sections-rental-overrides/progress.md. Earlier pending development and approval checkpoints are historical.


## Modern Web Guidance integration — 2026-11-20

Development-only Modern Web Guidance is installed at `.agents/skills/modern-web-guidance/SKILL.md`, official source commit `64a6d708f5a43f2cd3fde5d4d7db3905ba1bdf65`, CLI 1.0.1, with `skills-lock.json`. Codex discovered the project skill during this session. Use `scripts/modern-web-guidance.ps1` from the project root: it pins the CLI and sets process-scoped telemetry/file-log opt-outs; `.mwgrc` prefers Baseline Widely Available. Existing host skills are retained; do not run duplicate copies or install more skills automatically. See SKILLS_REGISTRY.md for sources, privacy limitations and update procedure.

The all-module source audit records 7 P2 and 4 P3 findings, no verified P0/P1 in its bounded scope. Roadmap and explicit approval gate: `docs/audits/FRONTEND_MODERNIZATION_AUDIT.md` and `docs/plans/FRONTEND_MODERNIZATION_ROADMAP.md`. No frontend remediation, business changes, schema changes, live data access or deployment occurred. Existing Section/override implementation and operational acceptance gates remain unchanged. Fresh isolated suite passed 237 tests/1903 assertions; PHP7.4 grammar309/0; JS syntax and local asset build passed. Runtime telemetry guard and runner self-check passed. Full evidence/limits: `docs/audits/FRONTEND_INTEGRATION_REPORT.md`.

Next task is explicit approval of a bounded modernization stage (recommended Stage1). This does not supersede the separately pending operator MySQL rehearsal/live rollout, actual PHP7.4 execution, LGU policy/UAT or Word fidelity reviews. No operator-manual UI/workflow content changed, so existing v1.2 screenshots/diagrams/exports remain the current product documentation.


## Project-wide frontend modernization design checkpoint — 2026-11-20

The project-wide implementation brief explicitly requires approval of the Superpowers written design. Discovery, focused guidance retrieval, the official root scan, source audit and fictional Chrome baseline are complete. Use `docs/frontend/FRONTEND_MODERNIZATION_AUDIT.md` and `docs/superpowers/specs/2026-11-20-frontend-modernization-design.md`. Scope is shared accessibility/recovery, module interactions and measured loading fixes within the current stack. The builder review clarified which forms and tables are covered, a 320 CSS px reflow check and Help image work triggers.


## Frontend modernization implementation — 2026-11-20

The user approved the frontend modernization design. Implementation keeps Blade, Tailwind and the existing JavaScript libraries. Preserve the single permission-filtered sidebar, native no-script links and sign out, explicit drawer focus, matching submitter confirmation, scoped safe form recovery and named scroll areas. Chart.js appears only on Dashboard. Help loads its first allowlisted figure eagerly and defers later images. Legacy rates batch the market lookup. The project builds operator manual v1.3 from the existing authoring pipeline.

## PHP 7.4 production parse error — 2026-11-24

The production screenshot identified PHP 8-only parenthesized dynamic class construction in RecordController. Replaced all three application occurrences in RecordController (create/save) and RecordRequest (relation validation) with class-name variables. The existing parser's version target is best-effort; scripts/check-php74.php now explicitly rejects this syntax and no longer labels its result as a strict grammar check. See docs/operations/PHP74_PARSE_ERROR.md for operator commands and debug-display precautions. No data/schema/permission/UI workflow change; manuals and exports do not require rebuilding for this syntax-only correction. Native PHP 7.4 and production verification remain operator tasks.

## Adjustable fixed-day billing — 2026-11-25

The user approved separate weekly bills with a configurable interval, record-only CUSA and disposable verification. The switch is off by default. New fixed-day terms default to seven days and freeze the selected interval, exact rental basis and record-only CUSA. A first ordinary assignment needs an effective approved category rate. The existing market-policy authority check still applies to concurrent occupancy. Historical monthly values and approval paths remain authoritative for existing monthly assignments. Fixed-day billing uses complete intervals anchored to the assignment start and explicit posting.

Use [the design](../superpowers/specs/2026-11-25-fixed-day-billing-design.md) and [the operator runbook](../operations/FIXED_DAY_BILLING.md). Live database access, migrations, seeding and activation are outside the prepared change.

## Optional assignment information — 2026-11-26
Staff can leave permitted goods or services blank when assigning a stall. A supplied agreement reference must stay unique. Blank assignment and renewal references receive a generated internal `RA-` reference. Tenant reference entry and business permit or registration reference entry remain optional. For concurrent occupancy, the authorization reference is optional. The reason, approved capacity in every involved market and occupancy protections still apply.

The existing monthly lease draft needs verified goods or services in its saved assessment. Use a fresh eligible renewal when the assessment is missing these details. Preserve saved business and rental snapshots, issued documents and financial references.

## v04 lease source integration — 2026-11-26

Active private lease source is v04, pinned by checksum. New drafts select approved v04 copies; prior v02 prepared templates and generated originals stay readable. The v04 map uses frozen assessment values and verified tenant/helper identity; the source stays untouched. See `docs/V04_CONTRACT_UPGRADE.md` for map and discrepancies. v04 registration and issuance still require authorized nine-page visual review. No live DB or migration was performed.
