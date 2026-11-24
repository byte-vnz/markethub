# Section Management and rental overrides — technical design

Date: 2026-11-19. Status: **approved by user; implementation authorized**. This document is not authority to approve LGU rental policy, migrate the live database or backfill records. User brief: attachment `8c7a7122-d44f-4dbe-8c6b-83b88e703c38/Pasted text.txt`.

## Intent and constraints

Enhance the existing Laravel8.83.29/PHP7.4-compatible MarketHub application. Centralize physical Sections in Settings and support assignment-specific, separately approved manual rentals alongside the default automatic category computation. Preserve relational business management, occupancy, posted financials, immutable assessments and private original DOCX files. No dependency upgrade, replacement module, duplicate section FK or production data write.

The user's new requirement supersedes the prohibition on manual overrides **for the approved workflow described here**. Category schedules themselves remain unchanged. Inner uses minimum, Outer maximum. Zero or out-of-range rent still requires specific approved policy authority; a permission grant alone cannot supply it.

## Discovery and baseline

- `market_sections` already has `market_id`, `code`, `name`, `status`, timestamps and unique `(market_id,code)`. Stalls already have required `market_section_id`; map positions are in a separate table. Sections use generic record CRUD and `markets.view/manage`; a Sections link already exists. Enhance these endpoints rather than add a second module.
- Generic stall forms currently load all related sections, including inactive or other-market entries. Validation already checks matching market; section search/activity selection and richer management are missing. Existing history prevents moving a stall between markets/sections. Preserve that rule.
- Section deactivation currently refuses occupied locations. Retain this safeguard. Existing inactive-section references remain visible; new stall placement/assignment cannot select them.
- The calculator resolves approved category/policy once at assignment commencement. Later billing/contract assessments copy the immutable initial basis. Monthly billing accepts full months only; no approved proration exists. Contract generation requires frozen goods/business, verified current identity, approved template and existing review/issue gates.
- Assessment idempotency is `(assignment,period,purpose)`. Generated files and posted payments/bills are immutable. Overrides must create future assessment snapshots, never edit an initial/billed/contract snapshot.
- Fresh baseline: **182 tests,1,277 assertions passed**,81.741seconds,64MB,PHP8.2.12,forced SQLite`:memory:`. `.qa/sections-overrides-baseline.txt`.
- Read-only MySQL discovery at2026-11-19 01:10Asia/Manila found0sections,0stalls,0layoutpositions,0orphan/cross-market/normalizedduplicate links. Latest live installation now has21businesscategories/21rates and the prior unified tables; this differs from the earlier handoff. No migration or seed was executed during this discovery. Operational tenant/assignment/bill/payment/contract counts and financial control totals remain zero;0rentalpolicies/assessments. Evidence `.qa/sections-overrides-live-discovery.json` and `sections-overrides-live-controls.json`.

## Approaches and proposed choice

1. **Recommended: reuse section records and add versioned assignment overrides feeding new immutable assessments.** Reuses existing services, keeps an automatic reference and protects historical records. Requires explicit period/contract guards.
2. Editing the agreement amount or approved category schedule is incompatible with tenant-specific scope and immutable history. Do not use it.
3. Requiring an occupancy renewal for every override would change assignment history simply to change rent, and would not deliver the requested workflow on the current assignment. Retain renewal for legacy agreements and changed business terms, but use approved reassessment for eligible assessed assignments.

The recommended approach is a builder proposal for approval, not a claim that the user has already chosen all details below. Extend the existing Blade/Tailwind design and plain local JavaScript; no new UI or arithmetic dependency. A detailed TDD implementation plan follows approval of this design.

## 1. Section master and migration

Retain `market_sections`, `market_id`, `status=active|inactive`, current IDs, names and scoped code uniqueness. Add nullable description/color, unsigned sort_order(default0), nullable created_by/updated_by FKs. Reuse `market_section_id` throughout; do not add `section_id` or an independently editable section name to stalls. Add `MarketSection::stalls()` and appropriate casts.

Code uniqueness remains per market because changing existing scoped codes to global uniqueness would break valid installations. Prevent new normalized duplicate names within a market by trimming/collapsing whitespace and case-folding for comparison under the market parent lock. Preserve the actual existing spelling; report historical candidates without silently renaming/merging. Display color is nullable strict `#RRGGBB`. Validate status, bounded strings and nonnegative sort order. No permanent section-delete route.

Enhance existing section record routes/views with responsive list/detail/form, market/search/status filters, description/color/order and derived total/occupied/vacant/reserved/maintenance/inactive counts. Counts use existing stall rows, never stored counters. Details link to scoped Stall Management and map. Reordering accepts authorized bounded IDs from one market under a parent lock; updates only order/actor and audits change. Keep a single Settings→Sections link, move/enhance rather than duplicate it.

Stall form: searchable active section options scoped to selected market. Show a currently selected inactive section read-only/retained for unrelated edits; changing selection requires an active matching-market section. No new assignment into an inactive section. Preserve existing history restrictions on section movement. Add an authorized shortcut to create a section and return to the stall form; do not lose unsaved data silently.

Map: retain every coordinate and occupancy color. Show ordered section grouping/legend/count summaries, active section filter and dynamic names. Optional section color is a secondary label/border, not a replacement occupancy signal. Historical map/detail references still identify inactive sections. A rename updates current labels but never rewrites frozen contract/assessment section names.

### Reconciliation and rollback

Add a read-only `sections:reconcile` command returning distinct master values, normalized duplicate candidates, orphan/cross-market links, status counts and coordinate/assignment/financial preservation controls. It runs safely before/after additive migration and does not invent section names. In this schema there is no free-text stall Section to convert; existing FK-linked records are already the masterlist. Preserve all raw values/IDs.

No live backfill. If a different installation has free-text legacy columns, report them and stop short of matching; a reviewed explicit map/backfill requires separate authorization. Migration adds metadata only, preserving coordinates and FK IDs. Before rollout back up database/private documents/APP_KEY. After new records exist retain additive tables/history; use corrective forward changes, not destructive rollback or deleting keys/records.

## 2. Override storage and state machine

Add `rental_overrides`, FK to existing `stall_assignments`; never copy an override on transfer/renewal/new tenant. Fields:

- Assignment, revision/source request identity, requested/updated actors; type `rate_per_sqm|daily_amount`.
- Frozen automatic basis reference (initial assessment), original automatic rate/daily amount, one nullable override rate or one daily amount, signed comparison difference and method.
- Required reason, optional supporting remarks/reference; reference mandatory for out-of-range/exemption/contractual authority cases.
- Inclusive effective_from/effective_until, status `draft|pending|approved|rejected|revoked`, approved/rejected actors/times and reasons, revoked actor/time/reason/effective date.
- Approved override-authority policy FK/reference and approval fingerprint. DECIMAL values and appropriate assignment/date/status indexes; FKs restrict deletion.

Drafts can be edited by an authorized requester; submission freezes terms in pending state. Pending/rejected rows do not affect rent. Approval requires a different active user, the explicit approve permission, and the fingerprint of the displayed request/basis/policy. Reject/revoke are explicit transitions with reasons. Changed submitted terms require a new version/request linked to the old row, never overwrite an approved request. Repeated identical submissions/transitions are idempotent; stale/conflicting transitions reject clearly.

Expired is a derived **as-of-date** display state for approved rows whose effective_until has passed; no scheduled mutation is needed. A revoked record retains its original approval and an effective revocation boundary. Resolution may use its historically valid pre-revocation dates; new snapshots/old reprints never depend on changing its display label.

Parent assignment/tenant locks serialize request approval, date overlap checks, bill/contract assessment and revocation in the existing lock order. No two applicable approved periods may overlap. Date checks repeat inside the transaction after locking. Database uniqueness protects submission keys/revision identities. No deletion or blind bulk update endpoints.

## 3. Effective dates and protected obligations

**Proposed financial boundary for approval:** new override activation and return-to-automatic take effect on the first day of a full calendar billing month; inclusive end dates are month ends. Do not invent daily splitting/proration. Override dates must fit the assignment and must not be retroactive relative to approval/revocation. If an ordinary requested date is mid-month, show the next eligible month explicitly and require the user to select it; never silently change a date.

Only category-assessed assignments are eligible. Legacy monthly agreements require the existing authorized renewal/mapping path; do not infer a daily amount from monthly legacy rent.

Initial automatic assessments remain the immutable reference and do not themselves prevent requesting an override. **Already created billing/contract assessments, bills (including drafts) and contract snapshots block a conflicting change for their affected periods.** Do not overwrite or silently refresh those documents. Operators must choose a later unassessed month; ordinary draft voiding is not a mechanism to erase a frozen basis. Guard both approval and later billing/contract creation against stale/racing decisions.

An issued/signed/active contract locks its covered rental obligations. Applying or ending an override inside that contractual period is blocked until the existing authorized termination/renewal process creates an eligible arrangement; no general legal-amendment subsystem is invented. A pending draft/ready contract also blocks conflicting changes to its planned covered period until a new eligible arrangement is chosen; no original is regenerated.

Contracts can only represent one constant applicable daily basis through their term. If an approved override starts/ends or returns to automatic within the proposed contract term, reject that contract term and require a term within a consistent authorized interval or renewal. Recheck this before generation/issuance, not merely draft creation. This protects against a future-expiring override being printed as if it lasts for the entire lease.

Revocation/return-to-automatic is prospective, with a first-of-month effective date and reason; respect all frozen assessments and contractual obligations. An approved future override may be cancelled before it starts without applying it. Overlap resolution considers the effective cancellation/end boundary, retaining original requested/approved dates and audit events. Expiry/revocation returns to the **frozen agreement's automatic basis**, not today's mutable category/area/CUSA masters.

Implementation clarification: existing end/transfer behavior remains available. An override's actual applicability is intersected with its owning assignment term; ending that assignment bounds future use without rewriting the original approved request or passing it to a successor. Policy retirement can consider that actual end, while still protecting frozen billing/contract reference months and outstanding non-void/non-terminated contract obligations. An old open-ended request must not permanently prevent retiring a policy after all actual coverage and preserved obligations have ended. This does not authorize prorated billing or alter a contract's terms/status.

## 4. Policy authority and permissions

Extend versioned `rental_policies` with default-false out-of-range override and zero-rent exemption authorization flags plus an explicit override authority reference. These settings are draft-only, require approved effective policy and existing `rates.approve`, and are never auto-enabled/seeded. Existing approved policy rows retain false defaults. Approvals reference an effective authorized revision without changing the agreement's frozen CUSA/day/concurrency/advance terms. A user's approval is an application control, not legal validation of the supplied reference.

**Proposed policy-duration rule:** the authorizing policy must already be approved at override approval, and its effective dates must cover the entire requested override period. It need not be in effect on the approval day if it is an approved future revision, but must be effective on activation and through expiry. An open-ended override requires an open-ended authorizing policy. Expired/retired revisions cannot authorize a new override. Store the exact policy revision/reference/authorization flags in the approved override snapshot.

Extend policy retirement validation to approved override dependencies as well as initial assessments. A retirement/end-date change must not remove coverage from any approved non-cancelled override, including its historically applied interval or frozen billing/contract obligations. First use the prospective revoke/return workflow to close eligible future override coverage, then retire only at/after the remaining covered end. If contractual/assessment guards prevent closing that interval, retirement also remains blocked. Pending requests are revalidated against current policy dates/flags/fingerprint when approved; they gain no vested authority from submission. A policy edit/retirement never silently cancels an approved override or reprices an old assessment.

Within-range positive overrides require separate financial approval and reason. Out-of-range values show a clear warning and cannot be approved unless the effective policy explicitly authorizes them and both policy/override references exist. Zero requires explicit exemption authorization/reference as well; zero rent leaves CUSA unchanged. Direct daily range comparison uses category minimum/maximum multiplied by frozen area with the same daily rounding; a rounded equivalent rate cannot bypass the range check. Negative/overflow/ambiguous/malformed values reject on the server.

Proposed permissions follow current conventions:

| Permissions | Default roles |
| --- | --- |
| sections.view | Existing six default roles |
| sections.manage (create/edit/status/order) | Super Administrator, Market Administrator |
| rentals.override.view/request/history | Super Administrator, Market Administrator, Market Staff |
| rentals.override.view/history/report | Treasury/Accounting Officer additionally |
| rentals.override.approve/reject/revoke | Super Administrator |
| rentals.override.report | Super Administrator, Market Administrator additionally |

Request/approval separation is mandatory even for Super Administrator: requester cannot approve own request. Two suitably authorized users are required; there is no hidden bypass. Revocation of one's own requested override is allowed only with revoke authority and prospective guardrails. Roles upgrade additively/transactionally, preserving custom grants and deliberate revocations; custom roles require explicit new grants. Legacy section routes delegate to the enhanced service and enforce the new section permission; document this deployment permission change. Backend checks cover every page/action/preview and direct route invocation.

Section counts disclose no tenants/financials. Override data/approval reasons require override view/history; report requires its dedicated permission. Map monetary fields also require existing financial visibility; management viewer keeps occupancy access without rental/approval leakage. ID fields remain independently protected.

## 5. One authoritative calculation path

Keep public pure automatic `calculate()` semantics compatible and `resolve()` as the approved new-assignment automatic resolver. Extend RentalCalculationService with assignment-aware preview and assessment resolution using the initial assessment's immutable category/area/rate/CUSA/day basis. A small RentalOverrideService owns authorized state transitions, not arithmetic.

Resolution: (1) obtain frozen standard automatic basis; (2) resolve at most one approved/effective override for assignment/date; (3) calculate applicable daily; (4) calculate monthly/advance/deposit from that daily using the existing day rule; (5) return both automatic and applicable values, signed applicable-minus-automatic difference, method/reference/status/approval/effective dates. No controller/Blade/JS duplicates multiplication; frontend fetches validated preview responses.

Rate method: round-half-up once `override_rate×frozen_area`. Direct daily: use entered daily amount exactly, **never multiply it by area**. Use BCMath strings and supported DECIMAL bounds. Shared daily-to-monthly composition preserves CUSA and formulas for both methods, including authorized zero. Reject any overflow before persistence.

Extend new assessment snapshots with explicit computation_mode(default automatic), override FK, automatic rate/daily, signed difference, current/assessed section ID/name and relevant authority/approval metadata. Store equivalent rate as a clearly non-authoritative reference plus a contract consistency flag when direct daily is used. Old snapshots remain untouched and receive compatible automatic defaults/fallbacks; initial automatic reference stays frozen. Existing `(assignment,period,purpose)` idempotency remains; cross-purpose resolution verifies the same applicable basis for the same period. Approval guards prevent changing a period after its first billing/contract assessment.

Examples: Outer Vegetables10sqm at26.88 →automatic268.80. Rate override25.00 →250.00, difference−18.80. Direct daily200.00 →200.00, difference−68.80. For30days/CUSA10: automatic base8064,total8364; rate override base7500,total7800; direct daily base6000,total6300,advance12000,deposit6000,initial18000. Direct200/10=20.00 is outside21.04–26.88 and **must remain unapproved without explicit out-of-range policy/reference**. Tests cover calculation separately from authority; do not seed official exemptions.

## 6. Tenant UI, billing, contracts and reports

Rental Information tab shows each assessed business/stall/section with automatic basis and selected reference month, final applicable basis/mode, comparison and approval state. Automatic is default. Authorized requesters select Manual Override, then method/one amount/reason/dates/reference/remarks. Server preview drives the comparison; hidden/extra method amounts reject. Provide pending/history, approve/reject/revoke and return-to-automatic actions according to permissions with clear outcome/errors and keyboard/mobile behavior.

Registration drafts remain automatic until a real assignment exists; link to the resulting Rental Information tab to request an assignment-scoped override. Do not store orphan overrides or silently apply unapproved registration inputs.

Billing uses only a fresh immutable period assessment from the centralized resolver. Existing bills/assessments/payment allocations/outstanding balances stay unchanged; expired/revoked/pending/rejected overrides do not affect subsequent eligible months. Scheduler remains draft-only. Do not implement advance/deposit accounting or proration.

New contracts consume approved applicable amounts and store complete internal override metadata. Rate override prints approved rate. Direct daily computes equivalent rate only for reference: using the existing two-decimal DOCX rate field must reproduce the exact daily amount after standard rounding. If it cannot, flag the mismatch and **block generation and issuance** until a consistent approved arrangement is chosen; authorization cannot make contradictory numbers accurate. No official template changes are authorized by this brief. Keep ZIP structure, legal words, tables/logos/signatures/notarial sections and original generated-file hashes. Existing template/numbering/visual-review authority and generation/issue separation remain.

Map details show current section, frozen section when different, automatic/applicable daily, mode and approved-active indicator when allowed. Distinguish historical issued amounts from an as-of-date preview. No new coordinate write path.

Add Rental Override Report to existing Reports/export pipeline with tenant/business/stall/section, original/applicable/difference, type/reason/reference/approver/effective dates/status. Add supported section/category/computation/override-state/as-of filters to relevant map/list/assessment/override reports with shared screen/export predicates. Pending/expired override filters select request state, not pretend it was applied to a historical assessment. Do not expose unsupported filters on other reports. Preserve formula-safe CSV/XLSX and readable wide print/PDF layout/export limits.

## 7. Audit, tests and documentation

Reuse Audit safe allowlists for section changes/status/order and stall section actions, override draft/submit/approval/rejection/version/revocation/return-auto and assessments using overrides. Record actor, safe before/after, reason/reference and approval IDs/dates. No passwords/ID snapshots or document bodies in logs. Preserve existing logs and original assessment/override records.

TDD/regression coverage: populated additive schema preservation; section CRUD/scoped duplicates/normalized ambiguity/color/order/derived counts/inactive rules/denial; map names/filter/coordinate identity; automatic Inner/Outer; both override methods/no double area; malformed/zero/out-of-range/overflow guards; two-person approval/fingerprint/staleness/direct API denial; overlap/races/expiry/revocation/full-month transitions; protected existing bills/contracts; no cross-assignment inheritance; frozen-basis fallback after master edits; idempotent assessment/submission; direct-daily DOCX consistency; report permissions/filter/export parity. Use synthetic SQLite only; optional native MySQL locking tests require an explicit isolated target.

After implementation run full suite, PHP7.4 grammar/native lint, Blade/cache/assets, independent financial/security review and actual desktop/tablet/mobile browser flows. Capture Section/Stall/Override interfaces using fictional data. Preserve source reference checksums. Word nine-page fidelity remains unverified unless an actual compatible renderer/reviewer is available.

Update SECTION_MANAGEMENT.md, SECTION_DATA_MIGRATION.md, RENTAL_OVERRIDE_MANAGEMENT.md, calculation/unified/template mapping guides, AGENTS and codex memory/state/decisions/handoff. Maintain manual text, screenshot index/coverage, role/report matrices, workflows and PDF/DOCX/source ZIP exports with actual checks. Mark old override prohibition superseded only when approved implementation exists, retaining old historical checkpoints.

## Implementation phases after approval

1. Write detailed TDD plan and new protected source checkpoint; no Git repository exists, so use approved in-place/file rollback strategy.
2. Enhance existing section schema/master/validation/reconciliation and integrate stalls/map.
3. Add override/policy/snapshot schema and central calculation/state transitions with independent financial review.
4. Add tenant workflow, billing/contract/report/navigation integration and security regression tests.
5. Browser/maintained-manual QA, full checks, migration/reconciliation/rollback documentation and handoff.

No product code, feature migration or live backfill is part of this design checkpoint. Pending user approval includes the proposed month boundaries, obligation protection, policy flags, role defaults and equivalent-rate generation block above. Detailed implementation planning is the next step after approval.

## Builder check

A fresh independent Laravel/financial-systems reviewer read this design and relevant source. One important ambiguity was identified: policy authority dates and later retirement dependencies. The proposed whole-period coverage/retirement rule above resolves it for user review. No other important unanswered question was found about section preservation or frozen assessments/contracts. Review is of the design only, not proof of implementation.
