# MD11 — Pilot Closure dan Handoff

**Tarikh:** 4 September 2026  
**Fasa:** 11 — penutupan akhir  
**Status:** feature ditutup; menunggu audited revoke grant

## Keputusan

Feature runtime dan approval pilot telah dimatikan. Schema kekal dipasang secara
dormant untuk penggunaan maintenance akan datang. Grant pilot tidak lagi
efektif kerana runtime feature OFF.

Owner mengecualikan ujian login sebenar kerana credential akaun developer tidak
tersedia. Oleh itu, automated contracts dan HTTP login page adalah PASS tetapi
password → MFA → dashboard tidak dituntut sebagai bukti UAT sebenar. Risiko ini
diterima owner dan perlu diuji pada maintenance exercise akan datang.

## Baki closure

Grant pilot mesti direvoke melalui UI oleh admin dengan Admin Step-Up. Tindakan
ini tidak boleh digantikan dengan kemas kini SQL kerana audit mesti merekod
aktor, sebab, reference, version dan correlation ID.

Selepas revoke:

```bash
php tools/maintenance_developer_phase11_closure.php
```

Fasa lengkap apabila output ialah `DECISION=CLOSED`, active grant sifar dan
event `REVOKED` tersedia. Feature serta pilot approval mesti kekal OFF.
