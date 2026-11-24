# Tenant and stallholder source field mapping

The unchanged source is `storage/app/private/references/INVENTORY OF STALLHOLDERS.LEGAL.xlsx`. This document contains schema and aggregate observations only. No real tenant records were imported, seeded, copied into tests or included here.

## Workbook assessment

The actual `masterfile.rpt` worksheet has its A–U headers in row 11, 999 stored rows and 282 merged ranges. `attendance` has 30 stored rows, 15 merged ranges and headers at row 9. These are worksheet structure counts, not verified tenant totals. Within rows with a name, stall code and category, column B contains a repeated value, column J repeats extensively and E/F are sparsely populated category counts. Column A happens to be distinct in that subset but this does not establish an approved tenant key. Column D is empty in that subset. There are 156 rows with a stall code but without a name, consistent with grouped continuations. Import preview must resolve merged/grouped ownership rather than inserting a tenant for every row.

Observed formula families are `O=L*M`, `Q=N*P`, `R=O*P`, `S=R*2`, `T=R`, and `U=T+S`, plus final SUM rows. Two M cells derive a rate using multiplication. Formula coverage is incomplete: much of the workbook is stored values. Cached results are not authoritative calculations or proof of current LGU policy. Future import must reconcile each applicable row and surface discrepancies for review. Summary and subtotal rows must be excluded from detail imports.

## All 21 master columns

| Column | Exact source header | Destination / treatment |
| --- | --- | --- |
| A | INDIVIDUAL SN | `tenants.source_individual_sn`, nullable text retained only for reconciliation; not unique identity. |
| B | SN ACCDG TO NAME | `tenants.source_name_sn`, nullable text; duplicates allowed. |
| C | PREV ACCT NO. | `tenants.previous_account_number`, text including leading zeroes and punctuation. |
| D | PRES ACCT NO. | `tenants.present_account_number`, nullable text; never derive from C without approval. |
| E | # OF STALL PER CATEGORY | `stall_assignments.source_stall_count`, nullable source count; do not multiply across grouped continuation rows. |
| F | ACTUAL REGISTERED STALL PER CATEGORY | `stall_assignments.registered_stall_count`, nullable source count; distinguish from the actual application assignment count. |
| G | NAME | Existing `tenants.name`; preserve unsplit legacy text. Verify last/first/middle against attendance or staff before structured conversion. |
| H | ADDRESS | Existing `tenants.address` permanent residential address. |
| I | CODE | Match existing `stalls.code` within its market, then use `stall_assignments.stall_id`; reject missing/ambiguous matches. |
| J | #S | `stall_assignments.source_sequence`, nullable text; report sequence/count, not tenant identity. |
| K | StallCategory | Existing `stall_categories` / `stalls.stall_category_id`; require approved matching. |
| L | AREA | Existing `stalls.area` DECIMAL; contract snapshot stores the applicable area. Do not overwrite historical source area silently. |
| M | RENTAL/SQM | `lease_contracts.rental_rate_sqm`; validate approved rate and units; separate from existing monthly rental rate records. |
| N | DAILY CUSA | `lease_contracts.daily_cusa` DECIMAL input, with explicit approved policy. |
| O | DAILY RENTAL | Recalculate `lease_contracts.daily_rental = area × rental_rate_sqm`. |
| P | # OF DAYS | Explicit `lease_contracts.billing_days`; never infer 30/31 universally. |
| Q | MONTHLY CUSA | Recalculate `lease_contracts.monthly_cusa = daily_cusa × billing_days`. |
| R | MONTHLY RENTAL | Recalculate `lease_contracts.monthly_rental = daily_rental × billing_days`. |
| S | 2-MOS ADV | Recalculate `lease_contracts.advance_rental = monthly_rental × 2`, excluding CUSA, subject to LGU approval. |
| T | SECURITY DEPOSIT | Recalculate `lease_contracts.security_deposit = monthly_rental`, excluding CUSA, subject to LGU approval. |
| U | TOTAL (2 MOS ADVANCE + DEPOSIT) | Recalculate `lease_contracts.total_initial_payment = advance_rental + security_deposit`. No payment posting is implied. |

## Supplementary attendance and contract fields

Attendance last/first/middle name or initial, contact number, gender, category and stall number may supplement verified profiles. TIME and SIGNATURE remain attendance-only fields. Attendance is not a new module.

The tenant enhancement adds nullable last/first/middle name and initial, civil status, configurable gender, citizenship (Filipino default), account strings, government ID type, encrypted ID number, issuance date/place, and an exception reason for legitimate documents that omit issuance details. Existing `name`, `reference`, `phone`, `email`, `address`, status and remarks remain compatible. New form references use `T-` plus a UUID; imported/legacy references are retained. Existing names are never automatically split. New UI registration requires last and first names. Contract issuance must validate completeness independently.

`tenant_helpers` links to both the tenant and a specific existing `stall_assignment_id`. Two unique registration slots per assignment are maintained transactionally under a tenant/assignment lock; only `authorized` helpers enter a new contract snapshot. Editing current registrations does not change an issued contract's preserved snapshot. No tenant action creates duplicate stalls or silently authorizes simultaneous active occupancy. Existing assignment validation and unique active-stall constraints remain authoritative; multiple active assignments display a review notice.

The ordinary tenant list never outputs government ID numbers. Detail views mask the number unless the viewer has `tenants.view_sensitive`. Numbers use Laravel's encrypted cast, are hidden from model serialization and are never prefilled in edit forms or audit values. Keep `APP_KEY` secure and backed up: losing it makes encrypted IDs and contract snapshots unreadable.

## Future import boundary

No import module existed and none is added. A future authorized import requires a private upload, preview, formula reconciliation, source-row provenance, duplicate/name/account review, explicit existing-stall matching, grouped-cell handling, error reporting and transactions. Account cells must be read using displayed number formats where necessary to preserve leading zeroes; importing numeric cached values alone is insufficient. Blank accounts do not authorize invented account numbers. A repeated source key or name is not automatic evidence of the same tenant.

Migration: `2026_11_18_000001_enhance_tenant_profiles.php` adds nullable profile/source fields and the helper table without changing existing names, references or financial records. No development or production migration/import was run during implementation. Run normal migrations only after backup and the deployment review.
