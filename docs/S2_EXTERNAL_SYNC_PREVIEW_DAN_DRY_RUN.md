# S2 — External Sync Preview dan Dry-run

Tarikh: 14 Julai 2026
Owner perubahan: Pemilik sistem OneID
Owner rollback: Pemilik sistem OneID
Status: **SELESAI — IMPLEMENTASI, AUTOMATED TEST DAN UAT BROWSER READ-ONLY LULUS**

## 1. Tujuan

S2 memberi pentadbir gambaran blast radius external sync sebelum sebarang data
ditulis. Butang lama yang terus menjalankan full sync telah diganti dengan
`Preview external sync`.

Preview mengambil satu external snapshot dan dua bacaan internal, kemudian
menghasilkan cadangan `NEW`, `UPDATE`, `DEACTIVATE` dan `REACTIVATE`. S2 tidak
mempunyai butang Apply dan tidak menghasilkan sync header, staging row, audit
sync atau perubahan `user_tbl`.

## 2. Boundary Keselamatan

- Endpoint baharu: `admin_preview_sync_user`, admin-only dan dilindungi CSRF.
- Service: `app/Sync/SyncPreviewService.php`.
- Persistence calls yang dibenarkan hanya `activeUsers()` dan
  `inactiveUserIds()`.
- External snapshot kosong ditolak sebelum sebarang bacaan database sync.
- Response sentiasa mengandungi `mode=preview` dan `can_apply=false`.
- Action lama `admin_add_sync_user` kekal untuk compatibility tetapi default
  fail-closed. Ia hanya boleh melepasi gate jika environment
  `ONEID_SYNC_APPLY_ENABLED=true`; flag ini **jangan diaktifkan dalam S2**.
- Raw ID, nombor pengenalan, nama, email dan source row tidak dipulangkan.
  Sample menggunakan SHA-256 digest sahaja.
- Exception teknikal direkod bersama correlation ID; browser menerima mesej
  generik.

## 3. Perlindungan Akaun Manual S1

Pure `SyncPlanner` kini selaras dengan polisi writer S1:

1. Akaun `account_source=manual` dan `sync_protected=1` dikeluarkan daripada
   external matching dan auto-deactivation.
2. Identity map dibina daripada `u_id`, `data2` dan `data4` akaun protected.
3. External row yang bertembung melalui `data4` atau `data2` dibuang daripada
   pelan.
4. Preview memaparkan jumlah akaun protected dan jumlah collision, tanpa
   memaparkan identiti sebenar.

Ini mengelakkan preview menunjukkan perubahan yang writer production sendiri
patut menolak.

## 4. Anomaly dan Expiry

- Plan hash menggunakan SHA-256 dan terikat pada safe projection, count,
  discard count, protected count serta warning.
- Preview luput selepas 300 saat.
- Jika kadar cadangan deactivation melebihi 5% daripada active sync scope,
  status preview menjadi `blocked`.
- Invalid row, policy exclusion dan protected collision menghasilkan warning.
- Walaupun status `normal`, S2 tetap tidak boleh apply.

Threshold ini ialah safety baseline S2, bukan kelulusan untuk live writer.
Lock, transaction, reconciliation dan controlled apply masih skop S3–S4.

## 5. Fail Diubah/Ditambah

- `app/Sync/DTO/SyncPlan.php`
- `app/Sync/SyncPlanner.php`
- `app/Sync/SyncPreviewService.php`
- `lib/external_data_source_API.php`
- `lib/request_security.php`
- `lib/q_func.php`
- `admin/dashboard.php`
- `tests/characterization/s2_sync_preview_zero_mutation.php`
- `tools/s2_sync_preview_contract.php`
- `package.json`

## 6. Automated Verification

Jalankan:

```bash
cd /var/www/app/oneid-uat
php tools/s2_sync_preview_contract.php
```

Alias `npm run check:sync-preview` turut disediakan jika Node/npm dipasang pada
host. Host semasa tidak mempunyai executable `npm`, jadi verification rasmi
telah dijalankan terus menggunakan PHP.

Contract membuktikan:

- external snapshot dipanggil sekali;
- persistence hanya menerima dua read call;
- semua mutation method akan menggagalkan test jika tersentuh;
- empty snapshot fail-closed tanpa database read;
- protected manual tidak diubah;
- collision dibuang;
- raw PII tidak berada dalam response;
- hash, expiry dan threshold warning wujud;
- dashboard tidak lagi POST `admin_add_sync_user`.

Keputusan implementasi 14 Julai 2026:

- S2 contract: `29/29` lulus;
- zero-mutation fixture: `16/16` lulus;
- S1 regression: `39/39` lulus;
- semua characterization PHP: lulus;
- public-root smoke test: `10/10` lulus;
- semua fail PHP first-party yang diperiksa: syntax lulus.

UAT pertama pada 14 Julai 2026 gagal dengan correlation ID
`70c646f381ca91b1`. Log dan reproduksi read-only mengenal pasti
`ODBC_EXTENSION_UNAVAILABLE`: PHP 8.3 CLI/FPM host tidak mempunyai extension
ODBC. Endpoint telah ditambah fail-closed preflight dan safe diagnostic code;
dependency server masih perlu dipasang sebelum UAT diulang.

Selepas extension dipasang, verification read-only kedua mengesahkan driver
`FreeTDS` tersedia tetapi gagal dengan SQLSTATE `IM002` dan code
`EXTERNAL_STAFF_CONNECTION_FAILED`. Kedua-dua secret menyimpan nama DSN,
sedangkan `/etc/odbc.ini` dan `/home/iqs/.odbc.ini` kosong. Tiada definisi DSN
lain ditemui dalam host atau backup OneID. System DSN perlu dibina dalam
`/etc/odbc.ini` menggunakan server/port/TDS version/database yang disahkan
daripada konfigurasi server asal atau pemilik external source. Credential kekal
dalam secret store dan tidak boleh diletakkan dalam fail DSN atau repository.

Keputusan owner selepas verification metadata production: sumber staff rasmi
ialah `ehrmdb.dbo.SSO_Staf_Aktif`. `stafdb` ialah database legacy dan dilarang
digunakan. Query ambil semua staff dan lookup staff tertentu dalam integration
aktif serta compatibility integration telah dikunci kepada `ehrmdb`; contract
S2 akan gagal jika rujukan kembali kepada `stafdb`. Pengesahan production hanya
menggunakan sambungan, `SELECT DB_NAME()`, system metadata dan `SELECT TOP 0`;
tiada row pengguna dibaca dan tiada transaksi dijalankan.

Full read-only preview pertama terhadap production kemudian menemui schema
contract mismatch: FreeTDS memulangkan alias sebagai uppercase (`DATA1`, dan
seterusnya), sedangkan planner menggunakan key lowercase. Semua ODBC result row
dalam integration aktif dan compatibility path kini dinormalisasikan dengan
pure `ExternalRowNormalizer` sebelum memasuki planner. Normalizer menangani
case serta mapping nama asal FreeTDS seperti `idpekerja`, `no_matrik`,
`nama_ptj` dan `jenis`, kemudian hanya mengeluarkan canonical
`data1`–`data12` dan `ext_data_source_category`. Preview itu gagal sebelum plan
siap dan tidak menjalankan sebarang mutation.

Selepas normalizer dibetulkan, full preview terhadap external production source
telah dijalankan dengan kebenaran owner dalam mod read-only. Ia hanya
melaksanakan external `SELECT`, dua bacaan internal OneID dan pure planning;
`can_apply=false` dan tiada transaction/header/staging/audit/write dipanggil.
Evidence tanpa PII:

- source rows: `6485`;
- `NEW=71`, `UPDATE=4`, `DEACTIVATE=0`, `REACTIVATE=0`;
- protected manual: `1`, protected collision: `0`;
- invalid/excluded: `0/0`;
- deactivation: `0.00%`;
- risk: `normal`;
- plan hash prefix: `89c2e0fc52c0`.

Konfigurasi production yang disahkan menggunakan default database `asisdb`.
Student query membaca view dalam `asisdb`; staff query menggunakan fully
qualified `ehrmdb.dbo.SSO_Staf_Aktif`. Oleh itu ketiadaan `Database` pada DSN
staff bukan ambiguity: database sumber dipilih secara eksplisit dalam SQL,
sementara `Database=asisdb` pada DSN student menetapkan konteks bagi view
student yang tidak qualified.

## 7. UAT Browser

UAT browser telah diselesaikan oleh owner pada 14 Julai 2026. Keputusan:

- external rows: `6485`;
- New/Update: `71/4`;
- Deactivate/Reactivate: `0/0`;
- protected manual/collision: `1/0`;
- plan hash prefix: `89c2e0fc52c0`;
- status: `PREVIEW ONLY — no changes applied`;
- warning: tiada planner warning;
- Apply action: tiada.

Keputusan browser sepadan dengan preview CLI read-only. Exit gate S2 diterima.

### Prosedur retest

Login admin dan pilih:

```text
Add User → Preview external sync
```

Semak bahawa:

1. modal bertajuk `External Sync Preview (Read-only)`;
2. count New/Update/Deactivate/Reactivate dipaparkan;
3. plan hash pendek, expiry, protected count dan warning dipaparkan;
4. tiada butang Apply;
5. selepas tutup modal, data pengguna tidak berubah;
6. jika preview gagal, hanya mesej generik dan correlation ID dipaparkan.

Jangan hidupkan `ONEID_SYNC_APPLY_ENABLED` untuk ujian ini.

## 8. Rollback

Rollback code dibuat melalui revert commit S2 selepas checkpoint Git. Jika perlu
containment segera tanpa code rollback:

1. pastikan `ONEID_SYNC_APPLY_ENABLED` tiada atau bernilai `false`;
2. jangan gunakan endpoint mutating lama;
3. kekalkan cron retired;
4. semak PHP/Nginx log menggunakan correlation ID jika preview gagal.

S2 tidak mempunyai migration database dan tidak memerlukan data rollback.

## 9. Gate Ke S3

S2 boleh ditutup hanya selepas automated contract dan UAT browser lulus serta
count preview diterima sebagai munasabah. S3 selepas itu perlu menangani lock,
transaction boundary, source completeness, hard-stop, reconciliation dan
operational rollback sebelum sebarang apply dibenarkan.
