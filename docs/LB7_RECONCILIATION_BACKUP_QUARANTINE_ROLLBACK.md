# LB7 — Reconciliation, Backup, Quarantine dan Rollback

**Status:** TOOLING LOCAL PASS / LIVE BACKUP DAN RESTORE REHEARSAL BELUM DIJALANKAN

LB7 menyediakan alat operasi tetapi tidak menjalankannya. Migration LB1 belum
diaplikasi, feature flag dinamik kekal OFF dan tiada aset dipindah atau dipadam.

## 1. Reconciliation read-only

```bash
php tools/lb7_login_banner_reconciliation.php > /tmp/lb7-reconciliation.json
```

Laporan mengasingkan environment dan menyenaraikan:

- reference database yang kehilangan fail;
- mismatch byte size atau SHA-256;
- fail yang tidak dirujuk oleh environment semasa tetapi dirujuk environment lain;
- calon orphan yang langsung tidak dirujuk seluruh database.

Script tidak mempunyai SQL mutation atau filesystem mutation. Schema dormant
dilaporkan sebagai unavailable dan bukan alasan untuk menganggap semua fail
sebagai orphan.

## 2. Backup

Semakan tanpa perubahan:

```bash
php tools/lb7_login_banner_backup.php --check
```

Selepas Change ID diluluskan:

```bash
export ONEID_LB7_CHANGE_ID='ONEID-LB7-UAT-CHANGE-ID'
php tools/lb7_login_banner_backup.php --create
```

Backup private diletakkan di
`storage/backups/login_banner/<environment>/<change-id>/` dengan permission
minimum. Ia mengandungi SQL bagi lima jadual, salinan immutable asset dan
`evidence.json` dengan checksum. Status `restore_rehearsal` bermula sebagai
`PENDING`; operator mesti memulihkan kepada database/direktori isolated,
membandingkan row count dan checksum, kemudian merekod bukti PASS di luar repo.
Backup bukan dianggap sah hanya kerana fail berjaya dicipta.

## 3. Quarantine tanpa delete

Dry-run ialah default:

```bash
php tools/lb7_login_banner_quarantine.php
```

Hanya fail `unreferenced_all_database_candidate` berumur sekurang-kurangnya 90
hari layak. Apply memerlukan dua gate:

```bash
export ONEID_LB7_CHANGE_ID='ONEID-LB7-UAT-CHANGE-ID'
export ONEID_LB7_QUARANTINE_AUTHORIZED='true'
php tools/lb7_login_banner_quarantine.php --apply
```

Fail dipindahkan ke private quarantine bersama manifest checksum. Tiada automatic
deletion. Jika satu move gagal, move sebelumnya dikompensasi. Restore tidak
overwrite fail sedia ada:

```bash
php tools/lb7_login_banner_quarantine.php --restore=<batch-id>
```

## 4. Urutan rollback operasi

Trigger rollback termasuk login availability regression, banner salah locale,
asset missing/corrupt, checksum mismatch, carousel tidak boleh digunakan atau
error DB meningkat selepas activation.

1. **Feature rollback:** tetapkan `ONEID_LOGIN_BANNER_ENABLED=false`, reload
   konfigurasi PHP secara terkawal dan smoke-test password serta MyDigital ID.
   Static `banner6.png`/`banner7.png` kembali serta-merta.
2. **Content rollback:** jika sistem stabil dan isu hanya kandungan, gunakan
   rollback versioned LB3 melalui UI dengan Admin Step-Up dan audit.
3. **Code rollback:** revert release code kepada commit diluluskan selepas feature
   OFF; jangan ubah database atau asset semasa rollback code.
4. **Asset restore:** pulihkan batch quarantine menggunakan manifest dan checksum;
   tiada overwrite. Untuk kerosakan lebih luas, pulihkan salinan backup LB7.
5. **Database restore:** hanya melalui DBA selepas backup isolated restore PASS,
   scope row dipastikan dan application writers dihentikan.
6. **Schema down:** migration down ialah pilihan terakhir selepas semua code lama
   dan baharu tidak bergantung pada jadual, data retention diluluskan dan backup
   disahkan. Schema down **bukan langkah pertama** ketika incident.

Setiap rollback merekod masa, environment, Change ID, pelaksana, trigger,
correlation ID, checksum sebelum/selepas dan keputusan smoke test.

## 5. Exit gate

Tooling contract boleh lulus secara local, tetapi LB7 operational gate hanya PASS
selepas backup sebenar, isolated restore, exact row/checksum reconciliation dan
static-fallback smoke test direkod. Sehingga itu LB8 controlled activation kekal
`NO-GO`.
