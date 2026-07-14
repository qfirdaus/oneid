# W0–W4 — Audit dan Hardening Web Apps

**Tarikh:** 14 Julai 2026  
**Owner perubahan:** Pemilik sistem OneID  
**Owner rollback:** Pemilik sistem OneID  
**Release sasaran:** OneID v2.0.4  
**Status:** SELESAI — IMPLEMENTASI, AUTOMATED VERIFICATION DAN OWNER UAT LULUS

## 1. Skop

Siri ini mengawal lifecycle kategori aplikasi `sp_group` dan aplikasi
`sp_list`. Ia berbeza daripada kategori pengguna `user_category` dan ACL
pengguna.

Keperluan owner:

1. tab kategori admin hanya dipaparkan apabila mempunyai aplikasi aktif;
2. kategori tidak boleh dipadam selagi mana-mana aplikasi, termasuk aplikasi
   inactive, masih assigned kepadanya;
3. default category `Uncategorize` ID `0` tidak boleh dipadam;
4. inactive/removed app tidak boleh muncul atau diluluskan oleh effective ACL.

## 2. Finding audit

| ID | Finding | Risiko |
| --- | --- | --- |
| W-F01 | Endpoint senarai memulangkan semua kategori dan UI membina tab berkiraan `0`. | Paparan kosong dan tindakan Remove mengelirukan. |
| W-F02 | Remove Category memindahkan aplikasi aktif ke ID `0` sebelum delete. | Bertentangan dengan polisi owner; mutation tersembunyi. |
| W-F03 | Aplikasi inactive tidak diambil semasa pemindahan kategori. | Rekod boleh terus menunjuk kepada category ID yang dipadam. |
| W-F04 | Perlindungan category ID `0` hanya pada JavaScript. | Request admin terus boleh cuba memadam kategori sistem. |
| W-F05 | Delete, reassignment dan audit tidak berada dalam satu transaction. | Partial mutation jika langkah pertengahan gagal. |
| W-F06 | `sp_list.sp_group_id` tiada foreign key ke `sp_group`. | Orphan reference tidak disekat database. |
| W-F07 | Remove App hanya membersihkan group ACL; direct ACL boleh kekal. | Inactive app berpotensi kekal dalam effective access. |
| W-F08 | Query effective ACL tidak menapis `sp_list.avail_status=1`. | Removed app boleh dipaparkan atau dibenarkan. |
| W-F09 | Category ID untuk removal diparse daripada `href` tab. | Nama kategori tertentu boleh menghasilkan ID salah. |
| W-F10 | Add Category tiada validation nama/duplicate yang jelas. | Kategori kosong, terlalu panjang atau duplicate boleh diwujudkan. |
| W-F11 | Senarai aplikasi menggunakan pola N+1 query per kategori. | Latency meningkat apabila kategori bertambah. |
| W-F12 | Beberapa AJAX error callback kosong dan response mutation hanya `1/0`. | Admin tidak mendapat sebab atau correlation reference. |

## 3. Baseline data sebelum reconciliation

- 35 aplikasi aktif dan 35 aplikasi inactive.
- `Uncategorize` mempunyai 0 aktif dan 5 inactive.
- Tiga aplikasi inactive merujuk category ID `11` yang sudah tiada.
- Empat direct ACL dan dua blacklist ACL masih merujuk aplikasi inactive.
- Tiada kategori bukan-sistem yang benar-benar kosong apabila inactive app
  turut dikira.
- `sp_group` dan `sp_list` hanya mempunyai primary key; tiada foreign key antara
  kedua-duanya.

## 4. Pelan

### W0 — Baseline dan reconciliation

- sediakan audit read-only berulang;
- snapshot orphan rows ke `storage/backups/`;
- fail jika orphan aktif ditemui;
- pindahkan orphan inactive sahaja ke category ID `0`;
- sahkan zero orphan selepas transaction.

### W1 — Safe category deletion

- server-side validation, default protection dan category row lock;
- kira semua assigned app tanpa mengira status;
- reject `CATEGORY_NOT_EMPTY` tanpa reassignment;
- delete dan mandatory correlated audit dalam satu transaction.

### W2 — Category management UI

- tab utama hanya kategori yang mempunyai aplikasi aktif;
- gunakan category ID sebagai data attribute, bukan parse `href`;
- Manage Categories memaparkan semua kategori dan removal eligibility;
- kategori berisi/default disabled bersama sebab.

### W3 — Inactive app dan ACL

- semua effective ACL/access query menapis aplikasi aktif;
- Remove App melakukan soft-delete dan cleanup preference/ACL secara atomic;
- request terus kepada inactive app ditolak.

### W4 — Constraint dan verification

- tambah foreign key `sp_list.sp_group_id -> sp_group.sp_group_id` dengan
  `ON DELETE RESTRICT` selepas reconciliation;
- characterization/contract, migration check dan HTTP regression;
- manual UAT oleh owner.

## 5. Polisi deletion akhir

`assigned_count` merangkumi aplikasi aktif dan inactive. Kategori hanya boleh
dipadam apabila jumlah ini tepat `0`. Sistem tidak akan memindahkan aplikasi
secara automatik ketika deletion. Admin perlu edit/reconcile assignment terlebih
dahulu supaya intent jelas dan boleh diaudit.

## 6. Keputusan pelaksanaan

- W0: tiga orphan inactive direconcile ke category ID `0`; snapshot SHA-256
  disimpan di `storage/backups/W0-20260714-225241/`.
- W1: default category dilindungi server-side dan kategori dengan sebarang app
  assignment ditolak tanpa mutation; characterization lulus 10/10.
- W2: endpoint direktori menggunakan satu query dan hanya membina tab daripada
  aplikasi aktif. Manage Categories memaparkan active/inactive/assigned count.
- W3: 27 aplikasi inactive lama dipindah ke category arkib ID `0` dan enam
  reference ACL/Favourite dibersihkan; snapshot disimpan di
  `storage/backups/W3-20260714-230736/`. Characterization lulus 8/8.
- W4: `uq_sp_group_name` dan `fk_sp_list_sp_group` telah dipasang. Create
  category kini tervalidasi, transaction-safe dan diaudit. Rollback enforcement
  tersedia di `docs/migrations/20260714_w4_web_app_category_integrity_down.sql`.

## 7. Sempadan operasi selepas W4

- `Remove App` ialah soft archive: `avail_status=0`, category ID `0`, semua
  ACL/Favourite aplikasi dibuang dan event `ADMIN_REMOVE_APP` direkod.
- Category deletion tidak pernah memindahkan app secara tersembunyi.
- Foreign key menggunakan `ON UPDATE RESTRICT ON DELETE RESTRICT`.
- External Sync Apply S4E kekal tidak berkaitan dan tidak diaktifkan.

## 8. UAT owner

1. Pastikan kategori `Uncategorize` dengan 0 active app tidak muncul sebagai
   tab utama.
2. Buka Manage Categories dan sahkan kategori berisi/default disabled.
3. Cipta kategori kosong, sahkan duplicate ditolak, kemudian padam kategori
   kosong itu dan simpan kedua-dua correlation reference.
4. Pada satu aplikasi ujian, gunakan Remove App; sahkan aplikasi hilang daripada
   direktori dan direct URL/SSO access ditolak.
5. Semak Audit Log untuk `ADMIN_ADD_WEB_CATEGORY`,
   `ADMIN_REMOVE_WEB_CATEGORY` dan `ADMIN_REMOVE_APP`.

## 9. Evidence UAT owner

UAT browser dan regression akhir telah dilaksanakan pada 14 Julai 2026.

| Ujian | Keputusan | Reference/evidence |
| --- | --- | --- |
| Cipta kategori kosong | Lulus | `c20e8b18fc093c5d` |
| Tolak nama kategori duplicate | Lulus, `W4_CATEGORY_DUPLICATE` | `e4ba31d792fa4d89` |
| Padam kategori kosong pertama | Lulus | `27f220b0b24dcc39` |
| Archive aplikasi ujian | Lulus; App ID `4TSVQFXCSR`, ACL/Favourite revoked | `6e05bb71b98f2e68` |
| Padam kategori selepas aplikasi diarkib | Lulus | `4b340f051d1ea052` |

Keadaan database selepas UAT:

- 35 aplikasi aktif dan 36 aplikasi inactive; pertambahan satu inactive ialah
  aplikasi ujian `4TSVQFXCSR` yang sengaja diarkibkan.
- semua 36 aplikasi inactive berada dalam category arkib ID `0`;
- orphan category reference: `0`;
- inactive direct/group/deny ACL: `0`;
- duplicate category name: `0`;
- foreign key dan unique category-name constraint: aktif;
- W4 management contract: 12/12 lulus;
- HTTP restructure smoke: 10/10 lulus.

Kesimpulan owner UAT: behaviour create, duplicate rejection, empty-only deletion,
soft archive dan cleanup access reference berfungsi seperti polisi W0–W4.
