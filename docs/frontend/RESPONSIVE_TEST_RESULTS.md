# Responsive and browser baseline

20 November 2026. Chrome 152.0.7977.83 over localhost. Headless Chromium DevTools emulation used 375, 768, 1024 and 1440 CSS px at a 900 px viewport height, scale factor 1. Laravel ran under PHP 8.2.12 with a guarded SQLite copy populated by DemoSeeder. The fixture had 48 fictional stalls and fictitious accounts and transactions. Browser navigation and interactions used the real Laravel pages and session login.

| Width | Pages opened | Page-level horizontal overflow observed |
| --- | --- | --- |
| 375 px | 25, all HTTP 200 | Market Sections measured 520 px document scroll width. Identified tables and the map also have intentionally contained scroll regions. |
| 768 px | 25, all HTTP 200 | None in this sweep |
| 1024 px | 25, all HTTP 200 | None in this sweep |
| 1440 px | 25, all HTTP 200 | None in this sweep |

The sweep covered Dashboard, map and layout editor, stall list/create, section list/edit, tenant list/create/profile, assignment form, category and policy settings, override creation, contracts list/draft screen, billing, payments and allocation form, receivables, reports, staff, audit, settings and Help. The guest login page was captured separately. Module page loads do not imply that every action was exercised at all four widths.

| Interaction at 375 px | Baseline result |
| --- | --- |
| Login and sign out | Synthetic accounts used the existing authentication forms and session redirects. |
| Drawer and Escape | Drawer opened and moved focus to Close. Escape closed it and restored focus to Menu. The open sidebar had no modal role or `aria-modal`. |
| Shared script blocked | Menu could not open and the sidebar stayed hidden. |
| Map | Selecting a stall fetched details, marked one stall selected and moved focus to the detail heading. |
| Stall market and section | The selected market loaded four eligible sections. |
| Tenant search and registration | Search returned the expected fictional tenant. Selecting a fictional business category and a stall produced the automatic monthly rental preview in the registration form. |
| Rental override proposal | Proposed amount preview rendered with the approval-required label. |
| Staff validation | A duplicate email produced a server error. The attempted safe name disappeared. The email lacked `aria-invalid` and focus remained outside the main content. |
| Role restrictions | Viewer had no Staff link and `/staff` returned HTTP 403. |
| JavaScript exceptions | No uncaught page exceptions were captured by the DevTools `Runtime.exceptionThrown` event. This check did not collect every console warning or network error. |

At 375 px, the Tenant Management table overflowed its wrapper. The wrapper lacked an explicit tab stop and accessible name. The Sections table already has both. The page-wide Sections overflow is a separate problem. Contract generation/issuance, actual contract downloads, persisted registration or override approval were outside the browser baseline. The focused Laravel suite covers related existing workflows on disposable fixtures. Browser repeat, Edge/Firefox/Safari, touch hardware, screen reader, 320 CSS px, zoom, contrast and print checks are scheduled after approval.

A temporary DevTools positioning probe on Sections reduced document width from 520 to 360 px while its table scroll width remained 514 px. The probe reverted the inline style and did not edit files. The targeted PHPUnit run passed **70 tests and 796 assertions** across SidebarNavigation, SectionManagement, UnifiedTenantRegistration, RentalOverrideWorkflow, LeaseContractWorkflow and HelpCenter in 1:35.390 on SQLite.

Raw observations are in `../../.qa/frontend-modernization/baseline-browser.json`. The 23 fictional baseline screenshots are in `../../.qa/frontend-modernization/baseline/`, including Dashboard at all four widths, Market Sections at 375 px, drawer and map selection, registration preview and staff validation.

## Implementation verification

Chrome 152.0.7977.83 loaded all 25 screens at **320, 375, 768, 1024 and 1440 CSS px**. All 125 responses were HTTP 200 and no page exceeded the emulated viewport width. The wide Sections table retained internal horizontal scroll on a 375 px viewport. Dashboard uses an explicit single grid column before the large-screen breakpoint so charts fit at 320 px. The browser passed a separate interaction run after review corrections.

- Keyboard checks covered table arrow scrolling, drawer entry and Escape focus return, the skip link's inert state, and drawer dismissal and focus when resizing to desktop. Blocking the app script left permissions-filtered navigation, sign-out and the authorized stall link usable.
- Map selection moved focus to the returned detail heading. Inverting two actual detail responses left the newer request in control of busy state and selection. Simulating a network error produced a short retry announcement.
- Stall section options, tenant search, category and stall selection, automatic rent preview, proposed override preview and draft save worked in the fictional fixture. The staff duplicate-email case kept safe values, focused the summary and moved focus to the field when its link was activated. Password values were blank after the failed save.
- A fictional lease viewed in the browser returned an authenticated DOCX attachment with a ZIP signature. Void and Terminate presented distinct prompts. Cancel retained the page and the confirmation replay preserved the chosen existing destination. The harness intercepted the replay before the request. An Occupied Stalls print report rendered with data, and the viewer remained denied access to Staff.
- None of these Chrome runs recorded uncaught page exceptions. Browser evidence includes the viewport sweep and reviewed interaction rerun in `../../.qa/frontend-modernization/after-browser.json` and `../../.qa/frontend-modernization/after-browser-interactions.json`. Captures and the fictional print PDF are in `../../.qa/frontend-modernization/after/`.

Edge 157.0.4341.60 also passed the 25-screen 375 CSS px smoke sweep, with all HTTP 200, no page-level overflow, and the same drawer, table, map, form, contract-download, print-view and role interactions. No uncaught page exceptions were captured. Evidence is in `../../.qa/frontend-modernization/after-browser-edge.json`.

The final disposable PHP suite passed **247 tests and 1,988 assertions**. PHP 7.4 grammar validation parsed **316 files** without errors. The asset build and JavaScript syntax checks passed. The post-change legacy scan found no matches in its 52 rules. The compiled assets and changed source inventory are recorded in `../../.qa/frontend-modernization/approved-change-inventory.json`.

Viewport emulation and keyboard checks do not cover physical touch hardware, a screen reader, formal WCAG conformance or every office browser. The [accessibility review](ACCESSIBILITY_REVIEW.md) describes the remaining checks.
