# Tenant inventory source assessment — 2026-11-17

This is a read-only structural assessment of `storage/app/private/references/INVENTORY OF STALLHOLDERS.LEGAL.xlsx`. The original workbook was not modified, imported, published, or used to seed records. This document contains aggregate findings and field names only. No tenant names, addresses, account numbers, or contact details are reproduced.

## Inspection method and scope

The XLSX ZIP package was inspected using Python standard-library ZIP/XML parsing. Shared strings, cached values, merged ranges and shared-formula definitions were read. Shared formulas were expanded by their shared indices before comparing their normalized expression patterns. No Excel macros or external links were executed. Cached-value comparisons are a reconciliation aid, not a fresh Excel recalculation or confirmation of LGU financial policy.

Both worksheets were present:

| Worksheet | Observed structure |
| --- | --- |
| `masterfile.rpt` | Used dimension A1:V999; primary headers A:U in row 11; 591 stall lines in rows 12–602; one aggregate row at 603; 282 merged ranges. Formatting extends past the data. |
| `attendance` | Used dimension A2:K33; registration-form headers in row 9; 15 merged ranges; 23 preprinted serial-number slots. No populated permanent tenant profile fields were found in the body; the other populated body cells are gender subheadings. |

Stall lines were identified using populated stall code and area, rather than requiring every category cell to be populated. K267:K268 is merged: rejecting its continuation row as an incomplete record would omit a stall line. A row count is not a distinct-tenant count, and blank cells in merged report groups are not evidence of vacant stalls.

## All 21 source columns

These are mappings for reviewed data entry and a future separately authorized migration. There is no automated inventory importer in this change. Existing database IDs, tenant references, stalls, businesses and assignment history remain authoritative.

| Column | Source header | System destination / treatment |
| --- | --- | --- |
| A | INDIVIDUAL SN | Preserve as `tenants.source_individual_sn` where applicable; retain per-source-row provenance in any future import. Report serial, never a tenant primary key. |
| B | SN ACCDG TO NAME | `tenants.source_name_sn`; report grouping reference, never a unique tenant identifier. |
| C | PREV ACCT NO. | `tenants.previous_account_number`, string verbatim including leading zeroes. |
| D | PRES ACCT NO. | `tenants.present_account_number`, string under existing account validation; do not fabricate a number from another source field. |
| E | # OF STALL PER CATEGORY | `stall_assignments.source_stall_count`, optional legacy report value. Do not copy the aggregate count to every member row as if it were a new assignment. |
| F | ACTUAL REGISTERED STALL PER CATEGORY | `stall_assignments.registered_stall_count`, optional reviewed legacy value. Current registered counts should be derived from actual assignments; reconcile discrepancies. |
| G | NAME | Reuse `tenants.name`; structured first/last/middle fields require reviewed parsing or verified entry. Preserve the legacy full name rather than guessing components. |
| H | ADDRESS | Reuse `tenants.address`; preserve existing addresses during any future reconciliation. |
| I | CODE | Match existing `stalls.code` within its market; link through `stall_assignments.stall_id`. Do not create duplicate stalls. |
| J | #S | `stall_assignments.source_sequence`, optional report sequence reference. Never a tenant or stall primary key. |
| K | StallCategory | Match existing `stall_categories` through `stalls.stall_category_id`; resolve explicitly merged continuation cells before matching. |
| L | AREA | Reuse `stalls.area`; reviewed contract snapshot `floor_area`. Existing stall storage is DECIMAL(10,2). |
| M | RENTAL/SQM | Contract calculation input/snapshot `rental_rate_sqm`, DECIMAL(14,2). Do not silently reinterpret existing monthly `rental_rates.amount` as a daily per-square-meter rate. |
| N | DAILY CUSA | Contract calculation input/snapshot `daily_cusa`, DECIMAL(14,2); blank source is missing evidence, not an approved zero-charge policy. |
| O | DAILY RENTAL | Server-derived snapshot `daily_rental = floor_area × rental_rate_sqm`. |
| P | # OF DAYS | Explicit reviewed `billing_days`; no hardcoded 30-day default in the calculation service. |
| Q | MONTHLY CUSA | Server-derived snapshot `monthly_cusa = daily_cusa × billing_days`. |
| R | MONTHLY RENTAL | Server-derived snapshot `monthly_rental = daily_rental × billing_days`. |
| S | 2-MOS ADV | Server-derived snapshot `advance_rental = monthly_rental × 2`, excluding CUSA. |
| T | SECURITY DEPOSIT | Server-derived snapshot `security_deposit = monthly_rental`, excluding CUSA. |
| U | TOTAL (2 MOS ADVANCE + DEPOSIT) | Server-derived snapshot `total_initial_payment = advance_rental + security_deposit`. |

`total_monthly_rental_cusa` is an additional derived contract value, equal to monthly base rental plus monthly CUSA. None of these snapshots creates or modifies posted bills, payment allocations, or ledger history.

## Grouping, identifiers and incomplete source values

- A has 591 populated and distinct serial values on the stall lines. This only establishes uniqueness in this workbook snapshot, not a durable identity rule.
- B has 437 populated cells and 436 distinct values, with 117 merged ranges originating in that column. One populated serial value repeats. G has 435 populated cells and 360 distinct full-name strings, with 119 merged ranges originating in that column. Repeated names, blanks and merged grouping prevent deriving a reliable unique-tenant count from either field.
- C has 590 populated account values, all stored as shared strings and all beginning with a zero. One stall line lacks C. D is blank across all 591 stall lines. Store accounts as strings; do not cast to numbers, remove zeroes or infer present accounts from previous accounts.
- E has 51 populated count cells; 48 occur where J resets to 1, while three occur at another sequence value. There are 41 merges originating in E. Its grouped-report meaning cannot be reduced to a unique tenant identifier or a universal row-level count.
- F is populated only three times, each in a merged block (F278:F279, F300:F346, F355:F357). The values cannot establish a complete, reconciled registered-stall inventory.
- J is populated on all 591 stall lines, has 90 distinct values and resets to 1 in 48 places. It is a sequence within report groups, not a globally unique identifier. Reconcile group boundaries with merged categories/counts before any future migration.
- Six stall lines have no base rental rate in M. Every daily CUSA cell in N is blank. Incomplete financial inputs must be confirmed by authorized staff before contract issuance.
- Some area/rate numeric XML values contain long binary-representation decimal tails. Presentation formatting can hide that precision. A future import must preserve raw source provenance and obtain an approved normalization/reconciliation rule; this assessment does not silently round and import those values.

Source serial fields on a tenant can retain a representative legacy reference during reviewed entry. They cannot losslessly represent multiple stall rows by themselves. Any future multi-row import needs source workbook/sheet/row provenance and reconciliation before grouping rows into an existing tenant.

## Formula review and discrepancies

The 591 stall lines have the same normalized base-rental formula patterns after shared-formula expansion:

| Column | Observed formula pattern | Coverage |
| --- | --- | --- |
| O | `L[row]*M[row]` | 591 stall lines |
| Q | `N[row]*P[row]` | 5 stall lines; remaining 586 cells blank |
| R | `O[row]*P[row]` | 591 stall lines |
| S | `R[row]*2` | 591 stall lines |
| T | `R[row]` | 591 stall lines |
| U | `T[row]+S[row]` | 591 stall lines |

Row 603 has SUM aggregates in R:U; those formulas are report totals, not alternate tenant billing rules. No alternative base-rent/advance/deposit formula pattern was found on the stall lines. Comparing cached values with those expressions using decimal arithmetic found no differences greater than half a cent; blank references were treated as zero solely to reproduce Excel arithmetic for that diagnostic. This does not approve treating absent inputs as zero in an issued contract.

Every populated P value is 30. This is an observed workbook convention, not evidence that all months or leases legally use 30 billing days. The CUSA columns are incomplete: five Q formulas evaluate from blank N, and 586 Q cells are empty. The workbook does not establish the actual CUSA amount or a waiver policy.

The service accepts explicit decimal strings/integers with at most two decimal places; it rejects floating-point inputs and scientific notation. Area and base rate must be positive, CUSA nonnegative, billing days an integer from 1 through 366. Area is limited to existing DECIMAL(10,2) capacity and monetary results to DECIMAL(14,2). The daily product is rounded half-up to two decimals before multiplying by days, so monthly base rental reconciles to the displayed daily rental. All calculations use BCMath strings. This consistent rounding boundary is an implementation choice requiring LGU financial sign-off if it differs from approved practice or raw workbook precision.

## Attendance mapping

| Attendance column | Source field | Treatment |
| --- | --- | --- |
| A | SN | Registration-form sequence only. |
| B | LAST NAME | Verified `tenants.last_name`. |
| C | FIRST NAME | Verified `tenants.first_name`. |
| D | MIDDLE NAME/INITIAL | Reviewed `middle_name` and/or `middle_initial`; do not infer an unprovided full middle name. |
| E | CONTACT NO. | Reuse `tenants.phone` as a string. |
| F | CATEGORY | Reconcile existing stall category. |
| G | STALL NUMBER | Reconcile existing stall code and assignment. |
| H:I | GENDER | Supplementary profile selection; the two columns contain gender subheadings rather than tenant records in the supplied form. |
| J | TIME | Attendance-only, excluded from mandatory tenant profile data. |
| K | SIGNATURE | Attendance-only, excluded from mandatory tenant profile data and generated signatures. |

No attendance module or attendance import was introduced.

## Decisions still requiring LGU approval

The workbook is an initial calculation reference. Confirm daily base rates, CUSA, monthly billing days, rounding, two-month advance and one-month security deposit excluding CUSA against the approved rental schedule before production issuance. Confirm grouped count discrepancies and whether any multiple registered assignments are legally allowed; repeated source rows do not authorize simultaneous occupancy.

Contract Section 3 describes first-month monthly billing and subsequent weekly rental payments. Existing billing remains a full-month agreement snapshot with draft-only scheduling and explicit posting. No weekly schedule, proration, automatic posting, CUSA allocation or financial-history conversion was implemented in this calculation change. That discrepancy needs a separately approved payment-scheduling design.

## Verification

Focused automated verification: `php vendor/phpunit/phpunit/phpunit tests/Unit/RentalCalculationTest.php` passed 31 tests / 47 assertions on the installed PHP 8.2 runtime. It exercises the requested 10 × 20, daily CUSA 10, 30-day example; 1/28/29/31/366-day schedules; exact large cents; half-up daily rounding; absent/invalid/floating inputs; and overflow. The tests do not touch a database. Native lint passed both new PHP files. Actual PHP 7.4 execution, MySQL locking, production issuance and live deployment are not established by this assessment.
