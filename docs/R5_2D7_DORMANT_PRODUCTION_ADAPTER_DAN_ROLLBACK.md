# R5.2D7 — Dormant Production Adapter dan Contract Test

Tarikh: 14 Julai 2026

Change ID: `R5-2D7-20260714-042950`

Change owner: Pemilik sistem OneID

Rollback owner: Pemilik sistem OneID

Status: **SELESAI — PRODUCTION CLASS DORMANT, TIADA CALLER/FEATURE-FLAG WIRING**

## 1. Tujuan dan Batas

R5.2D7 menyediakan empat implementation production bagi interface D1. Semua
class berada dalam `app/Sync/Adapters`, tetapi tiada runtime file memuatkan atau
instantiate class tersebut.

Subfasa ini tidak mengubah `run_admin_sync_user`, `q_func`, cron, dashboard,
feature flag atau transaction boundary. Ia juga tidak mengakses live database,
external API atau menjalankan sync.

## 2. Adapter

### `ExternalApiUserSource`

Mengekalkan panggilan global `EXTERNAL_DATA_SOURCE_GET_ALL_USER()`. Return serta
exception diteruskan tanpa normalization baharu. Future factory mesti memastikan
`lib/external_data_source_API.php` telah dimuatkan sebelum adapter digunakan.

### `SecureInitialPasswordFactory`

Mengekalkan `oneid_password_hash(bin2hex(random_bytes(32)))`. Contract test
memastikan hash tidak kosong dan invocation berasingan menghasilkan nilai
berbeza. Plain random secret tidak dipulangkan atau dilog.

### `LegacySyncPolicy`

Mengekalkan default excluded UID `10`, enam category mapping dan fallback `0`.

### `DatabaseSyncPersistenceAdapter`

Memetakan semua 15 methods `SyncPersistenceInterface` kepada legacy Database
API. Test mengunci exact method, argument order, `data1`–`data12`, UID, category,
password hash, change hash dan return value.

Constructor menerima `object` untuk bridge kepada unnamespaced legacy `Database`
serta isolated spy. Future factory wajib memberikan instance `Database` sebenar.

## 3. Struktur

```text
app/Sync/Adapters/
├── DatabaseSyncPersistenceAdapter.php
├── ExternalApiUserSource.php
├── LegacySyncPolicy.php
└── SecureInitialPasswordFactory.php
```

Register mesin-baca: `docs/R5_2D7_PRODUCTION_ADAPTER_REGISTER.tsv`.

## 4. Contract Test

```bash
php tests/characterization/r52_sync_production_adapter_contracts.php
```

Keputusan: **32/32 PASS**.

Checks merangkumi interface/finality, external return/error, secure password,
policy mapping, exact persistence call/return parity, tiada test dependency,
no-production-wiring dan runtime checksum.

Test menggunakan global fixture dan operation spy dalam memory. Ia tidak
memuatkan config database atau membuat network connection.

## 5. Regression

| Suite | Keputusan |
|---|---:|
| R5.2D7 production adapter | 32/32 PASS |
| R5.2D6 planner purity | 17/17 PASS |
| R5.2D5 dry-run/zero mutation | 25/25 PASS |
| R5.2D3 orchestrator parity | 17/17 PASS |
| R5.2D2 test adapter parity | 21/21 PASS |
| R5.2D1 seam design | 18/18 PASS |
| R5.2D0 legacy orchestration | 18/18 PASS |
| PHP lint lima D7 files | PASS |

## 6. No-production-wiring Evidence

Static guard tidak menemui empat nama adapter dalam sync runner, Database,
`q_func`, cron atau dua dashboard.

| Runtime file | SHA-256 |
|---|---|
| `lib/sync_user_runner.php` | `7a5fbe4e9d176661eda4f7d26449641edef683e77d355f9b9d5e227a9331b9df` |
| `lib/Database.php` | `f9aff1d051c3cc7aa9d8902b299292fe49e70b34b997a69a2feb931109547658` |
| `lib/q_func.php` | `b44534b888c0809ffc3185f73456a8415663d65871e9c7fa8c07c441f5e6a33b` |
| `cron/run_sync.php` | `9c8017a5774a9ea5d12daa70e893ee3d909eeb967df75cceed6dbb438afb3e59` |
| `page/dashboard.php` | `9077b77174d7ec33fcc91b9a66ca349c7f6a105ccbc764dc06511e9f195fc361` |
| `admin/dashboard.php` | `24b2028f0d978a0ce38d1915d2a7bf60445e7ac36c0a360fea04b3db089be7dc` |

## 7. Fail dan Checksum

| Fail | SHA-256 |
|---|---|
| `app/Sync/Adapters/DatabaseSyncPersistenceAdapter.php` | `de98b393bbbc17510a6c936c5ae5d9ef58e7a2f1f62d2d7ac55d30d27f1513d2` |
| `app/Sync/Adapters/ExternalApiUserSource.php` | `a0a5e892abcd6596b1c59860d16e7b44218e37a4851cadf119edb4a379004fa9` |
| `app/Sync/Adapters/LegacySyncPolicy.php` | `c7ba8833f5d5fe291c29a69d393b233c667c012d331135bcc15b1cb994543d86` |
| `app/Sync/Adapters/SecureInitialPasswordFactory.php` | `d9504024ab479e61fa02d24fc688e20e72b93668cdeb55f3b830715ea46aa429` |
| `tests/characterization/r52_sync_production_adapter_contracts.php` | `5a54147308f9fbc3008c0cfa4366a0e5d1c303074f4cf9cf22566c1322538ee6` |

## 8. Risiko Yang Kekal

- Production orchestrator belum wujud.
- External adapter masih bergantung pada global function legacy.
- Persistence adapter bergantung pada method surface unnamespaced object.
- Tiada live credential/database/external validation.
- Shadow, lock, monitoring dan reconciliation belum dilaksanakan.
- Feature flag dan caller wiring kekal NO-GO.

## 9. Rollback D7

Tiada runtime/service/database rollback. Buang empat fail dalam
`app/Sync/Adapters`, D7 contract fixture, register dan dokumen ini. Pulihkan
rujukan D7 dalam README, D6, D4 dan pelan Fasa 7. Jangan buang test adapter D2
kerana ia masih menjadi independent contract fixture.

## 10. Gate Selepas D7

- [x] Empat production adapter tersedia.
- [x] Exact contract/mapping/password behavior lulus.
- [x] Tiada test dependency atau caller wiring.
- [x] Runtime tidak berubah.
- [ ] Production orchestrator dormant belum dibina.
- [ ] Factory/feature flag belum dibina.
- [ ] Live read-only snapshot belum diuji.
- [ ] Cutover gates kekal pending.

## 11. Langkah Seterusnya

Langkah selamat berikutnya ialah **R5.2D8 — bina production orchestrator dormant
dan buktikan full legacy parity menggunakan production adapter**, tanpa caller,
feature flag atau live sync. Selepas D8, application-layer restructuring boleh
berhenti untuk review sebelum D9 live read-only/shadow work.

**Kemaskini D8:** production orchestrator dormant telah tersedia dan full legacy
parity lulus 18/18. Tiada caller/feature-flag wiring dilakukan. Application-layer
sync restructuring kini berada pada checkpoint review.
