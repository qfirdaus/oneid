# Sync Log Source — Audit dan Implementasi Risiko Rendah

**Tarikh:** 8 Ogos 2026  
**Skop:** OneID Admin / Sync Log  
**Status:** IMPLEMENTED / PENDING STAGING MIGRATION AND UAT

## Objektif

Memaparkan sumber sebenar bagi setiap sesi external sync baharu tanpa mengubah
data sumber, token SSO atau aplikasi lain.

Kod canonical:

- `STAFF_HR` — Staf;
- `STUDENT_UG_SMP` — Prasiswazah;
- `STUDENT_ODL_PG` — ODL.

## Keputusan Reka Bentuk

`source_code` nullable disimpan pada `ext_data_temp_header`. Nilai dibawa daripada
`SyncEngineFactory` ke `DatabaseSyncPersistenceAdapter` dan ditulis ketika header
sync dicipta. Ini meliputi pilot, full, operational dan cron menggunakan laluan
safe orchestrator yang sama.

Rekod lama kekal `NULL` dan dipaparkan ringkas sebagai `Legacy`.
Sistem tidak membuat inferens daripada kategori pengguna atau change detail.

`triggered_by=ONEID-CRON` dipaparkan sebagai Cron. Identiti lain dipaparkan
sebagai Manual bersama nama dan nombor staf `user_tbl.data3`. ID Administrator
asal hanya menjadi fallback jika nombor staf tiada.

## Migration

```bash
php tools/sync_log_source_schema.php --check
php tools/sync_log_source_schema.php --apply
php tools/sync_log_source_schema.php --check
sudo /usr/sbin/php-fpm8.3 -t
sudo systemctl reload php8.3-fpm
```

Migration menambah:

```text
ext_data_temp_header.source_code VARCHAR(64) NULL
idx_ext_data_temp_header_source_code (source_code, ext_head_id)
```

Source code baharu fail-closed dengan `SYNC_LOG_SOURCE_SCHEMA_UNAVAILABLE` jika
sync apply dicuba sebelum migration. Pembacaan Sync Log kekal tersedia dan
memulangkan `NULL` supaya deployment source tidak menyebabkan halaman gagal.

Rollback schema tersedia di
`docs/migrations/20260808_sync_log_source_down.sql`. Jalankan rollback hanya
selepas source yang menulis `source_code` dikembalikan.

## Paparan

Senarai Sync Log menambah kolum `Sumber` dengan badge localized:

- Staf;
- Prasiswazah;
- ODL;
- Legacy.

Header detail turut menunjukkan sumber sesi. Paparan ini bukan live progress
tracker; ia menerangkan sumber bagi header sync yang telah direkodkan.

Senarai sesi menggunakan susun atur enam kolum yang lebih padat: ID sesi dan
sumber digabungkan, masa mula/tamat disusun menegak, dan metrik perubahan
dikekalkan dalam grid ringkas. Dalam detail, kolum Sasaran memaparkan
`Nama (No. Staf)` menggunakan `user_tbl.data1` dan `user_tbl.data3`. Snapshot
audit menjadi fallback untuk rekod lama, kemudian ID asal jika kedua-duanya
tidak tersedia. JOIN detail menggunakan perbandingan ID binary kerana kolum
legacy `sync_change_log.u_id` dan `user_tbl.u_id` mempunyai collation berbeza.

## UAT

1. Apply/check migration, uji konfigurasi PHP-FPM dan reload PHP-FPM.
2. Buka Admin → Sync Log dan sahkan rekod lama berlabel Legacy.
3. Jalankan satu manual sync yang mempunyai perubahan bagi sumber terkawal.
4. Sahkan badge sumber dan label Manual tepat pada header baharu.
5. Jalankan cron yang menghasilkan perubahan dan sahkan label Cron serta sumber.
6. Buka detail dan sahkan sumber sama dengan senarai.
7. Pastikan jumlah/status/change detail kekal sama.
8. Uji BM dan English serta paparan mobile.

Cron `SKIP_NO_CHANGES` tidak mencipta header dan oleh itu tidak muncul sebagai
sesi baharu; behavior tersebut tidak diubah oleh task ini.
