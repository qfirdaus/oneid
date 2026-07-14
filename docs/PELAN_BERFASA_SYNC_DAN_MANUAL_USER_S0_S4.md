# Pelan Berfasa Keselamatan Sync dan Manual User — S0 hingga S4

Tarikh: 14 Julai 2026  
Owner perubahan: Pemilik sistem OneID  
Owner rollback: Pemilik sistem OneID  
Status: **AKTIF — S0–S2 SELESAI; S3 SAFETY CORE DORMANT DIIMPLEMENTASIKAN; S4 BELUM**

## 1. Objektif

Pelan ini mengukuhkan dua operasi pentadbiran:

1. **Sync with external data** — full snapshot yang boleh menambah, mengemas
   kini, menyahaktif dan mengaktifkan semula akaun.
2. **Manual Add User** — penciptaan satu akaun oleh pentadbir.

Kedua-duanya berkongsi `user_tbl` dan saling mempengaruhi. Akaun manual yang
tiada dalam external snapshot kini boleh dianggap hilang lalu dinyahaktif oleh
full sync. Oleh itu kedua-dua flow tidak boleh dibaiki secara berasingan tanpa
polisi provenance yang jelas.

## 2. Ringkasan Fasa

| Fasa | Fokus | Risiko | Mutation production |
| --- | --- | --- | --- |
| S0 | Baseline, characterization dan keputusan polisi | Sangat rendah | Tiada |
| S1 | Hardening Manual Add User dan provenance | Rendah–sederhana | Ya, terkawal |
| S2 | Preview/dry-run external sync | Sederhana | Preview mesti zero mutation |
| S3 | Transaction, lock, threshold dan operational safety | Sederhana–tinggi | Tiada semasa dormant implementation |
| S4 | Feature-flag cutover orchestrator dan verification | Tinggi | Ya, terkawal |

## 3. S0 — Baseline dan Characterization

Skop:

- petakan UI → action → controller → persistence bagi kedua-dua flow;
- kunci authorization, CSRF, password dan audit behavior semasa;
- buktikan full-sync behavior menggunakan fixture in-memory;
- buktikan pure dry-run dormant kekal zero mutation;
- rekod kelemahan sebagai contract supaya perubahan fasa seterusnya disengajakan;
- tiada live sync, POST production, database query atau tambah pengguna.

Artefak S0:

- `docs/S0_BASELINE_DAN_CHARACTERIZATION_SYNC_MANUAL_USER.md`;
- `docs/S0_SYNC_MANUAL_USER_BASELINE_REGISTER.tsv`;
- `tests/characterization/s0_user_provisioning_contracts.php`;
- `tools/s0_user_provisioning_characterization.php`;
- script `npm run check:user-provisioning` dalam `package.json`.

Exit gate S0:

- semua contract static/in-memory lulus;
- checksum runtime tidak berubah;
- polisi akaun manual direkod untuk S1;
- risiko live sync difahami dan tiada sync dijalankan semasa S0.

## 4. S1 — Manual Add User Hardening

Perubahan dirancang:

- validation server-side untuk ID, nama, kategori, panjang dan format;
- email sah diwajibkan apabila onboarding menggunakan OTP;
- transaction atomik untuk duplicate check/insert, password flag dan audit;
- tangani duplicate-key race dengan respons selamat;
- isi error callback UI dan guna correlation ID;
- selaraskan `u_changes_hash` dengan canonical transformer;
- tambah provenance seperti `account_source=manual|external`;
- tambah polisi `sync_protected` untuk akaun manual;
- output encoding bagi data manual yang dipaparkan semula;
- characterization dan integration test tanpa mendedahkan PII.

Keputusan polisi awal: akaun `manual` hendaklah dilindungi daripada auto-
deactivation secara default. Sebarang pengecualian perlu tindakan pentadbir yang
jelas dan diaudit.

Exit gate S1:

- invalid request ditolak server-side;
- insert/audit tidak boleh separuh berjaya;
- onboarding mempunyai email sah;
- provenance boleh dibezakan;
- manual account tidak masuk deactivation path tanpa polisi eksplisit.

## 5. S2 — External Sync Preview dan Dry-run

R5.2D5–D8 telah menyediakan `SyncPlanner`, zero-mutation dry-run, production
adapter dan orchestrator secara dormant. S2 menggunakan semula komponen itu,
bukan membina planner kedua.

Perubahan dirancang:

- bina endpoint preview admin-only yang tidak memanggil mutation method;
- ambil satu snapshot external dan satu snapshot internal;
- paparkan count `NEW`, `UPDATE`, `DEACTIVATE`, `REACTIVATE`;
- paparkan identifier yang dimask/hashed, bukan raw PII;
- hasilkan deterministic plan hash dan expiry;
- warning untuk snapshot kosong, menyusut atau tidak lengkap;
- explicit confirmation yang terikat pada plan hash;
- bukti automated bahawa preview ialah zero mutation.

Exit gate S2:

- preview dan apply tidak bercampur;
- zero mutation dibuktikan;
- parity planner dengan legacy ialah sifar mismatch;
- pentadbir melihat blast radius sebelum sebarang write.

Kemaskini S2 pada 14 Julai 2026: endpoint/UI preview read-only, plan hash,
expiry, anomaly threshold, masking dan protection akaun manual telah
diimplementasikan. Automated zero-mutation contract dan live preview browser
read-only disahkan lulus oleh owner. Rujuk
`docs/S2_EXTERNAL_SYNC_PREVIEW_DAN_DRY_RUN.md`.

## 6. S3 — External Sync Operational Safety

Safety core yang telah diimplementasikan secara dormant:

- server-side connection-scoped single-run advisory lock;
- transaction bermula hanya selepas snapshot/preprocessing selamat dan semua
  kegagalan selepas `BEGIN` ditutup dengan rollback;
- ODBC/API failure menggunakan exception terkawal, bukan `echo/exit`;
- threshold hard-stop untuk mass deactivation dan perubahan luar biasa;
- provenance/sync protection dipatuhi oleh planner dan writer;
- exact reconciliation bagi planned, executed dan durable audit count sebelum
  commit.

Gate operational yang kekal untuk S4 sebelum live run:

- job/correlation ID dan structured monitoring;
- response generik kepada browser; detail teknikal hanya dalam log selamat;
- status operational yang jelas untuk completed, failed dan rolled-back;
- authoritative previous-source baseline;
- backup serta rollback rehearsal sebelum pilot.

Exit gate S3:

- concurrent run ditolak;
- partial-source anomaly berhenti sebelum mutation;
- semua failure path menutup transaction;
- reconciliation count sepadan dengan audit log;
- tiada akaun manual terlindung dinyahaktif.

## 7. S4 — Controlled Cutover dan Verification

Perubahan dirancang:

- strict server-side feature flag, default `legacy`;
- shadow comparison hanya antara pure planner, bukan dua writer;
- satu controlled pilot melalui manual admin sync;
- scheduled sync kekal retired dan di luar skop;
- semak count/status/audit sebelum dan selepas pilot;
- kekalkan rollback kepada legacy sepanjang observation window;
- retirement legacy hanya selepas parity, monitoring dan owner acceptance.

Exit gate S4:

- full UAT termasuk login admin, preview, apply, reconciliation dan SSO lulus;
- tiada unexplained deactivate/update;
- monitoring stabil dalam tempoh pemerhatian;
- rollback rehearsal lulus;
- owner memberi keputusan GO untuk retirement legacy.

## 8. Peraturan Keselamatan Merentas Semua Fasa

- Jangan gunakan butang full sync sebagai ujian UI biasa.
- Jangan jalankan dua engine mutating untuk perbandingan.
- Jangan hidupkan semula cron dalam fasa ini.
- Jangan rekod raw IC, email, token, password atau external row dalam evidence.
- Setiap live mutation memerlukan backup, owner, window dan rollback point.
- Satu fasa mesti lulus gate sebelum fasa berikutnya dimulakan.

## 9. Urutan Pelaksanaan

Urutan yang diluluskan ialah:

```text
S0 baseline → S1 manual/provenance → S2 preview → S3 writer safety → S4 cutover
```

Kemaskini S1: implementation, migration, automated verification, Manual Add,
OTP/password reset dan login selesai pada 14 Julai 2026. UAT menemui limiter
login legacy 10 aksara; ia dibetulkan kepada 20 dan login retest owner berjaya.
Rujuk `docs/S1_MANUAL_USER_HARDENING_DAN_PROVENANCE.md`.

S2 telah melepasi exit gate. Langkah berikutnya ialah checkpoint Git S2 sebelum
memulakan S3 transaction, lock, source-completeness dan reconciliation safety.

Kemaskini S3 pada 14 Julai 2026: safety core dormant, advisory lock,
transaction boundary, source-completeness thresholds dan exact reconciliation
telah diimplementasikan tanpa production caller wiring atau live sync. Rujuk
`docs/S3_TRANSACTION_LOCK_SOURCE_SAFETY_DAN_RECONCILIATION.md`. S3 hanya boleh
bergerak ke live pilot melalui runbook dan feature flag S4.
