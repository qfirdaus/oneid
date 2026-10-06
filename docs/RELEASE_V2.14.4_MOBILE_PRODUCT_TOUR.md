# OneID 2.14.4 — Penjajaran Product Tour Mobile

Tarikh release: 6 Oktober 2026

## Ringkasan

Release ini membetulkan keadaan pada telefon apabila spotlight Panduan Sistem
boleh menunjuk elemen yang berlainan daripada arahan pada kad. Puncanya ialah
senarai target dikira semula semasa aplikasi dan butang Pilihan masih dimuatkan.

## Pembetulan

- pasangan langkah, kandungan dan target dibekukan sepanjang sesi tour;
- target yang benar-benar kelihatan dipilih apabila terdapat beberapa padanan;
- menu mobile dibuka untuk langkah Keselamatan Akaun;
- spotlight dikemas kini semasa scroll, resize dan pertukaran orientasi;
- scroll mobile mengambil kira ketinggian bottom sheet; dan
- kad arahan mobile dikecilkan kepada maksimum 44dvh.

## Skop

Tiada perubahan database, SSO, PHP/Nginx atau pengaktifan mobile login
production. Perubahan melibatkan paparan Product Tour dashboard pengguna dan
metadata release sahaja.

## English summary

This release keeps product-tour instructions and spotlight targets aligned on
phones, including during asynchronous application rendering, scrolling,
orientation changes and collapsed mobile-menu navigation.
