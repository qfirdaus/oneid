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
    : '2026 © PTMK | Aplikasi Digital. Version 2.10.3';
?>
<!doctype html>
<html lang="<?=htmlspecialchars($locale, ENT_QUOTES, 'UTF-8')?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?=htmlspecialchars($title, ENT_QUOTES, 'UTF-8')?></title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;background:linear-gradient(135deg,#edf7fb,#dceaf3);color:#173848;font-family:Arial,sans-serif;min-height:100vh;display:grid;place-items:center;padding:22px}
        .maintenance-card{background:#fff;border-radius:20px;box-shadow:0 26px 75px rgba(15,40,54,.2);max-width:820px;overflow:hidden;text-align:center;width:100%}
        .maintenance-accent{height:6px;background:linear-gradient(90deg,#ff5b2b,#f5ac27,#079bd3,#173d79)}
        .maintenance-content{padding:28px 56px 25px}
        .maintenance-brands{align-items:center;border-bottom:1px solid #e4edf2;display:flex;justify-content:space-between;margin-bottom:20px;padding:0 4px 16px}
        .maintenance-brands img{display:block;height:auto;object-fit:contain}
        .maintenance-brands__oneid{max-width:176px;width:31%}
        .maintenance-brands__office{max-width:187px;width:33%}
        .maintenance-eyebrow{color:#0877a8;font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
        .maintenance-locale{align-items:center;background:#f2f7fa;border:1px solid #d9e7ee;border-radius:999px;display:inline-flex;gap:2px;margin:0 auto 10px;padding:3px}
        .maintenance-locale a{border-radius:999px;color:#607487;font-size:11px;font-weight:800;line-height:1;padding:7px 10px;text-decoration:none}
        .maintenance-locale a.is-active{background:#079bd3;box-shadow:0 3px 9px rgba(7,155,211,.2);color:#fff}
        .maintenance-icon{align-items:center;background:#fff7e8;border-radius:50%;color:#d98a00;display:flex;height:58px;justify-content:center;margin:10px auto 14px;width:58px}
        .maintenance-icon svg{height:25px;width:25px}
        h1{color:#12334a;font-size:29px;line-height:1.25;margin:0 auto 9px;max-width:680px}
        .maintenance-message{color:#5f7087;font-size:16px;line-height:1.5;margin:0 auto;max-width:700px}
        .maintenance-time{background:#eef7fb;border:1px solid #d2e9f2;border-radius:9px;color:#356276;margin:17px auto 0;max-width:700px;padding:11px 16px}
        .maintenance-support{background:#f8fbfd;border:1px solid #d9e7ee;border-radius:12px;margin:16px auto 0;max-width:700px;overflow:hidden;text-align:left}
        .maintenance-support__head{align-items:center;background:#eef7fb;border-bottom:1px solid #d9e7ee;display:flex;gap:10px;padding:10px 14px}
        .maintenance-support__icon{align-items:center;background:#fff;border:1px solid #d2e9f2;border-radius:8px;color:#078fbe;display:flex;flex:0 0 34px;height:34px;justify-content:center}
        .maintenance-support__head strong{color:#173848;display:block;font-size:14px;line-height:1.35}
        .maintenance-support__head span{color:#66798c;display:block;font-size:12px;line-height:1.45;margin-top:2px}
        .maintenance-support__body{display:grid;gap:12px;grid-template-columns:minmax(0,1.45fr) minmax(210px,.8fr);padding:13px 14px}
        .maintenance-support__identity strong{color:#173848;display:block;font-size:14px;line-height:1.45;margin-bottom:5px}
        .maintenance-support__identity p{color:#607487;font-size:12px;line-height:1.45;margin:0}
        .maintenance-support__contacts{display:grid;gap:6px}
        .maintenance-support__link{align-items:center;background:#fff;border:1px solid #dbe7ed;border-radius:8px;color:#18566f;display:flex;font-size:12px;font-weight:700;gap:8px;min-width:0;padding:7px 9px;text-decoration:none;transition:border-color .15s ease,box-shadow .15s ease,transform .15s ease}
        .maintenance-support__link:hover{border-color:#8fcde2;box-shadow:0 5px 13px rgba(18,86,111,.08);transform:translateY(-1px)}
        .maintenance-support__link span:last-child{overflow-wrap:anywhere}
        .maintenance-support__link-icon{align-items:center;background:#eaf7fb;border-radius:6px;color:#078fbe;display:flex;flex:0 0 26px;height:26px;justify-content:center}
        .maintenance-support__icon svg{height:18px;width:18px}
        .maintenance-support__link-icon svg{height:13px;width:13px}
        .maintenance-actions{display:flex;gap:10px;justify-content:center;margin-top:17px}
        .maintenance-actions a{border-radius:8px;font-size:14px;font-weight:700;padding:11px 18px;text-decoration:none;transition:transform .15s ease,box-shadow .15s ease}
        .maintenance-actions a:hover{transform:translateY(-1px)}
        .maintenance-retry{background:#079bd3;box-shadow:0 8px 18px rgba(7,155,211,.2);color:#fff}
        .maintenance-admin{border:1px solid #cbd8df;color:#405673}
        .maintenance-icon-action{align-items:center;display:inline-flex;height:42px;justify-content:center;padding:0!important;width:42px}
        .maintenance-icon-action svg{height:20px;width:20px}
        .maintenance-icon-action--developer{background:#eef9fd;border-color:#9ed9ed;color:#087da8}
        .maintenance-icon-action--administrator{background:#f4f6fb;border-color:#c9d2e1;color:#344e72}
        .maintenance-icon-action:focus{box-shadow:0 0 0 3px rgba(7,155,211,.18);outline:0}
        .maintenance-sr-only{height:1px;margin:-1px;overflow:hidden;padding:0;position:absolute;width:1px;clip:rect(0,0,0,0);white-space:nowrap}
        .maintenance-footer{color:#9aa9b2;font-size:11px;line-height:1.4;margin-top:13px}
        @media(max-width:650px){body{padding:14px}.maintenance-content{padding:24px 20px 23px}.maintenance-brands{gap:18px;margin-bottom:18px;padding-bottom:14px}.maintenance-brands__oneid{width:40%}.maintenance-brands__office{width:44%}h1{font-size:25px}.maintenance-message{font-size:15px}.maintenance-support__body{grid-template-columns:1fr}.maintenance-actions{flex-wrap:wrap}.maintenance-retry{flex:1 0 100%}.maintenance-actions a.maintenance-icon-action{width:42px}}
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
        <div class="maintenance-eyebrow"><?=htmlspecialchars($serviceEyebrow, ENT_QUOTES, 'UTF-8')?></div>
        <div class="maintenance-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/><circle cx="12" cy="12" r="5"/></svg>
        </div>
        <h1><?=htmlspecialchars($title, ENT_QUOTES, 'UTF-8')?></h1>
        <p class="maintenance-message"><?=nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'))?></p>
        <?php if ($end): ?>
            <div class="maintenance-time"><?=htmlspecialchars($locale === 'en' ? 'Expected service restoration: ' : 'Dijangka tersedia semula: ', ENT_QUOTES, 'UTF-8')?><?=htmlspecialchars($end, ENT_QUOTES, 'UTF-8')?></div>
        <?php endif; ?>
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
        <footer class="maintenance-footer"><?=htmlspecialchars($applicationFooter, ENT_QUOTES, 'UTF-8')?></footer>
    </div>
</main>
</body>
</html>
