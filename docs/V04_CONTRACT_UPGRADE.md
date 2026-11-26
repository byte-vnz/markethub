# v04 lease source integration — 2026-11-26

The active private source is `storage/app/private/references/v04_San_Pedro_Public_Market_Lease_Template.docx` (SHA-256 `d1963eab708eefc59fcd686b8ad16fe5647bd0a12311f496cb60add7c0f454f0`). `config/contracts.php` chooses it. The original is never written. Registration creates a new private working copy, which must receive authorized visual review and a real approval before it appears for new drafts. Existing v02 template rows, generated originals and downloads remain intact; legacy prepared v02 copies still render. No database migration is needed.

Both DOCX packages have 19 parts, five tables, 16 merged-cell grid spans and the same logo bytes. v02 has 224 `w:p` paragraphs; v04 has 221. The v04 rental table moves the lease term below the rental rows, removes the v02 daily CUSA, billing-basis and total-initial fields, adds a distinct `LOCATION (INNER/CORNER)` label and a separate CUSA prose sentence, and changes the monthly figure to rental only. All 24 section headings remain. After accounting for the three-paragraph shift, the only changed Section 1–24 paragraph is Section 7, which fixes the opening word from `old` to `Hold`. The DOCX package differences are `word/document.xml`, footnotes, endnotes, footer, settings and core/app properties; the media image is byte-identical. Text and package comparison cannot establish visual formatting or pagination fidelity. The source's Section 3 still describes first-month monthly and succeeding weekly payments; this integration does not rewrite legal language.

## v04 field map

Positions are zero-based paragraphs in `word/document.xml`, pinned to the checksum. Existing v02 mapping remains in `CONTRACT_TEMPLATE_MAPPING.md` and the service.

| Paragraph | Source field |
| --- | --- |
| 2 and 79 | Contract control number |
| 5, 65, 209 | Lessor name from reviewed source |
| 8, 212 | Verified lessee name |
| 11 | Civil status choices and recorded gender |
| 13, 15, 18 | Citizenship, residential address, contact number |
| 20, 22, 25 | ID type, number, date and place |
| 27, 29 | Assignment helpers, maximum two |
| 33, 36, 39 | Stall code, assessed category/section, frozen floor area |
| 40 | Appends assessed Inner or Outer to the unchanged source location label |
| 42 | Frozen authorized line of business and verified permitted goods |
| 45, 48 | Assessed rate per sqm and daily rent |
| 50, 52, 54 | Assessed two-month advance, one-month deposit, base monthly rent |
| 57 | Validated contract start and expiration dates |
| 61 | Signing date |
| 210–214 | Lessor and lessee identification details where recorded |
| 204, 220 | Removes sample notarial year; notarial completion stays manual |

The generation service uses the existing frozen rental assessment and contract snapshot. It does not multiply daily rent by a guessed 30 days. Contract control numbers, dates, template version, file path/checksum, generation actor and time already exist on the contract and template records. Reprint reads the preserved original. Existing authorization, private storage, review, audit and issuance rules apply.

Verification on the development host: the forced in-memory SQLite application suite passed 285 tests and 2,287 assertions on PHP 8.2.12. The final contract workflow file passed 24 tests and 102 assertions, including v04 generation, retained v02 rendering and rejection of v02 for a new draft. The synthetic sample check found 19 matching DOCX parts, 221 paragraphs, all 24 section headings and unchanged legal paragraphs, and no unresolved placeholders. The best-effort PHP 7.4 source scan parsed 323 files with zero failures; this is not native PHP 7.4 execution. The v1.6 Help publication check passed 43 assertions. The generated manual has 98 PDF pages and the quick guide six, both with no automated print-bound issues.

## Review gates and limitations

The source's sample lease term ends before it starts. The application replaces it with validated contract dates; the intended sample expiration year remains for administrator confirmation. The v04 label says `INNER/CORNER`, while MarketHub records `Inner/Outer`. An Outer value is rendered as Outer and must be reviewed by the authorized office; the software does not equate Outer with Corner. The separate CUSA prose statement and Section 3 collection terms require legal and policy review against current billing rules before approval. Missing identity, goods and issued-ID details remain generation blockers or documented exceptions under the existing workflow. No synthetic signature or notarial fact is inserted.

Use an isolated database to register, generate and inspect a fictional sample. Compare every page of the reviewed working copy and sample with the v04 reference in Word, including the logo, 24 legal sections, footer, signatures, acknowledgment and pagination. This host lacks LibreOffice; Word layout and nine-page fidelity have not been verified. Do not approve or issue the v04 working copy until that review and the terminology/date questions are resolved. Never migrate or reset the live database as part of this source change.

Future replacement: add the new private source unchanged, inspect its package and field positions, pin its checksum with a new mapping, update `config/contracts.php`, run isolated tests, register a new private working copy, and obtain authorized visual approval. Retain all earlier source and generated files.
