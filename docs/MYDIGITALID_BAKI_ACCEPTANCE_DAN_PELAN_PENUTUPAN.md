# MyDigital ID — Baki Acceptance dan Pelan Penutupan Audit

**Tarikh pelan:** 29 Julai 2026

**Environment:** staging (`https://oneid-uat.upnm.edu.my/`)

**Status:** `GATE A PASS / CLOSED`

**Production:** `OUT OF SCOPE / NO-GO` sehingga authorization berasingan

**Sumber status canonical:** `MYDIGITALID_STAGING_IMPLEMENTATION_CLOSEOUT.md`

## 1. Tujuan dan keputusan semasa

Dokumen ini ialah worklist canonical untuk menutup baki audit MyDigital ID.
Implementation staging, positive pilot, schema, security dan automated
regression telah lulus. Browser acceptance, regression manual, ACL/logout,
secret/reference review dan observation 72 jam kemudiannya disahkan berjaya
oleh system owner pada 29 Julai 2026.

Closure dibahagikan kepada dua gate:

1. **Gate A — Staging Acceptance Close:** skop aktif dokumen ini.
2. **Gate B — Production Readiness:** projek berasingan yang memerlukan
   authorization Business, Security, DBA, Infrastructure dan provider.

Gate A tidak memberi kebenaran production.

## 2. Peranan dan kawalan evidence

| Peranan | Tanggungjawab |
|---|---|
| System owner / acceptance lead | Menyelaras run, menyemak evidence dan memberi keputusan akhir Gate A |
| MyDigital ID test user owner | Menyediakan akaun provider yang diluluskan tanpa berkongsi credential |
| OneID application owner | Menyemak session, token, category, ACL dan audit event |
| DBA | Menyediakan fixture terkawal, query read-only dan memulihkan fixture |
| Infrastructure / Security | Menyemak Nginx safe log, provider availability dan incident evidence |
| Operations | Menetapkan threshold, channel, on-call dan observation window |

Setiap evidence mesti merekod tarikh/masa MYT, tester, environment, test ID,
keputusan, correlation/audit reference yang selamat dan disposition. Jangan
rekod NRIC, token, authorization code, state, cookie, client secret, HMAC key
atau screenshot yang memaparkan data sensitif.

## 3. Definition of Done Gate A

Gate A hanya boleh ditutup apabila:

- STG-01 hingga STG-10 mempunyai keputusan `PASS`, atau exception bertulis yang
  diluluskan bagi item yang tidak boleh dijalankan;
- STG-11 mempunyai keputusan `PASS` atau `ACCEPTED AUTOMATED COVERAGE`;
- STG-12 observation selesai tanpa unresolved severity tinggi/kritikal;
- security suite semasa lulus `24/24`;
- tiada auto-registration, profile overwrite atau authorization bypass;
- callback access log kekal bebas query/token;
- reference/secret close-out selesai;
- semua defect mempunyai disposition dan retest;
- feature flag serta rollback owner direkod; dan
- system owner menandatangani `STAGING ACCEPTANCE: PASS / CLOSED`.

## 4. Pelan pelaksanaan mengikut urutan

### Wave 0 — Preflight dan change control

| ID | Aktiviti | Owner | Evidence / exit |
|---|---|---|---|
| PRE-01 | Tetapkan change reference, tester, VPN window dan escalation contact | Acceptance lead | Rekod approval dan window |
| PRE-02 | Sahkan staging sahaja, backup tersedia dan rollback melalui `ONEID_MYDID_ENABLED=false` | Application owner / DBA | Runtime boolean sahaja; jangan paparkan secret |
| PRE-03 | Sediakan pilot aktif, akaun rejected dan fixture terkawal | Test-user owner / DBA | Reference terabstrak; tiada credential/NRIC dalam dokumen |
| PRE-04 | Jalankan security suite sebelum ujian browser | Application owner | `php tools/mydigitalid_f6_security_suite.php`; `commands=24 failures=0` |
| PRE-05 | Sahkan Nginx callback safe-log masih aktif | Infrastructure | Callback path tanpa `?`, `code=`, `state=` atau `session_state=` |

Kegagalan PRE-02, PRE-04 atau PRE-05 membatalkan window.

### Wave 1 — Rejection UX dan pertukaran akaun

#### STG-01 — Dedicated rejected-user page

- **Baki:** kod/contract F8 lulus tetapi paparan browser VPN belum direkod.
- **Langkah:** login menggunakan identity tanpa akses; sahkan redirect ke
  `/auth/mydigitalid/access-denied.php`; semak BM/English, no-store,
  no-referrer dan direct-URL fail-closed.
- **PASS:** mesej generik dipaparkan tanpa reason code, PII atau token; tiada
  session OneID dan tiada akaun/link baharu.
- **Evidence:** masa, tester, URL path sahaja, locale, audit outcome dan
  screenshot yang telah diredact.

#### STG-02 — Provider logout dan QR baharu

- **Baki:** account-switch automated `PASS`; browser chain belum diterima.
- **Langkah:** dari Access Denied klik `Cuba akaun MyDigital ID lain`; sahkan
  POST+CSRF, provider logout dan pilihan identiti/QR baharu.
- **PASS:** rejected state one-use, sesi provider lama tidak dipilih secara
  senyap dan browser kembali melalui registered redirect.
- **FAIL:** loop, auto-login identity lama, raw token/query terdedah atau GET
  boleh melaksanakan switch.

#### STG-03 — Login pilot selepas account-switch

- **Baki:** successful pilot dan account-switch telah diuji berasingan, bukan
  sebagai satu chain.
- **Langkah:** selepas STG-02, pilih pilot aktif dan lengkapkan login.
- **PASS:** dashboard dicapai, session ID diregenerate, authentication event
  berjaya dan tiada residual rejected state.

STG-01 → STG-02 → STG-03 hendaklah dijalankan dalam satu browser journey dan
menggunakan satu evidence reference berkorelasi.

### Wave 2 — Negative authorization fixtures

#### STG-04 — Inactive OneID identity

- **Prasyarat:** DBA menyediakan akaun fixture inactive yang diluluskan serta
  snapshot sebelum/selepas.
- **PASS:** provider authentication boleh selesai tetapi OneID menolak akses;
  tiada session/token/link baharu, tiada profile mutation dan audit merekod
  rejection generik.
- **Cleanup:** pulihkan fixture hanya melalui prosedur DBA yang diluluskan.

#### STG-05 — Ambiguous/duplicate NRIC identity

- **Prasyarat:** fixture duplicate terkawal, change window dan cleanup query.
- **PASS:** exact-one gate menolak padanan berganda; sistem tidak memilih akaun
  pertama, tidak mencipta link dan tidak mengubah `user_tbl`.
- **Kawalan:** jangan mencipta duplicate pada akaun sebenar atau merekod NRIC
  mentah dalam evidence.

Jika fixture live tidak diluluskan, owner boleh memberi disposition
`ACCEPTED ISOLATED COVERAGE` berdasarkan rehearsal sedia ada. Disposition mesti
menyatakan risiko yang diterima dan tidak boleh dianggap production evidence.

### Wave 3 — Password regression dan authorization parity

#### STG-06 — Password login staf

- Ulang login ID pengguna/kata laluan staf selepas MyDigital ID activation.
- **PASS:** dashboard, token/session policy dan logout password kekal normal.
- Initial smoke terdahulu ialah evidence sokongan; final acceptance tetap perlu
  direkod atau diberi disposition bertulis.

#### STG-07 — Password login pelajar tempatan

- Uji akaun pelajar tempatan aktif menggunakan laluan password.
- **PASS:** login, category Pelajar, menu dan ACL asal tidak berubah.

#### STG-08 — Pelajar antarabangsa

- Uji nombor matrik/passport melalui laluan password yang sedia ada.
- **PASS:** MyDigital ID tidak menjadi syarat untuk pengguna tanpa NRIC dan
  tiada perubahan pada authentication/authorization asal.

#### STG-09 — ACL parity selepas MyDigital ID login

- Login pilot yang sama melalui password dan MyDigital ID dalam sesi bersih.
- Banding category, application/menu entitlement dan tindakan dibenarkan.
- **PASS:** ACL sama; MyDigital ID hanya menggantikan authentication, bukan
  authority OneID.
- Evidence mesti menyimpan senarai entitlement, bukan token/cookie.

### Wave 4 — Logout dan degraded-provider UX

#### STG-10 — Local + provider logout

- Login pilot melalui MyDigital ID, logout OneID, kemudian cuba memulakan login
  baharu.
- **PASS:** token/session OneID direvoke atau dibersihkan dahulu, provider
  logout rasmi berlaku dan identity lama tidak disambung semula tanpa pilihan
  pengguna.
- Semak password-origin logout tidak terjejas.

#### STG-11 — Provider timeout/unavailable

- Automated fail-closed coverage sudah `PASS`.
- Operations/Security memilih sama ada controlled manual test selamat dilakukan
  atau menerima `ACCEPTED AUTOMATED COVERAGE`.
- Jika diuji: jangan ubah DNS/firewall bersama tanpa change approval.
- **PASS:** mesej generik, tiada partial session/link dan password login kekal
  tersedia.

### Wave 5 — Monitoring dan observation

#### STG-12 — Threshold, channel dan observation window

Sebelum observation bermula, Operations mesti merekod:

| Perkara | Keputusan wajib |
|---|---|
| Tempoh | Cadangan 72 jam selepas acceptance browser terakhir |
| Channel | Dashboard/log review dan channel alert yang diluluskan |
| On-call | Nama peranan serta escalation path |
| Critical | auth bypass, auto-registration, raw token/PII exposure, session fixation |
| High | callback/provider failure berulang, ACL mismatch, logout failure |
| Warning | rejection spike, latency/timeout meningkat, audit-write warning |
| Baseline | successful login, rejection dan error count sebelum window |

Minimum pemerhatian:

- callback/provider error dan timeout;
- successful/rejected MyDigital ID event;
- unexpected link creation atau duplicate/ambiguous rejection;
- session/logout failure;
- ACL/helpdesk incident;
- callback access-log query leakage; dan
- password-login regression.

**PASS:** observation tamat, tiada unresolved Critical/High, warning mempunyai
disposition, callback safe-log kekal lulus dan owner menandatangani closure.
Jika tiada platform alert automatik, manual review berjadual mesti dinyatakan
secara jujur; jangan menamakan manual observation sebagai automated monitoring.

## 5. Reference-folder dan secret close-out

| ID | Aktiviti | Exit criteria |
|---|---|---|
| SEC-01 | Inventori `resources/references/mydigital-id/` tanpa membuka secret ke output | Tiada runtime dependency |
| SEC-02 | Padam/arkib restricted reference mengikut arahan owner | Disposition dan owner direkod |
| SEC-03 | Semak tracked files dan Git history untuk pattern credential | Tiada secret disahkan; jangan cetak nilai |
| SEC-04 | Sahkan permission private runtime | Owner/group/mode diluluskan |
| SEC-05 | Tentukan rotation staging dan wajib rotation sebelum production | Rotation reference tanpa nilai credential |

Jangan memadam evidence atau rotate credential tanpa authorization owner.

## 6. Housekeeping contract

`tools/login_mydigitalid_logo_contract.php` ialah contract historical F0 yang
masih menjangka kad MyDigital ID tidak aktif dan mengabaikan pointer. Staging
kini sengaja aktif melalui private feature flag dan suite canonical F0–F8
`24/24` lulus. Contract ini perlu sama ada:

- dipindahkan/ditanda sebagai historical F0; atau
- dikemas kini kepada contract presentation semasa tanpa menguji activation.

Item ini diklasifikasi `TEST MAINTENANCE`, bukan bukti kegagalan runtime. Ia
mesti mempunyai disposition sebelum Gate A ditutup supaya tiada contract repo
yang diketahui gagal.

## 7. Defect, retest dan stop conditions

Hentikan acceptance dan disable feature flag jika berlaku:

- authorization bypass atau akaun inactive/ambiguous berjaya login;
- auto-registration/profile overwrite;
- token, code, state, NRIC atau secret muncul dalam UI/log;
- session fixation atau logout gagal membersihkan sesi tempatan; atau
- ACL lebih luas berbanding password login.

Setiap defect perlu ID, severity, root cause, fix reference, affected test dan
retest evidence. Critical/High tidak boleh menerima waiver Gate A tanpa review
Security dan system owner.

## 8. Rekod keputusan

Rekod keputusan owner verification:

| ID | Status | Tarikh/Masa MYT | Tester/Owner | Evidence reference | Catatan/Disposition |
|---|---|---|---|---|---|
| PRE-01–05 | PASS | 29 Julai 2026 | Firdaus / system owner | `MYDID-STG-ACCEPTANCE-20260729-01` | Preflight dan safe-log verified |
| STG-01 | PASS | 29 Julai 2026 | Firdaus / system owner | `MYDID-STG-ACCEPTANCE-20260729-01` | Access Denied verified |
| STG-02 | PASS | 29 Julai 2026 | Firdaus / system owner | `MYDID-STG-ACCEPTANCE-20260729-01` | Account-switch/provider logout verified |
| STG-03 | PASS | 29 Julai 2026 | Firdaus / system owner | `MYDID-STG-ACCEPTANCE-20260729-01` | Chained pilot login verified |
| STG-04 | PASS | 29 Julai 2026 | Firdaus / system owner | `MYDID-STG-ACCEPTANCE-20260729-01` | Inactive rejection verified |
| STG-05 | PASS | 29 Julai 2026 | Firdaus / system owner | `MYDID-STG-ACCEPTANCE-20260729-01` | Duplicate/ambiguous rejection verified |
| STG-06 | PASS | 29 Julai 2026 | Firdaus / system owner | `MYDID-STG-ACCEPTANCE-20260729-01` | Password login staf verified |
| STG-07 | PASS | 29 Julai 2026 | Firdaus / system owner | `MYDID-STG-ACCEPTANCE-20260729-01` | Password login pelajar verified |
| STG-08 | PASS | 29 Julai 2026 | Firdaus / system owner | `MYDID-STG-ACCEPTANCE-20260729-01` | International-student path verified |
| STG-09 | PASS | 29 Julai 2026 | Firdaus / system owner | `MYDID-STG-ACCEPTANCE-20260729-01` | ACL parity verified |
| STG-10 | PASS | 29 Julai 2026 | Firdaus / system owner | `MYDID-STG-ACCEPTANCE-20260729-01` | Local/provider logout verified |
| STG-11 | ACCEPTED AUTOMATED COVERAGE | 29 Julai 2026 | Firdaus / system owner | Security suite `24/24` | Fail-closed coverage accepted |
| STG-12 | PASS / CLOSED | 29 Julai 2026 | Firdaus / system owner | `MYDID-STG-ACCEPTANCE-20260729-01` | Observation 72 jam completed; no unresolved Critical/High |
| SEC-01–05 | PASS / CLOSED | 29 Julai 2026 | Firdaus / system owner | `MYDID-STG-ACCEPTANCE-20260729-01` | Secret/reference review verified |
| TEST-MAINT-01 | PASS / CLOSED | 29 Julai 2026 | OneID code owner | Updated presentation contract | Historical inactive-only assertion removed |

Exact observation start/end timestamps and any restricted screenshots/log
extracts remain in the owner-controlled operational evidence; raw secret, token
dan PII tidak disalin ke repository.

## 9. Gate B — Production Readiness (pelan berasingan)

Selepas Gate A ditutup, production masih `NO-GO`. Gate B memerlukan sekurang-
kurangnya:

1. production issuer/client/redirect/post-logout registration;
2. secret dan HMAC key production berasingan;
3. key custody, rotation dan incident procedure;
4. audit retention serta purge/archival approval;
5. backup/restore rehearsal, change window dan rollback owner;
6. production collision/baseline serta controlled pilot plan;
7. gated schema migration pada target production yang disahkan;
8. safe Nginx log sebelum callback pertama;
9. dormant regression, pilot dan observation production; dan
10. Business/Security/DBA/Infrastructure/provider GO.

Tiada evidence atau credential staging boleh dijadikan production approval.

## 10. Gate A closure decision

```text
Decision: STAGING ACCEPTANCE PASS / CLOSED
Environment: staging
Change reference: ONEID-V264-CHANGELOG-20260726-01
Evidence reference: MYDID-STG-ACCEPTANCE-20260729-01
Acceptance verification: completed
Observation window: 72 hours completed; exact timestamps retained in operational evidence
Required tests passed: STG-01 through STG-10
Accepted exceptions: STG-11 accepted automated fail-closed coverage
Open Critical/High: 0
Security suite: commands=24 failures=0
Feature flag state: enabled in staging; committed default remains disabled
Rollback owner: Firdaus / system owner
System owner: Firdaus
Approval date MYT: 29 Julai 2026
Production authorization: NO
```

Gate A ditutup berdasarkan pengesahan system owner bahawa semua acceptance
manual yang disenaraikan berfungsi dan berjaya, observation 72 jam selesai,
secret/reference file telah disemak dan tiada defect Critical atau High yang
belum selesai.

## 11. Closure statement template untuk run masa hadapan

```text
Decision: STAGING ACCEPTANCE PASS / CLOSED | REWORK
Environment: staging
Change reference:
Acceptance window:
Observation window:
Required tests passed:
Accepted exceptions:
Open Critical/High:
Security suite: commands=24 failures=0
Feature flag state:
Rollback owner:
System owner:
Approval date/time MYT:
Production authorization: NO
```
