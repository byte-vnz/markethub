# Stall location classification

Stall Management owns `location_type` and floor area. Tenant registration and calculation previews retrieve these values as read-only master data.

- **Inner** (`inner`): use approved business-category minimum daily rate per square metre.
- **Outer** (`outer`): use approved business-category maximum daily rate per square metre.
- **Unclassified** (NULL): retained for existing records awaiting verification; never inferred from stall code, physical category or graphical coordinates.

New stalls require Inner or Outer. Old unclassified records remain viewable and filterable. Verify the location and positive measured area before creating a new authoritative agreement/assessment. Staff with `stalls.manage` may classify/edit area; existing history requires a correction reason and records safe old/new values. Existing occupancy/history and map positioning remain unchanged by classification.

Stall and map lists support Inner, Outer and Unclassified filters, plus business category. Tenant/business details and financial rates require their existing permissions. Occupied-stall rental values are labeled with their stored assessment reference; editing current master area/location does not rewrite that basis. Use authorized renewal for changed financial terms.

No bulk classification backfill is automatically executed. Preview and reconcile records first; production data updates require explicit approval and before/after counts/financial controls. See `php artisan tenants:reconcile` and [migration instructions](TENANT_BUSINESS_MIGRATION.md).
