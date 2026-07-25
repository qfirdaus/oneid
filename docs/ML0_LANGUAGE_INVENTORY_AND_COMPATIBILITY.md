# ML0 Language Inventory and Compatibility Evidence

**Environment:** UAT
**Evidence reference:** `ONEID-ML0-20260725-01`
**Change reference:** `ONEID-ML0-20260724-01`
**Date:** 25 Julai 2026

> **Status semasa:** ML0 ialah **PASS / CLOSED**. Ayat authorization dalam
> dokumen ini merekod boundary pada masa ML0 ditutup. ML1 hingga
> Administrator Multilingual Completeness kemudiannya telah dilaksanakan dan
> ditutup pada Local WSL. Rujuk Bahagian 19 dalam
> `AUDIT_DAN_PELAN_PELAKSANAAN_MULTILANGUAGE_BM_ENGLISH.md` untuk status
> authoritative.

## Inventory contract

Inventori per-string dan per-location dijana secara read-only oleh:

```bash
php tools/ml0_language_inventory_contract.php --list
```

Scanner merangkumi fail PHP dan JavaScript bukan vendor serta mengasingkan
lokasi, channel dan teks calon yang boleh dilihat pengguna. Output `--list`
ialah manifest tab-separated yang boleh disimpan sebagai evidence UAT tanpa
mengubah source. Contract menetapkan coverage minimum dan surface kritikal:

| Surface | Fail utama | Channel |
|---|---|---|
| Login, Forgot Password dan OTP | `index.php` | HTML, JavaScript, AJAX feedback |
| Dashboard pengguna | `page/dashboard.php` | HTML, JavaScript, AJAX feedback |
| Dashboard Administrator | `admin/dashboard.php` | HTML, JavaScript, operational feedback |
| Admin Step-Up | `page/admin_step_up.php` | HTML, security feedback |
| API/AJAX legacy | `lib/q_func.php` | response code dan legacy `msg` |
| E-mel umum | `app/Mail/OneIdEmailTemplate.php` | subject dan body |
| E-mel Admin Step-Up | `app/Auth/AdminStepUpPhpMailerSender.php` | subject dan body keselamatan |

`Unresolved critical strings: 0` bermaksud semua surface kritikal mempunyai
lokasi inventori dan owner/reviewer yang ditetapkan. Ia tidak bermaksud semua
teks telah diterjemahkan; translation catalogue hanya boleh dibina selepas ML1
diluluskan.

## Literal-dependent compatibility mapping

| Consumer semasa | Contract peralihan | Keputusan |
|---|---|---|
| Frontend membaca `response.msg` | Frontend baharu membaca stable response code dan translation key; `msg` kekal fallback | Retain legacy |
| Characterization mencari ayat literal | Ubah secara berfasa kepada assertion response code/translation key | Mapped for ML1+ |
| SweetAlert/toast literal | Title/body menjadi translation key dengan placeholder bertipe | Mapped for ML3+ |
| E-mel literal | Subject/body menggunakan catalogue mengikut locale penerima | Mapped for ML3 |
| Exact Apply confirmation | Paparan arahan boleh diterjemah tetapi phrase canonical kekal invariant | Frozen |
| Audit/correlation/error identifiers | Nilai teknikal tidak diterjemahkan | Frozen |

Legacy `msg` hanya boleh dibuang selepas 100% active response code mempunyai
translation mapping, semua consumer dipindahkan, regression test lulus dan
sekurang-kurangnya satu release observation selesai. Pembuangannya memerlukan
change approval berasingan.

## Evidence commands

```bash
php tools/ml0_language_inventory_contract.php
php tools/version_documentation_contract.php
php tools/release_metadata_contract.php
```

ML0 tidak memasang locale resolver, catalogue, switcher, cookie atau migration.
Semua implementasi tersebut kekal di bawah authorization ML1 yang berasingan.
