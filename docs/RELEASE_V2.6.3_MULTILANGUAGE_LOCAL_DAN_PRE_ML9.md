# OneID v2.6.3 — Multilingual Local dan Pre-ML9 Readiness

**Versi:** 2.6.3
**Tarikh release:** 26 Julai 2026
**Environment implementation:** Local WSL
**Environment sasaran seterusnya:** UAT staging melalui ML9
**Change reference:** `ONEID-ML9A-RELEASE-20260726-01`

## Ringkasan Bahasa Melayu

Release ini menyatukan pelaksanaan BM/English yang telah disahkan pada Local
WSL: locale infrastructure, Login/Recovery/OTP, User dan Administrator
Dashboard, API/e-mel/notification, metadata aplikasi dan kategori, FAQ,
Version Releases, External Sync, Admin Step-Up serta Administrator
Multilingual Completeness.

Kandungan release mempunyai parity `38/38` release dan `229/229` changelog
BM/English selepas release ini ditambah. Approved catalogue diikat kepada
digest:

`1eba6fbee555b918adab56366b5bc28f5c4b963c1663c0c3782c9f32d0f5de66`.

## English Summary

This release consolidates the BM/English implementation verified on Local WSL:
locale infrastructure, Login/Recovery/OTP, the User and Administrator
Dashboards, API/e-mail/notification feedback, application and category
metadata, FAQ, Version Releases, External Sync, Admin Step-Up and
Administrator Multilingual Completeness.

After adding this release, the approved catalogue provides parity for `38/38`
releases and `229/229` BM/English changelog items. The catalogue is bound to
the exact digest shown above.

## Security and operational boundaries

- Bahasa Melayu remains the default and hard fallback.
- Legacy `msg` remains available during the compatibility window.
- Exact confirmations, source codes, action codes, purpose/factor codes,
  hashes, correlation IDs and technical identifiers remain canonical.
- Authentication, authorization, ACL, session lifetime and External Sync
  business rules are unchanged.
- English User Manual PDF remains deferred by the owner; the explicit BM
  fallback notice remains active.
- This release does not authorize staging schema migration, staging activation
  or Production deployment.

## Verification

ML9A executes the complete multilingual contract suite, dashboard
characterization, release/documentation checks, secret scan and private-runtime
exclusion before Git commit and push.
