# Unified Tenant Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Unify tenant/business registration and derive all new rental assessments from approved business-category rates and verified stall location/area.

**Architecture:** Extend existing normalized entities and financial services. Retain legacy agreement amounts; new assignments freeze category/policy assessments that supply billing/contracts. Nest business CRUD in tenants and preserve compatible routes.

**Tech Stack:** PHP 7.4-compatible Laravel 8.83, MySQL, SQLite isolated tests, BCMath, Blade, Tailwind 3, existing local JavaScript and DOCX XML renderer.

**Spec:** `docs/superpowers/specs/2026-11-18-unified-tenant-management-design.md`, approved by user 2026-11-18 with "Approve the design and implement".

## Global Constraints

- No dependency/framework upgrade, real data in tests, original source modification, destructive migration or live backfill.
- Money uses decimal strings/BCMath; Inner=minimum, Outer=maximum; authoritative inputs resolved server-side.
- Existing payments, posted bills, issued snapshots and private original files remain unchanged.
- Seed exactly21 categories as undated unapproved schedules; actual policies require authorized dates/references/approval.
- Active-account, permission, CSRF and object ownership checks apply independently of menu visibility.
- No Git repository exists: use the approved workspace-local source checkpoint instead of inventing commits/worktrees.
- User authorized implementation after design approval; proceed continuously without an additional plan approval round.

## Review Focus

- Concurrent approval requests must serialize on category/market parents and reject inclusive period overlap.
- Draft helpers and stall selections must survive resume without reserving occupancy or exposing ID data.
- New contract content must not combine edited current trade details with an earlier assessment category.
- Global tenant concurrency must include other markets and legacy assignments under every involved policy.
- Compatible generic endpoints must enforce new validation and never provide manual-rate or ownership bypasses.

## Shared interfaces and ownership

Settings worker owns new category/policy models, category seeder, `2026_11_18_000003_unify_business_categories.php`, category settings controller/request/service/views/routes and settings tests. It may enhance Business/Tenant/Stall model relationships/casts, but does not edit existing controllers/routes/web.php/RoleSeeder/Resources.

Financial worker owns `RentalCalculationService`, AssignmentService/Request/Controller/views, BillingService, LeaseContractService/Controller/create view, RentalAssessment/model migration `000004`, RentalAgreement/RentalBill/LeaseContract relations, financial regression fixtures/tests. No web.php/RoleSeeder edits.

Tenant worker owns TenantController/Request/DataService/views, new BusinessService/Request/Controller, nested tenant routes, registration tests and `000005` registration submission migration if required. It does not edit RecordController, model casts, web.php or shared JavaScript/CSS; it supplies a standalone `resources/js/tenant-registration.js` entry for root to load locally.

Root owns integration in web.php/RecordController/Resources/RecordRequest, RoleSeeder, Audit, stalls/map/reports/navigation, shared assets, reconciliation command, docs and verification. All workers report exact red/green commands and file ownership; root integrates and reviews after each deliverable.

Model names/interfaces: `BusinessCategory`, `BusinessCategoryRentalRate`, `RentalPolicy`, `RentalAssessment`; Business relation `businessCategory()`, Stall field `location_type`, Tenant hidden encrypted `registration_draft`. Rate fields `business_category_id`, `minimum_rate`, `maximum_rate`, `effective_from`, `effective_until`, `status`, `reference_document`, `remarks`, `created_by`, `approved_by`, `approved_at`; policy fields `market_id`, `daily_cusa`, `billing_day_rule` (`calendar`/`fixed`), `fixed_billing_days`, `max_concurrent_assignments`, `advance_deposit_authorized`, same dates/state/reference/actors. Approval permission `rates.approve` is granted only to Super Administrator initially.

`RentalCalculationService::resolve(Business $business, Stall $stall, $date, $period): array` returns existing calculation keys plus business/category/schedule/policy identifiers/names, `minimum_rate`, `maximum_rate`, `location_type`, `billing_day_rule`, `fixed_billing_days`, `max_concurrent_assignments`, references and effective dates. This method validates current approved configuration and does not persist data. `fromAssessment(RentalAssessment $assessment, $period): array` recomputes days/amounts from frozen basis only. `assess(User $actor, StallAssignment $assignment, $period, $purpose = 'initial'): RentalAssessment` freezes an initial or period-specific basis, reuses existing same assignment/period/purpose, and snapshots private identity/business data. Actor authorization remains at corresponding write service boundaries plus assess permission validation.

`AssignmentService::assign(User,array)` retains existing API shape but requires `business_id` for new agreements, removes manual rate selection and supports `concurrent_authorization_reference`/`concurrent_authorization_reason`. Existing `end`/`transfer` keep month boundaries and append history. New agreement has `rental_assessment_id`; legacy agreement has `rental_rate_id` only. Billing snapshot FK: `rental_bills.rental_assessment_id`; contract FK: `lease_contracts.rental_assessment_id`. Category table names exactly as design. Financial migration owns assessment structure and nullable legacy agreement FK.

## Task 1: Category schema, draft seeder and approved settings

**Files:** new category/rate/policy models; migration000003; BusinessCategorySeeder; BusinessCategoryService/Controller/Request and settings views; routes/business-categories.php; tests/Feature/BusinessCategoryTest.php.

**Produces:** models/columns above; route names `business-categories.index/create/store/edit/update`, category rate save/approve/retire and policy save/approve/retire actions; rates.view/manage/approve boundaries.

- [x] Write failing tests asserting21 categories, exact IT34.84/40.00 and Fruits30.97/30.97, preserved approved revisions, no invented dates/approval, nullable existing stall locations and blank commercial name support.
- [x] Run focused test and record expected missing schema/behavior failure.
- [x] Implement additive schema and idempotent seed; retain legacy fields. Add settings forms/list/history with exact source descriptions, range/date/reference validation, inactive categories and serialized overlap-safe approval/retirement.
- [x] Test unauthorized writes/approvals, inclusive date overlaps, invalid ranges, explicit zero CUSA, calendar/fixed policy validation, historical immutability and schedule retirement checks.
- [x] Run focused suite to green; report file inventory and interfaces for independent review. Register seeder after roles in root integration.

## Task 2: Authoritative assessments and unified financial callers

**Files:** existing calculator/assignment/billing/contract services and forms/controllers; RentalAssessment plus migration000004; financial models and focused tests/fixtures.

**Consumes:** category/policy models above. **Produces:** shared resolver/assessment interfaces above and compatible financial routes.

- [x] Write failing tests for six exact daily examples:210.40,268.80,174.20,200.00,309.70,309.70; fractional half-up rounding and overflow; absent/ambiguous approvals and unclassified stalls.
- [x] Record red; extend existing calculator using decimal strings and active ownership/effective schedule/policy validation.
- [x] Add immutable assessment table with assignment/period/purpose unique key, frozen non-sensitive basis and encrypted hidden identity/business snapshot, exact component columns, category/rate/policy IDs and historical display values.
- [x] Test/implement new assign/transfer/renew flow without manual rate inputs; enforce all market concurrency caps and authorization references under existing locks.
- [x] Test/implement initial and period assessment links, calendar/leap/fixed days, separate rental/CUSA bill items, untouched legacy billing and separate posting.
- [x] Test/implement contracts from assessment goods/category/rate/area snapshots, prevent legacy/new manual bypass, preserve generated reprints and approval/issue gates, require renewal for changed trade details.
- [x] Adapt old fixtures to new authoritative paths while retaining explicit legacy rows/tests; run financial/regression suites to green, report red/green evidence and review package.

## Task 3: Unified registration, nested businesses and profile

**Files:** TenantController/Request/DataService/views; new BusinessService/Controller/Request; routes/tenant-businesses.php; standalone tenant-registration.js; registration tests and submission migration if necessary.

**Consumes:** model names/relationships and AssignmentService/calculator interfaces above. **Produces:** nested `tenant-businesses.*` routes; `tenants.rental-preview`; tenant form/list/profile and BusinessService shared mutation entry.

- [x] Write/observe failing tests for nested business CRUD/ownership, blank business name, archive safety, draft/resume helpers, transactional tenant+multiple businesses+assignment rollback, duplicate submit and no implicit active lease.
- [x] Implement `BusinessService::save(User $actor,array $data,$id=null,$tenantId=null): Business`, preserving legacy aliases/permit fields, ownership/history checks and explicit reconciliation reason when repairing an orphan. Controller actions enforce tenant scope.
- [x] Extend TenantDataService transaction for registration; personal-only saves retain existing API. Keep pending choices/helpers encrypted, validate every selected business/assignment, and confirm via AssignmentService only with assignment authority. Preserve existing ID masking/helper limits/string accounts.
- [x] Add seven structured sections/review, repeatable business entries, searchable category/stall inputs, protected dynamic preview and safe submission/resume. No computed values are trusted.
- [x] Add nine profile tabs, nested CRUD, exact ledger/payment/contract/history links and permission gates. Extend paginated list/search/location/category filters and summaries without duplicating tenants.
- [x] Test no helper reservations on draft, lost availability on confirmation, same-named tenants warning without automatic merge, unauthorized preview/IDOR and encrypted hidden draft data. Run focused tests green; report integration requirements.

## Task 4: Stall location and safe generic compatibility

**Files:** RecordController/RecordRequest/Resources, stall record views, Audit, RoleSeeder/DatabaseSeeder, routes/web.php, tests/Feature/UnifiedIntegrationTest.php.

- [x] Write/observe failing tests for required new inner/outer location, preserved null old stalls, invalid classification, history edit reason, area/location permission checks and nested legacy Business GET/POST/PUT compatibility.
- [x] Implement location fields/filter/list/details; expose associated business/category and assessment amounts using stored history. Restrict master edits to stalls.manage and audit safe old/new fields.
- [x] Wire new route files before generic routes; delegate legacy business mutation to BusinessService, redirect GET to tenant tab/search and reject manual-rate bypasses.
- [x] Register category seeder after RoleSeeder, add approval permission only to Super Administrator, keep all existing grants. Run focused compatibility/security tests green.

## Task 5: Map, reports and navigation integration

**Files:** MapController/map views, Reports/ReportController/report filters, config/navigation.php and navigation tests.

- [x] Write/observe failing tests for map location/business filters, frozen historical values after master edits, financial/PII visibility and three new reports with correct export permissions.
- [x] Add business-category, rental-assessment, lease-contract-registry report queries using snapshot fields and existing export infrastructure; preserve16 existing types. Add new filters with bounded server validation.
- [x] Update grouped menu to requested entries, remove standalone Business item only after Task3/4 green, retain physical category/legacy rate/audit/help discoverability.
- [x] Run map/report/sidebar regression tests green and build local assets; ensure tenant module JS is locally loaded and no runtime package/CDN added.

## Task 6: Read-only reconciliation and operational documentation

**Files:** Console reconciliation command/tests; docs/UNIFIED_TENANT_MANAGEMENT.md, BUSINESS_CATEGORY_MANAGEMENT.md, RENTAL_RATE_MASTERLIST.md, RENTAL_COMPUTATION_RULES.md, STALL_LOCATION_CLASSIFICATION.md, TENANT_BUSINESS_MIGRATION.md, CONTRACT_TEMPLATE_MAPPING.md; project checkpoint docs.

- [x] Write/observe failing reconciliation tests for orphan/owner mismatch/unclassified/unmapped/duplicate candidates/legacy totals; implement aggregate-safe read-only command with private authorized detailed workflow where needed.
- [x] Document exact setup/seed/approval/migration/rollback commands, defaults and financial boundaries. Record phase status and superseded requirements after each completed phase.
- [x] Reconcile actual existing database read-only; do not backfill or migrate it. Compare counts/totals to discovery only when no external user changes occurred; report differences accurately.

## Task 7: Synthetic browser evidence and maintained manuals

**Files:** affected user-manual chapters/quick reference/workflows/screenshot indexes/matrices/help config and generated exports; relevant Help tests.

- [x] Prepare disposable synthetic fixture only and run actual browser desktop/tablet/mobile registration, business/rate policy approval, classification/calculation/contract/map flows; capture relevant screenshots and verify errors/loading/keyboard behavior.
- [x] Update current manual to implemented behavior, exact role/report matrix and new workflows; retain original sources and label approval/renderer limitations accurately.
- [x] Rebuild exports using existing scripts/bundled authoring runtime; verify all PDF pages/links/bounds and DOCX structure, Help allowlists/authentication. No claim of Word rendering without installed renderer.

## Task 8: Independent reviews and final verification

- [x] Review each bounded deliverable for spec/quality; fix confirmed issues with regression tests and ledgered decisions.
- [x] Run `php vendor/phpunit/phpunit/phpunit --configuration phpunit.xml --colors=never`, `php scripts/check-php74.php`, changed PHP lint, `php artisan view:cache`, `npm run build`, manual source self-check/export checks.
- [x] Obtain whole-change independent security/financial review; inspect source diff against checkpoint, classify every changed file and verify preserved source checksums and no credential exposure.
- [x] Update plan/ledger/project docs with actual tests, exact deployment/rollback steps and remaining PHP7.4 runtime/MySQL concurrency/Word review limits. No merge/deployment without authorization.

## Self-review and execution status

Coverage: Tasks1–2 implement schema/seeding/settings/automatic assessment/history; Task3 unified UI/drafts; Tasks4–5 compatibility/stalls/map/reports/nav/security; Tasks6–8 reconciliation/docs/tests/handoff. Review Focus cases are assigned explicitly above. Shared signatures and column ownership align across workers. Implementation starts under the user's explicit approval; no second plan gate.

Final2026-11-19: all implementation tasks completed in source; live deployment and operational/Word acceptance remain pending as documented in docs/UNIFIED_IMPLEMENTATION_REPORT.md. No live migration/backfill.
