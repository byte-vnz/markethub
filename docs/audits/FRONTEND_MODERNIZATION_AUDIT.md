# Frontend modernization audit

Date: 20 November 2026, Asia/Manila. Scope: current MarketHub workspace, all 63 Blade templates, shared CSS, three client scripts, asset build, navigation, routes and relevant rendering/validation controllers. **Source audit complete; remediation awaits explicit approval.** No product UI or business logic was changed.

## Executive summary

The Blade/Tailwind architecture already has local assets, native controls, visible focus, responsive grids, server-authoritative money and approvals, protected downloads and useful accessibility foundations. Retain it. Eleven manually verified source findings remain: **P0 0, P1 0, P2 7, P3 4**. Start with shared error recovery, action-specific confirmation, keyboard-scrollable tables and mobile navigation resilience. No verified critical security issue was established by this bounded frontend review; this is not a claim that the legacy application is secure for internet deployment.

The official legacy scanner reported **zero matches**, with 52 rules across 38 guides (~20% of the corpus). It does not replace manual review, Blade rendering, browser or assistive-technology checks. No automated scan result was promoted without inspecting the actual source. Proposed performance improvements below are supported by code paths, not measured Core Web Vitals. No WCAG conformance, real-device, Word fidelity or MySQL locking certification is claimed.

## Architecture and installed versions

| Layer | Observed implementation |
| --- | --- |
| Runtime | XAMPP CLI PHP 8.2.12; required compatibility remains PHP 7.4.30. Installed MySQL server binary 8.4.7, verified with `mysqld --version`; no live database connection in this audit. |
| Framework | Installed Laravel 8.83.29, Spatie Permission 5.11.1, PHPUnit 9.6.39. Composer platform targets 7.4.30. |
| Frontend | Blade, Tailwind 3.4.17, vanilla JS, Chart.js 4.4.9, SweetAlert2 11.22.4; installed package metadata checked. Node 24.12.0/npm 11.19.0. |
| Build | Tailwind CLI plus `scripts/build-assets.js`, all output under `public/assets`; system fonts, no runtime CDN. Existing dependency versions unchanged. |
| Requests | Route → authenticated/active-account middleware → controller/Form Request/policy → transaction service → Eloquent. Shared JS enhances ordinary forms and fetches authorized server-rendered stall details. |
| Navigation | `config/navigation.php` → `App\Support\Navigation` → `components/sidebar.blade.php`; permissions and nested route context remain centralized. |
| Rental state | `RentalCalculationService`, immutable assessments and approved assignment overrides; previews use the server, not browser arithmetic. Separate section/category/location concepts remain intact. |
| Content | Shared app layout, specialized tenant/category/section/override views, generic remaining masters; private Help content and approved original-template contracts. |

Discovery used AGENTS, codex memory/state/decisions/handoff, master spec, existing implementation plans/reports, navigation/system architecture, security and manual build guidance. Earlier approval-pending/deployment entries are historical; latest Section/override handoff controls existing behavior. No Git repository exists. Source/asset/document hashes and an instruction snapshot were captured before changes under `.qa/modern-web-guidance`.

## Module coverage

Each row was reviewed in source. Runtime coverage from the existing full isolated suite is reported separately; a row does not imply a new browser walkthrough.

| Module | Source examined / observations | Findings |
| --- | --- | --- |
| Login/authentication | `auth/login`, shared middleware/routes; explicit labels, password-manager hints, native required fields and CSRF | F01 |
| Dashboard/analytics | `dashboard`, chart initializer, controller; all chart values already available in native text disclosure | F10; reflow/contrast still need browser checks |
| Map/occupancy/editor | `map/*`, `MapController`, stall fetch handler; status text complements color, named map scroll region, numeric coordinate alternative | F07/F08 shared shell; measurement/no-JS follow-up below |
| Market Sections | `sections/*`, reorder JS, shared record controller; labelled keyboard reorder and named table region are existing good patterns | F01 |
| Markets/stalls/categories | `records/*`, `stalls/*`, metadata/request; historical relationships and coordinates retained | F01/F04/F05/F06 |
| Tenant/business/helper registration | `tenants/*`, `tenant-registration.js`, tenant controllers; nested fieldsets/legends, encrypted drafts, server previews and stable IDs | F01/F05/F06; dataset measurement below |
| Assignments/transfer/rent | `assignments/*`, calculator/controller integration; native selects and exact server results | F01/F06 |
| Category schedules/policy/overrides | `business-categories/*`, `rental-overrides/*`, JS and routes; scoped old-input/review identities are reusable | F01/F06 |
| Legacy rental rates | Generic record list/form and `Resources` metadata | F01/F04/F05/F06/F11 |
| Billing/receivables | `billing/*`; native controls, explicit drafts, contextual allocation/display data | F01/F06 |
| Payments/collections/history | `payments/*`, shared submit/confirmation handler; contextual allocation input labels and saved submit intent | F01/F06 |
| Reports/export/print | `reports/*`, `ReportController`; named screen scroll region, 30-row pages, 5,000-row export cap, wide report record layout | F05; visual print verification pending |
| Contracts/templates | `contracts/*`, shared confirm handler; private downloads, approval/issuance separate, originals preserved | F01/F03/F06 |
| Users/roles | `admin/staff*`, `AdminController`, role matrix; existing predefined-role selection, no custom role editor | F01/F02 |
| Settings/audit | `admin/settings`, `admin/audit`; ordinary server forms and paginated audit | F01/F06 |
| Navigation/sidebar | Shared layout/sidebar/CSS/JS; native disclosures, focus trap, Escape, inert, reduced motion and active links already present | F07/F08 |
| Help/manual | `help/*`, `HelpController`; allowlisted images/downloads, escaped raw Markdown HTML and unsafe-link blocking | F06 extension/F09/F10 |

## Verified findings

Priorities: P0 immediate critical harm; P1 major blocking/high-risk defect; P2 meaningful usability/accessibility defect; P3 bounded improvement. Locations refer to the audited source, including compact templates whose entire form is on one line. All entries below are source-verified; runtime acceptance remains to be executed after approval.

### F01 — P2 — Server errors lack field associations and focus recovery

**Evidence:** `resources/views/layouts/app.blade.php:1` emits plain error list items with `role=alert`, but no focus target or field links. Inline errors at `records/form.blade.php:9` and `tenants/form.blade.php:29` lack IDs/described-by associations; there is no `aria-invalid` rendering in current view/JS source. Login has the same plain-summary limitation. This affects long nested registration as well as financial/admin forms.

**Recommendation:** add a focused, linked summary and field-specific error IDs/state, mapping nested/repeated fields carefully. Reuse existing form patterns, retain server validation and do not repopulate secret fields. **Complexity/risk:** medium; shared change must handle disclosed controls and array keys without duplicate IDs or cross-form error leakage. **Acceptance:** invalid fictional simple and nested submissions lead to the summary/first failed field; links focus the correct control; its specific error is announced; corrected fields clear invalid state; valid saves and authorization remain unchanged. **Guidance:** forms, accessibility. **Security:** do not echo hidden IDs/passwords or loosen server rules.

### F02 — P2 — Staff validation failures lose safe edits and hide the affected form

**Evidence:** `admin/staff-form.blade.php:1` reads database/default values rather than scoped old input. `admin/staff.blade.php:4` never reopens the edited disclosure. `AdminController.php:21–32,42–54` can reject duplicate email, password confirmation, last-admin or self-disable changes.

**Recommendation:** identify the submitted account/form, preserve only its safe fields and reopen only its disclosure. The category/policy `form_key` pattern already solves this locally. **Complexity/risk:** medium; global `old()` in every form would repeat one person's input across accounts. **Acceptance:** a rejected fictional edit retains attempted name/email/role/status only in its form; passwords stay blank; other accounts and protected-admin guards remain unchanged. **Guidance:** forms, accessibility. **Security:** secret fields and unauthorized role changes remain excluded.

### F03 — P2 — Terminate contract confirmation says “Void”

**Evidence:** `contracts/show.blade.php:22` shares `data-confirm="Void this contract and retain its history?"` between Void and Terminate. `resources/js/app.js:296–308` uses that text, while `requestSubmit(e.submitter)` correctly retains Terminate's `formaction`. This is a confirmed misleading confirmation, not an incorrect backend route.

**Recommendation:** choose confirmation text from the activated action, or separate the forms, retaining reason validation. **Complexity/risk:** low–medium; preserve clicked submit intent, native validation and cancel semantics. **Acceptance:** both fictional eligible actions name the actual transition; cancel sends nothing; confirm targets the correct route with the reason; originals/history remain intact. **Guidance:** forms, accessibility (clear action names). **Security:** authorization/legal issuance and immutable records remain server-enforced.

### F04 — P3 — Generic master controls omit required-state/constraint parity

**Evidence:** `records/form.blade.php:3–9` and `stalls/section-picker.blade.php:2` omit most native required/length hints; `RecordRequest.php:46–54,65–78,94–105` requires the corresponding codes/names/relations/area/rate fields. JS adds required to the section select, but the server-rendered fallback does not. Most controls already have labels.

**Recommendation:** expose the applicable existing constraints through current metadata/templates, including visible required indicators. **Complexity/risk:** medium; do not mark every optional legacy field required or introduce a second validation engine. **Acceptance:** native empty-field feedback and persistent required indicators match server rules, including legacy and occupied-stall exceptions. **Guidance:** forms, accessibility. **Security:** server validation remains authoritative.

### F05 — P3 — Repeated actions have indistinguishable accessible names

**Evidence:** `tenants/index.blade.php:82–83`, `records/index.blade.php:5`, `reports/index.blade.php:4` repeat View/Edit/Open report without the record name. `sections/index.blade.php:48–49` already uses an `sr-only` name suffix.

**Recommendation:** reuse that suffix pattern. **Complexity/risk:** low; reveal only context already authorized on the page. **Acceptance:** accessibility-tree/link-list names distinguish destinations and retain the visible action words; hrefs/permissions and compact visual appearance stay unchanged. **Guidance:** accessibility. This is a navigation improvement, not a blanket assertion that every repeated table link fails WCAG.

### F06 — P2 — Scrollable table accessibility is inconsistent

**Evidence:** `.tablewrap` in `resources/css/app.css:17` enables horizontal scrolling. Missing explicit focusable/named region wrappers include `tenants/index:34`, `stalls/index:17`, `billing/table:1`, `payments/index:5`, `contracts/index:7`, `assignments/index:5`, `admin/audit:4`, `business-categories/index:15`, `tenants/business-index:19`, `records/index:5`, `map/editor:4`, `contracts/show:23`, and tenant detail tables. Reports/Sections/map already implement named focusable regions. Help Markdown tables use block overflow without that wrapper. Contract list also has an unnamed action column.

**Recommendation:** apply the existing named `role=region`, `tabindex=0` wrapper pattern and complete relevant header labels; preserve table semantics. **Complexity/risk:** low–medium due to breadth; avoid redundant focus stops/names or turning cells into arbitrary stacked blocks. **Acceptance:** at 320/390px and 200% zoom each overflowing region is keyboard reachable/scrollable with visible focus; header associations and data remain available. **Guidance:** responsive-table, accessibility. Browser-native scroll focus varies, so missing tabindex does not prove unusability in every browser. **Security:** no data/authorization changes.

### F07 — P2 — Mobile navigation/logout require successful JavaScript

**Evidence:** `app.css:29,47` translates/hides the sidebar below 1024px; `layouts/app.blade.php:1` offers only a JS menu button; `app.js:58–65` opens it. Logout lives in the hidden sidebar (`components/sidebar.blade.php:27`).

**Recommendation:** retain visible server-rendered navigation until enhancement initializes, or supply a small fallback from the same permission-filtered model. **Complexity/risk:** medium; preserve desktop/compact/mobile behavior without independently maintaining two menus. **Acceptance:** block JS at mobile/tablet widths and reach authorized modules/logout; enable JS and retain current drawer behavior. **Guidance:** html, navigation-drawer. **Security:** menu hiding is never authorization; keep CSRF POST logout and permission filtering.

### F08 — P2 — Modal drawer lacks corresponding accessibility semantics

**Evidence:** `components/sidebar.blade.php:3` remains an aside while `app.js:53–65,78–96` makes content inert and traps focus; role/dialog/aria-modal are not set.

**Recommendation:** expose dialog/modal semantics only while the mobile drawer is open; remove them on close/desktop transition. Retain accessible name, existing focus trap/Escape/overlay/return and inert handling. **Complexity/risk:** medium; resize and close paths must restore correct state. **Acceptance:** accessibility tree identifies the modal on mobile and normal navigation on desktop; keyboard, close/resize and focus restoration pass. **Guidance:** navigation-drawer. A new dialog/popover library or swipe implementation is unnecessary. **Security:** no authority change.

### F09 — P2 — Help images lack loading and layout-size hints

**Evidence:** `HelpController.php:33–45` renders the full manual; `:168–191` rewrites allowlisted image URLs but adds no loading/dimensions. `help/layout.blade.php:6` only sets max-width/auto-height. Workflow images at `help/reader.blade.php:22` are lazy but have no reserved dimensions.

**Recommendation:** add intrinsic dimensions and below-fold lazy loading through the existing allowlisted image path, leaving first-visible content appropriately prioritized. **Complexity/risk:** medium; keep Markdown escaping, path allowlists and authenticated image routes intact. **Acceptance:** slow-network browser check shows reserved image space and deferred below-fold requests, working anchors/authorized images and no unauthorized access. Measure actual CLS/LCP before claiming improvement. **Guidance:** performance, html. **Security:** never accept raw HTML or arbitrary filesystem paths to add these hints.

### F10 — P3 — Chart.js loads on every authenticated page

**Evidence:** `layouts/app.blade.php:1` includes local deferred Chart.js globally; only `dashboard.blade.php:7` supplies chart-data and `app.js:327–329` gates initialization. Artifact is 206,670 uncompressed bytes; wire transfer/cache behavior was not measured.

**Recommendation:** include the existing asset only on Dashboard with correct script order. **Complexity/risk:** low; no library removal or bundler change. **Acceptance:** no chart request on Help/staff/tenant/report pages, all five dashboard charts and text equivalents work, including reload/cache conditions. **Guidance:** performance. **Security:** keep assets local; do not add CDNs or analytics.

### F11 — P3 — Legacy-rate list performs relation queries in the view

**Evidence:** `records/index.blade.php:5` calls `$meta[2]::find($record->$key)` per relation cell. Rates metadata in `app/Support/Resources.php` exposes market_id; `RecordController.php:36,72` paginates without loading the relation. Other newer masters mostly delegate to specialized views.

**Recommendation:** load the existing market relationship or a market label map for this remaining list. **Complexity/risk:** low; avoid new generic infrastructure or changing rate eligibility. **Acceptance:** a fixture with 20 fictional rates has a bounded market lookup count rather than one query per row; labels/null fallbacks, pagination and permissions remain identical. **Guidance:** performance principle applied by source inference; the guide does not specifically prescribe SQL. **Security:** preserve query scope and historical amounts.

## Measurements and compatibility work still required

- Measure fictional large datasets before choosing optimization: TenantController loads complete relations for a 20-row page while showing only a few entries; assignment/payment candidates and full map grids can grow. No measured slowness, excessive-DOM threshold or new pagination requirement is asserted. Coordinate maps must retain all saved positions; never naively paginate away spatial context.
- Map stall buttons are enhanced-only; assess a small no-JS link to the existing Stall record in Stage 3. Shared map fetch aborts old requests, but browser race/focus/aria-busy timing should be tested under throttling before declaring it correct.
- CSS uses Grid/Flexbox, responsive breakpoints and fixed/sticky navigation; current JS uses fetch, AbortController, optional chaining, replaceAll, replaceChildren, requestSubmit, inert, matchMedia events and Web Animations. Revalidate each against the actual office browser inventory. Baseline Widely Available is the preference, not proof all deployed browsers support the code.
- Native month inputs need a format-preserving fallback in browsers without the picker. Do not change server `Y-m` interpretation or dates/timezone semantics.
- Review 320/390/768/1024/1440px, 200% and 400% zoom, long fictional names/money, forced colors and reduced motion; measure contrast with computed colors. Existing text status/focus/reduced-motion rules are positives, not full conformance evidence.
- Screen-reader and keyboard checks must cover login, nested forms, dialog/drawer, errors, previews, dynamic options, reordered sections, table regions and charts' text alternative. No screen reader was run in this task.
- Print/PDF styles already repeat headers, wrap cells and use detailed records for wide reports. Verify representative long report pages, page breaks and actual printer settings later. Original legal DOCX wording/snapshots and Word visual-review gates are unchanged.
- No cache headers/real-network traces were inspected; fixed asset URLs are a deployment verification item, not a proven stale-cache incident. No LCP/INP/CLS score was measured.

## Security boundaries and rejected false positives

Keep server authorization/ownership checks, CSRF, escaped Blade and safe DOM text insertion. `innerHTML` for authorized server-rendered map fragments is not automatically an XSS finding; its origin/escaping must be reviewed before changing it. Native implicit checkbox labels and submit-button default types are valid. Third-party tenant information should not be autofilled from the officer's identity. Structured names and decimal/text field types reflect approved business/legal requirements. Existing SweetAlert2 and Chart.js are retained. Reports already paginate/cap exports and charts already have text data; no missing-alternative or unbounded-export finding is justified.

No external analytics, service worker, passkey migration, new UI framework, experimental API or package upgrade is proposed by this integration. Legacy stack advisories and internet-facing deployment restrictions remain open in project operations docs; dependency vulnerability data was not refreshed here.

## Guidance and verification evidence

Official retrieved guide IDs (all read during the bounded source reviews): `forms`, `accessibility`, `responsive-table`, `navigation-drawer`, `performance`, `html`. Version/privacy/provenance: [skills registry](../codex/SKILLS_REGISTRY.md). Guide sources at the reviewed commit: [forms](https://github.com/GoogleChrome/modern-web-guidance/tree/64a6d708f5a43f2cd3fde5d4d7db3905ba1bdf65/skills/modern-web-guidance/guides/forms), [accessibility](https://github.com/GoogleChrome/modern-web-guidance/tree/64a6d708f5a43f2cd3fde5d4d7db3905ba1bdf65/skills/modern-web-guidance/guides/accessibility), [performance](https://github.com/GoogleChrome/modern-web-guidance/tree/64a6d708f5a43f2cd3fde5d4d7db3905ba1bdf65/skills/modern-web-guidance/guides/performance). Use the runner to retrieve exact IDs and current fallback guidance before approved changes.

Fresh full regression: **237 tests / 1,903 assertions passed** on forced SQLite `:memory:`, PHP 8.2.12. Actual invocation and all other integration checks are recorded in [integration report](FRONTEND_INTEGRATION_REPORT.md); raw non-sensitive evidence is under `.qa/modern-web-guidance`. Source review is deliberately distinguished from browser/assistive-technology/production acceptance.

Next action: approve a bounded first modernization stage from the [roadmap](../plans/FRONTEND_MODERNIZATION_ROADMAP.md). No finding has been fixed in product code by this audit.
