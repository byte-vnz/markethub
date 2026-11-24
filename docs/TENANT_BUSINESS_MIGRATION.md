# Tenant/business migration discovery

Checkpoint: 2026-11-18, design approval pending. No feature migration or live data backfill has been executed.

## Current relationship and duplicate-interface assessment

`tenants` → `businesses` is already one-to-many with a required foreign key. `stall_assignments` stores both tenant and optional business, and the assignment service checks ownership. Existing business name, legacy free-text category, description, permit/registration references, dates and status must remain available. Business CRUD currently uses generic record routes/controller/views and shares `tenants.view/manage`; there is no separate business-only permission in the shipped role matrix.

The separate Businesses menu/form is the duplicate workflow. The business table is not redundant. Physical stall categories are distinct from commercial business categories and must remain distinct. New commercial category mapping must never be inferred from tenant names, stall codes or map coordinates.

Legacy agreements freeze a selected market/physical-classification monthly rate; bills copy that amount. Contracts currently accept independent daily-rate inputs. This dependency must be changed across assignment, billing and contracts together, while keeping historical amounts/files intact.

## Read-only live reconciliation

Inspected the configured existing MySQL database within `START TRANSACTION READ ONLY`, then rolled back the inspection transaction. Capture: 2026-11-18 17:33 Asia/Manila. Schema/aggregate-only evidence: `.qa/unified-live-assessment.json`; no names, IDs, credentials or record contents were exported.

| Check | Observed count |
| --- | ---: |
| Tenants | 0 |
| Businesses | 0 |
| Businesses linked to existing tenants | 0 |
| Orphaned businesses | 0 |
| Potential duplicate normalized tenant-name groups | 0 |
| Assignment/business ownership mismatches | 0 |
| Assignments without a business | 0 |
| Stalls / assignments | 0 / 0 |
| Legacy rates / agreements | 0 / 0 |
| Bills / payments / contracts | 0 / 0 / 0 |
| Businesses needing normalized category mapping | 0 |
| Stalls needing location classification | 0 |

Posted bill total, gross posted payment total and payment-allocation total were all zero. Gross posted payment total is a control total, not a net collection or balance calculation. Legacy/category rate discrepancies cannot yet be compared: category schedules and verified stall locations do not exist. Existing profile/contract columns are present; the currently configured installation has already received those earlier migrations. This observation does not authorize another deployment or change the older checkpoint evidence.

Zero records here does not remove compatibility requirements for other installations or future data. Repeat reconciliation and exact-decimal financial controls immediately before and after any approved migration or backfill. Review duplicate candidates without auto-merging, and leave ambiguous records unresolved.

## Proposed migration and rollback

The full proposed additive schema and preservation rules are in [the technical design](superpowers/specs/2026-11-18-unified-tenant-management-design.md). New schedules seed as unapproved, undated drafts; existing locations remain unclassified. No business deletion, automatic ownership matching, production classification backfill or retroactive recalculation.

Before an authorized deployment, back up the database, private generated/source documents and APP_KEY through the operator's protected process. Record source-file inventory because this workspace has no Git repository. Forward migrations and seeders must preserve existing record counts and financial control totals. Approval of software design does not approve rates, financial policy or live backfills.

Rollback after new data exists retains additive schema/data and restores reviewed application files or applies a corrective forward migration. Do not run destructive down migrations or restore an old database over newer financial transactions.

## Baseline verification

Fresh isolated SQLite baseline passed **125 tests, 789 assertions** in 149.277 seconds on PHP 8.2.12. Evidence: `.qa/unified-baseline.txt`. A diagnostic rerun also passed the same suite in 76.341 seconds; the initial output delay was not a failing test. Both private reference checksums match their existing documented values. No new feature behavior is implemented or tested at this checkpoint; actual PHP 7.4 runtime, MySQL concurrency and Word contract fidelity remain pending.

## Final implementation and reconciliation — 2026-11-19

The design was approved and implemented. The preceding discovery sections describe the pre-implementation checkpoint, not current source behavior. Business CRUD now uses nested tenant routes and BusinessService; old GET endpoints redirect while authorized POST/PUT clients use the shared service. No business table, historical relationship or endpoint was dropped. No source data matching, merging, production classification or backfill was performed.

Forward migrations 2026_11_18_000003 through 000005 add category/rate/policy masters, nullable legacy-compatible business/stall fields, encrypted MEDIUMTEXT registration drafts, immutable rental assessments/links and durable registration submission keys. SQLite preservation/rollback tests cover populated legacy rows and pre-existing orphan violations. MySQL native ALTER execution remains pending. New assessment links are nullable for historical agreements/bills/contracts.

Read-only `php artisan tenants:reconcile` was rerun at 2026-11-19 00:28 Asia/Manila. Existing operational counts and all four posted/effective financial controls remain zero; new category/policy/assessment tables are absent. Evidence: `.qa/unified-reconciliation-current.json`. Thus there are zero live businesses to migrate in this installation; compatibility with populated legacy data is verified in disposable tests. Reconcile again immediately before/after operator deployment, preserve ambiguous ownership, and obtain explicit classification/category/rate/policy authority.

Deployment/rollback commands and full file inventory: UNIFIED_IMPLEMENTATION_REPORT.md and UNIFIED_FILE_INVENTORY.md. Do not use destructive rollback after new records or registration keys exist.
