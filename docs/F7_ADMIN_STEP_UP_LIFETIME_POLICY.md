# F7 — Polisi Tempoh Admin Step-Up

**Status:** IMPLEMENTED / DEFAULT 15 MINUTES / UAT CONTRACT PASS  
**Tarikh:** 20 Julai 2026  
**Skop:** Semua grant Admin Step-Up baharu, bagi setiap admin dan purpose

## Keputusan reka bentuk

Tempoh Admin Step-Up tidak mengikuti `token_timeout` SSO. Kedua-dua polisi
dikekalkan berasingan kerana grant Administrator melindungi operasi sensitif.

Nilai yang dibenarkan ialah 5, 10, 15 dan 30 minit. Nilai default dan nilai UAT
semasa kekal 15 minit. Constraint database dan validation service menolak nilai
lain.

## Semantik perubahan

- perubahan memerlukan grant `SECURITY_CONFIGURATION_CHANGE`;
- sebab perubahan minimum 10 aksara diperlukan;
- `configuration_version` mencegah lost update;
- configuration history dan syslog event 54 direkod dalam transaction yang sama;
- hanya grant yang dicipta selepas polisi disimpan menggunakan nilai baharu;
- `expires_at` grant sedia ada tidak diubah atau dipanjangkan;
- purpose isolation, session binding dan browser binding kekal berkuat kuasa.

## Lokasi UI

`Administrator → Configuration → Admin 2FA → Tempoh pengesahan Administrator`.

## Ujian

```bash
php tools/f7_admin_step_up_lifetime_schema_migrate.php --check
php tools/f7_admin_step_up_lifetime_contract.php
```

Ujian manual perlu mengesahkan pilihan baharu hanya mempengaruhi grant yang
dikeluarkan selepas verification seterusnya. Tetapkan semula kepada 15 minit
selepas eksperimen UAT jika nilai lain digunakan.
