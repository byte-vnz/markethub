# Task 2 financial implementation and integration handoff

## Delivered source

The approved assignment-specific rental workflow uses the initial assessment as its frozen automatic reference. Policy authority applies to the whole requested interval. A separate authorized user approves the exact reviewed terms. Billing uses the resulting period assessment automatically. Lease draft, generation and issuance use the assignment lock and recheck the applicable interval. Printed two-decimal rates that cannot reproduce the direct daily rent block generation and issue. All fixtures are synthetic.

The implementation diff against `.qa/sections-overrides-checkpoint/after-task-1-fix1.zip` is `.superpowers/sdd/sections-rental-overrides/task-2-review.diff`.

| Files | Responsibility |
| --- | --- |
| `database/migrations/2026_11_19_000002_create_rental_overrides.php` | Additive schema, snapshot defaults, restrictive foreign keys, unique identities, forward-only retention |
| `app/Models/RentalOverride.php` | Date coverage, derived status, actor/assignment/assessment relations, submitted terms and deletion guards |
| `app/Models/RentalPolicy.php`, `app/Models/RentalAssessment.php` | Boolean and fixed-scale decimal casts |
| `app/Services/RentalOverrideService.php` | Preview, draft/version/decision lifecycle, authority binding, locks, obligation guards |
| `app/Services/RentalCalculationService.php` | Automatic and manual exact arithmetic, immutable period snapshot, legacy fallback |
| `app/Services/BusinessCategoryService.php` | Explicit exception settings, approval validation and retirement dependencies |
| `app/Services/LeaseContractService.php` | Contract term and precision checks and complete internal snapshot |
| `app/Services/Audit.php`, `database/seeders/RoleSeeder.php` | Entity-specific safe financial fields and additive permission defaults |
| `resources/views/business-categories/schedule-form.blade.php`, `resources/views/business-categories/revision.blade.php` | Draft exception flags/reference and displayed approval terms |
| `tests/Feature/RentalOverrideTest.php`, `tests/Feature/LeaseContractWorkflowTest.php` | Forward migration, transitions, money, assignments, billing, payments, protected leases, frozen contract and retirement regressions |

The existing BillingService already calls the calculator under tenant/assignment locks, creates immutable bill items, and returns an existing bill on replay. This integration required no separate financial calculation in BillingService. Template XML, source references, payment/receipt workflows, assignment end/transfer code and existing operational records are preserved.

## Interfaces for Task 3

All dates on RentalOverride are ISO date strings. Transition timestamps have datetime casts. Decimal amounts use strings. Every HTTP entry must check its own display/action permissions and bind the assignment to the tenant currently in the URL. Override-specific messages and reasons require the appropriate view/history permission. Only the monetary/metadata fields actually authorized for a given screen should be sent to its browser.

| Method | Input and output |
| --- | --- |
| `RentalOverrideService::saveDraft(User $actor, StallAssignment $assignment, array $data, ?RentalOverride $override = null): RentalOverride` | Required UUID `submission_key` on create, `override_type` of `rate_per_sqm` or `daily_amount`, exactly one matching `override_rate_per_sqm` or `override_daily_amount`, `reason`, first-of-month `effective_from`. Optional month-end `effective_until`, `reference_document`, `remarks`, `source_override_id` (a submitted request on the same assignment). Omit the other method amount or send null. Draft editing is limited to the original requester. Supply `draft_fingerprint` from the displayed draft when editing via HTTP. A repeated UUID with matching terms returns the same request. |
| `RentalOverrideService::preview(User $actor, StallAssignment $assignment, array $data): array` | Requires request permission. `period` (YYYY-MM), `override_type` and the one amount. Optional tenant, stall, business and assignment IDs checked against the assignment. No persistence. Returns `computation_mode=proposed`, null override ID, `out_of_range`, `zero_rent`, exact calculated amounts, equivalent rate, consistency, automatic reference and signed difference. Display separately from the currently approved amount. |
| `submit(User $actor, RentalOverride $override): RentalOverride` | Requester's own draft becomes pending and frozen. Direct stale-model submission checks request hash. |
| `RentalOverrideService::review(RentalOverride $override): array` | Returns `policy` (null or exact authority snapshot) and `fingerprint` from the same policy read. Render these together on the approval form. `fingerprint(RentalOverride): string` also exists. `approvalPolicy(RentalOverride): ?RentalPolicy` resolves only one approved revision covering the entire interval. |
| `approve(User $actor, RentalOverride $override, string $fingerprint): RentalOverride` | Different requester and reviewer, mandatory approve permission. Revalidates request, policy, assignment, amounts, overlaps and obligations under locks. |
| `reject(User $actor, RentalOverride $override, string $reason): RentalOverride` | Pending only, requires reject permission and nonblank reason. |
| `revoke(User $actor, RentalOverride $override, array $data): RentalOverride` | Approved only, requires revoke permission, `revocation_effective_from` (prospective month start), `reason`. Return to automatic uses this same method. Exact duplicate transitions are idempotent. |
| `RentalCalculationService::forAssignment(StallAssignment $assignment, string $period): array` | YYYY-MM overlapping assignment. Returns `automatic` or `manual_override` in `computation_mode`, original and applicable money, `rental_override_id`, `override_type`, `rental_difference`, `equivalent_rate_per_sqm`, `contract_rate_consistent`, frozen section fields and nested `override` (null or approval metadata including authority snapshot). Use for currently applicable pricing and historical as-of previews. Existing issued bill/contract displays use their immutable saved assessments. Legacy monthly assignments have no daily basis and receive validation error `assessment`. |
| `RentalOverrideService::guardContractTerms(StallAssignment $assignment, string $start, string $end, ?RentalAssessment $assessment = null): void` | Called within the existing locked lease transaction. No override boundary may cross the term. Supplying the frozen assessment during generation/issuance additionally checks identity and the direct-daily precision flag. |
| `RentalOverride::displayStatus($date = null): string` | Returns draft/pending/rejected/approved/revoked/expired. Approved includes approved future terms. A scheduled revocation displays approved before its effective boundary, then revoked. Approved terms past expiry or assignment end display expired. Use resolver override ID to indicate active application on a selected month. This label is a display of the current decision against effective dates, not a reconstruction of what a reviewer knew at a past time. |
| `RentalOverride::covering(string $start, ?string $end = null)` | SQL interval intersection on original approval and its revocation boundary. Null end means open-ended query. It excludes wholly cancelled intervals. Apply the owning assignment term when using this scope directly for reports. `lastCoveredDate()` provides inclusive override end after revocation, before intersection with assignment end. |

The optional explicit ownership inputs are `tenant_id`, `business_id`, `stall_id`, `stall_assignment_id`. Return validation messages by field, including `assessment`, `stall_assignment_id`, `period`, `effective_from`, `effective_until`, `revocation_effective_from`, `override_type`, `override_authority`, `rental_policy`, `approved_by`, `fingerprint`, `draft_fingerprint`, `status`, `contract` and money fields. New positive out-of-range values need both references and explicit range authority. Zero outside the frozen range requires both range and exemption flags. Existing CUSA remains payable.

## Stored values and reporting

- `rental_overrides` holds assignment/initial-assessment/source IDs, assignment-scoped unique revision, globally unique UUID, request hash, type, `automatic_rate_per_sqm`, `automatic_daily_rental`, `proposed_daily_rental`, exactly one entered amount, and signed `rental_difference`. The proposed amount is persisted once at rounding precision, suitable for reports in every state. Do not describe a proposed amount as applied merely because a request exists. Frozen area, location, schedule/category and section are on the initial assessment.
- Actor fields are `requested_by`, `updated_by`, `approved_by`, `rejected_by`, `revoked_by`. State uses `status` (draft default), `submitted_at`, `approved_at`, `rejected_at`, `revoked_at`, `rejection_reason`, `revocation_reason`, `revocation_effective_from`. Keep the original requested effective dates on cancellation or early return. `assignment`, `initialAssessment`, `requester`, `approver` are model relations.
- Only approval writes `rental_policy_id`, `approval_fingerprint` and `authority_snapshot`. The snapshot includes the exact revision ID, market, inclusive dates, flags and reference, ordinary policy reference, approval actor/time, day rule, fixed days, CUSA, assignment cap and advance/deposit policy. The original agreement's ordinary financial settings remain authoritative for calculations.
- Policy exception flags `allow_out_of_range_overrides` and `allow_zero_rent_overrides` default false. `override_authority_reference` defaults null and is mandatory when approving a draft policy that enables either flag. User-role defaults follow the approved role matrix. Existing permission grants or deliberate revocations survive repeat seeding.
- New assessments save `computation_mode` (default automatic), nullable `rental_override_id`, automatic rate/daily snapshots, `rental_difference` (default zero), nullable `market_section_id`, `market_section_name`, `equivalent_rate_per_sqm`, and `contract_rate_consistent` (default true). `basis.override` contains the matching immutable approval metadata. Existing assessment snapshots retain their bytes. For old rows the resolver falls back to the original frozen rate, daily amount and section name in basis. A historically unknown section ID remains null.
- `rental_rate_sqm` on direct-daily period assessments carries the two-decimal equivalent for the original template. The authoritative daily amount always comes from the entered direct amount. Terms that fit February but exceed DECIMAL limits during a longer covered calendar month fail before persistence.
- Ending an assignment bounds future applicability without editing the approved row. A successor starts automatic. Retirement still respects saved billing/contract reference months and nonvoid/nonterminated lease obligations even if the original assignment ends.

## Verification

Tests use the project's forced SQLite `:memory:` PHPUnit configuration. Files below are under `.qa/`.

| Check | Evidence |
| --- | --- |
| Initial red | `task2-red.txt` (3 missing service/resolver errors) and `task2-permissions-red.txt` (expected grant absent) |
| Initial green | `task2-first-green.txt` (4 tests, 29 assertions) |
| Document boundaries red and green | `task2-contract-red.txt` (cross-boundary contract accepted and contradictory DOCX generated), `task2-contract-green.txt` (3 tests, 11 assertions) |
| Frozen zero assessment and assignment end | `task2-edge-red.txt`, `task2-ended-red.txt`, `task2-expanded-green.txt` (12 tests, 83 assertions) |
| Populated migration and policy form | Initial `task2-upgrade-policy.txt` exposed a test fixture previously bound to the original SQLite connection. Corrected the fixture lookup to the dedicated prior-schema connection. Included in green `task2-focused.txt`. |
| Focused checks | `php vendor/bin/phpunit --filter 'RentalOverrideTest\|RentalAssessmentTest\|BusinessCategoryTest\|LeaseContractWorkflowTest\|UnifiedMigrationTest'` produced `task2-focused.txt` with 62 tests, 386 assertions before final calendar/report-field additions. The complete suite below includes those additions. |
| Later calendar overflow and frozen daily | `task2-calendar-red.txt` and `task2-persisted-daily-red.txt` captured previously accepted overflow and missing persisted proposed rent. |
| First complete suite | `php vendor/bin/phpunit` produced `task2-full.txt`, 212 tests, 1,554 assertions, before final persisted proposed daily field. |
| Final complete suite | `php vendor/bin/phpunit` produced `task2-full-final.txt`, **212 tests, 1,555 assertions passed**, 112.291 seconds, 68 MB, PHP 8.2.12, forced SQLite memory. Final source and diff frozen for independent review. |
| PHP checks | Native `php -l` succeeded for all 14 changed source/view/test files. `php scripts/check-php74.php` produced `task2-php74.txt`, 291 files parsed with zero grammar errors. |

Native MySQL concurrency/FK behavior, actual PHP 7.4 execution and official template Word visual fidelity remain unverified. No live migration, seeding, backfill, dependency upgrade or operational data write was performed. Review and integration are the coordinator's next steps.

## Independent review corrections, round 1

The six changed files are `app/Models/AuditLog.php`, `app/Http/Controllers/AdminController.php`, `app/Services/Reports.php`, `app/Services/RentalCalculationService.php`, `app/Services/RentalOverrideService.php` and `tests/Feature/RentalOverrideTest.php`. The scoped diff is `.superpowers/sdd/sections-rental-overrides/task-2-fix1-review.diff`, relative to `.qa/sections-overrides-checkpoint/after-task-2.zip`. Public financial service signatures remain compatible.

1. The audit page and all audit-trail exports apply the same query restriction. Reading rental-request history and assessment override application events requires both override view and history permissions in addition to audit access. Other entity actions and system actions with a null entity remain visible. Stored audit rows retain their original information.
2. Draft creation retries check the authorized actor, assignment, optional ownership IDs, UUID and canonical terms before applying current date and assignment activity rules to a new write. A matching committed draft can be retrieved on a later day or after assignment closure. Conflicting reuse is rejected.
3. Before a second financial purpose creates its assessment under the assignment lock, the service checks the existing period snapshot against the resolved financial terms and approval identity. Legacy automatic columns fall back to their own frozen rate and daily amount. Same-purpose replay returns the original assessment. A later prospective revocation still permits creation against a matching earlier approval.
4. A blank or whitespace-only optional end date becomes SQL null before request hashing and storage. The approved open interval resolves in future months, and blank/null create retries share the same identity.

### Round 1 verification

| Command or check | Result and log |
| --- | --- |
| `php vendor/bin/phpunit --filter 'test_override_audit_query\|test_committed_draft_retries\|test_blank_end\|test_cross_purpose'` | Initial red, 10 tests, one retrospective date validation error and seven failed privacy, open-end or financial conflict assertions. `.qa/task2-fix1-red.txt`. Final expanded green, **11 tests, 107 assertions**, `.qa/task2-fix1-final-targeted.txt`. |
| Related assessment audit privacy | Three permission combinations exposed the assessment application event before the final query restriction. `.qa/task2-fix1-audit-application-red.txt`. The final targeted suite includes the corrected page, report, CSV and print checks. |
| `php vendor/bin/phpunit --filter 'RentalOverrideTest\|RentalAssessmentTest\|LeaseContractWorkflowTest\|WorkflowTest\|UnifiedIntegrationTest'` | **105 tests, 618 assertions passed** in 74.420 seconds using forced SQLite memory. `.qa/task2-fix1-financial-report-final.txt`. |
| Native PHP lint | All six changed PHP files passed `php -l`. |

The full-suite result above predates round 1. The coordinator schedules the next full suite after integration.

