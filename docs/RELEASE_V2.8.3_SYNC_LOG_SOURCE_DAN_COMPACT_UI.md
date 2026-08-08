# OneID v2.8.3 — Sync Log Source dan Compact UI

**Versi:** 2.8.3  
**Tarikh:** 8 Ogos 2026  
**Skop:** OneID Administrator / External Sync Log

## Ringkasan

Release ini menjadikan setiap sesi Sync Log baharu boleh dikenal pasti mengikut
sumber canonical `STAFF_HR`, `STUDENT_UG_SMP` atau `STUDENT_ODL_PG`. Rekod lama
yang tidak mempunyai metadata tersebut kekal dipaparkan sebagai Legacy / Tidak
diketahui dan tidak diteka daripada kandungan perubahan.

Paparan senarai disusun semula kepada enam kolum yang lebih padat. Session dan
source digabungkan, masa mula/tamat disusun menegak, metrik perubahan diringkas,
dan Cron dibezakan daripada Manual. Pelaksana Manual dipaparkan sebagai
`Nama (No. Staf)` menggunakan `user_tbl.data1` dan `user_tbl.data3`.

Detail session menggunakan format sasaran yang sama dengan fallback kepada
snapshot audit atau ID asal. Perbandingan binary digunakan untuk menyokong
collation berbeza antara jadual legacy tanpa mengubah data.

## Keselamatan dan Compatibility

- Satu Sync Log session kekal mewakili satu source sahaja.
- Cron boleh menghasilkan beberapa session berasingan dalam satu execution.
- Source tanpa perubahan tidak menghasilkan header baharu.
- Migration `source_code` adalah nullable dan idempotent.
- Apply source-scoped gagal secara selamat jika schema belum tersedia.
- Tiada perubahan kepada token SSO, kontrak service provider atau aplikasi lain.

## Deployment Staging

```bash
php tools/sync_log_source_schema.php --apply
php tools/sync_log_source_schema.php --check
php tools/release_metadata_contract.php
php tests/characterization/sync_log_source_visibility.php
```

Migration telah dipasang di staging semasa pembangunan v2.8.3. Deployment
semula kekal selamat kerana schema tool hanya menambah komponen yang belum ada.

## English Summary

OneID v2.8.3 records the canonical source for every new External Sync Log
session, distinguishes Cron from Manual activity, shows full staff numbers for
manual actors, and provides a compact professional six-column session view.
Legacy records remain explicitly unknown, differing table collations are handled
without data mutation, and SSO or service-provider contracts remain unchanged.
