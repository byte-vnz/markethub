# Accessibility review

The target is WCAG 2.2 AA. The [source audit](FRONTEND_MODERNIZATION_AUDIT.md) records the affected modules and exact checks. Source findings are evidence for the design. The [responsive test results](RESPONSIVE_TEST_RESULTS.md) report the Chromium baseline. Screen-reader, formal contrast and conformance checks remain pending. See the [W3C standard](https://www.w3.org/TR/WCAG22/).

| Area | Relevant criteria | Acceptance following approval |
| --- | --- | --- |
| Labels, table headers and errors | 1.3.1, 3.3.1, 3.3.2, 4.1.2 | Identify the control and its error programmatically. Keep descriptions, table header relationships and clear required instructions. |
| Navigation, table scrolling and modals | 2.1.1, 2.4.3, 2.4.7, 2.4.11 | Complete authorized navigation using only a keyboard. Keep focus visible and unobscured. Verify modal entry, cancellation, return and breakpoint switching. |
| Reflow, contrast and status | 1.4.3, 1.4.10, 1.4.11, 4.1.3 | Exercise narrow widths and zoom, inspect computed colors and verify text and live status. Preserve justified two-dimensional scrolling in data tables and the map. |

Existing positives include explicit login labels, native disclosures, skip link, text status next to color and text equivalents for dashboard charts. Prioritize actual barriers before cosmetic changes. No conformance rating has been assigned.

The implemented Chrome checks passed for narrow drawer focus and Escape, inert sibling content including the skip link, focus after resizing, native navigation and sign-out with the script blocked, keyboard arrow scrolling, field error focus and association, and map status under stale or failed requests. The focusable response summary moves focus without requiring a screen reader and links only mapped, rendered controls. A separate disposable HTTP regression confirmed invalid staff role/status values do not preselect a more privileged value. Reflow was checked at 320 CSS px and four larger widths. These results do not establish full WCAG conformance. Formal contrast, screen-reader and physical device checks remain open.
