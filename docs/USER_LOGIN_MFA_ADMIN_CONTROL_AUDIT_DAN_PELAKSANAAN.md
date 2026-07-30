# Audit dan Pelaksanaan Kawalan Administrator User Login MFA

**Tarikh:** 30 Julai 2026  
**Status:** PRIORITI 1 IMPLEMENTED / UAT BELUM DISAHKAN / PRIORITI 2–6 MENUNGGU KELULUSAN OWNER

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
| 1 | Admin Configuration global User MFA `ON/OFF` | Diluluskan |
| 2 | Polisi kategori staf/pelajar daripada sumber authoritative | Menunggu kelulusan |
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

## 5. Reka bentuk Prioriti 2–6 (belum diberi kuasa)

### Prioriti 2

Kategori mesti datang daripada source/provenance authoritative. Label pilot
manual tidak boleh menjadi sumber polisi produksi. Akaun `UNKNOWN` masuk
reconciliation dan tidak menerima pengecualian senyap.

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

Selepas Prioriti 1 siap dan diserahkan untuk UAT, pembangunan berhenti.
Prioriti 2 hanya boleh bermula selepas arahan kelulusan owner yang jelas.
