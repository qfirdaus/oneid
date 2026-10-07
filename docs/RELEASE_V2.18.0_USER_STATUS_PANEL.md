# OneID 2.18.0 — User Connection and Display Status

Tarikh release: 7 Oktober 2026

Release ini menambah panel status ringkas pada dashboard pengguna. Ikon graf berwarna jingga diletakkan di sebelah countdown sesi dengan ketinggian yang sepadan.

Panel memaparkan status sambungan browser, masa respons halaman, masa data terakhir dikemas kini dan versi OneID. Pengguna boleh menyegarkan senarai aplikasi dan sesi, mengosongkan data paparan dalam memori, menyalin diagnostik bukan sensitif dan membuka saluran sokongan PTMK.

Tindakan **Clear cache** hanya menyegarkan data paparan dashboard. Ia tidak menjalankan pembersihan menyeluruh `localStorage` atau `sessionStorage`, tidak membaca atau memadam cookie, dan tidak mengubah sesi login, token SSO, bahasa atau favourite pengguna.

Susun atur desktop menggunakan satu baris untuk empat metrik dan satu baris untuk empat tindakan. Pada telefon, panel menggunakan ruang skrin yang tersedia dan menyusun kandungan kepada dua kolum supaya teks kekal boleh dibaca tanpa melimpah keluar viewport.
