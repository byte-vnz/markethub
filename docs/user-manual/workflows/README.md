# System workflows

These twelve diagrams describe the implemented MarketHub processes. Each row identifies the responsible person or application, the action and any failed-validation outcome. Refer to the manual's role-permission matrix to see who may perform each action.

1. `01-overall` — overall system workflow
2. `02-configuration` — initial system configuration
3. `03-tenant` — tenant registration and profile management
4. `04-stall-assignment` — stall registration and assignment
5. `05-contract` — contract generation and controlled issuance
6. `06-payment` — rental billing and payment recording
7. `07-renewal-termination` — renewal, termination and separate occupancy closure
8. `08-user-administration` — user accounts, roles and permissions
9. `09-reports` — report generation, printing and export
10. `10-lifecycle` — complete operational lifecycle across roles

11. `11-section-management` — Market Sections and stall selection
12. `12-rental-overrides` — assignment overrides and prospective return

The PNG diagrams are generated from the editable `flows.json` source. Matching Mermaid `.mmd` files are also retained. Rebuild instructions are in the documentation build guide. These are process drawings, not application screenshots or government policy approvals.

Contract closure and occupancy ending are separate actions. Monthly and fixed-day billing drafts require explicit posting. Corrections preserve the original posted record. Fixed-day billing uses complete intervals and excludes record-only CUSA from charges.

