# OneID v2.9.4 — Maintenance Mode dan Session History

**Tarikh:** 13 Ogos 2026  
**Skop:** Kawalan penyelenggaraan, keselamatan credential aplikasi, ketahanan sesi dan pemantauan sesi pentadbir.

## Ringkasan

- Maintenance Mode menyokong jadual bertarikh dan mod sehingga dimatikan,
  dengan akses awam dipulihkan secara automatik selepas jadual tamat.
- Halaman penyelenggaraan dwibahasa menggunakan identiti rasmi OneID dan
  menyediakan laluan log masuk khas untuk pentadbir yang dibenarkan.
- Laluan `/admin` dan `/admin/login` dikendalikan oleh aplikasi tanpa mendedahkan
  halaman direktori `403 Forbidden` daripada nginx.
- Add App dan rotation Site API Code menggunakan aliran credential yang konsisten,
  selamat dan boleh diaudit.
- Pengawal sesi pengguna tidak lagi mengulang dialog sesi tamat selepas tindakan
  pengguna, manakala identiti pendua nombor staf boleh disatukan dengan selamat.
- Active Sessions dan Session History menyediakan paparan sesi semasa dan sejarah
  tamat yang berasingan, boleh dicari, ditapis serta dipaginasi.

## Migration

Gunakan migration Session History yang telah disediakan jika belum dipasang:

```text
tools/session_history_schema_migrate.php
```

## Verification

```bash
php tools/release_metadata_contract.php
php tools/version_documentation_contract.php
php tools/maintenance_mode_contract.php
php tools/session_history_contract.php
php tools/as0_active_sessions_contract.php
git diff --check
```
