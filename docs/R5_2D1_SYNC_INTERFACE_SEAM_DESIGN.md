# R5.2D1 — Sync Interface dan Seam Design

Tarikh: 14 Julai 2026

Change ID: `R5-2D1-20260714-035318`

Owner perubahan: Pemilik sistem OneID

Owner rollback: Pemilik sistem OneID

Status: **SELESAI — DESIGN SAHAJA, TIADA PRODUCTION WIRING**

## 1. Tujuan dan Skop

R5.2D1 menyediakan contract di `app/Sync` supaya orchestration kelak tidak
bergantung terus pada global external function dan `Database` concrete object.

Subfasa ini tidak:

- mengubah atau memindahkan `run_admin_sync_user`;
- mencipta adapter production;
- membetulkan transaction boundary D0-W01;
- menjalankan live sync;
- mengubah dashboard, `q_func`, cron atau database;
- mengubah matching, category, exclusion atau password behavior.

## 2. Contract Yang Disediakan

### External source

`ExternalUserSourceInterface::fetchAll()` menjadi seam untuk upstream rows.
Production implementation belum disediakan.

### Persistence dan transaction

`SyncPersistenceInterface` merangkumi transaction, header, user state, staging,
insert/update, audit changes dan summary. Ia menggambarkan capability yang
orchestration semasa perlukan tanpa mendedahkan nama method legacy kepada future
orchestrator.

### Policy dan password

`SyncPolicyInterface` memisahkan excluded user IDs dan source-category mapping.
Identity matching staf/pelajar masih belum dikeluarkan kerana contract datanya
memerlukan fixture tambahan. `InitialPasswordFactoryInterface` memisahkan random
password hash generation tanpa menyediakan implementation production.

### Result DTO

`SyncRunSummary` ialah readonly DTO. `toLegacyArray()` mengekalkan key
`ext_head_id`, `Deactivate`, `Update`, `New`, `Reactivate` dan header fields lain.

## 3. Struktur

```text
app/Sync/
├── Contracts/
│   ├── ExternalUserSourceInterface.php
│   ├── InitialPasswordFactoryInterface.php
│   ├── SyncPersistenceInterface.php
│   └── SyncPolicyInterface.php
├── DTO/
│   └── SyncRunSummary.php
├── SyncDataTransformer.php
└── README.md
```

Mapping contract kepada legacy method tersedia dalam
`docs/R5_2D1_SYNC_SEAM_METHOD_MAP.tsv`. Semua row ditandakan
`production_wired=no`.

## 4. No-production-wiring Guard

Runner `tools/r52_sync_seam_design.php` memeriksa bahawa symbol D1 tidak dirujuk
oleh sync runner, `q_func`, cron atau kedua-dua dashboard. Ia turut mengesahkan
checksum sync runner kekal
`7a5fbe4e9d176661eda4f7d26449641edef683e77d355f9b9d5e227a9331b9df`.

## 5. Characterization

```bash
php tools/r52_sync_seam_design.php
```

Keputusan: **18/18 PASS**.

Checks merangkumi PHP lint lima files, exact method list empat interface, DTO
availability/readonly/legacy projection, tiada production wiring dan sync runner
tidak berubah.

Regression sedia ada kekal:

- sync orchestration fixture 18/18;
- dashboard static characterization 21/21;
- transformer/helper parity 28/28;
- HTTP characterization 70/70 bagi setiap hostname.

## 6. Design Boundary dan Risiko

Interface menyediakan transaction methods tetapi tidak menentukan lokasi
`try/catch`. D0-W01 masih wujud dalam production runner. Memindahkan transaction
boundary ialah functional change berasingan.

Persistence interface masih luas kerana orchestration legacy mengurus banyak
responsibility. Ia hanya boleh dipecahkan selepas adapter dan fixture membuktikan
boundary stabil. External/user/change rows kekal array supaya D1 tidak mengubah
field coercion atau missing-key semantics.

## 7. Gate Sebelum Production Adapter

- [x] D0 orchestration fixture tersedia.
- [x] Interface method mapping tersedia.
- [x] DTO legacy projection diuji.
- [x] No-production-wiring guard lulus.
- [x] Production sync runner tidak berubah.
- [x] Legacy Database adapter dibina sebagai test-only dahulu (R5.2D2).
- [x] Adapter parity dengan fake operation diluluskan (21/21).
- [ ] Transaction boundary change dipisahkan atau diterima scope-nya.
- [ ] Rollback bagi production wiring disediakan.
- [ ] Owner memberi arahan khusus untuk adapter/wiring.

## 8. Rollback D1

D1 tidak mempunyai runtime rollback. Untuk membatalkan design, buang hanya:

```text
app/Sync/Contracts/ExternalUserSourceInterface.php
app/Sync/Contracts/InitialPasswordFactoryInterface.php
app/Sync/Contracts/SyncPersistenceInterface.php
app/Sync/Contracts/SyncPolicyInterface.php
app/Sync/DTO/SyncRunSummary.php
tests/characterization/r52_sync_seam_contracts.php
tools/r52_sync_seam_design.php
docs/R5_2D1_SYNC_SEAM_METHOD_MAP.tsv
docs/R5_2D1_SYNC_INTERFACE_SEAM_DESIGN.md
```

Kemudian pulihkan `app/Sync/README.md` kepada keadaan C1. Jangan buang
`SyncDataTransformer.php` kerana ia ialah runtime extraction C1.

## 9. Checksum

| Fail | SHA-256 |
|---|---|
| `ExternalUserSourceInterface.php` | `9ad2e074202046efcd824b4f2c41a0952f0c4e9bbe05356e7b3931f3c860dbf6` |
| `InitialPasswordFactoryInterface.php` | `148ba8d959ca258689ff72f866f5d27b8782298d0d23ed5a44247e4c4561f234` |
| `SyncPersistenceInterface.php` | `837e249d28a0bd96afe5b07fbd4c5803129400e38364309ba3c04e4096547550` |
| `SyncPolicyInterface.php` | `6d43aa4588edb1ff809a59e69b8934e626a40c0dc514df28d04b956b7372f7bb` |
| `SyncRunSummary.php` | `ad0fd6682c85f31065ff7627e386fb855c2b0fdef20cd0e7a8c78a4d725cc3d1` |
| `app/Sync/README.md` | `d8349e9c632de4a36dfcc1fe9d783c94ee884c41063164050517a280a37ae695` |
| `r52_sync_seam_contracts.php` | `f885b61336ff7e900cfb0644e743bce4fd740731d6d4fc13894c0cf77de5419d` |
| `tools/r52_sync_seam_design.php` | `89314d6dc9d6dd6681c6e05b8c1b13fed530e1c939f76309a6fa5af32e9c5437` |
| `docs/R5_2D1_SYNC_SEAM_METHOD_MAP.tsv` | `07094a8ff7dfaf43294a10a9895a36ffb4c688623e539b736b714bf652174212` |
| `lib/sync_user_runner.php` tidak berubah | `7a5fbe4e9d176661eda4f7d26449641edef683e77d355f9b9d5e227a9331b9df` |

## 10. Keputusan

R5.2D1 selesai sebagai design-only seam. Contract boleh digunakan oleh fake/test
adapter seterusnya, tetapi production orchestration masih menggunakan legacy
function dan Database object tanpa perubahan.
