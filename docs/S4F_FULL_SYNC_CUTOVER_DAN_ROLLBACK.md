# S4F Full Sync Cutover dan Rollback

Tarikh penyediaan: 18 Julai 2026
Status: **IMPLEMENTED DORMANT — FULL APPLY DISABLED**

## Tujuan

S4F menyediakan full synchronization melalui safe orchestrator tanpa mengubah
default runtime. Kod yang dideploy tidak boleh Apply sehingga konfigurasi
private menetapkan mode `true/safe`, full mode, exact counts dan full 64 aksara
plan hash daripada fresh preview yang diterima owner.

## Sempadan Keselamatan

- action full berasingan daripada controlled pilot;
- pilot dan full tidak boleh enabled serentak;
- admin, CSRF dan exactly-one-action guard kekal wajib;
- approval disimpan server-side, terikat kepada admin dan tamat dalam 5 minit;
- writer mengambil snapshot baharu dan mengesahkan exact counts serta plan hash;
- advisory lock menghalang writer serentak;
- semua mutation, audit dan reconciliation berada dalam transaction;
- mismatch, warning, expiry atau reconciliation failure menyebabkan fail-closed;
- committed defaults sentiasa `false/disabled/false`.

## Gate Sebelum GO

1. Siasat dan terima semua calon Update dan Deactivate secara private.
2. Pastikan fresh preview tiada warning/collision dan source baseline sah.
3. Rekod exact `New/Update/Deactivate/Reactivate`, source rows dan 64-char hash.
4. Cipta backup baharu di luar document root dan sahkan checksum/restore.
5. Tetapkan maintenance window, freeze, DBA/SA, monitoring dan rollback owner.
6. Pastikan scheduler kekal retired dan hanya seorang admin menjalankan Apply.
7. Rekod arahan GO owner untuk exact plan tersebut.

## Konfigurasi Private Dalam Window

Contoh sahaja; nilai mesti datang daripada fresh preview yang diluluskan:

```php
'ONEID_SYNC_APPLY_ENABLED' => 'true',
'ONEID_SYNC_ENGINE' => 'safe',
'ONEID_SYNC_PILOT_ENABLED' => 'false',
'ONEID_SYNC_FULL_ENABLED' => 'true',
'ONEID_SYNC_FULL_EXPECTED_NEW' => '<exact>',
'ONEID_SYNC_FULL_EXPECTED_UPDATE' => '<exact>',
'ONEID_SYNC_FULL_EXPECTED_DEACTIVATE' => '<exact>',
'ONEID_SYNC_FULL_EXPECTED_REACTIVATE' => '<exact>',
'ONEID_SYNC_FULL_EXPECTED_PLAN_HASH' => '<64-char hash>',
```

Reload PHP-FPM mengikut prosedur operasi dan jalankan:

```bash
php tools/s4f_full_sync_preflight.php
php tools/s4f_full_sync_contract.php
```

## Apply Dan Reconciliation

Generate preview baharu selepas flags aktif. Butang hanya muncul jika counts dan
hash tepat. Taip frasa yang dipaparkan, semak ringkasan, kemudian lakukan maksimum
satu Apply. Selepas success, ambil header dan jalankan:

```bash
php tools/s4f_full_sync_result_audit.php \
  --header=<id> --source=<rows> --new=<n> --update=<n> \
  --deactivate=<n> --reactivate=<n> --expected-admin=<admin-id>
```

## Abort Dan Rollback

Abort tanpa Apply jika counts/hash berubah, approval tamat, warning muncul,
backup gagal, log tidak tersedia atau owner/on-call tiada. Selepas request apa
pun, segera pulangkan semua Apply/full flags kepada disabled dan reload PHP-FPM.
Jika reconciliation gagal, jangan retry; kekalkan freeze, kumpul correlation ID,
semak transaction/header dan gunakan restore procedure DBA hanya selepas keputusan
rollback owner. Feature flag tidak membatalkan data yang telah committed.

## Current Preview Bukan GO

Preview `6485 / 70 / 33 / 1 / 0` dengan hash prefix `af73c50a124f` hanyalah
evidence read-only. Hash penuh, semakan 33 Update, satu Deactivate, backup baharu,
window dan keputusan GO masih diperlukan sebelum konfigurasi private boleh diubah.

## Rekod Semakan Calon

Pada 18 Julai 2026 15:15 +08:00, Firdaus menyelesaikan semakan private dan
menerima semua `33` calon Update serta `1` calon Deactivate. Manifest Update
yang diterima ialah
`a1f94c97346db8bf4f93d7048596f6fbe9a8edc00be25f83f5d406aa5d110e2f`.
Tiada identiti atau nilai mentah daripada output reveal disimpan dalam Git.

Semakan ini menutup gate calon sahaja. Ia tidak memberi GO Apply dan akan
terbatal jika fresh preview menghasilkan count atau manifest yang berbeza.

## Rekod Backup Preflight

Preflight pada AppsStagingv1 lulus pada 18 Julai 2026 untuk source
`172.16.2.141`, server `mysql8-DEV` dan database `oneiddb`. Anggaran database
ialah `179240960` bait dengan `91757707264` bait ruang bebas; `mysql` dan
`mysqldump` tersedia serta effective Apply kekal `false/disabled`. Gate backup
masih pending sehingga execute, checksum dan isolated restore reconciliation
selesai.

Backup dan isolated restore kemudiannya selesai pada `15:26:26 +08:00` dengan
change ID `S4D-20260718-152545`. Dump `81770695` bait mempunyai SHA-256
`ca983cf587527ce2a6f9521923f69c8e4677a9ac0f9fb998edbd241219e47122`.
Kesemua `18` jadual mempunyai exact row-count digest
`9996e7171fd49b2c72cd380354fdf662fe90353bc6d2985a20b538ea34c5cedc` pada
source dan restore. Source tidak diubah dan target rehearsal berjaya dibuang.
Gate backup S4F-07 ditutup sebagai PASS.

## Rekod Maintenance Window

Change `ONEID-S4F-20260718-01` diluluskan untuk window 18 Julai 2026
`16:00-16:30 +08:00`, dengan observation sehingga `17:30`. Executing admin ialah
`0530-09`. Firdaus ialah change owner, rollback owner, DBA/SA on-call,
monitoring owner dan security reviewer. Saluran insiden ialah WhatsApp dan
maximum Apply request ialah satu. Gate window S4F-08 ditutup sebagai PASS.

## Fresh Full Plan Evidence

Fresh staging preview pada 18 Julai 2026 menghasilkan source `6485`, New `70`,
Update `33`, Deactivate `1`, Reactivate `0`, protected manual `1`, collision `0`
dan tiada planner warning. Full plan hash ialah
`af73c50a124ff1fa28adc641c21aff2338ca49ba9529a93b112b7386997e8b5f`.
Approval preview tersebut tamat pada `15:43:35 +08:00`; ia bukan authorization
Apply. S4F-09 kekal pending sehingga exact plan menerima GO eksplisit dan fresh
preview baharu dijana dalam maintenance window.

Firdaus kemudian memberikan keputusan `GO S4F FULL SYNC` untuk change
`ONEID-S4F-20260718-01`, release `ff20b0d`, exact counts `70/33/1/0`, full hash
di atas, update manifest yang telah diterima, backup `S4D-20260718-152545` dan
executing admin `0530-09`. Maximum Apply request kekal satu. Gate S4F-09
ditutup sebagai PASS; fresh preview dalam window masih wajib sepadan sebelum
butang Apply digunakan.

## Rekod Runtime Activation

Pada 18 Julai 2026 `16:03:59 +08:00`, AppsStagingv1 release `ff20b0d`
berjaya reload PHP-FPM. Service aktif dengan `0` process aktif, `6` idle dan
`0` slow request. Effective runtime ialah Apply `true`, engine `safe`, pilot
`false`, full `true`, expected counts `70/33/1/0` dan plan hash prefix
`af73c50a124f`. Preflight melaporkan `full_runtime_ready=yes` dan
`mutation_statements=0`. Gate S4F-10 ditutup sebagai PASS.

## Rekod Full Apply dan Reconciliation

Full sync committed sebagai header `42` dengan source `6485`, New `70`, Update
`33`, Deactivate `1` dan Reactivate `0`. Flags dipulangkan kepada
`false/disabled/false` selepas respons dan preflight mengesahkan full runtime
tidak lagi ready.

Audit pertama menggunakan staff ID `0530-09` dan mendapati `admin_match=no`.
Investigation read-only membuktikan header menyimpan canonical login ID
`820705025923`; akaun itu aktif, bertaraf admin dan mempunyai approved staff ID
`0530-09` dalam `data3`. Audit diulang menggunakan canonical login ID dan lulus:
header status `2`, uploaded `70`, semua counts serta audit sepadan dan syslog
marker tepat satu. Gate reconciliation S4F-11 ditutup sebagai PASS. Perbezaan
identifier tidak menunjukkan executor berlainan dan tiada database correction
dilakukan.

## Rekod Observation dan Keputusan Akhir

Observation pasca-Apply diteruskan sehingga masa yang diluluskan dan selesai
pada 18 Julai 2026 `17:30:00 +08:00`. PHP-FPM kekal aktif, log akses dan error
Nginx boleh dibaca, serta semakan selepas Apply mendapati `0` fatal/database
error dan `0` marker kegagalan `ONEID_SYNC_FULL`. Tiada pendedahan data sensitif
dikesan dan effective runtime Apply kekal disabled.

Firdaus merekodkan keputusan akhir `ACCEPT` untuk change
`ONEID-S4F-20260718-01`. Gate monitoring S4F-12 dan keputusan akhir S4F-13
ditutup sebagai PASS; operasi Full External Sync ini selesai tanpa rollback.
