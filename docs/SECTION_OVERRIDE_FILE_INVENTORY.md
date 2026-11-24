# Section and rental override file inventory

Application files below were compared with the protected pre-feature snapshot. The workspace has no Git history. The exact source diff and the final ZIP are in the feature ledger and QA checkpoint directory. The ZIP contains application source and assets only.

## Added or modified application source

- `app/Console/Commands/ReconcileSections.php`
- `app/Http/Controllers/AdminController.php`
- `app/Http/Controllers/AssignmentController.php`
- `app/Http/Controllers/MapController.php`
- `app/Http/Controllers/RecordController.php`
- `app/Http/Controllers/RentalOverrideController.php`
- `app/Http/Controllers/ReportController.php`
- `app/Http/Controllers/TenantController.php`
- `app/Http/Requests/RecordRequest.php`
- `app/Http/Requests/RentalOverrideRequest.php`
- `app/Models/AuditLog.php`
- `app/Models/MarketSection.php`
- `app/Models/RentalAssessment.php`
- `app/Models/RentalOverride.php`
- `app/Models/RentalPolicy.php`
- `app/Models/Stall.php`
- `app/Models/StallAssignment.php`
- `app/Policies/RecordPolicy.php`
- `app/Services/AssignmentService.php`
- `app/Services/Audit.php`
- `app/Services/BusinessCategoryService.php`
- `app/Services/LeaseContractService.php`
- `app/Services/RentalCalculationService.php`
- `app/Services/RentalOverrideService.php`
- `app/Services/Reports.php`
- `app/Services/SectionService.php`
- `app/Support/AssignmentRental.php`
- `app/Support/Navigation.php`
- `app/Support/Resources.php`
- `config/help-screenshots.php`
- `config/help.php`
- `config/navigation.php`
- `database/migrations/2026_11_19_000001_enhance_market_sections.php`
- `database/migrations/2026_11_19_000002_create_rental_overrides.php`
- `database/seeders/RoleSeeder.php`
- `public/assets/app.css`
- `public/assets/app.js`
- `public/assets/rental-overrides.js`
- `resources/js/app.js`
- `resources/js/rental-overrides.js`
- `resources/views/business-categories/revision.blade.php`
- `resources/views/business-categories/schedule-form.blade.php`
- `resources/views/map/details.blade.php`
- `resources/views/map/index.blade.php`
- `resources/views/records/form.blade.php`
- `resources/views/records/index.blade.php`
- `resources/views/records/show.blade.php`
- `resources/views/rental-overrides/decision.blade.php`
- `resources/views/rental-overrides/form.blade.php`
- `resources/views/rental-overrides/index.blade.php`
- `resources/views/rental-overrides/quote.blade.php`
- `resources/views/reports/print.blade.php`
- `resources/views/reports/show.blade.php`
- `resources/views/sections/form.blade.php`
- `resources/views/sections/index.blade.php`
- `resources/views/sections/show.blade.php`
- `resources/views/sections/swatch.blade.php`
- `resources/views/stalls/assignment-summary.blade.php`
- `resources/views/stalls/index.blade.php`
- `resources/views/stalls/section-picker.blade.php`
- `resources/views/tenants/form.blade.php`
- `resources/views/tenants/index.blade.php`
- `resources/views/tenants/show.blade.php`
- `routes/rental-overrides.php`
- `routes/web.php`
- `scripts/build-assets.js`
- `tests/Feature/LeaseContractWorkflowTest.php`
- `tests/Feature/RentalOverrideTest.php`
- `tests/Feature/RentalOverrideWorkflowTest.php`
- `tests/Feature/SectionManagementTest.php`
- `tests/Feature/UnifiedIntegrationTest.php`

## Documentation and verification changes

- Operating guides: SECTION_MANAGEMENT.md, SECTION_DATA_MIGRATION.md, RENTAL_OVERRIDE_MANAGEMENT.md, RENTAL_COMPUTATION_RULES.md, UNIFIED_TENANT_MANAGEMENT.md, CONTRACT_TEMPLATE_MAPPING.md.
- v1.2 user manual, quick guide, coverage, changelog, screenshot index, verification notes, 11 added screenshots, 2 added workflow drawings, permission evidence, and fictional override Word sample under user-manual/.
- User manual PDF and editable Word, quick reference PDF, editable source ZIP and manifest under user-manual/exports/.
- Project instructions and handoff: root README.md, AGENTS.md, docs/codex/{PROJECT_MEMORY,CURRENT_STATE,DECISIONS,HANDOFF}.md, and the approved design, implementation plan and progress ledger.
- Final evidence: SECTION_OVERRIDE_IMPLEMENTATION_REPORT.md and SECTION_OVERRIDE_TEST_RESULTS.md, isolated automated logs, aggregate browser evidence, read-only live reconciliation and inspection renders.
