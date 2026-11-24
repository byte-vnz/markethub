# Task 3 workflow and reporting handoff

The assignment-scoped rental override UI, map previews, and report integration are implemented. Task 2 remains the authority for comparison values, terms, permissions, effective boundaries, and financial locks. Task 3 reads supplied service results or persisted assessment/request fields. All development fixtures and tests used isolated SQLite with fictional records.

## Tenant and approval workflow

- Tenant Management → tenant record → Rental Information uses `GET /manage/tenants/{id}?tab=rental&period=YYYY-MM`. It shows each assignment's business and current section, the assessed section if different, applicable-month automatic and final daily amounts, applicable mode, and the saved initial assessment. The selected month is preserved through links and actions. A pending count requires override view/history rights. An out-of-term or legacy assignment retains its saved basis and history. New requests require an eligible full prospective month on an assessed assignment of an active tenant and business.
- The “Choose rental mode” selector defaults to Automatic. Selecting Manual override request opens the method and amount form. Selecting Automatic returns to the profile or takes an approved/scheduled arrangement to Override history with directions for a prospective Return to automatic. The selector only navigates. Confirmed registrations with assignments offer an “Open Rental Information” link for authorized requesters.
- All nested routes are under `/tenants/{tenant}/assignments/{assignment}/rental-overrides`. Each authorizes the tenant, verifies that the assignment belongs to the tenant, and checks the override's owning assignment before any action or preview.

| Method and suffix | Route name suffix | Required override grants in addition to tenant view |
| --- | --- | --- |
| GET base | index | view, history |
| GET /create | create | view, request. Revising a submitted request also requires history |
| POST base | store | view, request. Revising a submitted request also requires history |
| POST /preview | preview | view, request |
| GET /{override}/edit | edit | view, request, original requester |
| PUT /{override} | update | view, request, original requester |
| POST /{override}/submit | submit | view, request, original requester |
| POST /{override}/approve | approve | view, history, approve, distinct reviewer |
| POST /{override}/reject | reject | view, history, reject |
| POST /{override}/revoke | revoke | view, history, revoke |

The name prefix is `rental-overrides.`. Create links accept the selected `period`, the navigation-only `computation_mode` of `automatic|manual_override`, and the optional `source_override_id` for a new revision. Preview receives one editable amount, the selected method, and the proposed start month. It returns the frozen automatic reference and server-computed proposal. The form shows approval-required, exception authority/CUSA, and lease-template precision messages where applicable. The supplied request reason, optional reference/remarks, and full-month dates are validated. A UUID binds create retries. Save Draft keeps editable state. Submit for Review on creation uses one transaction for the draft and its submission. An existing draft is edited/saved separately and requires the fingerprint of the saved terms both to edit and submit. Exact repeated pending submissions are idempotent. A stale display of mutable draft terms fails. A draft's submit control is disabled when edits are unsaved.

History shows the reference-month applicable comparison and independent request status. Pending or future approved proposals are labeled separately from the currently applicable amount. A reviewer sees the policy reference, effective dates, exception flags, and matching approval fingerprint from one policy review read. Approvals bind that displayed result. Previously approved requests use the recorded authority snapshot. Rejection and return to automatic keep reasons and recorded dates. Revision creates a new request linked to the submitted row. Section names including `0` and inactive labels retain their meanings.

## Map and reports

- Map keeps the existing market, section, occupancy, location, and business category behavior. The Rental reference month (`period=YYYY-MM`) and Rental computation (`computation_mode=automatic|manual_override`) controls require `tenants.view`, `billing.view`, and `rentals.override.view`. The same month reaches `GET /map/stalls/{stall}`. Map details distinguish frozen initial financial information from the separately labeled preview. Manual application shows an approved badge only when the period resolver selects an override. The history link additionally needs `rentals.override.history`. Management viewers retain physical occupancy information without override details. Tenant Management's new `section_id` filter combines with the existing stall scope.
- Reports → Rental Override Report is `GET /reports/rental-overrides`, protected by `rentals.override.report`. Supported filters are `market_id`, `section_id`, `stall_id`, `tenant_id`, `business_category_id`, `as_of=YYYY-MM-DD`, `computation_mode=automatic|manual_override`, and `status=draft|pending|approved|rejected|revoked|expired`. The status is based on current recorded decisions and the selected applicability date. Approval may be scheduled. An expired or pending row does not represent manual application. If another row supplies applicable manual rent or the assignment is outside term, the selected row's applicable mode and values are blank. Status remains visible. The separate proposal and proposed difference are always retained.
- The override report has request ID, tenant, business, stall, assessed section/category/area, method, original automatic daily, proposed daily and difference, reason, supporting reference, approver, policy ID and exception reference, effective and revocation dates, mode, status, and applicable daily and difference. Current section ID is used for filter fallback on older snapshots that never recorded a section ID. An older stored section name still displays as stored.
- Rental Assessment Report retains its existing source date bounds, business category, and assessed location filters. It adds optional `as_of` recorded-by date and displays the stored section name. With override view permission it also displays and filters the immutable `computation_mode` and shows the automatic comparison fields. Existing report readers keep their pre-existing financial columns. Historical assessments do not join to live request statuses.
- Screen, CSV, XLSX, PDF, and print share the same validated predicates. The selected applicability date is retained in export links, the existing 5,000-row guard applies, and print/PDF use the record-detail layout for the wide report. CSV retains formula-safe cells, XLSX uses explicit strings, and the existing Audit Trail visibility scope remains in its original branch. The report index still has its original 19 reports plus Rental Override Report. Unsupported report controls are hidden. Settings still has one Market Sections entry.

## Source and verification

`RentalOverrideController`, `RentalOverrideRequest`, `routes/rental-overrides.php`, assignment rental display support, local view scripts, and views implement the workflow. Existing Tenant/Map/Report controllers, shared Reports/navigation, tenant/map/report views, the assignment relation, and asset build receive integration changes. The public override JS is built locally with the existing Tailwind workflow. No dependency was added.

| Evidence | Result |
| --- | --- |
| `.qa/task3-red.txt` | Six expected failures on missing routes, applicable profile rendering, and report before implementation |
| `.qa/task3-finite-red.txt` | Reproduced finite-term create 500 from an invalid Blade class reference |
| `.qa/task3-mode-red.txt`, `.qa/task3-visibility-red.txt`, `.qa/task3-filter-controls-red.txt` | Reproduced missing mode selection, missing pending summary, and unsupported report controls |
| `.qa/task3-edge-final.txt` | Finite term, mode selection, and scoped pending/snapshot privacy passed, 3 tests and 32 assertions |
| `.qa/task3-workflow-verified.txt` | Isolated workflow and report integration, 10 tests and 187 assertions. The PDF path received the same filtered print rows. |
| `.qa/task3-adjacent-first.txt` | First adjacent workflow, navigation, finance, and section suite, 136 tests and 1,079 assertions, before final edge additions |
| `.qa/task3-full-final.txt` | Initial integrated full suite, 232 tests and 1,834 assertions, before the final filter-control case and strengthened PDF/revision assertions |
| `.qa/task3-frozen-full.txt` | Frozen implementation full suite passed, 233 tests and 1,852 assertions, 02:13.219, PHP 8.2.12 and forced SQLite memory |
| Build and syntax | `npm.cmd run build`, source/served JS SHA256 match, Node syntax checks, PHP native lint of 14 source/test files, and PHP 7.4 grammar parse of 301 files passed. Installed Browserslist data emitted its existing age warning. |

Task 4 owns integrated browser screenshots/manuals and print visual checks. Actual PHP 7.4 runtime, MySQL contention/locking, official Word visual review and live deployment remain unverified. No live migration, backfill, or operational data changes occurred.

Review package `task-3-review.diff` compares against `.qa/sections-overrides-checkpoint/after-task-2-fix1.zip`. Frozen source ZIP `after-task-3.zip` SHA256 `F4BB7EDD76405CFDB0781E393D30F5D646DC15FFC45D6372A9B4FD3A7CFA0CF4`. The generic review package also contains the separately reviewed Section list description and its SectionManagementTest. Independent review should treat those two files as the earlier Section fix.

## Review fix round 1

- The edit form restores submitted fields only when their posted fingerprint still matches the draft displayed by the server. An intervening edit or policy change reloads the entire saved draft and its matching fingerprint, and tells the requester to review and re-enter edits. The browser regression follows the validation redirect and posts the redisplayed form to prove it keeps the newer saved terms.
- Saved-draft submit now requires `draft_terms_hash` alongside `draft_fingerprint` in both HTML forms. The hash identifies the canonical saved terms independently of current policy and state. A pending retry must match that same terms identity. The stale fingerprint still blocks a live draft and the existing locked service compares the route-bound terms inside the transaction. The combined create-and-submit path still binds its replay through `submission_key` and the canonical input terms.
- Create with a revision source authorizes history before resolving the supplied ID. A requester without history permission receives 403 for both an existing and a missing source.
- The review page shows proposal-specific out-of-range and zero-rent exemption warnings beside approval. The shared calculator determines both flags from the frozen assessment. Approvers can see the warnings with approval and history rights even without request rights. The authority snapshot and approval fingerprint still come from the same service review.

Four regressions reproduced before fixes in `.qa/task3-fix1-red.txt`. The first green result in `.qa/task3-fix1-first-green.txt` passed 5 tests and 51 assertions. After strengthening the direct-daily and zero warnings assertions, `.qa/task3-fix1-warning-check.txt` passed 1 test with 8 assertions. The focused workflow, override service, and assessment suites in `.qa/task3-fix1-focused.txt` passed 57 tests and 494 assertions on isolated in-memory SQLite. PHP 7.4 grammar check passed for 301 files, native PHP lint passed for the three changed PHP source/test files, and served JS syntax passed; assets did not change in this round.

Scoped review diff `task-3-fix1-review.diff` contains five files against `.qa/sections-overrides-checkpoint/after-task-3-root.zip`. Frozen source ZIP `after-task-3-fix1.zip` SHA256 `EE8EF819E0A4E9B7B7D3C7889B1C8F8CE18304E55D2EE50F714DA546E3681397`. Final full-suite verification remains with the integrated handoff.
