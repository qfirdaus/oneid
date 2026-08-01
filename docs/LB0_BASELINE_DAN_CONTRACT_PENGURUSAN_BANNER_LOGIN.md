# LB0 — Baseline dan Contract Pengurusan Banner Login

**Tarikh:** 1 Ogos 2026  
**Status:** BASELINE RECORDED / OWNER DECISIONS CONFIRMED / ZERO MUTATION  
**Environment:** Local WSL; staging perlu menjalankan inventory read-only sendiri  

## 1. Tujuan

LB0 mengunci keadaan banner login sebelum schema atau runtime baharu dibina.
Ia menyediakan caller map, decision register, inventory tool dan contract yang
akan melindungi static fallback sepanjang LB1 hingga LB8.

## 2. Artefak

- `docs/AUDIT_DAN_PELAN_IMPLEMENTASI_PENGURUSAN_BANNER_LOGIN.md`;
- `docs/LB0_LOGIN_BANNER_DECISION_REGISTER.tsv`;
- `docs/LB0_LOGIN_BANNER_CALLER_MAP.tsv`;
- `tools/lb0_login_banner_audit.php`; dan
- `tools/lb0_login_banner_contract.php`.

## 3. Baseline kod dan aset

Semasa checkpoint:

- carousel berada dalam `index.php` dan mempunyai dua item live;
- `banner6.png` ialah item pertama/active;
- `banner7.png` ialah item kedua;
- `banner5.png` hanya berada dalam comment;
- lima fail `banner3.png` hingga `banner7.png` tracked oleh Git;
- tiada source class, route, action atau migration bernama login banner;
- tiada admin tab pengurusan banner; dan
- static fallback tidak bergantung pada database.

Semua lima fail ialah PNG `3780 x 1890` (`2:1`). Fail aktif adalah besar dan
perlu digantikan oleh derivative teroptimum hanya selepas dynamic path lulus
gate. LB0 tidak mengubah atau mengoptimumkan fail tersebut.

## 4. Contract yang dikunci

1. LB1 schema mesti dormant dan tidak mengubah output `index.php`.
2. Static banner6/banner7 kekal fallback sehingga LB8 diterima owner.
3. Dynamic banner failure tidak boleh menghalang form login, Forgot Password
   atau MyDigital ID.
4. Locale BM dan English boleh berkongsi satu asset ID tanpa salinan binary.
5. Alt text kekal wajib per locale walaupun asset sama.
6. Tiada silent English fallback; admin memilih same-as-BM secara eksplisit.
7. Binary asset khusus environment dan tidak cross-fallback.
8. Draft tidak muncul di public surface.
9. Publish/reorder/inactivate/rollback memerlukan Administrator, CSRF, active
   token, `SECURITY_CONFIGURATION_CHANGE` step-up dan correlated audit.
10. Fail live immutable; update menghasilkan filename/version baharu.
11. DB/shared metadata tidak menyimpan absolute path atau binary.
12. Cleanup tidak berlaku dalam inventory/reconciliation.

## 5. Keputusan owner

Semua `LB-D01` hingga `LB-D12` telah disahkan melalui arahan owner untuk
meneruskan cadangan pada 1 Ogos 2026. Nilai penuh berada dalam
`LB0_LOGIN_BANNER_DECISION_REGISTER.tsv`.

Approval ini membenarkan LB0 documentation/contract. Ia belum membenarkan
migration LB1, runtime implementation, upload, staging deployment atau
Production promotion.

## 6. Cara menjalankan semakan

```bash
php tools/lb0_login_banner_audit.php
php tools/lb0_login_banner_contract.php
```

Audit ialah read-only dan menghasilkan inventory untuk filesystem tempat ia
dijalankan. Staging perlu menjalankannya dari checkout staging supaya ownership,
saiz dan checksum staging tidak dianggap sama dengan local.

## 7. Gate LB0

LB0 lulus apabila:

- decision register mempunyai tepat 12 keputusan CONFIRMED;
- caller map meliputi public render, admin, request boundary, upload, audit,
  environment dan fallback;
- dua banner live serta lima tracked banner disahkan;
- semua image boleh dibaca dan bernisbah `2:1`;
- contract mengesahkan zero schema/runtime implementation; dan
- `git diff --check` lulus.

## 8. Handoff LB1

LB1 hanya boleh bermula selepas owner mengarahkan implementasi seterusnya. LB1
akan menghasilkan migration additive/dormant, repository contract dan rollback
rehearsal tanpa mengaktifkan dynamic banner pada halaman login.

Contract dijalankan pada 1 Ogos 2026 dengan keputusan `8/8 PASS`.

**Keputusan LB0:** PASS / COMPLETE; RUNTIME UNCHANGED; LB1 NOT AUTHORIZED.
