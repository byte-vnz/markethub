# Unified Tenant Management

The approved enhancement joins tenant and business work in Tenant Management while preserving separate normalized `tenants`, `businesses`, `stall_assignments`, agreements, bills, payments and contracts. The former standalone Businesses menu is removed; its authorized URLs remain compatible. No records are merged by name.

## Registration

Use Tenant Management → Add tenant. The structured form contains seven sections:

1. Personal information: verified structured name, civil status, gender, citizenship, residential/contact details, string account numbers and source references.
2. Identification: existing encrypted government ID number, issuance date/place or explicit supported exception. Blank ID on edit preserves its prior value.
3. Businesses: commercial name optional for an individual stallholder; assign an active business category, trade/goods and existing permit/registration details. Add multiple related businesses without repeating the tenant identity.
4. Stall assignments: choose an eligible stall and the tenant-owned business. Location and area are read from Stall Management. An unclassified/incomplete choice cannot create an authoritative assessment.
5. Rental computation: server preview resolves approved category rates and market policy. Inner uses minimum; Outer maximum. No editable tenant rate or manually selected legacy monthly rate.
6. Helpers: at most two per applicable assignment, with name, relationship/contact, status and remarks.
7. Review: save a draft or confirm authorized assignments. Confirmation uses one transaction; a failed branch rolls back all related changes. Registration does not create or activate a lease contract.

Drafts retain pending selections/helpers in hidden encrypted registration data. They do not reserve a stall or make its availability permanent. Confirmation rechecks ownership, permission, occupancy, effective policy and all calculations. Submission keys protect repeat submissions; changed content needs its own submission.

Supply verified permitted goods before creating the first assessment if a lease contract will follow. The assessment freezes trade/category details. Later trade changes require an authorized renewal before appearing in a new contract; previously generated documents are never regenerated from current records.

## Profile and list

The profile has Overview, Personal Information, Businesses, Stall Assignments, Rental Information, Authorized Helpers, Payment History, Lease Contracts and Activity History tabs. Business registration/edit/category assignment/archive is nested here. Financial tabs use existing ledger/payments; there is no second financial transaction table or copied last-payment field.

Search the paginated tenant list by tenant/business name, stall or string account number. Filter business category, location, tenant or lease status. Multiple related businesses/assignments remain summaries under one tenant row. Potential duplicate details prompt review, never automatic merging or a uniqueness rule on names.

## Authority and preservation

`tenants.view/manage` continues to govern business records. Assignment actions additionally require `assignments.manage`; financial details and payments require existing billing/payment permissions. Sensitive ID visibility remains separate. A business cannot move to another tenant through ordinary edits, and active assignments must end before archival. Historical records remain retained.

Additional concurrent assignments require an approval reference/reason plus an approved policy in every involved market permitting the proposed global count. Existing history is preserved; a registration screen is not authorization for additional occupancy.

Legacy business GET URLs redirect to nested tenant pages/search. Existing POST/PUT clients retain field aliases and call the same ownership/validation service. Free-text legacy categories remain reconciliation metadata and cannot authorize an assessment. Orphan reconciliation requires explicit tenant selection/reason and rejects existing ownership or assignment history; no automatic matching occurs.

See [calculation rules](RENTAL_COMPUTATION_RULES.md), [business settings](BUSINESS_CATEGORY_MANAGEMENT.md), [migration assessment](TENANT_BUSINESS_MIGRATION.md) and the [approved technical design](superpowers/specs/2026-11-18-unified-tenant-management-design.md). Verification and deployment status must be read from the latest project handoff, not inferred from this guide.

## Rental Information for established assignments

The Rental Information tab shows the initial assessment and the applicable rental preview for the selected assignment and month. Confirmed initial assignments still use automatic category rent. A separate [rental override workflow](RENTAL_OVERRIDE_MANAGEMENT.md) handles eligible prospective exceptions and preserves the initial assessment.

