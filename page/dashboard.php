<?php
   require_once __DIR__ . '/../lib/session_security.php';
   oneid_start_secure_session();
   require_once __DIR__ . '/../lib/config.php';
   require_once __DIR__ . '/../lib/SSO_IDP_INC.php';
   require_once __DIR__ . '/../lib/request_security.php';
   require_once __DIR__ . '/../lib/shared_faq.php';
   require_once __DIR__ . '/../lib/user_session_presentation.php';
   require_once __DIR__ . '/../lib/environment_banner.php';
   require_once __DIR__ . '/../lib/display_settings.php';
   require_once __DIR__ . '/../app/Auth/UserMfa/UserLoginMfaPolicy.php';
   require_once __DIR__ . '/../app/Auth/UserMfa/UserMfaOperationalModeResolver.php';
   require_once __DIR__ . '/../app/Auth/UserMfa/PdoUserMfaPolicyReader.php';
   require_once __DIR__ . '/../app/Integration/EmadaniAsnbStatusClient.php';
   oneid_require_authenticated_page();
   oneid_require_active_sso_page($operation);
   if (isset($_GET['locale'])) {
      if (oneid_set_session_locale((string) $_GET['locale'])) {
         oneid_set_guest_locale_cookie((string) $_GET['locale']);
         oneid_promote_authenticated_locale((string) $_SESSION['login_user']);
      }
      header('Location: ' . APP_URL . '/page/dashboard', true, 303);
      exit;
   }
   $user_info = $operation->admin_search_user_account($_SESSION['login_user']);
   $showEmadaniAsnbReminder = false;
   if (!isset($_SESSION['emadani_asnb_checked'])
       && (int)($user_info['u_category'] ?? 0) === 10
       && filter_var(oneid_config('ONEID_EMADANI_ASNB_API_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN)
   ) {
      $_SESSION['emadani_asnb_checked'] = true;
      $matrik = strtoupper(trim((string)($user_info['data4'] ?? $_SESSION['login_user'] ?? '')));
      try {
         $emadaniClient = new \OneId\App\Integration\EmadaniAsnbStatusClient(
            trim((string)oneid_config('ONEID_EMADANI_ASNB_API_URL', '')),
            trim((string)oneid_config('ONEID_EMADANI_ASNB_API_CLIENT_ID', 'oneid')),
            oneid_secret('ONEID_EMADANI_ASNB_API_SECRET', false),
            max(1, min(10, (int)oneid_config('ONEID_EMADANI_ASNB_API_TIMEOUT_SECONDS', '5')))
         );
         $emadaniStatus = $emadaniClient->check($matrik);
         $showEmadaniAsnbReminder = is_array($emadaniStatus)
            && $emadaniStatus['applicable'] === true
            && $emadaniStatus['asnb_complete'] === false;
         if ($emadaniStatus === null) {
            error_log('e-Madani ASNB status unavailable user_hash=' . hash('sha256', (string)$_SESSION['login_user']));
         }
      } catch (Throwable) {
         error_log('e-Madani ASNB reminder failed user_hash=' . hash('sha256', (string)$_SESSION['login_user']));
      }
   }
   $userRoleTranslationKey = in_array((int) ($user_info['u_category'] ?? 0), [2, 3], true)
      ? 'dashboard.role.staff'
      : 'dashboard.role.student';
   $userMfaEnrollmentAvailable = false;
   try {
      if (in_array(
          (string) oneid_config('ONEID_USER_MFA_MODE', 'OFF'),
          ['ENROLLMENT', 'PILOT_ENFORCED', 'ENFORCED'],
          true
      )
          && filter_var(oneid_config('ONEID_USER_MFA_ACTIVATION_AUTHORIZED', false), FILTER_VALIDATE_BOOLEAN)
      ) {
         $userMfaPdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
         $userMfaPolicyReader = new \OneId\App\Auth\UserMfa\PdoUserMfaPolicyReader($userMfaPdo);
         $userMfaPolicyReader->assertRuntimeParity((string) oneid_config('ONEID_USER_MFA_MODE', 'OFF'));
         $userMfaEffectiveMode = $userMfaPolicyReader->policy()->mode;
         $userMfaUser = (string) $_SESSION['login_user'];
         $userMfaEnrollmentAvailable = $userMfaEffectiveMode !== 'OFF'
            && $userMfaPolicyReader->selfServiceEligible($userMfaUser)
            && ($userMfaEffectiveMode !== 'PILOT_ENFORCED'
               || $userMfaPolicyReader->pilotEligible($userMfaUser));
      }
   } catch (Throwable) {
      $userMfaEnrollmentAvailable = false;
   }
   $productTourEnabled = filter_var(
      oneid_config('ONEID_PRODUCT_TOUR_ENABLED', 'false'),
      FILTER_VALIDATE_BOOLEAN
   );
   $productTourId = 'dashboard';
   $productTourVersion = 2;
   $productTourServerStatus = null;
   $productTourStorageAvailable = false;
   if ($productTourEnabled) {
      try {
         $productTourStorageAvailable = $operation->supportsUserProductTourProgress();
         if ($productTourStorageAvailable) {
            $productTourServerStatus = $operation->getUserProductTourStatus(
               (string) $_SESSION['login_user'], $productTourId, $productTourVersion
            );
         }
      } catch (Throwable) {
         $productTourStorageAvailable = false;
      }
   }
   // echo "Xxxxx" . $_SESSION['user'];
    // echo json_encode($user_info);
   ?>
<!DOCTYPE html>
<html lang="<?=htmlspecialchars(oneid_current_locale(), ENT_QUOTES, 'UTF-8')?>">
   <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <title><?=htmlspecialchars(oneid_translate('dashboard.title'), ENT_QUOTES, 'UTF-8')?></title>
      <!-- Favicon -->
      <link rel="shortcut icon" href="favicon.ico">
      <link rel="icon" href="favicon.ico" type="image/x-icon">
      <!-- Morris Charts CSS -->
      <link href="../vendors/bower_components/morris.js/morris.css" rel="stylesheet" type="text/css"/>
      <!-- vector map CSS -->
      <link href="../vendors/vectormap/jquery-jvectormap-2.0.2.css" rel="stylesheet" type="text/css"/>
      <link href="../assetsM/css/sweetalert.css" rel="stylesheet" type="text/css">
      <link href="../dist/css/oneid-professional-alert.css?v=20260910-2" rel="stylesheet" type="text/css">
      <link href="../vendors/bower_components/jquery-toast-plugin/dist/jquery.toast.min.css" rel="stylesheet" type="text/css">
      <!-- Custom CSS -->
      <link href="../dist/css/style.css" rel="stylesheet" type="text/css">
      <link href="../dist/css/oneid-locale-switcher.css?v=20260725-3" rel="stylesheet" type="text/css">
      <link href="../dist/css/oneid-sidebar-menu.css?v=20260823-1" rel="stylesheet" type="text/css">
      <link href="../dist/css/oneid-user-faq.css?v=20261006-2" rel="stylesheet" type="text/css">
      <link href="../dist/css/oneid-password-modal.css?v=20260814-6" rel="stylesheet" type="text/css">
      <link href="../dist/css/oneid-asnb-reminder.css?v=20260903-4" rel="stylesheet" type="text/css">
      <link href="../dist/css/oneid-header-motion.css?v=20260823-3" rel="stylesheet" type="text/css">
      <link href="../dist/css/oneid-user-profile-role.css?v=20260824-4" rel="stylesheet" type="text/css">
      <link href="../dist/css/oneid-session-indicators.css?v=20260930-1" rel="stylesheet" type="text/css">
      <link href="../dist/css/oneid-user-health.css?v=20261008-4" rel="stylesheet" type="text/css">
      <link href="../dist/css/oneid-user-session.css?v=20260916-1" rel="stylesheet" type="text/css">
      <link href="../dist/css/oneid-environment-banner.css?v=20260810-1" rel="stylesheet" type="text/css">
      <link href="../dist/css/oneid-accessibility-baseline.css?v=20260915-1" rel="stylesheet" type="text/css">
      <link href="../dist/css/oneid-display-settings.css?v=20260915-4" rel="stylesheet" type="text/css">
      <?php if ($productTourEnabled): ?><link href="../dist/css/oneid-product-tour.css?v=20261006-1" rel="stylesheet" type="text/css"><?php endif; ?>
      <script src="../dist/js/oneid-display-settings.js?v=20260915-4"></script>

      <style>
      /* Keep navbar on top */
.navbar.banner-nav.navbar-fixed-top{
  z-index:9999 !important;
  padding:0 !important;
}

/* Kill legacy floats so we can center */
.navbar.banner-nav .mobile-only-brand,
.navbar.banner-nav .nav-header,
.navbar.banner-nav .logo-wrap{
  float:none !important;
  width:100% !important;
}

/* Center the banner horizontally */
.navbar.banner-nav .nav-wrap{
  display:flex !important;
  justify-content:center !important;
  align-items:center !important;
}

/* Center the link + image; stretch link to full width */
.navbar.banner-nav .logo-wrap a{
  display:block !important;
  width:100% !important;
  text-align:center !important;
}
/* Center the link + image; stretch link to full width */
.navbar.banner-nav .logo-wrap{
  display:block !important;
  width:100% !important;
  text-align:center !important;
  padding-top: 4px !important;
}
/* Show only one image and size it nicely */
.navbar.banner-nav .brand-text{ display:none !important; }
.navbar.banner-nav .brand-img{
  display:inline-block !important;
  max-height:72px !important;   /* tweak to your navbar height */
  height:auto !important;
  width:auto !important;
}
/* if your navbar is ~9999 */
.modal-backdrop{ z-index:10000 !important; }
.modal{ z-index:10001 !important; }
.admin-entry-loader{position:fixed;inset:0;z-index:12000;display:none;align-items:center;justify-content:center;background:rgba(11,24,43,.72);backdrop-filter:blur(7px);-webkit-backdrop-filter:blur(7px)}
.admin-entry-loader.is-visible{display:flex}.admin-entry-loader-card{width:min(420px,calc(100% - 40px));padding:34px 30px;text-align:center;background:#fff;border-radius:18px;box-shadow:0 24px 70px rgba(0,0,0,.3)}
.admin-entry-loader-shield{width:58px;height:66px;margin:0 auto 18px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:25px;background:linear-gradient(145deg,#174d91,#2f82d0);clip-path:polygon(50% 0,94% 17%,88% 72%,50% 100%,12% 72%,6% 17%)}
.admin-entry-loader-ring{width:42px;height:42px;margin:0 auto 18px;border:4px solid #dce8f5;border-top-color:#256bb2;border-radius:50%;animation:admin-entry-spin .75s linear infinite}.admin-entry-loader-title{margin:0 0 7px;color:#172b4d;font-size:18px;font-weight:700}.admin-entry-loader-text{margin:0;color:#64748b;font-size:14px}@keyframes admin-entry-spin{to{transform:rotate(360deg)}}


      .user-app-results { display:block; padding:12px 16px; color:#075b86; font-weight:600; }
      .user-app-content .user-app-result-category { display:block; color:#52677d; margin:3px 0; padding:0; }
      @media (max-width:767px) {
        .profile-box .profile-cover-pic { height:auto; min-height:0; aspect-ratio:2 / 1; background-size:100% 100%; }
        .profile-box .profile-info .profile-img-wrap { width:110px; height:110px; margin:-65px auto 0; padding:4px; border-width:4px; z-index:2; animation:none; }
        .profile-box .profile-info { padding:0 12px; margin-bottom:8px !important; }
        .profile-box .profile-info h6 { margin-top:6px !important; }
        .profile-box .profile-info > span { font-size:12px; line-height:1.5; }
        .oneid-user-sidebar-menu { margin-top:12px !important; }
        .oneid-user-sidebar-menu .oneid-sidebar-nav { display:block; }
        .oneid-user-sidebar-menu .oneid-sidebar-nav > li { width:100%; margin:0 !important; min-width:0; }
        .oneid-user-sidebar-menu .oneid-sidebar-nav > li > a { padding:8px; min-height:44px; font-size:12px; }
        .oneid-user-sidebar-menu .oneid-sidebar-nav > li > a span { overflow-wrap:anywhere; }
      }
   </style>
   </head>
   <body class="<?=trim(oneid_environment_body_class())?>">
      <?php oneid_render_environment_banner(); ?>
      <?php oneid_render_display_settings(); ?>
      <div id="adminEntryLoader" class="admin-entry-loader" role="status" aria-live="polite" aria-hidden="true"><div class="admin-entry-loader-card"><div class="admin-entry-loader-shield"><i class="fa fa-lock"></i></div><div class="admin-entry-loader-ring"></div><p class="admin-entry-loader-title"><?=htmlspecialchars(oneid_translate('dashboard.admin_check_title'), ENT_QUOTES, 'UTF-8')?></p><p class="admin-entry-loader-text"><?=htmlspecialchars(oneid_translate('dashboard.admin_check_text'), ENT_QUOTES, 'UTF-8')?></p></div></div>
      <!--Preloader-->
      <div class="preloader-it">
         <div class="la-anim-1"></div>
      </div>
      <!--/Preloader-->
      <div class="wrapper theme-2-active navbar-top-light horizontal-nav">
         <?php include __DIR__ . '/const/top.php'; ?>
         <!--  <?php //include 'const/left.php'; ?> -->
         <div id="modal_change_first_time_password" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" style="display: none;" data-backdrop="static" 
     data-keyboard="false">
                                 <div class="modal-dialog">
                                    <div class="modal-content">
                                       <div class="modal-body">
                                          <div class="alert alert-info alert-style-1">
                                             <i class="zmdi zmdi-info-outline"></i><?=htmlspecialchars(oneid_translate('dashboard.first_password_notice'), ENT_QUOTES, 'UTF-8')?>
                                          </div>
                                          <h5 class="modal-title"></h5>
                                       </div>
                                       <div class="modal-footer">
                                          <button type="button" class="btn btn-danger" onclick="open_change_password(1);">OK</button>
                                       </div>
                                    </div>
                                 </div>
                              </div>

         <!-- Modal: FAQ OneID@UPNM -->
<div id="modal_faq" class="modal fade oneid-faq-modal" tabindex="-1" role="dialog" aria-labelledby="faqModalLabel" aria-describedby="faqModalIntro">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content oneid-faq-dialog">
      <div class="modal-header oneid-faq-header">
        <button type="button" class="close oneid-faq-close" data-dismiss="modal" aria-label="<?=htmlspecialchars(oneid_translate('common.close'), ENT_QUOTES, 'UTF-8')?>">×</button>
        <span class="oneid-faq-header-icon"><i class="fa fa-question-circle" aria-hidden="true"></i></span>
        <div><span class="oneid-faq-eyebrow"><?=htmlspecialchars(oneid_translate('faq.eyebrow'), ENT_QUOTES, 'UTF-8')?></span><h5 class="modal-title" id="faqModalLabel"><?=htmlspecialchars(oneid_translate('faq.title'), ENT_QUOTES, 'UTF-8')?></h5><p id="faqModalIntro"><?=htmlspecialchars(oneid_translate('faq.intro'), ENT_QUOTES, 'UTF-8')?></p></div>
      </div>
      <div class="modal-body modal-body-scroll oneid-faq-body">
        <?=oneid_render_dashboard_faq()?>
      </div>
      <div class="modal-footer oneid-faq-footer">
        <button type="button" class="oneid-faq-dismiss" data-dismiss="modal"><i class="fa fa-check" aria-hidden="true"></i><?=htmlspecialchars(oneid_translate('common.close'), ENT_QUOTES, 'UTF-8')?></button>
      </div>
    </div>
  </div>
</div>


         <div id="modal_change_password" class="modal oneid-password-modal" tabindex="-1" role="dialog" aria-labelledby="aria_modal_change_password" aria-describedby="oneidPasswordIntro" aria-hidden="true" data-backdrop="static" data-keyboard="false">
            <div class="modal-dialog" role="document">
               <div class="modal-content oneid-password-dialog">
                  <div class="modal-header oneid-password-header">
                     <span class="oneid-password-header-icon"><i class="fa fa-key" aria-hidden="true"></i></span>
                     <div><span class="oneid-password-eyebrow"><?=htmlspecialchars(oneid_translate('dashboard.password.eyebrow'), ENT_QUOTES, 'UTF-8')?></span><h5 class="modal-title" id="aria_modal_change_password"><?=htmlspecialchars(oneid_translate('dashboard.password.title'), ENT_QUOTES, 'UTF-8')?></h5><p id="oneidPasswordIntro"><?=htmlspecialchars(oneid_translate('dashboard.password.intro'), ENT_QUOTES, 'UTF-8')?></p></div>
                  </div>
                  <form id="form_change_password">
                     <div class="modal-body oneid-password-body">
                        <input type="text" name="username" value="<?=htmlspecialchars((string) $_SESSION['login_user'], ENT_QUOTES, 'UTF-8')?>" autocomplete="username" hidden>
                        <div class="oneid-password-notice" id="initial_password_setup_notice" style="display:none;"><i class="fa fa-info-circle" aria-hidden="true"></i><span><?=htmlspecialchars(oneid_translate('dashboard.password.initial_notice'), ENT_QUOTES, 'UTF-8')?></span></div>
                        <section class="oneid-password-card oneid-password-card--current" id="current_password_group">
                           <div class="oneid-password-card-heading"><span>01</span><div><label for="change_password_current" id="default_pwd_text"><?=htmlspecialchars(oneid_translate('dashboard.password.current'), ENT_QUOTES, 'UTF-8')?></label></div></div>
                           <div class="form-group"><div class="oneid-password-input"><i class="fa fa-lock" aria-hidden="true"></i><input type="password" class="form-control" id="change_password_current" name="change_password_current" autocomplete="current-password" required></div></div>
                           <?php if (($_SESSION['auth_method'] ?? '') === 'mydigitalid'): ?><button type="button" class="oneid-password-recovery" id="btn_mydid_password_recovery"><i class="fa fa-envelope-o" aria-hidden="true"></i><?=htmlspecialchars(oneid_translate('dashboard.password.forgot_mydid'),ENT_QUOTES,'UTF-8')?></button><?php endif; ?>
                        </section>
                        <div class="oneid-password-notice" id="mydid_password_recovery_notice" style="display:none;"><i class="fa fa-shield" aria-hidden="true"></i><span><?=htmlspecialchars(oneid_translate('dashboard.password.recovery_notice'),ENT_QUOTES,'UTF-8')?></span></div>
                        <section class="oneid-password-card" id="mydid_password_otp_group" style="display:none;">
                           <div class="oneid-password-card-heading"><span><i class="fa fa-envelope-o"></i></span><div><label for="mydid_password_otp"><?=htmlspecialchars(oneid_translate('dashboard.password.otp_label'),ENT_QUOTES,'UTF-8')?></label><p><?=htmlspecialchars(oneid_translate('dashboard.password.otp_help'),ENT_QUOTES,'UTF-8')?></p></div></div>
                           <div class="form-group"><input type="text" class="form-control oneid-password-otp" id="mydid_password_otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}"></div>
                        </section>
                        <section class="oneid-password-card oneid-password-card--new">
                           <div class="oneid-password-card-heading"><span>02</span><div><label for="change_password_new"><?=htmlspecialchars(oneid_translate('dashboard.password.new'), ENT_QUOTES, 'UTF-8')?></label><p><?=htmlspecialchars(oneid_translate('dashboard.password.security_note'), ENT_QUOTES, 'UTF-8')?></p></div></div>
                           <div class="form-group"><div class="oneid-password-input"><i class="fa fa-lock" aria-hidden="true"></i><input type="password" class="form-control" id="change_password_new" name="change_password_new" autocomplete="new-password" minlength="12" required></div></div>
                        </section>
                        <section class="oneid-password-card oneid-password-card--confirm">
                           <div class="oneid-password-card-heading"><span>03</span><div><label for="change_password_new_reconfirm"><?=htmlspecialchars(oneid_translate('dashboard.password.confirm'), ENT_QUOTES, 'UTF-8')?></label></div></div>
                           <div class="form-group"><div class="oneid-password-input"><i class="fa fa-check-circle" aria-hidden="true"></i><input type="password" class="form-control" id="change_password_new_reconfirm" name="change_password_new_reconfirm" autocomplete="new-password" minlength="12" aria-describedby="password_confirmation_status" required></div></div>
                        </section>
                        <section class="oneid-password-requirements"><h6><i class="fa fa-shield" aria-hidden="true"></i><?=htmlspecialchars(oneid_translate('dashboard.password.requirements_title'), ENT_QUOTES, 'UTF-8')?></h6><ul id="password-requirements"><li id="p_length">❌ <?=htmlspecialchars(oneid_translate('dashboard.password.length'), ENT_QUOTES, 'UTF-8')?></li><li id="p_lowercase">❌ <?=htmlspecialchars(oneid_translate('dashboard.password.lowercase'), ENT_QUOTES, 'UTF-8')?></li><li id="p_uppercase">❌ <?=htmlspecialchars(oneid_translate('dashboard.password.uppercase'), ENT_QUOTES, 'UTF-8')?></li><li id="p_number">❌ <?=htmlspecialchars(oneid_translate('dashboard.password.number'), ENT_QUOTES, 'UTF-8')?></li><li id="p_special">❌ <?=htmlspecialchars(oneid_translate('dashboard.password.special'), ENT_QUOTES, 'UTF-8')?></li><li class="oneid-password-match" id="password_confirmation_status" role="status" aria-live="polite">❌ <?=htmlspecialchars(oneid_translate('dashboard.password.match'), ENT_QUOTES, 'UTF-8')?></li></ul></section>
                        <div id="password_change_feedback" class="alert oneid-password-feedback" role="alert" aria-live="assertive" style="display:none;user-select:text;-webkit-user-select:text;"><p id="password_change_feedback_text" style="white-space:pre-wrap;margin-bottom:8px;"></p><button type="button" class="btn btn-xs btn-default" id="password_change_copy_button" onclick="copyPasswordChangeFeedback();"><i class="fa fa-copy" aria-hidden="true"></i> <?=htmlspecialchars(oneid_translate('dashboard.password.copy'), ENT_QUOTES, 'UTF-8')?></button></div>
                     </div>
                     <div class="modal-footer oneid-password-footer">
                        <button type="button" class="oneid-password-logout" id="chge_pwd_logout" onclick="logout();"><i class="fa fa-sign-out" aria-hidden="true"></i><?=htmlspecialchars(oneid_translate('dashboard.menu.logout'), ENT_QUOTES, 'UTF-8')?></button>
                        <div><button id="btn_close_changePW" type="button" class="oneid-password-close" data-dismiss="modal"><?=htmlspecialchars(oneid_translate('common.close'), ENT_QUOTES, 'UTF-8')?></button><button type="submit" class="oneid-password-submit" id="btn_change_password_submit"><i class="fa fa-check" aria-hidden="true"></i><span id="change_password_submit_label"><?=htmlspecialchars(oneid_translate('dashboard.password.change'), ENT_QUOTES, 'UTF-8')?></span></button></div>
                     </div>
                  </form>
               </div>
            </div>
         </div>
         <!-- Main Content -->
         <div class="page-wrapper">
            <div class="container">
               <!-- Row -->
               <div class="row">
                  <div class="col-sm-4">
                     <div class="row">
                        <div class="col-sm-12">
                           <div class="panel panel-default card-view  pa-0">
                              <div class="panel-wrapper collapse in">
                                 <div class="panel-body  pa-0">
									<div class="profile-box">
                                       <div class="profile-cover-pic">
                                          <svg class="oneid-user-cover-fx" viewBox="0 0 500 250" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
                                             <defs>
                                                <linearGradient id="oneid-user-trace-gradient" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#e8ad25"/><stop offset=".5" stop-color="#fff4cd"/><stop offset="1" stop-color="#7f5aa3"/></linearGradient>
                                                <filter id="oneid-user-key-glow" x="-250%" y="-250%" width="500%" height="500%"><feGaussianBlur stdDeviation="3.2" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
                                                <filter id="oneid-user-letter-glow" x="-80%" y="-80%" width="260%" height="260%"><feGaussianBlur stdDeviation="8"/></filter>
                                             </defs>
                                             <g>
                                                <path class="oneid-circuit-trace" d="M337 0V42L354 59V103L372 121V164"/>
                                                <path class="oneid-circuit-trace is-reverse" d="M350 0V38L367 55V96L386 115V159"/>
                                                <path class="oneid-circuit-trace" d="M366 0V32L383 49V91L402 110V153"/>
                                                <path class="oneid-circuit-trace is-reverse" d="M386 0V37L403 54V94L423 114V158"/>
                                                <path class="oneid-circuit-trace" d="M410 0V48L429 67V109L448 128V173"/>
                                                <path class="oneid-circuit-trace is-reverse" d="M438 0V57L457 76V119L477 139V182"/>
                                                <path class="oneid-circuit-trace" d="M277 177L296 196V250"/>
                                                <path class="oneid-circuit-trace is-reverse" d="M284 177L305 198V250"/>
                                                <path class="oneid-circuit-trace" d="M292 180L314 202V250"/>
                                                <path class="oneid-circuit-trace is-reverse" d="M300 184L321 205H376L395 186H500"/>
                                                <path class="oneid-circuit-trace" d="M307 190L329 212H397L419 190H500"/>
                                                <path class="oneid-circuit-trace is-reverse" d="M315 199L337 221H417L440 198H500"/>
                                                <path class="oneid-circuit-trace" d="M0 48H61L82 69H148L169 90"/>
                                                <path class="oneid-circuit-trace is-reverse" d="M0 64H52L75 87H138L160 109"/>
                                                <path class="oneid-circuit-trace" d="M0 204H64L87 181H157L178 160"/>
                                                <path class="oneid-circuit-trace is-reverse" d="M0 220H74L96 198H169L190 177"/>
                                             </g>
                                             <g filter="url(#oneid-user-letter-glow)" fill="#fff4cd">
                                                <ellipse cx="68" cy="126" rx="32" ry="29" opacity="0"><animate attributeName="opacity" values="0;0;.32;0;0" keyTimes="0;.42;.445;.47;1" dur="16s" repeatCount="indefinite"/></ellipse>
                                                <ellipse cx="126" cy="126" rx="32" ry="29" opacity="0"><animate attributeName="opacity" values="0;0;.32;0;0" keyTimes="0;.45;.475;.5;1" dur="16s" repeatCount="indefinite"/></ellipse>
                                                <ellipse cx="188" cy="126" rx="32" ry="29" opacity="0"><animate attributeName="opacity" values="0;0;.32;0;0" keyTimes="0;.48;.505;.53;1" dur="16s" repeatCount="indefinite"/></ellipse>
                                                <ellipse cx="276" cy="126" rx="20" ry="35" opacity="0"><animate attributeName="opacity" values="0;0;.3;0;0" keyTimes="0;.51;.535;.56;1" dur="16s" repeatCount="indefinite"/></ellipse>
                                                <ellipse cx="340" cy="126" rx="42" ry="31" opacity="0"><animate attributeName="opacity" values="0;0;.32;0;0" keyTimes="0;.54;.565;.59;1" dur="16s" repeatCount="indefinite"/></ellipse>
                                             </g>
                                             <line class="oneid-key-track" x1="276" y1="63" x2="276" y2="213"/>
                                             <g transform="translate(276 63)" filter="url(#oneid-user-key-glow)">
                                                <circle r="8" fill="#7f5aa3" opacity=".15"><animate attributeName="r" values="8;15;8;7;7;8" keyTimes="0;.12;.35;.68;.88;1" dur="16s" repeatCount="indefinite"/><animate attributeName="opacity" values=".12;.65;.22;.18;.2;0" keyTimes="0;.12;.35;.68;.88;1" dur="16s" repeatCount="indefinite"/></circle>
                                                <circle r="4" fill="#fff4cd" stroke="#7f5aa3" stroke-width="1.5"><animate attributeName="opacity" values=".25;1;1;.75;.8;0" keyTimes="0;.12;.35;.68;.88;1" dur="16s" repeatCount="indefinite"/></circle>
                                                <animateTransform attributeName="transform" type="translate" values="276 63;276 63;276 213;276 213;276 63;276 63" keyTimes="0;.12;.35;.68;.88;1" dur="16s" repeatCount="indefinite" calcMode="linear"/>
                                             </g>
                                          </svg>
                                          <span class="oneid-user-role-badge"><i class="fa fa-user" aria-hidden="true"></i><?=htmlspecialchars(oneid_translate($userRoleTranslationKey), ENT_QUOTES, 'UTF-8')?></span>
                                          <!-- <div class="profile-image-overlay"></div> -->
                                          </div>
                                       <div class="profile-info text-center mb-15">
                                          <div class="profile-img-wrap">
                                             <img id="user_photos" class="inline-block" src="profile-photo.php" alt="<?=htmlspecialchars(oneid_translate('dashboard.profile_photo'), ENT_QUOTES, 'UTF-8')?>"/>
                                             <span class="oneid-user-profile-online" aria-hidden="true"></span>
                                             </div>	
                                          <h6 class="block mt-10 weight-500 capitalize-font txt-dark"><?php echo $_SESSION['user']; ?> (<?= (trim($user_info['data3']) == "") ? $user_info['data4'] : $user_info['data3']; ?>)</h6>
                                          <span class="block capitalize-font"><?php echo $user_info['data6']; ?></span>
                                          <span class="block capitalize-font"><?php echo $user_info['data7']; ?></span>
                                          <span class="time block truncate txt-grey"></span>
                                          <nav class="profile-locale-switcher" aria-label="<?=htmlspecialchars(oneid_translate('dashboard.language'), ENT_QUOTES, 'UTF-8')?>">
                                             <i class="fa fa-globe" aria-hidden="true"></i>
                                             <a class="<?=oneid_current_locale() === 'ms' ? 'is-active' : ''?>" href="?locale=ms" lang="ms" hreflang="ms" title="Bahasa Melayu" aria-label="Bahasa Melayu" aria-current="<?=oneid_current_locale() === 'ms' ? 'true' : 'false'?>">BM</a>
                                             <a class="<?=oneid_current_locale() === 'en' ? 'is-active' : ''?>" href="?locale=en" lang="en" hreflang="en" title="English" aria-label="English" aria-current="<?=oneid_current_locale() === 'en' ? 'true' : 'false'?>">EN</a>
                                          </nav>
                                       </div>
                                    </div>

                                    <button type="button" class="oneid-mobile-menu-toggle" aria-expanded="false" aria-controls="myTabs_8"><i class="fa fa-bars" aria-hidden="true"></i><span>Menu</span><i class="fa fa-chevron-down" aria-hidden="true"></i></button>
                                    <div class="pills-struct vertical-pills mt-40 oneid-user-sidebar-menu">
                                      <!-- Vertical nav -->
                                      <ul role="tablist" class="nav nav-pills ver-nav-pills oneid-sidebar-nav" id="myTabs_8">
                                        <?php if($_SESSION['login_user_type'] == 1){ ?>
                                          <li role="presentation" class="pill-yellow" style="cursor: pointer !important;" >
                                          <a id="administrator_entry" href="admin-step-up?purpose=ADMIN_ACCESS">
                                            <i class="fa fa-shield oneid-sidebar-icon" aria-hidden="true"></i><span>Administrator</span><i class="fa fa-chevron-right oneid-sidebar-trailing" aria-hidden="true"></i>
                                          </a>
                                        </li>
                                       <?php } ?>
                                        <li role="presentation" class="oneid-sidebar-email">
                                          <a href="https://outlook.cloud.microsoft/mail/" target="_blank" rel="noopener noreferrer">
                                            <i class="fa fa-envelope oneid-sidebar-icon" aria-hidden="true"></i><span><?=htmlspecialchars(oneid_translate('dashboard.menu.email'), ENT_QUOTES, 'UTF-8')?></span><i class="fa fa-external-link oneid-sidebar-trailing" aria-hidden="true"></i>
                                          </a>
                                        </li>
                                        <li class="active" role="presentation">
                                          <a aria-expanded="true" data-toggle="tab" role="tab" id="follo_tab_8" href="#follo_8">
                                            <i class="fa fa-th-large oneid-sidebar-icon" aria-hidden="true"></i><span><?=htmlspecialchars(oneid_translate('dashboard.menu.applications'), ENT_QUOTES, 'UTF-8')?> <span class="inline-block" id="follo_data_list_count_text"></span></span>
                                          </a>
                                        </li>
                                        <li role="presentation">
                                          <a href="https://directory.upnm.edu.my/" target="_blank" rel="noopener noreferrer">
                                            <i class="fa fa-address-book oneid-sidebar-icon" aria-hidden="true"></i><span><?=htmlspecialchars(oneid_translate('dashboard.menu.staff_directory'), ENT_QUOTES, 'UTF-8')?></span><i class="fa fa-external-link oneid-sidebar-trailing" aria-hidden="true"></i>
                                          </a>
                                        </li>
                                        <li role="presentation" style="cursor: pointer !important;" onclick="open_faq();">
                                          <a id="tab_faq" >
                                            <i class="fa fa-question-circle oneid-sidebar-icon" aria-hidden="true"></i><span><?=htmlspecialchars(oneid_translate('dashboard.menu.faq'), ENT_QUOTES, 'UTF-8')?></span>
                                          </a>
                                        </li>
                                       <?php if ($productTourEnabled): ?>
                                        <li role="presentation">
                                          <a href="#" class="oneid-tour-trigger" data-oneid-product-tour-start>
                                            <i class="fa fa-compass oneid-sidebar-icon" aria-hidden="true"></i><span><?=htmlspecialchars(oneid_translate('dashboard.menu.product_tour'), ENT_QUOTES, 'UTF-8')?></span>
                                          </a>
                                        </li>
                                       <?php endif; ?>
                                        <!--<li role="presentation">
                                          <a data-toggle="tab" id="security_tab_1" role="tab" href="#security_tab" aria-expanded="false">
                                            <span>Signed Devices</span>
                                          </a>
                                        </li>-->
                                       <li role="presentation" style="cursor: pointer !important;" onclick="open_change_password(0);">
                                          <a id="tab_chang_pwd" >
                                            <i class="fa fa-key oneid-sidebar-icon" aria-hidden="true"></i><span><?=htmlspecialchars(oneid_translate('dashboard.menu.change_password'), ENT_QUOTES, 'UTF-8')?></span>
                                          </a>
                                        </li>
                                       <?php if ($userMfaEnrollmentAvailable): ?>
                                        <li role="presentation">
                                          <a id="tab_user_mfa_security" href="user-mfa-security">
                                            <i class="fa fa-lock oneid-sidebar-icon" aria-hidden="true"></i><span><?=htmlspecialchars(oneid_translate('user_mfa.security.title'), ENT_QUOTES, 'UTF-8')?></span>
                                          </a>
                                        </li>
                                       <?php endif; ?>
                                        <li role="presentation" style="cursor: pointer !important;" >
                                          <a id="tab_faq" href="logout">
                                            <i class="fa fa-sign-out oneid-sidebar-icon" aria-hidden="true"></i><span><?=htmlspecialchars(oneid_translate('dashboard.menu.logout'), ENT_QUOTES, 'UTF-8')?></span>
                                          </a>
                                        </li>
                                      </ul>

                                      
                                    </div>


                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>


                     <div class="row">
                        <div class="col-sm-12">

                        </div>
                     </div>


                  </div>
                  <div class="col-sm-8">
                     <div class="row">
                        <div class="col-sm-12">
                           <div class="panel panel-default card-view pa-0">
                              <div class="panel-wrapper collapse in">
                                 <div class="panel-body pa-0">


                                 <!-- Tab content -->
                                   <div class="tab-content" id="myTabContent_8">

                                     <!-- Applications -->
                                     <div id="follo_8" class="tab-pane fade active in" role="tabpanel">
                                       <div class="user-app-panel">
                                          <div class="user-app-header">
                                             <div>
                                                <span class="user-app-eyebrow"><?=htmlspecialchars(oneid_translate('dashboard.apps.eyebrow'), ENT_QUOTES, 'UTF-8')?></span>
                                                <h4 class="user-app-title"><?=htmlspecialchars(oneid_translate('dashboard.apps.title'), ENT_QUOTES, 'UTF-8')?></h4>
                                                <p class="user-app-intro"><?=htmlspecialchars(oneid_translate('dashboard.apps.intro'), ENT_QUOTES, 'UTF-8')?></p>
                                             </div>
                                             <div class="user-app-header-actions">
                                                <div class="user-app-summary" aria-live="polite" aria-label="<?=htmlspecialchars(oneid_translate('dashboard.apps.summary_label'), ENT_QUOTES, 'UTF-8')?>">
                                                   <div class="user-app-count">
                                                      <span><?=htmlspecialchars(oneid_translate('dashboard.apps.total'), ENT_QUOTES, 'UTF-8')?></span>
                                                      <strong id="user_app_count">&mdash;</strong>
                                                   </div>
                                                   <div class="user-app-count is-sso">
                                                      <span><?=htmlspecialchars(oneid_translate('dashboard.apps.full_sso'), ENT_QUOTES, 'UTF-8')?></span>
                                                      <strong id="user_app_sso_count">&mdash;</strong>
                                                   </div>
                                                   <div class="user-app-count is-non-sso">
                                                      <span><?=htmlspecialchars(oneid_translate('dashboard.apps.non_sso'), ENT_QUOTES, 'UTF-8')?></span>
                                                      <strong id="user_app_non_sso_count">&mdash;</strong>
                                                   </div>
                                                </div>
                                                <button type="button" class="user-app-refresh" onclick="get_specific_user_app_list();" title="<?=htmlspecialchars(oneid_translate('dashboard.apps.refresh'), ENT_QUOTES, 'UTF-8')?>" aria-label="<?=htmlspecialchars(oneid_translate('dashboard.apps.refresh'), ENT_QUOTES, 'UTF-8')?>">
                                                   <i class="fa fa-refresh" aria-hidden="true"></i>
                                                </button>
                                             </div>
                                          </div>

                                          <div class="user-app-category-card">
                                             <div class="user-app-category-title-row">
                                                <h5><?=htmlspecialchars(oneid_translate('dashboard.apps.categories'), ENT_QUOTES, 'UTF-8')?></h5>
                                                <button type="button" class="user-app-recent__trigger" id="user_app_recent_trigger" title="<?=htmlspecialchars(oneid_translate('dashboard.apps.recent'), ENT_QUOTES, 'UTF-8')?>" aria-label="<?=htmlspecialchars(oneid_translate('dashboard.apps.recent'), ENT_QUOTES, 'UTF-8')?>" aria-expanded="false" aria-controls="user_app_recent" hidden>
                                                   <i class="fa fa-history" aria-hidden="true"></i>
                                                </button>
                                             </div>
                                             <section class="user-app-recent" id="user_app_recent" hidden role="dialog" aria-modal="false" aria-labelledby="user_app_recent_title">
                                                <div class="user-app-recent__head">
                                                   <span id="user_app_recent_title"><i class="fa fa-history" aria-hidden="true"></i><?=htmlspecialchars(oneid_translate('dashboard.apps.recent'), ENT_QUOTES, 'UTF-8')?></span>
                                                   <button type="button" class="user-app-recent__close" data-recent-close aria-label="<?=htmlspecialchars(oneid_translate('dashboard.health.close'), ENT_QUOTES, 'UTF-8')?>">&times;</button>
                                                </div>
                                                <div class="user-app-recent__grid" id="user_app_recent_grid"></div>
                                                <div class="user-app-recent__footer">
                                                   <button type="button" data-recent-clear><i class="fa fa-trash-o" aria-hidden="true"></i><?=htmlspecialchars(oneid_translate('dashboard.apps.clear_recent'), ENT_QUOTES, 'UTF-8')?></button>
                                                </div>
                                             </section>
                                             <div class="user-app-search">
                                                <i class="fa fa-search" aria-hidden="true"></i>
                                                <label class="sr-only" for="user_app_search"><?=htmlspecialchars(oneid_translate('dashboard.apps.search'), ENT_QUOTES, 'UTF-8')?></label>
                                                <input type="search" id="user_app_search" autocomplete="off" placeholder="<?=htmlspecialchars(oneid_translate('dashboard.apps.search_placeholder'), ENT_QUOTES, 'UTF-8')?>" aria-autocomplete="list" aria-controls="user_app_search_suggestions" aria-expanded="false">
                                                <button type="button" id="user_app_search_clear" title="<?=htmlspecialchars(oneid_translate('dashboard.apps.clear_search'), ENT_QUOTES, 'UTF-8')?>" aria-label="<?=htmlspecialchars(oneid_translate('dashboard.apps.clear_search'), ENT_QUOTES, 'UTF-8')?>" hidden>
                                                   <i class="fa fa-times" aria-hidden="true"></i>
                                                </button>
                                                <div class="user-app-search-suggestions" id="user_app_search_suggestions" role="listbox" aria-label="<?=htmlspecialchars(oneid_translate('dashboard.apps.suggestions'), ENT_QUOTES, 'UTF-8')?>" hidden></div>
                                             </div>
                                             <ul role="tablist" class="nav" id="WebAppsTabsHeader"></ul>
                                          </div>

                                          <div id="app_list_loading" class="user-app-skeleton" style="display:none;" role="status" aria-live="polite">
                                             <span class="sr-only"><?=htmlspecialchars(oneid_translate('dashboard.apps.loading'), ENT_QUOTES, 'UTF-8')?>. <?=htmlspecialchars(oneid_translate('dashboard.apps.loading_help'), ENT_QUOTES, 'UTF-8')?></span>
                                             <?php for ($skeletonIndex = 0; $skeletonIndex < 5; $skeletonIndex++): ?>
                                             <div class="user-app-skeleton__row" aria-hidden="true">
                                                <span class="user-app-skeleton__index"></span><span class="user-app-skeleton__image"></span>
                                                <span class="user-app-skeleton__copy"><i></i><i></i></span>
                                                <span class="user-app-skeleton__action"></span>
                                             </div>
                                             <?php endfor; ?>
                                          </div>

                                          <div id="app_list" class="user-app-directory">
                                             <div class="tab-content" id="WebAppsTabsContent"></div>
                                             <div id="follo_data_list"></div>
                                          </div>
                                       </div>
                                     </div>

                                     <!-- Signed Devices -->
                                     <div id="security_tab" class="tab-pane fade" role="tabpanel">
                                       <div class="panel-heading">
                                         <div class="pull-left">
                                           <h6 class="panel-title txt-dark"><?=htmlspecialchars(oneid_translate('dashboard.sessions.title'), ENT_QUOTES, 'UTF-8')?></h6>
                                         </div>
                                         <div class="pull-right">
                                           <button type="button" class="pull-left inline-block refresh mr-15 oneid-icon-button" onclick="get_specific_user_activ_session()" aria-label="<?=htmlspecialchars(oneid_translate('dashboard.sessions.refresh'), ENT_QUOTES, 'UTF-8')?>">
                                             <i class="zmdi zmdi-replay text-primary" aria-hidden="true"></i>
                                           </button>
                                         </div>
                                         <div class="clearfix"></div>
                                       </div>

                                       <div id="app_security_session_loading" style="display:none;">
                                         <br/>
                                         <div class="col-lg-12">
                                           <div class="progress progress-lg">
                                             <div class="progress-bar progress-bar-primary active progress-bar-striped"
                                                  aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"
                                                  style="width: 100%" role="progressbar">
                                               <?=htmlspecialchars(oneid_translate('dashboard.sessions.loading'), ENT_QUOTES, 'UTF-8')?>
                                             </div>
                                           </div>
                                         </div>
                                         <br/><br/>
                                       </div>

                                       <div class="followers-wrap" id="app_security_session_list">
                                         <ul class="followers-list-wrap">
                                           <li class="follow-list">
                                             <div class="follo-body" id="security_tab_session"></div>
                                           </li>
                                         </ul>
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
               <!-- /Row -->
            </div>
            <!-- Footer -->
            <footer class="footer pl-30 pr-30">
               <div class="container">
                  <div class="row">
                     <div class="col-sm-6">
                        <p><?php echo htmlspecialchars(oneid_application_footer(), ENT_QUOTES, 'UTF-8'); ?></p>
                     </div>
                     <!-- <div class="col-sm-6 text-right">
                        <p>Follow Us</p>
                        <a href="#"><i class="fa fa-facebook"></i></a>
                        <a href="#"><i class="fa fa-twitter"></i></a>
                        <a href="#"><i class="fa fa-google-plus"></i></a>
                        </div> -->
                  </div>
               </div>
            </footer>
            <!-- /Footer -->
         </div>
         <!-- /Main Content -->
      </div>
      <!-- /#wrapper -->
      <!-- JavaScript -->
      <!-- jQuery -->
      <script src="../vendors/bower_components/jquery/dist/jquery.min.js"></script>
      <!-- Bootstrap Core JavaScript -->
      <script src="../vendors/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
      <!-- Counter Animation JavaScript -->
      <script src="../vendors/bower_components/waypoints/lib/jquery.waypoints.min.js"></script>
      <script src="../vendors/bower_components/jquery.counterup/jquery.counterup.min.js"></script>
      <!-- Slimscroll JavaScript -->
      <script src="../dist/js/jquery.slimscroll.js"></script>
      <!-- Fancy Dropdown JS -->
      <script src="../dist/js/dropdown-bootstrap-extended.js"></script>
      <!-- Switchery JavaScript -->
      <script src="../vendors/bower_components/switchery/dist/switchery.min.js"></script>
      <!-- Sweet-Alert  -->
      <script src="../vendors/bower_components/sweetalert/dist/sweetalert.min.js"></script>
      <script src="../dist/js/oneid-professional-alert.js?v=20260910-3"></script>
      <script>
         window.OneIdUserSessionConfig = <?=json_encode(
            oneid_user_session_presentation_config((int) ($_SESSION['password_change_required'] ?? 0) !== 1),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
         )?>;
      </script>
      <script src="../dist/js/oneid-user-session.js?v=20261001-1"></script>
      <script>
         window.OneIdUserHealthConfig = <?=json_encode([
            'version' => ONEID_APP_VERSION,
            'environment' => strtolower(trim((string) oneid_config('ONEID_ENVIRONMENT', 'unknown'))),
            'supportEmail' => 'ask.oneid@upnm.edu.my',
            'supportPhone' => '03-9051 2700',
            'text' => [
               'title' => oneid_translate('dashboard.health.title'),
               'subtitle' => oneid_translate('dashboard.health.subtitle'),
               'connection' => oneid_translate('dashboard.health.connection'),
               'online' => oneid_translate('dashboard.health.online'),
               'offline' => oneid_translate('dashboard.health.offline'),
               'response' => oneid_translate('dashboard.health.response'),
               'fast' => oneid_translate('dashboard.health.fast'),
               'moderate' => oneid_translate('dashboard.health.moderate'),
               'slow' => oneid_translate('dashboard.health.slow'),
               'updated' => oneid_translate('dashboard.health.updated'),
               'version' => oneid_translate('dashboard.health.version'),
               'refresh' => oneid_translate('dashboard.health.refresh'),
               'clear' => oneid_translate('dashboard.health.clear'),
               'copy' => oneid_translate('dashboard.health.copy'),
               'view' => oneid_translate('dashboard.health.view'),
               'diagnosticsTitle' => oneid_translate('dashboard.health.diagnostics_title'),
               'environment' => oneid_translate('dashboard.health.environment'),
               'browser' => oneid_translate('dashboard.health.browser'),
               'viewport' => oneid_translate('dashboard.health.viewport'),
               'reference' => oneid_translate('dashboard.health.reference'),
               'checkedAt' => oneid_translate('dashboard.health.checked_at'),
               'notAvailable' => oneid_translate('dashboard.health.not_available'),
               'diagnosticsNote' => oneid_translate('dashboard.health.diagnostics_note'),
               'support' => oneid_translate('dashboard.health.support'),
               'refreshing' => oneid_translate('dashboard.health.refreshing'),
               'refreshed' => oneid_translate('dashboard.health.refreshed'),
               'failed' => oneid_translate('dashboard.health.failed'),
               'cleared' => oneid_translate('dashboard.health.cleared'),
               'retry' => oneid_translate('dashboard.health.retry'),
               'copied' => oneid_translate('dashboard.health.copied'),
               'close' => oneid_translate('dashboard.health.close'),
               'closeDiagnostics' => oneid_translate('dashboard.health.close_diagnostics'),
               'note' => oneid_translate('dashboard.health.note'),
            ],
         ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
      </script>
      <script src="../dist/js/oneid-user-health.js?v=20261008-4"></script>
      <?php if ($productTourEnabled): ?>
      <script>
         window.OneIdProductTourConfig = <?=json_encode([
            'enabled' => true,
            'id' => 'dashboard-guide-v2',
            'tourId' => $productTourId,
            'tourVersion' => $productTourVersion,
            'serverStatus' => $productTourServerStatus,
            'persistenceEnabled' => $productTourStorageAvailable,
            'apiUrl' => APP_URL . '/lib/q_func.php',
            'csrfToken' => oneid_csrf_token(),
            'text' => [
               'eyebrow' => oneid_translate('dashboard.tour.eyebrow'),
               'step' => oneid_translate('dashboard.tour.step'),
               'back' => oneid_translate('dashboard.tour.back'),
               'next' => oneid_translate('dashboard.tour.next'),
               'skip' => oneid_translate('dashboard.tour.skip'),
               'finish' => oneid_translate('dashboard.tour.finish'),
            ],
            'steps' => [
               ['selector' => '#user_app_search', 'title' => oneid_translate('dashboard.tour.search.title'), 'body' => oneid_translate('dashboard.tour.search.body')],
               ['selector' => '.user-app-favourite', 'title' => oneid_translate('dashboard.tour.favourite.title'), 'body' => oneid_translate('dashboard.tour.favourite.body')],
               ['selector' => '.oneid-display-settings__trigger', 'title' => oneid_translate('dashboard.tour.display.title'), 'body' => oneid_translate('dashboard.tour.display.body')],
               ['selector' => '#oneid_user_health_trigger', 'title' => oneid_translate('dashboard.tour.health.title'), 'body' => oneid_translate('dashboard.tour.health.body')],
               ['selector' => '#tab_user_mfa_security', 'mobileReveal' => 'sidebar', 'title' => oneid_translate('dashboard.tour.security.title'), 'body' => oneid_translate('dashboard.tour.security.body')],
               ['selector' => '[data-oneid-user-session-renew]', 'title' => oneid_translate('dashboard.tour.session.title'), 'body' => oneid_translate('dashboard.tour.session.body')],
            ],
         ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
      </script>
      <script src="../dist/js/oneid-product-tour.js?v=20261007-2"></script>
      <?php endif; ?>
      <script src="../vendors/bower_components/jquery-toast-plugin/dist/jquery.toast.min.js"></script>
      <script src="../assetsM/js/oneid-notifications.js?v=20260716-1"></script>
      <!-- Init JavaScript -->
      <script src="../dist/js/init.js?v=20260716-1"></script>
      <script src="../dist/js/widgets-data.js"></script>
      <script>
         const dashboardI18n = <?=json_encode([
            'noAccess' => oneid_translate('dashboard.apps.no_access'),
            'noAccessHelp' => oneid_translate('dashboard.apps.no_access_help'),
            'searchResults' => oneid_translate('dashboard.apps.search_results'),
            'favourite' => oneid_translate('dashboard.apps.favourite'),
            'recent' => oneid_translate('dashboard.apps.recent'),
            'noRecent' => oneid_translate('dashboard.apps.no_recent'),
            'lastUsed' => oneid_translate('dashboard.apps.last_used'),
            'statusAvailable' => oneid_translate('dashboard.apps.status.available'),
            'statusSlow' => oneid_translate('dashboard.apps.status.slow'),
            'statusMaintenance' => oneid_translate('dashboard.apps.status.maintenance'),
            'statusUnavailable' => oneid_translate('dashboard.apps.status.unavailable'),
            'statusChecking' => oneid_translate('dashboard.apps.status.checking'),
            'statusChecked' => oneid_translate('dashboard.apps.status.checked', ['time' => '{time}']),
            'statusBlocked' => oneid_translate('dashboard.apps.status.blocked', ['status' => '{status}']),
            'addFavourite' => oneid_translate('dashboard.apps.add_favourite'),
            'removeFavourite' => oneid_translate('dashboard.apps.remove_favourite'),
            'noFavourite' => oneid_translate('dashboard.apps.no_favourite'),
            'noFavouriteSearch' => oneid_translate('dashboard.apps.no_favourite_search'),
            'emptyCategory' => oneid_translate('dashboard.apps.empty_category'),
            'emptySearch' => oneid_translate('dashboard.apps.empty_search'),
            'suggestions' => oneid_translate('dashboard.apps.suggestions'),
            'noResultsHelp' => oneid_translate('dashboard.apps.no_results_help'),
            'directAccess' => oneid_translate('dashboard.apps.direct_access'),
            'oneidSso' => oneid_translate('dashboard.apps.oneid_sso'),
            'access' => oneid_translate('dashboard.apps.access'),
            'login' => oneid_translate('dashboard.apps.login'),
            'accessTitle' => oneid_translate('dashboard.apps.access_title'),
            'loginTitle' => oneid_translate('dashboard.apps.login_title'),
            'loadFailed' => oneid_translate('dashboard.apps.load_failed'),
            'loadFailedHelp' => oneid_translate('dashboard.apps.load_failed_help'),
            'favouriteFailed' => oneid_translate('dashboard.apps.favourite_failed'),
            'accessDenied' => oneid_translate('dashboard.apps.access_denied'),
            'currentSession' => oneid_translate('dashboard.sessions.current'),
            'signOff' => oneid_translate('dashboard.sessions.sign_off'),
            'signOffTitle' => oneid_translate('dashboard.sessions.confirm_title'),
            'signOffText' => oneid_translate('dashboard.sessions.confirm_text'),
            'signOffButton' => oneid_translate('dashboard.sessions.confirm_button'),
            'signOffSuccess' => oneid_translate('dashboard.sessions.success'),
            'signOffError' => oneid_translate('dashboard.sessions.error'),
            'passwordCurrent' => oneid_translate('dashboard.password.current'),
            'passwordTitle' => oneid_translate('dashboard.password.title'),
            'passwordInitialTitle' => oneid_translate('dashboard.password.initial_title'),
            'passwordMyDigitalIdReauth' => oneid_translate('dashboard.password.mydigitalid_reauth'),
            'passwordLength' => oneid_translate('dashboard.password.length'),
            'passwordLowercase' => oneid_translate('dashboard.password.lowercase'),
            'passwordUppercase' => oneid_translate('dashboard.password.uppercase'),
            'passwordNumber' => oneid_translate('dashboard.password.number'),
            'passwordSpecial' => oneid_translate('dashboard.password.special'),
            'passwordCopied' => oneid_translate('dashboard.password.copied'),
            'passwordChange' => oneid_translate('dashboard.password.change'),
            'passwordChanging' => oneid_translate('dashboard.password.changing'),
            'passwordForgotMyDigitalId' => oneid_translate('dashboard.password.forgot_mydid'),
            'passwordOtpSent' => oneid_translate('dashboard.password.otp_sent'),
            'passwordOtpVerify' => oneid_translate('dashboard.password.otp_verify'),
            'passwordOtpInvalid' => oneid_translate('dashboard.password.otp_invalid'),
            'passwordReset' => oneid_translate('dashboard.password.reset'),
            'passwordRecoveryFailed' => oneid_translate('dashboard.password.recovery_failed'),
            'passwordWeak' => oneid_translate('dashboard.password.weak'),
            'passwordStrong' => oneid_translate('dashboard.password.strong'),
            'passwordMismatch' => oneid_translate('dashboard.password.mismatch'),
            'passwordMatch' => oneid_translate('dashboard.password.match'),
            'passwordSuccess' => oneid_translate('dashboard.password.success'),
            'passwordFailed' => oneid_translate('dashboard.password.failed'),
            'passwordReauth' => oneid_translate('dashboard.password.reauth'),
            'passwordHttpFailed' => oneid_translate('dashboard.password.http_failed', ['status' => '{status}']),
            'feedbackCode' => oneid_translate('dashboard.feedback.code'),
            'feedbackReference' => oneid_translate('dashboard.feedback.reference'),
            'sessionStatusUnavailable' => oneid_translate('user_session.request_failed'),
            'asnbReminderTitle' => oneid_translate('dashboard.asnb_reminder.title'),
            'asnbReminderMessage' => oneid_translate('dashboard.asnb_reminder.message'),
            'asnbReminderOpen' => oneid_translate('dashboard.asnb_reminder.open'),
            'asnbReminderLater' => oneid_translate('dashboard.asnb_reminder.later'),
         ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
         $.ajaxSetup({
            headers: {'X-CSRF-Token': <?php echo json_encode(oneid_csrf_token()); ?>}
         });
         var oneidHeartbeatLastWarningAt = 0;
         $(document).ajaxSuccess(function(event, xhr, settings) {
            var url = String(settings.url || '');
            if (url.indexOf('lib/q_func') === -1) return;
            var data = typeof settings.data === 'string' ? new URLSearchParams(settings.data) : settings.data || {};
            var technical = ['update_specific_token_datetime','user_session_status','admin_step_up_status'];
            var meaningful = technical.every(function(action) {
               return !(data instanceof URLSearchParams ? data.has(action) : Object.prototype.hasOwnProperty.call(data, action));
            });
            if (meaningful) document.dispatchEvent(new CustomEvent('oneid:user-activity-committed'));
         });

         $(document).ready(function() {
             get_specific_user_app_list();
             get_specific_user_activ_session();
             check_default_password();
             startTokenRefresh();
         });

         function showEmadaniAsnbReminder(){
             <?php if ($showEmadaniAsnbReminder): ?>
             swal({
                customClass: 'oneid-asnb-reminder',
                title: dashboardI18n.asnbReminderTitle,
                text: dashboardI18n.asnbReminderMessage,
                html: true,
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#1f6f43',
                confirmButtonText: dashboardI18n.asnbReminderOpen,
                cancelButtonText: dashboardI18n.asnbReminderLater,
                closeOnConfirm: true
             }, function(confirmed) {
                if (confirmed) go_to_service_provider('8R8QLPLTDN');
             });
             <?php endif; ?>
         }

        function check_default_password(sp_id){
         $.ajax({
                 type: 'POST',
                 url: '../lib/q_func',
                 dataType: "json",
                 data: {check_default_password:""},
                 beforeSend: function(){
                   // $('#login_status').html('<div class="alert alert-info alert-dismissable alert-style-1"><i class="zmdi zmdi-info-outline"></i>Signing on. Checking info. Wait a moment.</div>');
                 },
                 success: function (response) {
                  if(response['result']==="change_pwd"||response['result']==="initial_setup"){
                     initialPasswordSetup=response['result']==="initial_setup";
                     //open_change_password();
                      $('#modal_change_first_time_password').modal('show');
                     $('#btn_close_changePW').hide();
                  }else if(response['result']==="mydigitalid_reauth_required"){
                     alert(dashboardI18n.passwordMyDigitalIdReauth);
                     window.location.href=response.redirect_uri||'../';
                  }else{
                     $('#btn_close_changePW').show();
                     showEmadaniAsnbReminder();
                              }
         
             },
             error: function (xhr, error, thrown) {
             }
         });
         }
         
         
         var userAppDirectoryGroups = [];
         var userAppSearchTerm = '';
         var userAppActiveTab = '#user_app_favourites_tab';
         var userAppHealth = {};
         var userAppHealthRequest = null;
         var userAppRecentStorageKey = 'oneid.recent-apps.v1';
         var userAppSearchStorageKey = 'oneid.app-search.v1';

         function userAppRecentEntries(){
            try {
               var value = JSON.parse(localStorage.getItem(userAppRecentStorageKey) || '[]');
               return Array.isArray(value) ? value.map(function(item){
                  if (item && typeof item === 'object') return {id:String(item.id || ''),at:Number(item.at || 0)};
                  return {id:String(item || ''),at:0};
               }).filter(function(item){ return item.id !== ''; }).slice(0, 6) : [];
            } catch (error) { return []; }
         }

         function userAppRecentIds(){ return userAppRecentEntries().map(function(item){ return item.id; }); }

         function userAppRecentTimestamp(appId){
            var entry = userAppRecentEntries().find(function(item){ return item.id === String(appId); });
            if (!entry || !entry.at) return '';
            try {
               return new Intl.DateTimeFormat(document.documentElement.lang === 'en' ? 'en-MY' : 'ms-MY', {
                  day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'
               }).format(new Date(entry.at));
            } catch (error) { return new Date(entry.at).toLocaleString(); }
         }

         function rememberUserApp(appId){
            var id = String(appId || '');
            if (!id) return;
            var entries = userAppRecentEntries().filter(function(item){ return item.id !== id; });
            entries.unshift({id:id,at:Date.now()});
            try { localStorage.setItem(userAppRecentStorageKey, JSON.stringify(entries.slice(0, 6))); } catch (error) {}
         }

         function userAppStatusText(state){
            return {available:dashboardI18n.statusAvailable,slow:dashboardI18n.statusSlow,
               maintenance:dashboardI18n.statusMaintenance,unavailable:dashboardI18n.statusUnavailable,
               checking:dashboardI18n.statusChecking}[state] || dashboardI18n.statusChecking;
         }

         function userAppHealthEntry(appId){
            var entry = userAppHealth[String(appId)];
            if (entry && typeof entry === 'object') return entry;
            return {state:String(entry || 'checking'), checked_at:''};
         }

         function userAppHealthCheckedAt(value){
            if (!value) return '';
            var date = new Date(value);
            if (isNaN(date.getTime())) return '';
            try {
               return new Intl.DateTimeFormat(document.documentElement.lang === 'en' ? 'en-MY' : 'ms-MY', {
                  hour:'2-digit', minute:'2-digit'
               }).format(date);
            } catch (error) { return date.toLocaleTimeString(); }
         }

         function userAppHealthMarkup(appId){
            var entry = userAppHealthEntry(appId);
            var state = entry.state;
            var checked = userAppHealthCheckedAt(entry.checked_at);
            var checkedText = checked ? dashboardI18n.statusChecked.replace('{time}', checked) : '';
            return '<span class="user-app-health is-'+state+'" data-app-health="'+userAppText(appId)+'" title="'+userAppText(checkedText)+'"><i></i><span>'+userAppText(userAppStatusText(state))+'</span>'+
               (checkedText ? '<em>'+userAppText(checkedText)+'</em>' : '')+'</span>';
         }

         function userAppHealthBlocksAccess(state){
            return state === 'maintenance' || state === 'unavailable';
         }

         function userAppText(value){
            return $('<div>').text(value == null ? '' : value).html();
         }

         function userAppNormalize(value){
            var text = String(value || '').toLocaleLowerCase();
            try { text = text.normalize('NFD').replace(/[\u0300-\u036f]/g, ''); } catch (error) {}
            return text.replace(/[^a-z0-9]+/g, ' ').trim().replace(/\s+/g, ' ');
         }

         function userAppSearchFields(application){
            var name = userAppNormalize(application.sp_name);
            var description = userAppNormalize(application.sp_description);
            var ignored = {system:1,sistem:1,application:1,aplikasi:1,the:1,of:1,dan:1,untuk:1};
            var initials = name.split(' ').filter(function(word){ return word && !ignored[word]; })
               .map(function(word){ return word.charAt(0); }).join('');
            return {
               name:name,
               description:description,
               compact:name.replace(/\s/g, ''),
               acronym:initials
            };
         }

         function userAppMatches(application, term){
            var query = userAppNormalize(term);
            if (query === '') return true;
            var fields = userAppSearchFields(application);
            var compactQuery = query.replace(/\s/g, '');
            return fields.name.indexOf(query) !== -1 || fields.description.indexOf(query) !== -1 ||
               fields.compact.indexOf(compactQuery) !== -1 || fields.acronym.indexOf(compactQuery) !== -1;
         }

         function userAppSearchScore(application, term){
            var query = userAppNormalize(term);
            var compactQuery = query.replace(/\s/g, '');
            var fields = userAppSearchFields(application);
            if (!query) return 0;
            if (fields.name === query || fields.compact === compactQuery) return 100;
            if (fields.acronym === compactQuery) return 95;
            if (fields.name.indexOf(query) === 0) return 85;
            if (fields.compact.indexOf(compactQuery) === 0) return 80;
            if (fields.name.indexOf(query) !== -1) return 70;
            if (fields.acronym.indexOf(compactQuery) !== -1) return 65;
            if (fields.description.indexOf(query) !== -1) return 50;
            return 0;
         }

         function hideUserAppSearchSuggestions(){
            $('#user_app_search_suggestions').prop('hidden', true).empty();
            $('#user_app_search').attr('aria-expanded', 'false');
         }

         function renderUserAppSearchSuggestions(){
            var term = userAppSearchTerm.trim();
            var $list = $('#user_app_search_suggestions');
            if (!term || !$list.length) { hideUserAppSearchSuggestions(); return; }
            var suggestions = userAppUniqueApplications().map(function(application){
               return {application:application, score:userAppSearchScore(application, term)};
            }).filter(function(item){ return item.score > 0; })
              .sort(function(a, b){ return b.score - a.score || String(a.application.sp_name).localeCompare(String(b.application.sp_name)); })
              .slice(0, 5);
            if (!suggestions.length) { hideUserAppSearchSuggestions(); return; }
            var html = '<div class="user-app-search-suggestions__label">'+userAppText(dashboardI18n.suggestions)+'</div>';
            suggestions.forEach(function(item){
               var application = item.application;
               var image = userAppText(application.sp_image);
               var source = image === '' ? '../img/thumb-1.jpg' : '../public_img/' + image;
               html += '<button type="button" role="option" class="user-app-search-suggestion" data-search-value="'+userAppText(application.sp_name)+'">';
               html += '<img src="'+source+'" alt="" onerror="this.onerror=null;this.src=\'../img/thumb-1.jpg\';">';
               html += '<span><strong>'+userAppText(application.sp_name)+'</strong><small>'+userAppText(application.sp_description)+'</small></span>';
               html += '<i class="fa fa-arrow-right" aria-hidden="true"></i></button>';
            });
            $list.html(html).prop('hidden', false);
            $('#user_app_search').attr('aria-expanded', 'true');
         }

         function userAppNoResults(applicationPool){
            var choices = applicationPool.slice().sort(function(a, b){
               return Number(b.is_favourite || 0) - Number(a.is_favourite || 0) || String(a.sp_name).localeCompare(String(b.sp_name));
            }).slice(0, 3);
            var html = '<div class="user-app-category-empty user-app-search-empty"><i class="fa fa-search" aria-hidden="true"></i>'+
               '<span>'+userAppText(dashboardI18n.emptySearch)+'</span><small>'+userAppText(dashboardI18n.noResultsHelp)+'</small>';
            if (choices.length) {
               html += '<div class="user-app-search-empty__choices">';
               choices.forEach(function(application){
                  html += '<button type="button" data-search-value="'+userAppText(application.sp_name)+'">'+userAppText(application.sp_name)+'</button>';
               });
               html += '</div>';
            }
            return html + '</div>';
         }

         function userAppUniqueApplications(){
            var seen = {};
            var applications = [];
            $.each(userAppDirectoryGroups, function(_, group){
               $.each(Array.isArray(group.data) ? group.data : [], function(__, application){
                  var id = String(application.sp_id || '');
                  if (id !== '' && !seen[id]) {
                     seen[id] = true;
                     applications.push(application);
                  }
               });
            });
            return applications;
         }

         function userAppGroupIsNonSso(group){
            var applications = Array.isArray(group.data) ? group.data : [];
            return applications.length > 0 && applications.every(function(application){
               return String(application.sp_sso_support) !== '0';
            });
         }

         function userAppGroupsWithNonSsoLast(groups){
            var ssoGroups = [];
            var nonSsoGroups = [];
            $.each(Array.isArray(groups) ? groups : [], function(_, group){
               (userAppGroupIsNonSso(group) ? nonSsoGroups : ssoGroups).push(group);
            });
            return ssoGroups.concat(nonSsoGroups);
         }

         function userAppCard(application, index){
            var appId = userAppText(application.sp_id);
            var appName = userAppText(application.sp_name);
            var appDescription = userAppText(application.sp_description);
            var appImage = userAppText(application.sp_image);
            var imageSource = appImage === '' ? '../img/thumb-1.jpg' : '../public_img/' + appImage;
            var isDirect = String(application.sp_sso_support) !== '0';
            var isFavourite = Number(application.is_favourite) === 1;
            var favouriteTitle = userAppText((isFavourite ? dashboardI18n.removeFavourite : dashboardI18n.addFavourite)
               .replace('{name}', function(){ return String(application.sp_name || ''); })).replace(/"/g, '&quot;');
            var accessLabel = isDirect ? dashboardI18n.directAccess : dashboardI18n.oneidSso;
            var buttonLabel = isDirect ? dashboardI18n.login : dashboardI18n.access;
            var buttonTitle = isDirect ? dashboardI18n.loginTitle : dashboardI18n.accessTitle;

            var healthEntry = userAppHealthEntry(application.sp_id);
            var healthState = healthEntry.state;
            var accessBlocked = userAppHealthBlocksAccess(healthState);
            var card = '<article class="user-app-card" data-app-card="'+appId+'">';
            card += '<div class="user-app-index">'+index+'</div>';
            card += '<div class="user-app-image"><img src="'+imageSource+'" alt="" loading="lazy" onerror="this.onerror=null;this.src=\'../img/thumb-1.jpg\';"></div>';
            card += '<div class="user-app-content"><div class="user-app-name"><strong title="'+appName+'">'+appName+'</strong><span class="user-app-access '+(isDirect ? 'is-direct' : '')+'">'+accessLabel+'</span>'+userAppHealthMarkup(application.sp_id)+'</div>';
            if (userAppSearchTerm.trim() !== '') {
               var categories = [];
               $.each(userAppDirectoryGroups, function(_, group){
                  if ((group.data || []).some(function(item){ return String(item.sp_id) === String(application.sp_id); })) {
                     if (categories.indexOf(group.sp_group_name) === -1) categories.push(group.sp_group_name);
                  }
               });
               card += '<small class="user-app-result-category">'+userAppText(categories.join(' · '))+'</small>';
            }
            card += '<p title="'+appDescription+'">'+appDescription+'</p></div>';
            card += '<div class="user-app-actions">';
            card += '<button type="button" class="user-app-favourite '+(isFavourite ? 'is-selected' : '')+'" data-app-id="'+appId+'" data-favourite="'+(isFavourite ? '1' : '0')+'" aria-pressed="'+(isFavourite ? 'true' : 'false')+'" title="'+favouriteTitle+'" aria-label="'+favouriteTitle+'"><i class="fa fa-star" aria-hidden="true"></i></button>';
            var effectiveTitle = accessBlocked ? dashboardI18n.statusBlocked.replace('{status}', userAppStatusText(healthState)) : buttonTitle;
            card += '<button type="button" class="user-app-open '+(isDirect ? 'is-direct ' : '')+(accessBlocked ? 'is-disabled' : '')+'" data-app-id="'+appId+'" title="'+userAppText(effectiveTitle)+'" '+(accessBlocked ? 'disabled aria-disabled="true"' : '')+'><i class="fa '+(isDirect ? 'fa-sign-in' : 'fa-external-link')+'" aria-hidden="true"></i><span>'+buttonLabel+'</span></button>';
            card += '</div></article>';
            return card;
         }

         function renderUserAppRecentlyUsed(applications){
            var $section = $('#user_app_recent');
            var $trigger = $('#user_app_recent_trigger');
            var $grid = $('#user_app_recent_grid');
            if (!applications.length || userAppSearchTerm.trim() !== '') {
               $section.prop('hidden', true);
               $trigger.prop('hidden', true).attr('aria-expanded', 'false');
               $grid.html('');
               return;
            }
            var html = '';
            $.each(applications.slice(0, 6), function(_, application){
               var appId = userAppText(application.sp_id);
               var appName = userAppText(application.sp_name);
               var appImage = userAppText(application.sp_image);
               var imageSource = appImage === '' ? '../img/thumb-1.jpg' : '../public_img/' + appImage;
               var healthState = userAppHealthEntry(application.sp_id).state;
               var accessBlocked = userAppHealthBlocksAccess(healthState);
               var lastUsed = userAppRecentTimestamp(application.sp_id);
               html += '<button type="button" class="user-app-recent__item" data-recent-open data-app-id="'+appId+'" data-app-card="'+appId+'" title="'+appName+'" '+(accessBlocked ? 'disabled aria-disabled="true"' : '')+'>';
               html += '<img src="'+imageSource+'" alt="" loading="lazy" onerror="this.onerror=null;this.src=\'../img/thumb-1.jpg\';">';
               html += '<span class="user-app-recent__name">'+appName+'</span>';
               html += '<span class="user-app-recent__meta">'+userAppHealthMarkup(application.sp_id)+'<span class="user-app-recent__separator" aria-hidden="true">&middot;</span><span class="user-app-recent__time" title="'+userAppText(dashboardI18n.lastUsed)+'"><i class="fa fa-clock-o" aria-hidden="true"></i>'+userAppText(lastUsed || '\u2014')+'</span></span>';
               html += '<i class="fa fa-angle-right user-app-recent__arrow" aria-hidden="true"></i></button>';
            });
            $grid.html(html);
            $trigger.prop('hidden', false);
            if ($trigger.attr('aria-expanded') !== 'true') $section.prop('hidden', true);
         }

         function renderUserAppDirectory(){
            var term = userAppSearchTerm.trim();
            var allApplications = userAppUniqueApplications();
            var favouriteApplications = allApplications.filter(function(application){
               return Number(application.is_favourite) === 1 && userAppMatches(application, term);
            });
            var tabs = '';
            var panes = '';
            var matchingTabs = [];
            var requestedTab = userAppActiveTab;
            var recentOrder = userAppRecentIds();
            var recentApplications = recentOrder.map(function(id){
               return allApplications.find(function(application){ return String(application.sp_id) === id; });
            }).filter(Boolean).filter(function(application){ return userAppMatches(application, term); });

            if (allApplications.length === 0) {
               $('#user_app_count').text('0');
               $('#user_app_sso_count, #user_app_non_sso_count').text('0');
               $('#WebAppsTabsHeader, #WebAppsTabsContent').html('');
               $('#user_app_recent').prop('hidden', true);
               $('#user_app_recent_trigger').prop('hidden', true).attr('aria-expanded', 'false');
               $('#follo_data_list').html(
                  '<div class="user-app-state"><span><i class="fa fa-th-large" aria-hidden="true"></i></span>' +
                  '<strong>'+userAppText(dashboardI18n.noAccess)+'</strong>' +
                  '<small>'+userAppText(dashboardI18n.noAccessHelp)+'</small></div>'
               );
               return;
            }

            renderUserAppRecentlyUsed(recentApplications);

            tabs += '<li class="is-favourite-tab" role="presentation">';
            tabs += '<a data-toggle="tab" role="tab" href="#user_app_favourites_tab" title="'+userAppText(dashboardI18n.favourite)+'" aria-label="'+userAppText(dashboardI18n.favourite)+'"><i class="fa fa-star" aria-hidden="true"></i><span>'+userAppText(dashboardI18n.favourite)+'</span><strong>'+favouriteApplications.length+'</strong></a></li>';
            panes += '<div id="user_app_favourites_tab" class="tab-pane fade" role="tabpanel"><div class="user-app-list">';
            matchingTabs.push('#user_app_favourites_tab');
            if (favouriteApplications.length === 0) {
               panes += '<div class="user-app-category-empty"><i class="fa fa-star-o" aria-hidden="true"></i><span>'+userAppText(term === '' ? dashboardI18n.noFavourite : dashboardI18n.noFavouriteSearch)+'</span></div>';
            } else {
               $.each(favouriteApplications, function(index, application){ panes += userAppCard(application, index + 1); });
            }
            panes += '</div></div>';

            $.each(userAppGroupsWithNonSsoLast(userAppDirectoryGroups), function(index, group){
               var groupId = String(group.sp_group_id || index);
               var paneId = 'user_app_group_' + groupId.replace(/[^A-Za-z0-9_-]/g, '');
               var groupNameRaw = String(group.sp_group_name || 'Uncategorized');
               var groupName = userAppText(groupNameRaw);
               var isNonSso = userAppGroupIsNonSso(group);
               var applications = (Array.isArray(group.data) ? group.data : []).filter(function(application){
                  return userAppMatches(application, term);
               });

               if (applications.length > 0) {
                  matchingTabs.push('#' + paneId);
               }
               tabs += '<li class="'+(isNonSso ? 'is-non-sso-tab' : '')+'" role="presentation">';
               tabs += '<a data-toggle="tab" role="tab" href="#'+paneId+'"><span>'+groupName+'</span><strong>'+applications.length+'</strong></a></li>';
               panes += '<div id="'+paneId+'" class="tab-pane fade" role="tabpanel"><div class="user-app-list">';
               if (applications.length === 0) {
                  panes += '<div class="user-app-category-empty"><i class="fa fa-inbox" aria-hidden="true"></i><span>'+userAppText(term === '' ? dashboardI18n.emptyCategory : dashboardI18n.emptySearch)+'</span></div>';
               } else {
                  $.each(applications, function(appIndex, application){ panes += userAppCard(application, appIndex + 1); });
               }
               panes += '</div></div>';
            });

            var ssoCount = allApplications.filter(function(application){
               return String(application.sp_sso_support) === '0';
            }).length;
            $('#user_app_count').text(allApplications.length);
            $('#user_app_sso_count').text(ssoCount);
            $('#user_app_non_sso_count').text(allApplications.length - ssoCount);
            $('#follo_data_list_count_text').html('(' + allApplications.length + ')');
            $('#follo_data_list').html('');
            if (term !== '') {
               var results = allApplications.filter(function(application){ return userAppMatches(application, term); });
               tabs = '<li class="active" role="presentation"><span class="user-app-results" role="status">'+userAppText(dashboardI18n.searchResults)+' ('+results.length+')</span></li>';
               panes = '<div class="user-app-list">';
               $.each(results, function(index, application){
                  panes += userAppCard(application, index + 1);
               });
               if (!results.length) panes += userAppNoResults(allApplications);
               panes += '</div>';
            }
            $('#WebAppsTabsHeader').toggleClass('is-search-results', term !== '').html(tabs);
            $('#WebAppsTabsContent').html(panes);

            if (term !== '') return;
            if (matchingTabs.indexOf(requestedTab) === -1) {
               requestedTab = matchingTabs.length > 0 ? matchingTabs[0] : '#user_app_favourites_tab';
            }
            var $requestedLink = $('#WebAppsTabsHeader a[href="'+requestedTab+'"]');
            if ($requestedLink.length) {
               $requestedLink.tab('show');
               userAppActiveTab = requestedTab;
            }
            refreshVisibleDownstreamStatus();
         }

         function refreshVisibleDownstreamStatus(){
            var ids = [];
            $('#user_app_recent [data-app-card], #WebAppsTabsContent .tab-pane.active [data-app-card], #WebAppsTabsContent > .user-app-list [data-app-card]').each(function(){
               var id = String($(this).data('app-card') || '');
               if (id && ids.indexOf(id) === -1 && !userAppHealth[id]) ids.push(id);
            });
            ids = ids.slice(0, 12);
            if (!ids.length || userAppHealthRequest) return;
            userAppHealthRequest = $.ajax({type:'POST',url:'../lib/q_func',dataType:'json',timeout:5000,
               data:{user_downstream_status:'',sp_ids:JSON.stringify(ids)}})
               .done(function(response){
                  $.each(response && response.applications ? response.applications : {}, function(id, item){
                     userAppHealth[String(id)] = {
                        state:String(item.state || 'unavailable'),
                        checked_at:String(item.checked_at || '')
                     };
                     var entry = userAppHealthEntry(id);
                     var checked = userAppHealthCheckedAt(entry.checked_at);
                     var checkedText = checked ? dashboardI18n.statusChecked.replace('{time}', checked) : '';
                     $('[data-app-health="'+String(id).replace(/"/g, '')+'"]').attr('class','user-app-health is-'+entry.state).attr('title',checkedText)
                        .html('<i></i><span>'+userAppText(userAppStatusText(entry.state))+'</span>'+(checkedText ? '<em>'+userAppText(checkedText)+'</em>' : ''));
                     var blocked = userAppHealthBlocksAccess(entry.state);
                     $('.user-app-open[data-app-id="'+String(id).replace(/"/g, '')+'"], [data-recent-open][data-app-id="'+String(id).replace(/"/g, '')+'"]')
                        .prop('disabled', blocked).attr('aria-disabled', blocked ? 'true' : 'false').toggleClass('is-disabled', blocked)
                        .each(function(){
                           var normalTitle = $(this).hasClass('user-app-open')
                              ? ($(this).hasClass('is-direct') ? dashboardI18n.loginTitle : dashboardI18n.accessTitle)
                              : String($(this).find('.user-app-recent__name').text() || '');
                           $(this).attr('title', blocked ? dashboardI18n.statusBlocked.replace('{status}', userAppStatusText(entry.state)) : normalTitle);
                        });
                  });
               }).always(function(){ userAppHealthRequest = null; window.setTimeout(refreshVisibleDownstreamStatus, 0); });
         }

         document.addEventListener('oneid:dashboard-health-refresh', function(){
            Object.keys(userAppHealth).forEach(function(id){ delete userAppHealth[id]; });
         });

         //----Login
         function get_specific_user_app_list(){
            var href = $('#WebAppsTabsHeader li.active a').attr('href');
            if (href) {
               if (userAppSearchTerm.trim() === '') userAppActiveTab = href;
            }
         return $.ajax({
                 type: 'POST',
                 url: '../lib/q_func',
                 dataType: "json",
                 data: {get_specific_user_app_list:""},
                 beforeSend: function(){
                   $('#user_app_count, #user_app_sso_count, #user_app_non_sso_count').text('\u2014');
                   $('#app_list_loading').fadeIn();
                   $('#app_list').hide();
				   $('#WebAppsTabsHeader').html('');
				   $('#WebAppsTabsContent').html('');
				   $('#follo_data_list').html('');
                 },
				 success: function (response) {
                   $('#app_list_loading').hide();
                   $('#app_list').fadeIn();
				   userAppDirectoryGroups = Array.isArray(response) ? response : [];
				   if (userAppSearchTerm === '') {
				      try { userAppSearchTerm = String(sessionStorage.getItem(userAppSearchStorageKey) || ''); } catch (error) {}
				      $('#user_app_search').val(userAppSearchTerm);
				      $('#user_app_search_clear').prop('hidden', userAppSearchTerm === '');
				   }
				   renderUserAppDirectory();
				},
				error: function (xhr, error, thrown) {
                  $('#user_app_count, #user_app_sso_count, #user_app_non_sso_count').text('\u2014');
				   $('#app_list_loading').hide();
				   $('#app_list').show();
				   $('#follo_data_list').html(
					  '<div class="user-app-state is-error">' +
					  '<span><i class="fa fa-exclamation-triangle" aria-hidden="true"></i></span>' +
					  '<strong>'+userAppText(dashboardI18n.loadFailed)+'</strong>' +
					  '<small>'+userAppText(dashboardI18n.loadFailedHelp)+'</small>' +
					  '</div>'
				   );
				}
         });
         }

         $(document).on('input', '#user_app_search', function(){
            userAppSearchTerm = String(this.value || '');
            $('#user_app_search_clear').prop('hidden', userAppSearchTerm === '');
            try {
               if (userAppSearchTerm === '') sessionStorage.removeItem(userAppSearchStorageKey);
               else sessionStorage.setItem(userAppSearchStorageKey, userAppSearchTerm);
            } catch (error) {}
            renderUserAppDirectory();
            renderUserAppSearchSuggestions();
         });

         $(document).on('focus', '#user_app_search', renderUserAppSearchSuggestions);

         $(document).on('keydown', '#user_app_search', function(event){
            if (event.key === 'ArrowDown') {
               var first = $('#user_app_search_suggestions .user-app-search-suggestion').first();
               if (first.length) { event.preventDefault(); first.focus(); }
            } else if (event.key === 'Escape') hideUserAppSearchSuggestions();
         });

         $(document).on('keydown', '.user-app-search-suggestion', function(event){
            var items = $('.user-app-search-suggestion');
            var index = items.index(this);
            if (event.key === 'ArrowDown') { event.preventDefault(); items.eq((index + 1) % items.length).focus(); }
            if (event.key === 'ArrowUp') { event.preventDefault(); index > 0 ? items.eq(index - 1).focus() : $('#user_app_search').focus(); }
            if (event.key === 'Escape') { hideUserAppSearchSuggestions(); $('#user_app_search').focus(); }
         });

         $(document).on('click', '[data-search-value]', function(){
            userAppSearchTerm = String($(this).attr('data-search-value') || '');
            $('#user_app_search').val(userAppSearchTerm);
            $('#user_app_search_clear').prop('hidden', false);
            try { sessionStorage.setItem(userAppSearchStorageKey, userAppSearchTerm); } catch (error) {}
            hideUserAppSearchSuggestions();
            renderUserAppDirectory();
            $('#user_app_search').focus();
         });

         $(document).on('click', function(event){
            if (!$(event.target).closest('.user-app-search').length) hideUserAppSearchSuggestions();
         });

         $(document).on('click', '#user_app_search_clear', function(){
            userAppSearchTerm = '';
            $('#user_app_search').val('').focus();
            $(this).prop('hidden', true);
            try { sessionStorage.removeItem(userAppSearchStorageKey); } catch (error) {}
            hideUserAppSearchSuggestions();
            renderUserAppDirectory();
         });

         $(document).on('shown.bs.tab', '#WebAppsTabsHeader a[data-toggle="tab"]', function(){
            if (userAppSearchTerm.trim() === '') userAppActiveTab = $(this).attr('href');
            refreshVisibleDownstreamStatus();
         });

         $(document).on('click', '#user_app_recent_trigger', function(event){
            event.stopPropagation();
            var $trigger = $(this);
            var open = $trigger.attr('aria-expanded') !== 'true';
            $trigger.attr('aria-expanded', open ? 'true' : 'false');
            $('#user_app_recent').prop('hidden', !open);
            if (open) $('#user_app_recent [data-recent-open]').first().trigger('focus');
         });

         $(document).on('click', '[data-recent-close]', function(){
            $('#user_app_recent').prop('hidden', true);
            $('#user_app_recent_trigger').attr('aria-expanded', 'false').trigger('focus');
         });

         $(document).on('click', '[data-recent-clear]', function(){
            try { localStorage.removeItem(userAppRecentStorageKey); } catch (error) {}
            $('#user_app_recent_grid').html('');
            $('#user_app_recent').prop('hidden', true);
            $('#user_app_recent_trigger').prop('hidden', true).attr('aria-expanded', 'false');
         });

         $(document).on('click', function(event){
            if (!$(event.target).closest('#user_app_recent, #user_app_recent_trigger').length) {
               $('#user_app_recent').prop('hidden', true);
               $('#user_app_recent_trigger').attr('aria-expanded', 'false');
            }
         });

         $(document).on('keydown', function(event){
            if (event.key === 'Escape' && !$('#user_app_recent').prop('hidden')) {
               $('#user_app_recent').prop('hidden', true);
               $('#user_app_recent_trigger').attr('aria-expanded', 'false').trigger('focus');
            }
         });

         $(document).on('click', '[data-recent-open]', function(){
            var applicationWindow = window.open('about:blank', '_blank');
            if (applicationWindow) applicationWindow.opener = null;
            go_to_service_provider(String($(this).data('app-id') || ''), applicationWindow);
         });

         $(document).on('click', '.user-app-open', function(){
            if (this.disabled || $(this).attr('aria-disabled') === 'true') return;
            var applicationWindow = window.open('about:blank', '_blank');
            if (applicationWindow) {
               applicationWindow.opener = null;
            }
            go_to_service_provider(String($(this).data('app-id') || ''), applicationWindow);
         });

         function userAppFavouriteFailed($button){
            $button.prop('disabled', false).removeClass('is-saving');
            $.toast().reset('all');
            $.toast({heading: dashboardI18n.favouriteFailed, text: dashboardI18n.loadFailedHelp,
               position: 'bottom-center', loaderBg: '#fec107', icon: 'error', hideAfter: 3500, stack: 4});
         }

         $(document).on('click', '.user-app-favourite', function(){
            var $button = $(this);
            var appId = String($button.data('app-id') || '');
            var enabled = String($button.data('favourite')) === '1' ? '0' : '1';
            $button.prop('disabled', true).addClass('is-saving');

            $.ajax({
               type: 'POST',
               url: '../lib/q_func',
               dataType: 'json',
               timeout: 15000,
               data: {user_set_app_favourite: '', sp_id: appId, enabled: enabled},
               success: function(response){
                  if (!response || Number(response.status) !== 1) {
                     userAppFavouriteFailed($button);
                     return;
                  }
                  $.each(userAppDirectoryGroups, function(_, group){
                     $.each(Array.isArray(group.data) ? group.data : [], function(__, application){
                        if (String(application.sp_id) === appId) {
                           application.is_favourite = Number(response.is_favourite) === 1 ? 1 : 0;
                        }
                     });
                  });
                  var restoreFocus = document.activeElement === $button[0] || document.activeElement === document.body;
                  renderUserAppDirectory();
                  if (restoreFocus) {
                     var $next = $('#WebAppsTabsContent .user-app-favourite').filter(function(){
                        return String($(this).data('app-id')) === appId && $(this).is(':visible');
                     }).first();
                     if ($next.length) $next.trigger('focus');
                     else $('#WebAppsTabsHeader li.active a').trigger('focus');
                  }
               },
               error: function(){
                  userAppFavouriteFailed($button);
               }
            });
         });
         
         
         function get_specific_user_activ_session(){
         return $.ajax({
                 type: 'POST',
                 url: '../lib/q_func',
                 dataType: "json",
                 data: {admin_get_all_token_for_specific_user:""},
                 beforeSend: function(){
                   $('#app_security_session_loading').fadeIn();
                   $('#app_security_session_list').hide();                 
                 },
                 success: function (response) {
                   $('#app_security_session_loading').hide();
                   $('#app_security_session_list').fadeIn();   
                 	var list_count = 0;
                 	// if(response.length == 0){
                 	// 	$('#follo_data_list_count_text').html('');
                 	// }else{                		
                 	// 	$('#follo_data_list_count_text').html('('+response.length+')');
                 	// }
                 	var tr ='';
                     $('#security_tab_session').html('');
            $.each( response, function( i, value ) {
                tr += '<div class="follo-data">';
                var current_session = "";
                if(response[i]['current_token']=="1"){
                	current_session = '<i class="fa fa-check-circle text-primary"></i>';
                 tr += '<div class="user-data"><span class="name block capitalize-font">'+(i+1)+'. '+response[i]['device_info']+' '+current_session+'</span></div>';
                 tr += '<button class="btn btn-default pull-right btn-xs fixed-btn " ><span class="btn-text">'+userAppText(dashboardI18n.currentSession)+'</span></button>';
         
                }else{
                 tr += '<div class="user-data"><span class="name block capitalize-font">'+(i+1)+'. '+response[i]['device_info']+' '+current_session+'</span></div>';
                 tr += '<button class="btn btn-danger pull-right btn-xs fixed-btn  " onclick="sign_off_token(&quot;'+response[i]['token_id']+'&quot;);"><span class="btn-text">'+userAppText(dashboardI18n.signOff)+'</span></button>';
         
         
                }
                // tr += '<img class="user-img img-circle"  src="../img/user.png" alt="user"/>';
                //  fa-check-circle
                tr += '<div class="clearfix"></div>';
                tr += '</div>';
            });
         
                     $('#security_tab_session').html(tr);
         
             },
             error: function (xhr, error, thrown) {
             }
         });
         }
         
         
         function go_to_service_provider(sp_id, applicationWindow){
            $.ajax({
               type: 'POST',
               url: '../lib/q_func',
               dataType: 'json',
               data: {go_to_service_provider: '', sp_id: sp_id},
               success: function(response){
                  if (Number(response.status) === 1 && String(response.domain || '').trim() !== '') {
                     rememberUserApp(sp_id);
                     var destination = String(response.domain).trim();
                     if (applicationWindow && !applicationWindow.closed) {
                        applicationWindow.location.replace(destination);
                     } else {
                        window.location.assign(destination);
                     }
                     return;
                  }

                  if (applicationWindow && !applicationWindow.closed) {
                     applicationWindow.close();
                  }
                  get_specific_user_app_list();
                  $.toast().reset('all');
                  $.toast({
                     heading: '',
                     text: dashboardI18n.accessDenied,
                     position: 'bottom-center',
                     loaderBg: '#fec107',
                     icon: 'danger',
                     hideAfter: 3500,
                     stack: 6
                  });
               },
               error: function(){
                if (applicationWindow && !applicationWindow.closed) {
                   applicationWindow.close();
                }
                $.toast().reset('all');
                $.toast({
                   heading: dashboardI18n.loadFailed,
                   text: dashboardI18n.loadFailedHelp,
                   position: 'bottom-center',
                   loaderBg: '#fec107',
                   icon: 'danger',
                   hideAfter: 3500,
                   stack: 6
                });
               }
            });
         }
         
         
         function sign_off_token(token_id){
         swal({   
             title: dashboardI18n.signOffTitle,
             text: dashboardI18n.signOffText,
             type: "warning",   
             showCancelButton: true,   
             confirmButtonColor: "#DD6B55",   
             confirmButtonText: dashboardI18n.signOffButton,
             closeOnConfirm: false 
         }, function(){   
         
                 $.ajax({
                         type: 'POST',
                         url: '../lib/q_func',
                         dataType: "json",
                         data: {user_signoff_security_sessions:'',token_id:token_id},     
                         beforeSend: function(){
                         },
                         success: function (response) {
                             if (response == 1){
             					get_specific_user_activ_session();
                                 swal(dashboardI18n.signOffTitle, dashboardI18n.signOffSuccess, "success");
                             }else{
                                 swal(dashboardI18n.signOffTitle, dashboardI18n.signOffError, "error");
                             }
         
                     },
                     error: function (xhr, error, thrown) {
                     }
                 });
         });
         }

         function open_faq(){
         $('#modal_faq').modal('show');

         }
         
         var initialPasswordSetup = false;
         var myDigitalIdRecoveryStage = 'none';
         function open_change_password(type){   
		 
		 if(type==0){
			 $('#chge_pwd_logout').hide();
		 }else{
			 $('#chge_pwd_logout').show();
		 }
		 
         if ($('#modal_change_first_time_password').hasClass('in')) {
             // Modal is open
             $('#default_pwd_text').text(dashboardI18n.passwordCurrent);

            $('#modal_change_first_time_password').modal('hide');
         } else {
             // Modal is closed
             $('#default_pwd_text').text(dashboardI18n.passwordCurrent);
         }
         $('#current_password_group').toggle(!initialPasswordSetup);
         $('#change_password_current').prop('required',!initialPasswordSetup);
         $('#initial_password_setup_notice').toggle(initialPasswordSetup);
         myDigitalIdRecoveryStage='none';
         $('#mydid_password_recovery_notice,#mydid_password_otp_group').hide();
         $('.oneid-password-card--new,.oneid-password-card--confirm,#password-requirements').show();
         $('#btn_mydid_password_recovery').toggle(!initialPasswordSetup);
         $('#aria_modal_change_password').text(initialPasswordSetup?dashboardI18n.passwordInitialTitle:dashboardI18n.passwordTitle);
         $('#change_password_current').val('');
         $('#change_password_new').val('');
         $('#change_password_new_reconfirm').val('');
         $('#password_change_feedback').hide().removeClass('alert-success alert-danger alert-info');
         $('#password_change_feedback_text').text('');
         resetPasswordChecks();
         updatePasswordConfirmationState();
         $('#modal_change_password').modal('show');
         }
         
         $('#change_password_new').on('input', function() {
		    var password = $(this).val();

		    // Check each requirement
		    $('#p_length').text((password.length >= 12 ? '✅ ' : '❌ ') + dashboardI18n.passwordLength);
		    $('#p_lowercase').text((/[a-z]/.test(password) ? '✅ ' : '❌ ') + dashboardI18n.passwordLowercase);
		    $('#p_uppercase').text((/[A-Z]/.test(password) ? '✅ ' : '❌ ') + dashboardI18n.passwordUppercase);
		    $('#p_number').text((/\d/.test(password) ? '✅ ' : '❌ ') + dashboardI18n.passwordNumber);
		    $('#p_special').text((/[\W_]/.test(password) ? '✅ ' : '❌ ') + dashboardI18n.passwordSpecial);
		    updatePasswordConfirmationState();
		});

         $('#change_password_new_reconfirm').on('input', updatePasswordConfirmationState);

         function checkPasswordStrength(password) {
		    var strongRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{12,}$/;

		    if (password.length === 0) {
		        return { message: '', color: '' };
		    } else if (!strongRegex.test(password)) {
		        return { status:0,message: dashboardI18n.passwordWeak, color: 'red' };
		    } else {
		        return { status:1,message: dashboardI18n.passwordStrong, color: 'green' };
		    }
		}

		function resetPasswordChecks() {
		    $('#p_length').text('❌ ' + dashboardI18n.passwordLength);
		    $('#p_lowercase').text('❌ ' + dashboardI18n.passwordLowercase);
		    $('#p_uppercase').text('❌ ' + dashboardI18n.passwordUppercase);
		    $('#p_number').text('❌ ' + dashboardI18n.passwordNumber);
		    $('#p_special').text('❌ ' + dashboardI18n.passwordSpecial);
		}

         function updatePasswordConfirmationState(){
            var password=$('#change_password_new').val();
            var confirmation=$('#change_password_new_reconfirm').val();
            var status=$('#password_confirmation_status');
            var input=$('#change_password_new_reconfirm');
            var passwordStage=myDigitalIdRecoveryStage!=='otp'&&$('.oneid-password-card--confirm').css('display')!=='none';
            var matches=password!==''&&confirmation!==''&&password===confirmation;
            status.removeClass('is-match is-mismatch').text('❌ '+dashboardI18n.passwordMatch);
            input.removeClass('is-match is-mismatch').removeAttr('aria-invalid');
            if(passwordStage){
               status.addClass(matches?'is-match':'is-mismatch').text((matches?'✅ ':'❌ ')+dashboardI18n.passwordMatch);
            }
            if(passwordStage&&confirmation!==''){
               input.addClass(matches?'is-match':'is-mismatch').attr('aria-invalid',matches?'false':'true');
            }
            var strong=checkPasswordStrength(password).status===1;
            $('#btn_change_password_submit').prop('disabled',passwordChangeSubmitting||(passwordStage&&(!strong||!matches)));
         }

         var passwordChangeSubmitting = false;
         function setPasswordChangeSubmitting(submitting){
            passwordChangeSubmitting = submitting;
            $('#btn_change_password_submit').attr('aria-busy', submitting ? 'true' : 'false');
            $('#change_password_submit_label').text(submitting ? dashboardI18n.passwordChanging : dashboardI18n.passwordChange);
            $('#change_password_current, #change_password_new, #change_password_new_reconfirm').prop('disabled', submitting);
            updatePasswordConfirmationState();
         }
         function passwordChangeFeedback(response, success){
            var code=response&&response.code?response.code:'UC1_RESPONSE_INVALID';
            var reference=response&&response.correlation_id?response.correlation_id:'Unavailable';
            return (response&&response.localized_msg?response.localized_msg:(response&&response.msg?response.msg:(success?dashboardI18n.passwordSuccess:dashboardI18n.passwordFailed)))+' '+dashboardI18n.feedbackCode+': '+code+'. '+dashboardI18n.feedbackReference+': '+reference+'.';
         }
         function showPasswordChangeFeedback(message,type){
            var panel=$('#password_change_feedback');panel.removeClass('alert-success alert-danger alert-info').addClass(type==='success'?'alert-success':(type==='info'?'alert-info':'alert-danger'));
            $('#password_change_feedback_text').text(message);panel.show();
         }
         function copyPasswordChangeFeedback(){
            var text=$('#password_change_feedback_text').text();if(!text){return;}
            if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(text).then(function(){$('#password_change_copy_button').text(dashboardI18n.passwordCopied);});return;}
            var area=$('<textarea>').val(text).css({position:'fixed',left:'-9999px'}).appendTo('body');area[0].select();document.execCommand('copy');area.remove();$('#password_change_copy_button').text(dashboardI18n.passwordCopied);
         }

         var form_change_password = $('#form_change_password');
         $('#btn_mydid_password_recovery').on('click',function(){
            if(passwordChangeSubmitting){return;}
            $.ajax({type:'POST',url:'../lib/q_func',dataType:'json',data:{action_mydigitalid_password_recovery_request:''},beforeSend:function(){setPasswordChangeSubmitting(true);},success:function(response){
               myDigitalIdRecoveryStage='otp';
               $('#current_password_group,#password-requirements').hide();
               $('#change_password_new,#change_password_new_reconfirm').closest('.oneid-password-card').hide();
               $('#mydid_password_recovery_notice').text(dashboardI18n.passwordOtpSent).show();
               $('#mydid_password_otp_group').show();$('#mydid_password_otp').val('').focus();
               $('#change_password_submit_label').text(dashboardI18n.passwordOtpVerify);
            },error:function(){showPasswordChangeFeedback(dashboardI18n.passwordRecoveryFailed,'error');},complete:function(){setPasswordChangeSubmitting(false);if(myDigitalIdRecoveryStage==='otp')$('#change_password_submit_label').text(dashboardI18n.passwordOtpVerify);}});
         });
         form_change_password.on('submit', function(ev){
             ev.preventDefault();
             if(passwordChangeSubmitting){return;}

             if(myDigitalIdRecoveryStage==='otp'){
                var otp=$('#mydid_password_otp').val().replace(/\D/g,'');
                if(otp.length!==6){showPasswordChangeFeedback(dashboardI18n.passwordOtpInvalid,'error');return;}
                $.ajax({type:'POST',url:'../lib/q_func',dataType:'json',data:{action_mydigitalid_password_recovery_verify:'',otp_id:otp},beforeSend:function(){setPasswordChangeSubmitting(true);},success:function(response){if(response.result==='true'&&response.reset_required){myDigitalIdRecoveryStage='reset';$('#mydid_password_otp_group').hide();$('#mydid_password_recovery_notice').text(response.msg).show();$('#change_password_new,#change_password_new_reconfirm').closest('.oneid-password-card').show();$('#password-requirements').show();$('#change_password_submit_label').text(dashboardI18n.passwordReset);$('#change_password_new').focus();}else{showPasswordChangeFeedback(response.msg||dashboardI18n.passwordOtpInvalid,'error');}},error:function(){showPasswordChangeFeedback(dashboardI18n.passwordRecoveryFailed,'error');},complete:function(){setPasswordChangeSubmitting(false);if(myDigitalIdRecoveryStage==='otp')$('#change_password_submit_label').text(dashboardI18n.passwordOtpVerify);else if(myDigitalIdRecoveryStage==='reset')$('#change_password_submit_label').text(dashboardI18n.passwordReset);}});return;
             }

              var password = $('#change_password_new').val();
              var password2 = $('#change_password_new_reconfirm').val();
			  var checking = checkPasswordStrength(password);
			  if(checking.status == 0){
				showPasswordChangeFeedback(checking.message,'error');
			  	return;
			  }
			  if(password != password2){
				showPasswordChangeFeedback(dashboardI18n.passwordMismatch,'error');
			  	return;
			  }


             var data = $('#form_change_password').serializeArray();
             data.push({name: myDigitalIdRecoveryStage==='reset'?'action_mydigitalid_password_recovery_reset':(initialPasswordSetup?'action_set_initial_password':'action_change_password'), value: ''});
                 $.ajax({
                         type: 'POST',
                         url: '../lib/q_func',
                         dataType: "json",
                         data:data,
                         beforeSend: function(){
                           setPasswordChangeSubmitting(true);
                         },
                         success: function (response) {
                             if (response['status'] == 1 || response['result'] === 'true'){
                                if(response.csrf_token){$.ajaxSetup({headers:{'X-CSRF-Token':response.csrf_token}});}
								showPasswordChangeFeedback(passwordChangeFeedback(response,true),'success');
								$('#change_password_current, #change_password_new, #change_password_new_reconfirm').val('');
									if((response.reauthentication_required||myDigitalIdRecoveryStage==='reset')&&response.redirect_uri){showPasswordChangeFeedback(passwordChangeFeedback(response,true)+'\n'+dashboardI18n.passwordReauth,'success');setTimeout(function(){window.location.href=response.redirect_uri;},2500);}
								                             }else{
								showPasswordChangeFeedback(passwordChangeFeedback(response,false),'error');
                             }
         
                     },
                     error: function (xhr, error, thrown) {
                        if(initialPasswordSetup&&xhr.responseJSON&&xhr.responseJSON.code==='UC6_INITIAL_SETUP_GRANT_INVALID'){
                           showPasswordChangeFeedback(xhr.responseJSON.msg||dashboardI18n.passwordMyDigitalIdReauth,'error');
                           setTimeout(function(){window.location.href=xhr.responseJSON.redirect_uri||'../';},2500);
                           return;
                        }
                        showPasswordChangeFeedback(dashboardI18n.passwordHttpFailed.replace('{status}',xhr.status),'error');
                     },
                     complete: function(){
                        setPasswordChangeSubmitting(false);
                     }
                 });
         });

        
         
         function countdownTimeStart(){
         
         var countDownDate = new Date("<?php echo date('M d, Y  H:i:s', strtotime('+30 minute', strtotime(LOCAL_COOKIES_HANDLER()->sso_dt)));?>").getTime();
         var x = setInterval(function() {
         
         // Get todays date and time
         var now = new Date().getTime();
         
         // Find the distance between now an the count down date
         var distance = countDownDate - now;
         
         // Time calculations for days, hours, minutes and seconds
         var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
         var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
         var seconds = Math.floor((distance % (1000 * 60)) / 1000);
         
         // Output the result in an element with id="demo"
         document.getElementById("demo").innerHTML = hours + "h "
         + minutes + "m " + seconds + "s ";
         
         // If the count down is over, write some text 
         if (distance < 0) {
         clearInterval(x);
         document.getElementById("demo").innerHTML = "EXPIRED";
         }
         }, 1000);
         }
         
        /*  window.onload = function () {
         countdownTimeStart()
         }; */
         
          // logout
         function logout() {
          window.location.href = "logout";
        }
		
		
		
        function startTokenRefresh(){
              setInterval(function() {
                  refresh_tokens();
              }, 300000);
        }

        function refresh_tokens() {
          $.ajax({
              type: 'POST',
              url: '../lib/q_func',
              dataType: "json",
              data: {
                  update_specific_token_datetime:"1"
              },
              success: function(response) {
                  oneidHeartbeatLastWarningAt = 0;
              },
              error: function(xhr, status, error) {
                  var code = xhr.responseJSON && xhr.responseJSON.code ? xhr.responseJSON.code : '';
                  var terminalCodes = ['USER_SESSION_EXPIRED','SSO_TOKEN_REVOKED','ACCOUNT_INACTIVE'];
                  if (window.OneIdUserSession && typeof window.OneIdUserSession.handleExternalError === 'function') {
                     window.OneIdUserSession.handleExternalError(xhr.status, code);
                  } else if (terminalCodes.indexOf(code) !== -1) {
                     window.location.replace(<?=json_encode(APP_URL.'/')?>);
                  }
                  if (terminalCodes.indexOf(code) === -1 && Date.now() - oneidHeartbeatLastWarningAt > 60000) {
                     oneidHeartbeatLastWarningAt = Date.now();
                     $.toast().reset('all');
                     $.toast({heading:'OneID',text:dashboardI18n.sessionStatusUnavailable,position:'bottom-center',loaderBg:'#fec107',icon:'warning',hideAfter:5000,stack:1});
                  }
              }
          });
      }
      </script>
      <style>
      .user-app-panel {
        min-height: 620px;
        padding: 30px;
        background: #f7f9fc;
      }

      .user-app-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        padding-bottom: 22px;
        margin-bottom: 18px;
        border-bottom: 1px solid #e3e8ef;
      }

      .user-app-eyebrow {
        display: block;
        margin-bottom: 5px;
        color: #168fcb;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .11em;
        text-transform: uppercase;
      }

      .user-app-title {
        margin: 0 0 7px;
        color: #1f2937;
        font-size: 24px;
        font-weight: 600;
        line-height: 1.25;
      }

      .user-app-intro {
        max-width: 620px;
        margin: 0;
        color: #687386;
        font-size: 14px;
        line-height: 1.6;
      }

      .user-app-header-actions {
        display: flex;
        align-items: stretch;
        flex: 0 0 auto;
        gap: 9px;
      }

      .user-app-summary {
        display: flex;
        align-items: stretch;
        gap: 7px;
      }

      .user-app-count {
        min-width: 102px;
        padding: 10px 14px;
        border: 1px solid #cfe8f6;
        border-radius: 7px;
        background: #eef8fd;
        text-align: right;
      }

      .user-app-count span,
      .user-app-count strong {
        display: block;
      }

      .user-app-count.is-sso {
        border-color: #cbe9d8;
        background: #edf9f2;
      }

      .user-app-count.is-sso strong {
        color: #22844f;
      }

      .user-app-count.is-non-sso {
        border-color: #f2dfbd;
        background: #fff8eb;
      }

      .user-app-count.is-non-sso strong {
        color: #a86c15;
      }

      .user-app-count span {
        margin-bottom: 2px;
        color: #627386;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: .06em;
        text-transform: uppercase;
      }

      .user-app-count strong {
        color: #087eaf;
        font-size: 17px;
        font-weight: 700;
      }

      .user-app-refresh {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        padding: 0;
        border: 1px solid #dce4ec;
        border-radius: 7px;
        background: #fff;
        color: #168fcb;
        font-size: 14px;
        transition: background .18s ease, border-color .18s ease;
      }

      .user-app-refresh:hover,
      .user-app-refresh:focus {
        border-color: #b9ddeb;
        background: #eef8fd;
        color: #087eaf;
      }

      .user-app-category-card,
      .user-app-directory,
      .user-app-state {
        border: 1px solid #e1e6ed;
        background: #fff;
        box-shadow: 0 2px 7px rgba(31, 41, 55, .04);
      }

      .user-app-category-card {
        padding: 18px 20px 14px;
        border-radius: 8px 8px 0 0;
      }

      .user-app-category-card { position:relative; }
      .user-app-category-title-row { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:10px; }
      .user-app-category-title-row h5 { margin:0; }
      body .user-app-recent__trigger { display:inline-flex; align-items:center; justify-content:center; box-sizing:border-box; flex:0 0 26px; width:26px; min-width:26px!important; max-width:26px; height:26px; min-height:26px!important; max-height:26px; padding:0; border:1px solid #cfe1e9; border-radius:6px; background:#f5fafc; color:#168fcb; font-size:11px!important; line-height:1; }
      .user-app-recent__trigger[hidden] { display:none!important; }
      .user-app-recent__trigger:hover,.user-app-recent__trigger:focus,.user-app-recent__trigger[aria-expanded="true"] { border-color:#75bdd5; background:#eaf7fc; color:#087ba7; outline:0; }
      .user-app-recent { position:absolute; z-index:20; top:46px; right:20px; width:430px; max-width:calc(100vw - 40px); padding:0; border:1px solid #cfe0e8; border-radius:10px; background:#fff; box-shadow:0 13px 32px rgba(25,66,86,.20); }
      .user-app-recent[hidden] { display:none!important; }
      .user-app-recent__head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:11px 12px; border-bottom:1px solid #e5edf1; color:#294b5d; font-size:11px; font-weight:700; }
      .user-app-recent__head span { display:flex; align-items:center; gap:7px; }
      .user-app-recent__head i { color:#168fcb; font-size:12px; }
      body .user-app-recent__close { display:inline-flex; align-items:center; justify-content:center; box-sizing:border-box; width:25px; min-width:25px!important; height:25px; min-height:25px!important; padding:0; border:0; border-radius:6px; background:#eef7fa; color:#537281; font-size:17px!important; line-height:1; }
      .user-app-recent__close:hover,.user-app-recent__close:focus { background:#dff1f7; color:#174f68; outline:0; }
      .user-app-recent__grid { display:grid; grid-template-columns:1fr; gap:6px; padding:9px; }
      .user-app-recent__item { position:relative; display:grid; grid-template-columns:34px minmax(0,1fr); grid-template-rows:auto auto; align-items:center; gap:2px 9px; width:100%; min-height:50px; padding:6px 25px 6px 7px; border:1px solid transparent; border-radius:8px; background:#f8fbfc; color:#24475a; text-align:left; }
      .user-app-recent__item:hover,.user-app-recent__item:focus { border-color:#8ecde2; background:#eef9fd; outline:0; }
      .user-app-recent__item > img { grid-row:1 / 3; width:34px; height:34px; border:1px solid #dce4e9; border-radius:7px; object-fit:cover; }
      .user-app-recent__name { overflow:hidden; color:#243f50; font-size:11px; font-weight:700; line-height:1.25; text-overflow:ellipsis; white-space:nowrap; }
      .user-app-recent__meta { grid-column:2; display:flex; align-items:center; min-width:0; gap:5px; overflow:hidden; white-space:nowrap; }
      .user-app-recent__item .user-app-health { flex:0 0 auto; font-size:8px; line-height:1.1; }
      .user-app-recent__separator { flex:0 0 auto; color:#a2b1b9; font-size:9px; }
      .user-app-recent__time { min-width:0; overflow:hidden; color:#748995; font-size:8px; font-weight:500; line-height:1.2; text-overflow:ellipsis; white-space:nowrap; }
      .user-app-recent__time i { margin-right:4px; color:#7895a4; }
      .user-app-recent__arrow { position:absolute; right:9px; top:50%; color:#8aa0ad; font-size:13px; transform:translateY(-50%); }
      .user-app-recent__footer { display:flex; justify-content:flex-end; padding:8px 9px 9px; border-top:1px solid #e7eef2; }
      body .user-app-recent__footer button { display:inline-flex; align-items:center; gap:6px; min-height:30px!important; padding:5px 9px; border:1px solid #d8e4ea; border-radius:7px; background:#fff; color:#607985; font-size:9px!important; font-weight:700; }
      .user-app-recent__footer button:hover,.user-app-recent__footer button:focus { border-color:#e3aeb5; background:#fff6f7; color:#a83d4b; outline:0; }


      .user-app-category-card h5 {
        margin: 0 0 4px;
        color: #29384b;
        font-size: 14px;
        font-weight: 600;
      }

      .user-app-category-card p {
        margin: 0 0 15px;
        color: #7a8696;
        font-size: 12px;
        line-height: 1.45;
      }

      .user-app-search {
        position: relative;
        display: flex;
        align-items: center;
        width: 100%;
        margin-bottom: 14px;
      }

      .user-app-search > i {
        position: absolute;
        left: 13px;
        z-index: 1;
        color: #168fcb;
        pointer-events: none;
      }

      .user-app-search input {
        width: 100%;
        height: 40px;
        padding: 8px 42px 8px 38px;
        border: 1px solid #dce4ec;
        border-radius: 7px;
        background: #fbfcfd;
        color: #344358;
        font-size: 12px;
        outline: none;
        transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
      }

      .user-app-search input:focus {
        border-color: #41b8e3;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(17, 168, 223, .11);
      }

      .user-app-search button {
        position: absolute;
        right: 5px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 30px;
        padding: 0;
        border: 0;
        border-radius: 5px;
        background: transparent;
        color: #8995a5;
      }

      .user-app-search button:hover,
      .user-app-search button:focus {
        background: #edf4f8;
        color: #168fcb;
      }

      .user-app-search-suggestions {
        position: absolute;
        z-index: 30;
        top: calc(100% + 5px);
        left: 0;
        right: 0;
        overflow: hidden;
        border: 1px solid #cfe2ed;
        border-radius: 9px;
        background: #fff;
        box-shadow: 0 12px 28px rgba(33, 58, 82, .16);
      }

      .user-app-search-suggestions__label {
        padding: 8px 12px 6px;
        color: #708397;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
      }

      .user-app-search .user-app-search-suggestion {
        position: relative;
        right: auto;
        display: grid;
        grid-template-columns: 32px minmax(0, 1fr) 18px;
        width: 100%;
        height: auto;
        min-height: 48px;
        gap: 9px;
        padding: 7px 11px;
        border-radius: 0;
        border-top: 1px solid #edf2f5;
        color: #173d57;
        text-align: left;
      }

      .user-app-search .user-app-search-suggestion:hover,
      .user-app-search .user-app-search-suggestion:focus {
        background: #eef8fc;
        outline: 0;
      }

      .user-app-search-suggestion img { width:32px; height:32px; border:1px solid #d4dee5; border-radius:7px; object-fit:cover; }
      .user-app-search-suggestion span { min-width:0; }
      .user-app-search-suggestion strong,
      .user-app-search-suggestion small { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
      .user-app-search-suggestion strong { font-size:11px; line-height:16px; }
      .user-app-search-suggestion small { color:#73869a; font-size:10px; line-height:15px; }
      .user-app-search-suggestion > i { color:#7ea0b6; }

      .user-app-skeleton {
        overflow: hidden;
        border: 1px solid #e0e7ed;
        border-radius: 0 0 8px 8px;
        background: #fff;
      }

      .user-app-skeleton__row {
        display: grid;
        grid-template-columns: 28px 50px minmax(0, 1fr) 112px;
        align-items: center;
        gap: 14px;
        min-height: 82px;
        padding: 14px 18px;
        border-bottom: 1px solid #edf1f4;
      }

      .user-app-skeleton__row:last-child { border-bottom:0; }
      .user-app-skeleton__index,
      .user-app-skeleton__image,
      .user-app-skeleton__copy i,
      .user-app-skeleton__action {
        position: relative;
        overflow: hidden;
        display: block;
        background: #e8eef2;
      }
      .user-app-skeleton__index { width:22px; height:22px; border-radius:50%; }
      .user-app-skeleton__image { width:50px; height:50px; border-radius:9px; }
      .user-app-skeleton__copy i { width:min(320px, 72%); height:12px; margin-bottom:9px; border-radius:5px; }
      .user-app-skeleton__copy i:last-child { width:min(470px, 92%); height:9px; margin-bottom:0; }
      .user-app-skeleton__action { width:112px; height:36px; border-radius:7px; }
      .user-app-skeleton__index::after,
      .user-app-skeleton__image::after,
      .user-app-skeleton__copy i::after,
      .user-app-skeleton__action::after {
        content:"";
        position:absolute;
        inset:0;
        transform:translateX(-100%);
        background:linear-gradient(90deg, transparent, rgba(255,255,255,.75), transparent);
        animation:oneid-skeleton-shimmer 1.25s ease-in-out infinite;
      }
      @keyframes oneid-skeleton-shimmer { to { transform:translateX(100%); } }

      #WebAppsTabsHeader {
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
        max-width: 100%;
        min-height: 54px;
        align-items: flex-start;
        scrollbar-width: thin;
        gap: 8px;
        margin: 0;
        padding: 3px 2px 12px;
        border: 0;
      }

      #WebAppsTabsHeader.is-search-results {
        min-height: 0;
        padding: 0;
        overflow: visible;
      }
      #WebAppsTabsHeader.is-search-results .user-app-results {
        padding: 0;
        line-height: 20px;
      }

      #WebAppsTabsHeader > li {
        flex: 0 0 auto;
        white-space: nowrap;
        float: none;
        margin: 0;
      }

      #WebAppsTabsHeader > li > a {
        display: inline-flex;
        align-items: center;
        min-height: 34px;
        padding: 7px 11px;
        border: 1px solid #e2e7ed;
        border-radius: 20px;
        background: #f7f9fb;
        color: #657286;
        font-size: 11px;
        font-weight: 500;
        gap: 7px;
        line-height: 1.2;
      }

      #WebAppsTabsHeader > li > a strong {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 5px;
        border-radius: 10px;
        background: #e7edf3;
        color: #667589;
        font-size: 9px;
        font-weight: 700;
      }

      #WebAppsTabsHeader > li.active > a,
      #WebAppsTabsHeader > li.active > a:hover,
      #WebAppsTabsHeader > li.active > a:focus {
        border-color: #11a8df;
        background: #11a8df;
        color: #fff;
      }

      #WebAppsTabsHeader > li.active > a strong {
        background: rgba(255, 255, 255, .22);
        color: #fff;
      }

      #WebAppsTabsHeader > li.is-favourite-tab > a {
        min-width: 46px;
        justify-content: center;
        border-color: #ead9a9;
        background: #fffbef;
        color: #a87809;
      }

      #WebAppsTabsHeader > li.is-favourite-tab.active > a,
      #WebAppsTabsHeader > li.is-favourite-tab.active > a:hover,
      #WebAppsTabsHeader > li.is-favourite-tab.active > a:focus {
        border-color: #e8ad25;
        background: #e8ad25;
        color: #fff;
      }

      #WebAppsTabsHeader > li.is-non-sso-tab > a {
        border-color: #e3d8f2;
        background: #f8f4fc;
        color: #72538f;
      }

      #WebAppsTabsHeader > li.is-non-sso-tab > a strong {
        background: #eee5f6;
        color: #72538f;
      }

      #WebAppsTabsHeader > li.is-non-sso-tab.active > a,
      #WebAppsTabsHeader > li.is-non-sso-tab.active > a:hover,
      #WebAppsTabsHeader > li.is-non-sso-tab.active > a:focus {
        border-color: #7f5aa3;
        background: #7f5aa3;
        color: #fff;
      }

      #WebAppsTabsHeader > li.is-non-sso-tab.active > a strong {
        background: rgba(255, 255, 255, .22);
        color: #fff;
      }

      .user-app-directory {
        overflow: hidden;
        border-top: 0;
        border-radius: 0 0 8px 8px;
      }

      .user-app-list {
        padding: 0;
      }

      .user-app-card {
        display: grid;
        grid-template-columns: 26px 54px minmax(0, 1fr) auto;
        align-items: start;
        gap: 13px;
        padding: 16px 20px;
        border-bottom: 1px solid #edf0f4;
        background: #fff;
      }

      .user-app-card:last-child {
        border-bottom: 0;
      }

      .user-app-card:hover {
        background: #fbfdff;
      }

      .user-app-index {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        margin-top: 16px;
        border-radius: 50%;
        background: #eaf6fc;
        color: #168fcb;
        font-size: 9px;
        font-weight: 700;
      }

      .user-app-image {
        width: 54px;
        height: 54px;
        overflow: hidden;
        border: 1px solid #e1e6eb;
        border-radius: 10px;
        background: #f4f6f8;
      }

      .user-app-image img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
      }

      .user-app-content {
        min-width: 0;
        padding-top: 1px;
      }

      .user-app-name {
        display: flex;
        align-items: center;
        min-width: 0;
        gap: 8px;
        margin-bottom: 3px;
      }

      .user-app-name strong {
        min-width: 0;
        overflow: hidden;
        color: #2f3e52;
        font-size: 13px;
        font-weight: 600;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      .user-app-access {
        flex: 0 0 auto;
        padding: 3px 7px;
        border-radius: 20px;
        background: #e7f7ee;
        color: #22844f;
        font-size: 8px;
        font-weight: 700;
        letter-spacing: .035em;
        line-height: 1.35;
        text-transform: uppercase;
      }

      .user-app-access.is-direct {
        background: #fff3df;
        color: #a86c15;
      }

      .user-app-health {
        display: inline-flex;
        align-items: center;
        flex: 0 0 auto;
        gap: 4px;
        color: #647985;
        font-size: 8px;
        font-weight: 700;
        line-height: 1.35;
        white-space: nowrap;
      }

      .user-app-health i {
        display: inline-block;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #9aabb4;
      }

      .user-app-health em {
        color: #7a8e9a;
        font-size: 8px;
        font-style: normal;
        font-weight: 500;
      }

      .user-app-health em::before { content: "\00b7"; margin: 0 3px; }

      .user-app-health.is-available { color:#21824e; }
      .user-app-health.is-available i { background:#24a861; }
      .user-app-health.is-slow { color:#9a6900; }
      .user-app-health.is-slow i { background:#e5a60b; }
      .user-app-health.is-maintenance { color:#9a5a12; }
      .user-app-health.is-maintenance i { background:#ef811a; }
      .user-app-health.is-unavailable { color:#b23d4b; }
      .user-app-health.is-unavailable i { background:#d94b5b; }


      .user-app-content p {
        display: block;
        margin: 0;
        overflow: hidden;
        color: #758193;
        font-size: 11px;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      .user-app-actions {
        display: flex;
        align-items: center;
        gap: 7px;
        margin-top: 10px;
      }

      .user-app-favourite {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        padding: 0;
        border: 1px solid #dce4ec;
        border-radius: 6px;
        background: #fff;
        color: #a6b0bd;
        transition: color .18s ease, border-color .18s ease, background .18s ease;
      }

      .user-app-favourite:hover,
      .user-app-favourite:focus,
      .user-app-favourite.is-selected {
        border-color: #e6b745;
        background: #fff9e9;
        color: #e2a919;
      }

      .user-app-favourite.is-saving {
        cursor: wait;
        opacity: .6;
      }

      .user-app-open {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 74px;
        height: 34px;
        margin-top: 0;
        padding: 0 12px;
        border: 1px solid #11a8df;
        border-radius: 6px;
        background: #11a8df;
        color: #fff;
        font-size: 10px;
        font-weight: 600;
        gap: 7px;
      }

      .user-app-open:hover,
      .user-app-open:focus {
        border-color: #0c91c2;
        background: #0c91c2;
        color: #fff;
      }

      .user-app-open.is-direct {
        border-color: #e0a74e;
        background: #fff;
        color: #a86c15;
      }

      .user-app-open.is-direct:hover,
      .user-app-open.is-direct:focus {
        background: #fff6e8;
        color: #8d5a0f;
      }

      .user-app-open.is-disabled,
      .user-app-open:disabled,
      .user-app-recent__item:disabled {
        cursor: not-allowed;
        filter: grayscale(.35);
        opacity: .58;
      }

      .user-app-open.is-disabled:hover,
      .user-app-open:disabled:hover { border-color:#dce4ec; background:#eef2f4; color:#72818d; }

      .user-app-state,
      .user-app-category-empty {
        padding: 38px 20px;
        color: #6f7c8c;
        text-align: center;
      }

      .user-app-state {
        border-top: 0;
        border-radius: 0 0 8px 8px;
      }

      .user-app-state span,
      .user-app-state strong,
      .user-app-state small {
        display: block;
      }

      .user-app-state > span {
        margin-bottom: 9px;
        color: #27a8d8;
        font-size: 18px;
      }

      .user-app-state strong {
        margin-bottom: 4px;
        color: #425166;
        font-size: 13px;
      }

      .user-app-state small {
        color: #8994a2;
        font-size: 11px;
      }

      .user-app-state.is-error > span {
        color: #d46b62;
      }

      .user-app-category-empty {
        font-size: 11px;
      }

      .user-app-category-empty i,
      .user-app-category-empty span {
        display: block;
      }

      .user-app-category-empty i {
        margin-bottom: 8px;
        color: #91a0b1;
        font-size: 18px;
      }

      .user-app-search-empty small { display:block; margin:7px auto 0; color:#718398; font-size:11px; }
      .user-app-search-empty__choices { display:flex; flex-wrap:wrap; justify-content:center; gap:7px; margin-top:12px; }
      .user-app-search-empty__choices button { min-height:32px; padding:5px 11px; border:1px solid #cde1ec; border-radius:16px; background:#f4fafc; color:#12698f; font-size:10px; font-weight:600; }
      .user-app-search-empty__choices button:hover,
      .user-app-search-empty__choices button:focus { border-color:#22a8db; background:#e8f7fc; outline:0; }

      @media (max-width: 767px) {
        .user-app-panel {
          padding: 20px 15px;
        }

        body .user-app-recent__trigger { flex-basis:28px; width:28px; min-width:28px!important; max-width:28px; height:28px; min-height:28px!important; max-height:28px; }
        .user-app-recent { left:10px; right:10px; top:48px; width:auto; max-width:none; }
        .user-app-recent__head { padding:10px 11px; }
        .user-app-recent__grid { grid-template-columns:1fr; padding:8px; }
        .user-app-recent__item { min-height:50px; }
        .user-app-search-suggestions { left:-2px; right:-2px; }
        .user-app-search .user-app-search-suggestion { min-height:52px; padding:8px 10px; }
        .user-app-skeleton__row { grid-template-columns:44px minmax(0,1fr) 72px; min-height:76px; gap:10px; padding:12px 14px; }
        .user-app-skeleton__index { display:none; }
        .user-app-skeleton__image { width:44px; height:44px; }
        .user-app-skeleton__action { width:72px; height:34px; }

        .user-app-header {
          display: block;
        }

        .user-app-header-actions {
          width: 100%;
          flex-wrap: wrap;
          margin-top: 16px;
        }

        .user-app-summary {
          flex: 1 1 auto;
          flex-wrap: wrap;
        }

        .user-app-count {
          flex: 1 1 88px;
          min-width: 88px;
        }

        .user-app-count {
          text-align: left;
        }

        .user-app-card {
          grid-template-columns: 22px 46px minmax(0, 1fr) auto;
          gap: 9px;
          padding: 14px;
        }

        .user-app-image {
          width: 46px;
          height: 46px;
        }

        .user-app-name {
          align-items: flex-start;
          flex-direction: column;
          gap: 4px;
        }

        .user-app-index {
          margin-top: 12px;
        }

        .user-app-open {
          min-width: 36px;
          width: 36px;
          padding: 0;
          margin-top: 0;
        }

        .user-app-open span {
          display: none;
        }

        .user-app-actions {
          align-items: flex-end;
          flex-direction: column;
          gap: 5px;
          margin-top: 4px;
        }

        .user-app-favourite,
        .user-app-open {
          width: 34px;
          min-width: 34px;
          height: 32px;
        }
      }

      @media (prefers-reduced-motion: reduce) {
        .user-app-skeleton__index::after,
        .user-app-skeleton__image::after,
        .user-app-skeleton__copy i::after,
        .user-app-skeleton__action::after { animation:none; }
      }

      .pills-struct.vertical-pills { display:flex; gap:20px; }
      .pills-struct.vertical-pills > .nav { flex: 0 0 100%; }
.pills-struct.vertical-pills > .tab-content { flex: 1 1 75%; }

      .ver-nav-pills > li { float:none; }
      .ver-nav-pills > li > a { display:block; }


      /* Yellow pill with black text */
      .pill-yellow > a{
        background:#ffeb3b !important;
        color:#000 !important;
        display:block;            /* make bg fill the pill */
        border-radius:4px;
      }
      .pill-yellow > a:hover,
      .pill-yellow > a:focus {
        background:#fdd835 !important; /* slightly darker on hover */
        color:#000 !important;
      }
      .pill-yellow.active > a {
        background:#fbc02d !important; /* active state */
        color:#000 !important;
      }





      /* Parent: 3-col layout (thumb | text | button) */
.follo-data{
  display:grid !important;
  grid-template-columns: 65px 1fr auto !important;
  align-items:center !important;            /* centers thumb + button */
  column-gap:12px !important;
  padding:12px 16px !important;
}

/* Text column fills row height and centers the title line */
/* text column: let content wrap freely */
/* precise-centre title; description grows underneath */
.follo-data .user-data:nth-child(2){
  display:grid !important;
  grid-template-rows: 1fr auto auto 1fr;   /* spacer | title | desc | spacer */
}
.follo-data .name{ grid-row:2; align-self:center; margin:0 0 4px 0; }
.follo-data .time{ grid-row:3; white-space:normal; overflow:visible; text-overflow:clip; }


/* title line */
.follo-data .name{
  margin:0 0 4px 0;
  white-space:normal;                /* no truncation */
  overflow:visible;
  text-overflow:clip;
}

/* description: unwrap, no clamp/ellipsis */
.follo-data .time{
  text-align: justify !important;
  text-justify: inter-word;   /* legacy IE/old Edge; harmless elsewhere */
  hyphens: auto;              /* nicer breaks on long words */
  word-break: break-word;     /* you already have this */
      width: auto !important;
  white-space:normal !important;
  overflow:visible !important;
  text-overflow:clip !important;
  display:block !important;          /* remove any -webkit-box/clamp from earlier */
  -webkit-line-clamp:unset !important;
  -webkit-box-orient:unset !important;
  word-break:break-word;             /* prevent layout blow-up on super long words/URLs */
}

/* keep button centered on the right */
.follo-data .btn{
  align-self:center !important;
  justify-self:end !important;
  position:static !important;
  float:none !important;
  margin:0 !important;
}


/* Thumb */
.follo-data .user-data:first-child img{
  width:65px; height:65px; object-fit:cover; display:block;
}

#user_photos {
  width: 100%;
  height: 100%;
  margin: 0;
  border: 0;
  border-radius: 50%;
  box-shadow: none;
  object-fit: cover;
}
.profile-box .profile-info .profile-img-wrap {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 108px;
  height: 108px;
  padding: 4px;
  margin: -54px auto 0;
  overflow: visible;
  border: 4px solid #ff6028;
  border-radius: 50%;
  background: #fff !important;
  box-shadow: 0 8px 22px rgba(28, 55, 76, .22);
  animation: oneid-user-avatar-connected 16s cubic-bezier(.4, 0, .2, 1) infinite;
}

@keyframes oneid-user-avatar-connected {
  0%, 31%, 48%, 100% { border-color: #ff6028; box-shadow: 0 8px 22px rgba(28, 55, 76, .22), 0 0 0 0 rgba(127, 90, 163, 0); }
  35% { border-color: #ef8a53; box-shadow: 0 8px 22px rgba(28, 55, 76, .22), 0 0 0 4px rgba(127, 90, 163, .13), 0 0 20px rgba(255, 244, 205, .25); }
  39% { border-color: #d9b9f2; box-shadow: 0 8px 22px rgba(28, 55, 76, .22), 0 0 0 9px rgba(127, 90, 163, .12), 0 0 38px rgba(255, 244, 205, .44); }
  43% { border-color: #e4caf7; box-shadow: 0 8px 22px rgba(28, 55, 76, .22), 0 0 0 7px rgba(127, 90, 163, .1), 0 0 31px rgba(255, 244, 205, .36); }
  46% { border-color: #ef8a53; box-shadow: 0 8px 22px rgba(28, 55, 76, .22), 0 0 0 3px rgba(127, 90, 163, .07), 0 0 17px rgba(255, 244, 205, .17); }
}

.profile-img-wrap img {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
}

.oneid-user-profile-online {
  position: absolute;
  right: 2px;
  bottom: 7px;
  width: 14px;
  height: 14px;
  border: 3px solid #fff;
  border-radius: 50%;
  background: #1dbf73;
  box-shadow: 0 2px 6px rgba(29, 191, 115, .35);
  animation: oneid-user-online-pulse 3.8s ease-in-out infinite;
}

@keyframes oneid-user-online-pulse {
  0%, 100% { background:#1dbf73; box-shadow: 0 2px 6px rgba(29, 191, 115, .34), 0 0 0 0 rgba(29, 191, 115, .46); transform: scale(1); }
  50% { background:#28d985; box-shadow: 0 2px 8px rgba(29, 191, 115, .46), 0 0 0 10px rgba(29, 191, 115, 0); transform: scale(1.12); }
}

@media (prefers-reduced-motion: reduce) {
  .profile-box .profile-info .profile-img-wrap,
  .oneid-user-profile-online { animation: none; }
}





      .user-app-results { display:block; padding:12px 16px; color:#075b86; font-weight:600; }
      .user-app-content .user-app-result-category { display:block; color:#52677d; margin:3px 0; padding:0; }
      @media (max-width:767px) {
        .profile-box .profile-cover-pic { height:auto; min-height:0; aspect-ratio:2 / 1; background-size:100% 100%; }
        .profile-box .profile-info .profile-img-wrap { width:110px; height:110px; margin:-65px auto 0; padding:4px; border-width:4px; z-index:2; animation:none; }
        .profile-box .profile-info { padding:0 12px; margin-bottom:8px !important; }
        .profile-box .profile-info h6 { margin-top:6px !important; }
        .profile-box .profile-info > span { font-size:12px; line-height:1.5; }
        .oneid-user-sidebar-menu { margin-top:12px !important; }
        .oneid-user-sidebar-menu .oneid-sidebar-nav { display:block; }
        .oneid-user-sidebar-menu .oneid-sidebar-nav > li { width:100%; margin:0 !important; min-width:0; }
        .oneid-user-sidebar-menu .oneid-sidebar-nav > li > a { padding:8px; min-height:44px; font-size:12px; }
        .oneid-user-sidebar-menu .oneid-sidebar-nav > li > a span { overflow-wrap:anywhere; }
      }
      .oneid-mobile-menu-toggle { display:none; }
      @media (max-width:767px) {
        .oneid-mobile-menu-toggle { display:flex; align-items:center; gap:10px; width:calc(100% - 24px); margin:12px; min-height:44px; padding:10px 14px; border:1px solid #cfe3ef; border-radius:10px; background:#f0f8fc; color:#075b86; font-weight:600; cursor:pointer; }
        .oneid-mobile-menu-toggle > i:last-child { margin-left:auto; }
        .oneid-mobile-menu-toggle[aria-expanded="true"] > i:last-child { transform:rotate(180deg); }
        .oneid-mobile-menu-toggle:focus-visible { outline:2px solid #008eb8; outline-offset:2px; }
        .oneid-user-sidebar-menu { display:none !important; margin:0 12px 12px !important; }
        .oneid-user-sidebar-menu.is-open { display:block !important; }
        .oneid-user-sidebar-menu .oneid-sidebar-nav { float:none; width:100%; max-width:100%; box-sizing:border-box; padding:0 !important; }
        .oneid-user-sidebar-menu .oneid-sidebar-nav > li > a { width:100%; box-sizing:border-box; }
        .oneid-user-sidebar-menu .oneid-sidebar-nav > li { float:none; margin-bottom:4px !important; }
        .user-app-card { grid-template-columns:44px minmax(0,1fr); gap:10px 12px; padding:16px; }
        .user-app-index { display:none; }
        .user-app-image { width:44px; height:44px; }
        .user-app-name { flex-wrap:wrap; }
        .user-app-name strong { white-space:normal; overflow-wrap:anywhere; }
        .user-app-content > p { white-space:normal; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
        .user-app-actions { grid-column:1 / -1; display:flex; flex-direction:row; align-items:center; justify-content:flex-end; gap:8px; margin-top:2px; }
        .user-app-favourite { width:44px; min-width:44px; height:44px; }
        .user-app-open { width:auto; min-width:112px; height:44px; padding:0 16px; }
        .user-app-open span { display:inline; }
      }
   </style>
   <script>
   (function(){
      var menuToggle = document.querySelector('.oneid-mobile-menu-toggle');
      var mobileMenu = document.querySelector('.oneid-user-sidebar-menu');
      if (menuToggle && mobileMenu) {
         menuToggle.addEventListener('click', function(){
            var open = menuToggle.getAttribute('aria-expanded') !== 'true';
            menuToggle.setAttribute('aria-expanded', String(open));
            mobileMenu.classList.toggle('is-open', open);
         });
         mobileMenu.addEventListener('keydown', function(event){
            if (event.key === 'Escape') {
               mobileMenu.classList.remove('is-open');
               menuToggle.setAttribute('aria-expanded', 'false');
               menuToggle.focus();
            }
         });
      }
      var entry=document.getElementById('administrator_entry'),loader=document.getElementById('adminEntryLoader');
      if(!entry||!loader)return;
      entry.addEventListener('click',async function(event){
         event.preventDefault();if(loader.classList.contains('is-visible'))return;
         loader.classList.add('is-visible');loader.setAttribute('aria-hidden','false');
         var stepUrl=<?=json_encode(APP_URL.'/page/admin-step-up?purpose=ADMIN_ACCESS')?>;
         try{
            var response=await fetch(<?=json_encode(APP_URL.'/lib/q_func.php')?>,{method:'POST',headers:{'X-CSRF-Token':<?=json_encode(oneid_csrf_token())?>,'Accept':'application/json'},body:new URLSearchParams({_csrf_token:<?=json_encode(oneid_csrf_token())?>,admin_step_up_status:'1',purpose:'ADMIN_ACCESS'})});
            var result=await response.json();
            window.location.replace(response.ok&&result.grant_valid?<?=json_encode(APP_URL.'/admin/dashboard')?>:stepUrl);
         }catch(error){window.location.replace(stepUrl);}
      });
   })();
   </script>
   </body>
</html>
