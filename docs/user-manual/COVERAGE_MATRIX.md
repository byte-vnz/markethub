# Documentation coverage and verification matrix

Version 1.3, 20 November 2026. Captures use isolated fictional data. The Screenshot Index identifies individual captures and the source cross-check records the permission, report and figure checks. See VERIFICATION.md for test and publication results.

| Implemented module or workflow | Manual chapter | Screenshot reference | Evidence and limits |
| --- | --- | --- | --- |
| Login, logout and session | 2, 11, 13 | 01-login, 26-mobile-menu | Isolated browser sign-in. Logout, disabled accounts and throttle are covered by source and regression checks. |
| Dashboard and occupancy totals | 2, 9, 12 | 02-dashboard | Browser rendering and ledger regression coverage. |
| Grouped sidebar and mobile navigation | 2 | 26-mobile-menu | Role-filtered drawer, keyboard focus return and navigation fallback when the drawer script is unavailable. |
| Keyboard scrolling in wide tables | 2, 10 | 22-report, 53-section-list | Named table regions maintain the tab order and allow access to columns extending past the visible area. |
| Markets, sections and physical stall categories | 4 | 34-markets, 35-sections, 36-categories | Configured training records and forms. CRUD and parent restrictions checked in source and tests. |
| Stall directory and registration | 5 | 03-directory, 05-stall-form | Directory and form. Source and tests cover location, area, history correction reason and occupancy guards. |
| Map, occupancy and assessed values | 5, 12 | 04-map, 43-training-map-details | Map and selected-stall details. The historical training image illustrates occupancy and ledger controls. Current location and frozen assessment use distinct values in source. |
| Map grid editor | 5 | 42-layout-editor | Browser editor. Layout checks cover positions, persistence and overlap. |
| Business categories and rates | 4, 7 | 46-business-categories, 47-category-history | Isolated rate approval and category history. Source covers seed drafts, stale approval protection, effective dates, retirement and archive gating. |
| Market rental policies | 4, 7 | 48-rental-policy | Isolated calendar policy approval. Source covers explicit CUSA, day rule, concurrency and advance/deposit authorization. |
| Legacy monthly rates | 4, 7, 8 | 37-rates | Legacy registry and preserved agreement path. |
| Unified registration and rental preview | 6, 12 | 49-unified-registration, 50-rental-preview | Browser previewed Vegetables Inner PHP 210.40 and Outer PHP 268.80 daily at 10 sq. m. Draft reserved no stall. Resume preserved pending choices, helper and text account values. Confirmation created one assignment. |
| Tenant profile, search and nested businesses | 6 | 06-list, 51-profile-tabs, 52-business-tab | Browser shows the saved training tenant, profile tabs and linked business. Search, ownership and access rules were checked against source. |
| Helpers and ID privacy | 6, 7, 14 | 49-unified-registration, 51-profile-tabs | Training helper retained through registration. Source and tests cover the two-helper limit, assignment ownership and ID masking/encryption. |
| Assignment, renewal or ending | 7, 12 | 27-assignment-form, 39-renew-transfer, 41-billed-end-validation | Browser confirmed assessed occupancy and older evidence shows blocked midmonth ending. Source covers fresh assessments on renewal and global concurrency authority. |
| Contract template, draft and original generation | 7, 12 | 11-draft-form, 12-draft, 13-ready, 14-templates, 29-generation-confirmation | Source requires an active assessed assignment for a new draft. First generation takes current verified tenant identity and authorized helpers with frozen commercial terms. Browser evidence includes a generated training original. |
| Contract visual review, issuance and closure | 7, 12 | 13-ready, 40-void-history | Earlier training void preserved the original and occupancy. The confirmation names the chosen void or termination action. Source and tests cover review, issue, signed upload, activation and expiration controls. Official review and issuance remain pending. |
| Billing drafts and posting | 8, 12 | 15-billing, 16-bill, 30-billing-run, 31-bill-posted | Isolated browser generated a draft and explicitly posted PHP 8,364.00 for the assessed November period, with PHP 8,064.00 base rent and PHP 300.00 CUSA. |
| Payment draft, posting and balance | 8, 9, 12 | 17-payment-form, 18-payment-draft, 19-payment-posted, 20-receivables, 32-post-confirmation, 33-balance-after-payment | Browser recorded, reviewed and posted a PHP 2,000.00 allocation. Assessed bill showed Partially paid and PHP 6,364.00 remaining. Payment was pending Treasury reconciliation. |
| Payment reversal, adjustments and Treasury reconciliation | 8 | 19-payment-posted | Browser controls plus source and test coverage for reasons, corrections and reconciliation. |
| All 20 reports and export or print | 10 | 21-reports, 22-report | Catalog and training report preview. Supported filters and permissions for all types were checked against the report controller and query definitions. |
| Staff accounts, roles and system settings | 3, 4, 11 | 23-staff, 24-settings | Forms use fictional accounts. An invalid save returns safe values to the same form. The error summary links to applicable fields. Source and tests cover seeded grants, status, last-administrator protection and safe login. |
| Audit and historical originals | 11, 12 | 25-audit, 40-void-history | Browser audit actions and preserved historical document controls. |
| Help, search, downloads, FAQ and workflows | 2, 13 | 44-help-center, 45-help-search | Browser Help and search. See VERIFICATION.md for authenticated downloads and allowlist checks. |
| Complete 19-step lifecycle | 12 | L01–L19 | Chapter instructions with actors, prerequisites, criteria and limits. Captions identify legacy demonstration screens where applicable. |
| Market Sections and stall selector | 4, 5 | 53–55, 63 | Fictional creation, rename, display order, occupancy protection and inactive selection behavior verified in browser. |
| Rental override approval and return | 7, 12 | 56–59, 61–62 | Separate requester and approver, pending automatic rent, exception review and rejection, unissued contract generation and prospective return verified in browser. |
| Rental Override Report | 10 | 60 | December manual and January automatic applicability observed. Export filters are covered by isolated tests. |

## Report coverage

Chapter 10 records the 20 report definitions with permissions and supported filters. Business Category Report covers category revisions and associations. Rental Assessment Report covers frozen assessed terms. Lease Contract Registry covers dates, status, rental fields and document milestones under contract-view access.

## Source and release checks

The manual includes Chapters 1–14 and 50 permission rows across six roles. The rate and policy approval grant initially belongs to Super Administrator. The cross-check compares manual headings, permission rows, report titles and figure references with source and captured grants.

LGU review, legal issuance approval, DOCX visual fidelity and live MySQL locking acceptance remain pending. See VERIFICATION.md for the exact application and publication checks.

## Frontend modernization (v1.3)

| Workflow | Chapters | Figures and evidence |
| --- | --- | --- |
| Narrow-screen navigation and wide tables | 2, 5, 10 | Figure 64 shows script-disabled navigation. Browser checks cover drawer focus, Escape, logout, and keyboard scrolling within Market Sections. |
| Validation recovery | 2, 6, 11 | Linked server errors and retry values are covered by disposable automated tests. Existing workflow captures remain in context. Passwords and protected identity numbers stay blank after errors. |
| Contract confirmations | 7 | Figure 66 shows the fictional Terminate prompt. The controlled browser check verified action names and cancellation. No contract issuance was performed. |
