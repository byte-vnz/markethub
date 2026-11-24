# XAMPP setup
Observed PHP 8.2.12 at C:/xampp/php/php.exe, Apache syntax OK, Node 24.12.0/npm 11.19.0. The expected XAMPP mysql/bin binary is absent; MySQL client is C:/Program Files/MySQL/MySQL Server 8.4/bin/mysql.exe (8.4.7). Server version has not been authenticated/verified.

User will configure MySQL and migrate later. Do not reuse unrelated databases. Create markethub_lgu with utf8mb4_unicode_ci, a dedicated local user and least-privilege runtime grants. Use a separate migration account if required. Run SELECT VERSION() and record actual version before deployment. Test on the actual server; MySQL 5.7 compatibility is intended but unverified.

The scoped apache-markethub.conf is now included from live httpd.conf and the manual XAMPP Apache instance was restarted on 2026-11-17; syntax and live guest URL checks passed. See APACHE_403_FIX.md for backup and rollback. Intended URL http://localhost/market-stall/. Do not serve project root or use artisan serve as deployment. Root .htaccess denies access before mapping. Verify /.env, /composer.json, /vendor/autoload.php, /storage/logs/laravel.log, /prototype/index.html are not exposed under the application URL. The public directory contains no sensitive files.

Do not replace shared PHP or modify unrelated apps. For PHP 7.4 acceptance, use an isolated compatible environment and the same lockfile; run the complete regression suite. Upgrade to supported PHP/Laravel before internet exposure.
