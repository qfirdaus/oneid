# R5.3 — Legacy Root Quarantine, Cleanup dan Rollback

Tarikh pelaksanaan: 14 Julai 2026  
Change ID: `R5-3-20260714-094844`  
Change owner: pemilik projek OneID  
Rollback owner: pemilik projek OneID

## Objektif

R5.3 mengurangkan struktur berganda selepas pertukaran Nginx kepada `public/` sebagai web root. Item yang telah dibuktikan tidak aktif dipindahkan ke quarantine dan **tidak dipadam kekal**. Struktur asal setiap item dikekalkan di bawah:

```text
storage/quarantine/R5-3-20260714-094844/payload/
```

Senarai sumber, klasifikasi dan sebab pemindahan direkodkan dalam `docs/R5_3_QUARANTINE_MOVE_MAP.tsv`.

## Sempadan keselamatan

Fail berikut sengaja dikekalkan walaupun berada di luar `public/`:

- `index.php`, `api.php`, `idms.php` dan `skp_api.php` kerana wrapper awam masih memanggil implementation ini.
- `admin/`, `page/` dan `lib/` kerana wrapper di `public/` masih memanggil PHP aktif di dalamnya.
- `vendors/spyc-master/` dan `vendors/device-detector-master/` kerana `lib/q_func.php` memerlukannya pada server side.
- `app/`, `bootstrap/`, `config/`, `resources/`, `src/`, `tests/` dan `tools/` kerana semuanya masih sebahagian daripada application, build atau verification layer.
- Build metadata seperti `Gruntfile.js`, `bower.json`, `.bowerrc` dan `package.json` belum dimodenkan dalam change ini.

## Bukti sebelum pemindahan

- Nginx root aktif ialah `/var/www/app/oneid-uat/public`.
- `assetsM`, `dist`, `img`, `public_docs` dan `videos` di root telah dibandingkan dengan salinan awam dan tiada perbezaan kandungan.
- 89 fail lama dalam root `public_img` telah tersedia dalam `public/public_img`; upload semasa menulis ke `public/public_img`.
- Browser vendor yang diduplikasi telah dibandingkan dengan `public/vendors/`.
- Carian caller tidak menemui penggunaan runtime bagi snapshot PHP lama dalam move map.
- Baseline sebelum cleanup: restructure smoke `10/10`, R5.2 characterization `70/70`, dan D8 parity `18/18` lulus.

Checksum sebelum pemindahan disimpan dalam:

```text
storage/quarantine/R5-3-20260714-094844/SHA256SUMS
```

## Perubahan

Status pelaksanaan: `PASS`

Sebanyak 38 sumber telah dipindahkan dengan struktur relatif asal ke `payload/`:

- 5,222 fail/symlink direkodkan;
- 112,699,218 byte dipindahkan;
- tiada symlink baharu dicipta;
- tiada kod production atau caller diwiring semula;
- semakan selepas move menemui `0` sumber tertinggal, `0` sumber hilang dalam quarantine dan `0` checksum mismatch.

Empat suite D5–D8 pada run pertama masih menganggap `cron/run_sync.php` sebagai runtime file wajib. Oleh sebab owner telah mengesahkan cron retired, characterization contracts D2, D3 dan D5–D8 dikemas kini untuk:

1. tidak lagi membaca atau mengunci checksum cron sebagai runtime aktif;
2. mengesahkan `cron/run_sync.php` tiada dari runtime;
3. terus mengunci checksum `lib/sync_user_runner.php`, `lib/Database.php` dan `lib/q_func.php`.

Ini ialah pembetulan expectation test terhadap keputusan cleanup, bukan perubahan production wiring.

## Verification selepas pemindahan

| Verification | Keputusan |
|---|---|
| Semua sumber dalam move map tiada di lokasi lama | PASS — `0` tertinggal |
| Semua sumber terdapat dalam quarantine | PASS — `0` hilang |
| Integriti payload selepas move | PASS — `0` checksum mismatch daripada 5,222 entry |
| Restructure smoke — `oneid.local` | PASS — `10/10` |
| Restructure smoke — `oneid-next.local` | PASS — `10/10` |
| R5.2 characterization — `oneid.local` | PASS — `70/70` |
| R5.2 characterization — `oneid-next.local` | PASS — `70/70` |
| R5.2 sync regression D0–D8 | PASS — `166/166` |
| PHP lint bagi implementation aktif | PASS — 63 fail |

## Rollback

Rollback keseluruhan boleh dilakukan dari root projek dengan arahan berikut:

```bash
cd /var/www/app/oneid-uat

QUARANTINE="storage/quarantine/R5-3-20260714-094844/payload"

tail -n +2 docs/R5_3_QUARANTINE_MOVE_MAP.tsv \
  | cut -f1 \
  | while IFS= read -r source; do
      install -d "$(dirname "$source")"
      mv -- "$QUARANTINE/$source" "$source"
    done
```

Selepas rollback, jalankan semula:

```bash
php tools/restructure_smoke.php https://oneid.local --insecure
php tools/restructure_smoke.php https://oneid-next.local
php tools/r52_characterization.php https://oneid.local --insecure
php tools/r52_characterization.php https://oneid-next.local
```

Jangan restore hanya sebahagian directory aset root sebagai penyelesaian kekal. Jika regresi berlaku, restore semua item dahulu, sahkan sistem pulih, kemudian siasat item berkenaan secara berasingan.

## Perkara ditangguhkan

- Pemindahan implementation PHP root ke `app/` belum dilakukan kerana wrapper awam masih bergantung padanya.
- Build pipeline lama masih menunjuk ke beberapa lokasi root dan perlu change tersendiri sebelum digunakan semula.
- Pengecilan kandungan third-party di dalam `public/vendors/` atau demo assets belum dibuat kerana memerlukan inventory URL yang lebih terperinci.
- Quarantine hanya boleh dipadam kekal selepas tempoh pemerhatian, backup disahkan, dan approval pemilik projek.

## Struktur aktif selepas cleanup

```text
oneid-uat/
├── public/       # satu-satunya web root dan semua aset awam
├── app/          # application services/classes baharu
├── bootstrap/    # bootstrap dan path helpers
├── config/       # konfigurasi application layer
├── admin/        # implementation PHP aktif di belakang public/admin wrappers
├── page/         # implementation PHP aktif di belakang public/page wrappers
├── lib/          # implementation/integration PHP aktif dan server-side libraries
├── resources/    # resources bukan awam
├── src/          # source build legacy; belum dimodenkan
├── tests/        # characterization/contract tests
├── tools/        # verification tools
├── vendors/      # hanya dependency PHP server-side yang masih diperlukan
├── storage/      # logs, cache dan quarantine
├── docs/         # audit, runbook dan change records
└── *.php         # implementation entry point legacy yang masih dipanggil wrapper public
```

Kewujudan PHP root bukan lagi salinan tidak digunakan. Ia ialah compatibility implementation layer dan hanya boleh dipindahkan kemudian bersama perubahan wrapper/caller yang berasingan.
