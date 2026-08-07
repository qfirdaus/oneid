# User Session Timeout F5 — Regression dan Controlled Rollout

**Tarikh:** 7 Ogos 2026  
**Status:** IMPLEMENTED / PENDING FINAL UAT SIGN-OFF

## Tujuan

Fasa 5 mengunci bukti teknikal Fasa 0–4 dan menyediakan rollout production yang
boleh dihentikan melalui feature flag tanpa perubahan pada aplikasi lain.
Tiada behavior runtime baharu diperkenalkan dalam fasa ini.

## Readiness Tool

Source/read-only suite:

```bash
php tools/user_session_timeout_f5_readiness.php --source
```

Keputusan source semasa:

```text
RESULT mode=--source contracts=14 failures=0
```

Selepas staging diaktifkan:

```bash
php tools/user_session_timeout_f5_readiness.php --staging
```

Mod staging turut mengesahkan secara read-only:

- `ONEID_USER_SESSION_WARNING_ENABLED=true`;
- audit dictionary event 68, 69 dan 70 lengkap;
- `sys_config.token_timeout` ialah pilihan Administrator yang dibenarkan.

Tool tidak mengubah database, setting Administrator, token, session atau fail
runtime.

## Liputan Regression

Readiness suite merangkumi:

- baseline serta polisi Fasa 0–1;
- endpoint, expiry dan logout-scope Fasa 2;
- SweetAlert, locale dan halaman Fasa 3;
- heartbeat/error handling Fasa 4;
- idle/technical heartbeat dan revoked-token enforcement;
- hierarchy dan renewal Administrator;
- konfigurasi serta lifetime token SSO;
- password change;
- MyDigital ID logout compatibility;
- kontrak API service provider yang tidak berubah.

## Matriks UAT Akhir

| Senario | Keputusan diperlukan |
|---|---|
| Setting 30 minit | Popup sekitar minit 28 |
| Setting 1 jam | Popup sekitar minit 58, bukan minit 28 |
| Stay Connected user | `USER_SESSION_RENEWED`, token sama, popup success + OK |
| Tiada tindakan | Portal tamat pada 00:00, aplikasi lain kekal aktif |
| End OneID Session | Session/cookie portal dibersihkan, token DB tidak direvoke |
| Logout manual | Token direvoke dan pengguna kembali ke landing |
| Dua tab | Renewal/expiry diselaraskan |
| Background/sleep | Baki authoritative dibaca apabila visible semula |
| Offline/5xx | Tiada logout atau reload loop |
| Token revoked | Terminal `SSO_TOKEN_REVOKED` |
| Akaun inactive | Terminal `ACCOUNT_INACTIVE` |
| CSRF stale | Satu controlled revalidation, tiada silent renewal |
| Modal/password/MFA | Tiada popup bertindih atau input sensitif tertinggal |
| BM/English/mobile/keyboard | Kandungan dan controls boleh digunakan |
| Administrator | Hanya popup admin; effective deadline mematuhi base session |
| Aplikasi SSO lain | Tiada perubahan kontrak atau code diperlukan |

Ujian 30 minit dan satu jam menggunakan masa sebenar. Test-clock override tidak
ditambah ke runtime kerana sebarang override timeout pada host production ialah
risiko bypass kawalan keselamatan.

## Production Rollout

1. Pastikan production OneID menggunakan host/pool khusus dan
   `session.gc_maxlifetime >= 28800`.
2. Ambil backup source, `.private/runtime.php`, konfigurasi FPM dan database.
3. Deploy source dengan feature flag kekal `false`.
4. Jalankan `--source`, lint PHP, FPM config test dan smoke-test login/SSO/logout.
5. Apply audit dictionary melalui `tools/user_portal_session_schema.php --apply`.
6. Sahkan event 68–70 dan setting timeout Administrator.
7. Aktifkan `ONEID_USER_SESSION_WARNING_ENABLED=true` dalam private runtime.
8. Reload PHP-FPM dan jalankan readiness `--staging` pada host production
   sebelum membuka rollout kepada semua pengguna.
9. Jalankan canary user/admin, kemudian pantau log dan audit event.

## Monitoring

Pantau sekurang-kurangnya:

```bash
grep -Ei 'USER_SESSION|SSO_TOKEN_REVOKED|ACCOUNT_INACTIVE|Unhandled|Fatal|TypeError' \
  /var/log/nginx/oneid-*.error.log
```

Semak event audit 68–70, kadar HTTP 401/403/5xx untuk `/lib/q_func.php`, dan
pastikan tiada siri request/reload berulang daripada browser yang sama.

## Rollback

Rollback paling rendah risiko:

```php
'ONEID_USER_SESSION_WARNING_ENABLED' => 'false',
```

Kemudian test dan reload PHP-FPM. Ini mematikan popup/controller serta-merta
tanpa mengubah token atau aplikasi lain. Endpoint Fasa 2 dan event audit boleh
kekal dormant. `gc_maxlifetime=28800` juga boleh kekal sebagai storage retention.

Jika source rollback diperlukan, revert commit mengikut urutan Fasa 4 hingga
Fasa 1 selepas feature flag dimatikan. Jangan rollback migration dictionary
ketika rekod audit 68–70 masih diperlukan untuk paparan sejarah.

## Gate Penutupan

- [x] Readiness source 14/14 lulus.
- [x] Feature flag staging boleh diaktif/nonaktif secara terkawal.
- [x] Audit dictionary staging lengkap.
- [x] Login, dashboard, Administrator, SSO dan logout smoke-test dilaporkan lulus.
- [ ] Matriks UAT akhir direkod lengkap oleh pemilik sistem.
- [ ] Kelulusan production rollout direkod.
