<?php
$locale = function_exists('oneid_current_locale') ? oneid_current_locale() : 'ms';
$title = $locale === 'en' ? $policy['title_en'] : $policy['title_ms'];
$message = $locale === 'en' ? $policy['message_en'] : $policy['message_ms'];
$end = $policy['ends_at'] ? date('j M Y, g:i A', strtotime($policy['ends_at'] . ' UTC')) : null;
$assetBase = rtrim(APP_URL, '/');
$supportHeading = $locale === 'en' ? 'Need assistance?' : 'Perlukan bantuan?';
$supportIntro = $locale === 'en' ? 'Contact the official OneID support service.' : 'Hubungi perkhidmatan sokongan rasmi OneID.';
?>
<!doctype html>
<html lang="<?=htmlspecialchars($locale, ENT_QUOTES, 'UTF-8')?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?=htmlspecialchars($title, ENT_QUOTES, 'UTF-8')?></title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;background:linear-gradient(135deg,#edf7fb,#dceaf3);color:#173848;font-family:Arial,sans-serif;min-height:100vh;display:grid;place-items:center;padding:28px}
        .maintenance-card{background:#fff;border-radius:20px;box-shadow:0 26px 75px rgba(15,40,54,.2);max-width:820px;overflow:hidden;text-align:center;width:100%}
        .maintenance-accent{height:6px;background:linear-gradient(90deg,#ff5b2b,#f5ac27,#079bd3,#173d79)}
        .maintenance-content{padding:38px 64px 42px}
        .maintenance-brands{align-items:center;border-bottom:1px solid #e4edf2;display:flex;justify-content:space-between;margin-bottom:28px;padding:0 4px 22px}
        .maintenance-brands img{display:block;height:auto;object-fit:contain}
        .maintenance-brands__oneid{max-width:176px;width:31%}
        .maintenance-brands__office{max-width:187px;width:33%}
        .maintenance-eyebrow{color:#0877a8;font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
        .maintenance-icon{align-items:center;background:#fff7e8;border-radius:50%;color:#d98a00;display:flex;font-size:32px;height:72px;justify-content:center;margin:14px auto 20px;width:72px}
        h1{color:#12334a;font-size:31px;line-height:1.25;margin:0 auto 14px;max-width:680px}
        .maintenance-message{color:#5f7087;font-size:17px;line-height:1.65;margin:0 auto;max-width:700px}
        .maintenance-time{background:#eef7fb;border:1px solid #d2e9f2;border-radius:9px;color:#356276;margin:24px auto 0;max-width:700px;padding:14px 18px}
        .maintenance-support{background:#f8fbfd;border:1px solid #d9e7ee;border-radius:12px;margin:24px auto 0;max-width:700px;overflow:hidden;text-align:left}
        .maintenance-support__head{align-items:center;background:#eef7fb;border-bottom:1px solid #d9e7ee;display:flex;gap:12px;padding:14px 16px}
        .maintenance-support__icon{align-items:center;background:#fff;border:1px solid #d2e9f2;border-radius:9px;color:#078fbe;display:flex;flex:0 0 38px;font-size:18px;height:38px;justify-content:center}
        .maintenance-support__head strong{color:#173848;display:block;font-size:14px;line-height:1.35}
        .maintenance-support__head span{color:#66798c;display:block;font-size:12px;line-height:1.45;margin-top:2px}
        .maintenance-support__body{display:grid;gap:14px;grid-template-columns:minmax(0,1.45fr) minmax(210px,.8fr);padding:16px}
        .maintenance-support__identity strong{color:#173848;display:block;font-size:14px;line-height:1.45;margin-bottom:5px}
        .maintenance-support__identity p{color:#607487;font-size:12px;line-height:1.6;margin:0}
        .maintenance-support__contacts{display:grid;gap:8px}
        .maintenance-support__link{align-items:center;background:#fff;border:1px solid #dbe7ed;border-radius:8px;color:#18566f;display:flex;font-size:12px;font-weight:700;gap:9px;min-width:0;padding:10px 11px;text-decoration:none;transition:border-color .15s ease,box-shadow .15s ease,transform .15s ease}
        .maintenance-support__link:hover{border-color:#8fcde2;box-shadow:0 5px 13px rgba(18,86,111,.08);transform:translateY(-1px)}
        .maintenance-support__link span:last-child{overflow-wrap:anywhere}
        .maintenance-support__link-icon{align-items:center;background:#eaf7fb;border-radius:6px;color:#078fbe;display:flex;flex:0 0 28px;height:28px;justify-content:center}
        .maintenance-actions{display:flex;gap:11px;justify-content:center;margin-top:24px}
        .maintenance-actions a{border-radius:8px;font-size:14px;font-weight:700;padding:13px 20px;text-decoration:none;transition:transform .15s ease,box-shadow .15s ease}
        .maintenance-actions a:hover{transform:translateY(-1px)}
        .maintenance-retry{background:#079bd3;box-shadow:0 8px 18px rgba(7,155,211,.2);color:#fff}
        .maintenance-admin{border:1px solid #cbd8df;color:#405673}
        @media(max-width:650px){body{padding:16px}.maintenance-content{padding:28px 22px 32px}.maintenance-brands{gap:18px}.maintenance-brands__oneid{width:40%}.maintenance-brands__office{width:44%}h1{font-size:25px}.maintenance-message{font-size:15px}.maintenance-support__body{grid-template-columns:1fr}.maintenance-actions{flex-direction:column}.maintenance-actions a{width:100%}}
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
        <div class="maintenance-eyebrow">OneID Service Status</div>
        <div class="maintenance-icon" aria-hidden="true">⚙</div>
        <h1><?=htmlspecialchars($title, ENT_QUOTES, 'UTF-8')?></h1>
        <p class="maintenance-message"><?=nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'))?></p>
        <?php if ($end): ?>
            <div class="maintenance-time"><?=htmlspecialchars($locale === 'en' ? 'Expected service restoration: ' : 'Dijangka tersedia semula: ', ENT_QUOTES, 'UTF-8')?><?=htmlspecialchars($end, ENT_QUOTES, 'UTF-8')?></div>
        <?php endif; ?>
        <section class="maintenance-support" aria-labelledby="maintenance-support-title">
            <div class="maintenance-support__head">
                <span class="maintenance-support__icon" aria-hidden="true">☎</span>
                <div>
                    <strong id="maintenance-support-title"><?=htmlspecialchars($supportHeading, ENT_QUOTES, 'UTF-8')?></strong>
                    <span><?=htmlspecialchars($supportIntro, ENT_QUOTES, 'UTF-8')?></span>
                </div>
            </div>
            <div class="maintenance-support__body">
                <div class="maintenance-support__identity">
                    <strong>OneID@UPNM Support Service</strong>
                    <p>Information and Communication Technology Division, National Defence University of Malaysia (UPNM)</p>
                    <p>Kem Perdana Sungai Besi, 57000 Kuala Lumpur</p>
                </div>
                <div class="maintenance-support__contacts">
                    <a class="maintenance-support__link" href="tel:+60390512700">
                        <span class="maintenance-support__link-icon" aria-hidden="true">☎</span>
                        <span>03-9051 2700</span>
                    </a>
                    <a class="maintenance-support__link" href="mailto:ask.oneid@upnm.edu.my">
                        <span class="maintenance-support__link-icon" aria-hidden="true">✉</span>
                        <span>ask.oneid@upnm.edu.my</span>
                    </a>
                </div>
            </div>
        </section>
        <div class="maintenance-actions">
            <a class="maintenance-retry" href="<?=htmlspecialchars(APP_URL . '/', ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($locale === 'en' ? 'Try Again' : 'Cuba Lagi', ENT_QUOTES, 'UTF-8')?></a>
            <a class="maintenance-admin" href="<?=htmlspecialchars(APP_URL . '/admin/login.php', ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($locale === 'en' ? 'Administrator Login' : 'Log Masuk Pentadbir', ENT_QUOTES, 'UTF-8')?></a>
        </div>
    </div>
</main>
</body>
</html>
