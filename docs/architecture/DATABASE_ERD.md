# Database ER diagram

Amounts use DECIMAL(14,2), area DECIMAL(10,2), MySQL InnoDB/utf8mb4, restricted historical foreign keys. No physical deletion workflow is exposed. Financial detail and audit models reject updates/deletes; trusted database administrators remain outside application authorization.

```mermaid
erDiagram
 users ||--o{ audit_logs : acts
 users ||--o{ stall_assignments : authorizes
 users ||--o{ payments : records
 users ||--o{ model_has_roles : receives
 roles ||--o{ model_has_roles : grants
 roles ||--o{ role_has_permissions : includes
 permissions ||--o{ role_has_permissions : defines
 users ||--o{ model_has_permissions : receives
 permissions ||--o{ model_has_permissions : grants
 markets ||--o{ market_sections : contains
 markets ||--o| market_layouts : maps
 markets ||--o{ stalls : owns
 market_sections ||--o{ stalls : groups
 stall_categories ||--o{ stalls : categorizes
 market_layouts ||--o{ stall_layout_positions : positions
 stalls ||--o| stall_layout_positions : locates
 stalls ||--o{ stall_assignments : retains
 tenants ||--o{ businesses : operates
 tenants ||--o{ stall_assignments : occupies
 businesses |o--o{ stall_assignments : trades
 markets ||--o{ rental_rates : approves
 rental_rates ||--o{ rental_agreements : snapshots
 stall_assignments ||--|| rental_agreements : agrees
 stall_assignments ||--o{ rental_bills : bills
 tenants ||--o{ rental_bills : owes
 stalls ||--o{ rental_bills : references
 rental_bills ||--o{ rental_bill_items : itemizes
 rental_bills ||--o{ billing_adjustments : adjusts
 rental_bills ||--o{ payment_allocations : receives
 tenants ||--o{ payments : pays
 payments ||--o{ payment_allocations : allocates
 payments ||--o| payment_reversals : reverses
 payments |o--o| payments : replaces
 users ||--o{ billing_adjustments : authorizes
 users ||--o{ payment_reversals : authorizes
 system_settings { bigint id PK
 string key UK
 text value }
```

Unique constraints: market code; market+section code; market+stall code; tenant reference; agreement reference; assignment+period bill; payment submission key/reference; active receipt reference; replacement_of_id; one reversal per payment; payment+bill allocation; adjustment submission key; stall position; nullable active_stall_id.

Core migrations: Laravel users/password resets/failed jobs plus permission tables and market schema. Sanctum API is not enabled. Amounts are calculated with BCMath string arithmetic. Nullable active-slot fields allow retained history while enforcing one effective reservation.
