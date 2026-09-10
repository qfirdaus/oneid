<?php
$locale = function_exists('oneid_current_locale') ? oneid_current_locale() : 'ms';
$title = $locale === 'en' ? $policy['title_en'] : $policy['title_ms'];
$message = $locale === 'en' ? $policy['message_en'] : $policy['message_ms'];
$end = $policy['ends_at'] ? date('j M Y, g:i A', strtotime($policy['ends_at'] . ' UTC')) : null;
$assetBase = rtrim(APP_URL, '/');
$supportHeading = $locale === 'en' ? 'Need assistance?' : 'Perlukan bantuan?';
$supportIntro = $locale === 'en' ? 'Contact the official OneID support service.' : 'Hubungi perkhidmatan sokongan rasmi OneID.';
$serviceEyebrow = $locale === 'en' ? 'OneID Service Status' : 'Status Perkhidmatan OneID';
$supportService = $locale === 'en' ? 'OneID@UPNM Support Service' : 'Perkhidmatan Sokongan OneID@UPNM';
$supportDivision = $locale === 'en'
    ? 'Information and Communication Technology Division, National Defence University of Malaysia (UPNM)'
    : 'Pusat Teknologi Maklumat dan Komunikasi, Universiti Pertahanan Nasional Malaysia (UPNM)';
$applicationFooter = function_exists('oneid_application_footer')
    ? oneid_application_footer()
    : '2026 © PTMK | Aplikasi Digital. Version 2.11.0';
?>
<!doctype html>
<html lang="<?=htmlspecialchars($locale, ENT_QUOTES, 'UTF-8')?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?=htmlspecialchars($title, ENT_QUOTES, 'UTF-8')?></title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;background:radial-gradient(circle at 12% 12%,rgba(47,174,218,.12),transparent 30%),radial-gradient(circle at 88% 86%,rgba(26,76,131,.12),transparent 34%),linear-gradient(135deg,#f1f8fb 0%,#e4f0f6 52%,#dce9f1 100%);color:#173848;font-family:Arial,sans-serif;min-height:100vh;display:grid;place-items:center;padding:32px 22px}
        body:before{background-image:linear-gradient(rgba(12,104,145,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(12,104,145,.035) 1px,transparent 1px);background-size:34px 34px;content:"";inset:0;mask-image:linear-gradient(to bottom,rgba(0,0,0,.75),transparent 72%);pointer-events:none;position:fixed}
        .maintenance-card{background:rgba(255,255,255,.98);border:1px solid rgba(255,255,255,.9);border-radius:24px;box-shadow:0 30px 80px rgba(22,61,83,.18),0 3px 12px rgba(22,61,83,.06);max-width:900px;overflow:hidden;position:relative;width:100%}
        .maintenance-accent{height:6px;background:linear-gradient(90deg,#ff6336 0%,#f5b52d 32%,#11a2cf 66%,#173d79 100%)}
        .maintenance-content{padding:27px 44px 24px}
        .maintenance-brands{align-items:center;border-bottom:1px solid #e1ebf1;display:flex;justify-content:space-between;margin-bottom:22px;padding:0 2px 20px}
        .maintenance-brands img{display:block;height:auto;object-fit:contain}
        .maintenance-brands__oneid{max-width:176px;width:31%}
        .maintenance-brands__office{max-width:187px;width:33%}
        .maintenance-eyebrow{color:#087ba9;font-size:11px;font-weight:800;letter-spacing:.15em;margin-bottom:5px;text-transform:uppercase}
        .maintenance-locale{align-items:center;background:#f2f7fa;border:1px solid #d6e4ec;border-radius:999px;display:inline-flex;gap:2px;margin:0;padding:3px;position:absolute;right:44px;top:109px}
        .maintenance-locale a{border-radius:999px;color:#607487;font-size:11px;font-weight:800;line-height:1;padding:7px 10px;text-decoration:none}
        .maintenance-locale a.is-active{background:#079bd3;box-shadow:0 3px 9px rgba(7,155,211,.2);color:#fff}
        .maintenance-status{background:linear-gradient(135deg,#f7fbfd 0%,#edf7fb 100%);border:1px solid #d6e8f0;border-radius:18px;box-shadow:inset 0 1px 0 #fff;display:grid;gap:20px;grid-template-columns:minmax(0,1fr) 280px;margin:0;padding:25px 26px;position:relative}
        .maintenance-status:before{background:#0b94c5;border-radius:3px;content:"";height:38px;left:-2px;position:absolute;top:28px;width:4px}
        .maintenance-hero{align-items:flex-start;display:flex;gap:17px;text-align:left}
        .maintenance-icon{align-items:center;background:linear-gradient(145deg,#fff7df,#ffedc8);border:1px solid #f6d99b;border-radius:16px;box-shadow:0 8px 18px rgba(210,139,12,.12);color:#d98a00;display:flex;flex:0 0 58px;height:58px;justify-content:center;width:58px}
        .maintenance-icon svg{height:27px;width:27px}
        h1{color:#102e43;font-size:29px;letter-spacing:-.025em;line-height:1.2;margin:0 0 8px;max-width:560px}
        .maintenance-message{color:#5d7084;font-size:15px;line-height:1.55;margin:0;max-width:560px}
        .maintenance-time{align-items:center;background:#fff;border:1px solid #d4e5ed;border-radius:13px;box-shadow:0 7px 18px rgba(23,72,99,.07);color:#356276;display:flex;gap:11px;min-height:82px;padding:14px 15px;text-align:left}
        .maintenance-time__icon{align-items:center;background:#e9f7fc;border-radius:10px;color:#078fbe;display:flex;flex:0 0 38px;height:38px;justify-content:center}
        .maintenance-time__icon svg{height:18px;width:18px}
        .maintenance-time span{color:#718393;display:block;font-size:10px;font-weight:800;letter-spacing:.08em;margin-bottom:4px;text-transform:uppercase}
        .maintenance-time strong{color:#174d67;display:block;font-size:14px;line-height:1.4}
        .maintenance-support{background:#fff;border:1px solid #d8e5ec;border-radius:16px;box-shadow:0 10px 25px rgba(23,72,99,.06);margin:17px 0 0;overflow:hidden;text-align:left}
        .maintenance-support__head{align-items:center;background:linear-gradient(90deg,#f3f9fc,#fbfdfe);border-bottom:1px solid #dfeaf0;display:flex;gap:11px;padding:12px 16px}
        .maintenance-support__icon{align-items:center;background:#e7f6fb;border:1px solid #cce8f2;border-radius:9px;color:#078fbe;display:flex;flex:0 0 36px;height:36px;justify-content:center}
        .maintenance-support__head strong{color:#173848;display:block;font-size:14px;line-height:1.35}
        .maintenance-support__head span{color:#66798c;display:block;font-size:12px;line-height:1.45;margin-top:2px}
        .maintenance-support__body{display:grid;gap:20px;grid-template-columns:minmax(0,1.45fr) minmax(240px,.8fr);padding:16px}
        .maintenance-support__identity strong{color:#173848;display:block;font-size:14px;line-height:1.45;margin-bottom:5px}
        .maintenance-support__identity p{color:#607487;font-size:12px;line-height:1.45;margin:0}
        .maintenance-support__contacts{display:grid;gap:8px}
        .maintenance-support__link{align-items:center;background:#f9fcfd;border:1px solid #dbe7ed;border-radius:10px;color:#18566f;display:flex;font-size:12px;font-weight:700;gap:9px;min-width:0;padding:8px 10px;text-decoration:none;transition:border-color .15s ease,box-shadow .15s ease,transform .15s ease}
        .maintenance-support__link:hover{border-color:#8fcde2;box-shadow:0 5px 13px rgba(18,86,111,.08);transform:translateY(-1px)}
        .maintenance-support__link span:last-child{overflow-wrap:anywhere}
        .maintenance-support__link-icon{align-items:center;background:#eaf7fb;border-radius:6px;color:#078fbe;display:flex;flex:0 0 26px;height:26px;justify-content:center}
        .maintenance-support__icon svg{height:18px;width:18px}
        .maintenance-support__link-icon svg{height:13px;width:13px}
        .maintenance-bottom{align-items:center;border-top:1px solid #e3ebf0;display:flex;justify-content:space-between;margin-top:19px;padding-top:17px}
        .maintenance-bottom__copy{color:#82929e;font-size:11px;line-height:1.45;margin:0;text-align:left}
        .maintenance-actions{display:flex;gap:9px;justify-content:flex-end;margin:0}
        .maintenance-actions a{border-radius:10px;font-size:13px;font-weight:700;padding:11px 18px;text-decoration:none;transition:transform .15s ease,box-shadow .15s ease}
        .maintenance-actions a:hover{transform:translateY(-1px)}
        .maintenance-retry{background:#079bd3;box-shadow:0 8px 18px rgba(7,155,211,.2);color:#fff}
        .maintenance-admin{border:1px solid #cbd8df;color:#405673}
        .maintenance-icon-action{align-items:center;display:inline-flex;height:42px;justify-content:center;padding:0!important;width:42px}
        .maintenance-icon-action svg{height:20px;width:20px}
        .maintenance-icon-action--developer{background:#eef9fd;border-color:#9ed9ed;color:#087da8}
        .maintenance-icon-action--administrator{background:#f4f6fb;border-color:#c9d2e1;color:#344e72}
        .maintenance-icon-action:focus{box-shadow:0 0 0 3px rgba(7,155,211,.18);outline:0}
        .maintenance-sr-only{height:1px;margin:-1px;overflow:hidden;padding:0;position:absolute;width:1px;clip:rect(0,0,0,0);white-space:nowrap}
        .maintenance-footer{color:#9aa9b2;font-size:11px;line-height:1.4;margin-top:14px;text-align:center}
        @media(max-width:760px){.maintenance-status{grid-template-columns:1fr}.maintenance-locale{right:28px}.maintenance-time{min-height:0}.maintenance-support__body{grid-template-columns:1fr}}
        @media(max-width:650px){body{padding:12px}.maintenance-card{border-radius:18px}.maintenance-content{padding:22px 18px 20px}.maintenance-brands{gap:18px;margin-bottom:17px;padding-bottom:16px}.maintenance-brands__oneid{width:40%}.maintenance-brands__office{width:44%}.maintenance-locale{margin:0 auto 13px;position:static}.maintenance-status{gap:16px;padding:20px 17px}.maintenance-status:before{top:22px}.maintenance-hero{gap:12px}.maintenance-icon{border-radius:13px;flex-basis:48px;height:48px;width:48px}.maintenance-icon svg{height:23px;width:23px}h1{font-size:24px}.maintenance-message{font-size:14px}.maintenance-support__body{gap:14px}.maintenance-bottom{align-items:stretch;flex-direction:column;gap:13px}.maintenance-bottom__copy{text-align:center}.maintenance-actions{flex-wrap:wrap;justify-content:center}.maintenance-retry{flex:1 0 calc(100% - 102px)}.maintenance-actions a.maintenance-icon-action{width:42px}}
    </style>
</head>
<body>
<main class="maintenance-card">
    <div class="maintenance-accent"></div>
    <div class="maintenance-content">
        <div class="maintenance-brands" aria-label="OneID dan Universiti Pertahanan Nasional Malaysia">
            <img class="maintenance-brands__oneid" src="<?=htmlspecialchars($assetBase . '/img/logo_oneid.png', ENT_QUOTES, 'UTF-8')?>" alt="OneID">
            <img class="maintenance-brands__office" src="<?=htmlspecialchars($assetBase . '/img/logo_upnm_30.png', ENT_QUOTES, 'UTF-8')?>" alt="Universiti Pertahanan Nasional Malaysia">
        </div>
        <nav class="maintenance-locale" aria-label="Pilihan bahasa / Language selection">
            <a class="<?=$locale === 'ms' ? 'is-active' : ''?>" href="<?=htmlspecialchars(APP_URL . '/?locale=ms', ENT_QUOTES, 'UTF-8')?>" lang="ms" hreflang="ms" aria-current="<?=$locale === 'ms' ? 'true' : 'false'?>">BM</a>
            <a class="<?=$locale === 'en' ? 'is-active' : ''?>" href="<?=htmlspecialchars(APP_URL . '/?locale=en', ENT_QUOTES, 'UTF-8')?>" lang="en" hreflang="en" aria-current="<?=$locale === 'en' ? 'true' : 'false'?>">EN</a>
        </nav>
        <section class="maintenance-status" aria-labelledby="maintenance-page-title">
            <div class="maintenance-hero">
                <div class="maintenance-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/><circle cx="12" cy="12" r="5"/></svg>
                </div>
                <div>
                    <div class="maintenance-eyebrow"><?=htmlspecialchars($serviceEyebrow, ENT_QUOTES, 'UTF-8')?></div>
                    <h1 id="maintenance-page-title"><?=htmlspecialchars($title, ENT_QUOTES, 'UTF-8')?></h1>
                    <p class="maintenance-message"><?=nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'))?></p>
                </div>
            </div>
            <?php if ($end): ?>
                <div class="maintenance-time">
                    <span class="maintenance-time__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/><path d="m9 16 2 2 4-4"/></svg>
                    </span>
                    <div>
                        <span><?=htmlspecialchars($locale === 'en' ? 'Expected restoration' : 'Jangkaan pemulihan', ENT_QUOTES, 'UTF-8')?></span>
                        <strong><?=htmlspecialchars($end, ENT_QUOTES, 'UTF-8')?></strong>
                    </div>
                </div>
            <?php endif; ?>
        </section>
        <section class="maintenance-support" aria-labelledby="maintenance-support-title">
            <div class="maintenance-support__head">
                <span class="maintenance-support__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a8 8 0 0 1 16 0"/><path d="M18 19c0 1.1-.9 2-2 2h-3"/><path d="M4 14v3a2 2 0 0 0 2 2h1v-7H6a2 2 0 0 0-2 2Zm16 0v3a2 2 0 0 1-2 2h-1v-7h1a2 2 0 0 1 2 2Z"/></svg>
                </span>
                <div>
                    <strong id="maintenance-support-title"><?=htmlspecialchars($supportHeading, ENT_QUOTES, 'UTF-8')?></strong>
                    <span><?=htmlspecialchars($supportIntro, ENT_QUOTES, 'UTF-8')?></span>
                </div>
            </div>
            <div class="maintenance-support__body">
                <div class="maintenance-support__identity">
                    <strong><?=htmlspecialchars($supportService, ENT_QUOTES, 'UTF-8')?></strong>
                    <p><?=htmlspecialchars($supportDivision, ENT_QUOTES, 'UTF-8')?></p>
                    <p>Kem Perdana Sungai Besi, 57000 Kuala Lumpur</p>
                </div>
                <div class="maintenance-support__contacts">
                    <a class="maintenance-support__link" href="tel:+60390512700">
                        <span class="maintenance-support__link-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.69 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.33 1.85.56 2.81.69A2 2 0 0 1 22 16.92Z"/></svg>
                        </span>
                        <span>03-9051 2700</span>
                    </a>
                    <a class="maintenance-support__link" href="mailto:ask.oneid@upnm.edu.my">
                        <span class="maintenance-support__link-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        </span>
                        <span>ask.oneid@upnm.edu.my</span>
                    </a>
                </div>
            </div>
        </section>
        <div class="maintenance-bottom">
            <p class="maintenance-bottom__copy"><?=htmlspecialchars($locale === 'en' ? 'You may retry when the maintenance window has ended.' : 'Anda boleh mencuba semula selepas tempoh penyelenggaraan tamat.', ENT_QUOTES, 'UTF-8')?></p>
            <div class="maintenance-actions">
                <a class="maintenance-retry" href="<?=htmlspecialchars(APP_URL . '/', ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($locale === 'en' ? 'Try Again' : 'Cuba Lagi', ENT_QUOTES, 'UTF-8')?></a>
                <?php if (oneid_maintenance_developer_access_enabled()): ?>
                <a class="maintenance-admin maintenance-icon-action maintenance-icon-action--developer" href="<?=htmlspecialchars(APP_URL . '/maintenance/developer-login.php', ENT_QUOTES, 'UTF-8')?>" aria-label="<?=htmlspecialchars($locale === 'en' ? 'Developer Login' : 'Log Masuk Developer', ENT_QUOTES, 'UTF-8')?>" title="<?=htmlspecialchars($locale === 'en' ? 'Developer Login' : 'Log Masuk Developer', ENT_QUOTES, 'UTF-8')?>">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m8 9-4 3 4 3"/><path d="m16 9 4 3-4 3"/><path d="m14 5-4 14"/></svg>
                    <span class="maintenance-sr-only"><?=htmlspecialchars($locale === 'en' ? 'Developer Login' : 'Log Masuk Developer', ENT_QUOTES, 'UTF-8')?></span>
                </a>
                <?php endif; ?>
                <a class="maintenance-admin maintenance-icon-action maintenance-icon-action--administrator" href="<?=htmlspecialchars(APP_URL . '/admin/login.php', ENT_QUOTES, 'UTF-8')?>" aria-label="<?=htmlspecialchars($locale === 'en' ? 'Administrator Login' : 'Log Masuk Pentadbir', ENT_QUOTES, 'UTF-8')?>" title="<?=htmlspecialchars($locale === 'en' ? 'Administrator Login' : 'Log Masuk Pentadbir', ENT_QUOTES, 'UTF-8')?>">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><circle cx="12" cy="10" r="2"/><path d="M8.5 16a4 4 0 0 1 7 0"/></svg>
                    <span class="maintenance-sr-only"><?=htmlspecialchars($locale === 'en' ? 'Administrator Login' : 'Log Masuk Pentadbir', ENT_QUOTES, 'UTF-8')?></span>
                </a>
            </div>
        </div>
        <footer class="maintenance-footer"><?=htmlspecialchars($applicationFooter, ENT_QUOTES, 'UTF-8')?></footer>
    </div>
</main>
</body>
</html>
