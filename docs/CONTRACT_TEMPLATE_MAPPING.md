# Contract template mapping

This document records the retained v02 mapping. New drafts use the separate [v04 map and review gates](V04_CONTRACT_UPGRADE.md).

The source authority is `storage/app/private/references/v02_San_Pedro_Public_Market_Lease_Template.docx`, SHA-256 `38eb9c85d161d98a7f32f213efbefa3b810e682b72d1a9d4352a70cc13b014fb`. The original is never edited. The prepared version is a private copy. A different checksum requires a reviewed mapping and a new version; it cannot silently reuse this map.

Locations below are zero-based `w:p` positions in `word/document.xml` (including table paragraphs). Actual source inspection found 224 paragraphs, five tables, sixteen `w:gridSpan` merged-cell definitions and a retained logo image. Paragraph indices are safe only because the source checksum is pinned.

| Location | Placeholder or change |
| --- | --- |
| 2, header table | `${contract_control_no}` |
| 5, introductory identification of lessor | `${lessor_name}`; only the existing person's name changes |
| 8, personal details table | `${lessee_full_name}` |
| 11, original Civil Status / Gender cell | `[${civil_single}] Single  [${civil_married}] Married  ${gender}` |
| 13 | `${citizenship}` |
| 15 | `${residential_address}` |
| 18 | `${contact_number}` |
| 20 | `${government_id_type}` |
| 22 | `${government_id_number}` |
| 25 | `${id_date_place_issued}` |
| 27, 29, original helper cells | `${helper_1}`, `${helper_2}` |
| 33, leased premises table | `${stall_code}` |
| 36 | `${stall_category}` |
| 39 | `${floor_area}` |
| 41 | `${lease_period}`; replaces the invalid original sample period |
| 43 | `${permitted_goods}` |
| 46 | `${rental_rate_sqm}` |
| 49 | `${daily_rental}` |
| 51 | `${daily_cusa}` |
| 53 | `${billing_basis}` |
| 55 | `${total_monthly_rental_cusa}` |
| 57 | `${advance_rental}` |
| 59 | `${security_deposit}` |
| 61 | `${total_initial_payment}` |
| 64, execution sentence | `${execution_day}`, `${execution_month}`, `${execution_year}` |
| 68, lessor signature block | `${lessor_name}`; signature line and title retained |
| 82, attachment reference | `${contract_control_no}` replaces the sample number fragmented across two existing runs |
| 212, acknowledgment lessor row | `${lessor_name}` |
| 213 | `${lessor_identity}`, derived from supplied lessor ID type and number, otherwise blank |
| 214 | `${lessor_id_date_place_issued}`, otherwise blank |
| 215, acknowledgment lessee row | `${lessee_full_name}` |
| 216 | `${lessee_identity}`, derived from government ID type and number |
| 217 | `${id_date_place_issued}` |
| 207, 223, notarial variable years | Fixed sample `2026` changed to `_____`; no notarial facts invented |

`civil_status` must be exactly `Single` or `Married`. Rendering derives the two checkbox values (`X ` or two spaces); both existing choices remain visible. Other statuses require an authorized alternative template mapping. Gender appears as a value within the original cell; no new visible label or cell is introduced. Empty helpers remain empty; registration eligibility is decided when the workflow builds the immutable snapshot.

All existing paragraphs, runs, table properties, merged cells, paragraph properties, fonts, sections, page settings, margins, page breaks and ZIP parts are preserved. Blank source fields contain no text runs; preparation adds one text run inheriting each existing paragraph's run properties. Existing variable fields change only their `w:t` content. No whole-document library serialization occurs. All non-document ZIP member contents remain byte-identical. All legal/signature paragraphs 84–201 remain byte-identical, including Sections 1–24. The lessee signature lines, witnesses and notarial seal/execution fields remain available for actual signing. The market manager name is retained from the template, pending any separately authorized new template version.

Review issues retained from the source: the invalid lease period must be supplied as valid dates by the workflow; the sample control number must be replaced; Section 3 uses the original wording `The less amount of two (2) months advanced rental` and first-month/monthly then weekly payments. Legal text and typographical discrepancies are not corrected by generation. The original replacement character visible in some source text is retained exactly and requires authorized source review rather than automatic cleanup.

The renderer consumes a frozen flat snapshot, never current tenant records. It escapes XML metacharacters, rejects control characters, placeholder syntax, missing fields and values exceeding 1,000 characters. This limit is an input guard, not proof that a value fits. Long names, addresses, both helpers and every actual issued document require visual review without truncation. The nine-page target and page-by-page Word/PDF comparison remain pending because no compatible document renderer was available in this environment.

## Unified assessed contracts — 2026-11-19

New contracts require an active business-linked assessed assignment. The assessment supplies category/trade/goods, location, measured area, selected category rate (Inner minimum or Outer maximum), daily rental, frozen CUSA/day policy and exact advance/deposit components. Reference month/day rule is included in the existing billing-basis placeholder; no legal paragraph, table structure, signature or notarial fact is invented. Template legal content and original reference ZIP parts remain authoritative.

Current verified tenant identity and assignment helpers are validated and frozen at first document generation; completing identity after assignment does not rewrite the assessment. Business/trade and financial basis remain the assessment's original values. Later downloads/reprints return the checksum-verified generated original. Changing business terms requires an authorized new agreement/assessment; a master edit never changes an existing file. Legacy monthly assignments require explicit renewal before creating a new assessed contract. Issuance still requires template approval, policy/numbering authority and authorized visual review. Word nine-page fidelity/PDF conversion remain unverified in this environment.
## Assignment rental override integration

Drafting, generation and issuance check that the lease fits one constant applicable rental interval. An approved manual request supplies base rent only for its owning assignment and covered months. The contract assessment records the applied mode, request and frozen basis. A direct daily amount whose two-decimal equivalent rate cannot reproduce the daily amount at the frozen area blocks DOCX generation and issuance. A previously generated original retains its checksum and remains the source for reprints.

