# Tenant and lease implementation checkpoint

## Implemented scope
The existing generic tenant routes now delegate to an enhanced tenant controller and service. Verified structured names, citizenship/civil status/gender, residential/contact information, text account numbers, encrypted government ID, optional documented issuance omissions and legacy source references extend the existing tenant record. New related helper slots belong to an existing stall assignment; a maximum of two is enforced by request/service validation and unique assignment-slot indexing. Tenant lists/profile expose search, pagination, category/status/lease filters, existing businesses/assignments, agreement rent, latest authoritative posted payment, helper records and contract history. Legacy names are retained until verified structured fields are supplied. No real inventory import exists.

The Lease Contracts module creates a draft for an eligible existing assignment, calculates rental/CUSA/advance/deposit using exact decimals, reserves a unique configurable control number and prevents overlapping contracts. Generation requires verified tenant data and an approved effective template. The generated DOCX is a preserved original with a checksum and encrypted data snapshot. Ready documents require an authorized nine-page visual review, policy references and explicit issuance. Separate signed upload, activation, expiry, termination and void actions retain audit history and never change stall occupancy or posted finances. Reprint downloads the stored original rather than regenerating current tenant data.

Original template registration uses a private working copy. Source checksum pinning, targeted XML replacement and byte-preserving ZIP member copying protect legal wording and layout structure. No PHPWord or other package was added: existing native ZipArchive/DOM and Symfony Process meet the required approach with fewer document transformations. PDF conversion uses a configured local LibreOffice executable and isolated temporary directories/profile, timeout, sanitized error logging and cleanup. If unavailable or failed, DOCX remains the supported download/Word-print path.

## Source findings and mappings
Both source files were located in storage/app/private/references and remain unchanged. See TENANT_FIELD_MAPPING.md (all 21 columns), TENANT_WORKBOOK_ASSESSMENT.md (shared formulas, merged rows and count/identifier findings), and CONTRACT_TEMPLATE_MAPPING.md (all paragraph/placeholder locations).

The masterfile has 591 source stall-code lines, many grouped name continuations, sparse category count anchors, no populated present-account numbers and no daily CUSA input values. Attendance is an unfilled registration form, not an attendance database. No serial/count field is treated as a tenant key. Approved occupancy limits and unknown accounts/CUSA must not be inferred or imported automatically.

The synthetic calculation 10 sq. m. × PHP 20/day, daily CUSA PHP 10 and 30 days produces daily rental200, monthly base6000, CUSA300, monthly total6300, advance12000, deposit6000 and initial18000. Advance/deposit exclude CUSA. Daily products round half-up to two decimals; billing days are explicit. The source's weekly-after-first-month clause conflicts with existing monthly billing. This module does not implement a revised collection schedule, advance-credit ledger or security-deposit accounting.

## Database and files
Two forward migrations: 2026_11_18_000001_enhance_tenant_profiles.php extends tenants/assignments and creates tenant_helpers; 2026_11_18_000002_create_lease_contracts.php creates contract_templates, contract_sequences and lease_contracts. Foreign keys retain relationships; decimal values, sequence row locking, submission hash/key and unique control numbers protect contracts. Existing rental_agreements and audit_logs are reused; no duplicate agreement or audit module.

New/changed groups: Tenant/TenantHelper/LeaseContract/ContractTemplate models; TenantDataService, RentalCalculationService, ContractNumberService, LeaseContractService, ContractTemplateService, ContractGenerationService and ContractPdfConversionService; TenantController/Request and existing RecordController/Request delegation; LeaseContractController; tenants/* and contracts/* Blade views; config/tenants.php, lease.php and contracts.php; centralized navigation; RoleSeeder; exception sensitive-input exclusions; .env.example and private-storage Git exclusion. Tests add tenant, rental, contract workflow, document and converter coverage. Build artifacts regenerated through existing Tailwind pipeline.

## Routes and permissions
Existing /manage/tenants routes retain their names. Added contracts.index/create/store/show/generate/upload/action/download and contract-templates.index/store/approve/download. Both route families use existing authenticated active-account middleware, CSRF and explicit service/controller authorization.

Super Administrator receives all contracts.* and contract_templates.* permissions plus tenants.view_sensitive. Market Administrator may view/create/generate/download/print/upload signed copies and view sensitive tenant IDs, but cannot approve/issue/void or manage templates. Market Staff may view/create drafts only; no private downloads, generation or approvals. Other roles receive no contract permissions automatically. Full IDs and encrypted snapshot data are excluded from generic serialization, validation flashing and audit payloads. Ordinary lists expose no full ID; tenant detail requires sensitive permission.

## Deployment and policy gates
No live MySQL migration, production data import, template approval, policy approval or actual legal contract issuance was performed in this implementation. Before enabling the updated tenant/contract screens, back up the database, private storage and APP_KEY, then run:

```powershell
C:\xampp\php\php.exe artisan migrate --force
C:\xampp\php\php.exe artisan db:seed --class=RoleSeeder --force
C:\xampp\php\php.exe artisan optimize:clear
npm run build
```

Migrations are additive; never migrate:fresh/reset or casually roll back columns containing live personal/history data. Apply them before using the new pages because the new controllers require their tables/columns. Do not enable DefaultUserSeeder or DemoSeeder as part of production setup.

Set CONTRACT_CONTROL_PREFIX and CONTRACT_NUMBER_FORMAT (must retain {period}/{sequence}), plus approved CONTRACT_NUMBERING_APPROVAL and CONTRACT_RENTAL_POLICY_APPROVAL references in private .env/config. Defaults do not authorize LGU policy. Configure CONTRACT_PDF_EXECUTABLE to the approved local LibreOffice console executable and CONTRACT_PDF_TIMEOUT as documented in CONTRACT_GENERATION_SETUP.md. Retain APP_KEY for encrypted IDs/snapshots and include private documents in protected backups. No public storage link is used.

Authorized template managers register a working version from the supplied source; authorized reviewers verify its layout/content before marking it approved. Complete page-by-page comparison of original and generated synthetic DOCX in Word, including long names/addresses and two helpers. If PDF is enabled, compare every PDF page against DOCX. The software's visual-review attestation records a human review; automated XML checks are not a substitute.

Remaining approvals: numbering convention, correct lease dates (never infer 2027), civil status values outside Single/Married, ID issuance exceptions, permitted multi-stall occupancy, CUSA and daily-rate rounding/billing days, deposit/advance treatment, weekly payment scheduling and signatory changes. New source/template wording requires remapping and review, not automatic correction. Actual PHP7.4 runtime, MySQL contention and Word/PDF visual fidelity remain unverified. The mandated legacy runtime/framework retain their previously documented support/security limitations.

See CONTRACT_TEST_RESULTS.md for exact executed results. This is an implementation checkpoint awaiting migration and authorized document acceptance, not a production-readiness certification.
