# Quick Reference Guide

Market Stall Management and Monitoring System  
City Government of San Pedro, Laguna  
Version 1.5 | Updated 26 November 2026 | Prepared for LGU review

## Start and finish safely

1. Open the authorized application address, normally http://localhost/market-stall/ on the host computer.
2. Sign in with your email address and private password. Use the email assigned to your account.
3. Check the signed-in name and role. Missing menus usually indicate a permission restriction.
4. Save or cancel unfinished work, then select **Sign out** in the sidebar footer. Confirm the login page appears.

There is no self-service profile, change-password or password-recovery screen. Ask the authorized account administrator for assistance. Never share passwords or put them in a support screenshot.

On a narrow screen, open **Menu** and press Escape to return to it after closing. If the drawer controls are unavailable, use the navigation groups and **Sign out** above the page content. For a wide table, Tab into its named area and use the Left and Right arrow keys to see more columns.

## Select the responsible officer

| Task | Seeded roles allowed |
| --- | --- |
| Maintain markets, sections and rate or rental-policy drafts | Super Administrator; Market Administrator |
| Approve or retire a rate or policy revision | Super Administrator |
| Maintain stalls, tenants and businesses | Super Administrator; Market Administrator; Market Staff |
| Assign, transfer, renew or end occupancy | Super Administrator; Market Administrator |
| Generate and post rental bills | Super Administrator; Market Administrator |
| Record and post payment references | Super Administrator; Authorized Collection Officer |
| Reverse payments, adjust bills and confirm reconciliation | Super Administrator; Treasury/Accounting Officer |
| Prepare contract draft | Super Administrator; Market Administrator; Market Staff |
| Generate/download/print contract; upload signed copy | Super Administrator; Market Administrator |
| Approve/issue/void contract; manage/approve templates | Super Administrator |
| Accounts, role assignment and system settings | Super Administrator |
| Dashboard and permitted summaries | All six roles, including LGU Management Viewer |

Role grants do not override record-state checks, approval requirements or LGU authority. Read the complete permission matrix in Chapter 3 of the manual.

## Configure before daily operations

1. In **Management → Markets**, register the active market. Use **Settings → Market Sections** and **Stall Categories** for physical inventory.
2. In **Management → Stall Management**, register a unique stall code within its market, section, classification, verified area and **Inner** or **Outer** location. Existing unclassified stalls need verified classification before new assessment.
3. In **Settings → Business Categories**, review the rate history. The 21 seeded categories start with undated draft rates. An authorized approver reviews the current minimum, maximum, effective dates and official reference before approval. Reopen and review again if an edit made the approval screen stale.
4. In **Settings → Rental Policies**, verify effective approvals for monthly calculations and additional occupancy. The monthly policy's Calendar or Fixed days option determines the monthly day multiplier. For optional separate fixed-day periods, MIS follows the fixed-day rollout runbook.
5. In **Settings → Users and Roles**, assign authorized staff roles. In **Contract Templates**, arrange approval of the authoritative DOCX working copy and configure authorized numbering and rental-policy references through MIS.

## Register a tenant and assign a stall

1. Search **Management → Tenant Management** by name, business, stall or account number before creating a record.
2. In **Register tenant**, complete **Personal**, **Identification**, **Businesses**, **Stall selections**, **Rental preview**, **Helpers** and **Review**. Preserve leading zeroes in account numbers and use verified identity/ID details.
3. Add the business and active category under the tenant. Enter verified permitted goods or services, permit details and registration details if available. The tenant reference is generated if left blank.
4. Select each eligible stall and linked business. Enter the assignment dates. Leave the agreement reference blank to use a generated internal reference. Additional occupancy still needs a reason and policy approval for the total count in every involved market. The authorization reference is optional.
5. Review the preview. Inner uses the approved category minimum and Outer the maximum. The server multiplies the daily rate by verified area. If fixed-day entry is available, choose 1 to 366 days and check the period base rent and recorded CUSA. Seven days is the installation default unless MIS configures a different value. An ordinary fixed-day assignment needs an approved category rate. Concurrent occupancy still requires the usual policy approval.
6. Add up to two helpers per assignment. **Save draft** keeps pending selections without reserving a stall. An authorized officer selects **Confirm registration** after the eligibility checks. For an existing tenant, **Stall Assignments → Assign a tenant** also uses the approved assessment basis.
7. Review the saved assignment, **Rental Information** and authorized helper records. Check occupied status on the map.

## Manage sections and rental overrides

- Open **Settings → Market Sections** to maintain each market's sections, color and order. The stall form searches active sections for its selected market.
- For a monthly assignment, open **Tenant Management → tenant → Rental Information** and select a reference month. Automatic uses the initial assessment. Choose **Manual override request** to propose a per-square-meter rate or a daily total for full future months.
- Provide the reason and any required supporting reference. Use **Override history** for decisions. A different authorized officer must approve using policy that covers the entire period. Out-of-range rent and zero rent need their respective explicit policy grants.
- Review applicable rent in the profile or map. Use **Rental Override Report** for the proposal, policy, status and applicable amount by date. **Return to automatic** applies from an eligible future month.

## Generate and retain a lease contract

1. In **Management → Lease Contracts**, create a draft using an active assessed assignment, the approved v04 template and valid lease dates. An authorized template manager must register and visually review the private v04 working copy first. A legacy assignment requires an authorized renewal before a new assessed contract.
2. Review the frozen business and rental basis of the monthly assignment. A lease draft needs 1–180 characters of verified goods or services in the assessment. If these are missing, complete the business details and use an eligible renewal. Fixed-day assignments need a separately approved compatible contract workflow.
3. Verify current tenant identity, ID and authorized assignment helpers before **Generate DOCX**. That first generation captures them while preserving the assessed trade and rental details.
4. Compare the preserved original against the authoritative source in an approved document application. A PDF is available if configured local conversion succeeds.
5. An authorized reviewer records actual visual review. Issuance also requires approved numbering and rental-policy references and advance/deposit authorization. Upload the executed signed copy before separate activation.
6. Use the authorized void/reissue workflow for a correction. Reprint retrieves the preserved original.

## Bill, record, post and monitor

1. In **Transactions → Rental Billing**, choose monthly or fixed-day billing where available. Enter the month or aligned interval start and approved due date, and select assignments of that cycle. A past fixed-day start requires the existing backdate permission in addition to billing generation.
2. For fixed-day bills, start counting at the assignment start and advance by the saved interval. Bill only complete intervals. Review the coverage and base rent payable. CUSA appears separately for reference. Monthly assessed bills retain their existing base rent and CUSA lines. Post each reviewed bill with the appropriate permission.
3. **Transactions → Rental Payments:** choose the tenant and load outstanding posted bills. Enter payment date, exact amount, method and approved Treasury receipt/reference.
4. Enter allocations whose total exactly equals the payment. The application rejects overpayment, unrelated-tenant allocation and duplicate references.
5. Save the draft, review amount/date/reference/allocations, then **Post reviewed payment** and confirm if authorized.
6. Inspect bill/payment history and **Outstanding Balances**. Posted payments count in collections while Treasury confirmation remains separate.
7. Correct a mistaken draft with **Void draft**; correct a posted payment through authorized reversal and linked replacement. Never overwrite posted amounts or invent an official receipt.

In the fictional November example, an Outer Vegetables stall of 10 sq. m. uses PHP 26.88 per sq. m. per day. The approved 30 calendar days yield PHP 8,064 base rent and PHP 300 CUSA. A PHP 2,000 posted allocation against the PHP 8,364 bill leaves PHP 6,364. The displayed advance and deposit calculation is separate from the posted ledger.

## Reports, closure and escalation

1. Open **Reports → All Reports**, choose a permitted report and apply only filters supported by that report.
2. Review on-screen records before CSV, Excel, PDF or **Print view**. Exports reject more than 5,000 rows; narrow filters rather than assume truncation.
3. Tenant statements need a tenant ID. Use **Business Category Report**, **Rental Assessment Report** or **Lease Contract Registry** for category revisions, assessed terms or contract milestones within your permissions.
4. For renewal, use **Transfer / renew** and select the same stall and an active linked business. Leave the agreement reference blank to generate it. Use a month boundary for an original monthly term or an aligned boundary for an original fixed-day term. A switch from fixed-day to monthly needs both. Review the new assessment.
5. For contract closure, enter the required reason and check that the dialog names **Terminate contract** or **Void contract**, as intended. Contract closure and occupancy ending are separate. Refer a blocked midmonth ending to the authorized office.
6. Preserve audit/history. Secure prints/downloads and sign out.

| Symptom | First action |
| --- | --- |
| Login rejected | Verify email, account active status and password; wait for throttle period if shown. |
| Access denied or missing menu | Confirm the required permission with the account administrator. |
| Cannot save | Check the error summary and follow the link to a field when shown. Correct the values before retrying. Re-enter passwords or protected ID values when required. |
| Contract unavailable | Check current tenant details, active assessed assignment, template approval/effectivity and policy references. Issuance needs actual visual review. |
| Download unavailable | Check permissions, file availability/renderer and the browser's download behavior; ask MIS about integrity errors. |
| Session expired | Sign in again, check whether the prior submission saved, and avoid duplicate posting. |
| Incorrect amount or closure within saved bill coverage | Ask the authorized financial officer about the available correction. Preserve posted history. |

Help is available through **Help & Support → User Manual / Quick Reference / System Workflows / FAQs**. Give support the time, page, action and safe record reference. Exclude passwords, tokens and unnecessary personal data. This guide does not certify regulatory compliance or replace approved LGU policies.
