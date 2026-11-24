# Frontend modernization roadmap — approval pending

Prepared 20 November 2026 using the existing architecture and [verified audit](../audits/FRONTEND_MODERNIZATION_AUDIT.md). Integration, privacy settings and development standards are authorized and completed separately. **No modernization task below is authorized for implementation yet.** Approval of a stage does not approve financial policy, schema rollout, live data work, dependency upgrades or deployment.

## Delivery rules

Preserve PHP 7.4 syntax/Laravel 8.83/MySQL/Blade/Tailwind 3/local JS and current design language. Consult the project Modern Web Guidance runner first; use Baseline Widely Available and office-browser evidence. Reuse existing components/patterns. Before each approved task, inspect every caller/related view, add a small meaningful regression check for changed behavior, observe the failure where applicable, implement the smallest fix, then run focused and shared regressions. Do not build a new form/table framework solely for this audit.

All fixtures must be fictional and databases disposable. Keep exact server money, immutable history/assessments, private generated originals, assignment approvals, separate reviewer rules, idempotency and custom role grants. Product changes must update affected user-manual text, actual sanitized screenshots, workflow diagrams, COVERAGE_MATRIX and exports per BUILD.md; verify authenticated Help. Documentation-only development-tool changes do not require replacing unchanged operator screenshots or manuals.

## Stage 1 — Foundation and concrete defects

**Tasks:** fix action-specific contract confirmation F03; introduce shared error-summary focus and field association F01, initially on one simple form and nested registration; address table keyboard entry F06 using the existing Sections/Reports pattern; add mobile no-JS navigation F07 and correct drawer modal semantics F08. Validate existing native APIs on supported office browsers; retain equivalent fallbacks where needed.

**Files/callers:** shared app layout/CSS/app.js/sidebar, contract show form, affected control templates, core list wrappers and scoped tests. Preserve `requestSubmit(submitter)` and form action/reason semantics. No service/calculation changes.

**Dependencies:** explicit approval; agreed office-browser inventory or provisional latest stable Chrome/Edge/Firefox/Safari plus documented office versions; baseline fixtures/screenshots; existing permissions/navigation presenter. **Risk:** medium shared-surface regression; repeated IDs, hidden error targets, resize/inert/focus restoration and click intent.

**Expected outcome:** staff can identify/recover from errors, confirm the intended legal-record action, scroll data with a keyboard and navigate/sign out on mobile if enhancement fails.

**Acceptance:** invalid simple/nested fields announce their exact error; summary links resolve unique controls; both contract action/cancel paths hit only intended routes; every audited overflowing core table is keyboard reachable at 320/390px and 200% zoom; JS-disabled mobile authorized links/logout work; mobile modal accessibility semantics disappear correctly at desktop width. Focused auth/navigation/contract/registration tests and complete isolated suite pass. No changed financial totals, role grants or original files.

## Stage 2 — Layout and design consistency

**Tasks:** recover staff form values/disclosures F02 with scoped form identity; add native constraint parity F04 only where matching current server rules; distinguish record/report action names F05; finish F01/F06 coverage across category/policy/override/settings/help forms and tables. Review dashboard density, sidebar states, spacing/typography, error/empty/loading/success/warning copy and existing SweetAlert dialogs. Correct only demonstrated inconsistencies; preserve chart text alternatives and status text.

**Files/callers:** admin staff form/controller presentation context, generic resources/form metadata, named list/action templates, dashboard/layout and Help table rendering as needed. Reuse `form_key`/`decision_id` patterns; do not apply unscoped old input to all account forms.

**Dependencies:** Stage 1 shared patterns stable; approved role fixtures; current v1.2 manual as reference. **Risk:** medium; accidental input cross-population, stricter legacy validation or hidden unauthorized labels. **Expected outcome:** predictable interactions and consistent information hierarchy using current design.

**Acceptance:** failed staff edit preserves safe fields in only the submitted disclosure and never password values; required indicators/native hints match conditional server rules; repeated actions have distinct accessible names; tests of six role defaults and preserved custom grants pass. Desktop/tablet/mobile and keyboard review finds no clipped essential actions or page-wide overflow (intentional table/map scroll regions allowed). Existing modal cancel/focus/Escape behavior remains available.

## Stage 3 — Core business workflows

**Tasks:** verify map details selection/loading/error/focus under rapid clicks and slow responses; add a modest no-JS Stall-record fallback if approved; complete accessible stall/section selection, nested tenant/business/helper previews, rental override comparison/request/decision and payment allocation flows. Review preview loading/error/retry messaging and saved-input recovery. Keep references and exact server-rendered amounts clear and distinct from proposals/history.

**Files/callers:** `map/*`, `stalls/*`, `tenants/*`, `rental-overrides/*`, `payments/*`, three client scripts and the existing corresponding controller/request endpoints. Avoid reimplementing rent or approval rules in JS. No new occupancy or override workflow.

**Dependencies:** Stage 1/2 interaction conventions; synthetic roles/approved test policies in disposable DB; immutable file/control-total baselines. **Risk:** high regression sensitivity despite small UI changes: selected submit intent, encrypted drafts, stale preview, idempotency, approval fingerprint and private identity.

**Expected outcome:** core workflows stay usable and clear through loading/failure/keyboard/mobile conditions. **Acceptance:** synthetic register/resume/confirm → assign → automatic preview → request → separate-user approval → bill/payment review sequence passes, including denied roles/stale proposals/retries. Pending overrides never change applicable rent; prior bills/assessments/contracts remain byte/value unchanged. Repeated submits do not double-post; preview errors do not authorize an assessment. Map coordinates/section links remain identical.

## Stage 4 — Measured performance

**Tasks:** defer below-fold allowlisted Help images and reserve intrinsic dimensions F09; load Chart.js only where needed F10; eliminate legacy-rate relation lookups F11 using the existing relation. Profile synthetic 20-row tenant/rate lists, 200/2,000-stall map fixtures and large eligible selects before any further query/rendering changes. Inventory CSS/JS/network payload and duplicate handlers before removing anything. Keep the coordinate map complete and exports bounded.

**Dependencies:** stable baseline behavior; reproducible local browser/network measurement environment and same synthetic datasets; security review of Help renderer. **Risk:** low for conditional chart inclusion, medium for image rendering, potentially high for unmeasured dataset rewrites. Avoid package upgrades, new bundlers, virtual lists or caching sensitive data without a separate justified proposal.

**Expected outcome:** smaller non-dashboard requests, more stable Help layout and bounded relation reads, with evidence for any next optimization. **Acceptance:** non-dashboard pages make zero Chart.js requests; five dashboard charts/text data remain correct; Help images reserve space/defer below-fold fetches and retain authorization; rate market-query count is bounded for 20 rows. Record pre/post response time, query count, DOM count, transferred bytes and browser traces on identical fixtures; report median and variability over at least five repeat runs. Target LCP ≤2.5s, INP ≤200ms and CLS ≤0.1 where a valid representative measurement exists; lab traces do not establish real-user percentile conformance. No claim of improvement without comparable evidence.

## Stage 5 — Release quality and documentation

**Tasks:** full isolated functional/regression suite, PHP 7.4 grammar/native lint, JS syntax, local build and Blade rendering. Keyboard/screen-reader and automated accessibility audit, then manually validate each result. Cross-browser and viewport checks, zoom/forced-color/reduced-motion, print/PDF representative long fictional records. Rebuild affected manual PDF/DOCX/quick/source exports and verify Help access/allowlists/search/downloads. Record unresolved Word rendering and actual PHP7.4/MySQL rehearsal separately.

**Dependencies:** approved prior stage fixes, browser/device availability, fictional fixtures, documentation authoring tools; authorized document visual reviewer for legal/Word assertions. **Risk:** missing device/AT/print coverage and unsupported-stack constraints; do not silently equate unit tests with these checks.

**Expected outcome:** reviewable release evidence and a precise operator handoff. **Acceptance:** all applicable tests pass with logged commands/counts; zero unresolved P0/P1 defects in tested scope; any P2 deferral has explicit owner/reason and user acceptance. Exercise 320/390/768/1024/1440px plus 200%/400% zoom, Chrome/Edge/Firefox/Safari versions recorded, NVDA/Firefox or equivalent plus keyboard-only path. Print has no missing essential columns, clipped identifiers or altered legal wording. Manual coverage and source checks pass, exports open and authenticated Help tests pass. Word visual fidelity is claimed only after actual page review. No live deployment implied.

## Approval boundary and next action

Recommended first approval: Stage 1, followed by a review of its focused diffs, regression evidence and affected manual updates. All other stages remain a prioritized proposal, not instructions to begin work now. Independent operator staging/MySQL concurrency/actual PHP7.4 and legal-policy/Word acceptance gates from the existing Section/override handoff continue unchanged.
