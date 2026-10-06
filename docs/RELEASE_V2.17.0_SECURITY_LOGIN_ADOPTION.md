# OneID 2.17.0 — Security and Login Adoption Summary

**Release date:** 6 October 2026  
**Scope:** Administrative reporting only

## Purpose

The existing Security Summary now includes adoption indicators for active staff and student accounts that have never logged in to OneID. This places the figures in the Sessions & Security report group without adding another report tab.

## Definition and classification

- **Never Logged In:** an active user account with no recorded session in `token_tbl`.
- **Staff:** `STAFF_HR`, `Pensyarah`, or `Staf Pentadbiran`.
- **Student:** an account source beginning with `STUDENT_`, or category `Pelajar`.
- Student classification takes precedence to prevent double counting.

The report shows `never logged in / active accounts (percentage)` separately for staff and students. The existing daily ended-session and MFA table remains available.

## Safety

The query is read-only. It does not expose user identities and does not change accounts, sessions, mobile production, Nginx, PHP runtime, cron, timers, or database structure.

## Validation

- PHP 8.4 syntax validation.
- Administrative report contract and bilingual locale parity.
- Read-only aggregation against UAT and production data.
- Release metadata and ML8C catalogue validation.
