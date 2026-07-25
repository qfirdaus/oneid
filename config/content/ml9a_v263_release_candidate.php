<?php
declare(strict_types=1);

return [
    'version' => '2.6.3',
    'date' => '2026-07-26',
    'status' => 'REVIEW_REQUIRED',
    'automatic_approval' => false,
    'bm' => [
        'Infrastruktur locale BM/English dilengkapkan dengan Bahasa Melayu sebagai default dan hard fallback, English sebagai bahasa kedua serta preference pengguna, sesi dan cookie yang divalidasi.',
        'Login, Pemulihan Kata Laluan, OTP, User Dashboard dan Administrator Dashboard kini menyokong pertukaran BM/English tanpa mengubah authentication, authorization atau ACL.',
        'Active Sessions, Audit Log, Sync Audit, Configuration dan senarai pengguna kategori dilengkapkan dengan label, pagination serta loading, empty, success dan error state BM/English.',
        'External Sync Summary, Staff, Prasiswazah dan ODL kini mempunyai presentation BM/English sementara source code, plan hash, counts, correlation ID dan exact confirmation kekal canonical.',
        'Admin Step-Up menyokong arahan dan feedback BM/English bagi OTP e-mel, Microsoft Authenticator, enrollment, reset, expiry dan rate limit tanpa mengubah purpose, factor atau grant security.',
        'API/AJAX, notification dan e-mel dalam skop dilengkapi stable response code serta translation key sambil mengekalkan legacy msg untuk compatibility.',
        'Metadata aplikasi dan kategori menggunakan translation tables additive, fallback kepada metadata asal, audit history dan optimistic concurrency tanpa mengubah ID, URL, SSO atau ACL.',
        'Semua 84 rekod metadata diklasifikasi; 33 terjemahan English baharu dan 33 audit history telah direkonsiliasi dengan content completeness 100%.',
        'Login dan User Dashboard berkongsi 8 FAQ BM/English daripada satu sumber kandungan dengan explicit fallback notice dan accessibility semantics.',
        'Administrator Version Releases mempunyai parity 37/37 release dan 217/217 changelog BM/English dengan digest approval fail-closed serta fallback penuh kepada BM.',
        'English User Manual PDF ditangguhkan oleh owner; MANUAL_SALAM.pdf kekal rasmi dan pengguna English menerima notis fallback BM yang jelas.',
        'Audit pre-ML9 merekonsiliasi semua fasa multilingual sebagai PASS/CLOSED pada Local WSL dengan document inventory 149, duplicate 0, missing target 0 dan blocking code 0.',
    ],
    'en' => [
        'The BM/English locale infrastructure is complete with Bahasa Melayu as the default and hard fallback, English as the secondary language, and validated user, session and cookie preferences.',
        'Login, Password Recovery, OTP, the User Dashboard and the Administrator Dashboard now support BM/English switching without changing authentication, authorization or ACL behaviour.',
        'Active Sessions, Audit Log, Sync Audit, Configuration and category user lists now provide BM/English labels, pagination, and loading, empty, success and error states.',
        'External Sync Summary, Staff, Undergraduate and ODL now provide BM/English presentation while source codes, plan hashes, counts, correlation IDs and exact confirmations remain canonical.',
        'Admin Step-Up provides BM/English guidance and feedback for e-mail OTP, Microsoft Authenticator, enrollment, reset, expiry and rate limits without changing purpose, factor or grant security.',
        'In-scope API/AJAX responses, notifications and e-mails now use stable response codes and translation keys while retaining legacy msg compatibility.',
        'Application and category metadata use additive translation tables, original-metadata fallback, audit history and optimistic concurrency without changing IDs, URLs, SSO or ACL configuration.',
        'All 84 metadata records were classified; 33 new English translations and 33 audit-history records were reconciled with 100% content completeness.',
        'Login and the User Dashboard share 8 BM/English FAQs from one content source with an explicit fallback notice and accessible semantics.',
        'Administrator Version Releases provide parity for 37/37 releases and 217/217 BM/English changelog items with fail-closed approval binding and full BM fallback.',
        'The English User Manual PDF is deferred by the owner; MANUAL_SALAM.pdf remains official and English users receive a clear BM fallback notice.',
        'The pre-ML9 audit reconciled every multilingual phase as PASS/CLOSED on Local WSL with 149 document identities, 0 duplicates, 0 missing targets and 0 blocking codes.',
    ],
];
