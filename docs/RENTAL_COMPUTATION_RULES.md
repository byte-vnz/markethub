# Authoritative rental computation

All new assignment, billing and contract calculations use the existing `RentalCalculationService`, extended to resolve registered business/category, approved rate revision, verified stall/location/area and approved market policy. Client-submitted rates/areas/location/amounts are not authority. Legacy manual rate IDs cannot create new agreements or contracts.

An eligible assignment starts on the frozen automatic basis. An approved [assignment override](RENTAL_OVERRIDE_MANAGEMENT.md) may replace base daily rent for covered full months using one per-square-meter rate or one direct daily amount. CUSA and billing-day rules retain their frozen basis. Subsequent automatic periods restore the original calculation. Existing posted amounts and generated originals retain their snapshots.

| Component | Exact rule |
| --- | --- |
| Inner applied daily rate per sq. m. | Approved category minimum |
| Outer applied daily rate per sq. m. | Approved category maximum |
| Daily base rental | Applied rate × verified area; round half-up to two decimals once |
| Monthly base rental | Rounded daily base rental × approved billing days |
| Monthly CUSA | Approved daily CUSA × approved billing days |
| Monthly total | Monthly base rental + monthly CUSA |
| Advance rental | Two base monthly rentals, excluding CUSA |
| Security deposit | One base monthly rental, excluding CUSA |
| Initial advance/deposit total | Advance rental + security deposit |

Use decimal strings/BCMath, not floating-point arithmetic. Positive area/rate, nonnegative CUSA and1–366 days are validated; overflow beyond supported DECIMAL amounts is rejected. Fixed/calendar days come from the approved effective policy, never a hardcoded30. Dates and references must be configured explicitly.

Required daily examples: Vegetables Inner10sqm→210.40; Outer10sqm→268.80; Cellphone Inner5sqm→174.20; IT Equipment Outer5sqm→200.00; Fruits10sqm Inner/Outer→309.70.

## Frozen assessments and bills

An initial immutable assessment records the agreement's business/category, schedule/policy identifiers and references, stall/location/area, rate range/selection, exact components, effective term and encrypted identity/business snapshot. Later calendar bills derive the specific month's day count from the frozen day rule. They do not resolve today's category rate, floor area or CUSA. Base rent and CUSA become separate bill items with an exact total.

Changes to masters do not alter assessments, posted bills, payments or previously generated contracts. New authorized terms use the existing renewal/transfer workflow and a fresh assessment. Additional deposit/advance accounting is not implemented by displaying its formula.

Full-month eligibility, existing due/backdate permissions, idempotent assignment/period billing, draft then explicit posting, append-only approved corrections and no advance-credit rules remain. Scheduler drafts never auto-post.

## Legacy agreements and contracts

Agreements linked only to a legacy monthly `rental_rate_id` retain their stored monthly amount and continuing legacy billing. They are labeled legacy. New category contracts require an authorized same-stall renewal with an active business, category, classified stall and approved schedule/policy. Existing generated/issued private files remain original downloads, including their historical data.

Contract goods/trade/category and financial basis come from the agreement assessment. A later business edit cannot create a hybrid contract with the old category's rate. A reference billing month and day rule describe the monthly amount; calendar months can have different approved day counts. No proration, new visible legal fields or silent invalid-date repair is introduced.

Missing approvals, inactive categories/businesses, ownership mismatch, unknown location, invalid area or overlapping schedules produce validation errors. Incomplete registration can remain draft; it cannot produce authoritative assessment/final issuance. Actual contract issuance still needs approved template, policy/numbering references and authorized visual review of the original DOCX layout.

For first document generation, current verified tenant identity and assignment helpers are frozen into the new contract. The original assessment identity remains unchanged; later reprints retrieve the generated original. This permits completing identification after assignment without changing the frozen business/rental basis.
