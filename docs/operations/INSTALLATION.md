# Local installation

Use PowerShell from `C:\xampp\htdocs\market-stall`. PHP at the inspected XAMPP path is **8.2.12**, not 7.4.30. Dependencies are pinned for PHP 7.4.30 compatibility; an actual 7.4 runtime test remains required. Enabled extensions include bcmath, pdo_mysql, pdo_sqlite, mbstring, openssl, fileinfo, gd, dom, xml and zip.

## 1. Database — user deferred this step
Use your MySQL administrator tool to verify `SELECT VERSION()`, create the new `markethub_lgu` database using utf8mb4/utf8mb4_unicode_ci and create a dedicated local application account. Do not reuse or reset another application's database. MySQL client **8.4.7** was found; server version remains unverified. App migrations use InnoDB, foreign keys, DECIMAL and unique/index constraints and avoid intentional MySQL 8-only syntax.

Configure `.env` with DB_HOST, DB_PORT, DB_DATABASE=markethub_lgu, DB_USERNAME and DB_PASSWORD. The generated `.env` contains no usable database credentials. Never put passwords in Git or CLI arguments. Existing APP_KEY was generated; retain it. For a fresh clone only, copy `.env.example` to `.env` if `.env` does not exist, then run `php artisan key:generate` once.

## 2. Install, migrate and build
```powershell
composer install --prefer-dist --no-interaction
C:\xampp\php\php.exe artisan migrate --force
C:\xampp\php\php.exe artisan db:seed --class=RoleSeeder --force
npm ci
npm run build
C:\xampp\php\php.exe artisan view:cache
```
Do not use `migrate:fresh` against development/production data. The project intentionally uses the normal development Composer autoloader; optimized generation was unusually slow on the inspected filesystem. `composer.lock` and `package-lock.json` are supplied. Composer security blocking is disabled **only for this project** because the user mandated the unsupported framework; review the audit before any deployment.

## 3. Optional fictional training seed (local/testing only)
Set `APP_ENV=local` and a unique `DEMO_PASSWORD` of at least 16 characters in `.env`, then:
```powershell
C:\xampp\php\php.exe artisan db:seed --class=DemoSeeder
```
The six accounts are `superadmin@markethub.test`, `marketadmin@markethub.test`, `staff@markethub.test`, `collector@markethub.test`, `treasury@markethub.test`, and `viewer@markethub.test`. All use the password **you supplied**. No password is shipped. Remove DEMO_PASSWORD after successful seeding. The seeder refuses production, refuses conflicting existing account emails, and does not reset records or credentials on a repeated run. DatabaseSeeder seeds roles and invokes the disabled-by-default DefaultUserSeeder; it never invokes DemoSeeder automatically. For an administrator without fictional market data, follow DEFAULT_ADMIN.md.

## 4. Apache
Follow XAMPP_SETUP.md and include apache-markethub.conf. Validate configuration, restart Apache yourself, and open http://localhost/market-stall/. Serve **public/** only. The provided include is local-only. No Apache global configuration or services were changed during implementation. `artisan serve` is not the deployment solution; a temporary localhost PHP server was used only for isolated UI QA and stopped afterward.

## 5. Tests
```powershell
C:\xampp\php\php.exe vendor\phpunit\phpunit\phpunit
C:\xampp\php\php.exe scripts\check-php74.php
```
Default phpunit.xml forces APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory: and an isolated APP_URL/key. It does not touch MySQL.

Optional real concurrency test: create a dedicated disposable **markethub_lgu_test** database, supply MySQL connection credentials through protected environment configuration, then run:
```powershell
C:\xampp\php\php.exe vendor\phpunit\phpunit\phpunit -c phpunit.mysql.xml
```
**This test runs migrate:fresh inside markethub_lgu_test and destroys that test database's tables.** It guards the exact database name/driver/environment before migration, uses two PHP worker processes to race full allocations, and requires one winner with zero outstanding balance. Do not put valuable data there. It was not run in this session.

## Scheduled drafts
`billing:draft YYYY-MM --due=YYYY-MM-DD --actor=USER_ID` creates drafts only. Actor must be active with billing.generate; historical months additionally require financial.backdate. No automatic posting exists. Optional scheduler configuration: BILLING_SCHEDULED_DRAFTS=true, BILLING_ACTOR_ID and approved BILLING_DUE_DAY (1–28). Otherwise it remains disabled. In Windows Task Scheduler run `C:\xampp\php\php.exe C:\xampp\htdocs\market-stall\artisan schedule:run` every minute, with the project as working directory. Review skipped partial-month agreements and command failures.
