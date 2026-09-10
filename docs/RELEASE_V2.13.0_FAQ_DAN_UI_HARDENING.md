# OneID 2.13.0 — FAQ dan UI Hardening

## Ringkasan

Release 2.13.0 menyelaraskan FAQ awam dan pengguna dengan operasi OneID semasa
serta menyeragamkan SweetAlert kepada reka bentuk profesional OneID. Perubahan
FAQ menggunakan satu sumber BM/English dan perubahan modal kekal pada lapisan
persembahan tanpa mengubah callback, AJAX, validasi atau kawalan keselamatan.

## FAQ

- 13 entri dengan identiti serta urutan BM/English yang sama;
- istilah `Akses` untuk Full SSO dan `Log Masuk` untuk Non-SSO;
- pautan pendaftaran rasmi MyDigital ID;
- mode MFA, User Security dan pemulihan Authenticator;
- kira detik, sambungan serta had maksimum sesi;
- notifikasi keselamatan e-mel; dan
- tingkah laku pengguna, developer dan Administrator semasa Maintenance Mode.

## Pengukuhan UI

- shell SweetAlert profesional pada semua entry point aktif;
- modal ringkas 560px dan kandungan berstruktur 680px;
- observer DOM bounded tanpa pemerhatian subtree atau style;
- cache-busting aset production; dan
- penjajaran jumlah Active/Inactive dalam pengurusan kategori aplikasi.

## Deployment

Tiada migration database dan tiada perubahan runtime diperlukan. Deployment
boleh dibuat melalui kemas kini code biasa selepas working tree production
disahkan bersih. Rollback dilakukan kepada tag pra-deployment yang dicipta
sebelum rebase.

## Verification

```bash
php tools/release_metadata_contract.php
php tools/version_documentation_contract.php
php tools/ml8b_shared_faq_contract.php
php tools/user_faq_presentation_contract.php
php tools/professional_sweetalert_ui_contract.php
```
