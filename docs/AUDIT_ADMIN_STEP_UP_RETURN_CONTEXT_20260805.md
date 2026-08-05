# Audit Admin Step-Up Return Context dan Transaction Resume

**Tarikh:** 5 Ogos 2026

**Skop:** Semua transaksi Administrator yang redirect ke `page/admin_step_up.php`

**Status:** AUDIT COMPLETE / CENTRALIZED REMEDIATION IMPLEMENTED / STAGING UAT PENDING

> **Implementation update:** `AdminStepUpReturnContext` kini menjadi allowlist
> server-side tunggal. Semua caller menggunakan context khusus dan dashboard
> membuka primary tab/subtab dahulu sebelum menerbitkan event resume. Contract
> `tools/admin_step_up_return_context_contract.php` mengunci completeness,
> unknown-target fallback, Active Sessions ordering dan hidden-resume gate.

## 1. Ringkasan Keputusan

Enforcement Step-Up, purpose isolation dan mutation control kekal berfungsi.
Isu yang ditemui ialah continuity UI selepas authentication: server kembali ke
dashboard, tetapi tab, subtab, modal atau workspace asal tidak sentiasa
dipulihkan.

Isu Active Sessions yang dilaporkan telah disahkan. URL pulang ialah
`admin/dashboard?active_sessions=1`, tetapi `admin/dashboard.php` tidak membaca
parameter `active_sessions`. Pending opaque target masih di-resume melalui
`sessionStorage`, menyebabkan Preview/Apply boleh diteruskan ketika tab utama
yang kelihatan bukan Active Sessions.

Ini bukan authorization bypass dan tidak menyebabkan revoke tanpa reason atau
confirmation. Namun ia ialah kecacatan context/UX dan boleh mengelirukan Admin
tentang sasaran operasi sensitif.

## 2. Inventori Return Target

| Return target | URL selepas Step-Up | Pemulihan context | Keputusan |
|---|---|---|---|
| `active_sessions` | `?active_sessions=1` | Tiada handler membuka `#tab_active_sessions`; pending target terus di-resume | **FAIL** |
| `login_banner` | Tidak dipetakan; jatuh ke dashboard default | Script menjangka `?return=login_banner`, tetapi URL itu tidak pernah dijana oleh Step-Up | **FAIL** |
| `admin_locale` | `?configuration=admin_locale` | Handler tersarang di dalam blok `account_recovery`, jadi tidak dicapai untuk locale | **FAIL** |
| `user_mfa_policy` — global | `?configuration=user_mfa_policy` | Parent Configuration dan subtab default Security dibuka; pending global resume pada panel betul | **PASS** |
| `user_mfa_policy` — category | URL sama seperti global | Parent dibuka tetapi subtab Category tidak dipulihkan; mutation boleh resume ketika panel tersembunyi | **PARTIAL/FAIL** |
| `user_mfa_policy` — exemption | URL sama seperti global | Parent dibuka tetapi subtab Exemption tidak dipulihkan; mutation boleh resume ketika panel tersembunyi | **PARTIAL/FAIL** |
| `account_recovery` | `?configuration=account_recovery` | Configuration → Account Recovery dibuka dan alamat ujian dipulihkan | **PASS** |
| `admin_2fa` | `?configuration=admin_2fa` | Configuration → Admin 2FA dibuka; pending lifetime/preference dipulihkan | **PASS** |
| `admin_metadata` | `?metadata=1` | Metadata workspace dibuka semula | **PASS dengan baki UX** |

`admin_metadata` tidak menyimpan semua field translation/reason sebelum
redirect. Workspace kembali betul, tetapi input yang belum disimpan boleh perlu
dimasukkan semula. File input Login Banner juga tidak boleh dipulihkan oleh
browser dan mesti dipilih semula secara eksplisit selepas return.

## 3. Punca Struktur

1. `page/admin_step_up.php` menggunakan allowlist `match` bagi return target,
   tetapi tiada registry bersama dengan handler dashboard.
2. Dashboard mempunyai beberapa mekanisme berasingan: query
   `configuration=*`, `metadata=1`, hash, dan `sessionStorage`.
3. Tiada satu fungsi pusat yang memetakan return target kepada primary tab,
   configuration group, subtab dan resume callback.
4. Kontrak sedia ada menguji Account Recovery dan beberapa mapping sahaja; ia
   tidak menguji completeness semua return target atau visible-tab-at-resume.

## 4. Risiko

- Admin hilang orientasi selepas authentication bagi operasi sensitif.
- Mutation resume boleh berjalan ketika panel asal tersembunyi.
- Admin boleh menyangka tindakan berlaku pada halaman/tab lain.
- Input belum disimpan boleh hilang; bagi upload, browser memang melarang
  pemulihan file input.
- Return target baharu boleh ditambah pada caller tanpa mapping/handler
  pasangan dan tiada contract yang mengesannya.

Tiada bukti bahawa isu ini melemahkan CSRF, exact-purpose grant, preview,
confirmation, transaction atau audit trail.

## 5. Reka Bentuk Remediasi Disyorkan

Gunakan allowlist return-context tunggal dengan identifier sempit, contoh:

```text
active_sessions
configuration_admin_2fa
configuration_account_recovery
configuration_locale
configuration_login_banner
configuration_user_mfa_security
configuration_user_mfa_category
configuration_user_mfa_exemption
admin_metadata
```

Setiap identifier mesti mempunyai:

- exact success URL yang dibina server-side;
- primary tab dan subtab yang mesti kelihatan;
- optional bounded `sessionStorage` resume key;
- resume hanya selepas tab selesai dibuka;
- query parameter dibersihkan dengan `history.replaceState`; dan
- zero automatic mutation jika context tidak dapat dipulihkan.

Untuk Active Sessions, urutan betul ialah:

```text
Step-Up success
→ dashboard membaca return context
→ buka tab Active Sessions
→ tunggu event tab shown
→ refresh listing jika perlu
→ consume pending opaque target
→ buka semula preview
```

Untuk Category/Exemption, gunakan return target berasingan. Jangan gunakan satu
`user_mfa_policy` generik bagi tiga subtab.

## 6. Minimum Contract

1. Set semua caller `return=*` sama tepat dengan set mapping Step-Up.
2. Set semua mapping sama tepat dengan handler dashboard.
3. Unknown return target jatuh ke dashboard dengan zero resume/mutation.
4. Active Sessions membuka tab sebelum pending preview dipanggil.
5. Locale, Login Banner, User MFA Security/Category/Exemption membuka subtab
   masing-masing.
6. Resume payload terikat kepada browser session, bounded dan dibersihkan.
7. Cancel/expired/wrong-purpose meninggalkan zero mutation.
8. File input tidak dipulihkan atau disimulasikan; UI meminta pemilihan semula.
9. BM/English dan mobile/desktop mengekalkan context yang sama.
10. Contract F7.4, AS0–AS3 dan configuration regression kekal lulus.

## 7. Keputusan Gate

Remediasi telah dibuat sebagai satu perubahan return-context bersama, bukan
patch khusus Active Sessions. Security enforcement kekal aktif; acceptance
akhir memerlukan staging UAT bagi setiap context dalam jadual.
