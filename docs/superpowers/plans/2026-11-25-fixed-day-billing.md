# Adjustable Fixed-day Billing Implementation Plan

> **For agentic workers:** Use superpowers:subagent-driven-development or superpowers:executing-plans; do not migrate, seed, connect to or modify the live database.

**Goal:** Prepare seven-day adjustable bills with record-only CUSA while retaining existing monthly history.
**Architecture:** Default-disabled opt-in; additive coverage dates and longer period keys; immutable assessment basis distinguishes fixed-day from historical monthly calculations.
**Tech Stack:** PHP 7.4 syntax, Laravel 8.83, MySQL, Blade, local Tailwind 3, disposable SQLite tests.
**Spec:** ../specs/2026-11-25-fixed-day-billing-design.md

## Global constraints
- Live database untouched; migration files are prepared only.
- Existing records, legal source templates and posted money remain unchanged.
- No policy is fabricated or approved; concurrent occupancy retains its separate authority gate.
- New fixed-day charges exclude CUSA. Old monthly snapshots retain their original treatment.
- No automatic posting or new dependency.

## Review focus
- Multiple bills within one calendar month; no overlapping intervals or mixed-mode generation.
- Exact interval alignment across month/year boundaries and incomplete final terms.
- Old schema while feature disabled, and populated migration preservation.
- Duplicate requests, backdating and later assignment end dates.
- Preview/save parity, draft replay, exports and monthly-only contract/override gates.

## Tasks
- [x] Schema: write migration and populated-preservation tests; verify against disposable SQLite only, document MySQL limitation.
- [x] Finance: fail tests for weekly assignment without policy, CUSA exclusion, default/custom intervals, duplicate/misaligned/overlapping billing, posting and legacy preservation; implement config, models, calculation, assignment and billing integration.
- [x] HTTP/UI: Form Requests, unified registration/preview, billing mode/date inputs and period labels; preserve native form operation and permissions; focused HTTP tests.
- [x] Documentation: update codex docs and manuals, fictional screenshots/workflows, regenerate and inspect exports, verify Help routes.
- [x] Final review: fix findings, run applicable isolated regression suite, native lint/PHP compatibility checks, build and browser checks; report pending operator deployment.

Completed preparation on 26 November 2026. Final forced SQLite suite: 277 tests / 2,230 assertions. Chrome: 44 responsive checks, keyboard and no-script billing, two independent weeks and explicit posting. Actual rebuilt Help publication: 1 test / 46 assertions. See the operator runbook and user-manual/VERIFICATION.md. Live deployment, native PHP7.4/MySQL concurrency and Word rendering are not completed or claimed.

## Work ownership
Root: finance services/models/config and finance tests, integration and verification.
Schema worker: new migration and migration-specific test only.
UI worker: requests/controllers/views/client JS, HTTP tests; no finance services except TenantDataService selection allowlist by agreement.
Documentation worker: operator/manual text and workflows; coordinate exports/screenshots with root.
