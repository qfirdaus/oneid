# Rollback release production

Rollback kod, routing dan mobile state ialah lapisan berbeza. **Jangan restore database penuh secara automatik** kerana ia boleh memadam perubahan pengguna yang berlaku selepas release.

## A. Masalah runtime PHP sahaja

Jika source baharu berfungsi tetapi runtime 8.4 bermasalah, tutup dahulu mobile jika pool 8.3 mobile production belum disediakan/disahkan. Pulihkan FastCGI web OneID ke socket asal `/run/php/php8.3-fpm-oneid.sock` daripada backup Nginx khusus window. Jalankan `nginx -t`, kemudian reload. Kekalkan source baharu (uji 8.3 juga), vendor hasil lock dan database. Jangan hentikan PHP 8.4 secara global jika pool lain sudah bergantung padanya.

Ini tidak sama dengan rollback feature/kod. Sahkan web login/downstream dan default PHP 8.3. Biarkan provider dan mobile OFF sehingga punca dipastikan. Jika mahu mobile terus berjalan pada 8.3, sediakan dan uji pool production berasingan dahulu; jangan guna socket UAT.

## B. Rollback mobile / keseluruhan release

1. Tutup public route mobile dan OIDC menggunakan dormant restrictions atau pulihkan Nginx sebelum-release, validate sebelum reload. Hentikan penerbitan/refresh token mobile. `/token-hook` tidak pernah dibuka awam.
2. Atomic-edit hosted config `enabled=false`; pastikan reload/invalidation berhasil. Entry point OFF pulangkan 404 sebelum DB/session. Feature OFF tidak membatalkan JWT yang sudah diterima offline oleh pihak lain; access token boleh kekal sah sehingga TTL (template 5 minit), kecuali consumer membuat introspection/revocation enforcement.
3. Sebelum menukar source ke versi lama, disable observer menggunakan **tool candidate**:

```sh
php8.4 tools/release/mobile-migration.php --disable-observer
```

Tool memerlukan root/config/physical DB production tepat dan hosted OFF. Jika belum ada control table kerana migration gagal separuh jalan, jangan jalankan langkah ini membuta tuli; periksa progress private dan schema dahulu. Jangan DROP jadual/trigger untuk mengatasi ralat.

4. Hentikan service Hydra **khusus OneID mobile production** jika perlu. Jangan hentikan private PostgreSQL sebelum backup; kekalkan data/key material untuk diagnosis dan pemulihan. Jangan rotate keys atau mengosongkan records/identity_epoch.
5. Pulihkan source + vendor daripada backup sebelum release menggunakan senarai manifest, buang hanya fail baharu yang dikenal pasti manifest jika perlu. Kekalkan uploads, logs, sessions dan `.private/runtime.php` production. Jangan `git reset --hard` atau `git clean -fdx` sebagai rollback menyeluruh. Jangan restore private config lama yang mengaktifkan mobile semula.
6. Pulihkan Nginx web ke socket 8.3 asal, validate, reload; reload FPM web untuk menghapus cache kod lama/baharu bercampur. Semak ownership dan session settings asal.
7. Kekalkan empat jadual mobile dan trigger dalam keadaan observer OFF. Ini mengekalkan subject mapping/audit; removal DDL ialah migration lain selepas review. Semak source writes masih berjaya. Trigger masih bergantung pada control table—jangan drop control table sahaja.
8. Sambung jobs yang dipause mengikut keadaan asal, semak login web/legacy downstream dan error logs. Catat backup ID, masa, punca dan ujian.

## C. DDL separuh jalan / pemulihan data

`mobile-migration.php` menyimpan `before.json`, `plan.json`, `progress.json`, `SUCCESS.json` dalam `.private/mobile-migration-*`. DDL MySQL autocommit; `ROLLBACK` SQL tidak membatalkan CREATE TABLE/TRIGGER. `state=starting` mungkin bermaksud statement sudah execute tetapi evidence gagal ditulis—bandingkan schema sebenar sebelum repair/resume. Default tool menolak existing mobile schema.

Gunakan reviewed forward repair apabila selamat. Restore DB snapshot hanya dengan approval kehilangan data yang dinilai dan point-in-time recovery jika tersedia. Simpan copy state/provider/keys selepas insiden sebelum restore. Rollback provider binary tidak semestinya serasi dengan schema PostgreSQL baharu; gunakan binary+DB backup+keys yang sepadan, bukan binary sahaja.

Rollback OFF/ON mengubah policy epoch, maka sesi mobile mungkin memerlukan login semula. Selepas observer OFF sementara source identities berubah, jangan aktifkan semula terus: reconcile identity generations/deletions dalam maintenance window dan revoke sesi lama terlebih dahulu. Web tidak menggunakan mobile tables untuk legacy login.
