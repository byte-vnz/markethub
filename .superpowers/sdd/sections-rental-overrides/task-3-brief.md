# Task3: Tenant workflow, reports, map and navigation

**Files:** create app/Http/Controllers/RentalOverrideController.php, app/Http/Requests/RentalOverrideRequest.php, routes/rental-overrides.php, resources/views/rental-overrides/*, resources/js/rental-overrides.js and tests/Feature/RentalOverrideWorkflowTest.php. Modify TenantController/profile, MapController/details, Reports/ReportController/report filters, config/navigation.php, routes/web.php and asset builder.

**Interfaces:** nested /tenants/{tenant}/assignments/{assignment}/rental-overrides route group; rental-overrides.index/create/store/edit/update/preview/submit/approve/reject/revoke names. Controller validates tenant→assignment ownership before mutation/preview; all authority stays in Task2 services. Report type rental-overrides with rentals.override.report; no new global Business navigation.

- [ ] Write/observe failing endpoint/IDOR/permission tests for request preview/submit/approve/reject/revoke/history and persisted submission intent. Authoritative values ignore/reject manipulated client amounts. Read-only roles cannot change rent.
- [ ] Implement comparison/form/state actions in existing Rental Information tab and nested screens, method switching only shows one editable amount, server preview plus keyboard/mobile validation; registration points to saved assignment before override request. Preserve tenant/assignment/reference month context on validation/retry.
- [ ] Add mode/active-approved indicator/section to map, gating monetary and approval details. Add Rental Override Report with exact spec columns and relevant section/category/mode/status/as-of filters. Historical assessment reports read stored mode; pending report does not imply application. Test screen/CSV/XLSX/PDF/print filter parity and viewer masking; no unsupported filter controls.
- [ ] Reuse one Settings Sections and one Reports Override entry; local build integration, no package/CDN. Run focused HTTP/nav/map/report suites green and submit report/diff for task review.

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


## Task2 confirmed API/payload contract (coordinator)
Draft payload: submission_key UUID; override_type rate_per_sqm|daily_amount; exactly one override_rate_per_sqm or override_daily_amount; reason; optional reference_document/remarks; effective_from/effective_until; optional source_override_id for new version. Optional tenant_id/business_id/stall_id/stall_assignment_id mustmatch existingassignment. Revocation payload revocation_effective_from and reason.
RentalOverrideService::preview(User,StallAssignment,array) accepts override_type/oneamount/period(Y-m), returns proposal numericfields plus out_of_range/zero_rent. Proposals are never authoritative; show proposedrent label and approvalrequired. forAssignment returns only validapprovedmanual orautomatic currentfinal. Pending detail needs distinguishrequested amount fromcurrentapplicableautomatic.
forAssignment fields: computation_mode automatic|manual_override, override_type, rental_override_id, automatic_rate_per_sqm, automatic_daily_rental, rental_difference, equivalent_rate_per_sqm, contract_rate_consistent, existing monetaryfields, approved metadata under override. Modeldates Y-m-d strings. Verify finalreport/methodsignaturesafterTask2reviewbeforecodingUI.
- Approval detail must obtain policy summary and fingerprint from one RentalOverrideService::review($override) call, returning ['policy'=>?array,'fingerprint'=>string]. Render that exact policy reference/authorizationflags/effective dates and post that exact fingerprint; do not independently querypolicy/fingerprint (stale display risk). Verify final Task2report for methodvisibility and returnedarraykeys.
- Frozen request report values: automatic_daily_rental, proposed_daily_rental, rental_difference (already rounded/calculated; no SQL/frontend subtraction or multiplication). Area from initialAssessment.floor_area. Authorizing policy FK rental_policy_id and frozen authority_snapshot; draft/pending/rejected have no approval snapshot. Confirm relation FKname and finalmetadata in Task2report beforeuse.
- Historical UI guard: TenantRentalInformation includes endedassignments; current-month forAssignment() rejects periods outside owningterm. Show stored historicalassessment for ended/ineligible assignments and disable current preview/request ratherthan breakingprofile/map. Map maystillshow occupiedactive status after end_date until explicitclosure; don't invent current rental outside financialterm. Label initialsnapshot vs applicable referenceperiod clearly.
- Approved/revoked/expired detail uses frozen authority_snapshot for historical policy/reference; only pending approval form uses fresh review()['policy','fingerprint']. Policyretirement mustnot make oldapprovalhistory look unapproved.
- Draft edit HTML must submit required draft_fingerprint from RentalOverrideService::fingerprint(displayedDraft), and pass it to saveDraft. Submit HTML must also bind the displayed draft fingerprint at HTTP boundary before calling submit(); then service stale-model/request_hash checks close races. Route binding alone loads latestrow and cannot identify an old openpage. Preserve idempotent repeated transitions without overwriting newerterms. Report documents API specifics.
