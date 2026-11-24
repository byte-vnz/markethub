# MASTER DEVELOPMENT INSTRUCTION
## LGU MARKET STALL MANAGEMENT AND RENTAL MONITORING SYSTEM

**Project:** MarketHub — LGU Market Stall Management System (MSMS)

**Development mode:** Full application implementation, not a prototype

**Primary platform:** Windows 64-bit, XAMPP 7.4.30

**Backend:** PHP 7.4.30 + Laravel 8.83.x

**Frontend:** Tailwind CSS 3 + Laravel Blade + JavaScript

**Database:** MySQL, version verified against the installed server

**Application path:** `C:\xampp\htdocs\market-stall`

**Target URL:** `http://localhost/market-stall/`

**Timezone:** Asia/Manila

**Currency:** PHP / Philippine Peso

---

# 1. YOUR ROLE

Act as a senior Laravel architect, full-stack developer, MySQL database engineer, UI/UX specialist, security engineer, QA engineer, and technical project manager.

Your responsibility is to build a functional and maintainable LGU Market Stall Management System from the existing MarketHub Tailwind prototype.

This must become a real Laravel application with a MySQL database.

Do not deliver another static HTML demonstration.

Do not use localStorage as the application database.

Do not simulate successful transactions.

Every approved CRUD operation must persist in MySQL.

Every financial transaction must be validated, authorized, recorded, and traceable.

Work autonomously on implementation tasks while protecting existing code and data.

# 2. FIRST ACTION — INSPECT THE EXISTING PROJECT

Before modifying anything:

1. Inspect the current working directory.
2. Identify the existing HTML prototype and its assets.
3. Read existing README, AGENTS.md, and project documentation.
4. Check whether a Laravel application already exists.
5. Inspect installed PHP, Composer, MySQL, Node.js, and npm.
6. Check the Apache/XAMPP environment where accessible.
7. Check Git repository status.
8. Identify reusable Tailwind components and JavaScript.
9. Identify existing dashboard, map, tenant, payment, and report screens.
10. Document what can be preserved.

If the repository already contains an application, extend it instead of blindly replacing it.

If it only contains the HTML prototype, preserve a reference copy under `prototype/` and build the Laravel application.

Never delete existing user data or overwrite production configuration.

# 3. STRICT TECHNOLOGY REQUIREMENTS

Use the following stack.

## Backend

- PHP 7.4.30
- Laravel 8.83.x
- Laravel Eloquent ORM
- Laravel Blade
- Form Request validation
- Controllers, Models, Services, Policies
- Database migrations and seeders
- Laravel database transactions
- Laravel Scheduler for approved recurring tasks

Do not use Laravel 9 or newer in this PHP 7.4 implementation.

Do not use PHP 8-only syntax or features, including:

- Enums
- Match expressions
- Constructor property promotion
- Union types
- Nullsafe operator

Keep all application code compatible with PHP 7.4.

Select Composer packages compatible with PHP 7.4 and Laravel 8. Verify each dependency before installing it.

Use a Composer platform constraint reflecting PHP 7.4.30 and commit a reproducible composer.lock.

## Frontend

- Tailwind CSS 3.x
- Laravel Blade
- Vanilla JavaScript
- Alpine.js if beneficial
- Fetch/AJAX for dynamic operations
- Chart.js for charts
- SweetAlert2 for confirmations
- Compatible icons
- Responsive desktop and tablet layout

Prefer Tailwind utilities over Bootstrap.

Do not introduce Bootstrap unless a specific retained component genuinely requires it and its coexistence is documented.

Avoid mixing unrelated frontend frameworks.

## CSS compilation

Use locally compiled Tailwind CSS.

Choose a compatible Tailwind CLI or Laravel Mix build configuration based on the installed Node.js environment.

Do not require the Tailwind CDN for normal operation.

Ensure generated CSS/JS assets work without internet access.

Do not use Vite unless there is an explicit, tested compatibility justification for this legacy Laravel project.

## Database

Use the installed MySQL server after confirming its actual version.

Support MySQL 5.7 or 8.0 where practical.

Use:

- InnoDB tables
- utf8mb4
- Foreign keys
- Indexes
- Unique constraints
- Decimal columns for monetary amounts
- Database transactions
- Row locking for concurrency-sensitive operations

Avoid database-specific features unsupported by MySQL 5.7 if compatibility is required.

## Environment

Target:

`C:\xampp\htdocs\market-stall`

The Laravel entry point must be served from the `public` directory.

Configure an Apache Alias or VirtualHost that safely maps the intended URL to Laravel's public directory.

Do not expose `.env`, `vendor`, `storage`, source code, or other nonpublic project directories through Apache.

If the requested URL is not achievable without changing Apache settings, implement a safe equivalent and document the exact URL.

Do not use an insecure root-level PHP entry-point workaround.

# 4. APPLICATION OBJECTIVES

Build an LGU application capable of:

1. Managing public market locations.
2. Managing market sections and stall layouts.
3. Creating and maintaining stall records.
4. Managing tenant and store information.
5. Assigning tenants to stalls.
6. Maintaining historical stall assignments.
7. Managing configurable rental rates.
8. Generating recurring rental bills.
9. Maintaining tenant receivables ledgers.
10. Recording authorized payments.
11. Tracking partial and complete payments.
12. Monitoring overdue balances.
13. Reviewing rental payment history.
14. Generating management reports.
15. Maintaining audit trails.
16. Managing staff accounts and access permissions.
17. Displaying an interactive graphical market map.

The application must support multiple public markets even if the initial LGU currently manages only one.

# 5. SYSTEM USER ROLES

Implement authentication and role-based access control.

Roles:

**Super Administrator**
- Manage configuration and accounts.
- Full technical administration.
- Cannot silently change posted financial records.

**Market Administrator**
- Manage markets, sections, stalls, tenants, assignments, and authorized rental configuration.

**Market Staff**
- Maintain permitted stall and tenant records.
- View billing information.
- Cannot post official collections without specific authorization.

**Authorized Collection Officer**
- Record approved rental payment information.
- View relevant collection records.
- Initiate approved correction workflows.

**Treasury/Accounting Officer**
- Review and reconcile collection records.
- Access authorized financial reports.
- Approve or perform designated financial adjustments based on permissions.

**LGU Management Viewer**
- Read-only dashboard.
- Occupancy and financial summary reports.
- No CRUD permission.

Use Laravel authorization policies and a PHP 7.4/Laravel 8-compatible release of Spatie Laravel Permission if appropriate.

Implement server-side permissions for every protected operation.

Create a documented permission matrix.

# 6. APPLICATION LAYOUT

Preserve the overall visual direction of the MarketHub Tailwind prototype.

Build a polished LGU administrative interface.

## Sidebar navigation

- Dashboard
- Interactive Market Map
- Markets and Sections
- Stall Management
- Tenant Management
- Stall Assignments
- Rental Rates
- Rental Billing
- Rental Payments
- Payment History
- Accounts Receivable
- Reports
- Audit Logs
- Staff Management
- System Settings

## Layout requirements

- Persistent responsive sidebar
- Top navigation/header
- Search functionality
- Current signed-in account
- Role indicator
- Logout
- Notification area
- Breadcrumbs
- Reusable modal components
- Reusable table components
- Loading states
- Empty states
- Validation messages
- Success/error notifications
- Accessible forms and buttons

Ensure the map and payment workflows are usable on tablets.

Do not create decorative buttons with no implemented behavior.

# 7. DASHBOARD MODULE

Create a real database-driven dashboard.

## Summary cards

- Total stalls
- Occupied stalls
- Vacant stalls
- Reserved stalls
- Stalls under maintenance
- Occupancy percentage
- Active tenants
- Amount billed for the selected period
- Amount collected for the selected period
- Outstanding rental balance
- Overdue receivables

## Dashboard charts

- Monthly collections
- Occupancy distribution
- Billing versus collections
- Outstanding balance by aging bracket
- Collections by market or section

## Other sections

- Recent authorized payment transactions
- Recently assigned stalls
- Tenants with overdue accounts
- Recent administrative activities

Support filtering by market and date range.

Never use hardcoded numbers in production dashboard queries.

# 8. INTERACTIVE MARKET STALL MAP

This is a primary application feature.

Create a visual market layout where users can click individual stalls.

Example:

Section A:
A-001, A-002, A-003...

Section B:
B-001, B-002, B-003...

## Stall status colors

- Green: Occupied
- Gray: Vacant
- Amber: Reserved
- Red: Under maintenance
- Dark gray: Inactive

These colors represent operational/occupancy status only.

Show payment status separately using badges or labels.

## On stall click

Open a side panel or modal containing:

- Stall number
- Market name
- Section
- Stall category
- Area or dimensions
- Occupancy status
- Current tenant name
- Business/store name
- Business classification
- Monthly rental amount
- Latest payment date
- Latest payment amount
- Total outstanding balance
- Current billing status
- Recent payment history
- Assignment history

## Stall actions

Subject to role permissions:

- View stall details
- Edit stall details
- Assign tenant
- Transfer tenant
- End assignment
- Open tenant record
- View billing history
- Record payment
- View payment history

## Map editor

Allow authorized administrators to:

- Create market layouts
- Add stalls to layouts
- Position stalls
- Edit row/column positions
- Change stall dimensions
- Reorganize sections
- Save layout changes

Store all layouts and stall positions in MySQL.

Use a grid-based editor initially. Drag-and-drop may be implemented if maintainable.

Ensure map changes persist after refresh.

# 9. STALL MANAGEMENT MODULE

Implement full CRUD.

## Stall fields

- Internal ID
- Unique stall code
- Market ID
- Section ID
- Floor/building
- Stall category
- Stall classification
- Area in square meters
- Applicable rental agreement or rate
- Operational status
- Description
- Remarks
- Created by
- Updated by
- Created timestamp
- Updated timestamp

## Required operations

- Add stall
- View stall
- Update stall
- Archive/deactivate stall
- Restore eligible archived stall
- Assign tenant
- View historical occupants
- Search and filter

Do not permanently delete any stall with linked historical assignments, billing, or financial records.

Validate unique stall codes within the applicable market.

# 10. TENANT AND BUSINESS MANAGEMENT

Implement tenant CRUD and business information management.

## Tenant information

- Tenant reference number
- Full legal name
- Contact number
- Email (optional)
- Address
- Tenant status
- Remarks

## Business information

- Store/business name
- Business category
- Business description
- Business permit reference (optional)
- Registration reference (optional)
- Permit validity dates where applicable

Do not assume a tenant and business are always the same entity.

Support one tenant managing multiple stalls if authorized by LGU policy.

## Tenant profile

Display:

- Personal/business information
- Currently assigned stalls
- Previous stall assignments
- Rental agreements
- Billing history
- Payments
- Outstanding balances
- Account statement

Provide searchable, paginated tenant records.

Protect tenant personal information through permissions.

# 11. STALL ASSIGNMENTS

Use a separate assignment history model.

Fields:

- Assignment ID
- Stall ID
- Tenant ID
- Business ID where appropriate
- Agreement reference
- Start date
- End date
- Status
- Remarks
- Authorized by

Operations:

- Assign
- Renew
- Transfer
- End assignment
- View assignment history

Prevent overlapping active occupancy assignments.

Use transactions and appropriate row locking to avoid concurrent assignment conflicts.

Do not overwrite the previous tenant record when transferring a stall.

# 12. RENTAL RATE MANAGEMENT

Rental rates must be configurable.

Fields:

- Rate ID
- Market or applicable scope
- Stall classification
- Rate amount
- Effective start date
- Effective end date
- Approval reference
- Status

Support rate changes without altering historical posted bills.

Do not assume one rental rate applies to every stall.

Preserve a snapshot of the applicable rate when generating the rental bill.

# 13. RENTAL BILLING MODULE

Implement real monthly rental billing.

## Billing fields

- Bill ID/reference
- Tenant/assignment
- Stall reference
- Billing period
- Due date
- Rental amount
- Approved charges
- Adjustments
- Total billed
- Total applied payments
- Outstanding balance
- Status

## Billing statuses

- Draft
- Posted
- Unpaid
- Partially Paid
- Paid
- Overdue
- Reversed/Voided where applicable

## Billing operations

- Generate monthly bills
- Preview billing run
- Post billing run
- Prevent duplicate billing
- View bill details
- View billing history
- View tenant ledger
- Apply approved adjustments
- Generate statement of account

Implement a Laravel Scheduler command for billing generation.

Do not automatically post financial bills until applicable LGU policy rules are configured and approved.

Support controlled backdated billing and corrections where authorized.

# 14. PAYMENT COLLECTION MODULE

Implement authorized rental payment recording.

## Fields

- Payment reference
- Tenant
- Stall/assignment
- Payment date
- Amount
- Payment method
- Receipt/reference number
- Authorized collection officer
- Transaction status
- Remarks
- Created timestamp
- Posting timestamp

## Features

- Create payment
- Review payment before posting
- Allocate payment to outstanding bills
- Support partial payment
- Support full payment
- Preserve overpayment/advance credit according to approved policy
- View payment history
- Display latest payment date and amount
- Reconcile payments with bills
- Void/reverse payments through authorized workflow

## Critical financial rules

1. Use DECIMAL monetary values.
2. Use MySQL transactions.
3. Prevent double submission.
4. Validate payment allocations.
5. Preserve original posted transactions.
6. Do not physically delete posted payments.
7. Require reasons for corrections.
8. Record the responsible account.
9. Do not silently rewrite balances.
10. Recalculate balances from authoritative transaction records.

Do not issue or invent official LGU receipts.

Treat recorded receipt numbers as references to approved Treasury procedures unless actual receipt issuance integration is separately implemented.

Clearly distinguish recorded payment, pending reconciliation, and Treasury-confirmed payment where applicable.

# 15. RENTAL PAYMENT HISTORY

Provide a searchable history table.

Columns:

- Transaction number
- Date
- Stall
- Tenant
- Store
- Amount
- Payment method
- Receipt reference
- Recorded by
- Status
- Actions

Filters:

- Market
- Section
- Stall
- Tenant
- Date range
- Payment status
- Collection officer

Payment history must persist after logout, browser refresh, and server restart.

# 16. REPORTS MODULE

Build exportable reports.

Required reports:

1. Stall Master List
2. Occupied Stall Report
3. Vacant Stall Report
4. Tenant Master List
5. Stall Assignment History
6. Monthly Rental Billing
7. Rental Collection Summary
8. Daily Collections
9. Collections by Cashier
10. Collections by Market/Section
11. Outstanding Receivables
12. Overdue Rental Accounts
13. Tenant Statement of Account
14. Payment Transaction History
15. Rental Rate History
16. Audit Trail Report

Provide:

- Filter controls
- Pagination
- Excel export
- CSV export
- PDF export
- Print-friendly view

Choose exporter/library versions explicitly compatible with PHP 7.4.

Use actual transaction-derived totals.

# 17. AUDIT LOGGING

Implement an audit trail for:

- Login activity
- Account management
- Tenant changes
- Stall changes
- Stall assignment changes
- Rental rate changes
- Billing creation/posting
- Payment creation/posting
- Payment reversal
- Permission changes
- System settings

Record:

- Actor ID
- Action
- Entity type
- Entity ID
- Old values where appropriate
- New values where appropriate
- Date/time
- IP address
- Reason when required

Protect audit records from unauthorized edits.

Do not log secrets, passwords, session tokens, or unnecessary sensitive information.

# 18. DATABASE ARCHITECTURE

Create normalized migrations and Eloquent relationships.

Suggested tables:

- users
- roles
- permissions
- markets
- market_sections
- market_layouts
- stall_categories
- stalls
- stall_layout_positions
- tenants
- businesses
- stall_assignments
- rental_agreements
- rental_rates
- rental_bills
- rental_bill_items
- payments
- payment_allocations
- payment_reversals
- billing_adjustments
- audit_logs
- system_settings

Refine the schema if necessary.

Document every significant change.

Use transactions for stall assignment conflicts and payment operations.

Create database indexes for frequently filtered columns.

Provide a Mermaid ER diagram.

# 19. PROJECT MEMORY AND HANDOFF

Create and maintain the following structure:

```text
docs/
├── codex/
│   ├── PROJECT_MEMORY.md
│   ├── CURRENT_STATE.md
│   ├── DECISIONS.md
│   ├── HANDOFF.md
│   ├── TASK_BACKLOG.md
│   ├── CHANGELOG.md
│   └── KNOWN_ISSUES.md
├── architecture/
│   ├── SYSTEM_OVERVIEW.md
│   ├── DATABASE_ERD.md
│   └── AUTHORIZATION_MATRIX.md
├── implementation/
│   ├── IMPLEMENTATION_PLAN.md
│   └── MODULE_STATUS.md
└── operations/
    ├── INSTALLATION.md
    ├── XAMPP_SETUP.md
    ├── DEPLOYMENT.md
    ├── BACKUP_RECOVERY.md
    └── SECURITY_CHECKLIST.md
```

Also create:

- `AGENTS.md`
- `README.md`
- `.env.example`

## PROJECT_MEMORY.md

Store:

- Project purpose
- System scope
- Technology stack
- Architecture decisions
- Database conventions
- UI standards
- Module dependencies
- Core business rules
- Important limitations
- Long-term implementation decisions

## CURRENT_STATE.md

Store:

- Current development phase
- Latest completed tasks
- Features implemented
- Features being developed
- Known problems
- Last test results
- Current blockers
- Next exact task

## DECISIONS.md

Maintain an Architecture Decision Record.

Document:

- Decision ID
- Date
- Context
- Alternatives
- Decision
- Rationale
- Consequences
- Approval status

## HANDOFF.md

Every development session must document:

- What was implemented
- Files added/modified
- Database migrations
- Commands executed
- Tests performed
- Test results
- Known errors
- Outstanding tasks
- Risks
- Next recommended implementation step

## AGENTS.md

Create permanent instructions requiring future Codex agents to:

1. Read project memory before coding.
2. Read the current state.
3. Read prior decisions.
4. Read the latest handoff.
5. Inspect actual repository contents.
6. Preserve completed functionality.
7. Follow PHP 7.4 compatibility rules.
8. Use Tailwind CSS consistently.
9. Follow secure Laravel coding practices.
10. Execute applicable tests.
11. Update documentation.
12. Never report unverified functionality as complete.

The documentation must remain synchronized with actual code.

# 20. TESTING REQUIREMENTS

Implement tests covering:

- Login/logout
- Roles and permissions
- Stall CRUD
- Tenant CRUD
- Assignment conflicts
- Historical assignments
- Rate changes
- Billing generation
- Duplicate bill prevention
- Payment posting
- Partial payments
- Full payments
- Payment allocation
- Payment reversals
- Outstanding balance calculation
- Concurrent submissions
- Audit logs
- Dashboard totals
- Report reconciliation

Use a PHPUnit version compatible with PHP 7.4.

Run tests after each major phase.

Never claim a test has passed unless it actually ran.

# 21. XAMPP CONFIGURATION

Inspect the installed environment.

Check:

```bat
C:\xampp\php\php.exe -v
C:\xampp\php\php.exe -m
composer --version
node --version
npm --version
```

Inspect the running MySQL version and available databases.

Confirm Apache document-root configuration.

Enable required PHP extensions where needed.

Create development database:

`markethub_lgu`

Configure `.env` safely.

Do not store real passwords in source control.

Do not modify unrelated XAMPP applications or databases.

Provide commands to:

- Install Composer dependencies
- Configure the environment
- Generate the app key
- Run migrations
- Seed development data
- Compile Tailwind CSS
- Run automated tests
- Start the application through Apache

Do not use `php artisan serve` as the final XAMPP deployment solution.

# 22. DEMONSTRATION DATA

Provide development-only seeders containing:

- One fictional LGU public market
- Four market sections
- At least 48 stalls
- Occupied stalls
- Vacant stalls
- Reserved stalls
- Maintenance stalls
- Fictional tenants
- Fictional store names
- Rental rates
- Historical bills
- Payment records
- Outstanding balances

Provide development test accounts for:

- Super Administrator
- Market Administrator
- Market Staff
- Collection Officer
- Management Viewer

Do not seed predictable credentials into production.

# 23. SECURITY REQUIREMENTS

Implement:

- Laravel CSRF protection
- Server-side validation
- Password hashing
- Secure sessions
- Login rate limiting
- Authorization policies
- Query parameter binding
- XSS-safe Blade output
- Input sanitization where appropriate
- Financial audit logs
- Backup and restore procedures
- Sensitive data access controls

Recognize that PHP 7.4 and Laravel 8 are unsupported.

Do not certify this version for internet-facing production.

Document a migration path to a currently supported PHP/Laravel stack, including dependency and regression testing requirements.

# 24. IMPLEMENTATION PHASES

## Phase 0 — Discovery and project foundation

- Inspect existing source code.
- Preserve prototype.
- Verify environment.
- Create project memory.
- Create AGENTS.md.
- Produce ERD.
- Document implementation plan.
- Identify pending LGU policy decisions.

## Phase 1 — Laravel installation and authentication

- Initialize Laravel 8.
- Configure MySQL.
- Configure Tailwind CSS.
- Configure Apache.
- Implement login/logout.
- Implement RBAC.
- Build dashboard layout.

## Phase 2 — Market and stall management

- Markets CRUD.
- Sections CRUD.
- Stall CRUD.
- Interactive market map.
- Stall modal/details.
- Layout persistence.

## Phase 3 — Tenant management

- Tenant CRUD.
- Business information.
- Stall assignment.
- Stall transfer.
- Historical assignments.

## Phase 4 — Rental rates and billing

- Rental rates.
- Effective dates.
- Rental agreements.
- Monthly billing.
- Billing ledger.
- Receivables.

## Phase 5 — Payment collection

- Payment recording.
- Bill allocations.
- Payment history.
- Partial payments.
- Posting/reversals.
- Balance reconciliation.

## Phase 6 — Reports and dashboard

- Financial dashboard.
- Occupancy reports.
- Collection reports.
- Outstanding receivables.
- Exports.

## Phase 7 — Security and quality

- Audit logging.
- Permissions verification.
- Validation coverage.
- Automated tests.
- Transaction integrity checks.
- Regression testing.

## Phase 8 — Deployment preparation

- XAMPP operational documentation.
- Environment validation.
- Backup and restore.
- Deployment checklist.
- User acceptance testing.
- Production security and upgrade plan.

# 25. DEVELOPMENT EXECUTION RULES

Do not stop after creating documentation.

Do not stop after generating migrations.

Do not stop after building a frontend shell.

Build a working application incrementally, implementing and testing as much as possible in each execution.

Prioritize a working vertical slice:

Login → Stall Map → Tenant → Assignment → Billing → Payment → Updated Balance.

Implement actual end-to-end persistence before expanding optional features.

When blocked by an LGU policy decision, implement the unblocked features and document the pending decision.

For each phase:

1. Inspect current code.
2. Read project memory.
3. Select tasks.
4. Implement the feature.
5. Run migrations as appropriate.
6. Run tests.
7. Check for regressions.
8. Update documentation.
9. Commit verified work where permitted.
10. Continue to the next unblocked task within the available execution.

Do not destroy working functionality.

Do not claim successful deployment without verification.

# 26. FINAL ACCEPTANCE TEST

The system must demonstrate:

1. Staff logs in using a MySQL-backed account.
2. Staff opens the dashboard.
3. Staff opens the interactive market map.
4. Staff clicks Stall A-001.
5. Laravel retrieves the stall record from MySQL.
6. The tenant and store information appears.
7. The monthly rental amount appears.
8. The latest payment amount and date appear.
9. Staff opens full payment history.
10. Authorized staff edits tenant information.
11. The changes persist in MySQL.
12. Authorized staff assigns a tenant to another eligible stall.
13. Assignment history is preserved.
14. An authorized user generates a rental bill.
15. An authorized collection officer posts a payment.
16. Payment allocations and balances update.
17. Dashboard figures update.
18. Audit logs record the responsible user.
19. Reports reflect the posted transaction.
20. Logout and login preserve all records.
21. Unauthorized users cannot access restricted CRUD or financial operations.
22. Refreshing or restarting Apache does not erase data.
23. The compiled Tailwind interface renders correctly without external CDN dependencies.

# 27. START IMPLEMENTATION NOW

Begin by inspecting the current working directory.

Verify PHP 7.4.30, Composer, MySQL, Apache, and Node.js availability.

Preserve the existing MarketHub prototype.

Create the complete project memory and handoff structure.

Initialize or extend Laravel 8.

Configure MySQL and locally compiled Tailwind CSS.

Implement Phase 1, then proceed through the next unblocked implementation phases.

Use the existing design as the visual baseline.

Prioritize a working, persistent, secure Laravel/MySQL application rather than a static presentation.

At each execution checkpoint, report:

- Completed features
- Modified files
- Database changes
- Tests performed
- Test results
- Remaining issues
- Current development phase
- Exact next task

Keep PROJECT_MEMORY.md, CURRENT_STATE.md, HANDOFF.md, TASK_BACKLOG.md, and MODULE_STATUS.md updated.

**START CODING.**
