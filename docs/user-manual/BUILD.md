# Rebuilding the user documentation

The editable sources are `USER_MANUAL.md`, `QUICK_REFERENCE_GUIDE.md`, real application PNG/JPEG captures under `screenshots/`, and `workflows/flows.json`. Do not edit generated PDF, DOCX, workflow PNG or Mermaid files by hand. Rebuild after changing the sources. Source assets and generated exports remain outside `public/`; the Help Center supplies authenticated, controlled downloads.

## Prerequisites

Use an offline authoring workstation with Python 3, `python-docx`, ReportLab, Pillow, pypdf, pdfplumber and pypdfium2. Arial on Windows or DejaVu Sans on Linux is required. These are authoring tools, not application runtime dependencies. No Python package, Word installation or document renderer is required on the deployed Laravel server to download already generated manuals.

This work used the bundled Codex Python interpreter:

```powershell
$manualPython = 'C:\Users\vnz\.cache\codex-runtimes\codex-primary-runtime\dependencies\python\python.exe'
```

On a different authoring workstation, replace that interpreter path with the approved local Python environment. No package installer is called by the builder.

## Build and check

Run from the repository root:

```powershell
& $manualPython scripts/build-user-manual.py --self-check
& $manualPython scripts/build-user-manual.py --version 1.4 --date 2026-11-26
```

The self-check uses disposable document fixtures only. It checks Markdown parsing, PDF print bounds, DOCX hyperlink/bookmark XML, and rejection of missing images. It does not connect to any application database.

The complete build creates:

- `exports/Market_Stall_User_Manual.pdf`
- `exports/Market_Stall_User_Manual.docx`
- `exports/Quick_Reference_Guide.pdf`
- `exports/build-manifest.json`, with source hashes, version, source revision, PDF page counts and automated layout results
- Twelve workflow PNGs and matching editable Mermaid files
- A PNG of every PDF page and contact sheets under `qa/` for internal inspection

The PDF and DOCX are generated from the same Markdown source; their pagination may differ. Both use A4 portrait pages, page numbers, headers/footers and a clickable contents list. PDF contents entries include page numbers and PDF outline bookmarks. The DOCX contents entries are internal links to heading bookmarks; they remain editable Word paragraphs. The DOCX footer uses a live page-number field.

The source revision defaults to a SHA256 digest of routes, role grants and navigation configuration. Supply `--source-revision <reviewed-revision>` when a precise release or commit identifier is available. Update the preparation date and version explicitly when publishing a new revision.

## Workflow edits

Edit each workflow's `title`, ordered `steps` and `note` in `workflows/flows.json`. Each step has an actor, action, and optional failed-validation outcome. The builder checks text bounds while drawing. Actors are responsibilities; the manual's role-permission matrix defines who may perform them. The sequence is explanatory and does not imply one user can perform every action.

```powershell
& $manualPython scripts/build-user-manual.py --diagrams-only
```

The generated `.mmd` files can be opened in a Mermaid editor. PNGs are deterministic local drawings, not generated screenshots. Changes to business workflows must first be checked against the corresponding controllers, Form Requests, service authorization and transaction/status rules.

## Screenshot maintenance

Use only actual application captures from an isolated fictional-data environment. Preserve the full navigation context for overview images and use focused detail images when small text must be read on paper. Retake affected screenshots after UI or permission changes, update `SCREENSHOT_INDEX.md` and coverage evidence, then rebuild. Never substitute a drawing or an AI image for an application screenshot.

The builder rejects missing image references. A successful build proves the referenced image file exists; it does not establish that the image matches the live application, contains only fictional data, or is readable. Review those points manually.

After updating the source verification notes, rebuild `exports/Documentation_Assets.zip` from the top-level Markdown files and the `screenshots`, `workflows`, `samples` and `verification` folders, with the build manifest as provenance. Exclude `qa`, generated exports other than the manifest, temporary databases and credentials. Check archive integrity and compare extracted source bytes before publishing it through Help.

For selected key forms, the PDF/DOCX builder also embeds a labeled enlargement cropped from the unchanged real screenshot. These derived images are saved under `qa/details/`; they do not replace the full-context figure. The fixed crop rectangles are in `print_detail` in the builder and must be visually checked or updated after recapturing those screens.

## Required publication checks

Inspect all PDF page PNGs or contact sheets, and open representative pages at full size. Check table wrapping, screenshot detail, captions, margins, page transitions, links and bookmarks. Automated print-bound checks detect text outside the printable region; they cannot establish visual quality or factual accuracy.

Render the DOCX separately with the bundled documents skill renderer on a workstation with Word/LibreOffice support:

```powershell
& $manualPython 'C:\Users\vnz\.codex\plugins\cache\openai-primary-runtime\documents\26.1123.11515\skills\documents\render_docx.py' docs/user-manual/exports/Market_Stall_User_Manual.docx --output_dir docs/user-manual/qa/word-render --emit_pdf
```

If a Word/LibreOffice renderer is unavailable, record the failed attempt and leave DOCX visual/pagination verification explicitly pending. The separately generated PDF is not evidence that Word renders identically. Do not claim approved government distribution, legal contract fidelity or human visual-review authorization from a document build.

Before release, verify all three authenticated download routes and guest denial, compare the manual against actual application behavior, and run relevant application regression tests against disposable databases only. Preserve the original lease template, source inventory, private documents and issued financial/contract records.
