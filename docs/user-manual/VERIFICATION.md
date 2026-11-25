# Documentation verification — 19 November 2026

Version 1.2 covers Market Sections and assignment rental overrides. The source check verified 14 chapters, 50 permission rows across six roles, 20 report types, 63 actual screenshots and 12 workflows. Build provenance is in exports/build-manifest.json. The two fictional data exercises are separate.

The main PDF has 90 pages, 28 bookmarks and 28 link annotations. The quick PDF has six pages. Automated checks found no text outside the printable region. Pages were rendered and reviewed using contact sheets and representative expanded pages. The Word package passed ZIP/XML, contents link targets and page-number field checks. Visual rendering in Word or LibreOffice remains pending because the renderer is unavailable.

The Help suite passed 10 tests and 148 assertions after the new allowlists were added. The three v1.2 export routes returned the expected content types to an authorized user and redirected guests to sign-in. The v1.2 browser confirmed searches, manual figures and workflow listings. The isolated override exercise confirmed separate approval, December manual rent, January automatic return, exception rejection, map permissions and a preserved unissued Word contract. The report PDF displayed both fictional request records.

See ../SECTION_OVERRIDE_TEST_RESULTS.md for the full regression results and verification limits. The source ZIP excludes temporary databases and credentials. The preview process and temporary runtime files were removed.

## Prior v1.1 evidence

Version1.1 covers the completed unified enhancement. Generated manual PDF79A4pages, editable DOCX and quick PDF6A4pages;52actual application image files,10editable Mermaid/PNG workflows and a checksum-verified fictional generated contract. Sources/preparation/version/hash metadata are in exports/build-manifest.json. Source revision: source-sha256-bc42bb6e40be. Prepared for LGU review, not officially approved.

Final isolated application suite:182tests,1,277assertions passed on PHP8.2.12/SQLite:memory:. Existing Help guest/disabled/no-permission denial,6roles,download/preview/image allowlists, traversal blocking and topic/search checks remain included. Native PHP lint158files, PHP7.4grammar220files, Blade cache and asset build passed. No live MySQL migration/seed/backfill occurred. Detailed application evidence: ../UNIFIED_TEST_RESULTS.md.

Browser captured actual fictional unified draft/resume/confirmation, category/policy approvals,10sqmOuter26.88preview,8364monthlybill,2000postedpayment and6364balance, profile/map/reports, original generated contract and retained void history, mobile/tablet and Help/search. Captured console errors/warnings: none. Screenshot dimensions/captions are in SCREENSHOT_INDEX.md. Sources were not AI mockups.44affected existing PNGs were refreshed losslessly from new actual captures,7new JPEGs added; login capture remained applicable. Payment100illustration was unposted/voided; captions distinguish it from posted2000. No legal issuance/signature/activation, future renewal or successful contract PDF conversion was simulated.

All85PDFpages were rendered; all contact sheets and representative full-size pages visually inspected, including enlarged rental preview, role matrix and report/field tables. PDF text extraction/contents annotations/bookmarks pass; zero text outside printable bounds. Wide application reports also rendered legibly as field/value records (assessment2pages,registry1page). DOCX remains editable with well-formed ZIP/XML/relationships, headings, internal contents bookmarks/hyperlinks and page-number fields. DOCX visual rendering failed because LibreOffice soffice is absent; a secondary Windows temporary-directory cleanup error is recorded in .qa/unified-word-render.txt. PDF layout is not proof of Word equivalence.

Source checker:14chapters,41permissionrows,6roles,19reports,51unique manual figure paths,52actual imagefiles,10diagrams,zeroerrors. Result:verification/source-cross-check.json; permissions and aggregate browser evidence are retained separately. Builder self-check passed. Documentation_Assets.zip contains editable text/screenshots/workflows/sample/verification sources and no QA databases/temporary credentials. Source XLSX and originalDOCX hashes remain unchanged.

Preview server/database/scripts/temporary login/key files were removed after evidence capture. Browser download-event waits were not counted as success; download/preview authorization passed automated HTTP tests and actual export files were checked. Word pagination/nine-page contract fidelity, office printer output, live MySQL/UAT, actualPHP7.4 and LGU/legal/policy approvals remain pending. No production/regulatory certification claim is made.
# Frontend modernization v1.3

Rebuilt the complete 94-page PDF, six-page quick guide and editable DOCX with the bundled authoring runtime. The builder reported zero PDF text-bound errors and retained contents annotations and outlines. Reviewed contact sheets across both PDFs and the new fictional screen examples. Source verification found 14 chapters, 50 permission rows, 6 roles, 20 reports, 65 screenshots and 12 diagrams with zero source errors. All three authenticated Help download routes returned HTTP 200 and attachment headers with the rebuilt file sizes. The guest check redirected to login. The editable source archive passed its CRC and byte comparison and the DOCX package XML parsed successfully.

The DOCX rendering attempt could not find LibreOffice `soffice.exe`. An additional Windows temporary-folder cleanup error followed. Word visual review and official LGU review remain open. Neither the exported manual layout nor the fictional contract download checks establish legal document fidelity or issuance approval. See `../frontend/RESPONSIVE_TEST_RESULTS.md` for the fictional browser checks.

## Fixed-day billing v1.4

The final application suite passed 277 tests and 2,230 assertions on PHP 8.2.12 with forced in-memory SQLite. It covered existing monthly behavior and the fixed-day migration, terms, generation, posting, report coverage, and authorization. The PHP 7.4 compatibility checker is a best-effort source check. Native PHP 7.4, MySQL migration and locking acceptance, and operator rollout remain pending.

Chrome 152 used a guarded localhost preview with fictional data. The browser saved a seven-day assignment without a market policy and previewed nine-day rent of PHP 1,800.00 with PHP 90.00 for CUSA records. It exercised keyboard mode and assignment selection, standard fixed-day draft generation with scripts blocked, and two eligible periods in one month. The first period was explicitly posted at PHP 1,400.00 with PHP 70.00 recorded CUSA. The second stayed in draft. The browser recorded 40 page and viewport checks and four populated preview viewport checks, with zero page overflow, console errors or unexpected HTTP responses.

Four reviewed captures appear as Figures 67–70. The single-page Chrome print capture shows the posted interval's inclusive dates and separate billed, outstanding and record-only CUSA columns. The final source cross-check found 14 chapters, 50 permission rows across six roles, 20 reports, 69 application screenshots and 12 diagrams, with zero errors. See [browser evidence](verification/fixed-day-browser-evidence.json) and [source cross-check](verification/source-cross-check.json).

The builder self-check passed. The 26 November v1.4 exports contain a 96-page PDF manual and a six-page quick reference. Both have zero PDF print-bound issues. All contact sheets and representative expanded pages were reviewed. The editable DOCX passed ZIP/XML checks, and all 14 contents links resolve to their chapter bookmarks. Its footer page field is present. The source archive passed its 115-entry checksum and byte comparison.

The separate in-memory Help check passed one test and 46 assertions. Each of the three actual export routes served the intended file with the expected MIME type, attachment disposition and file length to an authorized Market Staff reader. Guests were redirected to sign in. The same check verified the v1.4 Help home and quick guide, new billing instructions, workflow image and all four allowlisted figures.

DOCX rendering was attempted with the bundled renderer. The host could not locate LibreOffice `soffice.exe` and returned a secondary temporary-folder cleanup error. Word layout and pagination review remain pending, as does official LGU review. The Laravel preview used disposable SQLite only and was removed after capture.

