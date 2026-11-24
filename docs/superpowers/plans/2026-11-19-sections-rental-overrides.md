# Section Management and Rental Overrides Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Enhance the existing section master and add approved assignment-specific rental overrides without changing historical financial/contract records.

**Architecture:** Reuse market_sections, market_section_id, generic record endpoints and the existing map. Versioned rental_overrides feed RentalCalculationService and new immutable assessments; the existing billing, contract, reports and tenant interfaces consume that service.

**Tech Stack:** PHP7.4.30-compatible syntax, Laravel8.83.29, MySQL, Blade/Tailwind/local JavaScript, BCMath, existing installed dependencies.

**Spec:** docs/superpowers/specs/2026-11-19-sections-rental-overrides-design.md (user approved: “Approve the design and implement”).

## Global Constraints

- No dependency upgrade, replacement module, duplicate section FK or production data write.
- Preserve section IDs/names/scoped codes, market_section_id and all map coordinates.
- Automatic remains default: Inner minimum, Outer maximum. Use frozen initial category/area/CUSA/day basis for established assignments.
- Override activation/revocation is prospective on full-month boundaries; no proration or automatic posting.
- Separate requester/approver; policy must cover the entire override period. No implicit out-of-range/zero authority.
- Existing assessments, bills, contract snapshots/files, payments and balances remain unchanged. No destructive database command.
- Tests force isolated SQLite; native PHP is8.2.12, actual7.4/MySQL contention/Word fidelity remain unverified unless explicitly checked.
- No Git repository: work in the approved workspace using a protected source ZIP/hash checkpoint, not invented commits/worktrees.
- User has explicitly approved implementation; no second plan approval gate or between-task permission request.

## Review Focus

1. Existing normalized duplicate section names: permit unrelated edits retaining spelling, reject creating/renaming into duplicates; flag existing ambiguity (Task1).
2. Stale approval or concurrent bill/contract creation: fingerprint plus shared locks, protected periods rechecked transactionally (Task2).
3. Expiry/revocation after a master edit: fall back to frozen automatic basis, not current masters; never transfer override to another assignment (Task2).
4. Direct daily200/10=20 is out of21.04–26.88: arithmetic correct but approval denied without explicit policy; a nonrepresentable rate blocks DOCX generation/issue (Task2).
5. Pending/expired report filters versus applied historical mode: screen/export use matching predicates and no financial leakage to management viewers (Task3).

## File ownership and interfaces

Task1 owns sections migration/model/service/command, generic record/policy integration, section/stall/map section UI, section role/navigation/audit additions and its tests. Task2 begins after Task1 review and owns financial schema/models/calculator/override service/policy/contract/billing changes plus related tests and role/audit additions. Task3 begins after Task2 review and owns HTTP override routes/controller/request, tenant/map/report/navigation UI, JS and tests. Task4 owns manuals/Help assets and documentation. Root coordinates/checkpoints/reviews/final verification; source edits outside ownership require communication.

## Task1: Existing Sections, stalls and map

**Files:** create database/migrations/2026_11_19_000001_enhance_market_sections.php, app/Services/SectionService.php, app/Console/Commands/ReconcileSections.php, resources/views/sections/*, tests/Feature/SectionManagementTest.php. Modify MarketSection, RecordController/RecordRequest/Resources/RecordPolicy, generic stall form/list, MapController/map views, routes/web.php, RoleSeeder, Audit, config/navigation.php as needed.

**Interfaces:** SectionService::save(User $actor,array $data,$id=null):MarketSection; reorder(User $actor,$marketId,array $ids):void. Existing records.* URLs for type=sections remain canonical. Command sections:reconcile is read-only and safe before metadata migration. New sections.view/manage permissions; no delete route. Reuse status(active/inactive), market_section_id and scoped code uniqueness.

- [x] Write tests test_section_master_counts_and_metadata (create/edit/status/order/counts), test_section_duplicates_and_existing_ambiguous_spelling, test_stall_options_and_backend_reject_inactive_or_other_market_sections, test_section_rename_map_preserves_coordinates, test_section_routes_enforce_permissions_and_preserve_referenced_records, test_populated_metadata_upgrade_and_read_only_reconciliation. Expected sample counts: oneoccupied/onevacant/onemaintenance gives3total and individual1.
- [x] Run new tests before implementation; retain meaningful red output in .qa/sections-task1-red.txt.
- [x] Implement additive nullable metadata/actors plus sort_order0, model relationship and transactional service validation/normalized comparison/parent locks. Keep occupied-deactivation and historical movement safeguards.
- [x] Enhance existing section routes/views and scoped searchable stall options; counts from existing rows, dynamic names/map summary, configured color secondary to occupancy. Preserve inactive current option for unrelated stall edits. Add new permissions additively and audit safe before/after names/code/color/section fields.
- [x] Implement read-only reconciliation with normalized duplicate groups, orphan/mismatched references and unchanged controls; no auto-backfill/rename. Write SECTION_MANAGEMENT.md and update SECTION_DATA_MIGRATION.md with actual source status.
- [x] Run focused tests plus existing map/navigation/integration regressions green. Save report and source diff for task reviewer; no commits/live migrations.

## Task2: Approved override state, calculation and immutable downstream use

**Files:** create database/migrations/2026_11_19_000002_create_rental_overrides.php, app/Models/RentalOverride.php, app/Services/RentalOverrideService.php, tests/Feature/RentalOverrideTest.php. Modify RentalPolicy/RentalAssessment, RentalCalculationService, BusinessCategoryService and policy form, BillingService/LeaseContractService, RoleSeeder/Audit and necessary fixtures/tests.

**Interfaces:**
- RentalOverrideService::saveDraft(User $actor,StallAssignment $assignment,array $data,?RentalOverride $override=null):RentalOverride; submit(User,RentalOverride):RentalOverride; approve(User,RentalOverride,string $fingerprint):RentalOverride; reject(User,RentalOverride,string $reason):RentalOverride; revoke(User,RentalOverride,array $data):RentalOverride; static fingerprint(RentalOverride):string.
- RentalCalculationService::forAssignment(StallAssignment $assignment,string $period):array returns automatic+applicable full basis and metadata from frozen initial assessment, with exactly one eligible override; existing calculate/resolve callers remain compatible.
- RentalOverrideService::guardContractTerms(StallAssignment $assignment,string $start,string $end,?RentalAssessment $assessment=null):void protects constant term/immutable obligations/precision. Called at contract draft/generate/issue.
- RentalOverride::displayStatus($date=null):string derives expired state, preserving approved/revoked historical coverage.
- Policy fields allow_out_of_range_overrides=false, allow_zero_rent_overrides=false, override_authority_reference nullable. New assessment fields include computation_mode automatic, rental_override_id nullable, automatic_rate_per_sqm/daily_rental nullable(for legacy fallback), rental_difference default0, market_section_id/name nullable, equivalent_rate_per_sqm nullable and contract_rate_consistent boolean defaulttrue. DECIMAL-compatible bounds; basis JSON snapshots exact policy/approval data. Old rows untouched.

- [x] Write/observe failing arithmetic/authority tests:268.80 automatic, rate25→250 difference−18.80, direct200→200 difference−68.80; direct method neverarea-multiplied. For30daysCUSA10 directtotal6300/advance12000/deposit6000. Direct200 is denied until explicit out-of-range authority; zero denied until explicit exemption. Negative/overflow/two amounts/ownership/inactive/no reason reject.
- [x] Implement additive schema, explicit transitions/submission idempotency, fingerprint-bound separate-user approval, parent locks, overlap and stale/race guards. Legacy monthly assignments remain ineligible. Add exact role defaults from spec transactionally without resetting existing grants.
- [x] Test/implement whole-period policy coverage including future/open-ended policy and retirement dependency; pending/rejected/expired/revoked/new-assignment isolation; positive full-month activation/expiry/revoke/return dates within assignment and no retroactive changes. Draft edits retaining original data only until submission; approved updates require new records.
- [x] Extend calculator using BCMath composition from final daily, frozen automatic reference and snapshot metadata. Keep original pure automatic calculate semantics; no arithmetic duplicated elsewhere. Preserve currentday/monthcalendar rules. Existing created billing/contract assessments block conflicting effective changes; share lock order across approval and snapshot creation.
- [x] Test/implement billing/contract integration: old drafts/posted records/issuedfiles/paymentamounts byte/value unchanged; constant contract term must fit approved interval; issued/signed/active obligations block override changes; equivalent2decimalrate×area must reconcile or generation/issue rejects. Original DOCX remains unchanged. Frozen automatic fallback after category/area/policy edits is tested.
- [x] Update approved policy form/fingerprint/retirement rules and audit; test authorization direct service invocation, repeated transition idempotency, scoped exceptions and legacy no-override path.
- [x] Run new and existing financial/contract/category suites green; save report and diff for independent task review. No live schema writes.

## Task3: Tenant workflow, reports, map and navigation

**Files:** create app/Http/Controllers/RentalOverrideController.php, app/Http/Requests/RentalOverrideRequest.php, routes/rental-overrides.php, resources/views/rental-overrides/*, resources/js/rental-overrides.js and tests/Feature/RentalOverrideWorkflowTest.php. Modify TenantController/profile, MapController/details, Reports/ReportController/report filters, config/navigation.php, routes/web.php and asset builder.

**Interfaces:** nested /tenants/{tenant}/assignments/{assignment}/rental-overrides route group; rental-overrides.index/create/store/edit/update/preview/submit/approve/reject/revoke names. Controller validates tenant→assignment ownership before mutation/preview; all authority stays in Task2 services. Report type rental-overrides with rentals.override.report; no new global Business navigation.

- [x] Write/observe failing endpoint/IDOR/permission tests for request preview/submit/approve/reject/revoke/history and persisted submission intent. Authoritative values ignore/reject manipulated client amounts. Read-only roles cannot change rent.
- [x] Implement comparison/form/state actions in existing Rental Information tab and nested screens, method switching only shows one editable amount, server preview plus keyboard/mobile validation; registration points to saved assignment before override request. Preserve tenant/assignment/reference month context on validation/retry.
- [x] Add mode/active-approved indicator/section to map, gating monetary and approval details. Add Rental Override Report with exact spec columns and relevant section/category/mode/status/as-of filters. Historical assessment reports read stored mode; pending report does not imply application. Test screen/CSV/XLSX/PDF/print filter parity and viewer masking; no unsupported filter controls.
- [x] Reuse one Settings Sections and one Reports Override entry; local build integration, no package/CDN. Run focused HTTP/nav/map/report suites green and submit report/diff for task review.

## Task4: Browser evidence, manuals and operational documentation

**Files:** docs/SECTION_MANAGEMENT.md, SECTION_DATA_MIGRATION.md, RENTAL_OVERRIDE_MANAGEMENT.md, RENTAL_COMPUTATION_RULES.md, UNIFIED_TENANT_MANAGEMENT.md, CONTRACT_TEMPLATE_MAPPING.md; docs/user-manual sources/screenshots/coverage/matrices/workflows/exports/help allowlist; AGENTS and docs/codex memory/state/decisions/handoff. Final SECTION_OVERRIDE_IMPLEMENTATION_REPORT.md/TEST_RESULTS.md/file inventory.

- [x] Prepare isolated fictional preview without real inventory/credentials; browser SectionCRUD→stallselection→map, automatic→request→different-user approval→applicable assessment→bill/contract preview; pending/denied/out-of-range/expiry/return controls. Capture section/stall/override desktop and mobile/tablet, inspect console and reset viewport. No legal issue/Word visual assertion without review.
- [x] Update manual chapters/role matrix/report catalog/workflows/screenshots and authenticated Help allowlists; rebuild PDF/DOCX/quick/source ZIP using existing authoring tools. Source cross-check, print bounds/links/bookmarks and visual page inspection; separate Word render attempt and truthful limits.
- [x] Record reconciliation/current counts, exact additive migration/role seeding commands/backup/rollback instructions, superseded override restriction and policy approval responsibilities. No operational backfill/deployment.

## Task5: Final independent review and verification

- [x] Obtain whole-change fresh security/financial review, adjudicate/fix confirmed findings with red/green checks and ledger evidence. Compare source against checkpoint and verify original XLSX/DOCX hashes and secret exclusion.
- [x] Run full forced-SQLite suite, native lint, php scripts/check-php74.php, php artisan view:cache, npm run build, node syntax and documentation self/source checks. Report actual results, not predictions.
- [x] Stop verified temporary preview process and remove explicit temporary DB/key/login/scripts; retain non-sensitive evidence. Update plan/ledger/status with source-complete vs deployment-pending distinction. Do not merge/deploy/run destructive migrations.

## Self-review

Task1 covers Sections/schema/stalls/map/reconciliation; Task2 covers all financial/authorization/effective-date/snapshot/contract/policy constraints; Task3 exposes the approved workflow and shared reports; Task4 maintains required artifacts; Task5 provides independent final evidence. Shared source ownership is sequential. Signatures/permission names/migration numbers match the interfaces above. Review Focus1–5 have explicit tests in their owning task. No additional plan approval is required by the user's explicit execution authorization.

Final evidence: docs/SECTION_OVERRIDE_TEST_RESULTS.md. Billing and expiry were tested on controlled dates in isolated SQLite. The browser exercise used current time with fictional future reference periods. Live migrations remain for the operator.
