# OneID 2.17.4 — Category Translation and Step-Up Resume

**Release date:** 7 October 2026  
**Scope:** Administrator application-category and metadata translation workflow

## Purpose

This release corrects the category editor so it changes the canonical category
name rather than the currently localized display label. BM and English labels
remain independently managed through Metadata Translations.

When a translation save requires `SECURITY_CONFIGURATION_CHANGE` Admin Step-Up,
the pending request is retained in the current browser-tab session for up to 15
minutes. After successful verification, OneID reloads the latest translation
version and resumes the original save automatically. The Administrator no longer
needs to press Save or complete the same workflow a second time.

## Safety controls

- The pending draft is stored in `sessionStorage`, scoped to the current tab.
- A draft expires after 15 minutes.
- The latest server translation version is read before the resumed save.
- Server-side Admin Step-Up, CSRF, authorization, validation, optimistic locking
  and metadata audit history remain enforced.
- No database schema, mobile-production setting or external sync process changes.

## Validation

- PHP 8.4 syntax validation.
- ML7 bilingual metadata contract and isolated schema/repository rehearsal.
- Web application category rename characterization and contract suite.
- Admin Step-Up enforcement, return-context and protected-route checks.
- Release metadata and documentation version contracts.
