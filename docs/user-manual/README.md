# User documentation package

Version 1.3, prepared 20 November 2026 for LGU review. This revision explains how to use keyboard navigation, recover from validation errors, scroll wide tables and verify contract actions. The package also documents unified registration, rate and policy approvals, assessed rentals, Market Sections and assignment rental overrides. All captures use fictional demonstration records. LGU review is pending.

## Read and download
- User Manual source: USER_MANUAL.md (14 chapters, 19-step lifecycle, 50 permission rows, 20 reports).
- Quick guide source: QUICK_REFERENCE_GUIDE.md.
- Exports: exports/Market_Stall_User_Manual.pdf, Market_Stall_User_Manual.docx and Quick_Reference_Guide.pdf.
- Actual screenshots: screenshots/; index and evidence: SCREENSHOT_INDEX.md and COVERAGE_MATRIX.md.
- Twelve editable workflows: workflows/*.mmd, print PNGs and canonical flows.json.
- Fictional generated original contract: samples/Fictional_Training_Lease_Contract.docx; read samples/README.md before using it.

While signed into the application, use Help & Support → User Manual, Quick Reference, System Workflows or FAQs. Help home is /help; downloads use /help/download/manual-pdf, manual-docx or quick-reference-pdf. All routes require an active account with the existing dashboard.view permission. Figures and workflow images use fixed authenticated allowlists. Nothing here requires exposing docs/ or private storage through Apache.

## Maintain and regenerate
Follow BUILD.md using the bundled/approved authoring Python. The Laravel server only serves prebuilt exports; no new runtime dependency or Microsoft Word requirement was added. Update text, screenshots, permissions/source evidence and diagrams together, then run scripts/check-user-manual.py and scripts/build-user-manual.py. Review PDF pages and attempt separate DOCX rendering. Keep generated files separate from editable Markdown.

Word visual and pagination review needs an approved Word or LibreOffice renderer. See VERIFICATION.md and build-manifest.json for exact results and limits. No live MySQL migration/data import was performed to create this documentation.
