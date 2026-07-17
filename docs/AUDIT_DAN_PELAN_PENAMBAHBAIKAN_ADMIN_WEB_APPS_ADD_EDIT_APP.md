# Audit dan Pelan Penambahbaikan Admin Web Apps — Add/Edit App

**Tarikh audit:** 17 Julai 2026  
**Owner keputusan:** Pemilik sistem OneID  
**Status:** WA0 DIREKOD; WA1 IMPLEMENTED; WA2 COMPLETE; WA3 IMPLEMENTED; WA4 COMPLETE; WA5 COMPLETE; WA6 COMPLETE
**Skop:** `Admin > Web Apps > Add App` dan penggantian gambar melalui Edit App  
**Bukan sebahagian daripada:** W0–W4 category lifecycle yang telah selesai

## 1. Tujuan dokumen

Dokumen ini menyimpan hasil audit semasa, keputusan seni bina owner dan backlog
penambahbaikan untuk dilaksanakan apabila owner bersedia. Penyediaan dokumen ini
tidak mengubah kod, schema database, konfigurasi runtime atau fail gambar.

## 2. Gambaran proses semasa

Aliran Add App semasa menerima metadata aplikasi dan satu fail gambar. Handler
menyimpan fail ke direktori `public/public_img`, kemudian memasukkan nama fail
bersama metadata ke `sp_list`. Edit App menggunakan medan global
`sp_list.sp_image` untuk menggantikan rujukan gambar.

WSL dan staging menggunakan database OneID yang sama tetapi mempunyai filesystem
berasingan. Oleh sebab `sp_list.sp_image` hanya boleh menyimpan satu nama fail,
rekod semasa tidak dapat mewakili gambar WSL dan staging secara bebas.

## 3. Keputusan owner yang telah disahkan

1. WSL dan staging terus berkongsi database OneID yang sama.
2. Kedua-dua environment tidak boleh berkongsi filesystem.
3. Fail yang dimuat naik melalui WSL mesti kekal dan digunakan di WSL sahaja.
4. Fail yang dimuat naik melalui staging mesti kekal dan digunakan di staging
   sahaja.
5. Fail gambar tidak akan diselaraskan melalui Git, NFS, shared mount atau
   salinan automatik antara kedua-dua environment.
6. Pelaksanaan pembetulan ditangguhkan sehingga owner memberi arahan untuk
   menyambung.

## 4. Dapatan audit

| ID | Dapatan | Kesan/risiko | Keutamaan |
| --- | --- | --- | --- |
| WA-F01 | Add App menyimpan fail sebelum operasi database selesai. | Insert gagal boleh meninggalkan fail orphan. | Tinggi |
| WA-F02 | Kegagalan upload tidak semestinya menghentikan Add App; insert boleh diteruskan dengan nama gambar kosong. | Admin menerima aplikasi tanpa gambar walaupun menjangka upload berjaya. | Tinggi |
| WA-F03 | Edit App boleh mengekalkan gambar lama apabila upload baharu gagal, tetapi perubahan medan lain masih menghasilkan mesej umum “Record updated”. | Admin menyangka gambar berjaya diganti sedangkan ia tidak berubah. | Tinggi |
| WA-F04 | UI tidak menggunakan keputusan terperinci `app_icon` daripada response. | Punca kegagalan gambar tidak kelihatan dengan jelas. | Tinggi |
| WA-F05 | Satu medan global `sp_list.sp_image` digunakan oleh WSL dan staging yang mempunyai filesystem berlainan. | Upload pada satu environment boleh menyebabkan environment lain merujuk fail yang tidak wujud atau gambar tidak berubah seperti dijangka. | Kritikal |
| WA-F06 | Label saiz imej tidak dikuatkuasakan pada dimensi piksel; hanya jenis fail/saiz umum diperiksa. | Imej terlalu besar, nisbah salah atau paparan tidak konsisten. | Sederhana |
| WA-F07 | Fail diterima tanpa proses resize/re-encode dan pembuangan metadata. | Saiz storan, metadata dan format visual sukar dikawal; animated GIF juga boleh terlepas. | Sederhana |
| WA-F08 | Penggantian gambar tidak mempunyai lifecycle cleanup yang selamat untuk gambar lama. | Fail orphan bertambah dan penggunaan storan sukar diaudit. | Sederhana |
| WA-F09 | Validation backend untuk nama, penerangan, URL, kategori dan duplicate belum cukup ketat/seragam. | Data tidak konsisten atau URL tidak selamat boleh disimpan. | Tinggi |
| WA-F10 | App ID dibina menggunakan `mt_rand()` tanpa strategi collision/retry yang jelas. | Collision jarang tetapi boleh menggagalkan penciptaan aplikasi. | Sederhana |
| WA-F11 | Maksud checkbox/pilihan SSO mudah disalah tafsir kerana semantik UI dan nilai backend tidak cukup jelas. | Polisi aplikasi boleh tersimpan bertentangan dengan intent admin. | Tinggi |
| WA-F12 | Operational feedback masih umum: tiada confirmation lengkap, loading state, double-submit guard dan correlation reference yang konsisten. | Duplicate request dan troubleshooting sukar dilakukan. | Tinggi |
| WA-F13 | Event audit Add/Edit boleh ditulis walaupun keputusan operasi tidak lengkap/gagal. | Audit trail boleh memberi gambaran mutation berjaya sedangkan sebahagian operasi gagal. | Tinggi |

Kawalan yang sudah baik dan perlu dikekalkan ialah pemeriksaan admin/CSRF,
semakan MIME bersama pemeriksaan imej, had saiz fail dan penggunaan nama fail
rawak.

## 5. Baseline filesystem WSL pada masa audit

Baseline read-only menunjukkan:

- 61 rujukan gambar aplikasi dalam database;
- 90 fail gambar aplikasi pada filesystem WSL;
- 0 rujukan database yang hilang pada WSL;
- 29 fail berpotensi orphan pada WSL;
- 36 aplikasi aktif;
- 1 aplikasi aktif tidak mempunyai gambar.

Angka ini ialah snapshot WSL, bukan bukti keadaan staging. Fail orphan tidak
boleh dipadam hanya berdasarkan snapshot ini kerana database dikongsi tetapi
filesystem adalah khusus kepada environment.

## 6. Seni bina sasaran aset khusus environment

Metadata aplikasi kekal dalam `sp_list`. Rujukan gambar baharu dipisahkan ke
jadual khusus environment, secara konsep:

```text
sp_app_asset
  sp_id
  environment       # contoh: local atau staging
  image_filename
  updated_at
  updated_by
  PRIMARY KEY (sp_id, environment)
```

Environment mesti ditetapkan melalui konfigurasi runtime eksplisit seperti
`ONEID_ENVIRONMENT`; ia tidak boleh diteka daripada HTTP `Host`.

Peraturan bacaan dan tulisan:

1. WSL hanya menulis fail ke filesystem WSL dan row aset `environment=local`.
2. Staging hanya menulis fail ke filesystem staging dan row aset
   `environment=staging`.
3. Edit gambar hanya mengganti aset untuk environment semasa.
4. Jika aset environment belum ada, UI menggunakan placeholder/default yang
   jelas; ia tidak mengambil fail daripada environment lain.
5. `sp_list.sp_image` dikekalkan sementara sebagai compatibility field untuk
   data legacy sepanjang migration transition, tetapi upload baharu tidak
   bergantung kepadanya sebagai rujukan global.
6. Migration mesti bersifat expanding dan backward-compatible kerana database
   dikongsi serta kedua-dua deployment mungkin menjalankan versi kod berbeza
   semasa rollout.

## 7. Pelan pelaksanaan berfasa

### WA0 — Baseline dan contract

- sahkan identiti environment WSL dan staging;
- ambil inventori read-only database dan setiap filesystem secara berasingan;
- sediakan caller map Add/Edit/list/render;
- tetapkan contract response, validation, audit dan rollback;
- jangan padam atau pindah mana-mana fail.

**Gate keluar:** owner mengesahkan baseline, nama environment dan contract.

### WA1 — Ketepatan UI dan operational feedback

- betulkan label gambar, URL dan pilihan SSO;
- paparkan keputusan gambar secara khusus di dalam modal/form;
- tambah confirmation, loading state dan double-submit guard;
- mesej berjaya/gagal boleh dipilih/disalin serta mempunyai code dan correlation
  reference.

**Gate keluar:** UI tidak lagi melaporkan update gambar berjaya apabila upload
sebenarnya gagal.

### WA2 — Validation dan application service

- pindahkan Add/Edit daripada handler legacy kepada service yang sama;
- kuatkuasakan nama, panjang penerangan, kategori sah dan duplicate policy;
- parse URL secara ketat, benarkan `https` dan hostname yang sah mengikut polisi
  owner;
- gunakan ID rawak kriptografi dengan collision retry;
- tetapkan semantik SSO yang eksplisit dan konsisten.

**Gate keluar:** request UI dan request terus menerima validation yang sama.

### WA3 — Integriti persistence dan audit

- urus temp upload, database transaction dan publish fail secara terkawal;
- lakukan compensation/cleanup jika database atau publish fail gagal;
- audit hanya merekod outcome sebenar bersama before/after yang sesuai;
- Add/Edit berjaya hanya apabila metadata dan aset environment mencapai state
  yang konsisten.

**Gate keluar:** contract membuktikan tiada false success dan tiada orphan baharu
bagi simulasi kegagalan utama.

### WA4 — Aset khusus environment

- tambah jadual `sp_app_asset` melalui migration expanding;
- tambah konfigurasi runtime `ONEID_ENVIRONMENT` pada setiap deployment;
- ubah read/write supaya menggunakan pasangan `(sp_id, environment)`;
- sediakan fallback legacy yang terhad dan placeholder untuk aset yang tiada;
- rollout satu environment pada satu masa tanpa menyalin fail antara platform.

**Gate keluar:** upload WSL tidak mengubah gambar staging dan upload staging tidak
mengubah gambar WSL.

### WA5 — Normalisasi dan keselamatan imej

- tetapkan format output yang diluluskan;
- decode dan re-encode gambar, buang metadata, kawal dimensi/pixel count dan
  nisbah;
- resize kepada saiz paparan sasaran tanpa mempercayai extension asal;
- tetapkan polisi animated image dan had decompression.

**Gate keluar:** semua gambar tersimpan memenuhi format, saiz dan dimensi server.

### WA6 — Reconciliation dan cleanup terkawal

- hasilkan laporan referenced, missing dan orphan bagi setiap environment;
- jangan gabungkan keputusan WSL dengan staging;
- gunakan quarantine dan grace period sebelum deletion;
- rekod manifest/hash serta bukti rollback untuk setiap cleanup.

**Gate keluar:** owner meluluskan senarai tepat sebelum sebarang fail dipadam.

### WA7 — UAT dan rollout

- UAT Add App tanpa gambar dan dengan gambar sah;
- UAT fail salah jenis, terlalu besar, dimensi salah dan URL tidak sah;
- UAT penggantian gambar serta simulasi kegagalan;
- sahkan isolation dua hala WSL/staging;
- sahkan audit correlation, cache refresh, rollback dan tiada 5xx.

**Gate keluar:** owner UAT lulus pada kedua-dua environment.

## 8. Acceptance criteria utama

1. Gambar yang di-upload pada WSL wujud dan berubah pada WSL sahaja.
2. Gambar yang di-upload pada staging wujud dan berubah pada staging sahaja.
3. Metadata aplikasi yang memang global kekal konsisten melalui shared database.
4. Kegagalan upload tidak mencipta aplikasi/update palsu dan tidak memaparkan
   mesej berjaya.
5. Gambar lama tidak dipadam sehingga gambar baharu dan transaction disahkan.
6. Semua outcome mempunyai code dan correlation reference yang boleh diaudit.
7. Rollout tidak memerlukan migration database diulang pada staging kerana WSL
   dan staging menggunakan database yang sama.
8. Cleanup fail sentiasa dinilai dan dilaksanakan secara berasingan untuk setiap
   filesystem.

## 9. Perkara yang belum diputuskan

Keputusan berikut perlu disahkan ketika WA0 dimulakan:

- nilai rasmi environment, contohnya `local` dan `staging`;
- dimensi, nisbah dan format output standard untuk logo;
- sama ada gambar diwajibkan semasa Add App atau placeholder dibenarkan;
- polisi duplicate nama/URL aplikasi;
- senarai scheme/hostname URL yang diluluskan;
- tempoh quarantine sebelum fail orphan dipadam;
- turutan deployment WSL dan staging serta rollback window.

## 10. Status handoff

WA0 telah menghasilkan baseline shared database/WSL, caller map, decision
register dan kontrak operasi. Rekod penuh berada dalam
`docs/WA0_BASELINE_DAN_CONTRACT_ADMIN_WEB_APPS_ADD_EDIT_APP.md`.

WA1 UI dan operational feedback telah dilaksanakan dan direkod dalam
`docs/WA1_UI_DAN_OPERATIONAL_FEEDBACK_ADMIN_WEB_APPS_ADD_EDIT_APP.md`. Automated
contract telah disediakan; manual pilot UAT masih diperlukan sebelum WA1
ditutup.

WA2 validation/application service telah dilaksanakan tanpa schema migration.
Rekod berada dalam
`docs/WA2_VALIDATION_DAN_APPLICATION_SERVICE_ADMIN_WEB_APPS_ADD_EDIT_APP.md`.
Automated contract dan manual owner UAT telah lulus. Polisi duplicate sengaja
belum dienforce kerana WA0-D10 belum diputuskan.

WA3 atomic persistence dan mandatory audit telah dilaksanakan dengan staged
upload, transaction, ordered publish dan failure compensation. Rekod berada
dalam `docs/WA3_ATOMIC_PERSISTENCE_DAN_AUDIT_ADMIN_WEB_APPS_ADD_EDIT_APP.md`;
manual pilot UAT masih berbaki.

WA4 environment-specific asset persistence telah dilaksanakan dan migration
expanding dipasang sekali pada shared database. WSL menggunakan `local`; staging
mesti menetapkan runtime `staging` sebelum UAT. Rekod penuh berada dalam
`docs/WA4_ASET_KHUSUS_ENVIRONMENT_ADMIN_WEB_APPS.md`.

WA4 ditutup selepas automated contract, shared DB row isolation, reciprocal
filesystem check dan visual owner UAT WSL/staging semuanya lulus.

WA5 normalisasi imej telah dilaksanakan menggunakan GD: input baharu didecode,
dihadkan, ditolak jika animated dan dire-encode sebagai static 256×256 PNG.
Rekod berada dalam `docs/WA5_NORMALISASI_DAN_KESELAMATAN_IMEJ_WEB_APPS.md`.

WA5 ditutup selepas automated contract 10/10, UAT normalisasi dan paparan,
animated GIF rejection serta filesystem isolation lulus. Manual oversized UI
diterima owner sebagai bukan blocker kerana dimension-limit contract telah
lulus.

WA6 dimulakan sebagai reconciliation read-only per environment. Tiada fail
boleh dikuarantin atau dipadam sebelum manifest tepat diluluskan owner.

WA6 ditutup pada 17 Julai 2026 selepas manifest, hash, quarantine/restore
rehearsal, quarantine 27 fail lama bagi setiap environment, reconciliation dan
visual UAT local/staging lulus. Kedua-dua batch berada dalam observation 30
hari; permanent deletion kekal memerlukan kelulusan baharu.

Tiada pembetulan Web Apps/Add App, migration atau cleanup dibuat dalam WA0.
Baseline filesystem staging dan keputusan bertanda `PENDING`/`PROPOSED` masih
perlu disahkan sebelum gate berkaitan ditutup. WA1 boleh dirancang secara
berasingan, tetapi WA4 tidak boleh dimulakan sebelum gate seni bina WA0 lengkap.
