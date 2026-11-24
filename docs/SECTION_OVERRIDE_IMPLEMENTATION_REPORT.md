# Market Sections and rental override implementation

The approved enhancement is implemented in the MarketHub workspace. Settings → Market Sections maintains the existing physical master and displays descriptions, color, order and occupancy counts. Stall Management references that master and searches active sections for the selected market. Market map labels reflect the current master while layout positions and assessed details retain their recorded values. Business categories and Inner/Outer classification serve separate purposes.

Automatic assessment remains the default, using the approved minimum for Inner stalls and maximum for Outer stalls. The manual option is scoped to one assignment and uses an approved rate per square meter or direct daily amount for prospective full months. A separate authorized user must approve the exact proposal against policy covering the entire period. Pending requests do not affect billing. An approved exception requires the explicit applicable policy grant and authority references. The earlier prohibition against all manual overrides is superseded by this workflow.

The tenant's Rental Information, market map and Rental Override Report distinguish applicable terms from proposals and historical amounts. A new bill or lease assessment freezes the applied basis. Return to automatic takes effect from a permitted future month and restores the original automatic basis. The workflow preserves existing financial and contract snapshots and the bytes of generated originals.

## Schema and files

| Migration | Additions |
| --- | --- |
| `2026_11_19_000001_enhance_market_sections.php` | Section description, color, display order and creator/updater links. Existing IDs, codes and stall foreign keys retained. |
| `2026_11_19_000002_create_rental_overrides.php` | Assignment requests and immutable decision history, authority flags/references on rental policies, and mode, section, override, comparison and contract precision fields on rental assessments. Existing assessments default to automatic. |

See the [source file inventory](SECTION_OVERRIDE_FILE_INVENTORY.md), [Section guide](SECTION_MANAGEMENT.md), [rental override guide](RENTAL_OVERRIDE_MANAGEMENT.md), [test results](SECTION_OVERRIDE_TEST_RESULTS.md) and [v1.2 manual package](user-manual/README.md). The file inventory uses a protected source snapshot because this workspace has no Git history.

## Operator rollout

The operator will apply the migrations and grants after reviewing the release in a disposable MySQL copy. Use a maintenance window and make verified protected backups of the database, private templates/generated documents, application source and APP_KEY. If an upgrade rehearsal detects discrepancies, stop and resolve them before the live rollout.

From the project root, save the before and after reconciliation reports in restricted storage. Use the installed PHP executable for these commands.

```powershell
php artisan sections:reconcile
php artisan tenants:reconcile
php artisan migrate:status
php artisan migrate
php artisan db:seed --class=RoleSeeder
php artisan config:clear
php artisan route:clear
php artisan view:cache
php artisan sections:reconcile
php artisan tenants:reconcile
```

Compare IDs, row counts, layout/link hashes and financial controls before resuming operations. Check intended role grants and custom access, confirm the Section link and tenant Rental Information, and verify the report and Help downloads. Release assets are built with `npm run build`. Use approved rental policies and documented authorization for any real exceptions. The seeded category schedules are drafts until approved. Default administrator seeding is governed separately by the local opt-in.

Use a reviewed forward correction if the additive schema or data needs repair. A compatible prior source release can restore service where verified against the newer schema. Once any new rental records exist, downgrading the source without review could bypass their rules. Preserve the database and original documents, and coordinate any restore with the operator so post-release transactions are protected. The override migration deliberately refuses destructive downgrade.

The final live read-only reconciliation found zero operational sections, stalls, layout positions, tenants, assignments, assessments, bills and contracts. It found 21 categories and 21 category rate records, and zero rental policies. Posted balance controls were PHP 0.00. No live migration, seed, backfill, policy approval or legal contract issuance was performed.
