# Task1: Existing Sections, stalls and map

**Files:** create database/migrations/2026_11_19_000001_enhance_market_sections.php, app/Services/SectionService.php, app/Console/Commands/ReconcileSections.php, resources/views/sections/*, tests/Feature/SectionManagementTest.php. Modify MarketSection, RecordController/RecordRequest/Resources/RecordPolicy, generic stall form/list, MapController/map views, routes/web.php, RoleSeeder, Audit, config/navigation.php as needed.

**Interfaces:** SectionService::save(User $actor,array $data,$id=null):MarketSection; reorder(User $actor,$marketId,array $ids):void. Existing records.* URLs for type=sections remain canonical. Command sections:reconcile is read-only and safe before metadata migration. New sections.view/manage permissions; no delete route. Reuse status(active/inactive), market_section_id and scoped code uniqueness.

- [ ] Write tests test_section_master_counts_and_metadata (create/edit/status/order/counts), test_section_duplicates_and_existing_ambiguous_spelling, test_stall_options_and_backend_reject_inactive_or_other_market_sections, test_section_rename_map_preserves_coordinates, test_section_routes_enforce_permissions_and_preserve_referenced_records, test_populated_metadata_upgrade_and_read_only_reconciliation. Expected sample counts: oneoccupied/onevacant/onemaintenance gives3total and individual1.
- [ ] Run new tests before implementation; retain meaningful red output in .qa/sections-task1-red.txt.
- [ ] Implement additive nullable metadata/actors plus sort_order0, model relationship and transactional service validation/normalized comparison/parent locks. Keep occupied-deactivation and historical movement safeguards.
- [ ] Enhance existing section routes/views and scoped searchable stall options; counts from existing rows, dynamic names/map summary, configured color secondary to occupancy. Preserve inactive current option for unrelated stall edits. Add new permissions additively and audit safe before/after names/code/color/section fields.
- [ ] Implement read-only reconciliation with normalized duplicate groups, orphan/mismatched references and unchanged controls; no auto-backfill/rename. Write SECTION_MANAGEMENT.md and update SECTION_DATA_MIGRATION.md with actual source status.
- [ ] Run focused tests plus existing map/navigation/integration regressions green. Save report and source diff for task reviewer; no commits/live migrations.

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

