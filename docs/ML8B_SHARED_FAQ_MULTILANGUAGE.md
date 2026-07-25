# ML8B Shared FAQ Multilanguage

**Environment:** Local WSL
**Authorization:** `ONEID-ML8B-LOCAL-20260725-01`
**Status:** PASS / CLOSED
**Git, staging and Production:** NOT AUTHORIZED

## Implemented contract

- `8` FAQ menggunakan identiti stabil dan satu content source dalam
  `app/Documentation/SharedFaqContent.php`.
- Login dan User Dashboard membaca kandungan yang sama mengikut locale aktif.
- Katalog BM dan English mempunyai parity `8/8`.
- Locale tidak sah fail safely kepada BM.
- Jika keseluruhan katalog English belum tersedia, kandungan BM dipaparkan
  bersama notis English yang jelas. Silent fallback tidak dibenarkan.
- Markup renderer disesuaikan kepada Bootstrap 5 pada Login dan Bootstrap 3
  pada User Dashboard tanpa menduplikasi kandungan.
- Soalan, jawapan dan notis di-output menggunakan HTML escaping.
- Accordion mengekalkan `aria-expanded`, `aria-controls`, hubungan heading/panel
  dan `lang` untuk accessibility.
- Audit susulan Login turut menutup baki literal pada browser title,
  Manual/Direktori, blok Hubungi Kami, loading state, login limiter dan label
  correlation reference.
- Phone, support e-mail, URL, OTP dan technical identifier kekal invariant.
- Apabila English dipilih, pautan manual BM disertai explicit availability
  notice seperti yang ditetapkan oleh kontrak ML8A.

## Security and data boundary

ML8B tidak menambah schema atau mutation database. Authentication,
authorization, ACL, session lifetime, External Sync dan Admin Step-Up tidak
diubah. Automatic atau machine approval tidak diperkenalkan.

## Rollback

Rollback wiring boleh mengembalikan markup FAQ asal pada dua surface. Locale
resolver akan terus fallback kepada BM dan tiada account, session atau ACL data
perlu dipadam.

## Closure gate

ML8B hanya boleh ditutup selepas penguji mengesahkan:

1. Login FAQ BM dan English;
2. User Dashboard FAQ BM dan English;
3. parity semua `8` soalan pada kedua-dua surface;
4. preference locale kekal;
5. explicit fallback notice;
6. keyboard, focus dan screen-reader metadata; dan
7. authentication, authorization serta ACL regression.

Kelulusan ini tidak membenarkan Git push, staging atau Production.

## Local observation and closure

Firdaus, System Analyst/DBA telah melaksanakan observation pada Local WSL bagi
`https://oneid.local` dan `https://oneid.local/page/dashboard`.

Evidence `ONEID-ML8B-LOCAL-20260725-01` mengesahkan:

- Login FAQ BM/English: PASS;
- User Dashboard FAQ BM/English: PASS;
- FAQ parity `8/8`: PASS;
- locale preference persistence: PASS;
- explicit English-to-BM fallback notice: PASS;
- keyboard, focus dan accessibility metadata: PASS;
- Login navigation, User Manual notice, contact/support information, loading
  dan login-attempt feedback: PASS;
- technical identifiers kekal invariant: PASS;
- authentication, authorization dan ACL regression: PASS; dan
- critical atau security defects: `0`.

**Decision:** ML8B PASS / CLOSED.

Closure ini terhad kepada Local WSL. Git push, staging dan Production kekal
tidak dibenarkan.
