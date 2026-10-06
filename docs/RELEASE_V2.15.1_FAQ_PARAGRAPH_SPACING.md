# OneID 2.15.1 — FAQ Paragraph Spacing

**Release date:** 6 October 2026  
**Change reference:** `ONEID-V2151-FAQ-SPACING-20261006-01`

## Purpose

Improve the readability of every user FAQ answer by showing a full blank-line gap between paragraphs on both the login page and the authenticated user dashboard.

## Audit result

- The shared catalogue contains 18 Malay and 18 English FAQ answers.
- All 36 localized answers contain two or three explicit, non-empty paragraphs.
- Both FAQ surfaces use the same shared renderer and stylesheet.
- The previous 10px paragraph margin was smaller than the FAQ line height and did not look like a separate line.

## Change

- Set the spacing between consecutive FAQ paragraphs to `1.15em`, approximately one full text line.
- Updated the FAQ stylesheet asset version on the login page and user dashboard so browsers fetch the corrected style.
- Extended the shared FAQ contract to validate paragraph structure, spacing and asset references.

## Scope

This release changes presentation, tests and release metadata only. It does not change the database, SSO contracts, PHP runtime, cron jobs or production mobile-login state.

## Validation

- PHP lint for the FAQ source, renderer, login page and dashboard.
- Shared FAQ characterization for all 18 entries in both supported languages.
- Release metadata and approved bilingual catalogue contracts.
