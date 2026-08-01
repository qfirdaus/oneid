# LB3 — Domain Service dan Audit Atomic Banner Login

**Tarikh:** 1 Ogos 2026  
**Status:** LOCAL PASS / BEHAVIOR 13 OF 13 PASS / SOURCE CONTRACT 10 OF 10 PASS  
**Runtime/HTTP wiring:** Tiada  
**Database migration apply:** Tidak dijalankan  

## 1. Tujuan

LB3 menggabungkan persistence LB1 dan secure image pipeline LB2 melalui domain
service dormant. Ia menetapkan boundary atomic bagi draft, publish, inactivate,
reorder dan rollback sebelum endpoint atau UI Administrator dibina.

## 2. Komponen

- `LoginBannerImagePipelineInterface` membolehkan pipeline sebenar atau fake
  terkawal digunakan tanpa memintas contract;
- `LoginBannerPersistenceInterface` ditambah dengan locked locale assets,
  published-set dan rollback-history reads;
- `PdoLoginBannerPersistence` melaksanakan query environment-scoped;
- `LoginBannerService` menguatkuasa domain invariant dan audit; dan
- `LoginBannerDomainException` menyediakan stable rejection code.

## 3. Create draft atomic

Aliran create draft:

1. sahkan environment, actor, IP, reason, key, order, schedule dan alt text;
2. stage BM dan, jika berbeza, English melalui LB2;
3. mula transaction persistence;
4. insert logical DRAFT dan dua translation;
5. atomic publish staged binary;
6. insert asset metadata AVAILABLE;
7. map BM/EN kepada asset;
8. tulis success history; dan
9. commit.

Pilihan `same image for English` menghasilkan satu stage, satu published file dan
satu asset row. BM dan English merujuk asset ID sama. Jika imej berbeza, dua
asset immutable diterbitkan.

Jika mana-mana database atau mandatory audit operation gagal selepas filesystem
publish, transaction rollback dan service membuang staged/published file yang
tepat. Cleanup failure direkodkan ke application error log dan tidak menghasilkan
false success.

## 4. Publish invariant

Publish memerlukan:

- banner wujud dan expected version tepat;
- transition hanya DRAFT/INACTIVE kepada PUBLISHED;
- translation BM serta English lengkap;
- kedua-dua locale mempunyai mapping bagi environment semasa;
- asset berstatus AVAILABLE;
- English `SAME_AS_MS` benar-benar merujuk asset ID BM;
- schedule overlap tidak menghasilkan lebih lima banner; dan
- versioned update serta success audit masing-masing menulis tepat satu row.

Published set dikunci melalui persistence sebelum kiraan overlap supaya dua
publisher serentak tidak melepasi limit secara senyap.

## 5. Inactivate, reorder dan rollback

- Inactivate hanya menerima banner PUBLISHED.
- Reorder menerima maksimum lima item, banner/order unik, semua PUBLISHED dan
  expected version tepat; seluruh batch berada dalam satu transaction.
- Rollback membaca latest successful before-state di bawah lock, mengesahkan
  state dan menghasilkan version baharu; ia tidak menurunkan version counter.
- Rollback kepada PUBLISHED menjalankan semula locale, asset dan overlap checks.

Rollback LB3 meliputi lifecycle/order/schedule state. Asset binary immutable
tidak dioverwrite. Editing/replacement version history akan diperluas apabila
fungsi edit UI dibina pada LB5.

## 6. Audit

Success audit ialah mandatory dan berada dalam transaction mutation yang sama.
Ia membawa actor, IP, action, reason code, change reason, before/after,
environment, versions dan 16-hex correlation ID.

Rejected attempt cuba direkod melalui transaction berasingan selepas rollback.
Ia bersifat best-effort supaya audit storage failure tidak menukar rejection
menjadi success atau menyembunyikan error asal. Failure secondary audit direkod
dalam application error log bersama correlation.

## 7. Verification

Behavioral test membuktikan:

- same-image menghasilkan satu asset dan dua mapping;
- separate locale menghasilkan dua asset;
- alt/fallback metadata tepat;
- draft success dan audit satu transaction;
- publish/version increment;
- stale rejection dan rejected audit;
- rollback previous state;
- valid inactivate transition;
- mandatory audit failure rollback;
- filesystem compensation; dan
- atomic reorder.

Commands:

```bash
php tests/characterization/lb3_login_banner_service.php
php tools/lb3_login_banner_contract.php
```

## 8. Boundary

LB3 tidak menambah require/wiring pada `index.php`, `admin/dashboard.php`,
`lib/q_func.php` atau action map. Tiada HTTP input sebenar, CSRF/Step-Up endpoint,
UI, migration apply atau public renderer.

Langkah seterusnya ialah LB4 endpoint dan request boundary. LB4 perlu menambah
action secara eksplisit, menguatkuasa Administrator/active token/CSRF serta
`SECURITY_CONFIGURATION_CHANGE` Step-Up sebelum memanggil service ini.

**Keputusan semasa:** LB3 LOCAL PASS / DATABASE AND LOGIN RUNTIME UNCHANGED.
