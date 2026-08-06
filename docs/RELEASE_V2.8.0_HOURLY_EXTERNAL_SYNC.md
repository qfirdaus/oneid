# OneID v2.8.0 — Hourly Conditional External Sync

**Versi:** 2.8.0

**Tarikh release:** 6 Ogos 2026

**Environment sasaran:** Staging/UAT

**Status:** IMPLEMENTED / STAGING ACTIVATED

## Administrator session expiry warning

- Semua tempoh Admin Step-Up memaparkan SweetAlert apabila baki grant mencapai dua minit.
- Countdown menggunakan baki grant server dan tamat dengan redirect ke akaun pengguna tanpa logout SSO yang masih sah.
- `Stay Connected` menggantikan grant `ADMIN_ACCESS` lama secara atomik mengikut nilai konfigurasi semasa.
- Pembaharuan dilindungi CSRF, session/browser binding, transaksi, audit event 67 dan penyelarasan multi-tab.
- Status polling diklasifikasikan sebagai technical heartbeat supaya semakan masa tidak dianggap aktiviti manusia.

## Ringkasan

Release ini mengaktifkan External Sync pada awal setiap jam bagi Staff,
Prasiswazah dan ODL. Setiap sumber mempunyai plan tersendiri; plan kosong tamat
tanpa mutation, manakala semua perubahan yang lulus kawalan integriti boleh
diproses secara automatik di staging termasuk `Deactivate`.

## Perubahan Utama

- jadual cron ialah `0 * * * *` mengikut zon masa server;
- `flock` dan advisory lock database mencegah run bertindih;
- `SKIP_NO_CHANGES` tidak mencipta transaction, header atau change log;
- staging membenarkan `New`, `Update`, `Deactivate` dan `Reactivate` melalui
  `ONEID_SYNC_CRON_ALLOW_ALL_SAFE_CHANGES=true`;
- source failure/empty, collision, plan drift dan reconciliation mismatch kekal
  fail-closed;
- kegagalan database memaparkan metadata selamat `stage`, `sqlstate` dan
  `driver` untuk diagnosis;
- service identity `ONEID-CRON` serasi dengan `triggered_by` legacy;
- log dirotasi mingguan, dimampatkan dan disimpan selama 52 minggu; dan
- private runtime dikekalkan sebagai `iqs:www-data` dengan mode `0640`.
- baris kategori `Admin SSO` mengira dan menyenaraikan role Administrator aktif
  (`u_type=1`) tanpa memindahkan staf keluar daripada kategori asal mereka.
- laporan senarai pengguna kategori menggunakan paparan profesional sedia cetak
  dengan branding UPNM/OneID, metadata, jumlah, jadual A4 dan print stylesheet.

## Kontrak Operasi

```text
top of hour
  -> flock
  -> STAFF_HR
  -> STUDENT_UG
  -> STUDENT_ODL_PG
  -> no changes: SKIP_NO_CHANGES
  -> safe changes: transaction + reconciliation + APPLIED
  -> failure: rollback + FAILED
```

## Validasi

```bash
php tests/characterization/s4h_conditional_sync_cron.php
php tests/characterization/s3_sync_operational_safety.php
php tools/s4h_conditional_sync_cron_contract.php
php tools/source_scoped_sync_apply_contract.php
php tools/release_metadata_contract.php
php tools/version_documentation_contract.php
```

## English Summary

Version 2.8.0 activates source-isolated External Sync at the start of every
hour in staging. Empty plans perform no mutation, while all safe change types
can be applied automatically. Locking, exact-plan validation, transactions,
rollback and reconciliation remain mandatory, and operational logs are retained
for 52 weeks.
