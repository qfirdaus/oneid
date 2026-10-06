# OneID 2.15.3 — Report Column Layout

**Release date:** 6 October 2026  
**Change reference:** `ONEID-V2153-REPORT-COLUMNS-20261006-01`

All 25 administrative report previews now use one content-aware column layout service. Sequence, numeric, date and date-time columns use consistent fixed widths. Variable text and URL columns are sized from their longest displayed header or value within bounded minimum and maximum widths.

The table width is the sum of its actual columns. Wide reports retain horizontal scrolling, while compact reports no longer stretch small columns across the entire page. Existing cell and new header tooltips preserve access to truncated content.

This presentation-only release does not change report queries, database data, SSO, runtime configuration or production mobile state.
