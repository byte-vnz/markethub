# Contract generation setup

Contract documents remain under private application storage. DOCX is the primary output; no HTML, Blade, DomPDF or external upload is used to recreate or convert it. The existing Apache public-only Alias and root deny rule must remain intact.

## Dependencies and source

No dependency was added or upgraded. The current `composer.json` targets PHP 7.4.30, Laravel 8.83 and uses Symfony Process through Laravel. Native `ZipArchive`, `DOMDocument`, XML/libxml and mbstring support are required. On the available XAMPP PHP 8.2.12 runtime ZIP, DOM, XML and libxml were confirmed enabled. Actual PHP 7.4 execution remains pending.

PHPWord TemplateProcessor was considered against the task's fidelity requirement. The reviewed source has fragmented control-number runs, merged cells and initially empty paragraphs without text runs. Existing native ZIP/DOM facilities support a smaller targeted solution that preserves every unaffected XML byte and all non-document parts. PHPWord was not installed; no package compatibility or upgrade claim is made.

Preserve the reference DOCX checksum documented in `CONTRACT_TEMPLATE_MAPPING.md`. The source and workbook contain official source material and must stay private; never publish or seed the workbook's real individuals. Prepare a new private working copy with `ContractTemplateService::prepare($sourcePath, $targetPath)`. Both paths are server-generated absolute paths, the destination directory must already exist and an existing destination file is rejected. Store its returned checksum/version metadata. Source changes require an explicit new mapping and template review.

`ContractGenerationService::render($templatePath, $flatSnapshot, $outputPath)` renders only the supplied snapshot. It never queries tenant records. The workflow must validate lease dates, calculations, required identity, signatory configuration, template approval, authorization and private paths before calling the renderer. Preserve issued snapshot/document files; reprints download those files. Rendering rejects missing placeholders, unsupported civil status, invalid XML characters and existing output files.

## Optional local PDF conversion

LibreOffice and Microsoft Word were not found through executable lookup or their conventional Program Files paths on 2026-11-17. No renderer was installed, no downloads were attempted and PDF layout has not been accepted.

After an administrator installs and approves a compatible local LibreOffice deployment, configure its absolute executable path in the private `.env`:

```dotenv
CONTRACT_PDF_EXECUTABLE="C:/Program Files/LibreOffice/program/soffice.com"
CONTRACT_PDF_TIMEOUT=60
```

Prefer LibreOffice's Windows console launcher (`soffice.com`) so the child process remains attached through completion; `soffice.exe` is also configurable but requires Windows timeout/process-lifetime acceptance. Restart the PHP worker and clear/rebuild Laravel configuration according to the normal deployment procedure. Grant the PHP/Apache service identity read access to the approved executable and write access only to required private storage directories.

`ContractPdfConversionService::convert($docxPath, $outputDirectory): ?string` returns an absolute PDF path or `null`. Missing/unconfigured conversion returns `null`; operational failures produce a sanitized warning with exception class only. Display an explicit DOCX fallback to staff; never label a missing PDF as successful conversion.

Each conversion stages a fixed-name copy under a cryptographically unique private directory, uses an isolated LibreOffice user profile and output folder, invokes Symfony Process with an argument array, enforces a timeout (1–300 seconds) and checks both exit success and PDF magic bytes. It moves successful output into the supplied private destination using a random filename and cleans staged files/profile on completion or failure. It does not log the document, ID numbers, original filename, process output or exception message. Converter runtime/profile files may be private but are temporary; do not include their contents in logs or support exports.

Before release, test concurrent conversions and actual Windows process-tree termination with the deployed LibreOffice version. The unavailable/failing converter paths and temporary cleanup are tested locally; successful conversion, actual timeout termination and Windows child lifetime are not yet verified against LibreOffice. No internet-facing production approval follows from this feature.

## Fidelity and acceptance

The source targets nine pages. Automated tests verify source immutability, exact preservation of other ZIP parts and all 24 legal sections/signature paragraphs, five tables, sixteen merged-cell spans, 224 paragraphs, consistent control references, acknowledgment mapping, XML escaping and placeholder completeness. These checks do not prove pagination.

An authorized reviewer must open both original and generated synthetic documents in the supported Microsoft Word version, compare all nine pages, and confirm paper size, margins, fonts, logo, table geometry, page numbering/breaks, headers/footers, signature lines and acknowledgment. Repeat with long names, long addresses and two registered helpers. Compare every converted PDF page against the completed DOCX. Require review or narrower valid inputs where values wrap; never silently truncate or shrink legal content. Record Word/LibreOffice versions and any differences. Until this is done, DOCX structural generation is verified and visual/PDF fidelity remains unverified.

Run the focused checks with:

```powershell
C:/xampp/php/php.exe vendor/phpunit/phpunit/phpunit --filter 'ContractDocumentTest|ContractPdfConversionTest'
```

These checks use synthetic data and disposable temporary files only. They do not migrate or write the development database.
