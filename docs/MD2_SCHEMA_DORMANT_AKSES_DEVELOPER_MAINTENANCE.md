# MD2 — Schema Dormant Akses Developer Semasa Maintenance

**Tarikh:** 4 September 2026  
**Rujukan:** `ONEID-MAINTENANCE-DEVELOPER-20260904-01`  
**Fasa:** 2 — reka bentuk database  
**Status:** diluluskan owner pada 4 September 2026; belum diaplikasikan ke database UAT

## 1. Hasil Fasa 2

Fasa ini menyediakan foundation database additive tanpa mengubah `user_tbl`:

- `maintenance_developer_access_grants` menyimpan lifecycle grant;
- `maintenance_developer_access_history` menyimpan audit perubahan grant;
- migration `UP` dan `DOWN` tersedia;
- feature dan schema apply flag default `false`; dan
- isolated rehearsal membuktikan forward, constraint dan rollback.

Tiada endpoint, UI, maintenance bypass atau activation diperkenalkan.

## 2. Keputusan schema

Grant hanya boleh dimiliki akaun dalam `user_tbl`. Semakan bahawa subject ialah
akaun aktif `u_type=0` kekal tanggungjawab transaction service Fasa 3 kerana
cross-table role/status tidak sesuai dikuatkuasakan menggunakan `CHECK`.

Hanya satu row berstatus `ACTIVE` dibenarkan bagi setiap user melalui generated
column `active_user_slot`. Status efektif `SCHEDULED` diperoleh daripada
`valid_from`; row kekal berstatus `ACTIVE`. Service Fasa 3 mesti menandakan row
sebagai `EXPIRED` sebelum mencipta grant pengganti.

Semua grant mesti mempunyai `valid_until`. Window maksimum dikunci kepada 30
hari. Perubahan masa depan kepada had ini memerlukan migration dan kelulusan
polisi baharu.

`configuration_version` menyediakan optimistic concurrency. Grant bermula pada
versi 1; revoke/expiry menaikkan versi tepat satu dan menghasilkan satu history
row dengan pasangan versi yang sepadan.

History tidak menyimpan password, OTP, TOTP, secret, cookie, session ID atau
token. Login/session events runtime akan menggunakan audit sedia ada pada fasa
kemudian dan tidak dimasukkan dalam jadual lifecycle konfigurasi ini.

## 3. Kawalan dormant

Nilai committed berikut semuanya fail-closed:

```text
ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED=false
ONEID_MAINTENANCE_DEVELOPER_SCHEMA_APPLY_ENABLED=false
```

Schema runner memerlukan `--apply`, environment `staging`, feature kekal
disabled, approval schema, rujukan change/backup dan window ISO-8601 maksimum
dua jam. Fasa ini tidak memberi approval untuk menjalankan runner terhadap UAT.

## 4. Migration dan rollback

- Forward: `docs/migrations/20260904_maintenance_developer_access_up.sql`
- Rollback: `docs/migrations/20260904_maintenance_developer_access_down.sql`
- Controlled runner: `tools/maintenance_developer_schema_migrate.php`
- Isolated rehearsal: `tools/maintenance_developer_phase2_isolated_rehearsal.php`

Rollback menjatuhkan history dahulu, kemudian grants. Ia destructive terhadap
audit/grant yang sudah tersimpan dan hanya boleh digunakan sebelum activation
atau selepas retention/export diluluskan.

## 5. Acceptance gate Fasa 2

- [x] Schema additive dan tidak mengubah `user_tbl`.
- [x] Foreign key subject, approver, revoker dan actor tersedia.
- [x] Hanya satu active grant per user.
- [x] Tempoh grant wajib, positif dan maksimum 30 hari.
- [x] Status dan revocation state dikawal constraint.
- [x] Optimistic configuration version tersedia.
- [x] Audit history mempunyai correlation ID unik.
- [x] Feature dan schema apply default OFF.
- [x] Forward dan rollback lulus di database isolated.
- [x] Isolated database dibuang selepas rehearsal.
- [x] Database/schema/data UAT tidak dimutasi.

Owner perlu mengesahkan Fasa 2 sebelum domain persistence/service Fasa 3
dibangunkan atau migration dipertimbangkan untuk UAT.

**Keputusan owner:** Fasa 2 disahkan melalui arahan memulakan Fasa 3 pada
4 September 2026. Kelulusan ini membenarkan domain layer dormant sahaja dan
tidak membenarkan migration UAT atau activation feature.
