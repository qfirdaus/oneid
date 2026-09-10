# OneID 2.13.1 — Login Banner Step-Up Resume

## Ringkasan

Release 2.13.1 melengkapkan integrasi Login Banner dengan Administrator Step-Up.
Perubahan yang belum dilaksanakan disimpan sementara dalam session browser dan
dipulihkan selepas pengesahan, tanpa melonggarkan validasi server atau kawalan
optimistic locking.

## Perubahan

- tindakan publish, inactivate, rollback dan reorder dipulihkan selepas Step-Up;
- susunan dan sebab perubahan tidak perlu dimasukkan semula;
- data banner dimuatkan semula dan `configuration_version` mesti masih sepadan;
- pending action tamat selepas 15 minit;
- modal sebab menyediakan pilihan boleh klik dan textarea untuk sebab lain;
- nilai teks draf dipulihkan, tetapi fail imej perlu dipilih semula kerana
  perlindungan keselamatan browser; dan
- teks serta paparan tersedia dalam BM dan English.

## Keselamatan

Step-Up hanya memberikan grant untuk tujuan `SECURITY_CONFIGURATION_CHANGE`.
Mutation tidak dijalankan secara automatik selepas redirect: Administrator masih
perlu menyemak dan mengesahkan tindakan. Pending payload kekal origin-bound dalam
`sessionStorage`, mempunyai tempoh pendek dan ditolak apabila versi rekod berubah.

## Deployment

Tiada migration database atau perubahan runtime diperlukan. Deployment ialah
fast-forward code biasa selepas working tree disahkan bersih.

## Verification

```bash
php tools/release_metadata_contract.php
php tools/version_documentation_contract.php
php tools/lb5_login_banner_admin_ui_contract.php
php tools/admin_multilingual_completeness_contract.php
php tools/lb6_login_banner_public_contract.php
```
