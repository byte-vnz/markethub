# Default local administrator

DefaultUserSeeder provisions one active local/testing account through the existing User model, email/password authentication and Spatie RBAC. It is disabled by default. No schema change, username login, authentication bypass or dependency change is required.

## Configure
After configuring and migrating your MySQL database using INSTALLATION.md, set these values in your private .env:

```dotenv
APP_ENV=local
SEED_DEFAULT_ADMIN=true
DEFAULT_ADMIN_NAME="System Administrator"
DEFAULT_ADMIN_EMAIL=admin@marketstall.local
DEFAULT_ADMIN_USERNAME=admin
DEFAULT_ADMIN_PASSWORD=
```

Supply your own unique strong password for the empty value before running the command. It must be **16–72 bytes**, include uppercase and lowercase letters, a digit and a non-whitespace symbol, and contain no null bytes. The upper bound prevents bcrypt password truncation. Do not copy credentials into .env.example, source control, command arguments, screenshots or logs. Use appropriate dotenv quoting for your secret.

Values are read only through config/default_admin.php, so cached Laravel configuration is supported. Clear stale configuration after editing .env:

```powershell
php artisan config:clear
php artisan db:seed
```

Or run this seeder individually:

```powershell
php artisan db:seed --class=DefaultUserSeeder
```

Both paths ensure roles/permissions are seeded before a new account is created. The default login identifier is **admin@marketstall.local**, or your configured email. The existing full-access role is **Super Administrator**; it is used for the requested administrator account, preserving the application's six-role matrix and last-administrator safeguards. It has management access through normal policies and still obeys financial invariants.

The users table and authentication support email only. DEFAULT_ADMIN_USERNAME is reserved configuration and is not written to the database or accepted as a login identifier. First-login password change is not supported by the current application, so no forced-change field/workflow was added. Existing authorized staff password management remains available.

## Preservation and security
- SEED_DEFAULT_ADMIN=false causes the account seeder to do nothing. Production/staging/other environments cannot create this account even with --force; only local/testing is allowed. No production provisioning mechanism was introduced.
- Missing/invalid identity or password configuration emits a generic warning and creates no account. Artisan returns success for a safely skipped seed; read the warning rather than assuming an account was created. Messages never contain the password.
- An existing email is checked without case sensitivity before password validation. Existing name, email, active status, password, remember token, timestamps and assigned roles remain unchanged. An existing non-Super-Administrator produces a warning and is never promoted. An inactive existing administrator is not reactivated.
- New account creation and role assignment are transactional. The existing unique email index prevents duplicate accounts. Repeated sequential runs preserve the original account; resetting its password requires the normal authorized workflow.
- The normal RoleSeeder continues to maintain the predefined permission matrix; no new role or global authorization bypass is introduced.
- After creation, set SEED_DEFAULT_ADMIN=false, remove DEFAULT_ADMIN_PASSWORD from .env and run php artisan config:clear again so the secret does not remain in a cached configuration file. Keep production APP_ENV correctly set and protect configuration/storage as documented in DEPLOYMENT.md.

## Verification scope
Automated tests exercise both Artisan seeding paths, login and management routes, repeated seeding/preservation, existing nonadministrator warning, invalid configuration, local/custom identity and production/staging denial using isolated SQLite memory. Laravel 8's in-memory RefreshDatabase uses ordinary migrate, without migrate:fresh/reset. No development MySQL account was provisioned during implementation; no password was supplied and the user's database setup remains deferred. Exact test results are in ../codex/CURRENT_STATE.md.
