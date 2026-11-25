<?php

return [
    "version" => "1.4",
    "updated" => "2026-11-26",
    "manual" => base_path("docs/user-manual/USER_MANUAL.md"),
    "quick" => base_path("docs/user-manual/QUICK_REFERENCE_GUIDE.md"),
    "workflows" => base_path("docs/user-manual/workflows/README.md"),
    "categories" => [
        "getting-started" => ["label" => "Getting started", "chapters" => [1, 2, 3]],
        "management" => ["label" => "Stalls, tenants & leases", "chapters" => [5, 6, 7]],
        "financial" => ["label" => "Billing, payments & reports", "chapters" => [8, 9, 10]],
        "administration" => [
            "label" => "Configuration & administration",
            "chapters" => [4, 11, 14],
        ],
        "support" => ["label" => "Workflows & troubleshooting", "chapters" => [12, 13]],
    ],
    "downloads" => [
        "manual-pdf" => [
            "label" => "User Manual (PDF)",
            "path" => base_path("docs/user-manual/exports/Market_Stall_User_Manual.pdf"),
            "name" => "Market_Stall_User_Manual.pdf",
            "mime" => "application/pdf",
        ],
        "manual-docx" => [
            "label" => "User Manual (Word)",
            "path" => base_path("docs/user-manual/exports/Market_Stall_User_Manual.docx"),
            "name" => "Market_Stall_User_Manual.docx",
            "mime" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        ],
        "quick-reference-pdf" => [
            "label" => "Quick Reference Guide (PDF)",
            "path" => base_path("docs/user-manual/exports/Quick_Reference_Guide.pdf"),
            "name" => "Quick_Reference_Guide.pdf",
            "mime" => "application/pdf",
        ],
    ],
    "diagrams" => [
        "01-overall" => [
            "path" => base_path("docs/user-manual/workflows/01-overall.png"),
            "label" => "Overall system workflow",
            "alt" =>
                "Configuration, tenant registration, assignment, contract, payment, reporting and closure workflow.",
        ],
        "02-configuration" => [
            "path" => base_path("docs/user-manual/workflows/02-configuration.png"),
            "label" => "Initial configuration",
            "alt" =>
                "Configure markets, sections, categories, stalls, approved rates, accounts and templates before operations.",
        ],
        "03-tenant" => [
            "path" => base_path("docs/user-manual/workflows/03-tenant.png"),
            "label" => "Tenant registration",
            "alt" =>
                "Find the stallholder, register owned businesses and pending stalls, preview category rental, save draft or confirm the related records together.",
        ],
        "04-stall-assignment" => [
            "path" => base_path("docs/user-manual/workflows/04-stall-assignment.png"),
            "label" => "Stall assignment",
            "alt" =>
                "Check business ownership, stall location and area, approved category rates and any required policy, then freeze the agreement.",
        ],
        "05-contract" => [
            "path" => base_path("docs/user-manual/workflows/05-contract.png"),
            "label" => "Lease and contract processing",
            "alt" =>
                "Draft contract, generate approved template, authorized visual review, issuance, signed copy and activation.",
        ],
        "06-payment" => [
            "path" => base_path("docs/user-manual/workflows/06-payment.png"),
            "label" => "Rental billing and payment recording",
            "alt" =>
                "Generate and post monthly or fixed-day bills, then record, allocate, post and reconcile payments.",
        ],
        "07-renewal-termination" => [
            "path" => base_path("docs/user-manual/workflows/07-renewal-termination.png"),
            "label" => "Renewal and termination",
            "alt" =>
                "Review lease expiry, choose authorized renewal or closure, and handle contract and assignment separately.",
        ],
        "08-user-administration" => [
            "path" => base_path("docs/user-manual/workflows/08-user-administration.png"),
            "label" => "User administration",
            "alt" =>
                "Authorized administrator verifies account, sets existing role and active status, saves and reviews audit history.",
        ],
        "09-reports" => [
            "path" => base_path("docs/user-manual/workflows/09-reports.png"),
            "label" => "Reports and printing",
            "alt" =>
                "Choose permitted report, apply filters, review records, then print or export within limits.",
        ],
        "10-lifecycle" => [
            "path" => base_path("docs/user-manual/workflows/10-lifecycle.png"),
            "label" => "Complete operational lifecycle",
            "alt" =>
                "Full operation from configuration through tenant, assignment, lease, billing, collection, reporting and renewal or closure.",
        ],
        "11-section-management" => [
            "path" => base_path("docs/user-manual/workflows/11-section-management.png"),
            "label" => "Market Sections and stall selection",
            "alt" => "Section changes preserve existing stall IDs and grid positions. Occupied sections cannot be deactivated.",
        ],
        "12-rental-overrides" => [
            "path" => base_path("docs/user-manual/workflows/12-rental-overrides.png"),
            "label" => "Assignment rental override and prospective return",
            "alt" => "Approved terms apply to the owning assignment only. Existing bills and contracts retain their saved amounts.",
        ],
    ],
];
