# Audit dan Pelan Implementasi Pengurusan Banner Login OneID

**Tarikh audit:** 1 Ogos 2026  
**Environment audit:** Local WSL, dengan sasaran UAT/Staging  
**Status:** LB0-LB8 COMPLETE / STAGING UAT OWNER ACCEPTED / FEATURE ACTIVE
**Skop:** Banner carousel pada halaman login awam dan pengurusannya melalui
dashboard Administrator  
**Mutation runtime/schema:** Tiada; dokumen ini sahaja  

## 1. Tujuan

Dokumen ini merekodkan baseline, kemungkinan reka bentuk, keputusan yang
disyorkan dan task implementasi supaya Administrator boleh mengurus banner
halaman login tanpa mengubah source code atau menjalankan deployment bagi setiap
pertukaran kandungan.

Penyediaan dokumen ini tidak memberi kebenaran untuk:

- mengubah schema database;
- menambah endpoint atau menu Administrator;
- memindah, mengganti atau memadam banner semasa;
- menulis ke filesystem staging;
- mengaktifkan kandungan baharu; atau
- push/deploy ke staging atau Production.

## 2. Baseline semasa

Banner login kini dirender terus dalam `index.php` menggunakan Bootstrap
carousel:

- `public/assetsM/images/banner6.png` ialah slide aktif pertama;
- `public/assetsM/images/banner7.png` ialah slide kedua;
- `banner5.png` masih berada dalam source tetapi slide-nya dikomen;
- semua path, urutan dan status aktif ditulis terus dalam HTML;
- kedua-dua banner mempunyai alt text generik `OneID@UPNM`;
- tiada jadual database, repository, service atau API khusus banner;
- tiada menu Administrator untuk banner;
- tiada sejarah versi, jadual penerbitan atau rollback banner; dan
- perubahan memerlukan penggantian fail/source, Git dan deployment.

Kedua-dua banner aktif semasa audit ialah PNG `3780 x 1890` dengan nisbah `2:1`,
bersaiz kira-kira 3.4 MB dan 3.5 MB. CSS menggunakan `max-height: 300px` dan
`object-fit: cover`. Browser boleh memuat turun hampir 7 MB hanya untuk dua
slide dan crop boleh berlaku apabila nisbah ruang paparan bukan `2:1`.

Kawalan platform yang boleh digunakan semula:

- session Administrator, active SSO-token check dan Admin Step-Up;
- CSRF boundary dan action allowlist dalam `lib/request_security.php`;
- MIME/content/dimension validation melalui `finfo` dan `getimagesize`;
- non-public upload staging, random filename, re-encode dan atomic publish dalam
  aliran aset Web Apps;
- `syslog_record()` dan configuration history pattern;
- locale BM/English melalui `oneid_current_locale()`; dan
- pattern aset khusus environment melalui `sp_app_asset`.

Validator ikon Web Apps tidak boleh digunakan terus kerana ia menghasilkan
canvas `256 x 256`; banner memerlukan validator dan normalizer khusus dengan
nisbah lebar.

## 3. Objektif fungsi

Administrator perlu boleh:

1. melihat semua banner dan status semasanya;
2. mencipta draft banner;
3. upload imej BM dan, jika perlu, imej English;
4. memilih `guna imej BM yang sama untuk English` tanpa upload pendua;
5. menetapkan alt text BM dan English;
6. preview desktop dan mobile sebelum publish;
7. menetapkan urutan paparan;
8. aktif/nonaktif dan menjadualkan tarikh mula/tamat;
9. mengganti aset tanpa memusnahkan versi yang sedang live;
10. publish secara atomik selepas pengesahan;
11. melihat siapa mengubah atau menerbitkan banner; dan
12. rollback kepada versi terdahulu yang masih sah.

Halaman login perlu:

- memilih banner berdasarkan locale semasa;
- menggunakan imej yang sama bagi BM/English apabila dikonfigurasi begitu;
- menggunakan fallback terkawal apabila variant locale tiada;
- sentiasa mempunyai fallback statik jika database/storage gagal;
- memaparkan hanya banner aktif dalam waktu penerbitannya;
- menghasilkan tepat satu `.active` carousel item;
- tidak merosakkan login apabila tiada banner dinamik; dan
- mengurangkan payload imej serta layout shift.

## 4. Dapatan audit

| ID | Dapatan | Risiko/kesan | Keutamaan |
| --- | --- | --- | --- |
| LB-F01 | Banner dan urutannya hardcoded dalam `index.php`. | Setiap perubahan bergantung kepada developer dan deployment. | Tinggi |
| LB-F02 | Tiada lifecycle draft, publish atau rollback. | Kandungan salah boleh terus kelihatan kepada semua pengguna. | Tinggi |
| LB-F03 | Tiada audit khusus perubahan banner. | Tidak dapat dibuktikan siapa menerbitkan imej tertentu. | Tinggi |
| LB-F04 | Dua imej aktif hampir 7 MB. | Login lambat dan penggunaan data tinggi. | Tinggi |
| LB-F05 | Imej tidak mempunyai cache-busting immutable filename yang diurus aplikasi. | Browser/proxy boleh terus memaparkan versi lama selepas overwrite. | Tinggi |
| LB-F06 | Alt text generik dan tidak mengikut locale. | Accessibility serta konteks BM/English tidak lengkap. | Sederhana |
| LB-F07 | CSS `object-fit: cover` boleh memotong kandungan di desktop/mobile. | Teks atau maklumat penting dalam imej boleh hilang. | Tinggi |
| LB-F08 | Tiada validation banner khusus. | Fail terlalu besar, pixel bomb, metadata atau format tidak sesuai boleh diterbitkan. | Kritikal |
| LB-F09 | Shared database tetapi filesystem local/staging berasingan. | Satu row global boleh merujuk fail yang tidak wujud pada environment lain. | Kritikal |
| LB-F10 | Tiada polisi locale/fallback. | Pengguna English boleh menerima kandungan BM secara senyap atau tiada banner. | Tinggi |
| LB-F11 | Semua Administrator semasa berkongsi kelas akses yang sama. | Tiada pemisahan content editor dan publisher. | Sederhana |
| LB-F12 | Tiada invariant sekurang-kurangnya satu fallback tersedia. | Kesilapan admin boleh mengosongkan ruang banner. | Tinggi |
| LB-F13 | Tiada reconciliation referenced/missing/orphan asset. | Fail hilang atau orphan boleh terkumpul tanpa dikesan. | Sederhana |
| LB-F14 | Tiada polisi pautan banner. | Jika link ditambah secara bebas, ia boleh menjadi open redirect/phishing surface. | Tinggi |
| LB-F15 | Jadual penerbitan belum menetapkan timezone dan boundary. | Banner boleh aktif terlalu awal/lewat atau bertindih. | Sederhana |

## 5. Model multilingual

### 5.1 Keputusan disyorkan

Banner ialah satu kempen/logical record dengan variant locale, bukan dua kempen
yang tidak berkaitan.

Peraturan:

```text
Locale BM
  -> gunakan aset BM

Locale EN
  -> gunakan aset EN jika ada
  -> jika "same as BM" dipilih, gunakan asset_id BM yang sama
  -> jika EN tiada dan fallback diluluskan, gunakan aset neutral/BM
  -> jika fallback tidak diluluskan, jangan terbitkan banner itu untuk EN
```

Admin tidak perlu upload fail yang sama dua kali. Kedua-dua locale boleh
merujuk `asset_id` yang sama. Alt text masih berasingan mengikut locale walaupun
imej sama.

### 5.2 Kemungkinan yang disokong

| Senario | Tingkah laku |
| --- | --- |
| Imej neutral tanpa teks | Satu aset dirujuk oleh BM dan EN; alt text berasingan |
| Imej BM dan EN sama | Admin pilih `same as BM`; tiada fail pendua |
| Imej BM dan EN berlainan | Setiap locale mempunyai asset sendiri |
| EN belum tersedia | Draft tidak boleh dipublish ke EN kecuali fallback eksplisit dipilih |
| Hanya EN tersedia | BM perlu asset/fallback sendiri; EN tidak menjadi fallback BM secara automatik |
| Locale tidak dikenali | Gunakan system default locale, kemudian static fallback |

### 5.3 Pilihan yang tidak disyorkan

1. **Dua row banner bebas bagi BM dan EN.** Urutan, jadual dan lifecycle mudah
   berbeza tanpa sengaja.
2. **Wajib upload dua fail.** Menghasilkan duplikasi apabila imej sama.
3. **Satu fail sahaja tanpa metadata locale.** Tidak dapat mengawal banner yang
   mengandungi teks bahasa tertentu.
4. **Fallback senyap.** Pengguna tidak dapat membezakan kandungan neutral dengan
   kandungan yang belum diterjemahkan.

## 6. Pilihan seni bina

### Pilihan A — Overwrite fail tetap

Admin menggantikan `banner6.png` atau `banner7.png`.

Kelebihan: perubahan kod dan schema kecil.  
Kekurangan: cache stale, race condition, rollback lemah, tiada version history,
deployment boleh menimpa fail, dua environment sukar diasingkan.  
**Keputusan:** Ditolak.

### Pilihan B — Senarai fail konfigurasi tanpa database

Metadata disimpan sebagai JSON/PHP manifest dan imej disimpan pada filesystem.

Kelebihan: tiada migration database.  
Kekurangan: concurrent write/locking, audit query dan shared-state deployment
sukar; pengurusan dua environment masih lemah.  
**Keputusan:** Tidak disyorkan untuk fungsi admin mutable.

### Pilihan C — Metadata database dan aset persistent khusus environment

Logical banner, locale, lifecycle dan history disimpan dalam database. Fail
normalisasi disimpan dalam runtime asset directory setiap environment dengan
filename immutable. Mapping aset mengandungi environment eksplisit.

Kelebihan: audit, scheduling, rollback, locale, concurrency dan reconciliation
boleh dikawal.  
Kekurangan: memerlukan migration, service, endpoint, UI dan operational storage
backup.  
**Keputusan:** Disyorkan.

### Pilihan D — Object storage/CDN

Metadata kekal dalam database tetapi fail diterbitkan ke object storage.

Kelebihan: cache dan delivery lebih baik, aset boleh dikongsi antara node.  
Kekurangan: credential, bucket policy, network dependency dan operasi baharu.  
**Keputusan:** Future option; bukan keperluan fasa pertama kecuali owner memang
mempunyai storage yang diluluskan.

## 7. Model data disyorkan

Nama akhir tertakluk kepada baseline schema, tetapi pemisahan konsep berikut
perlu dikekalkan.

### `login_banner`

```text
banner_id             BIGINT/UUID primary key
banner_key            identifier stabil dan unik
status                DRAFT | PUBLISHED | INACTIVE | ARCHIVED
display_order         integer positif
starts_at_utc          nullable
ends_at_utc            nullable
configuration_version optimistic concurrency version
created_by
updated_by
created_at
updated_at
```

### `login_banner_translation`

```text
banner_id
locale                 ms | en
alt_text               wajib apabila locale diterbitkan
fallback_policy        OWN_ASSET | SAME_AS_MS | EXPLICIT_FALLBACK
PRIMARY KEY (banner_id, locale)
```

Copy lain seperti title/caption tidak perlu dimasukkan pada fasa pertama kerana
paparan semasa ialah image-only. Jika kemudian ditambah, ia mesti menjadi teks
HTML escaped, bukan teks yang disuntik ke imej oleh server.

### `login_banner_asset`

```text
asset_id
banner_id
environment            local | staging | production
source_locale           neutral | ms | en
image_filename          random immutable filename
mime_type               image/webp atau format output diluluskan
width
height
byte_size
sha256_digest
storage_status          STAGED | AVAILABLE | QUARANTINED
created_by
created_at
```

Mapping translation boleh merujuk asset yang sama bagi BM dan EN supaya upload
pendua tidak berlaku.

### `login_banner_history`

```text
history_id
banner_id
configuration_version_before/after
actor_id
ip_address
action_name
outcome                 SUCCESS | REJECTED
reason_code
change_reason
before_json
after_json
correlation_id          unique
created_at
```

Jangan simpan binary imej atau kandungan base64 dalam database. Jangan simpan
absolute filesystem path. `environment` mesti datang daripada konfigurasi
runtime seperti `ONEID_ENVIRONMENT`, bukan HTTP Host.

## 8. Lifecycle dan aliran publish

```text
Upload
  -> validate request dan file
  -> decode/re-encode ke non-public staging
  -> cipta draft metadata
  -> preview
  -> Admin Step-Up + sebab perubahan
  -> lock/version check
  -> publish fail dengan immutable filename
  -> commit metadata + locale mapping + audit
  -> invalidate application manifest cache
  -> banner muncul pada request login seterusnya
```

Prinsip penting:

- upload/draft tidak terus mengubah halaman login;
- publish ialah satu tindakan eksplisit;
- fail live lama tidak dioverwrite;
- jika publish fail atau audit gagal, active manifest lama kekal;
- banner yang sedang dipaparkan tidak boleh dipadam secara fizikal;
- archive hanya mengeluarkan banner daripada manifest;
- cleanup fizikal melalui quarantine dan grace period berasingan; dan
- perubahan urutan beberapa banner perlu dilakukan sebagai satu transaction.

## 9. Validation dan keselamatan

### 9.1 Authorization

- hanya sesi Administrator aktif;
- token SSO masih aktif;
- CSRF wajib untuk semua mutation;
- publish, archive, reorder dan rollback memerlukan Admin Step-Up purpose
  `SECURITY_CONFIGURATION_CHANGE`;
- semua action ditambah secara eksplisit kepada request action map;
- endpoint direct request menerima kawalan sama seperti UI; dan
- keputusan owner diperlukan sama ada semua admin boleh publish atau hanya
  content publisher tertentu.

### 9.2 Fail

- format input dicadangkan: JPEG, PNG dan WebP statik;
- SVG, GIF, animated WebP/PNG dan fail tidak dikenali ditolak;
- semak `UPLOAD_ERR_*`, actual size, `finfo`, `getimagesize`, dimension dan pixel
  count;
- decode dan re-encode server-side untuk membuang metadata/embedded payload;
- output disyorkan WebP dengan fallback JPEG jika GD build tidak menyokong
  WebP;
- jangan mempercayai extension atau filename client;
- random filename + SHA-256 digest;
- non-public staging permission `0700/0600`;
- published asset read-only kepada web process jika deployment membenarkan;
- had upload awal dicadangkan 5 MB, maksimum 4096 x 2048 dan 16 juta piksel;
- standard output dicadangkan `1600 x 800`, nisbah `2:1`; dan
- kandungan penting perlu berada dalam safe area tengah supaya crop responsive
  tidak memotong teks/logo.

Nilai saiz/dimensi akhir perlu diluluskan melalui visual UAT, bukan dianggap
keputusan muktamad oleh dokumen ini.

### 9.3 Metadata

- alt text wajib, trimmed dan panjang dicadangkan 5-160 aksara;
- change reason minimum 10 aksara;
- masa mula mesti sebelum masa tamat;
- masa disimpan UTC, UI memaparkan `Asia/Kuala_Lumpur`;
- `display_order` dinormalisasi tanpa duplicate/gap selepas reorder;
- output di-escape menggunakan `htmlspecialchars`;
- tiada HTML bebas dalam title/alt/reason; dan
- URL klik banner dikecualikan daripada fasa pertama.

Jika pautan banner diperlukan kemudian, hanya HTTPS, tanpa credential/fragment,
dengan domain allowlist dan label destinasi yang jelas. Pautan tidak boleh
ditambah sebagai string bebas pada fasa awal.

## 10. Rendering, availability dan prestasi

Reader awam perlu read-only dan fail-safe:

1. baca manifest banner published bagi environment dan waktu semasa;
2. pilih variant locale/fallback yang diluluskan;
3. buang row yang asset-nya missing atau checksum/metadata tidak sah;
4. susun `display_order`, kemudian ID sebagai deterministic tie-breaker;
5. tandakan item pertama sahaja sebagai carousel `active`;
6. jika satu banner, sembunyikan controls carousel;
7. jika tiada banner dinamik sah, render banner statik semasa;
8. jika repository/database error, login form mesti terus berfungsi; dan
9. error direkod tanpa mendedahkan path atau SQL kepada pengguna.

Prestasi:

- filename content-addressed/immutable membolehkan cache panjang;
- banner pertama `loading="eager"` dan `fetchpriority="high"`;
- banner berikutnya `loading="lazy"`;
- tetapkan `width`/`height` untuk mengurangkan layout shift;
- gunakan `srcset` jika fasa responsive derivative diluluskan;
- target payload disyorkan maksimum 500 KB setiap derivative;
- cache manifest pendek atau invalidated selepas publish; dan
- jangan meletakkan query timestamp yang berubah pada setiap request.

## 11. UI Administrator disyorkan

Lokasi: `Administrator > Konfigurasi > Banner Login`.

Paparan senarai:

- thumbnail BM/EN;
- status draft/published/inactive/scheduled/expired;
- urutan;
- waktu mula/tamat dalam waktu Malaysia;
- locale coverage;
- updated by/time;
- warning jika asset environment semasa tiada; dan
- tindakan Preview, Edit, Publish, Inactivate, Reorder, History dan Rollback.

Form editor:

- dropzone/upload BM;
- checkbox `Gunakan imej BM yang sama untuk English`;
- upload EN hanya muncul jika checkbox dimatikan;
- alt text BM dan English;
- waktu mula/tamat optional;
- preview desktop/tablet/mobile;
- size/dimension/ratio feedback;
- perbandingan live versus draft; dan
- reason/reference sebelum publish.

UI mesti mempunyai loading state, double-submit guard, outcome code dan
correlation reference. “Berjaya” hanya dipaparkan selepas asset, metadata dan
audit konsisten.

## 12. Shared database dan filesystem berasingan

WSL dan staging berkongsi database tetapi tidak berkongsi filesystem. Oleh itu:

- logical banner dan lifecycle boleh dikongsi hanya jika owner mahu kandungan
  sama merentas environment;
- setiap asset row wajib mempunyai `environment`;
- upload local tidak boleh menyebabkan staging mengaktifkan filename local;
- publish perlu menyemak semua asset untuk environment semasa tersedia;
- production tidak boleh fallback kepada fail staging/local;
- migration database hanya diaplikasikan sekali pada shared database;
- rollout kod perlu expanding dan backward-compatible ketika versi deployment
  berbeza; dan
- backup/restore filesystem dinilai berasingan daripada backup database.

Dua mode operasi yang mungkin:

1. **Environment-local publish (disyorkan fasa awal):** admin staging mengurus
   aset dan publication staging sahaja.
2. **Promote manifest antara environment:** metadata versi diluluskan dipromote,
   tetapi binary asset mesti disalin melalui proses release terkawal dan
   checksum disahkan. Ini future work, bukan salinan automatik tersirat.

## 13. Audit, monitoring dan reconciliation

Event minimum:

- `LOGIN_BANNER_DRAFT_CREATED`;
- `LOGIN_BANNER_ASSET_UPLOADED`;
- `LOGIN_BANNER_PUBLISH_SUCCESS/REJECTED`;
- `LOGIN_BANNER_REORDER_SUCCESS/REJECTED`;
- `LOGIN_BANNER_INACTIVATED`;
- `LOGIN_BANNER_ROLLBACK_SUCCESS/REJECTED`; dan
- `LOGIN_BANNER_ASSET_RECONCILIATION`.

Audit tidak menyimpan binary, absolute path atau data authentication. Before dan
after merangkumi ID, locale mapping, digest, order, schedule dan status.

Reconciliation read-only perlu melaporkan mengikut environment:

- referenced and available;
- referenced but missing;
- file present but unreferenced;
- digest/dimension mismatch;
- published banner tanpa locale variant yang sah;
- schedule overlap atau zero-active outcome; dan
- temp/staged file melebihi TTL.

Cleanup tidak dijalankan bersama reconciliation. Orphan dipindahkan ke
quarantine selepas owner meluluskan manifest tepat, kemudian dipadam selepas
grace period yang diputuskan.

## 14. Pelan task berfasa

### LB0 — Baseline dan keputusan owner

- rekod caller map login carousel, locale switch, admin tab dan request guard;
- inventori banner tracked/runtime bagi local dan staging secara berasingan;
- sahkan `ONEID_ENVIRONMENT` bagi setiap deployment;
- sahkan siapa boleh draft/publish;
- putuskan standard imej, fallback locale, schedule dan retention;
- sediakan decision register dan contract read-only.

**Gate keluar:** semua keputusan dalam Seksyen 17 mempunyai owner dan status.

### LB1 — Schema dormant dan persistence contract

- tambah migration up/down untuk banner, translation, asset dan history;
- guna FK, unique constraints, status CHECK dan optimistic version;
- tambah repository read/write dengan exact affected-row contract;
- migration bersifat additive dan tidak mengubah `index.php`;
- jalankan rehearsal, rollback dan shared-DB inventory.

**Gate keluar:** schema dormant PASS, tiada perubahan pada login banner live.

### LB2 — Secure banner image pipeline

- bina validator/normalizer khusus banner;
- stage di luar public directory;
- enforce format, size, dimensions, pixels, animation dan ratio policy;
- re-encode, random immutable filename, digest dan atomic publish;
- tambah compensation bagi setiap failure point;
- characterization untuk spoof MIME, corrupt image, oversized/pixel bomb dan
  publish failure.

**Gate keluar:** fail ditolak secara fail-closed dan tiada orphan baharu dalam
simulasi kegagalan.

### LB3 — Banner domain service dan audit

- create/update draft;
- locale mapping termasuk `same as BM` tanpa duplicate file;
- publish/inactivate/reorder/rollback secara transactional;
- step-up, reason, version check dan correlated audit;
- enforce schedule, locale coverage dan at-least-one-fallback invariant;
- response code stabil dan tiada false success.

**Gate keluar:** service contract membuktikan atomicity dan audit outcome tepat.

### LB4 — Endpoint dan request boundary

- tambah action read/mutation kepada allowlist yang betul;
- CSRF, admin, active-token dan step-up enforcement;
- JSON response localization BM/English;
- rate/size limits dan consistent HTTP status;
- direct-request rejection tests.

**Gate keluar:** unauthorized, CSRF, expired step-up dan malformed upload tidak
mengubah database/filesystem.

### LB5 — UI Administrator

- tambah tab Banner Login dalam Konfigurasi;
- list, editor, upload, same-image checkbox dan locale alt text;
- preview responsive, scheduling, ordering dan publish confirmation;
- history, correlation reference dan rollback preview;
- accessibility keyboard/focus/live-region;
- responsive browser test.

**Gate keluar:** admin boleh mengurus draft dan publish di staging tanpa source
edit atau false feedback.

### LB6 — Dynamic login renderer

- bina read-only manifest/repository reader;
- locale selection dan explicit fallback;
- deterministic active item dan controls behavior;
- immutable asset URL, lazy/eager loading dan dimensions;
- static fallback apabila schema/DB/storage tiada;
- CSP/output escaping dan login regression tests.

**Gate keluar:** kegagalan banner tidak pernah menghalang password atau
MyDigital ID login.

### LB7 — Reconciliation, backup dan rollback

- laporan missing/referenced/orphan per environment;
- backup database dan runtime asset directory;
- quarantine workflow tanpa automatic deletion;
- rollback code, schema, manifest dan asset;
- bukti restore checksum.

**Gate keluar:** exact rollback rehearsal PASS dan banner lama boleh dipulihkan.

### LB8 — Staging UAT dan controlled activation

- upload imej neutral yang sama bagi BM/EN;
- upload pasangan BM/EN berbeza;
- fallback EN, missing asset dan invalid file tests;
- schedule boundary dalam Asia/Kuala_Lumpur;
- reorder, inactivate, rollback dan concurrent admin test;
- desktop/mobile, slow network, cache dan hard-refresh test;
- audit log dan reconciliation review;
- owner visual/content/accessibility acceptance.

**Gate keluar:** owner memberi GO eksplisit sebelum Production planning.

### LB9 — Production readiness (future, berasingan)

- production environment asset inventory;
- storage owner, backup, monitoring dan retention approval;
- exact change window dan rollback trigger;
- promote/copy mechanism dengan checksum jika digunakan;
- post-deployment login availability smoke test.

**Gate keluar:** semua Production gate ditutup; staging PASS sahaja bukan
authorization Production.

## 15. Test matrix minimum

### Functional

- zero, satu dan banyak banner;
- BM/EN same asset dan different asset;
- draft tidak muncul public;
- publish, reorder, inactive, scheduled, expired dan rollback;
- locale cookie/session/query flow;
- static fallback.

### Security

- unauthenticated/non-admin/direct POST;
- missing/invalid/replayed CSRF;
- absent/expired/wrong-purpose step-up;
- MIME spoof, polyglot/corrupt file, SVG, animation, decompression/pixel limit;
- filename traversal dan collision;
- stored metadata escaping;
- concurrent version conflict;
- audit-write and filesystem-write failure.

### Reliability

- database unavailable;
- storage directory unavailable/read-only;
- missing asset after metadata publish;
- transaction rollback dan publish compensation;
- partial deployment antara shared-DB environments;
- manifest cache invalidation;
- backup/restore checksum.

### UI/accessibility/performance

- desktop, tablet dan mobile crop/safe area;
- keyboard carousel controls dan focus visibility;
- meaningful localized alt text;
- reduced motion behavior mengikut Bootstrap capability/policy;
- first banner priority dan subsequent lazy load;
- payload, layout shift dan no-console-error;
- login password, Forgot Password dan MyDigital ID regression.

## 16. Acceptance criteria keseluruhan

1. Admin boleh menukar banner staging tanpa source edit, Git atau deployment.
2. Draft tidak pernah kelihatan sebelum publish eksplisit.
3. BM/EN boleh berkongsi satu fail tanpa duplicate upload.
4. BM/EN boleh mempunyai imej berbeza apabila diperlukan.
5. Alt text dipilih mengikut locale walaupun imej sama.
6. Upload local tidak mencipta broken banner di staging dan sebaliknya.
7. Fail invalid/oversized/animated tidak menghasilkan mutation atau orphan.
8. Setiap publish mempunyai actor, reason, before/after, digest dan correlation.
9. Concurrent update tidak menimpa perubahan admin lain secara senyap.
10. Kegagalan DB/storage/banner tidak menghalang login.
11. Banner live menggunakan immutable URL dan cache tidak memaparkan versi lama.
12. Sekurang-kurangnya satu fallback visual sentiasa tersedia.
13. Rollback memulihkan manifest terdahulu tanpa overwrite binary.
14. Reconciliation boleh mengesan missing dan orphan mengikut environment.
15. Browser UAT BM/EN desktop/mobile dan login regression lulus.

## 17. Keputusan owner yang masih diperlukan

| ID | Keputusan | Cadangan awal | Status |
| --- | --- | --- | --- |
| LB-D01 | Siapa boleh publish | Semua admin boleh draft/publish dalam fasa satu; publish dilindungi Step-Up dan audit | CONFIRMED |
| LB-D02 | Standard output | WebP `1600 x 800`, nisbah `2:1`, target <=500 KB | CONFIRMED |
| LB-D03 | Input maksimum | 5 MB, 4096 x 2048, <=16 juta piksel | CONFIRMED |
| LB-D04 | Polisi EN tiada | Explicit `same as BM` atau jangan publish kepada EN | CONFIRMED |
| LB-D05 | Alt text | BM dan EN wajib bagi locale yang diterbitkan | CONFIRMED |
| LB-D06 | Scheduling | Optional, UTC storage, Asia/Kuala_Lumpur display | CONFIRMED |
| LB-D07 | Pautan banner | Tidak termasuk fasa pertama | CONFIRMED |
| LB-D08 | Bilangan maksimum aktif | Maksimum 5 banner bagi effective window yang sama | CONFIRMED |
| LB-D09 | Tempoh carousel | 6 saat setiap slide, dengan controls kekal tersedia | CONFIRMED |
| LB-D10 | Retention versi/aset | Minimum 90 hari sebelum cleanup eligibility | CONFIRMED |
| LB-D11 | Environment publish | Environment-local bagi fasa pertama | CONFIRMED |
| LB-D12 | Production promotion | Manual controlled promotion + SHA-256 verification | CONFIRMED |

Keputusan penuh, owner action dan boundary direkod dalam
`docs/LB0_LOGIN_BANNER_DECISION_REGISTER.tsv`.

## 18. LB0 checkpoint — 1 Ogos 2026

LB0 telah selesai secara zero-mutation:

- caller map merekod `24` surface/callers;
- decision register merekod `12/12 CONFIRMED`;
- inventory read-only mengesahkan `5` tracked banner candidates;
- kelima-lima fail ialah PNG `3780 x 1890` dan bernisbah `2:1`;
- `2` banner berada dalam live markup dan tepat `1` item active;
- login-banner migration: `0`;
- login-banner runtime service: `0`; dan
- `php tools/lb0_login_banner_contract.php`: `8/8 PASS`.

Static login output tidak berubah. LB0 completion tidak memberi authorization
kepada migration/schema LB1, runtime implementation, staging deployment atau
Production.

## 19. LB1 checkpoint — 1 Ogos 2026

LB1 telah dilaksanakan secara dormant dan lulus secara lokal:

- migration additive `up/down` menyediakan `5` jadual;
- schema memisahkan banner, translation, environment asset, locale mapping dan
  correlated history;
- dua locale boleh berkongsi asset sama tanpa cross-banner/environment mapping;
- WebP `1600 x 800`, had 512,000 byte, immutable filename dan SHA-256 dikunci
  pada schema;
- repository mempunyai schema detection, transaction rollback, scheduled
  published reader, row lock dan optimistic version update;
- `php tools/lb1_login_banner_contract.php`: `12/12 PASS`;
- LB0 regression contract kekal `8/8 PASS`;
- `index.php`, admin dashboard dan request action map tidak di-wire; dan
- migration belum diaplikasikan kepada shared database.

Dokumen terperinci: `docs/LB1_SCHEMA_DORMANT_DAN_PERSISTENCE_BANNER_LOGIN.md`.

## 20. LB2 checkpoint — 1 Ogos 2026

LB2 secure image pipeline telah lulus secara dormant:

- upload provenance default menggunakan `is_uploaded_file()`;
- MIME sebenar, image signature, byte, dimension, pixel dan ratio disahkan;
- JPEG/PNG/WebP statik sahaja diterima;
- imej didecode dan re-encode kepada WebP `1600 x 800`;
- quality adaptif mengekalkan output maksimum 512,000 byte;
- staging private, immutable random filename, exact SHA-256 dan atomic publish;
- publish melakukan validation kedua dan tidak overwrite target;
- staged/published compensation dikunci kepada direktori dan filename sah;
- behavioral characterization: `13/13 PASS`;
- source contract: `10/10 PASS`;
- LB0 kekal `8/8 PASS` dan LB1 kekal `12/12 PASS`; dan
- tiada endpoint, UI, database apply atau login wiring.

Dokumen terperinci: `docs/LB2_SECURE_IMAGE_PIPELINE_BANNER_LOGIN.md`.

## 21. LB3 checkpoint — 1 Ogos 2026

LB3 domain service dan atomic audit telah lulus secara dormant:

- persistence dan image pipeline dipisahkan melalui interface;
- create draft menggabungkan stage, DB transaction, publish, locale mapping dan
  mandatory audit dengan compensation;
- BM/EN same-image menggunakan satu asset dan dua mapping;
- publish menguatkuasa locale completeness, AVAILABLE asset, same-as-BM mapping,
  maximum overlap dan optimistic version;
- state transition publish/inactivate dikawal;
- reorder batch dan rollback previous state adalah transactional;
- success audit atomic dan rejected audit best-effort correlated;
- behavioral characterization: `13/13 PASS`;
- source contract: `10/10 PASS`; dan
- tiada HTTP, UI, migration apply atau login wiring.

Dokumen terperinci:
`docs/LB3_DOMAIN_SERVICE_DAN_AUDIT_ATOMIC_BANNER_LOGIN.md`.

## 22. Rollback prinsip

Rollback mesti dipisahkan:

1. **Content rollback:** aktifkan manifest/version banner terdahulu.
2. **Code rollback:** reader baharu kembali kepada dua banner statik.
3. **Schema rollback:** hanya selepas semua deployment lama/baru tidak lagi
   bergantung pada jadual banner dan backup disahkan.
4. **Asset rollback:** immutable file lama dikekalkan; tiada overwrite diperlukan.
5. **Operational rollback:** jika halaman login terganggu, feature flag dynamic
   banner dimatikan dan static fallback digunakan serta-merta.

Migration down tidak boleh menjadi langkah pertama ketika incident. Availability
login dan static fallback didahulukan.

## 23. Status handoff

Audit mengesahkan fungsi ini boleh dibina dengan selamat menggunakan capability
sedia ada, tetapi implementasi bukan perubahan UI kecil. Ia melibatkan public
authentication surface, mutable upload, shared database, filesystem khusus
environment, locale selection dan audit/rollback.

Urutan yang disyorkan ialah `LB0 -> LB1 -> LB2 -> LB3 -> LB4 -> LB5 -> LB6 ->
LB7 -> LB8`. Production kekal di luar skop sehingga `LB9` diberi authorization
berasingan.

**Keputusan semasa (1 Ogos 2026):** LB0-LB8 lengkap. Migration lima jadual,
backup dan isolated restore, reconciliation, activation serta UAT staging telah
berjaya. Pemilik sistem menerima feature login banner sebagai lengkap dan
selesai. Production kekal di luar skop sehingga authorization berasingan.
