<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

final class MyDigitalIdAccessDeniedEndpoint
{
    public static function run(): never
    {
        $root = dirname(__DIR__, 3);
        require_once $root . '/bootstrap/app.php';
        require_once $root . '/lib/session_security.php';
        require_once $root . '/lib/request_security.php';
        require_once $root . '/vendor/autoload.php';

        $enabled = filter_var(
            \oneid_config('ONEID_MYDID_ENABLED', 'false'),
            FILTER_VALIDATE_BOOLEAN
        );
        if (!$enabled) {
            self::finish(404, 'Not Found');
        }

        \oneid_start_secure_session();
        if (isset($_GET['locale'])) {
            if (\oneid_set_session_locale((string) $_GET['locale'])) {
                \oneid_set_guest_locale_cookie((string) $_GET['locale']);
            }
            self::redirect(\oneid_config('ONEID_APP_URL', '') . '/auth/mydigitalid/access-denied.php');
        }

        if (!MyDigitalIdRejectedLogoutState::isAvailable($_SESSION, time())) {
            $_SESSION['oneid_login_flash'] = 'mydigitalid_invalid';
            session_write_close();
            self::redirect(\oneid_config('ONEID_APP_URL', '') . '/');
        }

        $appUrl = rtrim((string) \oneid_config('ONEID_APP_URL', ''), '/');
        $locale = \oneid_current_locale();
        $csrf = \oneid_csrf_token();
        self::securityHeaders();
        header('Content-Type: text/html; charset=UTF-8');

        $escape = static fn(string $value): string => htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        ?>
<!doctype html>
<html lang="<?=$escape($locale)?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?=$escape(\oneid_translate('mydigitalid.denied.page_title'))?></title>
  <link rel="shortcut icon" href="<?=$escape($appUrl)?>/img/favicon.png">
  <style>
    :root {
      color-scheme: light;
      --navy: #0b2d4d;
      --blue: #087fbd;
      --cyan: #09a3c6;
      --muted: #66778b;
      --line: #dce6ee;
    }
    * { box-sizing: border-box; }
    body {
      min-height: 100vh;
      margin: 0;
      display: grid;
      place-items: center;
      padding: 28px 16px;
      background:
        radial-gradient(circle at 15% 15%, rgba(9, 163, 198, .12), transparent 34%),
        radial-gradient(circle at 88% 82%, rgba(8, 127, 189, .1), transparent 32%),
        #f4f8fb;
      color: var(--navy);
      font-family: Inter, ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }
    .denied-shell { width: min(100%, 520px); }
    .locale {
      display: flex;
      justify-content: flex-end;
      gap: 6px;
      margin-bottom: 12px;
    }
    .locale a {
      padding: 7px 11px;
      border-radius: 999px;
      color: var(--muted);
      font-size: 12px;
      font-weight: 700;
      text-decoration: none;
    }
    .locale a[aria-current="true"] {
      background: #fff;
      color: var(--blue);
      box-shadow: 0 3px 12px rgba(11, 45, 77, .09);
    }
    .denied-card {
      overflow: hidden;
      border: 1px solid rgba(11, 45, 77, .09);
      border-radius: 22px;
      background: rgba(255, 255, 255, .96);
      box-shadow: 0 24px 60px rgba(18, 74, 112, .15);
    }
    .brand {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
      padding: 22px 26px;
      border-bottom: 1px solid var(--line);
      background: linear-gradient(145deg, #fff, #f7fbfe);
    }
    .brand-oneid { width: 155px; height: auto; }
    .brand-mydid { width: 118px; height: auto; }
    .content { padding: 32px 34px 34px; text-align: center; }
    .status-icon {
      width: 68px;
      height: 68px;
      margin: 0 auto 20px;
      display: grid;
      place-items: center;
      border: 8px solid #fff3f0;
      border-radius: 50%;
      background: #ffe3dc;
      color: #c33b2b;
      font-size: 30px;
      font-weight: 800;
      box-shadow: 0 0 0 1px rgba(195, 59, 43, .08);
    }
    .eyebrow {
      margin: 0 0 8px;
      color: var(--blue);
      font-size: 11px;
      font-weight: 800;
      letter-spacing: .1em;
      text-transform: uppercase;
    }
    h1 { margin: 0; font-size: clamp(24px, 5vw, 31px); line-height: 1.2; }
    .message {
      margin: 14px auto 0;
      max-width: 410px;
      color: #4d6173;
      font-size: 15px;
      line-height: 1.65;
    }
    .notice {
      margin: 22px 0;
      padding: 13px 15px;
      border: 1px solid #dbeaf2;
      border-radius: 12px;
      background: #f3f9fc;
      color: #48677b;
      font-size: 12px;
      line-height: 1.5;
    }
    .actions { display: grid; gap: 10px; }
    button, .secondary {
      width: 100%;
      min-height: 48px;
      border-radius: 12px;
      font: inherit;
      font-size: 14px;
      font-weight: 750;
      cursor: pointer;
    }
    button {
      border: 0;
      background: linear-gradient(118deg, #075b9a, var(--blue) 58%, var(--cyan));
      color: #fff;
      box-shadow: 0 8px 18px rgba(5, 92, 148, .2);
    }
    button:hover { filter: brightness(1.04); }
    button:focus-visible, .secondary:focus-visible {
      outline: 3px solid rgba(8, 127, 189, .25);
      outline-offset: 2px;
    }
    .secondary {
      display: grid;
      place-items: center;
      border: 1px solid #cfdce5;
      color: var(--navy);
      text-decoration: none;
      background: #fff;
    }
    .help { margin: 18px 0 0; color: #7a8997; font-size: 11px; line-height: 1.5; }
    @media (max-width: 480px) {
      .brand { padding: 18px 20px; }
      .brand-oneid { width: 132px; }
      .brand-mydid { width: 100px; }
      .content { padding: 27px 21px 28px; }
    }
  </style>
</head>
<body>
  <main class="denied-shell">
    <nav class="locale" aria-label="<?=$escape(\oneid_translate('login.language_label'))?>">
      <a href="?locale=ms" aria-current="<?=$locale === 'ms' ? 'true' : 'false'?>">BM</a>
      <a href="?locale=en" aria-current="<?=$locale === 'en' ? 'true' : 'false'?>">EN</a>
    </nav>
    <section class="denied-card" aria-labelledby="denied-title">
      <header class="brand">
        <img class="brand-oneid" src="<?=$escape($appUrl)?>/img/logo_oneid.png" alt="OneID">
        <img class="brand-mydid" src="<?=$escape($appUrl)?>/img/mydigitalid_logo_colored.svg" alt="MyDigital ID">
      </header>
      <div class="content">
        <div class="status-icon" aria-hidden="true">!</div>
        <p class="eyebrow"><?=$escape(\oneid_translate('mydigitalid.denied.eyebrow'))?></p>
        <h1 id="denied-title"><?=$escape(\oneid_translate('mydigitalid.denied.heading'))?></h1>
        <p class="message"><?=$escape(\oneid_translate('mydigitalid.denied.message'))?></p>
        <div class="notice"><?=$escape(\oneid_translate('mydigitalid.denied.notice'))?></div>
        <div class="actions">
          <form action="<?=$escape($appUrl)?>/auth/mydigitalid/switch-account.php" method="post">
            <input type="hidden" name="_csrf_token" value="<?=$escape($csrf)?>">
            <button type="submit"><?=$escape(\oneid_translate('login.mydigitalid.switch_account'))?></button>
          </form>
          <a class="secondary" href="<?=$escape($appUrl)?>/"><?=$escape(\oneid_translate('mydigitalid.denied.password_login'))?></a>
        </div>
        <p class="help"><?=$escape(\oneid_translate('mydigitalid.denied.help'))?></p>
      </div>
    </section>
  </main>
</body>
</html>
<?php
        exit;
    }

    private static function securityHeaders(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'self'; img-src 'self'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
    }

    private static function redirect(string $url): never
    {
        self::securityHeaders();
        header('Location: ' . $url, true, 303);
        exit;
    }

    private static function finish(int $status, string $body): never
    {
        http_response_code($status);
        self::securityHeaders();
        header('Content-Type: text/plain; charset=UTF-8');
        echo $body;
        exit;
    }
}
