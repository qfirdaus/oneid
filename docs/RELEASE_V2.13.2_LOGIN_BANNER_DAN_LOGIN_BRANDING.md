# OneID 2.13.2 — Login Banner dan Login Branding

## Ringkasan

Release 2.13.2 melengkapkan penambahbaikan operasi Login Banner dan memperkemas
identiti visual halaman login. Perubahan ini tidak memerlukan migration database
atau runtime key baharu.

## Perubahan utama

- modal sebab perubahan dan borang kemas kini banner diperkemas;
- satu aset `banner_default.png` digunakan apabila tiada banner dinamik aktif;
- pautan E-mel UPNM / UPNM Mail ditambah pada navigasi awam;
- fail upload kekal pada borang semasa Step-Up dibuat dalam tetingkap berasingan;
- kunci banner dinormalkan kepada awalan `LB-` sebelum validasi browser;
- jadual dipaparkan dalam waktu Malaysia dan kekal disimpan sebagai UTC;
- notis User Manual English dipermudah; dan
- logo OneID serta UPNM30 disusun sebaris dengan effect gerakan yang accessible.

## Keselamatan dan compatibility

Semua mutation banner masih memerlukan CSRF, sesi Administrator aktif dan grant
Step-Up `SECURITY_CONFIGURATION_CHANGE`. Fail upload tidak disimpan dalam browser
storage atau distage sebelum authorization. Pengguna dengan tetapan reduced motion
tidak menerima animasi logo.

## Deployment

Deployment ialah fast-forward code biasa. Tiada SQL migration atau perubahan
`.private/runtime.php` diperlukan.

## Verification

```bash
php tools/release_metadata_contract.php
php tools/version_documentation_contract.php
php tests/characterization/ml8c_approved_release_catalogue.php
php tools/lb5_login_banner_admin_ui_contract.php
php tools/lb6_login_banner_public_contract.php
php tools/login_method_layout_contract.php
```
