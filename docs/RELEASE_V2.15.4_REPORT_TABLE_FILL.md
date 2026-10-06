# OneID 2.15.4 — Report Table Panel Fill

**Release date:** 6 October 2026  
**Change reference:** `ONEID-V2154-REPORT-TABLE-FILL-20261006-01`

Report tables now fill their panel rather than ending at the sum of compact column widths. Fixed-width sequence, numeric, date and date-time columns retain their consistent sizes. The primary variable text column absorbs spare panel space.

The calculated data-based width remains the table minimum, so genuinely wide reports continue to scroll horizontally. This presentation correction applies to all 25 reports and does not change queries or database data.
