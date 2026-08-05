# OneID v2.7.4 — Administrator Session dan Profile UI

**Versi:** 2.7.4

**Tarikh release:** 5 Ogos 2026

**Environment sasaran:** Staging/UAT

**Status:** IMPLEMENTED / READY FOR STAGING

## Ringkasan

Release ini menutup penambahbaikan Active Sessions Administrator, controlled
session revocation, pemulihan return-context selepas Step-Up, Audit Log default
hari ini dan paparan profil pengguna. Perubahan UI tidak melonggarkan polisi
keselamatan atau membuang fungsi manual yang tersedia sebelum ini.

## Perubahan Utama

- Active Sessions dipadatkan kepada empat kolum dengan carian, lifecycle
  filter, metrik dan pagination default 10 rekod;
- sesi Due dan Expired boleh dibatalkan melalui Preview/Apply yang memerlukan
  Step-Up, sebab dan exact confirmation;
- opaque one-use target, self-lockout protection, transaction dan correlated
  audit diwajibkan bagi setiap pembatalan;
- return-context memulihkan tab/subtab tepat selepas Step-Up;
- Audit Log memuat tarikh semasa secara automatik;
- kad Administrator menggunakan cover OneID, role badge dan profil yang lebih
  tersusun;
- dashboard pengguna mempunyai badge `PENGGUNA`/`USER`; dan
- foto Administrator serta Active Sessions menggunakan resolver same-origin
  dengan sumber Staff/Pelajar dan fallback tempatan yang sama.

## Security Invariants

- fungsi listing Active Sessions kekal read-only;
- current Administrator session dan sesi Administrator lain tidak boleh
  dibatalkan melalui controlled flow;
- resolver foto pengguna lain memerlukan Administrator dan SSO aktif;
- identifier, TLS, timeout, saiz payload dan MIME imej divalidasi; dan
- kegagalan sumber foto menghasilkan fallback tanpa mendedahkan URL upstream.

## Validasi

```bash
php tools/release_metadata_contract.php
php tools/ml8c_content_preview.php
php tests/characterization/ml8c_approved_release_catalogue.php
php tools/ml4_user_dashboard_contract.php
php tools/profile_photo_fallback_contract.php
php tools/as0_active_sessions_contract.php
php tools/as3_controlled_session_revocation_contract.php
```

## English Summary

Version 2.7.4 completes the Administrator Active Sessions, controlled session
revocation, Step-Up return context, today-first Audit Log and profile interface
improvements. Existing security controls remain fail-closed, and user photos
are resolved through the same validated OneID backend path with a local
fallback.
