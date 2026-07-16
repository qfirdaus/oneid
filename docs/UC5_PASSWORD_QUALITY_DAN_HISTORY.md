# UC5 — Password Quality dan History

**Status:** IMPLEMENTED — EXPANDING MIGRATION APPLIED; CONTRACT PASS

Polisi 12 aksara/composition dikekalkan dan ditambah local predictable-password
denylist serta larangan memasukkan user ID. Password semasa dan lima hash
terdahulu tidak boleh digunakan semula. History hanya menyimpan hash, tidak
password asal, dan bermula selepas UC5; tiada historical password direka semula.

Table `user_password_history` ialah expanding schema dengan index user/id.
Rekod history, perubahan password dan pruning berlaku dalam transaction UC2.
