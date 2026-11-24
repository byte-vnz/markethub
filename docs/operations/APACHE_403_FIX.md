# Apache 403 resolution — 2026-11-17

## Cause and changes
Apache's live DocumentRoot is C:/xampp/htdocs. The MarketHub project root intentionally has a deny-all .htaccess. The prepared public-only Alias was not included in live httpd.conf. Apache error log recorded AH01630 for /market-stall and /market-stall/public. The latter URL did not bypass the root denial. Windows file access was sufficient: public assets became readable as soon as the Alias was active.

Backed up C:/xampp/apache/conf/httpd.conf to C:/xampp/htdocs/market-stall/.qa/apache/httpd.conf.before-markethub-20261117.bak before editing. Appended a single Include for docs/operations/apache-markethub.conf to the live httpd.conf. Alias /market-stall maps only public/, with parent directory denied, public directory Require local, Options -Indexes +FollowSymLinks, AllowOverride All and DirectoryIndex index.php. The global DocumentRoot, vhosts and other XAMPP apps were not changed. `httpd.exe -t` returned Syntax OK.

After restarting the manual XAMPP Apache process, static assets loaded but Laravel routes and sensitive file probes returned 500. The Apache log showed AH00124 (ten internal redirects). The public .htaccess lacked RewriteBase for this Alias. Added `RewriteBase /market-stall/` immediately after RewriteEngine On; live routing then worked. The root deny-all .htaccess remains intact. Cleared Laravel event/view/application/route/config caches using `php artisan optimize:clear`.

## Live checks
- GET /market-stall -> 301 to /market-stall/; GET /market-stall/ -> 302 to /market-stall/dashboard; guest dashboard -> 302 to /market-stall/login; GET login -> 200 with CSRF field and CSS URL under /market-stall.
- Local CSS, app.js and chart.js returned 200. /market-stall/assets/ returned 403 (directory listing disabled).
- Probes for .env, .git/config, composer.json, vendor/autoload.php, storage/logs/laravel.log, config/database.php, docs/MASTER_SPEC.md, prototype/index.html and public/index.php beneath the Alias returned 404. No sensitive content was returned. Trailing slash on directory-looking probes may redirect once before 404.
- Existing /appone/ returned 200 after Apache restart; localhost/ continued to redirect to the XAMPP dashboard.
- Apache server log identified PHP/8.2.12; CLI is PHP 8.2.12. The requested PHP 7.4 runtime remains unavailable, though project syntax was checked previously. No dependency changes were made.
- `php artisan migrate:status` was read-only and showed all five project migrations marked Yes. A read-only database probe returned MySQL 8.4.7 and confirmed admin@marketstall.local does not exist. No database writes, migrations, seeding or resets were performed in this fix.

Authenticated dashboard, map, tenant, billing, payment, AJAX and CRUD operations were not exercised on the live MySQL installation because no known administrator account is configured. The prior isolated SQLite feature suite covered those workflows but does not replace live acceptance. Configure the administrator per DEFAULT_ADMIN.md or another authorized account, then perform the live acceptance flow. Do not claim those checks passed until actually executed.

## Rollback
1. Stop the manually started Apache instance from XAMPP Control Panel or an approved operations window.
2. Restore the saved httpd.conf backup to C:/xampp/apache/conf/httpd.conf. Remove only the added `RewriteBase /market-stall/` line from public/.htaccess. Keep the project root deny rule.
3. Run `C:/xampp/apache/bin/httpd.exe -t` and restart Apache. This returns the project to its former 403 while preserving other sites. The backup is kept in ignored .qa/apache/; copy it to an operations-controlled location before deleting the workspace.
