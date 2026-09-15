# OneID Accessibility Fasa 3 — Local Hardening dan UAT Evidence

## Metadata

- **Tarikh:** 15 September 2026
- **Environment:** local WSL, `https://oneid.local`
- **Branch:** `main`
- **Skop:** pengguna/awam sahaja
- **Admin operational UI:** di luar skop dan tidak berubah
- **Commit/push:** tidak dibuat
- **Status keseluruhan:** `AUTOMATED_PASS / DESKTOP_REVIEW_PASS / MANUAL_MATRIX_PENDING`

## Automated evidence

| Gate | Keputusan | Ringkasan |
|---|---|---|
| Fasa 1 baseline contract | PASS | Zoom, readable floor, focus, target, reflow seam dan admin isolation |
| Fasa 2 display settings contract | PASS | 40 checks |
| Fasa 3 hardening contract | PASS | 19 checks |
| Runtime smoke `https://oneid.local` | PASS | 9 checks; login, asset, zoom dan 404 |
| JavaScript syntax | PASS | `oneid-display-settings.js` |
| PHP syntax | PASS | Renderer, page wiring dan contract |
| User Dashboard contract | PASS | 12 checks |
| Login layout contract | PASS | 7 checks |
| User password modal | PASS | 10 checks |
| Session indicator | PASS | 13 checks |
| Professional alert | PASS | 9 checks |
| Locale infrastructure | PASS | BM/English catalogue parity dan resolver |
| User Dashboard locale | PASS | ML4 source dan characterization |
| User FAQ presentation | PASS | 6 checks |
| Environment banner | PASS | 18 checks |
| Release metadata | PASS | 18 checks |
| Admin file isolation | PASS | Dashboard, report, user list dan Admin Step-Up tiada diff |

## Runtime smoke detail

- login memberi HTTP 200;
- runtime HTML mengandungi renderer Tetapan Paparan;
- lima skala `100`, `108`, `115`, `123`, `130` dirender;
- nilai `140` tidak dirender;
- browser zoom tidak disekat;
- baseline CSS, display-settings CSS dan JavaScript memberi HTTP 200; dan
- halaman 404 dengan Tetapan Paparan boleh dicapai.

## Desktop review bersama owner

Perkara berikut telah dilihat dan diterima secara iteratif oleh owner melalui
paparan lokal:

- ikon gear bersebelahan language selector pada login;
- ikon gear bersebelahan language selector selepas login;
- panel dashboard membuka ke kanan dan kekal dalam canvas;
- footer panel tidak menutup preference terakhir;
- checkbox berada di kiri dengan tajuk/penerangan left-aligned;
- eyebrow dan tajuk panel left-aligned;
- lima pilihan skala menggunakan maksimum 130%; dan
- owner menyatakan berpuas hati sebelum Fasa 3 dimulakan.

Status bahagian ini: `PASS (desktop owner review)`.

## Baseline/environment findings yang bukan disebabkan perubahan ini

Regression luas merekodkan dapatan berikut tanpa membuat mutation:

1. Suite lama MyDigital ID masih mempunyai assertion `dormant/disabled`, tetapi
   runtime lokal kini mengaktifkan MyDigital ID. Assertion tersebut gagal kerana
   state environment semasa, bukan kerana Tetapan Paparan.
2. Preflight read-only MyDigital ID melaporkan 6166 rekod NRIC eligible dan 6149
   nilai unik. Collision data sedia ada ini berada di luar skop accessibility.
3. Suite controlled password/MFA mempunyai live-evidence gate yang belum
   dipenuhi dalam environment semasa. Contract komponen UI, sesi, password dan
   MFA yang berkaitan tetap lulus.

Dapatan ini tidak boleh ditandakan selesai atau diubah melalui kerja
accessibility dan perlu ditriage oleh owner domain masing-masing.

## Manual matrix yang masih perlu dilakukan

Tiada Chromium/Firefox automation binary tersedia dalam WSL semasa audit.
Perkara berikut kekal `PENDING` dan tidak dianggap PASS secara inferens:

| Ujian | 100 | 108 | 115 | 123 | 130 |
|---|---:|---:|---:|---:|---:|
| Login desktop BM/EN | PENDING | PENDING | PENDING | PENDING | PENDING |
| Dashboard desktop BM/EN | PENDING | PENDING | PENDING | PENDING | PENDING |
| Mobile 320/375/430px | PENDING | PENDING | PENDING | PENDING | PENDING |
| User MFA/challenge | PENDING | PENDING | PENDING | PENDING | PENDING |
| FAQ/modal/alert | PENDING | PENDING | PENDING | PENDING | PENDING |

Ujian tambahan:

- keyboard-only: Tab, Shift+Tab, Enter, Space dan Escape;
- browser zoom 200%;
- high contrast sahaja dan bersama skala 130%;
- reduced motion manual serta OS `prefers-reduced-motion`;
- underline links;
- refresh, logout/login semula dan reset;
- `localStorage` blocked/corrupt; dan
- mobile portrait/landscape.

## Go/no-go

- **Teruskan ujian lokal:** GO
- **Commit:** GO — owner memberi kebenaran pada 15 September 2026
- **Push ke Git:** GO — untuk ujian staging/production oleh owner
- **Deployment:** HOLD — bukan sebahagian daripada push ini
- **Fasa 3 `UAT_ACCEPTED`:** HOLD sehingga manual matrix dilengkapkan
