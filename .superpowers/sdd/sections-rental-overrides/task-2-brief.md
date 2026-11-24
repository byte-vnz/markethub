# Task2: Approved override state, calculation and immutable downstream use

**Files:** create database/migrations/2026_11_19_000002_create_rental_overrides.php, app/Models/RentalOverride.php, app/Services/RentalOverrideService.php, tests/Feature/RentalOverrideTest.php. Modify RentalPolicy/RentalAssessment, RentalCalculationService, BusinessCategoryService and policy form, BillingService/LeaseContractService, RoleSeeder/Audit and necessary fixtures/tests.

**Interfaces:**
- RentalOverrideService::saveDraft(User $actor,StallAssignment $assignment,array $data,?RentalOverride $override=null):RentalOverride; submit(User,RentalOverride):RentalOverride; approve(User,RentalOverride,string $fingerprint):RentalOverride; reject(User,RentalOverride,string $reason):RentalOverride; revoke(User,RentalOverride,array $data):RentalOverride; static fingerprint(RentalOverride):string.
- RentalCalculationService::forAssignment(StallAssignment $assignment,string $period):array returns automatic+applicable full basis and metadata from frozen initial assessment, with exactly one eligible override; existing calculate/resolve callers remain compatible.
- RentalOverrideService::guardContractTerms(StallAssignment $assignment,string $start,string $end,?RentalAssessment $assessment=null):void protects constant term/immutable obligations/precision. Called at contract draft/generate/issue.
- RentalOverride::displayStatus($date=null):string derives expired state, preserving approved/revoked historical coverage.
- Policy fields allow_out_of_range_overrides=false, allow_zero_rent_overrides=false, override_authority_reference nullable. New assessment fields include computation_mode automatic, rental_override_id nullable, automatic_rate_per_sqm/daily_rental nullable(for legacy fallback), rental_difference default0, market_section_id/name nullable, equivalent_rate_per_sqm nullable and contract_rate_consistent boolean defaulttrue. DECIMAL-compatible bounds; basis JSON snapshots exact policy/approval data. Old rows untouched.

- [ ] Write/observe failing arithmetic/authority tests:268.80 automatic, rate25→250 difference−18.80, direct200→200 difference−68.80; direct method neverarea-multiplied. For30daysCUSA10 directtotal6300/advance12000/deposit6000. Direct200 is denied until explicit out-of-range authority; zero denied until explicit exemption. Negative/overflow/two amounts/ownership/inactive/no reason reject.
- [ ] Implement additive schema, explicit transitions/submission idempotency, fingerprint-bound separate-user approval, parent locks, overlap and stale/race guards. Legacy monthly assignments remain ineligible. Add exact role defaults from spec transactionally without resetting existing grants.
- [ ] Test/implement whole-period policy coverage including future/open-ended policy and retirement dependency; pending/rejected/expired/revoked/new-assignment isolation; positive full-month activation/expiry/revoke/return dates within assignment and no retroactive changes. Draft edits retaining original data only until submission; approved updates require new records.
- [ ] Extend calculator using BCMath composition from final daily, frozen automatic reference and snapshot metadata. Keep original pure automatic calculate semantics; no arithmetic duplicated elsewhere. Preserve currentday/monthcalendar rules. Existing created billing/contract assessments block conflicting effective changes; share lock order across approval and snapshot creation.
- [ ] Test/implement billing/contract integration: old drafts/posted records/issuedfiles/paymentamounts byte/value unchanged; constant contract term must fit approved interval; issued/signed/active obligations block override changes; equivalent2decimalrate×area must reconcile or generation/issue rejects. Original DOCX remains unchanged. Frozen automatic fallback after category/area/policy edits is tested.
- [ ] Update approved policy form/fingerprint/retirement rules and audit; test authorization direct service invocation, repeated transition idempotency, scoped exceptions and legacy no-override path.
- [ ] Run new and existing financial/contract/category suites green; save report and diff for independent task review. No live schema writes.

## Binding global constraints


- No dependency upgrade, replacement module, duplicate section FK or production data write.
- Preserve section IDs/names/scoped codes, market_section_id and all map coordinates.
- Automatic remains default: Inner minimum, Outer maximum. Use frozen initial category/area/CUSA/day basis for established assignments.
- Override activation/revocation is prospective on full-month boundaries; no proration or automatic posting.
- Separate requester/approver; policy must cover the entire override period. No implicit out-of-range/zero authority.
- Existing assessments, bills, contract snapshots/files, payments and balances remain unchanged. No destructive database command.
- Tests force isolated SQLite; native PHP is8.2.12, actual7.4/MySQL contention/Word fidelity remain unverified unless explicitly checked.
- No Git repository: work in the approved workspace using a protected source ZIP/hash checkpoint, not invented commits/worktrees.
- User has explicitly approved implementation; no second plan approval gate or between-task permission request.


## Source integration notes from coordinator
- LeaseContractService::locked currently locks tenant then contract, while create/billing lock tenant then assignment. When integrating financial transitions, acquire/recheck shared assignment lock before protected-period decisions; avoid approval racing generation/issuance. Respect original lock order and parent-policy retirement dependencies. Do not claim SQLite proves MySQL locking.
- Scheduled revocation must retain valid manual rent before its effective boundary; mode/active indicator and derived displayStatus(as-of) must agree. On/after boundary use frozen automatic basis. Actual stored revoked state alone must not make earlier coverage disappear.
- Pure direct daily200 arithmetic is a required example but is outside the supplied21.04minimum at10sqm; tests distinguish calculation from authorization. Zero rent does not waive frozen CUSA.
- Existing optional tests/MySql/ConcurrencyTest.php contains pre-existing migrate:fresh; do not execute it. No new destructive command or operational migration permitted.
- Existing policy callers/tests may omit new authorization flags. Preserve backward-compatible default-false semantics in service validation; old requests must not implicitly enable exceptions or fail solely for omitted new flags. New flags true require explicit authority reference at approval.
