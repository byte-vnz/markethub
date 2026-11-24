# Security and upgrade gate
- Public-only Apache mapping verified with direct-file requests; local-only network access.
- APP_DEBUG=false; APP_KEY generated and protected; .env never committed.
- HTTPS and SESSION_SECURE_COOKIE=true for any networked deployment.
- Named least-privilege DB account; backups encrypted and restore rehearsed.
- Random local-only demo passwords; never run demonstration seeder in production.
- CSRF, session regeneration/invalidation, login throttling, Form Requests, policies, audit redaction and posted-transaction invariants covered by tests.
- Review composer audit and npm audit results. Required PHP 7.4/Laravel 8 are unsupported; this release is NOT certified for internet-facing production.
- Upgrade path: clone into isolated branch/environment, select currently supported PHP and Laravel releases, increment framework major versions using official upgrade guides, update Spatie/export libraries and lockfiles, run auth/financial/concurrency/export regressions and MySQL UAT, then stage/rollback rehearsal before release.
