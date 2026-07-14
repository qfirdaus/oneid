# R5.2C0 — Characterization dan Pure Helper Map

Tarikh: 14 Julai 2026

Change ID: `R5-2C0-20260714-033629`

Owner perubahan: Pemilik sistem OneID

Owner rollback: Pemilik sistem OneID

Status: **SELESAI — AUDIT/CHARACTERIZATION SAHAJA, TIADA RUNTIME MOVEMENT**

## 1. Objektif dan Sempadan

R5.2C0 mengenal pasti helper kecil yang boleh dipisahkan tanpa mengubah HTTP,
session, database atau integration behavior. Subfasa ini:

- menginventori function aktif dan caller dalam repository;
- mengklasifikasikan purity, side effect dan risiko;
- membina characterization bagi calon paling selamat;
- menentukan batch extraction seterusnya;
- tidak memindahkan `q_func`, dashboard atau integration endpoint.

Sebanyak 56 fail PHP bukan-public/non-vendor berada dalam scan scope. Vendor,
snapshot legacy, storage quarantine dan generated public copies tidak dianggap
sebagai source calon extraction.

## 2. Kaedah Klasifikasi

Helper hanya dianggap calon pure apabila outputnya bergantung pada argument dan
tidak melakukan perkara berikut:

- membaca/menulis `$_SESSION`, `$_COOKIE`, `$_SERVER`, `$_POST` atau `$_ENV`;
- memanggil database, ODBC, HTTP, mail atau filesystem;
- menghantar header/response atau `exit`;
- menggunakan masa atau randomness;
- mengandungi authorization/security policy yang tidak patut digenerikkan.

Map mesin-baca penuh tersedia dalam `docs/R5_2C0_PURE_HELPER_MAP.tsv`.

## 3. Keputusan Inventori

### Calon extraction R5.2C1

Enam transformasi dalam `lib/sync_user_runner.php` dikenal pasti deterministic:

1. `sync_compute_hash`;
2. `sync_log_field_names`;
3. `sync_build_log_snapshot`;
4. `sync_pick_log_fields`;
5. `sync_get_changed_fields`;
6. `sync_remove_duplicateKeys`.

Semua caller aktif berada dalam `run_admin_sync_user`. Ia tidak mempunyai public
URL sendiri dan source file boleh dimuatkan dalam CLI tanpa memulakan database,
session atau network. Ini menjadikannya calon lebih selamat berbanding helper
dalam `q_func`.

`sync_get_exclude_uids` deterministic tetapi mengandungi policy hardcoded. Ia
tidak akan dimasukkan sebagai generic transformer sehingga konfigurasi exclusion
ditentukan secara berasingan.

### Sudah berada pada sempadan sesuai

- password/token helper kekal dalam `lib/auth_security.php`;
- path helper kekal dalam `bootstrap/paths.php`;
- filename validation kekal dalam `lib/upload_security.php`;
- integration authorization kekal dalam `lib/integration_security.php`;
- request action map kekal dalam `lib/request_security.php`.

Memindahkan helper ini semata-mata untuk mencantikkan tree tidak memberi manfaat
yang setimpal dengan risiko security regression.

### Bukan calon sekarang

Helper dalam `q_func`, `api.php` dan `skp_api.php` ditangguhkan kerana salah satu:

- mempunyai caller endpoint kritikal;
- membaca global request/session;
- menggunakan database, randomness atau external integration;
- tidak digunakan dan lebih sesuai melalui quarantine/cleanup;
- merupakan security/authorization policy, bukan generic utility.

Empat function tanpa active caller dalam `q_func` ialah `generate_random`,
`generate_random_char`, `sentence_case` dan `string_sanitize`. Comparator
`php_sort_alpahabet` juga tidak mempunyai active caller. Fail-fail ini bukan
untuk diextract; ia calon R5.3 cleanup selepas access/source evidence.

## 4. Characterization Calon Sync

Fail baharu:

- `tests/characterization/r52_pure_helper_contracts.php`;
- `tools/r52_pure_helpers.php`.

Arahan:

```bash
php tools/r52_pure_helpers.php
```

Contract merangkumi availability, hash, field ordering, trimming/default,
snapshot/pick, changed-fields, duplicate filtering dan exclusion policy. Test
bersifat local/read-only dan tidak memerlukan hostname atau credential.

Keputusan awal: **21/21 PASS**.

## 5. Weakness Yang Ditemui

### C0-W01 — Hash tanpa delimiter

`sync_compute_hash` mencantum field selepas `trim` tanpa delimiter atau struktur.
Contohnya, field awal `ab` + `c` menghasilkan input hash sama dengan `a` + `bc`.
Ini boleh mewujudkan ambiguity walaupun SHA-256 sendiri tidak rosak.

### C0-W02 — Changed-field scope tidak lengkap

`sync_get_changed_fields` hanya membandingkan `data1` hingga `data7`. Perubahan
`data8` hingga `data12` dan category tidak dilaporkan sebagai changed fields dan
mungkin tidak mencetuskan update melalui branch semasa.

### C0-W03 — Duplicate odd-count behavior

`sync_remove_duplicateKeys` membuang pasangan hash yang sama, tetapi occurrence
ketiga akan dimasukkan semula. Semantics bergantung pada bilangan ganjil/genap,
bukan sekadar uniqueness.

### C0-W04 — Hardcoded exclusion

`sync_get_exclude_uids` mengembalikan `['10']` terus daripada source. Rasional,
owner dan environment scope exclusion tersebut belum didokumentasikan sebagai
config policy.

Keempat-empat weakness ialah behavior sedia ada. R5.2C0 menguncinya sebagai
characterization dan **tidak membetulkannya**. Fix mesti dibuat sebagai change
functional/data-sync berasingan dengan fixture data, dry-run dan reconciliation.

## 6. R5.2C1 Yang Dicadangkan

Extraction seterusnya boleh mewujudkan class seperti:

```text
app/Sync/SyncDataTransformer.php
```

Strategi compatibility-first:

1. pindahkan enam transformasi deterministic tanpa mengubah algorithm;
2. kekalkan function bernama `sync_*` sebagai wrapper nipis sementara;
3. `run_admin_sync_user` kekal dalam lokasi dan behavior asal;
4. characterization 21 checks mesti lulus sebelum/selepas;
5. jangan jalankan live sync semata-mata untuk file movement;
6. sediakan fixture/dry-run khusus sebelum membetulkan C0-W01 hingga C0-W04.

## 7. Gate Sebelum R5.2C1

- [x] Caller map repository tersedia.
- [x] Pure-helper characterization lulus.
- [x] Tiada runtime PHP dipindahkan dalam C0.
- [x] Weakness legacy dipisahkan daripada restructuring scope.
- [ ] Wrapper design dan exact method signature disediakan.
- [ ] Rollback source/checksum direkodkan.
- [ ] Owner memberi arahan proceed ke C1.

## 8. Rollback R5.2C0

Tiada runtime rollback diperlukan. Untuk membatalkan tooling sahaja, buang:

```text
tests/characterization/r52_pure_helper_contracts.php
tools/r52_pure_helpers.php
docs/R5_2C0_PURE_HELPER_MAP.tsv
docs/R5_2C0_CHARACTERIZATION_DAN_PURE_HELPER_MAP.md
```

Jangan ubah `lib/sync_user_runner.php`, `q_func`, dashboard atau integration
endpoint kerana semuanya tidak disentuh dalam R5.2C0.

## 9. Keputusan

R5.2C0 selesai sebagai audit dan characterization. Calon paling selamat ialah
enam sync transformation helper, bukan helper dalam public request path. Keputusan
GO hanya untuk penyediaan R5.2C1 compatibility extraction; functional sync fixes
kekal di luar skop.

## 10. Checksum Baseline

| Fail | SHA-256 |
|---|---|
| `lib/sync_user_runner.php` tidak berubah | `78362eed2e33e6b037dde9e32e0b54a1d67c7c8b062acf754a56a34a6ca63dd1` |
| `tools/r52_pure_helpers.php` | `e0ab442a0dec7e4dabf8069c760114de65b725384bf0c442ea015241a2cb0645` |
| `tests/characterization/r52_pure_helper_contracts.php` | `b28e8164d5bd3908234d79c15013d697ba987b1f5621e58d8b13bc109cf31214` |
| `docs/R5_2C0_PURE_HELPER_MAP.tsv` | `e18e45335e777d061e77d2ceed88127b4495e717ff42338d6fa610bfa7eef344` |
| `tests/README.md` selepas C0 | `5edb7bae1fd77bc45120d2cf18f09f189d1152fbf88c9c3dc3bb89639e2e92c4` |

**Kemaskini R5.2C1:** Enam calon telah diextract ke
`app/Sync/SyncDataTransformer.php` dengan function `sync_*` kekal sebagai
wrapper. Pure-helper suite berkembang daripada 21 kepada 28 checks dan semuanya
lulus. Rujuk `R5_2C1_SYNC_TRANSFORMER_EXTRACTION_DAN_ROLLBACK.md`.
