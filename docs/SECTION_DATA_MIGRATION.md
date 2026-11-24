# Section data reconciliation

The section enhancement is implemented in source following the approved design in `superpowers/specs/2026-11-19-sections-rental-overrides-design.md`. The live discovery below was captured on 2026-11-19 at 01:10 Asia/Manila. Live section migration and backfill remain pending operator authorization.

## Existing architecture

Physical sections use `market_sections` with required `market_id`, scoped unique code, name, active/inactive status and timestamps. `stalls.market_section_id` references the master. Business categories and Inner/Outer classification remain separate concepts. The enhanced Settings → Market Sections link uses the existing record URLs.

`2026_11_19_000001_enhance_market_sections.php` adds nullable `description`, `color`, `created_by` and `updated_by` columns and `sort_order` with a zero default. The actor columns reference users. The migration requires no data conversion, matching or backfill. Existing IDs, names, codes, stall links, coordinates and downstream records stay as stored.

## Live read-only findings

Inspection ran inside a MySQL read-only transaction and exported schema/physical-section metadata and aggregates only; no tenant personal information or credentials.

| Check | Count |
| --- | ---: |
| Existing section master records/distinct section values | 0 |
| Stalls | 0 |
| Layout positions | 0 |
| Orphaned stall section links | 0 |
| Cross-market section links | 0 |
| Normalized duplicate section-name groups | 0 |

Current table columns: `market_sections` has code,created_at,id,market_id,name,status,updated_at. Stalls already have market_section_id and location_type. Evidence: `.qa/sections-overrides-live-discovery.json`.

The prior unified schema/category rollout is now visible:21businesscategories,21categoryrates,0rentalpolicies,0assessments. This is an external state change since the previous handoff, observed read-only; no live migration/seed was run by this discovery. Operational tenants/businesses/stalls/assignments/agreements/bills/payments/contracts remain zero. Posted bill, gross/effective posted payment and effective allocation controls are all0.00. Evidence: `.qa/sections-overrides-live-controls.json`.

There are therefore no current live section values to match/backfill. Do not invent records for an empty installation. Populated-installation compatibility must still be tested with synthetic fixtures.

## Verification and rollback

`php artisan sections:reconcile` prints read-only JSON before and after the metadata migration. It lists section values by market, normalized name candidates, orphaned and cross-market stall IDs, status counts and suspected legacy stall section columns. `preservation_controls` includes stable hashes for original section values, stall links and layout positions, counts for assignments and financial records, posted amounts, effective payments/allocations and outstanding balance. Compare these controls in a quiet maintenance window, when ordinary operations cannot change them between captures.

An installation with free-text section columns requires an explicit mapping preview and separate backfill authorization. Preserve raw legacy values until a reviewer verifies the mapping. Review possible duplicates and bad links before making any correction. The current schema needs no text-to-FK conversion.

Before an authorized deployment, back up the database, private source/generated documents and APP_KEY through the operator's protected process. Save the reconciliation output to restricted storage. After the reviewed forward migration, run the additive `RoleSeeder` under the same authorization and confirm `sections.view` and `sections.manage` grants. Customized roles require explicit grants. Verify the intended users' Settings link and direct section URLs, then capture the reconciliation output again. An in-memory SQLite upgrade test verifies populated rows, a cross-market link, name ambiguity, FK integrity and matching preservation controls. MySQL upgrade and lock contention remain unverified.

Use corrective forward migrations after operators add section metadata. Keep database restore and compatible source rollback under the operator's protected process so newer financial records are preserved.

The final read-only reconciliation on 19 November 2026 at 21:10 Asia/Manila confirmed the same physical counts and financial controls. Section metadata columns and operational rental overrides remain pending deployment. The two reports are saved in `.qa/sections-final-live-sections.json` and `.qa/sections-final-live-tenants.json`. See [operator rollout](SECTION_OVERRIDE_IMPLEMENTATION_REPORT.md#operator-rollout).
