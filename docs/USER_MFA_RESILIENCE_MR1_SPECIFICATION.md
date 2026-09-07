# MR1 — Spesifikasi Pemisahan User MFA dan Ketahanan Akses Kritikal

**Tarikh:** 7 September 2026
**Fasa:** 1 — spesifikasi, baseline dan acceptance contract
**Status:** APPROVED / CLOSED FOR PHASE 1
**Mutation:** tiada perubahan kod, schema, database atau runtime

**Pengesahan owner:** 7 September 2026 — spesifikasi MR1 Fasa 1 disahkan dan
penyediaan schema serta migration Fasa 2 dibenarkan.

## 1. Objektif

MR1 memastikan penurunan atau pemberhentian User Login MFA tidak menutup laluan
akses kritikal. Tiga boundary keselamatan mesti beroperasi secara bebas:

1. **User Login MFA** melindungi login password pengguna biasa;
2. **Maintenance MFA** melindungi login Administrator dan developer ketika
   Maintenance Mode; dan
3. **Admin Step-Up** melindungi tindakan pentadbiran sensitif selepas sesi
   Administrator diwujudkan.

Operasi harian mesti dibuat melalui UI Administrator. Private runtime kekal
sebagai deployment ceiling dan emergency kill switch, bukan suis operasi harian.

## 2. Baseline disahkan

Audit read-only pada staging, release `2.11.1` commit `aedd61f`, mengesahkan:

- runtime dan database User MFA berada pada `ENFORCED`;
- e-mel OTP dan TOTP aktif;
- 18 faktor TOTP aktif;
- 69 transaksi `PENDING/VERIFIED` dan 7 challenge belum terminal ketika snapshot;
- master OFF semasa merevoke pending transaction/challenge tetapi memelihara
  enrollment faktor dan sesi yang telah authenticated;
- self-service User MFA ditolak apabila database policy `OFF`;
- developer maintenance login menolak polisi User MFA `OFF`; dan
- administrator maintenance login juga memerlukan polisi User MFA enforced.

Kebergantungan dua login maintenance kepada master User MFA ialah jurang utama
yang hendak ditutup oleh MR1.

## 3. Istilah dan mode canonical

Nama sedia ada dikekalkan bagi mengurangkan risiko compatibility:

| Mode | Semantik |
|---|---|
| `ENFORCED` | User MFA diwajibkan mengikut category policy dan exemption sah. |
| `PILOT_ENFORCED` | User MFA diwajibkan hanya kepada pilot yang layak. |
| `ENROLLMENT` | Password login tidak dicabar; self-service enrollment dan pengurusan faktor kekal tersedia. |
| `EMERGENCY_BYPASS` | Password-only sementara, mempunyai mula/tamat dan auto-restore wajib. |
| `OFF` | User MFA enforcement dan self-service dihentikan sepenuhnya; emergency/kill-state sahaja. |

Label UI boleh menggunakan “Enrollment only” dan “Emergency bypass”, tetapi
nilai database/API mesti menggunakan canonical value di atas.

`EMERGENCY_BYPASS` tidak boleh menjadi keadaan tanpa tarikh tamat. `OFF` bukan
pengganti bypass operasi terancang.

## 4. Matriks tingkah laku

| Keupayaan | ENFORCED | PILOT_ENFORCED | ENROLLMENT | EMERGENCY_BYPASS | OFF |
|---|---:|---:|---:|---:|---:|
| MFA login password pengguna | Ya, mengikut polisi | Pilot sahaja | Tidak | Tidak | Tidak |
| Self-service Authenticator | Ya | Pilot/kelayakan | Ya | Ya | Tidak |
| Faktor/enrollment sedia ada dipelihara | Ya | Ya | Ya | Ya | Ya |
| Category/pilot/exemption disimpan | Ya | Ya | Ya | Ya | Ya |
| Password recovery OTP | Tidak terjejas | Tidak terjejas | Tidak terjejas | Tidak terjejas | Tidak terjejas |
| MyDigital ID (`PASSWORD_ONLY`) | Tidak dicabar | Tidak dicabar | Tidak dicabar | Tidak dicabar | Tidak dicabar |
| Maintenance MFA | Wajib dan bebas | Wajib dan bebas | Wajib dan bebas | Wajib dan bebas | Wajib dan bebas |
| Admin Step-Up | Wajib dan bebas | Wajib dan bebas | Wajib dan bebas | Wajib dan bebas | Wajib dan bebas |

## 5. Polisi Maintenance MFA berasingan

Maintenance MFA tidak boleh membaca `user_login_mfa_policy.policy_mode` untuk
menentukan sama ada faktor kedua diperlukan. Ia mempunyai polisi sendiri:

- status operational `ENFORCED` atau `DISABLED`;
- production default `ENFORCED`;
- faktor minimum e-mel OTP, dengan TOTP jika tersedia;
- developer menggunakan identiti `u_type=0` dan grant maintenance sah;
- Administrator menggunakan akaun Administrator aktif;
- challenge mesti selesai sebelum token/session finalization;
- maintenance dan grant disemak semula sebelum dan selepas finalization;
- `DISABLED` hanya emergency kill switch berasingan dan fail closed, bukan
  kesan sampingan perubahan User MFA.

Jika polisi Maintenance MFA atau schema tidak boleh dibaca, maintenance login
ditolak. Login biasa tidak boleh memperoleh keistimewaan maintenance.

## 6. Admin Step-Up invariant

Admin Step-Up kekal boundary berasingan dan wajib untuk sekurang-kurangnya:

- perubahan User MFA atau Maintenance MFA;
- emergency bypass, restore dan immediate revoke;
- mengaktifkan/menamatkan Maintenance Mode;
- grant/revoke Developer Maintenance Access;
- ACL, reset MFA dan session revoke; dan
- approval maker-checker.

Perubahan User MFA tidak boleh mencipta, memanjangkan atau membatalkan grant
Admin Step-Up sedia ada. Private runtime Admin Step-Up kekal fail closed.

## 7. Transition challenge

Admin mesti memilih satu strategi ketika menurunkan enforcement:

### Grace period

- default disyorkan: 5 minit;
- transaksi/challenge yang telah wujud boleh diselesaikan sehingga deadline;
- challenge baharu mengikut mode baharu;
- selepas deadline, worker merevoke baki secara idempotent.

### Immediate revoke

- semua transaksi `PENDING/VERIFIED` dan challenge belum terminal direvoke
  secara atomik;
- pengguna menerima mesej polisi berubah dan diarahkan login semula;
- wajib untuk incident response jika dipilih secara eksplisit.

Sesi yang telah authenticated tidak direvoke secara automatik. Session revoke
ialah tindakan berasingan yang mesti dipreview dan diluluskan.

## 8. Emergency bypass

Kontrak minimum:

- tempoh pilihan: 30 minit, 1, 2, 4 atau 8 jam;
- had production: 8 jam;
- `starts_at`, `expires_at`, previous mode, reason, change reference, actor dan
  correlation ID wajib;
- fresh Admin Step-Up wajib;
- production memerlukan maker-checker apabila tempoh melebihi 2 jam;
- requester tidak boleh meluluskan permintaan sendiri;
- auto-restore mengembalikan exact previous mode dan configuration version
  baharu;
- expiry berdasarkan database time, bukan browser;
- kegagalan worker tidak memanjangkan bypass secara logik: pembacaan polisi
  mesti menganggap bypass tamat apabila `expires_at <= NOW()`;
- restore manual dibenarkan lebih awal dengan Admin Step-Up; dan
- bypass baharu tidak boleh menindih bypass aktif tanpa closure atau emergency
  override yang diaudit.

Staging boleh menggunakan requester dan approver sama untuk UAT yang jelas
dilabel, tetapi production mesti menguatkuasakan separation of duties di server.

## 9. Preview impak dan UI

Sebelum perubahan, UI mesti menunjukkan:

- mode semasa, mode sasaran dan mode auto-restore;
- bilangan faktor aktif;
- pending transaction dan challenge;
- sesi pengguna aktif sebagai maklumat sahaja;
- category/pilot/exemption aktif;
- status Maintenance Mode dan polisi Maintenance MFA;
- developer grant aktif/berjadual;
- strategi grace/immediate;
- masa mula/tamat dalam zon `Asia/Kuala_Lumpur`; dan
- siapa requester/approver bagi production.

Input admin dipermudah melalui pilihan tempoh dan sebab standard, cadangan
change reference serta ruang “Other reason”. Reason 10–500 aksara, reference
8–100 aksara, optimistic versioning, CSRF dan typed confirmation kekal wajib.

Banner persistent muncul bagi `ENROLLMENT`, `EMERGENCY_BYPASS` atau `OFF` dan
memaparkan actor, sebab, reference, masa tamat/restore serta status Maintenance
MFA. `OFF` menggunakan severity critical; mode sementara menggunakan warning.

## 10. Notifikasi

Notifikasi menggunakan standard e-mel OneID semasa dan routing persekitaran
sedia ada:

- permintaan approval;
- approved/rejected;
- mode activated;
- amaran 30 dan 10 minit sebelum tamat;
- auto-restore/manual restore;
- worker retry/failure; dan
- perubahan Maintenance MFA.

Penerima minimum ialah requester, approver dan pentadbir keselamatan yang
ditetapkan. Pengguna akhir hanya dimaklumkan jika polisi komunikasi perubahan
memerlukannya; OTP/recovery routing tidak boleh terjejas.

## 11. Monitoring dan audit

Setiap mutation mesti merekod before/resulting policy, actor public ID, reason,
reference, approval, time window, strategy, impact planned/executed, correlation
ID dan IP tervalidasi dalam transaksi yang konsisten.

Semasa mode lemah, dashboard memantau sekurang-kurangnya login gagal berulang,
IP/peranti baharu, akaun berprivilege, perubahan ACL dan sesi serentak luar
biasa. Audit tidak boleh mengandungi password, OTP, TOTP secret, token, cookie,
NRIC atau e-mel penuh.

## 12. Authorization dan maker-checker

- Semua Administrator aktif boleh melihat status dan preview.
- Mutation memerlukan role/capability keselamatan yang ditentukan server-side.
- Sehingga capability granular tersedia, existing Administrator role boleh
  meminta perubahan tetapi production bypass melebihi 2 jam memerlukan
  approver Administrator kedua.
- Requester dan approver ditentukan daripada authenticated session, bukan input
  browser.
- Approval luput apabila payload, window atau configuration version berubah.
- Emergency override mesti mempunyai purpose khusus, confirmation lebih kuat
  dan audit berasingan.

## 13. Runtime dan database authority

Private runtime menentukan feature availability, schema readiness authorization,
maximum allowed mode dan kill switch. Database menentukan mode operasi, window,
approval dan restore state.

UI tidak memerlukan edit runtime bagi operasi normal. Runtime tidak boleh
menghidupkan mode database secara automatik. Jika database meminta mode melebihi
runtime ceiling, sistem fail closed dan menghasilkan alert tanpa fallback
senyap.

## 14. Rollback contract

Setiap fasa seterusnya mesti menyediakan rollback yang:

- memulihkan polisi User MFA terakhir yang diketahui baik;
- mengekalkan faktor TOTP dan audit history;
- tidak melemahkan Maintenance MFA atau Admin Step-Up;
- tidak menghidupkan semula challenge terminal;
- selamat diulang; dan
- mempunyai preview serta verification selepas rollback.

Rollback schema hanya dibenarkan selepas tiada rekod operational aktif yang
bergantung padanya dan bukti eksport/audit disimpan.

## 15. Acceptance criteria keseluruhan

MR1 hanya dianggap lengkap apabila semua perkara berikut lulus:

1. User MFA boleh bertukar antara semua mode melalui UI dengan authorization.
2. Developer dan Administrator maintenance login kekal MFA apabila User MFA
   `ENROLLMENT`, `EMERGENCY_BYPASS` atau `OFF`.
3. Admin Step-Up kekal wajib bagi semua mutation sensitif.
4. `ENROLLMENT` dan `EMERGENCY_BYPASS` membenarkan self-service faktor;
   `OFF` tidak.
5. Faktor, category, pilot dan exemption dipelihara merentas transition.
6. Grace dan immediate revoke mempunyai hasil deterministik serta mesej jelas.
7. Bypass tamat secara efektif walaupun worker lewat dan restore idempotent.
8. Maker-checker production menolak self-approval dan stale approval.
9. Banner, countdown, audit, alert dan e-mel mempunyai parity BM/English.
10. Password recovery, MyDigital ID, SSO, ACL, session lifecycle dan routing OTP
    tiada regression.
11. Tiada token/session maintenance diwujudkan sebelum MFA berjaya.
12. UAT, rollback drill dan controlled production activation disahkan owner.

## 16. Stop conditions

Deployment atau activation mesti dihentikan jika berlaku:

- Maintenance MFA boleh dipintas melalui User MFA mode;
- Admin Step-Up boleh dipintas atau purpose tidak terikat;
- token/session diwujudkan sebelum faktor wajib selesai;
- bypass boleh hidup selepas expiry;
- requester boleh self-approve perubahan yang memerlukan maker-checker;
- faktor pengguna terpadam sewaktu transition;
- audit mutation gagal tetapi perubahan committed;
- OTP/secret/token/NRIC terdedah; atau
- rollback tidak dapat memulihkan polisi dengan deterministik.

## 17. Boundary Fasa 1 dan gate Fasa 2

Fasa 1 hanya menghasilkan spesifikasi ini. Ia tidak membenarkan migration,
runtime edit, endpoint, worker, UI atau penghantaran e-mel ujian.

Fasa 2 hanya boleh bermula selepas owner mengesahkan:

- matriks mode;
- Maintenance MFA kekal enforced dan bebas;
- grace default 5 minit;
- bypass maksimum 8 jam;
- maker-checker production bagi tempoh melebihi 2 jam;
- `OFF` sebagai emergency/kill-state; dan
- acceptance serta stop conditions di atas.

Selepas pengesahan, Fasa 2 perlu menghasilkan ERD/data dictionary, migration
`up/down`, schema contract, zero-mutation preflight dan rollback checklist.
