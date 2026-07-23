# ODL Fasa 3 — Adapter MySQL Read-only

**Tarikh:** 23 Julai 2026

**Environment:** OneID UAT

**Status:** `IMPLEMENTATION READY / RUNTIME PREFLIGHT PENDING`

## Skop yang siap

- `OdlSourceConfig` membaca konfigurasi daripada private runtime secret store.
- `OdlStudentSource` melaksanakan `ExternalUserSourceInterface`.
- Query adalah fixed `SELECT` terhadap `upnm.student_basic_info`.
- Hasil dinormalisasi kepada kontrak student sedia ada dengan
  `source_code = STUDENT_ODL_PG` dan kategori `Pelajar`.
- Sambungan PDO tidak persistent, native prepare digunakan dan timeout terhad.
- TLS adalah fail-closed: CA wajib, sijil server wajib disahkan, dan sesi mesti
  mempunyai `Ssl_version` serta `Ssl_cipher`.
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
menggantikan runtime preflight adapter kerana adapter mewajibkan CA dan
pengesahan sijil server.

## Private runtime yang masih diperlukan

Nilai berikut mesti ditambah terus oleh administrator ke `.private/runtime.php`
atau secret store setara. Password dan kandungan private secret tidak boleh
dimasukkan ke Git, output ujian, screenshot atau log.

```php
'ONEID_ODL_MYSQL_HOST' => '172.16.2.224',
'ONEID_ODL_MYSQL_PORT' => '3308',
'ONEID_ODL_MYSQL_DATABASE' => 'upnm',
'ONEID_ODL_MYSQL_USERNAME' => 'viewer',
'ONEID_ODL_MYSQL_PASSWORD' => '<private>',
'ONEID_ODL_MYSQL_SSL_CA' => '/absolute/private/path/mysql-ca.pem',
'ONEID_ODL_MYSQL_CONNECT_TIMEOUT' => '5',
```

Fail CA mesti boleh dibaca oleh account servis OneID dan berada di luar
direktori `public`.

## Runtime exit check

Jalankan dari server OneID UAT:

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

Fasa 3 belum ditutup sehingga runtime preflight sebenar berjaya menggunakan
private configuration. Apply dan automatic sync kekal disabled.

**Change ID:** `ONEID-ODL-F3-20260723-01`
