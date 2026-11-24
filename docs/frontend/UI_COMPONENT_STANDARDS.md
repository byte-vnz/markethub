# MarketHub frontend component standards

Use the existing project palette, type, spacing, Blade templates and Tailwind build. Prefer the existing `.panel`, `.btn`, `.toolbar`, `.tablewrap`, `.badge`, `.notice` and status classes. Promote markup into a shared Blade component only when repeated implementation or consistency justifies it.

| Component | Current pattern and modernization rule |
| --- | --- |
| Layout and navigation | Use a first-level heading in the main landmark, visible skip link, breadcrumb and central permission-filtered sidebar. At narrow widths, allow the native links and sign-out form to remain usable before or without enhancement. |
| Forms | Use native inputs and linked labels, fieldsets for related controls and useful format hints. Show required state consistently with server rules. Associate error text with its control and provide navigable summary links where field identity is known. Preserve only safe values after errors. |
| Buttons and confirmations | Use buttons for actions and links for navigation. Use the existing shared dialog for consequential actions and name the exact action being confirmed. Keep the visible cancel action and focus return. |
| Tables and filters | Preserve table headers and all operational columns. Use a named, keyboard-focusable scroll region where width is constrained. Keep filters labelled, accessible action names distinct, and pagination and exports available. |
| Status and snippets | Include text for financial and occupancy states. Use short status regions for async transitions and reserve alerts for failures requiring attention. Use busy state while the current request is pending. |
| Charts and images | Keep text equivalents. Load local chart resources on pages that use them. Use intrinsic sizes and lazy loading selectively based on measurement and location. |

The browser preference remains Baseline Widely Available. Verify responsive, keyboard and role-based behavior with fictional records. Document the supported fallback when an enhancement needs a newer feature. See the [implemented response](FRONTEND_MODERNIZATION_AUDIT.md#implemented-response) for the changes that apply these standards.
