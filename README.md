# MarketHub — LGU Market Stall Management

Laravel application for multiple public markets, stalls, tenants/businesses, assignment history, rental agreements/billing and traceable payment recording. Reference prototype preserved in `prototype/index.html`. Application data uses MySQL; localStorage is not an application database.

## Start here
1. Read `docs/operations/INSTALLATION.md` and `docs/operations/XAMPP_SETUP.md`.
2. Configure `.env` for your own **markethub_lgu** MySQL database. Database creation/migration was deferred by the user; it has not been done.
3. Install locked dependencies, run migrations and role seeding, optionally seed fictional local training data, and compile assets.
4. Configure the supplied Apache public-only Alias. Intended URL: **http://localhost/market-stall/**. Project-root access fails closed until this mapping is installed.

For a local administrator without demo market data, follow [Default administrator setup](docs/operations/DEFAULT_ADMIN.md). Default login: admin@marketstall.local; provisioning is disabled until you opt in and supply a strong password.

## Implemented application
- Session login/logout, throttling, six predefined roles, model policies, disabled-account checks and password-reset session invalidation.
- Markets, sections, categories, stalls, tenants and businesses; eligible records archive via status, with historical links retained.
- Interactive grid map, persisted positions/sizes and tenant/billing/payment/history details; separate occupancy/payment statuses.
- Transactional assignment, end, transfer and renewal; active occupancy uniqueness and historical dates.
- Effective rental rates and agreement snapshots; monthly draft generation, explicit posting, append-only approved bill adjustments and receivables.
- Payment drafts, review/posting, exact allocations, partial/full payments, draft voiding, Treasury confirmation, authorized reversals and linked corrections. Original records remain intact.
- Database-derived dashboard, 16 report queries, pagination and CSV/XLSX/PDF/print exports; audit and staff/settings screens.
- Guarded fictional seed: one market, four sections, 48 stalls, 28 tenants, three months of bills, partial/full/unpaid examples and six role accounts.

## Verification and limits
See `docs/codex/CURRENT_STATE.md` for the latest exact test evidence, `docs/codex/KNOWN_ISSUES.md` for limitations, and `docs/codex/HANDOFF.md` for the next task. Tests use disposable SQLite; MySQL integration/concurrency and actual PHP 7.4.30 execution remain pending. The observed runtime is PHP 8.2.12. Composer resolves against platform **7.4.30**, framework **8.83.29**; assets use Tailwind **3.4.17**.

PHP 7.4/Laravel 8 are unsupported. `docs/operations/composer-audit.json` records known advisories. This build is not certified for internet-facing production. Proration, penalties, advance credits and automated posting are not enabled without LGU policy.

## Development
```powershell
C:\xampp\php\php.exe vendor\phpunit\phpunit\phpunit
npm run build
C:\xampp\php\php.exe scripts\check-php74.php
```
Read `AGENTS.md` and `docs/codex/` before further changes.

## Unified tenant management (2026-11-19)

Unified registration, nested businesses, 21 category schedules, approved rental policies and Inner/Outer assessed rentals are implemented in source. New reports bring the catalog to 19; legacy monthly rates remain for historical agreements. Read [implementation and deployment](docs/UNIFIED_IMPLEMENTATION_REPORT.md), [verified results](docs/UNIFIED_TEST_RESULTS.md), and [updated manuals](docs/user-manual/README.md). Live feature migrations remain pending operator execution.

The Market Sections and rental override enhancement adds section metadata and active stall selection, plus a separately approved assignment rental workflow and report. See the [implementation and operator rollout](docs/SECTION_OVERRIDE_IMPLEMENTATION_REPORT.md), [verification results](docs/SECTION_OVERRIDE_TEST_RESULTS.md) and [v1.2 user manuals](docs/user-manual/README.md).
