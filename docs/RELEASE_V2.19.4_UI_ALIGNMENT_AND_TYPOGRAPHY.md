# OneID 2.19.4 — UI Alignment and Typography

**Release date:** 9 October 2026  
**Scope:** Application-search alignment and health-panel typography

## Bahasa Melayu

Cadangan carian aplikasi kini menggunakan penjajaran kiri yang tetap. Nama sistem dan penerangan bermula pada tepi yang sama tanpa dipengaruhi gaya global atau panjang kandungan.

Safe diagnostics menggunakan saiz teks 10px secara konsisten bagi tajuk, label, nilai dan nota. Pautan PTMK Support turut dikunci pada satu baris bersama ikon untuk paparan telefon.

Versi cache aset CSS dan JavaScript dinaikkan supaya pelayar mendapatkan gaya baharu tanpa bergantung pada cache lama. Release ini tidak mengubah database, ACL, runtime atau mobile production.

## English

Application search suggestions now use a fixed left alignment. System names and descriptions begin at the same edge regardless of global styles or content length.

Safe diagnostics consistently uses a 10px text size for its heading, labels, values and note. The PTMK Support link is also kept on one line with its icon on mobile displays.

The CSS and JavaScript asset cache version has been advanced so browsers receive the updated presentation without relying on stale cache. This release does not change the database, ACL, runtime or production mobile access.

## Validation

- PHP syntax and release metadata validation.
- Dashboard search alignment, health panel and Account Security contracts.
- Existing Recently Used, user dashboard and release regression contracts.
- No database migration or production mobile activation.
