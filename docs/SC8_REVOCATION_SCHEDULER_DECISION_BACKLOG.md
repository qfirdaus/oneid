# SC8 — Revocation Scheduler Decision dan Activation

**Status:** BACKLOG / KEPUTUSAN OPERASI BERASINGAN  
**Tarikh keputusan owner:** 21 Julai 2026  
**Skop:** Scheduled policy-token revocation  
**Baseline:** Lazy revocation enforcement kekal aktif

## 1. Keputusan owner

Penerimaan Admin Step-Up 2FA F7.6 tidak mengaktifkan revocation scheduler.
Scheduler kekal sebagai task keputusan dan pelaksanaan berasingan. Lazy
enforcement semasa terus menolak token due apabila token digunakan.

Task ini juga berasingan daripada Cron External Sync dan AS3 Controlled
Active-Session Revocation.

## 2. Objektif

Menentukan sama ada runner revocation perlu dijalankan secara automatik untuk
menandakan token yang telah due sebagai revoked tanpa menunggu request pengguna.

```text
Tanpa scheduler: token due ditolak apabila digunakan.
Dengan scheduler: background job menandakan token due secara berkala.
```

## 3. Keputusan yang diperlukan

Sebelum activation, owner mesti menetapkan:

- keperluan operasi sebenar dan expected benefit;
- frequency dan maintenance window;
- service account berpermission minimum;
- single-run lock dan overlap behavior;
- timeout, batch size dan retry policy;
- idempotency dan cancellation boundary;
- monitoring, alert channel dan incident owner;
- audit/reconciliation serta log retention;
- backup, rollback dan recovery procedure.

Jika lazy enforcement memenuhi keperluan, keputusan sah boleh berupa
`DO NOT SCHEDULE`; scheduler tidak wajib diaktifkan.

## 4. Pelan penilaian

1. Refresh baseline token lifecycle dan due counts.
2. Audit runner serta semua mutation/audit paths.
3. Jalankan read-only `--check` berulang kali.
4. Rehearse controlled `--apply` dalam isolated environment.
5. Buktikan lock, timeout, retry dan idempotency.
6. Pilot satu controlled batch dengan expected count.
7. Reconcile planned, executed dan audited rows.
8. Uji disable/rollback serta alert failure.
9. Rekod keputusan eksplisit `ACTIVATE`, `DEFER` atau `DO NOT SCHEDULE`.

## 5. Minimum test matrix

- `--check` zero-mutation;
- empty due set berjaya tanpa mutation;
- exact due set sahaja direvoke;
- runner bertindih ditolak oleh lock;
- repeated apply idempotent;
- partial/database/audit failure rollback;
- timeout dan retry tidak menggandakan tindakan;
- counts direconcile;
- alert dihantar bagi hard failure;
- lazy request-time enforcement kekal berfungsi;
- Cron External Sync tidak disentuh;
- SC4/SC5 dan F7.6 regression lulus.

## 6. Exit gate

Scheduler hanya boleh diaktifkan selepas change approval, controlled rehearsal,
operational ownership, monitoring dan rollback evidence lengkap. Sehingga itu:

```text
LAZY REVOCATION ENFORCEMENT: ACTIVE
SCHEDULED REVOCATION: DISABLED / NOT SCHEDULED
```
