# Unified tenant and business management — implementation handoff

Prepared 19 November 2026. User-approved design is implemented directly in this workspace. Application source and isolated verification are complete; **live MySQL deployment/backfill is pending operator execution**. No Git repository exists, so there is no merge/PR. The source checkpoint is `.qa/unified-checkpoint/source-before.zip` with SHA256 manifest; it excludes credentials, private documents and database records.

## Existing module assessment and architecture

The existing Laravel8.83.29 application already separated tenants, businesses, stalls, assignments, agreements, posted billing/payments, roles and private original-template contracts. Reused those relationships, Spatie authorization, Form Requests, transaction/locking patterns, BCMath calculator, ledger, audit, report exporters and local Blade/Tailwind assets. No package upgrade/addition. PHP7.4 syntax is preserved; actual runtime used is8.2.12.

Tenant→business remains one-to-many; business→category provides the rate schedule. Each assignment links its tenant/business/stall; immutable assessment records approved category/policy/rate/location/area and components. Agreements, bills and contracts link the assessment; no copied balance or second payment subsystem. Helpers remain assignment-specific (maximum two).

## Delivered behavior

- Seven-section tenant registration with repeatable businesses/stalls, exact protected preview, encrypted resumable drafts, stale-version protection, transactional confirmation and durable submission idempotency. Drafts do not reserve stalls or create helpers/leases. Nine profile tabs consolidate business CRUD, assignments, rental, helpers, payments, contracts and activity.
- All21 exact source categories, including separate IT Equipment34.84/40.00 and Fruits30.97/30.97. Idempotent seeder creates undated unapproved drafts; missing policy/rate authority blocks new assessment. Settings support version history, date/overlap validation and fingerprint-bound approval. Business categories remain distinct from physical stall categories.
- Stall Management owns Inner/Outer and measured area. Inner selects minimum; Outer maximum; daily rent=selected category rate×area. Approved market policy supplies CUSA/day rule and concurrency/advance authorization. No manual category rate input for new assessments.
- Frozen initial assessments feed calendar/fixed-day billing and original DOCX contracts. Rent/CUSA bill items remain separate. No retroactive repricing, proration, automatic posting or added deposit ledger. Legacy monthly agreements continue their frozen amount; new assessed contracts require authorized renewal.
- Current verified tenant identity/helpers freeze at first generation; business/trade/financial basis remains original assessment. Reprints retrieve original files. Existing template review/approve/issue/sign/activate gates remain independent of occupancy.
- Map filters/details, payment ledger links,16existing plus3new reports, and permission-filtered navigation are integrated. Wide print/PDF reports retain every field in readable record tables. Business standalone menu removed; legacy GET redirects and POST/PUT compatibility use shared validation/service. Legacy rates remain labeled and reachable. Users/Roles are under Settings.

Guides: UNIFIED_TENANT_MANAGEMENT.md, BUSINESS_CATEGORY_MANAGEMENT.md, RENTAL_RATE_MASTERLIST.md, RENTAL_COMPUTATION_RULES.md, STALL_LOCATION_CLASSIFICATION.md, TENANT_BUSINESS_MIGRATION.md and CONTRACT_TEMPLATE_MAPPING.md. Previous standalone-business/manual-new-rate requirements are superseded; historical records remain authoritative.

## Schema changes

| Migration | Additions/preservation |
| --- | --- |
| 2026_11_18_000003_unify_business_categories | Categories, versioned category rates, market rental policies; nullable business category/trade metadata and business name; nullable stall location; encrypted MEDIUMTEXT tenant registration draft. Existing rows remain unclassified/unmapped. |
| 2026_11_18_000004_create_rental_assessments | Immutable exact-decimal assessments, hidden encrypted snapshot and non-sensitive frozen basis; nullable assessment links on agreement/bill/contract; nullable old rate FK for new assessed agreements. Legacy values retained. |
| 2026_11_18_000005_create_tenant_registration_submissions | Actor/submission key and request hash protect confirmations across retries. Down migration refuses when keys exist. |

SQLite populated upgrade/rollback and prior-orphan preservation are tested. Native MySQL ALTER path is implemented but has not been executed here. No automatic data matching/classification/backfill/import or destructive reset.

## Deployment instructions (operator only)

Before deployment, stop writes through the approved maintenance process and separately back up database, private source/generated files and APP_KEY. Keep protected backups outside downloadable paths. Capture read-only reconciliation and exact posted/effective totals. Confirm PHP extensions/installed packages and correct MySQL target, then run from the project root:

```powershell
php artisan migrate
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=BusinessCategorySeeder
npm run build
php artisan optimize:clear
php artisan view:cache
php artisan tenants:reconcile
```

`php artisan db:seed` is also registered in the proper order: roles before categories/default local account. Default local administrator remains opt-in and disabled by default; it is not needed for this feature. Never seed DemoSeeder into operational data. Existing roles retain their custom grants/revocations; only genuinely new permissions are added according to defaults. New `rates.approve` defaults only to Super Administrator; review grants through authorized administration.

After migration, use Settings→Business Categories to verify all21draft schedules against the supplied masterlist. Supply actual effective dates/references, review the displayed draft and approve with authorized authority. Configure and approve each market's rental policy (CUSA, billing-day rule, concurrent cap and applicable advance/deposit authority). Classify/measure stalls explicitly and map businesses to categories; do not infer legacy values. Reconcile ambiguous ownership manually with audit reasons. Existing generated contracts/posted financials must not be recalculated.

Compare before/after counts and all exact financial control totals; investigate every unexpected difference. Test authorized/denied roles on isolated MySQL, then perform the operational lifecycle and actual Word/original-template page review before issuance. These commands were documented, not executed on the live database.

## Existing-data and security review

Read-only MySQL reconciliation at2026-11-19 00:28Asia/Manila:0tenants/businesses/stalls/assignments/legacyrates/agreements/bills/payments/contracts; new tables absent. Orphan/duplicate/mismatch/unclassified/unmapped counts and posted/effective controls all zero. Thus no real business data was migrated. Evidence: `.qa/unified-reconciliation-current.json`.

Independent category, financial, tenant and whole-change reviews were completed. Confirmed issues corrected with regression checks: stale draft approval, long encrypted Unicode storage, existing SQLite orphan preservation, category existence probing, mutable-versus-frozen labels, unsupported report filters, registry status filtering, stale registration retry, current identity completion, goods-length validation, tenant-scoped contract selection, preservation of role revocations/interrupted seeding, populated submission rollback and clicked-submit intent lost by shared JS. No unresolved confirmed source defect remains from those reviews.

Authorization remains server-side on pages/previews/exports/mutations. No unconditional superadmin bypass. Encrypted sensitive snapshots are hidden; aggregate reconciliation exports no names/ID/credentials. Production default-account provisioning stays blocked. Secrets were not committed/published; temporary fictional preview credentials/database/scripts were removed and server stopped. Source inventory is in UNIFIED_FILE_INVENTORY.md. No existing module was dropped; only standalone business UI/manual-new-rate entry is superseded.

Protected original SHA256:
- XLSX: `07304919451078da6c73f1948940d8302eec011af0b3ccf2f07b3c68afae2e18`
- DOCX: `38eb9c85d161d98a7f32f213efbefa3b810e682b72d1a9d4352a70cc13b014fb`

## Verification, documents and rollback

Final automated result: **182tests /1,277assertions passed** on isolated SQLite. Native lint158files and PHP7.4grammar220files have zero failures; Blade/asset builds passed. Actual browser fictional registration→assignment→8364bill→2000payment→6364balance→contract generation/void/history passed with mobile/tablet and empty captured console. Full results/limits: UNIFIED_TEST_RESULTS.md.

Manual v1.1:79-page PDF, editable Word manual,6-page quick PDF,52actual screenshots and10editable workflow diagrams. Source matrix cross-check and PDF layout bounds pass. Authenticated Help serves fixed allowlists. Read user-manual/README.md, VERIFICATION.md and exports/build-manifest.json; source assets package is Documentation_Assets.zip. The fictional sample is explicitly unissued.

Rollback before any new data: restore reviewed application source from checkpoint through the approved process. After additive data exists, retain tables, assessments, registration keys, private documents and newer financial transactions; use a corrective forward migration/application repair. Do not truncate, migrate:fresh/reset, delete submission keys or restore an old database over new postings. Review source/schema compatibility before restoring older files.

Remaining operational acceptance: live migration/UAT, actual PHP7.4 execution, MySQL native ALTER/locking/concurrency, Word manual and nine-page original contract fidelity, successful contract PDF conversion, office printer and LGU/legal approval. Existing unsupported-stack/advisory restrictions remain; this is not internet-facing production certification.
