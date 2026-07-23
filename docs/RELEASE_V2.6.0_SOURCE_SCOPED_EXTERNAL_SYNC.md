# OneID v2.6.0 — Source-Scoped External Sync dan ODL Shadow Preview

**Versi:** 2.6.0  
**Tarikh release:** 23 Julai 2026  
**Environment sasaran:** UAT  
**Status:** Staff/UG guarded Apply; ODL read-only

## Ringkasan

Release ini menyusun semula External Sync mengikut sumber dan menambah laluan
ODL postgraduate secara berfasa. Staff dan Undergraduate mengekalkan keupayaan
Apply yang telah diuji, tetapi Preview, approval dan fresh writer plan kini
diikat kepada source masing-masing. ODL kekal dalam mod Read Only Shadow
Preview tanpa mutation.

## Perubahan External Sync

- parent modal menyediakan menu `External Sync Summary`,
  `Staff External Sync`, `Undergraduate External Sync`,
  `ODL External Sync (Read Only Shadow Preview)` dan `Manual Add User`;
- Summary menggabungkan metrik untuk pemantauan sahaja dan tidak menyediakan
  Apply;
- Staff dan Undergraduate memaparkan Preview source masing-masing serta
  Operational Apply apabila server mengeluarkan one-time approval;
- setiap Apply membawa `sync_source_code`, dan server membina semula plan
  menggunakan source sama sebelum transaksi;
- active-user read ditapis kepada kategori Staff `2,3` atau UG `10,11,12`
  sebelum planner membuat keputusan UPDATE atau DEACTIVATE;
- safety policy source-specific masih menguatkuasakan source tidak kosong,
  shrink maksimum 20%, invalid-row threshold, deactivation threshold,
  protected identity collision dan reconciliation;
- Apply tidak dipaparkan apabila tiada perubahan atau apabila safety/operational
  gate block.

## Integrasi ODL postgraduate

- provenance schema dan source `STUDENT_ODL_PG` didaftarkan secara dormant;
- membership undergraduate sedia ada dibackfill kepada `STUDENT_UG` tanpa
  mutation profil pengguna;
- adapter MySQL ODL menggunakan private runtime configuration dan akaun
  read-only;
- connection mesti menggunakan TLS dan gagal secara fail-closed jika sesi TLS
  tidak aktif;
- CA file boleh dikosongkan untuk UAT apabila TLS transport aktif, tertakluk
  kepada security review baharu sebelum production;
- data-quality audit memeriksa matrik, IC, e-mel dan row envelope;
- planner source-aware menyokong multi-source membership, account-activation
  policy, conflict block dan zero-mutation projection;
- Shadow Preview memaparkan Staff, UG dan ODL secara source-specific serta
  digest, health, shrink, membership dan calon tindakan;
- ODL Apply, mutation pengguna dan automatic scheduler kekal disabled.

## Penambahbaikan UI dan notifikasi

- modal Preview dilebarkan untuk kandungan panjang dan digest;
- loading text mengikut menu yang dipilih;
- child modal kembali kepada parent modal apabila ditutup;
- badge loceng dipaparkan hanya apabila ada NEW, UPDATE, REACTIVATE,
  DEACTIVATE atau calon ODL;
- source/safety block menggunakan badge amaran merah;
- badge disembunyikan apabila tiada tindakan diperlukan.

## Penyelenggaraan keselamatan

- endpoint external lama yang dikuarantin kekal tidak boleh dicapai;
- read external source kekal melalui polisi query read-only;
- had request, verification dan lockout OTP e-mel Admin 2FA boleh ditetapkan
  melalui private runtime configuration;
- refresh halaman admin selepas grant Administrator tamat membersihkan sesi
  lokal dan redirect ke login tanpa memaparkan respons guard berbentuk teks.

## Hasil semakan WSL

Pada snapshot release:

- Staff: 1,061 source rows, 3 calon NEW, risiko normal;
- Undergraduate: 5,452 source rows, tiada perubahan, risiko normal;
- ODL: 53 source rows, 53 `CANDIDATE_NEW`, risiko normal;
- ODL `can_apply=false` dan `mutation_statements=0`.

Kiraan ini ialah snapshot Preview dan boleh berubah selepas source atau OneID
dikemas kini. Admin mesti menjana Preview baharu sebelum setiap Apply.

## Batas release

- automatic scheduler kekal disabled;
- Summary dan ODL tidak mempunyai fungsi Apply;
- production ODL memerlukan security review baharu, credential khusus host
  OneID dan keputusan CA/certificate trust yang diluluskan;
- `.private/runtime.php`, password, credential dan secret tidak termasuk
  dalam Git;
- setiap Apply Staff/UG masih memerlukan runtime safe engine, operational gate,
  exact confirmation, one-time approval dan reconciliation berjaya.

## Ujian utama

- source-scoped persistence characterization;
- source-bound Preview/Apply contract;
- operational approval dan hard-limit contract;
- ODL shadow zero-mutation contract;
- planner, orchestrator, persistence adapter dan reconciliation parity;
- dashboard characterization serta release metadata contract.
