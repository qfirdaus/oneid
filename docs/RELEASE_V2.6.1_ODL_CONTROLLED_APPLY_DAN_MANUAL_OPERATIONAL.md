# OneID v2.6.1 — ODL Controlled Apply dan Manual Operational Preview

**Versi:** 2.6.1  
**Tarikh release:** 24 Julai 2026  
**Environment sasaran:** UAT  
**Status:** Fasa 0–9 `PASS / CLOSED`

## Ringkasan

Release ini melengkapkan Controlled Pilot dan Controlled Full Apply ODL,
menutup cross-source isolation hardening dan menyediakan Manual Operational
Preview ODL melalui Admin. Automatic scheduler, unattended mutation dan
production rollout kekal disabled.

## Controlled ODL rollout

- Fasa 7 mencipta tepat tiga akaun Pilot `NEW` dengan membership
  `STUDENT_ODL_PG`, audit correlation, reconciliation dan login/ACL smoke test;
- Fasa 8 mencipta baki 50 akaun `NEW` dalam satu exact-plan transaction;
- post-Apply scope mengandungi 53 membership ODL aktif;
- Staff kekal 1,061 membership dan Undergraduate kekal 5,423 membership;
- tindakan `UPDATE`, `DEACTIVATE` dan `REACTIVATE` semasa F8 adalah sifar;
- Apply flag dipadam semula selepas one-shot execution.

## Manual Operational Preview

- menu ODL kini menggunakan guarded Preview/Apply modal yang sama seperti UG;
- Preview membawa source rows, action counts, plan hash, expiry, safety status
  dan warning tanpa mendedahkan PII mentah;
- private gate Preview dan Apply ODL diasingkan;
- Preview dibenarkan di UAT, tetapi Apply kekal disabled sehingga exact-plan
  authorization baharu diterima;
- automatic scheduler atau cronjob belum diwiring.

## Source isolation dan profile policy

- source ODL dikunci kepada `STUDENT_ODL_PG` dan kategori `Pelajar/10`;
- planner read dan persistence write menggunakan provenance membership;
- Matrik, IC dan external membership collision dengan sumber lain diblock;
- akaun manual kekal protected;
- e-mel ODL kosong tidak memadam e-mel OneID sedia ada;
- deactivation tidak menyahaktifkan akaun yang masih mempunyai sumber aktif
  lain.

## Evidence

- F7: `ONEID-ODL-F7-20260723-01`;
- cross-source isolation: `ONEID-SOURCE-ISOLATION-20260723-01`;
- F8: `ONEID-ODL-F8-20260724-01`;
- F9 implementation dan Preview: `ONEID-ODL-F9-20260724-01`.

Live F9 Preview menghasilkan 53 source rows, 53 active scope, zero action, zero
blocking code dan zero mutation. Apply serta scheduler ialah `false`.

Selepas pasukan ODL menambah data terkawal, F9 exact-plan Apply menghasilkan
header 50 dengan 18 `NEW`, zero tindakan lain dan 71 active ODL memberships.
Post-Apply Preview kembali zero action dan login/ACL smoke test lulus. Evidence
closure ialah `ONEID-ODL-F9-20260724-02`; Apply dikembalikan kepada `false`.

## Batas release

- F9 live Apply pertama memerlukan perubahan sumber sebenar, fresh Preview dan
  exact-plan authorization;
- automatic scheduler kekal disabled;
- production tidak dibenarkan;
- credential dan `.private/runtime.php` tidak termasuk dalam Git.
