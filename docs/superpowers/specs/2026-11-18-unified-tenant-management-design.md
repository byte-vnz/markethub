# Unified Tenant Management and automatic rental computation

Date: 2026-11-18. Status: approved by user with "Approve the design and implement"; implementation in progress.
Requirement source: user attachment `38011da2-da75-47c2-9bd9-f36b9962fab3/Pasted text.txt`.

This document is a reviewable design, not an implementation or deployment claim. The user has approved merging the interfaces and Inner/minimum, Outer/maximum rental rules. Those decisions are not being reopened. The brief separately requires approval of this technical design before implementation.

## Intent and boundaries

Make Tenant Management the entry point for tenant, identification, business, assignment, helper, rental, payment and contract information. Keep normalized existing records and established authorization, financial posting and original-template workflows. Settings owns business categories and versioned rates; Stall Management owns location and area. One server calculation supplies previews, new agreements, assessments, billing and contracts.

PHP 7.4 syntax, Laravel 8.83, MySQL, Blade, existing local Tailwind/JavaScript and installed packages remain. No framework upgrade, new calculation library, new business table, legal-text rewrite, payment duplication, automatic posting, workbook import or production backfill. Nothing grants official LGU approval merely because a user approves this software design.

## Discovery and baseline

- `Tenant` has businesses and assignments. `Business.tenant_id` is already a required foreign key. `StallAssignment.business_id` already exists and ownership is checked by `AssignmentService`.
- Business CRUD uses `RecordController`, `RecordRequest`, `Resources::all()['businesses']`, generic record views and `tenants.view/manage`. There is no separate business permission family to migrate. The standalone menu duplicates the workflow, not a second business table.
- Business columns to reuse: `name`, `category` (legacy free text), `description`, `permit_reference`, `registration_reference`, permit dates and `status`. `name` is currently required. There is no normalized business category, location classification or effective-dated CUSA/day policy table.
- Physical `stall_categories`, `stalls.classification`, market/section, area and layout positions remain distinct from commercial categories. Never rename physical categories into business categories.
- Assignments require manually selected `rental_rates` by market/physical classification. `rental_agreements.rental_rate_id` is currently non-null; its monthly amount is frozen. `BillingService` copies that monthly amount. Contracts independently accept a daily rate, CUSA and day count. Both sources must be refactored together.
- Existing `RentalCalculationService` already validates decimal strings and uses BCMath. Keep its arithmetic and half-up daily rounding; add authoritative resolution methods here.
- `active_stall_id` has a unique constraint; tenant/stall locks and date-overlap checks already exist. There is no enforced tenant concurrency policy.
- Posted bills, adjustments, payments and reversals have established immutability/append-only rules. Generated contracts retain encrypted snapshots, original private files and checksums. Reprints retrieve those files.
- Audit currently retains field names and only a small safe value allowlist. New non-PII category/rate/location changes need safe old/new values, without expanding raw tenant/ID logging.
- Read-only MySQL inspection on 2026-11-18 found all existing tenant/contract schema columns present. Counts: tenants 0, businesses 0, stalls 0, assignments 0, agreements 0, legacy rates 0, bills 0, payments 0, contracts 0. System settings: 2. No live data was changed.
- Linked businesses, orphans, ownership mismatches, duplicate normalized-name groups, unmapped businesses and unclassified stalls all counted 0. Category-rate discrepancies are not yet comparable because category schedules/location columns do not exist. These observations do not replace a deployment-time reconciliation.
- Fresh baseline: `C:\xampp\php\php.exe vendor/phpunit/phpunit/phpunit --configuration phpunit.xml --colors=never`: **125 tests, 789 assertions**, 149.277 seconds, 64 MB, PHP 8.2.12, forced SQLite `:memory:`. Evidence: `.qa/unified-baseline.txt`. Actual PHP 7.4 execution and MySQL concurrency are not established by this run.
- Both private reference checksums match existing assessments: XLSX `07304919451078da6c73f1948940d8302eec011af0b3ccf2f07b3c68afae2e18`; DOCX `38eb9c85d161d98a7f32f213efbefa3b810e682b72d1a9d4352a70cc13b014fb`.

## Approaches

1. **Recommended: extend the normalized architecture and reuse current services.** Add category schedules, location and immutable assessment provenance; nest business management in tenants. This preserves identifiers, historical records, integrations and private contracts. It requires updating assignment, billing and contract callers together.
2. Merge tenant/business tables or replace the financial engine. This destroys useful one-to-many relationships and adds migration risk; excluded by the brief.
3. Change only navigation or add a second calculator to the new form. Smaller superficially, but billing would still use manual monthly rates while contracts used another source; excluded because it does not meet the requirement.

The brief's preservation and centralization requirements leave approach 1 as the viable implementation. The following details are proposed implementation choices for approval.

## Data architecture

Use additive, forward migrations and restrictive foreign keys. No record deletion, inferred matching or live classification backfill.

| Existing/new entity | Proposed change |
| --- | --- |
| `tenants` | Preserve all profile/account/encrypted-ID/source fields; add explicit `draft` status to application validation and nullable encrypted `registration_draft` for pending stall/helper selections. No name-based uniqueness constraint. |
| `businesses` | Reuse table and legacy columns; allow `name` to be null for individual stallholders. Add nullable `business_category_id`, `line_of_business`, `permitted_goods`, `remarks`, `created_by`, `updated_by`. Preserve `description` and permit/registration references and dates. |
| `business_categories` (new) | Unique stable `code`, unique name, optional description, display order, active flag, actors and timestamps. No destructive delete UI. |
| `business_category_rental_rates` (new) | Category FK, DECIMAL min/max, nullable dates for drafts, draft/approved/retired state, source/reference, remarks, creator/approver/date. Effective dates and official reference required on approval. |
| `stalls` | Nullable indexed `location_type` (`inner`/`outer`); old rows remain null. New stalls require an explicit classification. Keep physical category, area, occupancy and graphical coordinates. |
| `rental_policies` (new) | Market FK, effective dates, draft/approved/retired state, daily CUSA, billing-day rule (`calendar` or explicit fixed count), concurrent-assignment limit, advance/deposit policy authorization, reference, remarks and approval actors. No seeded official policy or assumed dates/zero CUSA/30-day rule. |
| `stall_assignments` | Reuse existing tenant/business/stall FKs and occupancy constraint. Add optional concurrent-assignment authorization reference/reason. Business is required for new authoritative assignments, although its commercial name may be blank. |
| `rental_assessments` (new) | Immutable assignment-linked assessment, reference period, effective term, business/category/rate/policy IDs, exact component amounts, creator/time and encrypted identity/business snapshot. Freeze location, area, min/max/applied rate, daily rent, days, CUSA, totals and source IDs/names. No update/delete workflow. |
| `rental_agreements` | Preserve all existing rows. Make legacy `rental_rate_id` nullable and add nullable initial `rental_assessment_id`. An agreement must have exactly one source in application validation: legacy monthly rate or new assessment. Do not fabricate legacy rate rows for new agreements. |
| `rental_bills` | Add nullable `rental_assessment_id`; reuse existing bills/items as the assessment-to-ledger boundary. Keep existing unique assignment/period key, posting and corrections. |
| `lease_contracts` | Add nullable `rental_assessment_id`; extend existing encrypted snapshot with category/rate/policy/location/business provenance. Existing documents and snapshots stay unchanged. |

Use native migration schema operations supported by this installed stack. Before nullable-column changes, test the exact SQL/platform behavior on disposable databases; do not add DBAL merely to alter two columns. Keep SQLite test migrations and MySQL DDL behavior equivalent.

The business owns its current category. No second editable category FK on tenants/stalls/assignments. Assessment snapshots intentionally preserve the category that applied historically. Assessment decimal fields permit safe financial queries without decrypting identification data; sensitive identity snapshot remains private and hidden from model serialization.

## Category seeding and settings

`BusinessCategorySeeder` seeds these exact values and descriptions from the brief, using stable codes. Draft schedules have no fabricated effectivity or approval. Re-running never overwrites administrator changes or approved/history-linked schedules. A previously unapproved, unreferenced IT seed draft with missing values may be repaired; an approved record must never be silently repaired.

| Code | Exact category name | Inner minimum | Outer maximum |
| --- | --- | ---: | ---: |
| AMBULANT | Ambulant Vendors | 24.89 | 34.84 |
| PREPARED-FOOD | Prepared Food | 33.74 | 42.18 |
| CHICKEN | Chicken | 52.37 | 64.27 |
| COCONUT | Coconut | 18.27 | 22.84 |
| FISH | Fish | 52.37 | 64.27 |
| FRUITS | Fruits | 30.97 | 30.97 |
| GRAINS-STAPLES | Grains and Staples | 18.27 | 22.84 |
| GROCERY | Grocery Items | 24.89 | 34.84 |
| ICE | Ice | 23.57 | 33.63 |
| JEWELRY | Jewelry | 21.10 | 28.13 |
| MEAT | Meat | 52.37 | 64.27 |
| NIGHT-MARKET | Night Market | 24.89 | 34.84 |
| OTHER-SERVICES | Other Services | 21.10 | 28.13 |
| PARLOR-BARBERSHOP | Parlor/Barbershop and other similar services | 21.10 | 28.13 |
| RTW | RTW | 21.10 | 28.13 |
| SPECIALTY | Specialty | 52.37 | 64.27 |
| CELLPHONE | Cellphone, Cellphone Accessories, Gadgets | 34.84 | 40.00 |
| IT-EQUIPMENT | Other IT related equipments/items | 34.84 | 40.00 |
| SCHOOL-SUPPLIES | School Supplies | 24.89 | 34.84 |
| SWEETS | Sweets | 21.10 | 28.13 |
| VEGETABLES | Vegetables | 21.04 | 26.88 |

Settings → Business Categories contains category information, business/stall counts, current or draft rates, effective dates, history and approval actions. Category edits do not mutate approved rate rows. Approved schedules are immutable; replace them with an approved successor and an audited prospective retirement/end date, rejecting periods already used by an assessment. Serialize approval on the category parent to prevent overlapping approved inclusive date ranges. Missing/ambiguous schedules fail closed.

Manage effective-dated rental policies in a focused section of the existing Settings interface, using the same approval principles. Fixed days must be supplied explicitly; calendar days come from the requested billing month, including leap years. Daily CUSA must be explicitly supplied, including an explicitly approved zero. Advance remains two base months and security deposit one base month, excluding CUSA, as already specified; these computed amounts are not automatically posted as deposits or credited as payments.

Concurrency defaults to a single new active assignment across all markets. Count every overlapping active assignment for the tenant, including legacy ones. For an additional assignment, the approved operational policy effective on its start date in every involved market must permit the proposed global count; a missing policy or any lower cap blocks it. Each additional concurrent assignment also requires an authorization reference/reason, checked while holding the tenant lock. This conservative cross-market rule is a proposed technical default for approval, not an assertion about an existing ordinance. Existing concurrent history is not altered. Operational concurrency permission is checked for the new action; it does not rewrite frozen financial policy in earlier agreements.

## Unified registration and profile

Reuse the tenant form and its validated service, structured into the seven requested sections: personal, identification, business, stall, rental, helpers, review. Use repeatable business blocks with a tenant-owned business key per assignment. Stall search filters eligible records; location/area/category rates and computed values are read-only.

Draft save persists the tenant (`draft`) and owned business records without activating occupancy, issuing assessments or creating a lease. Unsaved stall choices are not reservations. Helpers remain assignment-scoped: pending business IDs, stall/term selections and helper drafts remain in the tenant's hidden encrypted `registration_draft` until explicitly confirmed; no duplicate tenant identity or computed monetary values are stored there. Confirmation revalidates availability, ownership, current configuration and at most two helpers per assignment, then creates existing helper slots atomically and clears the confirmed draft entries. No orphan helper rows or fake active assignments.

Active confirmation uses one transaction for tenant, business, assignments, initial assessments, agreements and helpers. Reuse `TenantDataService` and `AssignmentService`; do not put financial writes into controllers. Validate every branch before committing, enforce separate permission checks and prevent stale/double submissions using a submission key and request hash scoped to the registration operation. Store only the minimal pending registration information needed for drafts; it contains no second copy of tenant identity data.

An existing tenant can save personal/business changes without assignment-management permission. New assignment confirmation requires `assignments.manage`; category/schedule/policy approvals remain separate. Registration never activates a lease contract.

Profile uses the nine requested tabs with server-rendered links and accessible progressive enhancement: Overview, Personal Information, Businesses, Stall Assignments, Rental Information, Authorized Helpers, Payment History, Lease Contracts, Activity History. Nested business CRUD returns to its tab and never needs an independently editable tenant selector. Archive retains referenced rows; active linked assignments must be ended before business archival. Ownership changes with history are rejected. Category changes on an active business are prospective metadata only and explicitly warn that existing assessments/contracts retain the agreed category; new terms require authorized renewal.

One tenant row in the paginated list shows business/category and stall/location summaries, stored monthly amounts, payment/lease/status summaries and authorized actions. Search spans names, business names, stall codes and string account numbers; filters distinguish business category from physical stall category. Financial/payment details require their existing permissions and aggregate through the ledger instead of exposing totals to every tenant viewer.

Potential duplicate registration is flagged by normalized name/account/phone matches for staff review, without silently merging records or imposing uniqueness on names/phone/shared addresses. Tenant reference stays unique; blank account numbers and leading zeroes are preserved.

## Authoritative calculation and historical behavior

Extend `RentalCalculationService` to resolve a business, verified stall, approved category schedule and approved market rental policy. Resolve inside the write transaction and lock relevant parents in one documented order. The category schedule/policy must be effective at the new agreement commencement. Inactive business/category, mismatched ownership, unclassified/invalid-area stall, missing approval or ambiguous period prevents assessment.

The service selects minimum for Inner and maximum for Outer, then uses the existing exact arithmetic: round daily rate × area half-up to two decimals, multiply by approved days, add daily CUSA × days, calculate two base months advance plus one base month deposit. No rate, min/max, area, location or computed amount submitted by the browser is trusted. A protected preview endpoint returns the same calculation and clear missing-configuration messages; it performs no writes. JavaScript replaces stale previews and handles loading/error states without a new frontend package.

Initial assessment freezes the agreement basis. Subsequent bills and new contracts for that agreement use this basis, never current business/stall/rate edits. Calendar-day policies calculate the specific requested month's days from the frozen rule, not a new rate schedule. Each monthly bill receives an immutable period-specific assessment and separate base-rent/CUSA items whose exact sum equals the bill amount. Existing full-month-only eligibility, backdate permission, draft/post separation and no advance-credit behavior stay in force.

The contract displays the approved reference month's breakdown using that same basis and billing-day rule; reference month is explicit in the app and existing billing-basis placeholder. A lease spanning calendar months with different lengths does not silently promise one fixed monthly amount for all months. No proration or new visible contract fields are invented.

Legacy agreements are explicitly labeled as legacy monthly snapshots. Existing bills and continuing legacy billing retain that amount. Manual rate selection is removed from all new assignment/transfer/renewal/contract paths, including compatible endpoints; sending only a legacy rate ID cannot create a new authoritative agreement. A legacy assignment needs an authorized same-stall renewal with a verified business/category/location and approved policy before producing a new category-derived contract. Already generated/issued legacy contracts remain downloadable without recalculation; ungenerated old drafts are retained and explain the renewal requirement.

Stall location/area changes are audited and require a reason when history exists. They never mutate an assessment. A renewal creates a fresh assignment/agreement/assessment through the existing month-boundary workflow; no in-place historical reassessment is introduced.

## Contracts, map, reports and navigation

Preserve private original DOCX preparation/generation, XML escaping, encryption, checksums, signed-upload constraints, numbering/visual-review/policy gates and separate approval/issue/sign/activate lifecycle. Load verified business goods/category from the agreement's assessment snapshot instead of re-entering them. A later business trade/category edit cannot be combined with an older agreement's rental basis in a new contract: an authorized renewal and fresh assessment are required first. This avoids mixing current trade details with a different frozen rental category. Populate the existing category/premises and rate placeholders without adding visible fields or modifying Sections 1–24. Retain the invalid-date rejection. No renderer is installed: successful PDF conversion and original nine-page Word fidelity require authorized external visual review.

Map retains positioning/editor and adds location/category filters and permission-filtered details. Occupied-stall values come from its assessment/agreement, with separate clearly labeled current master-data values where changed. Last payments come from existing effective payments/allocations, not copied fields. Add links to tenant tabs and contract records only when permitted.

Reuse `Reports` and existing CSV/XLSX/PDF/print export infrastructure for Business Category, Rental Assessment and Lease Contract Registry reports; reuse tenant-master and occupancy reports. Historical categories, rates and location use assessment snapshots. No report decrypts government IDs for general exports. Existing 16 reports remain available with unchanged privacy gates.

Navigation adopts Dashboard, Management, Transactions, Reports and Settings as requested, and keeps implemented Audit Logs, System Settings and Help & Support discoverable. Move Users and Roles to Settings. Remove only the standalone Businesses entry after nested CRUD is verified. Physical Stall Categories and legacy Rental Rates remain available as appropriately labeled settings/history links because they have distinct existing uses. Existing assignments remain reachable within Tenant Management and through compatible URLs. Do not link unimplemented custom-role editing.

Legacy Business GET pages redirect to the tenant Businesses tab or unified business search/reconciliation view; preserve authorization before redirect. Legacy POST/PUT routes call the same new business validation/service and preserve existing field aliases, ownership/history guards and response expectations. Category free text is retained as legacy metadata but cannot substitute for a normalized approved rate category. No generic CRUD path may bypass the new rules.

## Authorization, privacy and audit

Reuse `tenants.view/manage`, `assignments.view/manage`, `stalls.view/manage`, `rates.view/manage`, `billing.*`, existing contracts and report permissions. Add only a distinct `rates.approve` authority, initially Super Administrator; Market Administrator may prepare category rates/policies with existing rate-management permission but cannot approve them. Existing sensitive identity visibility stays separate. No broader tenant grant results from migration; custom business-only permissions, if discovered in deployment, must be mapped explicitly before a redirect grants access.

Check active account, permission and object ownership in requests/controllers and write services. Settings category creation/metadata uses `rates.manage`; schedule and policy approval uses `rates.approve`. Classifying/editing area requires `stalls.manage`; registration never writes stall master fields.

Audit actor/entity/time/reason and safe old/new values for business category IDs, location, area, schedule amounts/dates/state and policy references. Never record government IDs, raw names/addresses/passwords or complete encrypted snapshots. Tenant profile activity shows sanitized authorized events from existing logs. Downloads remain authenticated, allowlisted and private; CSRF and upload checks remain.

## Reconciliation, deployment and rollback

Provide a read-only reconciliation command/report with record counts, linked/unlinked businesses, duplicate candidates, invalid ownership, missing location/category, legacy agreements and discrepancies where comparable. Keep PII-bearing reconciliation exports private and permission-controlled; public handoff documentation uses counts only. Rerun immediately before/after any approved deployment/backfill and compare financial totals. Do not infer tenant ownership/category/location from names, coordinates or stall codes.

Orphan reconciliation requires explicit tenant selection, reason and authority, and rejects conflicts with existing assignment/financial history. Businesses with no reliable parent remain quarantined from authoritative assessment. No automatic production data repair/backfill is part of running migrations or seeders.

Before implementation, capture a source inventory/backup because this folder currently has no Git repository. Do not initialize a repository, move the checkout or create a fake worktree without need; use a workspace-local rollback copy excluding secrets and generated documents. Record the lack of Git history. Before deployment, the operator backs up MySQL, private documents and APP_KEY separately.

Forward deployment (after implementation verification and separate operational authorization): ordinary `php artisan migrate`, `php artisan db:seed --class=RoleSeeder`, `php artisan db:seed --class=BusinessCategorySeeder`, asset/cache refresh, reconcile and role/UAT checks. Draft seeds alone do not enable assessment. Authorize rate dates/references, CUSA/day policy and stall/category classifications through the application. No `migrate:fresh`, reset, truncate or live backfill.

Rollback application files from the approved checkpoint while retaining new tables, columns and records. Do not run destructive down migrations after new data exists, or restore an old database over newer financial transactions. Use a corrective forward migration if needed. A down migration is only a disposable-test validation tool.

## Implementation sequence after design approval

The detailed TDD implementation plan will be written under `docs/superpowers/plans/` using writing-plans immediately after design approval, as required by the installed workflow. Sequence:

1. Snapshot source and capture migration/reconciliation baseline; failing schema/seeder/history tests.
2. Add compatible schema/models, safe seeds, settings/approval workflows and tests.
3. Add stall classification/filter/audit checks, shared resolver and exact calculation tests.
4. Refactor assignment/renewal, assessment, billing and contract callers together; retain explicit legacy regression coverage.
5. Implement nested business CRUD, transactional seven-section registration/drafts, nine-tab profile and dynamic preview.
6. Integrate map, reports, compatible endpoints and navigation; test all permission boundaries.
7. Independent review, regression verification, real synthetic browser checks, manual/Help updates and exports, deployment/rollback handoff.

Use isolated subagents for bounded independent tasks/reviews with no overlapping file ownership. Observe red/green evidence before new logic; investigate failures before fixing. No implementation skill/product edits before design approval.

## Acceptance and documentation

Cover all six supplied examples, fractional-area/rounding/overflow cases, leap-year/calendar/fixed days, explicit zero CUSA, missing/overlapping/unapproved policies/schedules, concurrent assignments and approvals, ownership/IDOR, tampered input, idempotent seeding/submissions, drafts/atomic rollback/helpers, filters/tabs/legacy redirects, inactive/archived records and private downloads. Assert old agreements, contracts, posted bills, allocations/payments and balances are byte/value unchanged after master-data edits.

Run full isolated SQLite suite, PHP 7.4 grammar, changed-file native lint, Blade cache, asset build and source/document cross-checks. Run MySQL migration/concurrency checks only against an explicitly disposable testing database; no live MySQL test writes. Browser-test fictional desktop/tablet/mobile registration, business/category/location flows, formula refresh, navigation and permission states. Distinguish actual browser checks from source/unit evidence.

Update AGENTS and project memory/current-state/decisions/handoff after each completed phase. Produce requested unified/category/masterlist/calculation/location/migration documentation, update contract mapping, and mark conflicting manual-rate/separate-business requirements superseded. Update affected user-manual chapters, screenshots, workflow sources, permission/report matrices and authenticated Help pages; rebuild PDF/DOCX/quick-reference/assets and verify exports. Word rendering remains a stated limitation until a compatible renderer is available.

Completion deliverable includes exact file inventory, additive migrations, commands, reconciliation results, synthetic screenshots, test/review evidence, security findings and remaining operational/renderer limits. Software acceptance never substitutes for approved financial policy or production authorization.

## Approval requested

Approve this technical design to proceed to the detailed implementation plan and actual implementation. Key proposed choices: immutable assignment/period assessments, unapproved seed schedules, explicit effective-dated market policies, safe draft registration, grandfathered legacy billing and renewal-only adoption of new category rates for legacy assignments.

Independent builder check completed before handoff. Its two material questions were resolved in this revision: cross-market concurrency checks all involved policies against the global count; changed business trade details require renewal before use in a new contract for a different assessment basis. These are proposed implementation choices included in the approval request.


Implementation clarification (2026-11-18): assessment identity remains an immutable historical snapshot. For a draft contract's first document generation, verify current tenant identification and assignment helpers, then freeze them in the generated contract. This permits completing identification after assignment without changing any prior assessment or generated document. The agreement's frozen business/trade/category/rental basis is still mandatory; changed trade requires renewal. Ledger records the rationale and regression evidence.
