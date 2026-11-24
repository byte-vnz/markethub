# Market Sections

Open **Settings → Market Sections**. Filter by market, section name or code, and status. A section record shows its current description, display order, label color and stall counts. Counts come from existing stalls in that market and include occupied, vacant, reserved, maintenance and inactive stalls. Use **View stalls** or **View on market map** to open the same section in the corresponding screens.

## Maintain sections

- Super Administrator and Market Administrator can add, edit and reorder sections. By default, all six standard roles can view them. Customized roles need an explicit `sections.view` or `sections.manage` grant. Keep view and manage grants on roles that use the full workflow.
- Choose the market, enter its section code and name, and select a status. Codes are unique within a market. Name matching ignores case and repeated whitespace. Unrelated edits can retain an existing spelling flagged by reconciliation.
- Description and map label color are optional. Enter a six-digit hexadecimal color such as `#2366AC`. The stall map keeps its occupancy colors and shows section colors in small label swatches.
- Set **Display order** on an individual section or filter to one market, clear name and status filters, and open **Reorder sections**. Use **Move up** or **Move down**, then **Save order**. Markets with more than 500 sections use individual **Display order** edits. Order ties use name and record ID.
- Select **Inactive** when a section should stop receiving new stalls or assignments. End occupied assignments before deactivation. Referenced stalls and the section record remain available for history. A section containing stalls cannot move to another market.

## Select sections for stalls and the map

Choose a market on the stall form, then choose an active section. **Search sections** narrows the choices by name or code. An existing inactive section is marked **Retained** and can be kept while editing unrelated stall fields. Existing assignment history still restricts moving a stall to a different market or section.

If authorized to create sections, select **Add section in new tab**. Save the section, return to the original stall tab and choose **Refresh sections**. Your stall form entries stay in the original tab. The new section must be active and belong to the selected market to appear.

The market map groups its summary by current section order. Summary counts reflect the stalls shown under the current filters. The section filter lists active choices. Links to an existing inactive section still show its retained stalls. A rename updates current labels in Stall Management and the map. Existing layout coordinates and historical records retain their stored values.

## Operator verification

The enhanced screens reuse `/manage/sections` and its existing create, view and edit URLs. Additive metadata and role grants require an authorized rollout. Capture the read-only `sections:reconcile` report before and after rollout and compare its preservation controls. See [Section data reconciliation](SECTION_DATA_MIGRATION.md).

On Stall Details, assessed rate and rent labels refer to the initial assessment. Authorized users can open **View Rental Information** to check the applicable reference month for the assignment.
