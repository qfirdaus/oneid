# OneID 2.14.1 — Carian Rekod Lama

Tarikh release: 22 September 2026

## Ringkasan

Release ini memperjelas perbezaan antara akaun aktif dan rekod sejarah dalam
carian Akaun Pengguna Administrator tanpa memadam atau mengubah data pengguna.

## Perubahan utama

- Carian lalai hanya memaparkan akaun aktif.
- Administrator boleh memilih **Tunjukkan rekod lama** apabila diperlukan.
- Rekod sejarah memaparkan ID lama, nombor matrik dan akaun aktif pengganti.
- Badge dan kolum hasil carian diseragamkan serta top-aligned.
- Kolum nama menggunakan lebar kompak 90–120px dengan ellipsis.
- Susun atur mobile kekal responsif pada skrin yang sangat kecil.
- Regression contract melindungi penapisan dan paparan rekod sejarah.

## Pengesahan data

Semakan production mendapati 562 nombor matrik mempunyai satu akaun aktif dan
satu rekod lama. Tiada nombor matrik dalam kumpulan ini mempunyai lebih
daripada satu akaun aktif.

## English summary

This release makes active and historical User Account search results distinct,
keeps historical records opt-in, identifies replacement active accounts and
uses compact, consistent and responsive result columns.
