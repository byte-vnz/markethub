# Market Sections and rental override verification

Verified 19 November 2026 in the local workspace using XAMPP PHP 8.2.12 and Laravel 8.83.29. PHPUnit forced SQLite in memory. Browser checks used a separate disposable SQLite file, ordinary sign-in and fictional records.

| Check | Observed result |
| --- | --- |
| Full isolated application regression | 237 tests, 1,903 assertions passed after the final review fixes. Output in `.qa/sections-final-release.txt`. |
| Help allowlist follow-up | 10 tests, 148 assertions passed after adding the v1.2 artwork and workflow allowlists. Actual three manual downloads also returned 200 with expected types and redirected guests to sign-in. |
| Migration and preservation | Isolated SQLite exercised both migrations, populated pre-existing sections, link integrity, frozen history and an unchanged map location hash. Live MySQL reconciliations were read-only. |
| Runtime and build | Native PHP lint passed for 171 files. PHP 7.4 grammar check parsed 242 files without errors. Blade caching, asset compilation and served JS syntax passed. |
| Reports | Filtered screen and history showed December manual and January automatic. CSV/XLSX/PDF/print filters were covered by the isolated tests. Actual two-page fictional report PDF/print responses were checked for the approved and rejected rows. |
| Documentation | 14 chapters, 50 permission rows across 6 roles, 20 reports, 63 screenshots and 12 workflows passed the source cross-check. Both PDFs rendered and their contact sheets and representative pages were inspected. Word package structure and linked headings passed. |

## Browser exercise

- Added, renamed and reordered fictional sections. Verified displayed counts, the occupied-section deactivation guard and searchable stall selection. For a vacant stall, deactivation removed the section from new choices and an unrelated edit preserved its existing inactive section. Layout coordinates retained their original hash.
- Saved a request for PHP 25.00 per sq. m. on a 10 sq. m. Outer stall. While pending, December displayed PHP 268.80 automatic daily and PHP 8,642.80 monthly including CUSA. A separate approval switched the covered period to PHP 250.00 daily and PHP 8,060.00 monthly including CUSA.
- Generated an unissued Word contract with PHP 15,500.00 advance, PHP 7,750.00 deposit and PHP 23,250.00 initial total. Voided the training contract and verified the preserved original and copy retained the same SHA256. Returning to automatic from 1 January kept December manual and restored January's automatic amount. Future billing was verified in the isolated automated tests using controlled dates.
- Previewed and submitted a separate direct PHP 200.00 daily amount. The approver saw the out-of-range warning and rejected it. January stayed automatic. A management viewer saw the physical section and stall status while tenant, financial and override report details stayed restricted.
- Checked override history at 390 and 820 pixels without horizontal document overflow. Reset the viewport. Help v1.2 search, images and workflow catalog were available. The final browser checks showed no captured warnings or errors. The isolated server, runtime DB and credentials were removed.

## Verification limits

The three implementation stages passed independent scoped reviews after remediation. The independent whole-change review found that the stall details and lists needed clearer initial assessment labels and that two detail fields hid a literal 0. Both findings were reproduced and addressed. The focused regressions then passed with 2 tests and 56 assertions. Independent scoped re-review found no remaining issues. Native PHP 7.4 execution, disposable MySQL upgrade and lock contention, LGU acceptance and Word pagination remain pending. The Word rendering attempt failed because LibreOffice was unavailable. No real policy approval, contract issuance or live data change was performed.
