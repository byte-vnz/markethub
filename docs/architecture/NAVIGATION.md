# Sidebar navigation

Menu configuration lives in config/navigation.php. Groups and items appear in array order. Each item declares its label, icon, named route, route parameters, permission and active route patterns. App\Support\Navigation filters with the authenticated user's existing can() permission checks, removes empty groups and resolves current links including resource type parameters. Controllers/policies continue to enforce permissions independently. No role names are embedded in menu visibility logic.

## Menu hierarchy
- Dashboard: Dashboard.
- Management: Market Stall Layout, Markets, Stall Management, Tenant Management, Businesses, Stall Assignments (existing agreement/history workflow).
- Billing & Collection: Rental Billing, Record Payment, Payment History, Outstanding Balances.
- Reports: All Reports, Occupancy Report, Collection Report, Tenant Report. All Reports highlights for report pages without a dedicated shortcut.
- Administration: User Management, Audit Logs.
- Settings: Market Sections, Stall Categories, Rental Rates, System Settings.
- Account footer: current name/role and POST/CSRF Sign out. Profile, Change Password and a separate Roles & Permissions editor are omitted because these routes do not exist.

All original links remain represented. Sections, categories and businesses expose existing record modules; report shortcuts use existing report routes. Inline SVGs in components/nav-icon.blade.php share a 24px viewBox/consistent stroke; the old sidebar had no icon library, and no dependency was added.

## Presentation and interaction
components/sidebar.blade.php is included once by layouts/app.blade.php. Branding and account footer stay fixed within the sidebar while the navigation scrolls. Native details/summary categories preserve semantic keyboard disclosure behavior and show aria-expanded. Active pages use aria-current, and their group opens on each load.

resources/js/app.js persists only sidebar display preferences in localStorage key markethub.sidebar.v1. It catches unavailable/invalid storage. No application records are stored there. Native category states animate via the Web Animations API, honor reduced motion and ignore repeated clicks during transition. Stored group preferences are restored on navigation, with active-page expansion taking precedence.

At 1024px and above, sidebar width can switch between 16rem and a 4.75rem icon rail. The main flex column adjusts automatically; compact links have native title tooltips and all permitted links remain available. Below 1024px the sidebar becomes a drawer with backdrop, close button and hamburger trigger. Background content becomes inert; focus cycles within the drawer, Escape restores trigger focus, overlay dismisses and selecting a link closes it. Desktop width preference does not hide labels in the mobile drawer.

styles are in resources/css/app.css; npm run build compiles Tailwind and copies app.js into public/assets. No migration, dependency or business service changes are needed. The local Apache Alias and authentication remain unchanged.

## Verification
Four navigation feature tests cover administrator links, staff/viewer permission filtering, hidden empty groups, protected routes, nested record/report highlighting and POST logout presence. The full existing workflow suite also renders application/report screens and exercises CRUD/financial behavior. Final counts are recorded in codex/CURRENT_STATE.md.

Browser QA used a disposable SQLite preview on localhost:8099 with fictional demo records: desktop category toggle, nested Stall Management selection, reload restoration, compact icon rail, 390px mobile and 820px tablet drawer, Escape, link dismissal, no horizontal page overflow and no captured JavaScript errors. Screenshots: .qa/sidebar-desktop.png and sidebar-tablet.png. The preview router was corrected during QA to serve static assets normally. Actual live MySQL data was untouched. Full assistive-technology/device coverage is outside this checkpoint.

Help & Support now follows Settings: User Manual, Quick Reference Guide, System Workflows, FAQs. All entries use existing dashboard.view and authenticated active-account routes. Documentation files/figures are private allowlisted responses; no source directory is exposed.
