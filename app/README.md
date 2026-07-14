# Application Layer

Direktori sasaran bagi service, repository dan domain logic OneID.

R5.2B1 memulakan runtime extraction pertama melalui `Auth/LogoutHandler.php`.
Public URL dan compatibility entry point kekal di lokasi asal. Setiap extraction
selepas ini mesti mengekalkan HTTP/JSON contract, authorization map dan mempunyai
rollback batch sendiri.

R5.2C1 menempatkan enam transformasi sync deterministic dalam
`Sync/SyncDataTransformer.php`. Function global `sync_*` kekal sebagai
compatibility wrapper dalam `lib/sync_user_runner.php`.

R5.2D1 menambah interface dan DTO sync tanpa production wiring. Contract ini
belum boleh digunakan oleh runtime sehingga adapter/parity/rollback batch
berasingan diluluskan.

R5.2D5–D8 menambah immutable plan, pure planner, production adapter dan
production orchestrator di `Sync/`. Semua class tersebut masih dormant;
`run_admin_sync_user` dan caller legacy belum berubah. Full dormant production
parity lulus 18/18 dalam D8.

S1 menambah `User/ManualUserInput.php` dan `User/ManualUserCreator.php` untuk
validation serta penciptaan akaun manual secara atomik. Runtime controller
kekal di `lib/q_func.php`, tetapi mendelegasikan action Manual Add User kepada
application service ini. Provenance disimpan sebagai `manual` dan protected
daripada full external sync selepas migration S1 aktif.
