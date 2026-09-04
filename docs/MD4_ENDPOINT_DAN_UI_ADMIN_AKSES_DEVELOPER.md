# MD4 — Endpoint dan UI Admin Akses Developer Maintenance

**Tarikh:** 4 September 2026  
**Rujukan:** `ONEID-MAINTENANCE-DEVELOPER-20260904-01`  
**Fasa:** 4 — pengurusan developer oleh admin  
**Status:** diluluskan owner pada 4 September 2026; Fasa 5 dibenarkan

## 1. Hasil

Fasa ini menyediakan panel `Akses Developer` dalam konfigurasi admin untuk:

- mencari calon berdasarkan ID, nama atau identiti awam;
- menunjukkan sebab calon tidak layak;
- menetapkan waktu mula dan tamat;
- merekod sebab serta rujukan perubahan;
- mengesahkan grant menggunakan frasa terikat kepada user ID;
- menyenaraikan status efektif `SCHEDULED`, `ACTIVE`, `EXPIRED`, `REVOKED`; dan
- revoke menggunakan confirmation terikat grant ID dan optimistic version.

Panel menunjukkan kegagalan selamat apabila schema belum dipasang. Feature flag
runtime kekal OFF dan tiada perubahan dibuat kepada login atau maintenance gate.

## 2. Authorization boundary

Keempat-empat action berada pada level admin dan melalui CSRF, active SSO token,
status akaun dan admin authorization guard pusat. Search/list menggunakan
purpose `ADMIN_ACCESS`; grant/revoke menggunakan exact purpose
`SECURITY_CONFIGURATION_CHANGE`.

Signal Admin Step-Up dihantar oleh server wiring kepada domain service dan tidak
dibaca daripada POST. Input browser tidak boleh menetapkan dirinya sebagai
authorized.

## 3. Action contract

```text
admin_search_maintenance_developer_candidates
admin_list_maintenance_developer_access
admin_grant_maintenance_developer_access
admin_revoke_maintenance_developer_access
```

Semua respons JSON menggunakan `Cache-Control: no-store`. Domain error dipetakan
kepada status HTTP tanpa mendedahkan stack trace atau exception dalaman.

## 4. Mutation confirmation

Grant memerlukan teks tepat:

```text
GRANT MAINTENANCE ACCESS {USER_ID}
```

Revoke memerlukan:

```text
REVOKE MAINTENANCE ACCESS {GRANT_ID}
```

Confirmation ialah perlindungan salah klik dan bukan pengganti Admin Step-Up.

## 5. Keadaan dormant

Migration Fasa 2 belum diaplikasikan kepada UAT. Oleh itu panel dijangka
memaparkan `MAINTENANCE_ACCESS_SCHEMA_UNAVAILABLE` sehingga schema application
diluluskan secara berasingan. Ini ialah tingkah laku yang disengajakan.

Fasa ini masih belum membolehkan developer login semasa maintenance.

## 6. Verifikasi

```bash
php tools/maintenance_developer_phase4_contract.php
php tools/maintenance_developer_phase3_integration.php
php tools/maintenance_mode_contract.php
```

## 7. Gate Fasa 4

- [x] Search/list/grant/revoke berada dalam whitelist admin.
- [x] Grant/revoke memerlukan exact Security Configuration Change Step-Up.
- [x] CSRF dan active token guard digunakan.
- [x] Candidate dan list query parameterized serta bounded.
- [x] Typed confirmation terikat subject/grant.
- [x] UI dinamik meng-escape data server.
- [x] BM dan English catalogue tersedia.
- [x] Step-Up return context kembali ke tab yang betul.
- [x] Schema unavailable dipaparkan secara fail-closed.
- [x] Tiada runtime maintenance bypass atau live DB mutation.

Fasa seterusnya hanya boleh bermula selepas owner mengesahkan Fasa 4.

**Keputusan owner:** Fasa 4 diluluskan melalui arahan memulakan Fasa 5 pada
4 September 2026. Kelulusan tidak merangkumi migration atau activation UAT.
