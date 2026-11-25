# Fixed-day billing rollout

Fixed-day billing is prepared as an opt-in feature. When MIS enables it, new assignments and renewals use fixed-day terms. The default is seven days. An authorized assignment officer can enter a whole number from 1 to 366 when creating the term. Each assignment keeps the interval and amount basis saved at its start. Changing the installation default affects the suggested value for subsequent assignments.

## Operating rules

- An ordinary single assignment requires an effective approved business-category rate, a verified Inner or Outer classification, and verified stall area. The market rental policy is optional for this specific path. Concurrent occupancy still requires authorized references and effective approval for the total count in every involved market. The assessment retains the market policy used to authorize its assignment.
- A period begins on the assignment start or an exact multiple of its saved interval after that date. Coverage is inclusive. A seven-day assignment starting on 27 November covers 27 November through 3 December, then 4 through 10 December.
- Daily rent is the approved category rate multiplied by verified area and rounded to two decimals. Multiply the saved daily rent by the saved interval to obtain the period charge. Optional daily CUSA and its period equivalent are recorded for reference. The draft bill amount is base rent and excludes CUSA. Fixed-day assessments carry no initial advance or deposit charge.
- Generate a draft with the correct period start and due date. The due date must be on or after the start. A prior start requires the existing backdate permission. A future start or a final fragment that cannot cover the full saved interval is rejected. A generation request groups agreements by cycle and runs atomically. Repeating a successfully generated interval returns the saved bill and its original due date.
- Review coverage and the amount before explicitly posting using the existing permission. Posted history, allocations, corrections and reconciliation use the current financial controls. Scheduled monthly drafting follows the existing workflow.
- The monthly-only rental override workflow and original monthly contract template are unavailable for fixed-day assignments. Use a separately approved compatible workflow when one becomes available. The prepared feature preserves existing monthly assessments, billing, overrides, payments and issued documents.

## Rehearse before enabling

These instructions are for the authorized operator after approval to deploy. Development and verification for this change use disposable databases. Live migration and activation require a separate operator action.

1. Back up the application, database, private document storage and environment configuration. Protect and verify the backups. Record baseline counts, keys, amounts and status aggregates for assignments, agreements, assessments, bills, bill items, adjustments, payments and allocations.
2. Restore a representative copy to an isolated MySQL database with a dedicated database account. Confirm the target connection and Apache public-only routing. Leave `FIXED_DAY_BILLING_ENABLED=false`. Verify that the previous migrations have run. Use the target PHP 7.4 executable to apply `database/migrations/2026_11_25_000001_support_fixed_day_billing.php` through Laravel's forward migration command in the disposable copy.
3. Compare historical keys, period values, amounts, assessment relationships, immutable posted records and document checksums with the baseline. Verify foreign keys, unique keys, attachment integrity and the monthly workflows. Test concurrent generation and posting against disposable MySQL, including the same agreement and interval submitted concurrently, and posting while an assignment end or renewal is attempted.
4. In the disposable copy, set `FIXED_DAY_BILLING_ENABLED=true` and optionally set `FIXED_DAY_BILLING_DAYS=7` to the authorized installation default. Refresh Laravel's configuration cache using the site's established configuration procedure. Verify the default/custom interval, approved category requirement, record-only CUSA, consecutive independent bills, a cross-month interval, backdate restrictions, corrections, payment allocation and the monthly workflow. Check the authenticated Help documentation.
5. For an authorized live rollout, deploy the reviewed source with the switch off and apply the forward migration after a current protected backup and staging acceptance. Reconcile historical data and review monthly workflows before enabling fixed-day creation. Refresh configuration and check the feature using the operator's approved verification procedure.

Use the local PHP 7.4 executable in place of `php` if the shell uses another PHP version. With the isolated connection verified and previous migrations applied, the targeted commands are:

```powershell
php artisan migrate:status
php artisan migrate --path=database/migrations/2026_11_25_000001_support_fixed_day_billing.php
php artisan migrate:status
```

## Storage and recovery

Monthly period keys retain `YYYY-MM`. Fixed-day keys use the interval start as `YYYY-MM-DD`. Bills store both inclusive coverage dates. The assessment basis carries the fixed interval and record-only CUSA flag. Compatibility columns named `monthly_rental`, `monthly_cusa` and `total_monthly_rental_cusa` hold period base rent, informational CUSA and the period payable for fixed-day assessments. Use an assessment's billing cycle and bill coverage when interpreting exports or direct reconciliations. A fixed-day assessment may have a null market-policy relationship.

Turning the switch off stops creation of new fixed-day terms. Existing fixed-day assignments keep their saved basis and remain available for billing, posting and history. A monthly renewal of a fixed-day assignment must start on the first of a month that also aligns with the outgoing fixed-day interval. Retain the additive schema after fixed-day records exist. The prepared rollback refuses when fixed-day records are present. Investigate issues from saved records, use the existing authorized correction workflows for posted financial activity, and use a forward correction for application or schema changes. A pre-rollout database restore requires operator approval and reconciliation of any subsequent activity.

See the [approved fixed-day design](../superpowers/specs/2026-11-25-fixed-day-billing-design.md) and [implementation handoff](../codex/HANDOFF.md) for verification status.
