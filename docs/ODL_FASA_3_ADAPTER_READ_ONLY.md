# ODL Fasa 3 — Adapter MySQL Read-only

**Tarikh:** 23 Julai 2026

**Environment:** OneID UAT

**Status:** `CLOSED — WSL AND STAGING PREFLIGHT PASSED`

## Skop yang siap

- `OdlSourceConfig` membaca konfigurasi daripada private runtime secret store.
- `OdlStudentSource` melaksanakan `ExternalUserSourceInterface`.
- Query adalah fixed `SELECT` terhadap `upnm.student_basic_info`.
- Hasil dinormalisasi kepada kontrak student sedia ada dengan
  `source_code = STUDENT_ODL_PG` dan kategori `Pelajar`.
- Sambungan PDO tidak persistent, native prepare digunakan dan timeout terhad.
- TLS adalah fail-closed: sesi mesti mempunyai `Ssl_version` serta
  `Ssl_cipher` sebelum data dibaca. CA adalah optional untuk UAT; jika diberi,
  sijil server turut disahkan.
- Connection, query, empty source dan invalid row menghasilkan kegagalan nyata;
  ia tidak ditukar menjadi empty success.
- Adapter hanya dimuatkan sebagai definisi kelas. Tiada wiring kepada Preview,
  Apply, scheduler atau mutation pengguna.

## Bukti sedia ada

- Sambungan manual dari OneID UAT `172.16.2.153` ke ODL
  `172.16.2.224:3308` berjaya.
- Identiti sambungan manual: `viewer@172.16.2.153`, matched account
  `viewer@%`.
- Sesi manual menggunakan TLSv1.3 dengan
  `TLS_AES_256_GCM_SHA384`.
- Grant `viewer@%` adalah SELECT-only tetapi masih luas kepada `moodle.*` dan
  `upnm.*`; keadaan ini diterima untuk UAT sahaja melalui Gate F bersyarat.
- Ujian terdahulu membuktikan UPDATE dan DELETE ditolak.

Sambungan manual tersebut membuktikan route dan TLS tersedia, tetapi tidak
menggantikan runtime preflight adapter.

## Development dan deployment flow

Urutan pelaksanaan yang diluluskan:

1. develop dan jalankan semua ujian dari WSL pada local PC;
2. isi `.private/runtime.php` WSL secara local untuk runtime preflight;
3. setelah preflight WSL lulus, commit dan push hanya source code ke Git;
4. pull commit dari Git ke OneID staging;
5. isi secret staging secara berasingan pada `.private/runtime.php` staging;
6. ulang runtime preflight dari staging sebelum sebarang fasa seterusnya.

`.private/runtime.php` diabaikan oleh Git. Secret WSL tidak dihantar ke staging
melalui repository dan setiap environment memiliki runtime secret sendiri.

## Private runtime WSL yang masih diperlukan

Nilai berikut mesti ditambah terus ke `.private/runtime.php` dalam WSL.
Password dan kandungan private secret tidak boleh dimasukkan ke Git, output
ujian, screenshot atau log.

```php
'ONEID_ODL_MYSQL_HOST' => '172.16.2.224',
'ONEID_ODL_MYSQL_PORT' => '3308',
'ONEID_ODL_MYSQL_DATABASE' => 'upnm',
'ONEID_ODL_MYSQL_USERNAME' => 'viewer',
'ONEID_ODL_MYSQL_PASSWORD' => '<private>',
'ONEID_ODL_MYSQL_SSL_CA' => '', // optional UAT
'ONEID_ODL_MYSQL_CONNECT_TIMEOUT' => '5',
```

Jika CA dibekalkan kemudian, gunakan absolute path. Fail CA mesti boleh dibaca
oleh account servis OneID dan berada di luar direktori `public`.

## Runtime exit check WSL

Jalankan dari root repository dalam WSL:

```bash
php tools/odl_f3_runtime_preflight.php
```

Kriteria lulus:

- `ready=yes`;
- fixed view berjaya dibaca;
- `blank_matric=0`;
- `blank_ic=0`;
- `wrong_category=0`;
- `wrong_source=0`;
- `mutation_statements=0`.

Script hanya memaparkan aggregate dan kod kegagalan yang disanitasi. Ia tidak
memaparkan credential atau data peribadi.

Kelulusan WSL membuktikan adapter, konfigurasi dan connectivity dari development
environment. Ia tidak menggantikan preflight staging kerana source IP, route,
CA trust dan runtime secret staging mungkin berbeza.

### Keputusan WSL

Preflight WSL pada 23 Julai 2026:

```text
RESULT ready=yes rows=53 blank_matric=0 blank_ic=0 wrong_category=0 wrong_source=0 mutation_statements=0
```

Password kekal dalam `.private/runtime.php` yang diabaikan oleh Git. CA tidak
digunakan untuk UAT WSL; adapter memaksa sesi TLS dan membuktikan versi serta
cipher aktif sebelum fixed view dibaca.

## Ujian

```bash
php tests/characterization/odl_f3_student_source.php
php tools/odl_f3_adapter_contract.php
```

Semasa implementasi:

- characterization: 16 checks, 0 failed;
- contract: 11 checks, 0 failed;
- regression sedia ada kekal lulus.

## Baki exit gate

Implementation Fasa 3 kini sedia untuk dihantar ke staging. Selepas deployment,
exit gate environment staging memerlukan preflight yang sama menggunakan
private configuration staging. Apply dan automatic sync kekal disabled.

**Change ID:** `ONEID-ODL-F3-20260723-01`
