# OneID 2.15.2 — FAQ Blank-Line Fix

**Release date:** 6 October 2026  
**Change reference:** `ONEID-V2152-FAQ-BLANK-LINE-20261006-01`

The earlier paragraph margin was overridden by more specific panel and accordion rules. This release applies a stronger shared selector and a `1.65em` top margin, equal to one full FAQ text line, before every paragraph after the first.

The correction applies to all 18 Malay and 18 English FAQ answers on the login page and authenticated user dashboard. The asset cache key is updated. No database, SSO, runtime or production mobile setting is changed.
