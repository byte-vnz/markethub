# Unified implementation source inventory

Compared with `.qa/unified-checkpoint/manifest.json` (192 baseline files); this is a file comparison, not Git history. No credentials/private operational files are included. Documentation and generated assets are grouped below.

| Status | Source file |
| --- | --- |
| Modified | `AGENTS.md` |
| Created | `app/Console/Commands/ReconcileTenants.php` |
| Modified | `app/Http/Controllers/AssignmentController.php` |
| Modified | `app/Http/Controllers/BillingController.php` |
| Created | `app/Http/Controllers/BusinessCategoryController.php` |
| Created | `app/Http/Controllers/BusinessController.php` |
| Modified | `app/Http/Controllers/HelpController.php` |
| Modified | `app/Http/Controllers/LeaseContractController.php` |
| Modified | `app/Http/Controllers/MapController.php` |
| Modified | `app/Http/Controllers/RecordController.php` |
| Modified | `app/Http/Controllers/ReportController.php` |
| Modified | `app/Http/Controllers/TenantController.php` |
| Modified | `app/Http/Requests/AssignmentRequest.php` |
| Created | `app/Http/Requests/BusinessCategoryRequest.php` |
| Created | `app/Http/Requests/BusinessRequest.php` |
| Created | `app/Http/Requests/LeaseContractRequest.php` |
| Modified | `app/Http/Requests/RecordRequest.php` |
| Modified | `app/Http/Requests/TenantRequest.php` |
| Modified | `app/Models/Business.php` |
| Created | `app/Models/BusinessCategory.php` |
| Created | `app/Models/BusinessCategoryRentalRate.php` |
| Modified | `app/Models/LeaseContract.php` |
| Modified | `app/Models/RentalAgreement.php` |
| Created | `app/Models/RentalAssessment.php` |
| Modified | `app/Models/RentalBill.php` |
| Created | `app/Models/RentalPolicy.php` |
| Modified | `app/Models/Stall.php` |
| Modified | `app/Models/Tenant.php` |
| Modified | `app/Services/AssignmentService.php` |
| Modified | `app/Services/Audit.php` |
| Modified | `app/Services/BillingService.php` |
| Created | `app/Services/BusinessCategoryService.php` |
| Created | `app/Services/BusinessService.php` |
| Modified | `app/Services/LeaseContractService.php` |
| Modified | `app/Services/RentalCalculationService.php` |
| Modified | `app/Services/Reports.php` |
| Modified | `app/Services/TenantDataService.php` |
| Modified | `app/Support/Navigation.php` |
| Modified | `app/Support/Resources.php` |
| Modified | `config/help-screenshots.php` |
| Modified | `config/help.php` |
| Modified | `config/navigation.php` |
| Created | `database/migrations/2026_11_18_000003_unify_business_categories.php` |
| Created | `database/migrations/2026_11_18_000004_create_rental_assessments.php` |
| Created | `database/migrations/2026_11_18_000005_create_tenant_registration_submissions.php` |
| Created | `database/seeders/BusinessCategorySeeder.php` |
| Modified | `database/seeders/DatabaseSeeder.php` |
| Modified | `database/seeders/DemoSeeder.php` |
| Modified | `database/seeders/RoleSeeder.php` |
| Modified | `public/assets/app.css` |
| Modified | `public/assets/app.js` |
| Created | `public/assets/tenant-registration.js` |
| Modified | `resources/js/app.js` |
| Created | `resources/js/tenant-registration.js` |
| Created | `resources/views/assignments/concurrency.blade.php` |
| Modified | `resources/views/assignments/form.blade.php` |
| Modified | `resources/views/assignments/index.blade.php` |
| Modified | `resources/views/assignments/transfer.blade.php` |
| Modified | `resources/views/billing/show.blade.php` |
| Created | `resources/views/business-categories/edit.blade.php` |
| Created | `resources/views/business-categories/index.blade.php` |
| Created | `resources/views/business-categories/policies.blade.php` |
| Created | `resources/views/business-categories/revision.blade.php` |
| Created | `resources/views/business-categories/schedule-form.blade.php` |
| Modified | `resources/views/contracts/create.blade.php` |
| Modified | `resources/views/contracts/show.blade.php` |
| Modified | `resources/views/map/details.blade.php` |
| Modified | `resources/views/map/index.blade.php` |
| Modified | `resources/views/records/form.blade.php` |
| Modified | `resources/views/records/index.blade.php` |
| Modified | `resources/views/records/show.blade.php` |
| Modified | `resources/views/reports/print.blade.php` |
| Modified | `resources/views/reports/show.blade.php` |
| Created | `resources/views/stalls/assignment-summary.blade.php` |
| Created | `resources/views/stalls/index.blade.php` |
| Created | `resources/views/tenants/business-fields.blade.php` |
| Created | `resources/views/tenants/business-form.blade.php` |
| Created | `resources/views/tenants/business-index.blade.php` |
| Modified | `resources/views/tenants/form.blade.php` |
| Created | `resources/views/tenants/helper-fields.blade.php` |
| Modified | `resources/views/tenants/index.blade.php` |
| Created | `resources/views/tenants/registration-assignment.blade.php` |
| Created | `resources/views/tenants/registration-business.blade.php` |
| Created | `resources/views/tenants/registration-helpers.blade.php` |
| Modified | `resources/views/tenants/show.blade.php` |
| Created | `routes/business-categories.php` |
| Created | `routes/tenant-businesses.php` |
| Modified | `routes/web.php` |
| Modified | `scripts/build-assets.js` |
| Modified | `scripts/build-user-manual.py` |
| Modified | `scripts/check-user-manual.py` |
| Created | `tests/Feature/BusinessCategoryTest.php` |
| Modified | `tests/Feature/DefaultUserSeederTest.php` |
| Modified | `tests/Feature/HelpCenterTest.php` |
| Modified | `tests/Feature/LeaseContractWorkflowTest.php` |
| Created | `tests/Feature/RentalAssessmentTest.php` |
| Modified | `tests/Feature/TenantEnhancementTest.php` |
| Created | `tests/Feature/TenantReconciliationTest.php` |
| Created | `tests/Feature/UnifiedIntegrationTest.php` |
| Created | `tests/Feature/UnifiedMigrationTest.php` |
| Created | `tests/Feature/UnifiedProvisioningSafetyTest.php` |
| Created | `tests/Feature/UnifiedTenantRegistrationTest.php` |
| Modified | `tests/Feature/WorkflowTest.php` |
| Created | `tests/Fixtures/ApprovedRentalConfiguration.php` |
| Modified | `tests/MySql/ConcurrencyTest.php` |

## Documentation and generated assets

Created: docs/UNIFIED_IMPLEMENTATION_REPORT.md, UNIFIED_TEST_RESULTS.md, UNIFIED_FILE_INVENTORY.md, UNIFIED_TENANT_MANAGEMENT.md, BUSINESS_CATEGORY_MANAGEMENT.md, RENTAL_RATE_MASTERLIST.md, RENTAL_COMPUTATION_RULES.md, STALL_LOCATION_CLASSIFICATION.md and TENANT_BUSINESS_MIGRATION.md; unified specification/plan and recovery ledger; new screenshots46–52 and aggregate verification evidence.

Modified: AGENTS.md, README.md, docs/codex/{PROJECT_MEMORY,CURRENT_STATE,DECISIONS,HANDOFF}.md, CONTRACT_TEMPLATE_MAPPING.md, user-manual sources/index/coverage/change log/verification/build instructions,52-image screenshot set (44refreshed PNGs plus7new JPEGs and retained login),10workflow sources/PNG/Mermaid outputs, fictional sample and sample README, exports/manualPDF+DOCX/quickPDF/buildmanifest/sourceassetsZIP, local compiled public/assets. Unaffected references/private original contracts remain preserved.

Deprecated interface: standalone Business Management navigation/form; legacy GETs redirect into tenants and existing authorized POST/PUT endpoints delegate shared service. Manual rate selection is removed only for new assessed agreements. Legacy rate settings/billing/history remain supported. No package, model table or operational record was deleted.

QA screenshots, disposable test logs, rendered pages and source checkpoint are retained under ignored .qa/ and manual qa/; temporary preview database/credentials/scripts were removed. This inventory excludes vendor/node_modules, cache artifacts and operational storage.
