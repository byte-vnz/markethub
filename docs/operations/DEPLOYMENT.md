# Deployment gate

This is an implementation checkpoint, not a production deployment. User will set up MySQL/migrate later. Apache configuration is prepared and syntax-checked, not activated.

1. Backup first and rehearse restore (BACKUP_RECOVERY.md).
2. Verify installed MySQL server version, dedicated database/account, InnoDB/utf8mb4, runtime PHP and extensions.
3. Install lockfiles, preserve APP_KEY, configure environment without committing secrets, run forward migrations and role seed.
4. Compile local assets; set APP_DEBUG=false. Never seed demo accounts/data in production.
5. Install public-only local Apache mapping; verify intended URL and direct-file denial for .env/vendor/storage/prototype/source paths. Do not expose the project root.
6. Run isolated suite plus actual MySQL concurrency/integration tests; test full acceptance workflow with role-separated accounts, logout/login and server restart persistence.
7. Obtain LGU policy decisions and Treasury UAT. Record backup/rollback owner, release version, reconciliation totals and authorization sign-off.
8. Before internet-facing use, upgrade the unsupported PHP/Laravel stack and resolve applicable dependency advisories. The 2026-11-17 Composer report contains six Laravel advisories (including critical/high) and one Flysystem advisory. Two packages are abandoned. These are not waived by successful application tests.

Rollback: retain prior code, database snapshot, private storage and APP_KEY. Prefer a tested restore into a separate database and configuration switch; never casually run destructive rollback migrations against financial data. No production rollback/restore/deployment was performed here.

## Supported-stack migration path
1. In a separate branch and environment, select PHP/Laravel versions still supported on the migration date using their official support policies. Inventory the locked direct/transitive packages and replace abandoned dependencies.
2. Upgrade Laravel one major at a time using each official upgrade guide; update PHP constraints, Spatie Permission, PDF/spreadsheet packages and PHPUnit to compatible supported releases. Resolve all applicable audit advisories rather than retaining the legacy Composer exception.
3. Preserve the schema, decimal arithmetic, correction lineage and posted-history invariants. Run the full suite and real MySQL worker tests at each step; compare fixture ledger/report totals and exported outputs before/after. Review session, authorization and mail/filesystem changes explicitly.
4. Rehearse migration/backup restore with a sanitized database copy, retest XAMPP or replacement web-server public-only routing and offline assets, then obtain Treasury and staff UAT. Keep the prior release and tested database rollback plan until reconciliation is signed off.
