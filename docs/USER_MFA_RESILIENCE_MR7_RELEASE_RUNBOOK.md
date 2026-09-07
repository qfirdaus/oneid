# MR7 — Hardening, Regression dan Release Runbook

**Tarikh:** 8 September 2026
**Status:** IMPLEMENTED / STAGING VERIFIED / RELEASE 2.12.0 PREPARED / PRODUCTION NOT AUTHORIZED
**Release:** OneID `2.12.0`

## Skop dan hasil hardening

MR7 menutup Fasa 1–6 dengan kawalan berikut:

- User MFA, Maintenance MFA dan Admin Step-Up kekal sebagai tiga boundary bebas;
- Emergency Bypass hanya boleh dipadankan dengan request `ACTIVE`, policy version
  dan **environment aktif yang tepat**;
- environment kosong/tidak dikenali tidak boleh mengaktifkan bypass;
- manual restore terikat kepada environment, request, typed confirmation dan
  Admin Step-Up `SECURITY_CONFIGURATION_CHANGE`;
- borang manual restore disimpan sementara ketika Step-Up dan disambung sekali
  selepas kembali ke tab User MFA;
- expiry tetap fail closed secara logik walaupun worker lewat; dan
- worker mutasi kekal dormant secara default dalam repository.

Controlled staging UAT telah mengesahkan `ENROLLMENT`, pemulihan `ENFORCED`,
`EMERGENCY_BYPASS`, password-only semasa window, self-service, banner dan manual
restore kepada `ENFORCED`. Faktor pengguna tidak dipadam.

## Preflight release (read-only)

Jalankan daripada root aplikasi:

```bash
php tools/user_mfa_resilience_mr2_schema_contract.php
php tools/user_mfa_resilience_mr3_policy_separation_contract.php
php tools/user_mfa_resilience_mr4_operational_modes_contract.php
php tools/user_mfa_resilience_mr5_workflow_contract.php
php tools/user_mfa_resilience_mr6_lifecycle_contract.php
php tools/user_mfa_resilience_mr7_hardening_contract.php
php tools/user_mfa_resilience_mr7_readiness.php --release
php tools/user_mfa_lifecycle_worker.php --check
```

`--release` memerlukan baseline DB `ENFORCED`, Maintenance MFA `ENFORCED`, tiada
request transition terbuka dan runtime ceiling yang sesuai. Ia tidak membuat
mutation. `--activation` menambah syarat worker lifecycle telah diaktifkan.

## Maklumat perubahan yang perlu disahkan

```text
Change reference:
Masa mula (Asia/Kuala_Lumpur):
Masa tamat (Asia/Kuala_Lumpur):
Commit release:
Backup aplikasi production: selesai / belum
Backup database oneiddb_v2: selesai / belum
Restore drill backup database: lulus / gagal
Administrator requester:
Administrator approver berbeza:
```

## Backup wajib

Sebelum migration atau pull production:

1. hasilkan arkib aplikasi dengan timestamp dan checksum SHA-256;
2. dump `oneiddb_v2` dengan routines, triggers dan events;
3. sahkan dump bukan kosong dan checksum direkod;
4. restore dump kepada database sementara;
5. rekonsiliasi row count jadual User MFA, faktor, request, approval, transition,
   Maintenance MFA dan syslog; dan
6. simpan commit/tag rollback sebelum pull.

Tiada migration atau deployment dibenarkan jika restore drill belum lulus.

## Urutan deployment production

1. Aktifkan Maintenance Mode mengikut change window yang diluluskan.
2. Sahkan working tree production bersih dan commit sumber tepat.
3. Jalankan schema contract, kemudian migration MR2 `up` pada `oneiddb_v2` sekali
   sahaja. Jangan gunakan migrator staging yang mengehadkan target kepada
   `oneiddb`.
4. Sahkan lima jadual MR2 wujud dan polisi User MFA asal tidak berubah.
5. Pull release commit yang diluluskan.
6. Kekalkan worker `false`; jalankan dormant verification dan `--release`.
7. Pasang jadual worker sekurang-kurangnya sekali seminit dengan lock host-level,
   kemudian set private runtime worker kepada `true`.
8. Jalankan `--activation`; keputusan mesti semua `PASS`.
9. Uji ENFORCED login, User Security, Administrator Maintenance MFA dan Admin
   Step-Up tanpa melemahkan polisi.
10. Hanya selepas semua gate lulus, lakukan controlled mode test yang diluluskan.

Contoh cron (sesuaikan user PHP production dan pastikan hanya satu host aktif):

```cron
* * * * * flock -n /run/lock/oneid-user-mfa-lifecycle.lock php /var/www/oneid/tools/user_mfa_lifecycle_worker.php --apply >> /var/log/oneid/user-mfa-lifecycle.log 2>&1
```

## Controlled verification selepas activation

- `ENFORCED`: pengguna layak menerima challenge dan token tidak diwujudkan awal;
- `ENROLLMENT`: password-only dan self-service tersedia;
- restore `ENFORCED`: challenge kembali tanpa kehilangan faktor;
- Maintenance login masih mewajibkan Maintenance MFA;
- Admin Step-Up purpose mismatch ditolak dan purpose betul diterima;
- Emergency Bypass mempunyai maksimum 8 jam dan exact restore mode;
- worker `--check` menunjukkan keadaan munasabah; dan
- history, approval, transition run, syslog dan e-mel tidak mendedahkan rahsia.

## Rollback

### Code-only sebelum mutation polisi

Pulihkan tag/commit pra-release dan pastikan private runtime worker `false`.
Schema additive boleh dikekalkan dormant.

### Semasa mode lemah aktif

Jangan rollback code dahulu. Gunakan UI `Restore MFA now`, lengkapkan Admin
Step-Up dan sahkan DB kembali `ENFORCED`. Selepas tiada request terbuka, matikan
worker dan barulah rollback code.

### Schema rollback

Migration `down` hanya boleh dijalankan apabila:

- polisi User MFA ialah mode legacy yang serasi, bukan `EMERGENCY_BYPASS`;
- tiada request `PENDING_APPROVAL`, `APPROVED` atau `ACTIVE`;
- semua bukti audit telah dieksport; dan
- backup serta restore drill masih sah.

Schema rollback tidak memadam atau mengubah faktor TOTP pengguna.

## Stop conditions

Hentikan deployment/activation jika mana-mana perkara berlaku:

- readiness atau contract gagal;
- DB target bukan `oneiddb_v2` pada production;
- backup/restore drill tidak dapat dibuktikan;
- Maintenance MFA atau Admin Step-Up boleh dipintas;
- policy/request version atau environment tidak sepadan;
- Emergency Bypass aktif tanpa worker dan monitoring;
- faktor pengguna berubah atau terpadam;
- audit mutation gagal atau mengandungi credential/OTP/secret; atau
- restore tidak mengembalikan exact previous mode.

## Gate penutupan MR7

MR7 staging boleh ditutup selepas owner menerima laporan regression. Penetapan
versi, commit/push dan production deployment memerlukan arahan serta change
window berasingan. Dokumen ini sendiri tidak memberi kebenaran production.
