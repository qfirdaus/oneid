# OneID Version Numbering Policy

**Diluluskan:** 19 Julai 2026
**Baseline selepas normalisasi:** 2.4.0
**Versi semasa:** 2.6.0

## Polisi

OneID menggunakan format `MAJOR.MINOR.PATCH` dengan patch terhad kepada `0`
hingga `4` bagi setiap siri minor:

```text
2.0.0, 2.0.1, 2.0.2, 2.0.3, 2.0.4
2.1.0, 2.1.1, 2.1.2, 2.1.3, 2.1.4
2.2.0, 2.2.1, ...
```

Selepas `MAJOR.MINOR.4`, release seterusnya mesti menjadi
`MAJOR.(MINOR+1).0`. Major hanya berubah melalui keputusan major upgrade
berasingan.

## Normalisasi Sejarah 2.x

Release lama dipetakan tanpa mengubah urutan atau kandungan:

| Lama | Baharu |
|---|---|
| 2.0.0 hingga 2.0.4 | Kekal |
| 2.0.5 hingga 2.0.9 | 2.1.0 hingga 2.1.4 |
| 2.0.10 hingga 2.0.14 | 2.2.0 hingga 2.2.4 |
| 2.0.15 hingga 2.0.19 | 2.3.0 hingga 2.3.4 |
| 2.0.20 | 2.4.0 |

Commit Git lama kekal immutable dan mungkin mengandungi mesej versi lama.
Normalisasi ini mengawal metadata aplikasi, footer, sejarah release UI dan
dokumen aktif selepas keputusan ini. Versi dependency pihak ketiga tidak
tertakluk kepada polisi OneID.

## Gate Release

1. Ubah `ONEID_APP_VERSION` dalam `config/application.php`.
2. Selaraskan `package.json` dan latest release card.
3. Pastikan patch berada antara 0 hingga 4.
4. Pastikan release baharu ialah successor tepat kepada release sebelumnya.
5. Jalankan `php tools/release_metadata_contract.php` sebelum commit.
6. Jalankan `php tools/version_documentation_contract.php` untuk memastikan
   nombor legacy tidak muncul semula di luar jadual pemetaan dokumen ini.
