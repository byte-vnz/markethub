# Authorization matrix

No superadmin bypass. Services enforce explicit financial permissions. Users are currently LGU-wide; market-scoped staff access is not implemented. Management gets aggregates and occupancy without tenant PII.

| Role | Permissions |
|---|---|
| Super Administrator | dashboard.view, map.view, markets.view, markets.manage, stalls.view, stalls.manage, tenants.view, tenants.manage, assignments.view, assignments.manage, rates.view, rates.manage, billing.view, billing.generate, billing.post, billing.adjust, payments.view, payments.record, payments.post, payments.reverse, payments.reconcile, reports.view, reports.financial, reports.personal, audit.view, staff.manage, settings.manage, financial.backdate |
| Market Administrator | dashboard.view, map.view, markets.view, markets.manage, stalls.view, stalls.manage, tenants.view, tenants.manage, assignments.view, assignments.manage, rates.view, rates.manage, billing.view, billing.generate, billing.post, payments.view, reports.view, reports.financial, reports.personal |
| Market Staff | dashboard.view, map.view, markets.view, stalls.view, stalls.manage, tenants.view, tenants.manage, assignments.view, rates.view, billing.view, payments.view, reports.view, reports.personal |
| Authorized Collection Officer | dashboard.view, map.view, markets.view, stalls.view, tenants.view, assignments.view, rates.view, billing.view, payments.view, payments.record, payments.post, reports.view, reports.financial, reports.personal |
| Treasury/Accounting Officer | dashboard.view, map.view, markets.view, stalls.view, tenants.view, assignments.view, rates.view, billing.view, billing.adjust, payments.view, payments.reverse, payments.reconcile, reports.view, reports.financial, reports.personal, audit.view, financial.backdate |
| LGU Management Viewer | dashboard.view, map.view, markets.view, stalls.view, reports.view |

Posted payment reversal and billing adjustment require Treasury or Super Administrator, a reason, and audit. Collectors cannot reverse their own work through this UI. Staff management assigns only these six predefined roles; arbitrary permission editing is not exposed.
