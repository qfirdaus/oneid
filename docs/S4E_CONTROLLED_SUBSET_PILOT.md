# S4E Controlled Subset Pilot

Status lalai ialah fail-closed. Deployment code ini tidak mengaktifkan Apply.

## Scope tetap pilot pertama

- New: tepat 2
- Update: tepat 1
- Deactivate: 0
- Reactivate: 0
- satu approval server-side, terikat kepada admin, subset fingerprint, full
  source count, accepted baseline dan expiry maksimum lima minit;
- writer membina semula subset deterministik daripada snapshot terkini sebelum
  approval divalidasi dan sebelum transaction bermula.

Konfigurasi selain `2/1/0/0`, flag bukan literal `true|false`, subset tidak
mencukupi, plan berubah, approval tamat tempoh atau replay semuanya ditolak.

## Keadaan biasa sebelum window

```php
'ONEID_SYNC_APPLY_ENABLED' => 'false',
'ONEID_SYNC_ENGINE' => 'disabled',
'ONEID_SYNC_PILOT_ENABLED' => 'false',
'ONEID_SYNC_PILOT_NEW_LIMIT' => '2',
'ONEID_SYNC_PILOT_UPDATE_LIMIT' => '1',
'ONEID_SYNC_PILOT_DEACTIVATE_LIMIT' => '0',
'ONEID_SYNC_PILOT_REACTIVATE_LIMIT' => '0',
```

Dalam keadaan ini preview penuh kekal read-only dan button pilot tidak dihantar
sebagai available oleh server.

## Aktivasi dalam approved window sahaja

Selepas fresh preview, checksum backup, release commit, owner dan monitoring
semuanya disahkan, operator mengubah tiga flag berikut dalam private runtime:

```php
'ONEID_SYNC_APPLY_ENABLED' => 'true',
'ONEID_SYNC_ENGINE' => 'safe',
'ONEID_SYNC_PILOT_ENABLED' => 'true',
```

Limit mesti kekal `2/1/0/0`. Reload PHP-FPM, login sebagai pilot admin, jana
preview baharu dan pastikan UI menyatakan controlled scope yang sama. Button
hanya membawa opaque approval ID; raw identity tidak dihantar atau dilog.

## Selepas satu pilot request

Tanpa menunggu observation tamat, kembalikan ketiga-tiga flag kepada:

```php
'ONEID_SYNC_APPLY_ENABLED' => 'false',
'ONEID_SYNC_ENGINE' => 'disabled',
'ONEID_SYNC_PILOT_ENABLED' => 'false',
```

Reload PHP-FPM dan buktikan preview/preflight menunjukkan disabled. Rekod
header ID, counts `2/1/0/0`, audit `ADMIN_SYNC_SAFE`, reconciliation, log counts
redacted dan observation minimum 60 minit. Sebarang mismatch atau error ialah
NO-GO/rollback; jangan jana approval kedua.

Semak implementation dengan:

```bash
php tools/s4e_controlled_pilot_contract.php
```

Jika full preview Update berubah, inventori read-only boleh dijalankan dengan:

```bash
php tools/s4e_update_investigation.php
```

Output default hanya mengandungi digest, changed-field names dan manifest digest.
`--reveal` memerlukan confirmation interaktif dan hanya untuk semakan private;
jangan salin raw output ke Git, ticket, log atau chat. Plan lama yang tidak
mempunyai manifest calon tidak boleh dibanding secara retrospektif daripada
plan hash sahaja. Simpan hanya manifest digest/count sebagai evidence selepas
set baharu diterima supaya drift seterusnya boleh dikenal pasti dengan tepat.
