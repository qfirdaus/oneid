# S4G Operational External Sync

## Tujuan

Operational Sync membolehkan Administrator menjalankan Apply berulang untuk
data external yang baharu atau berubah tanpa mengikat setiap batch kepada
count/hash dalam private runtime dan tanpa full database dump bagi setiap
Apply. Setiap Apply masih memerlukan fresh preview, approval session sekali
guna, exact plan fingerprint, typed confirmation, advisory lock, transaction,
reconciliation dan audit.

## Polisi Backup

- Kekalkan backup database berjadual dan MySQL binary log/PITR mengikut polisi
  operasi organisasi.
- Full dump khusus tidak diperlukan untuk batch Operational biasa.
- Ambil backup khas jika preview menunjukkan batch luar biasa, perubahan besar
  yang telah diluluskan secara manual, kerja migration, atau sebelum perubahan
  struktur database.
- Operational Apply tidak menggantikan polisi retention, restore rehearsal dan
  pemantauan backup sedia ada.

## Activation Sekali Sahaja

Tetapkan nilai berikut dalam `.private/runtime.php` pada deployment sasaran:

```php
'ONEID_SYNC_APPLY_ENABLED' => 'true',
'ONEID_SYNC_ENGINE' => 'safe',
'ONEID_SYNC_PILOT_ENABLED' => 'false',
'ONEID_SYNC_FULL_ENABLED' => 'false',
'ONEID_SYNC_OPERATIONAL_ENABLED' => 'true',
```

Lint, reload PHP-FPM dan jalankan preflight:

```bash
php -l .private/runtime.php
sudo systemctl reload php8.3-fpm
php tools/s4g_operational_sync_preflight.php
```

Hanya teruskan apabila output ialah
`RESULT operational_runtime_ready=yes mutation_statements=0`.

## Operasi Setiap Batch

1. Administrator buka External Sync dan klik Preview.
2. Semak source rows, New, Update, Deactivate, Reactivate, protected manual,
   collision, warning, plan hash dan expiry.
3. Apply kekal disekat jika source kosong/tidak lengkap, baseline tiada, source
   shrink melebihi 20%, invalid rows melebihi 1%, Deactivate melebihi 5% active
   sync scope, protected identity collision atau kategori source tidak dikenali.
4. Jika plan selamat dan tidak kosong, taip frasa yang dipaparkan. Plan yang
   mempunyai Deactivate memerlukan frasa tambahan dengan exact Deactivate count.
5. Klik Apply sekali. Approval tamat selepas 5 minit dan terbakar selepas satu
   percubaan, termasuk confirmation salah atau plan yang telah berubah.
6. Rekod Header dan counts daripada respons. Semak Sync Log dan log aplikasi jika
   respons gagal atau secondary audit marker memberi warning.

Reconcile sesuatu batch menggunakan tool Operational, contohnya:

```bash
php tools/s4g_operational_sync_result_audit.php \
  --header=43 --source=6485 --new=0 --update=137 \
  --deactivate=0 --reactivate=0 --expected-admin=820705025923
```

Writer mengambil fresh snapshot sebelum transaction. Jika fingerprint/counts
berubah selepas Preview, Apply ditolak dan Administrator mesti menjana Preview
baharu.

## Disable dan Rollback Operasi

Untuk hentikan semua Apply tanpa deployment kod, pulangkan nilai private kepada:

```php
'ONEID_SYNC_APPLY_ENABLED' => 'false',
'ONEID_SYNC_ENGINE' => 'disabled',
'ONEID_SYNC_OPERATIONAL_ENABLED' => 'false',
```

Lint, reload PHP-FPM dan sahkan preflight melaporkan
`operational_runtime_ready=no`. Jangan ulang Apply jika status respons tidak
jelas; semak Sync Log/header, reconciliation dan server log terlebih dahulu.
