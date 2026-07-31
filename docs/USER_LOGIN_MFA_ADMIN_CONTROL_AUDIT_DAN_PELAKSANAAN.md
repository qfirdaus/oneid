# Audit dan Pelaksanaan Kawalan Administrator User Login MFA

**Tarikh:** 31 Julai 2026
**Status:** PRIORITI 1–2 UAT PASS / CLOSED / PRIORITI 3–6 MENUNGGU KELULUSAN OWNER

## 1. Objektif

Dokumen ini merekodkan audit kawalan User Login MFA dan urutan pelaksanaan
yang diluluskan. Setiap prioriti mempunyai gate kelulusan berasingan. Kerja
prioriti berikutnya tidak boleh bermula hanya kerana prioriti sebelumnya siap.

## 2. Keputusan audit

Tiga lapisan polisi boleh dilaksanakan:

1. master global `ON/OFF`;
2. enforcement mengikut kategori authoritative; dan
3. pengecualian individu sementara.

Kawalan tidak boleh menjadi bypass bebas. Administrator, Admin Step-Up,
optimistic concurrency, reason/reference, typed confirmation, audit atomik dan
fail-closed validation wajib digunakan untuk mutation polisi.

## 3. Urutan pelaksanaan dan gate

| Prioriti | Skop | Status |
|---|---|---|
| 1 | Admin Configuration global User MFA `ON/OFF` | UAT PASS / CLOSED |
| 2 | Polisi kategori staf/pelajar daripada sumber authoritative | UAT PASS / CLOSED |
| 3 | Pengecualian individu sementara dengan auto-expiry | Menunggu kelulusan |
| 4 | Administrator tidak boleh dikecualikan | Menunggu kelulusan |
| 5 | Monitoring, history dan pending-transaction cancellation lanjutan | Menunggu kelulusan |
| 6 | Pilot/observation 72 jam dan closure evidence | Menunggu kelulusan |

Walaupun sebahagian guardrail Prioriti 4 atau 5 diperlukan untuk menjadikan
Prioriti 1 selamat, ia hanya dipasang pada boundary master switch dan tidak
dianggap sebagai pelaksanaan penuh prioriti berkenaan.

## 4. Prioriti 1 — master global

### 4.1 Tingkah laku

- `ON` mengembalikan mode database kepada mode maksimum yang diluluskan oleh
  private runtime.
- `OFF` menghentikan challenge baharu bagi password login.
- `OFF` merevoke pending login transaction dan challenge yang belum terminal.
- Enrollment/faktor Microsoft Authenticator pengguna tidak dipadam.
- Sesi pengguna yang telah authenticated tidak dilog keluar semata-mata kerana
  master shutdown.
- MyDigital ID kekal mengikut keputusan `PASSWORD_ONLY` sedia ada.

### 4.2 Pemisahan kuasa

- private runtime ialah authorization ceiling, bukan suis operasi harian;
- database ialah effective operational policy;
- database boleh berada pada `OFF` walaupun runtime membenarkan mode lebih
  tinggi;
- UI tidak boleh mengaktifkan mode melebihi runtime;
- runtime `OFF` atau activation tidak diluluskan menyebabkan UI hanya boleh
  kekal `OFF`.

### 4.3 Acceptance

- hanya Administrator aktif;
- fresh Admin Step-Up `SECURITY_CONFIGURATION_CHANGE`;
- CSRF;
- configuration version;
- change reason 10–500 aksara;
- change reference 8–100 aksara;
- typed confirmation tepat;
- preview impak pending transaction/challenge;
- update polisi, revocation pending, history dan syslog dalam satu transaksi;
- faktor pengguna tidak berubah;
- BM/English dan SweetAlert confirmation;
- regression password `OFF` dan restore mode lulus.

## 5. Pelaksanaan Prioriti 2 dan reka bentuk Prioriti 3–6

### Prioriti 2

Kategori mesti datang daripada source/provenance authoritative. Label pilot
manual tidak boleh menjadi sumber polisi produksi. Akaun `UNKNOWN` masuk
reconciliation dan tidak menerima pengecualian senyap.

Keputusan pelaksanaan:

- `STAFF` datang daripada `external_source.source_family='staff'`;
- `STUDENT` datang daripada `external_source.source_family='student'`;
- hanya membership `user_external_identity.source_active=1` digunakan;
- default kedua-dua kategori ialah enabled supaya migration tidak mengubah
  login semasa;
- dalam `PILOT_ENFORCED`, category switch hanya menapis akaun pilot;
- dalam `ENFORCED`, category switch menentukan kategori yang dicabar;
- `UNKNOWN` atau `AMBIGUOUS` kekal enforced secara fail-safe;
- perubahan kategori memerlukan Admin Step-Up, reason, reference, typed
  confirmation, optimistic concurrency dan audit atomik; dan
- akaun/faktor/sesi pengguna tidak dipadam apabila kategori dimatikan.

Snapshot provenance sebelum pelaksanaan:

| Source | Family | Active membership |
|---|---|---:|
| `STAFF_HR` | staff | 1,062 |
| `STUDENT_UG` | student | 5,421 |
| `STUDENT_ODL_PG` | student | 71 |

Cross-family ambiguous active user: `0`.

Bukti pelaksanaan 30 Julai 2026:

- migration reference: `ONEID-USER-2FA-P2-20260730`;
- jadual polisi dan history berjaya dipasang pada shared database;
- polisi awal `STAFF=ON` dan `STUDENT=ON`, masing-masing version `1`;
- bacaan authoritative selepas migration: `STAFF=1,062` dan
  `STUDENT=5,492`;
- contract Prioriti 2: `11/11 PASS`;
- contract global Prioriti 1: `10/10 PASS`;
- regresi U0–U8: `PASS`.

Bukti UAT Administrator 31 Julai 2026:

1. kategori `STAFF` dimatikan melalui `Category Scope`;
2. akaun staf `0530-09` berjaya login tanpa faktor kedua;
3. kategori `STAFF` diaktifkan semula;
4. akaun staf `0530-09` kembali dicabar dengan pilihan OTP e-mel atau
   Microsoft Authenticator; dan
5. enrollment Microsoft Authenticator sedia ada kekal dan boleh digunakan.

Audit database shared mengesahkan transaksi berpasangan:

| Versi STAFF | Perubahan | Reference | Keputusan |
|---:|---|---|---|
| 2 | `ON` → `OFF` | `ONEID-USER-2FA-DISABLE-STAFF-20260731` | PASS |
| 3 | `OFF` → `ON` | `ONEID-USER-2FA-ENABLE-STAFF-20260731` | PASS |

Keadaan akhir ialah `STAFF=ON` version `3` dan `STUDENT=ON` version `1`.
Prioriti 2 ditutup sebagai `UAT PASS / CLOSED`.

### Prioriti 3

Pengecualian individu mesti sementara, mempunyai ticket/reference, sebab,
mula/luput, approver, compensating control dan auto-expiry. Pengecualian kekal
tidak dibenarkan.

### Prioriti 4

Akaun Administrator tidak boleh menerima User MFA exemption. Admin MFA kekal
boundary berasingan.

### Prioriti 5

Dashboard, alert, history, threshold dan reconciliation untuk OFF/category/
exemption/pending cancellation.

### Prioriti 6

Controlled pilot, minimum observation 72 jam, zero unresolved Critical/High,
owner sign-off dan closure/rollback evidence.

## 6. Stop condition

Selepas Prioriti 2 ditutup, pembangunan berhenti. Prioriti 3 hanya boleh
bermula selepas arahan kelulusan owner yang jelas.

## 7. Evidence penutupan Prioriti 1

Owner melaporkan browser UAT berjaya pada staging bagi kedua-dua arah polisi:

1. Administrator mematikan User 2FA melalui Admin Configuration, reason,
   reference, typed confirmation, SweetAlert dan Admin Step-Up.
2. Login password pengguna selepas shutdown tidak meminta OTP e-mel atau
   Microsoft Authenticator.
3. Administrator menghidupkan semula User 2FA melalui kawalan yang sama.
4. Login pengguna pilot kembali dicabar oleh User 2FA.

Snapshot database shared selepas UAT:

| Versi | Perubahan | Reference | Keputusan |
|---:|---|---|---|
| 4 | `PILOT_ENFORCED` → `OFF` | `ONEID-USER-MFA-DISABLE-20260730` | PASS |
| 5 | `OFF` → `PILOT_ENFORCED` | `ONEID-USER-MFA-ENABLE-20260730` | PASS |

Keadaan akhir:

- policy mode `PILOT_ENFORCED`;
- e-mel OTP enabled;
- Microsoft Authenticator enabled;
- configuration version `5`;
- enrollment Authenticator pengguna dipelihara; dan
- Prioriti 1 ditutup pada ketika itu tanpa memberikan authorization automatik
  kepada Prioriti 2; kelulusan Prioriti 2 diberikan secara berasingan kemudian.
