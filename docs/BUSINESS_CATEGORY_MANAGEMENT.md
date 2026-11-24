# Business Category Management

Business records belong in Tenant Management. Their classifications and effective rate revisions belong in Settings → Business Categories. Physical stall categories remain distinct.

## Seed and configure

After an authorized forward schema deployment:

```powershell
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=BusinessCategorySeeder
```

Normal `php artisan db:seed` includes the category seeder after roles. Default administrator provisioning remains separately disabled by default.

The category seeder creates the [21 exact source categories](RENTAL_RATE_MASTERLIST.md), stable codes, descriptions and undated **draft** rate schedules. It does not invent an effectivity date, approve a schedule or change referenced/approved administrator revisions. Repeated execution preserves existing categories/schedules. An identifiable unapproved, undated, unreferenced IT seed draft with missing values may have those missing values repaired to34.84/40.00; it is not promoted to approval.

## Settings workflow

Users with `rates.view` may view category schedules/history. `rates.manage` permits category information and draft preparation. `rates.approve`, initially granted only to Super Administrator, permits approval/retirement. Market Administrator can prepare drafts without this separate approval permission. Active-account checks apply to every action.

Provide minimum/maximum positive DECIMAL rates (maximum at least minimum), exact effective dates and the authorized reference. Review and approve explicitly. Approved revisions cannot be edited as drafts. To change a rate, close the old revision with a reason, prepare a successor and approve a non-overlapping inclusive date range. A retirement cannot exclude an existing agreement's frozen commencement. Approved retired schedules remain historical authority only within their closed effective range.

Category information has a stable code, name, description, display order and active state. Deactivation prevents new assessments; it does not erase businesses/history or change issued contracts. Associated-business links require tenant-view permission.

## Rental policies

Settings → Rental Policies stores market-specific effective approved rules: explicit daily CUSA (including an approved zero), calendar or fixed billing days, fixed count when applicable, permitted global concurrent-assignment count and advance/deposit authorization. Supply an official reference and actual effective dates; no official policy is seeded automatically.

Calendar mode uses the requested month's actual days, including leap years. Fixed mode requires the authorized count; the application does not assume30. For additional occupancy across markets, every involved market's effective policy must permit the new global count, and staff must record the assignment authorization reference/reason.

The calculator displays two base months advance and one base month security deposit, excluding CUSA. Those values are not payments, advance credits or posted deposit accounting. Contract issuance additionally requires the recorded advance/deposit authorization and existing contract policy/template/visual-review gates.

Approval/retirement serialize on category or market parents and audit safe amounts, dates, state, actor and reference. Database administrator writes remain a separate trust boundary. MySQL lock contention needs separate disposable-server verification; SQLite tests do not establish it.
