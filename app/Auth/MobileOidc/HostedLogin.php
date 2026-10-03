<?php
declare(strict_types=1);
namespace OneId\App\Auth\MobileOidc;

final class HostedLogin
{
    private string $language = 'ms';

    private function text(string $source): string
    {
        if ($this->language !== 'en') return $source;
        static $english = null;
        $english ??= require __DIR__ . '/HostedLoginEnglish.php';
        return $english[$source] ?? $source;
    }

    public function __construct(private readonly MobileIdentityAdapter $adapter, private readonly array $config, private readonly ?MobileMyDigitalId $myDigitalId = null, private readonly ?MobilePasswordRecovery $recovery = null) {}

    public function handle(string $method): void
    {
        $secure = str_starts_with($this->config['origin'], 'https://');
        ini_set('session.use_strict_mode', '1'); ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0'); ini_set('session.gc_maxlifetime', '900');
        session_name($secure ? '__Host-oneid_mobile' : 'oneid_mobile_fixture');
        session_save_path($this->config['session_path']);
        session_cache_limiter(''); // Entry point owns no-store headers consistently across all endpoints.
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
        $language = $_GET['lang'] ?? ($_SESSION['language'] ?? 'ms');
        $this->language = is_string($language) && in_array($language, ['ms','en'], true) ? $language : 'ms';
        $_SESSION['language'] = $this->language;
        $agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1024);
        $message = '';
        if ($method === 'GET' && parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/mobile/mydigitalid/callback') {
            try {
                if (!$this->myDigitalId) throw new \RuntimeException('MOBILE_MYDID_DISABLED');
                $r = $this->myDigitalId->finish($_SESSION,$agent,(string)($_SERVER['REMOTE_ADDR']??''),$_GET);
                if (($r['code']??'')==='AUTHENTICATION_READY') $r=$this->adapter->complete($_SESSION['tx'],$_SESSION['binding'],$agent);
                if (isset($r['redirect_to'])) {
                    $_SESSION=[]; session_destroy();
                    setcookie(session_name(), '', ['expires'=>time()-3600,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
                    header('Location: '.$r['redirect_to'],true,303); return;
                }
                $_SESSION['stage'] = ($r['code']??'')==='ACCOUNT_REQUIRED' ? 'ACCOUNT' : (($r['error']??'')==='MOBILE_PASSWORD_CHANGE_REQUIRED' ? 'CHANGE' : 'ERROR');
                $_SESSION['choices']=$r['choices']??[];
                $message = $_SESSION['stage']==='ACCOUNT' ? '' : $this->text($_SESSION['stage']==='CHANGE' ? 'Kata laluan anda perlu ditukar sebelum meneruskan.' : 'Pengesahan MyDigital ID tidak berjaya. Mulakan semula dari aplikasi.');
            } catch (\Throwable) {
                $_SESSION['stage']='ERROR';
                $message=$this->text('Pengesahan MyDigital ID tidak berjaya. Mulakan semula dari aplikasi.');
            }
            $this->render($_SESSION['stage'],$_SESSION['csrf']??'',$message,$_SESSION['choices']??[],200); session_write_close(); return;
        }
        if ($method === 'GET' && isset($_GET['login_challenge'])) {
            if (!is_string($_GET['login_challenge'])) { $this->render('ERROR', '', $this->text('Permintaan tidak sah.'), [], 400); return; }
            session_regenerate_id(true);
            $_SESSION = ['language' => $this->language, 'binding' => bin2hex(random_bytes(32)), 'csrf' => bin2hex(random_bytes(32)), 'stage' => 'PASSWORD'];
            $r = $this->adapter->begin($_GET['login_challenge'], $_SESSION['binding'], $agent, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
            if (isset($r['error'])) { $this->render('ERROR', '', $this->text('Log masuk tidak dapat dimulakan. Cuba semula dari aplikasi.'), [], 400); return; }
            $_SESSION['tx'] = $r['transaction_id'];
        } elseif ($method === 'POST') {
            if (($_SERVER['HTTP_ORIGIN'] ?? '') !== $this->config['origin']
                || !is_string($_POST['csrf'] ?? null) || !isset($_SESSION['csrf'])
                || !hash_equals($_SESSION['csrf'], $_POST['csrf']) || !isset($_SESSION['tx'], $_SESSION['binding'])) {
                $this->render('ERROR', '', $this->text('Permintaan tidak sah. Mulakan semula dari aplikasi.'), [], 403); return;
            }
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            $field = static fn($key) => is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
            $args = [$_SESSION['tx'], $_SESSION['binding'], $agent];
            if (str_starts_with($field('action'), 'recovery_')) {
                $result = 'ERROR';
                try {
                    if (!$this->recovery) throw new \RuntimeException('RECOVERY_UNAVAILABLE');
                    $action=$field('action');
                    if ($action==='recovery_begin' && ($_SESSION['stage']??'')==='PASSWORD') {
                        $id=$this->recovery->begin(...$args);
                        if ($id!==null) { $_SESSION['recovery_id']=$id; $result='IDENTITY'; }
                    } elseif (isset($_SESSION['recovery_id'])) {
                        $id=$_SESSION['recovery_id'];
                        $result=match($action) {
                            'recovery_cancel'=>$this->recovery->cancel($id,...$args)?'LOGIN':'ERROR',
                            'recovery_request'=>$this->recovery->request($id,$field('identity'),(string)($_SERVER['REMOTE_ADDR']??'')),
                            'recovery_verify'=>$this->recovery->verify($id,$field('code')),
                            'recovery_reset'=>$this->recovery->reset($id,$field('new_password'),$field('confirmation')),
                            default=>'ERROR',
                        };
                    }
                } catch (\Throwable) { $result='ERROR'; error_log('Mobile recovery operation unavailable'); }
                $message=match($result) {
                    'OTP'=>'Jika akaun layak, kod dihantar ke emel berdaftar. Kod sah selama 5 minit.',
                    'INVALID'=>'Kod tidak sah atau telah tamat tempoh.',
                    'MISMATCH'=>'Pengesahan kata laluan tidak sepadan.',
                    'QUALITY'=>'Gunakan sekurang-kurangnya 12 aksara, huruf besar, huruf kecil, nombor dan simbol.',
                    'REUSED'=>'Gunakan kata laluan yang belum digunakan baru-baru ini.',
                    'ERROR'=>'Pemulihan tidak dapat diteruskan. Kembali ke aplikasi dan mulakan semula, atau hubungi Helpdesk.',
                    default=>'',
                };
                if(in_array($result,['IDENTITY','OTP','NEW','DONE','ERROR'],true)) $_SESSION['stage']='RECOVERY_'.$result;
                if($result==='LOGIN') $_SESSION['stage']='PASSWORD';
                if(in_array($result,['DONE','ERROR','LOGIN'],true)) unset($_SESSION['recovery_id']);
                $this->render($_SESSION['stage'],$_SESSION['csrf'],$this->text($message),[],200);
                session_write_close(); return;
            }
            if ($field('action') === 'mydigitalid') {
                try {
                    if (!$this->myDigitalId) throw new \RuntimeException('MOBILE_MYDID_DISABLED');
                    $url=$this->myDigitalId->start($_SESSION,$agent);
                    session_write_close();header('Location: '.$url,true,303);return;
                } catch (\Throwable) {
                    $_SESSION['stage']='ERROR';
                    $this->render('ERROR','',$this->text('Pengesahan MyDigital ID tidak berjaya. Mulakan semula dari aplikasi.'),[],400);session_write_close();return;
                }
            }
            $r = match ($field('action')) {
                'mydigitalid_account' => $this->myDigitalId?->select($_SESSION,$agent,$field('choice')) ?? ['error'=>'MOBILE_REQUEST_INVALID'],
                'password' => $this->adapter->password(...[...$args, $field('identifier'), $field('password')]),
                'send_email' => $this->adapter->sendEmail(...$args),
                'verify' => $this->adapter->verify(...[...$args, $field('factor'), $field('code')]),
                'change' => $this->adapter->changePassword(...[...$args, $field('new_password'), $field('confirmation')]),
                'cancel' => $this->adapter->cancel(...$args),
                default => ['error' => 'MOBILE_REQUEST_INVALID'],
            };
            if ($field('action') === 'mydigitalid_account' && isset($r['error']) && $r['error'] !== 'MOBILE_PASSWORD_CHANGE_REQUIRED') $_SESSION['stage']='ERROR';
            if (($r['code'] ?? '') === 'AUTHENTICATION_READY') $r = $this->adapter->complete(...$args);
            if (isset($r['redirect_to'])) {
                $_SESSION = []; session_destroy();
                setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
                header('Location: ' . $r['redirect_to'], true, 303); return;
            }
            if (($r['code'] ?? '') === 'MFA_REQUIRED') { $_SESSION['stage'] = 'MFA'; $_SESSION['factors'] = $r['factors']; }
            if (($r['error'] ?? '') === 'MOBILE_PASSWORD_CHANGE_REQUIRED') $_SESSION['stage'] = 'CHANGE';
            $message = match ($r['code'] ?? $r['error'] ?? '') {
                'OTP_SENT' => $this->text('Kod telah dihantar ke emel akaun OneID anda.'),
                'MFA_REQUIRED' => $this->text('Lengkapkan pengesahan untuk meneruskan.'),
                'MOBILE_PASSWORD_CHANGE_REQUIRED' => $this->text('Kata laluan anda perlu ditukar sebelum meneruskan.'),
                'MOBILE_CREDENTIALS_INVALID' => $this->text('No. staf/matrik atau kata laluan tidak sah.'),
                'MOBILE_FACTOR_INVALID' => $this->text('Kod tidak sah atau telah tamat tempoh.'),
                'MOBILE_PASSWORD_CONFIRMATION' => $this->text('Pengesahan kata laluan tidak sepadan.'),
                'MOBILE_PASSWORD_REUSED' => $this->text('Gunakan kata laluan yang belum digunakan baru-baru ini.'),
                'MOBILE_PASSWORD_QUALITY' => $this->text('Gunakan sekurang-kurangnya 12 aksara, huruf besar, huruf kecil, nombor dan simbol.'),
                'MOBILE_RESEND_COOLDOWN', 'MOBILE_RATE_LIMITED' => $this->text('Terlalu banyak percubaan. Sila tunggu sebelum mencuba semula.'),
                default => $this->text('Permintaan tidak dapat diselesaikan. Cuba semula atau mulakan semula dari aplikasi.'),
            };
            if ($field('action')==='mydigitalid_account' && $_SESSION['stage']==='ERROR') $message=$this->text('Pilihan akaun tidak lagi sah. Mulakan log masuk semula dari aplikasi.');
            if (in_array($r['error'] ?? '', ['MOBILE_TRANSACTION_INVALID','MOBILE_AUTHORIZATION_UNAVAILABLE','MOBILE_UNAVAILABLE'], true)) $_SESSION['stage'] = 'ERROR';
        }
        $this->render($_SESSION['stage'] ?? 'ERROR', $_SESSION['csrf'] ?? '', $message, ($_SESSION['stage']??'')==='ACCOUNT' ? ($_SESSION['choices']??[]) : ($_SESSION['factors']??[]), isset($_SESSION['tx']) ? 200 : 400);
        session_write_close();
    }

    private function render(string $stage, string $csrf, string $message, array $factors, int $status): void
    {
        require_once dirname(__DIR__, 3) . '/config/application.php';
        http_response_code($status); header('Content-Type: text/html; charset=utf-8');
        header('Content-Language: ' . $this->language);
        $e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $uiNonce = base64_encode(random_bytes(24));
        header("Content-Security-Policy: default-src 'none'; script-src 'nonce-" . $uiNonce . "'; img-src 'self'; style-src 'self'; frame-ancestors 'none'; base-uri 'none'");
        $form = '<form method="post" action="/login"><input type="hidden" name="csrf" value="' . $e($csrf) . '">';
        echo '<!doctype html><html lang="' . $this->language . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $this->text('Login dengan OneID') . '</title><link rel="icon" href="/img/favicon.png"><link rel="stylesheet" href="/login.css?v=20"></head><body class="' . (($stage === 'PASSWORD' || str_starts_with($stage,'RECOVERY_')) ? 'login-compact' : '') . '"><main><header class="brand-header"><img class="logo-upnm" src="/img/logo_upnm_30.png" alt="' . $this->text('Universiti Pertahanan Nasional Malaysia') . '"><img class="logo-oneid" src="/img/logo_oneid.png" alt="OneID"></header><div class="content">';
        echo '<div class="login-toolbar">';
        if (($this->config['environment'] ?? '') === 'staging') {
            echo '<span class="environment-badge">' . $this->text('Persekitaran Ujian') . '</span>';
        }
        echo '<nav class="language-switch" aria-label="' . $this->text('Bahasa') . '">';
        echo '<span class="language-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18M5 6.5h14M5 17.5h14"/></svg></span>';
        foreach (['ms' => 'BM', 'en' => 'EN'] as $lang => $label) {
            echo '<a href="/login?lang=' . $lang . '" lang="' . $lang . '"' . ($lang === $this->language ? ' aria-current="true"' : '') . '>' . $label . '</a>';
        }
        echo '</nav></div>';
        echo '<div class="eyebrow"><span class="status-dot" aria-hidden="true"></span>' . $this->text('AKAUN RASMI · AKSES APLIKASI') . '</div><h1>' . $this->text('Login dengan OneID') . '</h1>';
        if ($message !== '') echo '<p class="notice" role="status"><span class="notice-icon" aria-hidden="true">!</span><span>' . $e($message) . '</span></p>';
        if (str_starts_with($stage,'RECOVERY_')) {
            $this->renderRecovery($stage,$form);
        } elseif ($stage === 'PASSWORD') {
            echo '<p>' . $this->text('Gunakan akaun staf atau pelajar OneID anda.') . '</p>' . $form . '<label for="identifier">' . $this->text('No. staf / no. matrik') . '</label><input id="identifier" name="identifier" autocomplete="username" maxlength="100" required autofocus><label for="password">' . $this->text('Kata laluan') . '</label><div class="password-field"><input id="password" type="password" name="password" autocomplete="current-password" maxlength="1024" required></div><button name="action" value="password" class="password-login-button"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4h5v16h-5M3 12h12m-5-5 5 5-5 5"/></svg>' . $this->text('Log Masuk') . '</button></form>';
            if ($this->recovery) echo $form . '<button class="recovery-link" name="action" value="recovery_begin">' . $this->text('Lupa kata laluan?') . '</button></form>';
            if ($this->myDigitalId) {
                echo '<div class="login-divider"><span>' . $this->text('ATAU TERUSKAN DENGAN') . '</span></div><section class="mydid-compact" aria-label="' . $this->text('Login MyDigital ID') . '"><p class="mydid-info"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path d="M12 10v7m0-11v2"/></svg><span>' . $this->text('Gunakan MyDigital ID untuk mengakses akaun OneID anda.') . '</span></p>' . $form;
                echo '<button class="mydid-button" name="action" value="mydigitalid" aria-label="' . $this->text('Login MyDigital ID') . '"><span class="mydid-brand"><img src="/img/mydigitalid_logo_colored.svg" alt="" width="128" height="34"></span><span class="mydid-copy"><strong>' . $this->text('Login MyDigital ID') . '</strong><small>' . $this->text('Pengesahan identiti yang selamat') . '</small></span><span class="mydid-arrow" aria-hidden="true">&rarr;</span></button></form></section>';

            }
        } elseif ($stage === 'ACCOUNT') {
            echo '<h2>' . $this->text('Pilih akaun untuk aplikasi') . '</h2><p>' . $this->text('Identiti anda telah disahkan. Pilih akaun OneID untuk meneruskan.') . '</p>' . $form;
            foreach ($factors as $choice) {
                if (!is_array($choice) || !in_array($choice['kind']??'', ['staff','student'],true)) continue;
                echo '<label class="account-choice"><input type="radio" name="choice" value="' . $e($choice['id']) . '" required><span>' . $this->text($choice['kind']==='staff' ? 'Staf' : 'Pelajar') . '</span></label>';
            }
            echo '<button name="action" value="mydigitalid_account">' . $this->text('Teruskan') . '</button></form>';
        } elseif ($stage === 'MFA') {
            echo '<h2>' . $this->text('Sahkan identiti anda') . '</h2><p class="intro">' . $this->text('Pilih salah satu kaedah di bawah untuk meneruskan log masuk.') . '</p>';
            foreach ($factors as $factor) {
                $isTotp = $factor === 'totp';
                echo '<section class="factor-card" aria-labelledby="title-' . $e($factor) . '"><div class="factor-heading"><span class="factor-icon" aria-hidden="true">' . ($isTotp ? '&#9638;' : '&#9993;') . '</span><div><h3 id="title-' . $e($factor) . '">' . ($isTotp ? $this->text('Aplikasi Authenticator') : $this->text('Pengesahan melalui emel')) . '</h3><p>' . ($isTotp ? $this->text('Gunakan pendaftaran Authenticator akaun pengguna OneID, bukan akaun admin.') : $this->text('Terima kod pengesahan melalui emel akaun OneID anda.')) . '</p></div></div>';
                if (!$isTotp) echo $form . '<button class="secondary" name="action" value="send_email">' . $this->text('Hantar kod ke emel') . '</button></form>';
                echo $form . '<input type="hidden" name="factor" value="' . $e($factor) . '"><label for="code-' . $e($factor) . '">' . ($isTotp ? $this->text('Kod Authenticator') : $this->text('Kod daripada emel')) . '</label><input class="otp-input" id="code-' . $e($factor) . '" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" aria-describedby="hint-' . $e($factor) . '" required><p class="field-hint" id="hint-' . $e($factor) . '">' . $this->text('Masukkan kod 6 digit yang masih sah.') . '</p><button name="action" value="verify">' . ($isTotp ? $this->text('Sahkan kod Authenticator') : $this->text('Sahkan kod emel')) . '</button></form></section>';
            }
        } elseif ($stage === 'CHANGE') {
            echo '<h2>' . $this->text('Tukar kata laluan') . '</h2><p>' . $this->text('Gunakan sekurang-kurangnya 12 aksara, huruf besar, huruf kecil, nombor dan simbol.') . '</p>' . $form . '<label for="new">' . $this->text('Kata laluan baharu') . '</label><input id="new" name="new_password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required><label for="confirm">' . $this->text('Sahkan kata laluan baharu') . '</label><input id="confirm" name="confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="72" required><button name="action" value="change">' . $this->text('Simpan dan teruskan') . '</button></form>';
        } else echo '<p>' . $this->text('Sila kembali ke aplikasi dan mulakan log masuk semula.') . '</p>';
        if ($stage !== 'ERROR' && !str_starts_with($stage,'RECOVERY_')) echo $form . '<button class="cancel" name="action" value="cancel">' . $this->text('Batal log masuk') . '</button></form>';
        echo '</div><footer class="application-footer"><p>' . $e(\oneid_application_footer()) . '</p></footer></main><span id="login-progress" class="visually-hidden" role="status" aria-live="polite"></span><script nonce="' . $uiNonce . '">' . file_get_contents(dirname(__DIR__, 3) . '/mobile-public/login-ui.js') . '</script></body></html>';
    }
    private function renderRecovery(string $stage,string $form): void
    {
        echo '<h2>'.$this->text('Tetapkan semula kata laluan').'</h2>';
        if($stage==='RECOVERY_IDENTITY') {
            echo '<p>'.$this->text('Masukkan maklumat pengenalan akaun OneID anda.').'</p>'.$form.'<label for="identity">'.$this->text('No. kad pengenalan / pasport').'</label><input id="identity" name="identity" maxlength="100" autocomplete="off" required><button name="action" value="recovery_request">'.$this->text('Hantar kod ke emel').'</button></form>';
        } elseif($stage==='RECOVERY_OTP') {
            echo '<p>'.$this->text('Semak emel berdaftar anda. Maksimum 5 cubaan kod.').'</p>'.$form.'<label for="recovery-code">'.$this->text('Kod daripada emel').'</label><input class="otp-input" id="recovery-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required><button name="action" value="recovery_verify">'.$this->text('Sahkan kod emel').'</button></form>';
        } elseif($stage==='RECOVERY_NEW') {
            echo '<p>'.$this->text('Gunakan sekurang-kurangnya 12 aksara, huruf besar, huruf kecil, nombor dan simbol.').'</p>'.$form.'<label for="password">'.$this->text('Kata laluan baharu').'</label><div class="password-field"><input id="password" type="password" name="new_password" autocomplete="new-password" minlength="12" maxlength="72" required></div><label for="confirmation">'.$this->text('Sahkan kata laluan baharu').'</label><input id="confirmation" type="password" name="confirmation" autocomplete="new-password" minlength="12" maxlength="72" required><button name="action" value="recovery_reset">'.$this->text('Simpan kata laluan').'</button></form>';
        } elseif($stage==='RECOVERY_DONE') {
            echo '<p class="recovery-success">'.$this->text('Kata laluan berjaya ditetapkan semula.').'</p>';
        }
        if(!in_array($stage,['RECOVERY_DONE','RECOVERY_ERROR'],true)) echo $form.'<button class="cancel" name="action" value="recovery_cancel">'.$this->text('Kembali ke login').'</button></form>';
        echo '<p class="field-hint recovery-return">'.$this->text(in_array($stage,['RECOVERY_DONE','RECOVERY_ERROR'],true) ? 'Tutup halaman ini untuk kembali ke aplikasi dan mulakan login baharu.' : 'Anda boleh membatalkan pemulihan dan kembali ke login selagi sesi masih sah.').'</p>';
    }

}
