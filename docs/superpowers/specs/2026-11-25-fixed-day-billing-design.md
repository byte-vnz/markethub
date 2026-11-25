# Adjustable fixed-day billing — prepared change

User instruction, 25 November 2026: create separate weekly bills, default seven days with an adjustable day count; CUSA is for records only; prepare and test changes without touching the live database. The earlier market-policy requirement is to be removed for this new ordinary assignment path. Category-rate approval, identity, occupancy and posted-history protections remain.

## Chosen approach

Add fixed-day billing alongside the existing monthly path. A default-disabled `FIXED_DAY_BILLING_ENABLED` switch keeps the current installation unchanged until an operator rehearses and applies the migration, then enables the feature. A mere seven-day multiplier on the monthly bill was rejected: it would allow only one weekly charge each month. Replacing historical monthly calculations was rejected because their saved basis must remain authoritative.

New assignments when enabled use `billing_interval_days` (integer 1–366, default 7), with intervals anchored to the assignment start. CUSA is an optional nonnegative daily record value; retain its period equivalent but exclude it from charges. Snapshot the interval, daily rent and CUSA treatment. Adjustment is available on creation or a subsequent renewal, never by rewriting an existing assessment. New fixed-day rent = rounded daily category rate × area × interval days. No CUSA bill item, automatic bill posting, or inferred advance/deposit charge.

Existing monthly assignments, assessments, bills, payments, overrides and issued documents keep their behavior. Existing master-policy approvals are still required for concurrent occupancy and monthly-only overrides. Fixed-day assignments cannot use monthly-only overrides or the unchanged monthly contract template; those actions explain the limitation instead of misrepresenting terms. First ordinary fixed-day assignment needs no market policy, but still needs a valid approved category rate.

## Storage and interfaces

Migration only; never execute against the live database. Widen `rental_bills.period` and `rental_assessments.period` to 10 characters: monthly values stay `YYYY-MM`, fixed-day values are the interval start `YYYY-MM-DD`. Keep existing uniqueness keys. Add nullable `coverage_start` and `coverage_end` to bills. Make assessment `rental_policy_id` nullable, retaining its FK. Preserve all existing rows, IDs, money, links and encrypted values, including on SQLite rebuilds used for testing; rollback refuses once fixed-day data exists.

Use existing amount columns for compatibility: for fixed-day snapshots `monthly_rental`, `monthly_cusa` and `total_monthly_rental_cusa` are respectively period rent, informational CUSA and period payable. Every affected display must label them by billing cycle, not imply a monthly charge. `basis.billing_cycle = fixed_days`, `basis.fixed_billing_days`, and `basis.cusa_chargeable = false` distinguish these from historical monthly snapshots. `RentalAssessment::isFixedDays()` exposes that distinction.

`RentalCalculationService::resolve(..., array $terms = [])` handles new fixed-day previews when enabled. `assess(..., array $terms = [])` freezes initial terms, accepts fixed-day date periods for fixed-day billing only, and preserves the historical monthly path. `BillingService::generate` accepts either a month for a monthly agreement or an aligned date for a fixed-day agreement. Bills for one assignment must not overlap; repeated generation returns the original bill without changing its due date or amount. Reject future starts, misaligned starts and incomplete final intervals. Require backdate permission for past interval starts. Explicit posting checks interval coverage against the assignment term.

## User flow

Assignment and unified-registration selections show billing interval and optional daily CUSA record fields only when enabled. Preview and save use the same terms. Billing generation offers Monthly or Fixed-day period selection and applies the selected type consistently to all selected agreements; mixed batches fail atomically. Lists, exports and details show explicit fixed-day date ranges and record-only CUSA. Existing monthly routes remain usable while the feature is disabled.

## Verification and rollout boundary

Use only disposable SQLite databases and fictional browser fixtures. Cover multiple weeks in one month, month/year/leap boundaries, non-seven intervals, duplicates, overlaps, term changes, permissions, exact money, record-only CUSA, atomic registration, historical monthly behavior and populated migration preservation. Update manual text, screenshots, workflow diagrams, coverage and exports. Rehearse native MySQL migration/locking, target PHP 7.4 and official contract rendering separately; do not claim these verified from SQLite. Operator deployment requires protected backups and reconciliation, followed by the prepared migration and explicit configuration. No live database connection is authorized by this document.
