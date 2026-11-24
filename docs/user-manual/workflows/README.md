# System workflows

These ten diagrams describe the implemented MarketHub processes. Each row separates the responsible person or application from the action and, when applicable, the failed-validation outcome. Read them with the manual's role-permission matrix: responsibility names describe duties, while actual permissions decide who may act.

1. `01-overall` — overall system workflow
2. `02-configuration` — initial system configuration
3. `03-tenant` — tenant registration and profile management
4. `04-stall-assignment` — stall registration and assignment
5. `05-contract` — contract generation and controlled issuance
6. `06-payment` — monthly rental monitoring and payment recording
7. `07-renewal-termination` — renewal, termination and separate occupancy closure
8. `08-user-administration` — user accounts, roles and permissions
9. `09-reports` — report generation, printing and export
10. `10-lifecycle` — complete operational lifecycle across roles

11. `11-section-management` — Market Sections and stall selection
12. `12-rental-overrides` — assignment overrides and prospective return

The PNG diagrams are generated from the editable `flows.json` source. Matching Mermaid `.mmd` files are also retained. Rebuild instructions are in the documentation build guide. These are process drawings, not application screenshots or government policy approvals.

Contract status changes never automatically change stall occupancy. Monthly billing drafts require explicit posting, and a posted financial correction preserves its original record. No workflow establishes an unapproved weekly billing, proration, advance-credit or legal issuance policy.

