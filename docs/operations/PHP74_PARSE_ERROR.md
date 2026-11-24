# PHP 7.4 dynamic class parse error — 2026-11-24

## Confirmed source defect

The production screenshot reports `syntax error, unexpected '('` in RecordController.php at the dynamic model construction used by the generic record routes. PHP 7.4 cannot parse parenthesized expressions after `new`; this syntax was added in PHP 8.0. Because the controller cannot load, even the Markets index fails before its action executes.

Three occurrences were replaced with a class-name variable followed by `new $modelClass()`: RecordController::create, RecordController::save, and RecordRequest::rules. Authorization, transactions, validation, model selection and data remain unchanged.

The prior `createForVersion(7.4)` parser check is best-effort and accepted this newer syntax. The checker now explicitly rejects a `T_NEW` token followed by `(`, ignoring whitespace/comments, and labels its output as best-effort. Previous successful grammar scans are not proof of PHP 7.4 compatibility. Actual PHP 7.4 runtime acceptance remains required.

## Operator application

Back up the deployed source and retain server-specific Apache and environment configuration. From `D:\xampp\htdocs\markethub`, pull the fix with `git pull --ff-only origin main`. If Git reports local conflicts, stop and reconcile them; do not discard the production `.env` or domain-root RewriteBase adjustment. No migrations, seeding, dependency updates or APP_KEY regeneration are needed for this fix.

Set `APP_ENV=production` and `APP_DEBUG=false` in the server's private `.env`. The screenshot displays an exception trace publicly. Apply the environment configuration and lint using the server's PHP executable:

```powershell
& "D:\xampp\php\php.exe" -v
& "D:\xampp\php\php.exe" -l app/Http/Controllers/RecordController.php
& "D:\xampp\php\php.exe" -l app/Http/Requests/RecordRequest.php
& "D:\xampp\php\php.exe" artisan config:cache
```

Restart Apache through the existing service controls if OPcache retains old code. With an authorized account, recheck `/manage/markets` and the Markets create form. Exercise create/update and relation validation in an isolated staging database, not by inserting test records into production. Inspect the private application log for any further errors; never enable public debugging to share them.

This source correction is not production certification. The existing unsupported-stack, MySQL concurrency and operator acceptance gates remain.

Verification: the strengthened checker rejected the original controller/request (2 failing files, exit 1), then passed 316 files after all three replacements (exit 0). PHP 8.2.12 lint passed the two application files and checker. Full forced SQLite :memory: suite passed 247 tests / 1,988 assertions in 04:16.112 (80 MB). `git diff --check` passed. No native PHP 7.4 runtime, production session, MySQL locking, migration or data write was tested/performed. Existing functional tests cover generic record index/create routes and record/section/stall workflows. Operator must lint with D:/xampp/php/php.exe and verify the deployed Markets screen after applying the patch.
