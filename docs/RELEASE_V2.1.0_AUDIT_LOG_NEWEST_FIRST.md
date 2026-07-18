# Release OneID v2.1.0 — Audit Log Newest First

**Tarikh:** 14 Julai 2026  
**Versi:** 2.1.0
**Jenis:** Audit Log ordering dan date-range correctness patch

## Perubahan

- Rekod Audit Log disusun menggunakan `datetime DESC, id DESC`.
- ID audit menjadi tie-breaker apabila beberapa rekod mempunyai timestamp sama.
- Tarikh akhir carian merangkumi keseluruhan hari yang dipilih.
- UI menyusun semula response secara defensif sebelum table dirender.
- Had query kekal 50 rekod terbaru untuk menjaga saiz response.

## Sempadan

Perubahan ini read-only terhadap Audit Log. Ia tidak mengubah, memadam atau
menulis semula rekod audit sedia ada dan tidak mengaktifkan External Sync Apply.

## Verification

```bash
php tools/audit_log_order_contract.php
php tools/release_metadata_contract.php
php tools/restructure_smoke.php https://oneid.local --insecure
```
