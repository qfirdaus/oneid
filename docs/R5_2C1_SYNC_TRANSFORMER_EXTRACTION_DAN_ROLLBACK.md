# R5.2C1 — Sync Transformer Compatibility Extraction dan Rollback

Tarikh: 14 Julai 2026

Change ID: `R5-2C1-20260714-034130`

Owner perubahan: Pemilik sistem OneID

Owner rollback: Pemilik sistem OneID

Status: **SELESAI — COMPATIBILITY EXTRACTION LULUS**

## 1. Skop

Enam transformasi deterministic diextract daripada `lib/sync_user_runner.php`
ke:

```text
app/Sync/SyncDataTransformer.php
```

Function global berikut kekal dengan signature asal sebagai wrapper:

- `sync_compute_hash`;
- `sync_log_field_names`;
- `sync_build_log_snapshot`;
- `sync_pick_log_fields`;
- `sync_get_changed_fields`;
- `sync_remove_duplicateKeys`.

`sync_get_exclude_uids` dan `run_admin_sync_user` kekal di lokasi serta dengan
behavior asal. Tiada live sync, database write, schema change atau external
request dijalankan.

## 2. Compatibility Design

Aliran selepas extraction:

```text
run_admin_sync_user
    -> function sync_* sedia ada
        -> app/Sync/SyncDataTransformer static method
```

Caller tidak perlu mengetahui class baharu. Ini mengekalkan nama function,
argument order, return shape dan lokasi include asal.

Class tidak menggunakan database, session, header, filesystem, environment,
masa atau randomness.

## 3. Before dan After

| Perkara | Sebelum | Selepas |
|---|---|---|
| Transform logic | Dalam global function | Dalam static transformer class |
| Global `sync_*` API | Implementation penuh | Compatibility wrapper |
| `run_admin_sync_user` | 1 caller set | Tidak berubah |
| Excluded UID policy | `['10']` | Tidak berubah |
| Sync runner LOC | 336 | 308 |
| New class LOC | Tiada | 82 |
| Public URL | Tiada perubahan | Tiada perubahan |

## 4. Behavior Legacy Yang Sengaja Dikekalkan

R5.2C1 tidak membetulkan weakness C0:

- hash masih mencantum field tanpa delimiter;
- changed-fields masih hanya `data1` hingga `data7`;
- duplicate ganjil masih boleh muncul semula;
- excluded UID `10` masih hardcoded dalam function asal.

Characterization memastikan behavior tersebut sama pada wrapper dan class.
Functional fix memerlukan change data-sync berasingan.

## 5. Fail Berubah

- baharu: `app/Sync/SyncDataTransformer.php`;
- dikemas kini: `lib/sync_user_runner.php`;
- dikemas kini: `tools/r52_pure_helpers.php` untuk class/wrapper parity;
- dikemas kini: `app/README.md`, helper map dan dokumen pelan.

Fixture contract `tests/characterization/r52_pure_helper_contracts.php` tidak
berubah.

## 6. Validation

| Ujian | Keputusan |
|---|---|
| PHP lint transformer | PASS |
| PHP lint sync runner | PASS |
| PHP lint characterization runner | PASS |
| Wrapper/helper legacy contracts | PASS |
| Enam class-to-wrapper parity checks | PASS |
| Jumlah pure-helper checks | 28/28 PASS |
| R5.2 HTTP `oneid.local` | 70/70 PASS |
| R5.2 HTTP `oneid-next.local` | 70/70 PASS |
| Public-root symlink | 0 |
| Live data sync | Tidak dijalankan dengan sengaja |

HTTP characterization turut memuatkan `q_func`, yang meng-include sync runner,
dan membuktikan path class baharu tersedia dalam web runtime.

Live sync tidak sesuai dijalankan semata-mata untuk file movement kerana ia
mempunyai database dan external-source side effect. Fixture parity menjadi
closure gate untuk transformasi pure ini.

## 7. Rollback

Trigger rollback:

- class/include tidak ditemui;
- parity check gagal;
- PHP fatal/warning berkaitan transformer;
- sync fixture menghasilkan hash/snapshot/change/duplicate output berbeza;
- HTTP `q_func` contract berubah.

Langkah:

1. pulihkan enam body function dalam `lib/sync_user_runner.php` daripada baseline
   R5.2C0;
2. buang `require_once` kepada `app/Sync/SyncDataTransformer.php`;
3. pastikan SHA-256 sync runner kembali kepada
   `78362eed2e33e6b037dde9e32e0b54a1d67c7c8b062acf754a56a34a6ca63dd1`;
4. buang class hanya selepas tiada caller;
5. pulihkan characterization runner C0 jika class parity tidak lagi diperlukan;
6. jalankan PHP lint, 21/21 C0 helper test dan 70/70 dua hostname.

Rollback tidak boleh digunakan untuk membetulkan atau mengubah empat behavior
legacy; ia hanya mengembalikan lokasi implementation.

## 8. Checksum

### Sebelum

| Fail | SHA-256 |
|---|---|
| `lib/sync_user_runner.php` | `78362eed2e33e6b037dde9e32e0b54a1d67c7c8b062acf754a56a34a6ca63dd1` |
| `tools/r52_pure_helpers.php` | `e0ab442a0dec7e4dabf8069c760114de65b725384bf0c442ea015241a2cb0645` |
| `tests/characterization/r52_pure_helper_contracts.php` | `b28e8164d5bd3908234d79c15013d697ba987b1f5621e58d8b13bc109cf31214` |

### Selepas

| Fail | SHA-256 |
|---|---|
| `app/Sync/SyncDataTransformer.php` | `6a83c10a44206a506a7802799bd963155d3bf505cc6fc5006db2012accf2dbe9` |
| `lib/sync_user_runner.php` | `7a5fbe4e9d176661eda4f7d26449641edef683e77d355f9b9d5e227a9331b9df` |
| `tools/r52_pure_helpers.php` | `6d457b57360f64199f16b55d6ef87eba18daa3f12f404ffad3f5b1d9e4f63984` |
| fixture contract tidak berubah | `b28e8164d5bd3908234d79c15013d697ba987b1f5621e58d8b13bc109cf31214` |

## 9. Keputusan

R5.2C1 ditutup sebagai PASS. Enam transformation algorithm kini berada dalam
application layer dengan API legacy dikekalkan sebagai wrapper. Tiada bukti
runtime regression dan tiada functional sync behavior diubah.
