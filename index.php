<?php 
require_once __DIR__ . '/app/Auth/MyDigitalId/MyDigitalIdRejectedLogoutState.php';
require_once __DIR__ . '/lib/session_security.php';
oneid_start_secure_session();
require_once __DIR__ . '/lib/request_security.php';
require_once __DIR__ . '/lib/SSO_IDP_INC.php';
require_once __DIR__ . '/lib/shared_faq.php';
require_once __DIR__ . '/lib/environment_banner.php';
require_once __DIR__ . '/app/LoginBanner/LoginBannerPersistenceException.php';
require_once __DIR__ . '/app/LoginBanner/LoginBannerPersistenceInterface.php';
require_once __DIR__ . '/app/LoginBanner/PdoLoginBannerPersistence.php';
require_once __DIR__ . '/app/LoginBanner/LoginBannerPublicResolver.php';
$requestedLocale = $_GET['locale'] ?? null;
if ($requestedLocale !== null) {
  if (oneid_set_session_locale((string) $requestedLocale)) {
    oneid_set_guest_locale_cookie((string) $requestedLocale);
    if (($_SESSION['login_status'] ?? '') === 'true') {
      oneid_promote_authenticated_locale((string) ($_SESSION['login_user'] ?? ''));
    }
  }
  header('Location: ' . APP_URL . '/', true, 303);
  exit;
}
$myDigitalIdEnabled = filter_var(
  oneid_config('ONEID_MYDID_ENABLED', 'false'),
  FILTER_VALIDATE_BOOLEAN
);
$loginFlashCode = is_string($_SESSION['oneid_login_flash'] ?? null)
  ? $_SESSION['oneid_login_flash']
  : '';
unset($_SESSION['oneid_login_flash']);
$myDigitalIdCanSwitchAccount = $loginFlashCode === 'mydigitalid_unavailable'
  && \OneId\App\Auth\MyDigitalId\MyDigitalIdRejectedLogoutState::isAvailable(
    $_SESSION,
    time()
  );
$loginFlashKey = match ($loginFlashCode) {
  'mydigitalid_invalid' => 'login.mydigitalid.invalid',
  'mydigitalid_unavailable' => 'login.mydigitalid.unavailable',
  'mydigitalid_temporary' => 'login.mydigitalid.temporary',
  default => null,
};
$loginBanners = [
  ['src' => 'assetsM/images/banner6.png', 'alt' => 'OneID@UPNM', 'width' => 3780, 'height' => 1890],
  ['src' => 'assetsM/images/banner7.png', 'alt' => 'OneID@UPNM', 'width' => 3780, 'height' => 1890],
];
if (filter_var(oneid_config('ONEID_LOGIN_BANNER_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN)) {
  try {
    $bannerPdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $bannerResolver = new \OneId\App\LoginBanner\LoginBannerPublicResolver(
      new \OneId\App\LoginBanner\PdoLoginBannerPersistence($bannerPdo),
      strtolower(trim((string) oneid_config('ONEID_ENVIRONMENT', ''))),
      oneid_public_path('login_banners'),
      'login_banners'
    );
    $dynamicBanners = $bannerResolver->resolve(oneid_current_locale(), gmdate('Y-m-d H:i:s'));
    if ($dynamicBanners !== []) {
      $loginBanners = $dynamicBanners;
    }
  } catch (\Throwable $bannerError) {
    $bannerCorrelation = bin2hex(random_bytes(8));
    error_log('LB6 public banner fallback correlation=' . $bannerCorrelation . ' exception=' . get_class($bannerError));
  }
}
?>
<!DOCTYPE html>
<html lang="<?=htmlspecialchars(oneid_current_locale(), ENT_QUOTES, 'UTF-8')?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?=htmlspecialchars(oneid_translate('login.page_title'), ENT_QUOTES, 'UTF-8')?></title>

  <!-- Favicon -->
  <link rel="shortcut icon" href="img/favicon.png" />

  <!-- Styles -->
  <link rel="stylesheet" href="assetsM/css/animate.css" />
  <link rel="stylesheet" href="assetsM/fonts/icomoon/icomoon.css" />
  <link rel="stylesheet" href="assetsM/css/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="assetsM/css/main.min.css" />
  <link rel="stylesheet" href="assetsM/css/custom.css" />
  <link rel="stylesheet" href="vendors/bower_components/jquery-toast-plugin/dist/jquery.toast.min.css" />
  <link rel="stylesheet" href="assetsM/css/sweetalert.css" />
  <link rel="stylesheet" href="dist/css/oneid-environment-banner.css?v=20260810-1" />
  <link href="https://fonts.googleapis.com/css2?family=Moon+Dance&display=swap" rel="stylesheet" />

</head>

<body class="login-container<?=oneid_environment_body_class()?>">
<?php oneid_render_environment_banner(); ?>
<div class="container py-5">
  <div class="row shadow-lg rounded-4 overflow-hidden" style="background: white;">
  
    <!-- LEFT SIDE: Login  order-2 order-md-1 -->
    <div class="col-md-4 bg-white p-4">
      <div class="mb-3 pb-2">
      </div>   

      <form id="loginform">
        <div style="text-align: center; margin-bottom: 12px;">          
          <img src="img/logo_oneid.png" alt="UPNM Logo" style="width: 80%; height: auto !important;" />
          <img src="img/logo_upnm_30.png" alt="UPNM 30 Tahun Logo" style="width: 40%; height: auto !important;" />
        </div>
        <div class="text-center mb-4">
          <h5 class="txt-heading text-center txt-dark mb-5 ">
            <!-- <u><font class="custom_link">S</font></u>atu <u><font class="custom_link">L</font></u>ogin, <u><font class="custom_link">S</font></u>emua <u><font class="custom_link">A</font></u>kses (SSO) -->
             <?=htmlspecialchars(oneid_translate('login.gateway'), ENT_QUOTES, 'UTF-8')?>
          </h5>
        </div>

        <div id="login_status" role="status" aria-live="polite"><?php if ($loginFlashKey !== null): ?>
          <div class="alert alert-warning alert-dismissable">
            <p><?=htmlspecialchars(oneid_translate($loginFlashKey), ENT_QUOTES, 'UTF-8')?></p>
            <?php if ($myDigitalIdCanSwitchAccount): ?>
              <div class="mydigitalid-rejection-actions">
                <button type="submit" class="btn btn-primary btn-sm" form="mydigitalid-switch-account-form">
                  <?=htmlspecialchars(oneid_translate('login.mydigitalid.switch_account'), ENT_QUOTES, 'UTF-8')?>
                </button>
                <button type="button" class="btn btn-link btn-sm" onclick="document.getElementById('username').focus()">
                  <?=htmlspecialchars(oneid_translate('login.mydigitalid.use_password'), ENT_QUOTES, 'UTF-8')?>
                </button>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?></div>

        <div class="login-form-block" style="display: flex; flex-direction: column; gap: 4px;margin-bottom: 20px;">
          <label for="username" class="login-form-label" style="margin-bottom: 4px; font-weight: 500; color: #2c2c2c;"> <?=htmlspecialchars(oneid_translate('login.user_id'), ENT_QUOTES, 'UTF-8')?></label>
          <input id="username" name="username" type="text" class="login-form-control custom_input login_placeholder" placeholder="<?=htmlspecialchars(oneid_translate('login.user_id_placeholder'), ENT_QUOTES, 'UTF-8')?>" maxlength="20" pattern="[A-Za-z0-9][A-Za-z0-9._@\-]*" autocomplete="username" />
        </div>

        <div class="login-form-block" style="display: flex; flex-direction: column; gap: 4px; margin-bottom: 20px;">
          <label for="password" class="login-form-label" style="margin-bottom: 4px; font-weight: 500; color: #2c2c2c;"> <?=htmlspecialchars(oneid_translate('login.password'), ENT_QUOTES, 'UTF-8')?></label>
          <input id="password" name="password" type="password" placeholder="<?=htmlspecialchars(oneid_translate('login.password_placeholder'), ENT_QUOTES, 'UTF-8')?>" class="login-form-control custom_input login_placeholder" autocomplete="current-password" />
		  <small><?=htmlspecialchars(oneid_translate('login.new_user_help'), ENT_QUOTES, 'UTF-8')?></small>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3" >
          <a style="cursor: pointer;" class="text-primary" onclick="open_forgot_password()"><?=htmlspecialchars(oneid_translate('login.forgot_password'), ENT_QUOTES, 'UTF-8')?></a>
          <button type="submit" class="btn btn-warning px-4">
            <i class="icon-login me-1 animate__animated animate__swing animate__infinite infinite"></i> <?=htmlspecialchars(oneid_translate('login.submit'), ENT_QUOTES, 'UTF-8')?>
          </button>
        </div>

        <?php if ($myDigitalIdEnabled): ?>
          <div class="mydigitalid-login-option" role="region" aria-labelledby="mydigitalid-divider">
            <div class="mydigitalid-divider">
              <span id="mydigitalid-divider"><?=htmlspecialchars(oneid_translate('login.mydigitalid.divider'), ENT_QUOTES, 'UTF-8')?></span>
            </div>
            <div class="mydigitalid-card">
              <a
                class="mydigitalid-button"
                href="auth/mydigitalid/login.php"
                aria-label="<?=htmlspecialchars(oneid_translate('login.mydigitalid.submit'), ENT_QUOTES, 'UTF-8')?>"
              >
                <span class="mydigitalid-brand">
                  <img src="img/mydigitalid_logo_colored.svg" alt="" width="128" height="34" loading="eager" />
                </span>
                <span class="mydigitalid-button-copy">
                  <strong><?=htmlspecialchars(oneid_translate('login.mydigitalid.submit'), ENT_QUOTES, 'UTF-8')?></strong>
                  <small><?=htmlspecialchars(oneid_translate('login.mydigitalid.security'), ENT_QUOTES, 'UTF-8')?></small>
                </span>
                <span class="mydigitalid-arrow" aria-hidden="true">&rarr;</span>
              </a>
            </div>
          </div>
        <?php else: ?>
          <div class="mydigitalid-preview" aria-label="<?=htmlspecialchars(oneid_translate('login.future_option'), ENT_QUOTES, 'UTF-8')?>">
            <span class="mydigitalid-preview-label"><?=htmlspecialchars(oneid_translate('login.future_option'), ENT_QUOTES, 'UTF-8')?></span>
            <img src="img/mydigitalid_logo_colored.svg" alt="MyDigital ID" width="158" height="42" loading="lazy" />
            <small><?=htmlspecialchars(oneid_translate('login.integration_disabled'), ENT_QUOTES, 'UTF-8')?></small>
          </div>
        <?php endif; ?>

        <input type="hidden" name="auth" value="auth">
      </form>
      <?php if ($myDigitalIdCanSwitchAccount): ?>
        <form
          id="mydigitalid-switch-account-form"
          action="auth/mydigitalid/switch-account.php"
          method="post"
          class="d-none"
        >
          <input type="hidden" name="_csrf_token" value="<?=htmlspecialchars(oneid_csrf_token(), ENT_QUOTES, 'UTF-8')?>" />
        </form>
      <?php endif; ?>
      
        <!-- New version row -->
        <div class="row mt-3 justify-content-center">
          <div class="col-auto" style="font-size: 11px; color: #b0b0b0;" align="center">
            <?php echo htmlspecialchars(oneid_application_footer(), ENT_QUOTES, 'UTF-8'); ?>
          </div>
        </div>
    </div>
      
    <!-- RIGHT SIDE: Slider + Contact order-1 order-md-2 -->
    <div class="col-md-8 bg-light px-4 py-4">
       <!-- MENU ATAS -->
      <div class="login-topbar mb-3 pb-2 border-bottom">
        <div class="login-topbar-links txt-heading">
          <a href="./public_docs/MANUAL_SALAM.pdf" class="menu_link" target="_blank" rel="noopener"><?=htmlspecialchars(oneid_translate('login.menu.manual'), ENT_QUOTES, 'UTF-8')?></a>
          <a href="#" class="menu_link" data-bs-toggle="modal" data-bs-target="#faqModal"><?=htmlspecialchars(oneid_translate('faq.link'), ENT_QUOTES, 'UTF-8')?></a>
          <a href="https://directory.upnm.edu.my/" target="_blank" rel="noopener" class="menu_link"><?=htmlspecialchars(oneid_translate('login.menu.directory'), ENT_QUOTES, 'UTF-8')?></a>
        </div>
        <nav class="login-locale-switcher" aria-label="<?=htmlspecialchars(oneid_translate('login.language_label'), ENT_QUOTES, 'UTF-8')?>">
          <i class="fa-solid fa-globe" aria-hidden="true"></i>
          <a class="<?=oneid_current_locale() === 'ms' ? 'is-active' : ''?>" href="?locale=ms" lang="ms" hreflang="ms" title="Bahasa Melayu" aria-label="Bahasa Melayu" aria-current="<?=oneid_current_locale() === 'ms' ? 'true' : 'false'?>">BM</a>
          <a class="<?=oneid_current_locale() === 'en' ? 'is-active' : ''?>" href="?locale=en" lang="en" hreflang="en" title="English" aria-label="English" aria-current="<?=oneid_current_locale() === 'en' ? 'true' : 'false'?>">EN</a>
        </nav>
      </div>
      <?php if (oneid_current_locale() === 'en'): ?>
        <div class="alert alert-info py-2 px-3 small" role="status">
          <?=htmlspecialchars(oneid_translate('login.manual_fallback_notice'), ENT_QUOTES, 'UTF-8')?>
        </div>
      <?php endif; ?>

      <!-- Slider -->
      <div id="carouselExample" class="carousel slide mb-4" data-bs-ride="carousel" data-bs-interval="6000">
        <div class="carousel-inner rounded">
          <?php foreach ($loginBanners as $bannerIndex => $banner): ?>
          <div class="carousel-item<?=$bannerIndex === 0 ? ' active' : ''?>">
            <img src="<?=htmlspecialchars((string)$banner['src'], ENT_QUOTES, 'UTF-8')?>" class="w-100 slider-img" alt="<?=htmlspecialchars((string)$banner['alt'], ENT_QUOTES, 'UTF-8')?>" width="<?=(int)$banner['width']?>" height="<?=(int)$banner['height']?>" loading="<?=$bannerIndex === 0 ? 'eager' : 'lazy'?>"<?=$bannerIndex === 0 ? ' fetchpriority="high"' : ''?>>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if (count($loginBanners) > 1): ?>
        <button class="carousel-control-prev" type="button" data-bs-target="#carouselExample" data-bs-slide="prev">
          <span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="visually-hidden"><?=htmlspecialchars(oneid_translate('login.banner_previous'), ENT_QUOTES, 'UTF-8')?></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#carouselExample" data-bs-slide="next">
          <span class="carousel-control-next-icon" aria-hidden="true"></span><span class="visually-hidden"><?=htmlspecialchars(oneid_translate('login.banner_next'), ENT_QUOTES, 'UTF-8')?></span>
        </button>
        <?php endif; ?>
      </div>

      <!-- Contact Info -->
      <div class="px-2">
        <h5 class="txt-heading"><i class="fa-solid fa-bullhorn fa-corporate-red "></i> <?=htmlspecialchars(oneid_translate('login.contact.title'), ENT_QUOTES, 'UTF-8')?></h5>
        <hr>
        <div class="row" style="margin-bottom:10px">
          <div class="col-md-12"><strong><?=htmlspecialchars(oneid_translate('login.contact.service'), ENT_QUOTES, 'UTF-8')?></strong><br><small></small></div>
        </div>
        <div class="row" style="margin-bottom:10px">
          <div class="col-md-3">
            <i class="fa-solid fa-phone fa-corporate-red"></i> 
            <small>03-9051 2700 </small>
          </div>        
        </div>        
        <div class="row" style="margin-bottom:10px">
          <div class="col-md-12">
            <i class="fa-solid fa-location-dot fa-corporate-red"></i> 
            <small><?=htmlspecialchars(oneid_translate('login.contact.department'), ENT_QUOTES, 'UTF-8')?></small>
            <br>
            <i class="fa-solid"></i> 
            <small><?=htmlspecialchars(oneid_translate('login.contact.address'), ENT_QUOTES, 'UTF-8')?></small>
          </div>
        </div>
        <div class="row" style="margin-bottom:10px">
          <div class="col-md-12"><i class="fa-solid fa-envelope fa-corporate-red "></i> 
          <small>ask.oneid@upnm.edu.my</small></div>
        </div>  
  
      </div>
    </div>


  </div>
</div>

  <!-- Other modals (forgot password, OTP, manual) remain the same -->
  <!-- Paste your existing modals here exactly as they are -->
  <!-- Example: -->

<!-- Modal 1 -->
                      <div class="modal fade" id="modal_forgot_password" tabindex="-1" aria-labelledby="modal_forgot_password_label"
                          aria-hidden="true">
                          <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                              <div class="modal-header">
                                <h5 class="modal-title" id="modal_forgot_password_label">
                                  <?=htmlspecialchars(oneid_translate('recovery.title'), ENT_QUOTES, 'UTF-8')?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                  aria-label="<?=htmlspecialchars(oneid_translate('common.close'), ENT_QUOTES, 'UTF-8')?>"></button>
                              </div>
                              <form id="form_forgot_password">
                              <div class="modal-body">
                                <div class="form-wrap">
                                             <div class="form-body overflow-hide">
                                                <div class="form-group" id="forgot_pwd_body">
                                                   <label class="form-label" for="forgot_password_id"><?=htmlspecialchars(oneid_translate('recovery.identity_label'), ENT_QUOTES, 'UTF-8')?></label>
                                                   <input type="text" class="form-control" id="forgot_password_id" name="forgot_password_id" placeholder="<?=htmlspecialchars(oneid_translate('recovery.identity_placeholder'), ENT_QUOTES, 'UTF-8')?>" autocomplete="username" required="">
                                                </div>
                                                <div id="forgot_pwd_loading_OTP">
                                                  <div class="spinners-container text-center">
                                                    <div class="spinner-grow text-green" role="status">
                                                      <span class="visually-hidden"><?=htmlspecialchars(oneid_translate('login.loading'), ENT_QUOTES, 'UTF-8')?></span>
                                                    </div>
                                                    <div class="spinner-grow text-red" role="status">
                                                      <span class="visually-hidden"><?=htmlspecialchars(oneid_translate('login.loading'), ENT_QUOTES, 'UTF-8')?></span>
                                                    </div>
                                                    <div class="spinner-grow text-blue" role="status">
                                                      <span class="visually-hidden"><?=htmlspecialchars(oneid_translate('login.loading'), ENT_QUOTES, 'UTF-8')?></span>
                                                    </div>
                                                    <div class="spinner-grow text-blue" role="status">
                                                      <span class="visually-hidden"><?=htmlspecialchars(oneid_translate('login.loading'), ENT_QUOTES, 'UTF-8')?></span>
                                                    </div>
                                                    <div class="spinner-grow text-red" role="status">
                                                      <span class="visually-hidden"><?=htmlspecialchars(oneid_translate('login.loading'), ENT_QUOTES, 'UTF-8')?></span>
                                                    </div>
                                                    <div class="spinner-grow text-green" role="status">
                                                      <span class="visually-hidden"><?=htmlspecialchars(oneid_translate('login.loading'), ENT_QUOTES, 'UTF-8')?></span>
                                                    </div>
                                                  </div>
                                                  <p class="text-center"><?=htmlspecialchars(oneid_translate('recovery.processing'), ENT_QUOTES, 'UTF-8')?></p>
                                                </div>
                                             </div>
                                          </div>
                              </div>
                              <div class="modal-footer" id="forgot_pwd_footer">
                                <button type="button" class="btn btn-dark" data-bs-dismiss="modal">
                                  <?=htmlspecialchars(oneid_translate('common.close'), ENT_QUOTES, 'UTF-8')?>
                                </button>
                                <button type="submit" class="btn btn-info">
                                  <?=htmlspecialchars(oneid_translate('recovery.reset'), ENT_QUOTES, 'UTF-8')?>
                                </button>
                              </div>
                            </form>
                            </div>
                          </div>
                        </div>



<!-- Modal OTP -->
                      <div class="modal fade" id="modal_OTP" tabindex="-1" aria-labelledby="modal_OTP_label"
                          aria-hidden="true">
                          <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                              <div class="modal-header">
                                <h5 class="modal-title" id="modal_OTP_label">
                                  <?=htmlspecialchars(oneid_translate('otp.title'), ENT_QUOTES, 'UTF-8')?>
                                </h5>
                              </div>
                              <form id="form_otp">
                              <div class="modal-body">
                                
                                <div class="row">
                           <div class="col-lg-12">

                              <div id="otp_modal_loading_OTP">
                                                  <div class="spinners-container text-center">
                                                    <div class="spinner-grow text-green" role="status">
                                                      <span class="visually-hidden"><?=htmlspecialchars(oneid_translate('login.loading'), ENT_QUOTES, 'UTF-8')?></span>
                                                    </div>
                                                    <div class="spinner-grow text-red" role="status">
                                                      <span class="visually-hidden"><?=htmlspecialchars(oneid_translate('login.loading'), ENT_QUOTES, 'UTF-8')?></span>
                                                    </div>
                                                    <div class="spinner-grow text-blue" role="status">
                                                      <span class="visually-hidden"><?=htmlspecialchars(oneid_translate('login.loading'), ENT_QUOTES, 'UTF-8')?></span>
                                                    </div>
                                                    <div class="spinner-grow text-blue" role="status">
                                                      <span class="visually-hidden"><?=htmlspecialchars(oneid_translate('login.loading'), ENT_QUOTES, 'UTF-8')?></span>
                                                    </div>
                                                    <div class="spinner-grow text-red" role="status">
                                                      <span class="visually-hidden"><?=htmlspecialchars(oneid_translate('login.loading'), ENT_QUOTES, 'UTF-8')?></span>
                                                    </div>
                                                    <div class="spinner-grow text-green" role="status">
                                                      <span class="visually-hidden"><?=htmlspecialchars(oneid_translate('login.loading'), ENT_QUOTES, 'UTF-8')?></span>
                                                    </div>
                                                  </div>
                                <p class="text-center"><?=htmlspecialchars(oneid_translate('recovery.processing'), ENT_QUOTES, 'UTF-8')?></p>
                                
                              </div>
                              <div  id="otp_modal_body">
                                 <div class="panel-wrapper">
                                    <div class="panel-body pa-0" style="display: flex; justify-content: center; align-items: center; flex-direction: column;">
                                       <div class="col-sm-12 col-xs-12" style="display: flex; flex-direction: column; align-items: center;">
                                          <div class="form-wrap">
                                             <div class="form-body overflow-hide" style="display: flex; flex-direction: column; align-items: center;">
                                                  <div class="form-group">
                                                    <div id="otp_inputs">
                                                      <?php for ($otpDigit = 1; $otpDigit <= 6; $otpDigit++): ?>
                                                      <input type="text" id="otp_digit_<?=$otpDigit?>" name="otp_digit_<?=$otpDigit?>" maxlength="1" class="otp-input form-control" inputmode="numeric" pattern="[0-9]" autocomplete="<?=$otpDigit === 1 ? 'one-time-code' : 'off'?>" aria-label="<?=htmlspecialchars(oneid_translate('otp.digit_label', ['digit' => $otpDigit]), ENT_QUOTES, 'UTF-8')?>" required="">
                                                      <?php endfor; ?>
                                                    </div>
                                                  </div>
                                                  <div class="form-group">
                                                     <h6><span id="countdown_OTP">60</span></h6>
                                                  </div>

                                             </div>
                                          </div>
                                       </div>
                                    </div>
                                 </div>
                              </div>
                           </div>
                        </div>

                              </div>
                              <div class="modal-footer" id="otp_modal_footer">
                                <button type="button" class="btn btn-dark" data-bs-dismiss="modal">
                                  <?=htmlspecialchars(oneid_translate('common.close'), ENT_QUOTES, 'UTF-8')?>
                                </button>
                                <button type="submit" class="btn btn-info" id="btn_otp_submit"><?=htmlspecialchars(oneid_translate('otp.submit'), ENT_QUOTES, 'UTF-8')?></button>
                                <button type="button" class="btn btn-info" id="btn_otp_request" onclick="resend_request_OTP();"><?=htmlspecialchars(oneid_translate('otp.request'), ENT_QUOTES, 'UTF-8')?></button>
                              </div>
                            </form>
                            </div>
                          </div>
                        </div>

<!-- Modal password baharu selepas OTP disahkan -->
<div class="modal fade" id="modal_reset_password" tabindex="-1" aria-labelledby="modal_reset_password_label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal_reset_password_label"><?=htmlspecialchars(oneid_translate('password.new_title'), ENT_QUOTES, 'UTF-8')?></h5>
      </div>
      <form id="form_reset_password">
        <div class="modal-body">
          <input type="text" id="reset_password_username" name="username" autocomplete="username" hidden>
          <div class="mb-3">
            <label class="form-label" for="reset_password_new"><?=htmlspecialchars(oneid_translate('password.new'), ENT_QUOTES, 'UTF-8')?></label>
            <input type="password" class="form-control" id="reset_password_new" name="reset_password_new" minlength="12" autocomplete="new-password" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="reset_password_confirm"><?=htmlspecialchars(oneid_translate('password.confirm'), ENT_QUOTES, 'UTF-8')?></label>
            <input type="password" class="form-control" id="reset_password_confirm" name="reset_password_confirm" minlength="12" autocomplete="new-password" required>
          </div>
          <small><?=htmlspecialchars(oneid_translate('password.rule'), ENT_QUOTES, 'UTF-8')?></small>
          <div id="reset_password_message" class="mt-3"></div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-info"><?=htmlspecialchars(oneid_translate('password.save'), ENT_QUOTES, 'UTF-8')?></button>
        </div>
      </form>
    </div>
  </div>
</div>

                        <div class="modal fade" id="faqModal" tabindex="-1" aria-labelledby="faqModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="faqModalLabel"><?=htmlspecialchars(oneid_translate('faq.title'), ENT_QUOTES, 'UTF-8')?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?=htmlspecialchars(oneid_translate('common.close'), ENT_QUOTES, 'UTF-8')?>"></button>
      </div>
      <div class="modal-body">

         <?=oneid_render_login_faq()?>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?=htmlspecialchars(oneid_translate('common.close'), ENT_QUOTES, 'UTF-8')?></button>
      </div>
    </div>
  </div>
</div>






  <!-- JavaScript dependencies -->
  <script src="assetsM/js/jquery.min.js"></script>
  <script src="assetsM/js/bootstrap.bundle.min.js"></script>
  <script src="vendors/bower_components/jquery-toast-plugin/dist/jquery.toast.min.js"></script>
  <script src="vendors/bower_components/sweetalert/dist/sweetalert.min.js"></script>
  <script src="assetsM/js/oneid-notifications.js?v=20260716-1"></script>


<script>
$.ajaxSetup({
  headers: {'X-CSRF-Token': <?php echo json_encode(oneid_csrf_token()); ?>}
});

function oneidPublicAlert(title, text, type) {
  swal({
    title: title,
    text: text,
    type: type || 'info',
    confirmButtonText: 'OK'
  });
}

function showLoginInlineError(message) {
  $('#login_status').html(
    '<div class="alert alert-danger alert-dismissable">' +
      '<i class="zmdi zmdi-block pr-15 pull-left"></i>' +
      '<p class="pull-left"></p><div class="clearfix"></div>' +
    '</div>'
  ).find('p').text(message || pilotI18n.loginFailed);
}

// ===== Client-side login limiter (cookie-based) =====
window.LoginLimiter = (function () {
  var THRESHOLD = 5;                        // 5 fails triggers lock
  var WINDOW_MS = 2 * 60 * 1000;           // 2 minutes
  var COOKIE = 'oneid_login_fails';         // JSON { "user": {count, until, blocked?} }
  var $btn = $('#loginform button[type="submit"]');
  var $msg = $('#login_status');

  function norm(u){ return (u||'').trim().toLowerCase(); }
  function getCookie(n){
    var m = document.cookie.match(new RegExp('(?:^|; )'+n.replace(/([.$?*|{}()[\]\\/+^])/g,'\\$1')+'=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : '';
  }
  function setCookie(n, v, age){
    document.cookie = n + '=' + encodeURIComponent(v) + '; max-age=' + age + '; path=/; samesite=lax';
  }
  function readState(){
    var raw = getCookie(COOKIE), st = {};
    try { st = raw ? JSON.parse(raw) : {}; } catch(e){ st = {}; }
    var now = Date.now();
    Object.keys(st).forEach(function(k){ if (!st[k] || now > (st[k].until||0)) delete st[k]; });
    return st;
  }
  function writeState(st){ setCookie(COOKIE, JSON.stringify(st), Math.ceil(WINDOW_MS/1000)); }

  function lockUI(remainSec){
    $btn.prop('disabled', true);
    $msg.html('<div class="alert alert-warning p-2 m-0"></div>').find('.alert').text(pilotI18n.loginLocked);
  }
  function clearUI(){ $btn.prop('disabled', false); $msg.empty(); }

  function check(username){
    var u = norm(username), st = readState(), rec = st[u], now = Date.now();
    if (rec && rec.count >= THRESHOLD && now < rec.until) {
      lockUI(Math.ceil((rec.until - now)/1000));
      return true;
    }
    clearUI(); return false;
  }

  function _notifyServerBlock(u, untilTs){
    // Adjust to your backend. Example posts to the same controller.
    $.post('./lib/q_func', { action_block_user: '', username: u, until: untilTs });
  }

  function onFailure(username){
    var u = norm(username), now = Date.now(), st = readState(), rec = st[u];
    if (rec && now < rec.until) rec.count += 1;
    else rec = st[u] = { count: 1, until: now + WINDOW_MS };

    var left = Math.max(THRESHOLD - rec.count, 0);
    if (left > 0) {
      $msg.html('<div class="alert alert-danger p-2 m-0"></div>').find('.alert').text(
        pilotI18n.loginRemainingAttempts.replace('{count}', left)
      );
    }
    if (rec.count >= THRESHOLD && now < rec.until && !rec.blocked) {
      rec.blocked = true; // de-dupe
      _notifyServerBlock(u, Math.floor(rec.until/1000));
    }
    writeState(st);
    check(u); // refresh UI
  }

  function onSuccess(username){
    var u = norm(username), st = readState();
    if (st[u]) { delete st[u]; writeState(st); }
    clearUI();
  }

  return { check, onFailure, onSuccess };
})();
</script>
 
 <script>
const pilotI18n = <?=json_encode([
  'loginRequiredUser' => oneid_translate('login.required_user'),
  'loginRequiredPassword' => oneid_translate('login.required_password'),
  'loginProcessing' => oneid_translate('login.processing'),
  'loginInvalid' => oneid_translate('login.invalid'),
  'loginSuccess' => oneid_translate('login.success'),
  'loginTimeout' => oneid_translate('login.timeout'),
  'loginServerError' => oneid_translate('login.server_error'),
  'loginFailed' => oneid_translate('login.failed'),
  'loginLocked' => oneid_translate('login.locked'),
  'loginRemainingAttempts' => oneid_translate('login.remaining_attempts', ['count' => '{count}']),
  'requestAccepted' => oneid_translate('recovery.request_accepted'),
  'requestRejected' => oneid_translate('recovery.request_rejected'),
  'requestInterrupted' => oneid_translate('recovery.request_interrupted'),
  'recoveryTimeout' => oneid_translate('recovery.timeout'),
  'recoveryServerError' => oneid_translate('recovery.server_error'),
  'otpExpires' => oneid_translate('otp.expires', ['seconds' => '{seconds}']),
  'otpExpired' => oneid_translate('otp.expired'),
  'otpIncomplete' => oneid_translate('otp.incomplete'),
  'otpReady' => oneid_translate('otp.ready'),
  'passwordResetFailed' => oneid_translate('password.reset_failed'),
  'reference' => oneid_translate('common.reference'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
   $(function(){
  LoginLimiter.check($('#username').val());
  $('#username').on('input', function(){ LoginLimiter.check(this.value); });
});
    //----Login
var fallback_sp_id=getUrlParameter('site_id');
var $loginform = $('#loginform');
$loginform.data('submitting', false);
$loginform.on('submit', function(ev){
    ev.preventDefault();

    var username = $('#username').val();
    var password = $('#password').val();

    // === NEW: abort if currently locked ===
    if (LoginLimiter.check(username) || $loginform.data('submitting')) return;
    if (!String(username || '').trim()) {
      showLoginInlineError(pilotI18n.loginRequiredUser);
      return;
    }
    if (!String(password || '')) {
      showLoginInlineError(pilotI18n.loginRequiredPassword);
      return;
    }

    var data = $('#loginform').serializeArray();
    data.push({name: "site_id", value: fallback_sp_id});
        $.ajax({
                type: 'POST',
                url: './lib/q_func',
                dataType: "json",
                timeout: 15000,
                data: data,
                beforeSend: function(){
                  $loginform.data('submitting', true).find(':submit').prop('disabled', true);
                  $('#login_status').html('<div class="alert alert-info alert-dismissable alert-style-1"></div>').find('.alert').text(pilotI18n.loginProcessing);
                },
                success: function (response) {
                    if (response['login_status'] == 0){
                      // === NEW: count a failure ===
                      LoginLimiter.onFailure(username);
                        showLoginInlineError(response['login_response_msg'] || pilotI18n.loginInvalid);

                    }else if(response['login_status']==2&&response['code']==='USER_MFA_REQUIRED'){
                        $('#password').val('');
                        window.location.href='page/user-mfa-challenge';
                    }else{
                        // === NEW: clear counter on success ===
                        LoginLimiter.onSuccess(username);
                        $('#login_status').html('<div class="alert alert-success alert-dismissable"><i class="zmdi zmdi-check pr-15 pull-left"></i><p class="pull-left"></p><div class="clearfix"></div></div>').find('p').text(pilotI18n.loginSuccess);
                        window.location.href = response['redirect_uri'];
                    }

            },
            error: function (xhr, error, thrown) {
                showLoginInlineError(error === 'timeout' ? pilotI18n.loginTimeout : pilotI18n.loginServerError);
            },
            complete: function(){
                $loginform.data('submitting', false).find(':submit').prop('disabled', false);
            }
        });
});

function open_forgot_password(){

  $('#forgot_pwd_body').show();
  $('#forgot_pwd_footer').show();
  $('#forgot_pwd_loading_OTP').hide();
  $('#modal_forgot_password').modal('show');
  // $('#modal_OTP').modal('show');
  $('#forgot_password_id').val('');
  OTP_stopCountdown();
  $('#otp_id').val('');


}


             
         var form_forgot_password = $('#form_forgot_password');
         var recoveryRequestInFlight = false;
         function recoveryReference(response){
           return response && response.correlation_id ? '\n' + pilotI18n.reference + ': ' + response.correlation_id : '';
         }
         function finishRecoveryLoading(){
           recoveryRequestInFlight = false;
           form_forgot_password.find(':submit').prop('disabled', false);
           $('#forgot_pwd_body, #forgot_pwd_footer, #otp_modal_body, #otp_modal_footer').show();
           $('#forgot_pwd_loading_OTP, #otp_modal_loading_OTP').hide();
         }
         form_forgot_password.on('submit', function(ev){
             ev.preventDefault();
             if (recoveryRequestInFlight) return;

             var data = $('#form_forgot_password').serializeArray();
             data.push({name: 'action_forgot_password', value: ''});
                 $.ajax({
                         type: 'POST',
                         url: './lib/q_func',
                         dataType: "json",
                         timeout: 20000,
                         data:data,
                         beforeSend: function(){
              recoveryRequestInFlight = true;
              form_forgot_password.find(':submit').prop('disabled', true);
              $('#forgot_pwd_body').hide();
              $('#forgot_pwd_footer').hide();
              $('#forgot_pwd_loading_OTP').show();
                         },
                         success: function (response) {
                             if (response['result'] == "true" && response['delivery_available'] !== false){
                              $('#modal_forgot_password').modal('hide');
                              setTimeout(function() {
                    $('#modal_OTP').modal('show');
                }, 500);
                    oneidPublicAlert(pilotI18n.requestAccepted, response['msg'] + recoveryReference(response), 'success');
                     OTP_startCountdown();
                     $("#btn_otp_request").hide();
                     $("#btn_otp_submit").show();
                }else{                 
                // alert();       
                     oneidPublicAlert(pilotI18n.requestRejected, response['msg'] + recoveryReference(response), 'error');
                             }
                
            $('#forgot_pwd_body').show();
            $('#forgot_pwd_footer').show();
            $('#forgot_pwd_loading_OTP').hide();
            $('#otp_modal_body').show();
            $('#otp_modal_footer').show();
            $('#otp_modal_loading_OTP').hide();
                     },
                     error: function (xhr, error, thrown) {
                       oneidPublicAlert(pilotI18n.requestInterrupted, error === 'timeout' ? pilotI18n.recoveryTimeout : pilotI18n.recoveryServerError, 'error');
                     },
                     complete: finishRecoveryLoading
                 });
         });

    // var timeLeft = 60; 
    // var countdownTimer = null;
    var countdownTimer = null;
    var otpEndTime = null;

    function OTP_updateCountdown() {
        var now = new Date().getTime();
        var remaining = Math.max(0, Math.floor((otpEndTime - now) / 1000));

        $('#countdown_OTP').text(pilotI18n.otpExpires.replace('{seconds}', remaining));

        if (remaining <= 0) {
            clearInterval(countdownTimer);
            countdownTimer = null;
            $('#countdown_OTP').text(pilotI18n.otpExpired);
            $("#btn_otp_request").show();
            $("#btn_otp_submit").hide();
            OTP_resetCountdown();
        }
        
    }

    function OTP_startCountdown() {
      if (countdownTimer !== null) return; // already running

        otpEndTime = new Date().getTime() + 300000; // 5 minutes from now

        countdownTimer = setInterval(function() {
            OTP_updateCountdown();
        }, 1000);

        // Initial display
        OTP_updateCountdown();
    }

    function OTP_stopCountdown() {
        clearInterval(countdownTimer);
        countdownTimer = null;
        $("#btn_otp_request").show();
        $("#btn_otp_submit").hide();
        OTP_resetCountdown();
    }

    function OTP_resetCountdown() {
        otpEndTime = null;
    }

    function resend_request_OTP(){
      if (recoveryRequestInFlight) return;
      $.ajax({
                         type: 'POST',
                         url: './lib/q_func',
                         dataType: "json",
                         timeout: 20000,
                         data:{action_forgot_password:"",forgot_password_id:$("#forgot_password_id").val()},
                         beforeSend: function(){
              recoveryRequestInFlight = true;
              $('#otp_modal_body').hide();
              $('#otp_modal_footer').hide();
              $('#otp_modal_loading_OTP').show();
                         },
                         success: function (response) {
                             if (response['result'] == "true" && response['delivery_available'] !== false){
                              $('#modal_forgot_password').modal('hide');
                              setTimeout(function() {
                    $('#modal_OTP').modal('show');
                }, 500);
                    oneidPublicAlert(pilotI18n.requestAccepted, response['msg'] + recoveryReference(response), 'success');
                     OTP_startCountdown();
                     $("#btn_otp_request").hide();
                     $("#btn_otp_submit").show();
                 
                }else{                 
                // alert();       
                     oneidPublicAlert(pilotI18n.requestRejected, response['msg'] + recoveryReference(response), 'error');
                             }

              $('#otp_modal_body').show();
              $('#otp_modal_footer').show();
              $('#otp_modal_loading_OTP').hide();
         
                     },
                     error: function (xhr, error, thrown) {
                       oneidPublicAlert(pilotI18n.requestInterrupted, error === 'timeout' ? pilotI18n.recoveryTimeout : pilotI18n.recoveryServerError, 'error');
                     },
                     complete: finishRecoveryLoading
                 });
    }

    // form_otp
    var form_otp = $('#form_otp');
         form_otp.on('submit', function(ev){
             ev.preventDefault();

             var otp = '';
        $('.otp-input').each(function() {
          otp += $(this).val();
        });

        if (otp.length < 6) {
          $('#otp_message').css('color', 'red').text(pilotI18n.otpIncomplete);
          return;
        } else {
          $('#otp_message').css('color', 'green').text(pilotI18n.otpReady);
        }

             var data = $('#form_otp').serializeArray();
             data.push({name: 'action_submit_OTP', value: ''});
             data.push({name: 'otp_id', value: otp});
             
                 $.ajax({
                         type: 'POST',
                         url: './lib/q_func',
                         dataType: "json",
                         data:data,
                         beforeSend: function(){
                         },
                         success: function (response) {
                             if (response['result'] == "true" && response['reset_required']){
                    $('#modal_OTP').modal('hide');
                    $('#reset_password_username').val($('#forgot_password_id').val());
                    $('#reset_password_new').val('');
                    $('#reset_password_confirm').val('');
                    $('#modal_reset_password').modal('show');
                }else{                 
                // alert();       
                     $.toast().reset('all');            
                     $.toast({
                      heading: '',
                      text: response['msg'],
                      position: 'bottom-center',
                      loaderBg:'#fec107',
                      icon: 'error',
                      hideAfter: 3500, 
                      stack: 6
                     });  
                             }
         
                     },
                     error: function (xhr, error, thrown) {
                     }
                 });
         });

    $('#form_reset_password').on('submit', function(ev){
      ev.preventDefault();
      var data = $(this).serializeArray();
      data.push({name: 'action_reset_password', value: ''});
      $.ajax({
        type: 'POST',
        url: './lib/q_func',
        dataType: 'json',
        data: data,
        success: function(response){
          if (response['result'] == 'true') {
            $('#reset_password_message').removeClass('text-danger').addClass('text-success').text(response['msg']);
            setTimeout(function(){ window.location.href = response['redirect_uri']; }, 1000);
          } else {
            $('#reset_password_message').removeClass('text-success').addClass('text-danger').text(response['msg']);
          }
        },
        error: function(xhr){
          var message = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : pilotI18n.passwordResetFailed;
          $('#reset_password_message').removeClass('text-success').addClass('text-danger').text(message);
        }
      });
    });


$('.otp-input').on('input', function() {
  var $this = $(this);
  var value = $this.val();

  // Only allow digits
  $this.val(value.replace(/[^0-9]/g, ''));

  if (value.length === 1) {
    $this.next('.otp-input').focus();
  }
});

$('.otp-input').on('keydown', function(e) {
  if (e.key === "Enter" && !(e.originalEvent && e.originalEvent.isComposing) && !e.repeat) {
    e.preventDefault();
    var submitButton = document.getElementById('btn_otp_submit');
    if (submitButton && !submitButton.disabled) {
      submitButton.click();
    }
    return;
  }
  if (e.key === "Backspace" && $(this).val() === '') {
    $(this).prev('.otp-input').focus();
  }
});

$('#otp_inputs').on('paste', function(e) {
    var pasteData = e.originalEvent.clipboardData.getData('text');
    var digits = pasteData.replace(/\D/g, '').substring(0, 6); // Only numbers, max 6

    $('.otp-input').each(function(index) {
        if (index < digits.length) {
            $(this).val(digits[index]);
        } else {
            $(this).val('');
        }
    });

    // Focus next empty input
    var nextInput = $('.otp-input').filter(function() {
        return $(this).val() === '';
    }).first();
    if (nextInput.length) {
        nextInput.focus();
    } else {
        $('.otp-input').last().focus();
    }
});


     function getUrlParameter(sParam) {
        var sPageURL = window.location.search.substring(1),
            sURLVariables = sPageURL.split('&'),
            sParameterName,
            i;

        for (i = 0; i < sURLVariables.length; i++) {
            sParameterName = sURLVariables[i].split('=');

            if (sParameterName[0] === sParam) {
                return sParameterName[1] === undefined ? true : decodeURIComponent(sParameterName[1]);
            }
        }
    };
	
	
	
	
	const MAXLEN = 20;
  const allowedRe = /^[A-Za-z0-9._@-]*$/;

  function showAlert(msg){ $('#usernameAlert').text(msg).show(); }
  function hideAlert(){ $('#usernameAlert').hide().text(''); }

  $('#username').on('input', function () {
    const $el = $(this);
    const before = $el.val();

    // strip disallowed chars
    let v = before.replace(/[^A-Za-z0-9._@-]/g, '');

    // cap length
    if (v.length > MAXLEN) v = v.slice(0, MAXLEN);

    // reflect any changes
    if (v !== before) {
      $el.val(v);
      if (!allowedRe.test(before)) {
        showAlert('Hanya huruf, nombor, titik, garis bawah, @ dan sempang dibenarkan.');
      } else if (before.length > MAXLEN) {
        showAlert('Maksimum 20 aksara.');
      }
    } else {
      hideAlert();
    }
  });

  // optional extra checks on blur
  $('#username').on('blur', function () {
    const v = this.value;
    if (!allowedRe.test(v) || v.length > MAXLEN) {
      showAlert('ID pengguna mesti maksimum 20 aksara.');
    } else {
      hideAlert();
    }
  });
    </script>

  <!-- Optional CSS to match Unsoed style -->
  <style>
      #otp_inputs {
  display: flex;
  gap: 5px;
}

.otp-input {
  width: 40px;
  height: 50px;
  text-align: center;
  font-size: 24px;
}
  body.login-container {
    background: linear-gradient(to bottom right, #f5f7fa, #c3cfe2);
  }

  .mydigitalid-preview {
    margin: 18px auto 4px;
    padding-top: 14px;
    border-top: 1px solid #e8e8e8;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    color: #777;
    text-align: center;
    pointer-events: none;
    user-select: none;
  }
  .mydigitalid-preview img {
    width: 142px;
    height: auto;
    max-width: 55%;
  }
  .mydigitalid-preview-label {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .04em;
    text-transform: uppercase;
  }
  .mydigitalid-preview small { font-size: 11px; }
  .mydigitalid-login-option {
    margin: 24px auto 6px;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 14px;
    color: #344054;
    text-align: center;
  }
  .mydigitalid-rejection-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    margin-top: 10px;
  }
  .mydigitalid-rejection-actions .btn {
    white-space: normal;
  }
  .mydigitalid-divider {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    color: #66778b;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .1em;
    text-transform: uppercase;
  }
  .mydigitalid-divider::before,
  .mydigitalid-divider::after {
    content: "";
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, transparent, #d8e1ea);
  }
  .mydigitalid-divider::after {
    background: linear-gradient(90deg, #d8e1ea, transparent);
  }
  .mydigitalid-card {
    position: relative;
    overflow: hidden;
    border: 0;
    border-radius: 14px;
    background: transparent;
    box-shadow:
      0 10px 24px rgba(18, 74, 112, .13),
      0 2px 4px rgba(16, 24, 40, .04);
  }
  .mydigitalid-button {
    position: relative;
    z-index: 2;
    display: grid;
    grid-template-columns: 108px minmax(0, 1fr) 34px;
    align-items: center;
    gap: 0;
    min-height: 76px;
    padding: 0 14px 0 0;
    overflow: hidden;
    border: 0;
    border-radius: 14px;
    background: linear-gradient(118deg, #075b9a 0%, #087fbd 58%, #09a3c6 100%);
    color: #fff;
    text-align: left;
    text-decoration: none;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .2);
    transition: box-shadow .2s ease, filter .2s ease, transform .2s ease;
  }
  .mydigitalid-button:hover,
  .mydigitalid-button:focus {
    color: #fff;
    text-decoration: none;
    filter: saturate(1.08) brightness(1.03);
    box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, .26),
      0 8px 18px rgba(5, 92, 148, .22);
    transform: translateY(-2px);
  }
  .mydigitalid-button:focus-visible {
    outline: 3px solid rgba(8, 121, 201, .3);
    outline-offset: 3px;
  }
  .mydigitalid-brand {
    display: flex;
    align-items: center;
    justify-content: center;
    align-self: stretch;
    min-height: 76px;
    padding: 12px 11px;
    border-right: 1px solid rgba(255, 255, 255, .65);
    border-radius: 12px 0 0 12px;
    background: linear-gradient(145deg, #fff 0%, #f5fbff 100%);
  }
  .mydigitalid-brand img {
    width: 92px;
    height: auto;
    max-width: 100%;
  }
  .mydigitalid-button-copy {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 12px 13px;
  }
  .mydigitalid-button-copy strong {
    color: #fff;
    font-size: 13px;
    line-height: 1.3;
    font-weight: 800;
    letter-spacing: -.01em;
  }
  .mydigitalid-button-copy small {
    position: relative;
    padding-left: 15px;
    color: rgba(255, 255, 255, .8);
    font-size: 10px;
    line-height: 1.35;
    font-weight: 500;
  }
  .mydigitalid-button-copy small::before {
    content: "";
    position: absolute;
    top: 2px;
    left: 0;
    width: 9px;
    height: 11px;
    border: 1.5px solid rgba(255, 255, 255, .8);
    border-radius: 2px 2px 4px 4px;
    box-shadow: inset 0 3px 0 rgba(255, 255, 255, .13);
  }
  .mydigitalid-arrow {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border: 1px solid rgba(255, 255, 255, .32);
    border-radius: 50%;
    background: rgba(255, 255, 255, .12);
    color: #fff;
    font-size: 17px;
    line-height: 1;
    transition: background .2s ease, transform .2s ease;
  }
  .mydigitalid-button:hover .mydigitalid-arrow {
    background: rgba(255, 255, 255, .2);
    transform: translateX(3px);
  }
  .form-control:focus {
    border-color: #ffc107;
    box-shadow: 0 0 0 0.2rem rgba(255,193,7,.25);
  }

  .shadow-lg {
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15) !important;
  }

  .input-group-text {
    background-color: #f8f9fa;
    border-left: 0;
  }

  .input-group input.form-control {
    border-right: 0;
  }

  .login-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    min-height: 51px;
  }

  .login-topbar-links {
    display: flex;
    align-items: center;
    gap: 28px;
    min-width: 0;
  }

  .login-locale-switcher {
    display: inline-flex;
    align-items: center;
    flex: 0 0 auto;
    gap: 2px;
    padding: 3px;
    border: 1px solid #d7e0e8;
    border-radius: 20px;
    background: rgba(255, 255, 255, .82);
    box-shadow: 0 2px 8px rgba(42, 63, 84, .06);
  }

  .login-locale-switcher > i {
    margin: 0 4px 0 6px;
    color: #6d7d8c;
    font-size: 11px;
  }

  .login-locale-switcher a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    height: 27px;
    padding: 0 8px;
    border-radius: 15px;
    color: #657483;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
    text-decoration: none;
    transition: color .18s ease, background .18s ease, box-shadow .18s ease;
  }

  .login-locale-switcher a:hover,
  .login-locale-switcher a:focus-visible {
    color: #007fb5;
    background: #eef8fc;
    outline: none;
  }

  .login-locale-switcher a.is-active {
    color: #fff;
    background: #078fca;
    box-shadow: 0 2px 6px rgba(7, 143, 202, .25);
  }

	  /* 🔸 Responsive tweaks */
	  @media (max-width: 767.98px) {
    .row > .col-md-6 {
      width: 100%;
      max-width: 100%;
      flex: 0 0 100%;
      padding: 2rem !important;
    }

    .row {
      flex-direction: column !important;
    }

    .rounded-4 {
      border-radius: 1rem !important;
    }

    .text-center.mb-4 h5 {
      font-size: 1.25rem;
    }

    .btn {
      width: 100%;
      display: block;
    }

    .d-flex.justify-content-between {
      flex-direction: column;
      gap: 10px;
      align-items: stretch;
    }

    .login-container .row {
      margin: 1rem;
    }

    .bg-warning.text-dark.p-2 {
      flex-direction: column;
      text-align: center;
    }

	    .bg-warning.text-dark img {
	      margin-bottom: 0.5rem;
	    }

      .login-topbar {
        align-items: flex-start;
        gap: 10px;
      }

      .login-topbar-links {
        flex-wrap: wrap;
        gap: 8px 16px;
      }

      .login-topbar-links .menu_link {
        white-space: nowrap;
      }

      .login-locale-switcher {
        margin-left: auto;
      }

      .mydigitalid-button {
        grid-template-columns: 98px minmax(0, 1fr) 32px;
        min-height: 72px;
        padding-right: 10px;
      }

      .mydigitalid-brand {
        min-height: 72px;
        padding: 11px 9px;
      }

      .mydigitalid-brand img {
        width: 84px;
      }

      .mydigitalid-button-copy {
        padding: 10px;
      }
	  }

#loginform input.login-form-control {
    background-color: #ffffff !important;   /* white background */
    border: 2px solid #f4b740 !important;   /* yellow border */
    color: #2c2c2c !important;              /* dark grey text */
    border-radius: 8px !important;
    padding: 10px !important;
    font-size: 16px !important;
    box-shadow: none !important;
    outline: none !important;
  }

  #loginform input.login-form-control::placeholder {
    color: #888 !important;
  }

  #loginform input.login-form-control:focus {
    border-color: #f4b740 !important;
    box-shadow: 0 0 0 2px rgba(244, 183, 64, 0.2) !important;
  }

   .topnav-link {
    position: relative;
    text-decoration: none;
    color: #00a0c6;
    font-weight: 500;
    padding-bottom: 4px;
    transition: all 0.2s ease;
  }

  .topnav-link:hover::after {
    content: '';
    position: absolute;
    bottom: -4px;
    left: 0;
    width: 100%;
    height: 3px;
    background-color: #00a0c6;
  }

.slider-img {
  max-height: 300px;
  object-fit: cover;
}

.accordion-button {
  background-color: #212529; /* Dark base */
  color: #000000;
}

.accordion-button:hover {
  background-color: #343a40; /* Slightly lighter dark */
  color: #fff;
}

.login_placeholder::placeholder {
  font-size: 13px;        /* Change this to your size */
  color: #999;            /* Optional */
}
</style>

</body>
</html>
