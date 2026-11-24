# Modern Web Guidance in this modernization

The project uses the installed official 1.0.1 CLI and the existing privacy runner described in the [skills registry](../codex/SKILLS_REGISTRY.md). The CLI ran from the project root before and after implementation. Searches used generic technical terms. Implementation follows the [approved design](../superpowers/specs/2026-11-20-frontend-modernization-design.md). See the [baseline scan](modern-web-guidance-scan.json) and [post-change scan](modern-web-guidance-scan-after.json).

| Guide IDs retrieved | Design decision to verify after implementation |
| --- | --- |
| `forms`, `accessibility`, `accessible-error-announcement` | Focus and link returned server errors. Preserve native constraints, help descriptions, field identity and secret handling. |
| `responsive-table` | Named keyboard entry to overflowing semantic tables using the current Sections/Reports pattern. |
| `navigation-drawer`, `html` | Keep a visible navigation fallback and expose accurate modal semantics only during the enhanced narrow-screen interaction. |
| `dialog` | Give confirmations accurate action names and safe initial focus while retaining the current dialog library and submission semantics. |
| `performance`, `optimize-script-priority` | Measure non-dashboard chart requests and Help images. Preserve defer order and useful first-view resources. |

| Guide applied | Implementation decision |
| --- | --- |
| `forms`, `accessible-error-announcement` | The guidance to “link inputs with help text or error messages” is applied via the new field error components. The summary links only to rendered controls. Blade keeps server errors usable and JavaScript moves focus when available. |
| `accessibility` | Distinct repeated action names use the existing visually hidden text technique. Per-request map announcements and focus on the returned heading separate status from detailed content. |
| `responsive-table` | The instruction to add a `tabindex="0"` to the scroll container became named regions with real headers, including generated Help tables. Existing table layouts remain familiar. |
| `navigation-drawer`, `html` | “Mark the rest of the page `inert` while the drawer is open.” `app.js` includes the skip link and restores state at the desktop breakpoint. Native disclosures remain available in the base layout. |
| `dialog` | “Put `autofocus` on the safe action” informed the existing SweetAlert2 `focusCancel` setting and action-specific title. The clicked submitter, reason and destination survive confirmation. |
| `performance` | “only download bytes required for the immediate viewport” informed the Blade script stack. Dashboard claims the existing deferred asset and other pages skip it. |
| `optimize-image-priority` | “for standard below-the-fold images” use native lazy loading. Help keeps the first allowlisted figure eager and defers those that follow. |

The existing custom drawer, SweetAlert2, text-equivalent charts, browser-native form controls and print layouts remain appropriate for the installed stack. The native mobile fallback and the `inert` capability check avoid requiring newer overlay commands or a polyfill. No changes depend on an origin trial. The regression and measurement details are in [responsive test results](RESPONSIVE_TEST_RESULTS.md) and [performance comparison](PERFORMANCE_COMPARISON.md).
