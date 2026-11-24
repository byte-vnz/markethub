# Verification checkpoint — 2026-11-17

| Check | Observed result |
| --- | --- |
| `C:\xampp\php\php.exe vendor/phpunit/phpunit/phpunit` | PASS: 33 tests, 175 assertions; 43.592 seconds, 52 MB; PHPUnit 9.6.39 on PHP 8.2.12. Forced testing environment, SQLite in memory. |
| `C:\xampp\php\php.exe artisan view:cache` | PASS: views cleared and Blade templates cached. |
| `C:\xampp\php\php.exe scripts/check-php74.php` | PASS: 164 files parsed under PHP 7.4 grammar, zero failures. Includes compiled views; not runtime execution on PHP 7.4. |
| Native `php -l` over app/config/database/routes/tests/scripts | PASS: 99 PHP files, zero failures on PHP 8.2.12. |
| `npm run build` | PASS: Tailwind 3.4.17 compiled/minified and local JS copied. Nonfatal stale Browserslist data notice. |
| `composer validate --no-check-publish` | PASS: composer.json valid. |
| `composer check-platform-reqs` | PASS on installed PHP 8.2.12/extensions. This does not substitute for the requested 7.4.30 runtime. |
| `httpd.exe -t -c 'Include "C:/xampp/htdocs/market-stall/docs/operations/apache-markethub.conf"'` | PASS: Syntax OK. Include not installed into running Apache configuration. |
| Composer audit | Seven advisories: six laravel/framework, one league/flysystem; two abandoned packages. JSON evidence in operations/composer-audit.json. This check is not clean. |
| Original prototype SHA256 | 051ECD81F2D4084C096ED8603E6E23BDB6DBEF85E89C06E4C5A6CA25F5C9BE5D; preserved reference verified. |

## Behavioral coverage
Login/logout/disabled accounts/login throttling and password-reset session invalidation; viewer PII/mutation denials and collection-officer reversal denial; CRUD/scope/archive checks; layout persistence and overlap rejection; assignment conflicts/history/transfer/renewal/billed-month boundaries; rate snapshots and duplicate bills; draft-only billing command; partial/full payments, stale drafts, overpayments and cross-tenant allocations; reversals, confirmation and totals; receipt correction lineage and draft cancellation; adjustment idempotency including full negative credit; append-only financial detail; every report/main screen; real CSV/XLSX/PDF downloads and CSV formula escaping; guarded repeatable demo seeding; exact centavo validation.

Normal tests do not connect to MySQL. `phpunit.mysql.xml` and two-process worker test were authored but **not executed**. Their sole named database is disposable markethub_lgu_test and the test explicitly runs migrate:fresh there. No claim of actual simultaneous MySQL-write correctness is made.

## Browser observations
Chrome, temporary localhost:8099 application with isolated fictional SQLite fixture. Logged in, opened dashboard and A-001 map details, observed tenant/store/rent/latest payment/history. Desktop and 820px tablet map inspected. Dynamic status color retention was fixed in Tailwind safelist and visually rechecked. Created/reviewed/posted fictional PHP 100 payment; bill balance changed from PHP 1,500 to PHP 1,400. Signed out. Screenshots: .qa/map-desktop.png, map-tablet.png, payment-tablet.png, verified-balance.png.

The temporary server was stopped and disposable fixture database/init script removed. Screenshots contain fictional demonstration records only. An independent visual review accepted the desktop/tablet screenshots; it did not establish full keyboard, screen-reader or mobile coverage. Source heuristic UI warnings were inspected as adjacent CSS-rule false pairings rather than the actual notice/status foreground-background combinations.

## Not verified
MySQL server version/migrations/integration/parallel locking, actual PHP 7.4.30 execution, activated Apache routing, production deployment, restore rehearsal, complete LGU policy acceptance, official receipt integration and all master-spec acceptance steps against MySQL. See codex/KNOWN_ISSUES.md and TASK_BACKLOG.md. No passing test result overrides these limits.

## Default administrator follow-up
Full regression command `C:\xampp\php\php.exe vendor/phpunit/phpunit/phpunit` passed **52 tests, 282 assertions**, 40.086 seconds, 52 MB on PHP 8.2.12. Nineteen new seeder cases exercised normal/individual db:seed, login/management access, duplicate prevention, immutable existing account data/roles/password, conflict warnings, local/custom identity, missing/invalid settings and production/staging denial including --force. Test-harness output mocking was disabled to inspect real console messages; before/after records are compared as fresh persisted attributes.

`C:\xampp\php\php.exe scripts/check-php74.php`: **167 files, zero failures**. Native php -l passed config/default_admin.php, database/seeders/DefaultUserSeeder.php, database/seeders/DatabaseSeeder.php and tests/Feature/DefaultUserSeederTest.php. Independent focused source review found no material issues. Private .env/MySQL unchanged; no destructive commands used. Previous browser/assets/Composer results above belong to the earlier application checkpoint and were not rerun for this backend-only change. Final suite output: .qa/phpunit-default-admin-final.txt.
