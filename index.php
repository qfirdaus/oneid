<?php 
require_once __DIR__ . '/lib/session_security.php';
oneid_start_secure_session();
require_once __DIR__ . '/lib/request_security.php';
require_once __DIR__ . '/lib/SSO_IDP_INC.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ONEID@UPNM - Gerbang Tunggal ke Sistem Digital UPNM</title>

  <!-- Favicon -->
  <link rel="shortcut icon" href="img/favicon.png" />

  <!-- Styles -->
  <link rel="stylesheet" href="assetsM/css/animate.css" />
  <link rel="stylesheet" href="assetsM/fonts/icomoon/icomoon.css" />
  <link rel="stylesheet" href="assetsM/css/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="assetsM/css/main.min.css" />
  <link rel="stylesheet" href="assetsM/css/custom.css" />
  <link rel="stylesheet" href="vendors/bower_components/jquery-toast-plugin/dist/jquery.toast.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Moon+Dance&display=swap" rel="stylesheet" />

</head>

<body class="login-container">
<div class="container py-5">
  <div class="row shadow-lg rounded-4 overflow-hidden" style="background: white;">
  
    <!-- LEFT SIDE: Login  order-2 order-md-1 -->
    <div class="col-md-4 bg-white p-4">
      <div class="mb-3 pb-2">
      </div>   

      <form id="loginform">
        <div style="text-align: center; margin-bottom: 12px;">          
          <img src="img/logo_oneid.png" alt="UPNM Logo" style="width: 80%; height: auto !important;" />
          <img src="img/logoupnm_2.png" alt="UPNM Logo" style="width: 40%; height: auto !important;" />
        </div>

        <div class="text-center mb-4">
          <h5 class="txt-heading text-center txt-dark mb-5 ">
            <!-- <u><font class="custom_link">S</font></u>atu <u><font class="custom_link">L</font></u>ogin, <u><font class="custom_link">S</font></u>emua <u><font class="custom_link">A</font></u>kses (SSO) -->
             Gerbang Tunggal ke Sistem Digital UPNM<br>
             <i><span class="txt-slogan">Your Gateway to UPNM's Digital Ecosystem</span></i>
          </h5>
        </div>

        <div id="login_status"></div>

        <div class="login-form-block" style="display: flex; flex-direction: column; gap: 4px;margin-bottom: 20px;">
          <label for="username" class="login-form-label" style="margin-bottom: 4px; font-weight: 500; color: #2c2c2c;"> ID Pengguna</label>
          <input id="username" name="username" type="text" class="login-form-control custom_input login_placeholder" placeholder="Masukkan No. Staf (XXXX-XX) / No. Pelajar" maxlength="20" pattern="[A-Za-z0-9][A-Za-z0-9._@\-]*" />
        </div>

        <div class="login-form-block" style="display: flex; flex-direction: column; gap: 4px; margin-bottom: 20px;">
          <label for="password" class="login-form-label" style="margin-bottom: 4px; font-weight: 500; color: #2c2c2c;"> Kata Laluan</label>
          <input id="password" name="password" type="password" placeholder="Masukkan Kata Laluan" class="login-form-control custom_input login_placeholder" />
		  <small>Pengguna baharu perlu menggunakan fungsi Lupa Kata Laluan untuk menetapkan kata laluan pertama.</small>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3" >
          <a style="cursor: pointer;" class="text-primary" onclick="open_forgot_password()">Lupa Kata Laluan?</a>
          <button type="submit" class="btn btn-warning px-4">
            <i class="icon-login me-1 animate__animated animate__swing animate__infinite infinite"></i> Log Masuk
          </button>
        </div>

        <div class="mydigitalid-preview" aria-label="MyDigital ID integration coming soon">
          <span class="mydigitalid-preview-label">Pilihan log masuk akan datang</span>
          <img src="https://www.digital-id.my/images/logo/logo_colored.svg" alt="MyDigital ID" width="158" height="42" loading="lazy" referrerpolicy="no-referrer" />
          <small>Integrasi belum diaktifkan</small>
        </div>

        <input type="hidden" name="auth" value="auth">
      </form>
      
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
      <div class="mb-3 pb-2 border-bottom">
        <div class="d-flex justify-content-start gap-3 txt-heading">
          <a href="./public_docs/MANUAL_SALAM.pdf" class="menu_link" target="_blank" rel="noopener">Manual Pengguna</a>
          <a href="#" class="menu_link" data-bs-toggle="modal" data-bs-target="#faqModal">Soalan Lazim</a>
          <a href="https://directory.upnm.edu.my/" target="_blank" class="menu_link">Direktori UPNM</a>
        </div>
      </div>

      <!-- Slider -->
      <div id="carouselExample" class="carousel slide mb-4" data-bs-ride="carousel">
        <div class="carousel-inner rounded">
          <div class="carousel-item active">
            <img src="assetsM/images/banner6.png" class="w-100 slider-img" alt="Slider">
          </div>
          <div class="carousel-item">
            <img src="assetsM/images/banner7.png" class="w-100 slider-img" alt="Slider">
          </div>
         <!-- <div class="carousel-item">
            <img src="assetsM/images/banner5.png" class="w-100 slider-img" alt="Slider">
          </div> -->
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#carouselExample" data-bs-slide="prev">
          <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#carouselExample" data-bs-slide="next">
          <span class="carousel-control-next-icon"></span>
        </button>
      </div>

      <!-- Contact Info -->
      <div class="px-2">
        <h5 class="txt-heading"><i class="fa-solid fa-bullhorn fa-corporate-red "></i> Hubungi Kami</h5>
        <hr>
        <div class="row" style="margin-bottom:10px">
          <div class="col-md-12"><strong>Perkhidmatan Sokongan OneID@UPNM</strong><br><small></small></div>
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
            <small>Bahagian Teknologi Maklumat & Komunikasi, Universiti Pertahanan Nasional Malaysia (UPNM)</small>
            <br>
            <i class="fa-solid"></i> 
            <small>Kem Perdana Sungai Besi, 57000 Kuala Lumpur</small>
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

  <!-- All modals (forgot password, OTP, manual) remain the same -->
  <!-- Paste your existing modals here exactly as they are -->
  <!-- Example: -->

<!-- Modal 1 -->
                      <div class="modal fade" id="modal_forgot_password" tabindex="-1" aria-labelledby="modal_forgot_password_label"
                          aria-hidden="true">
                          <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                              <div class="modal-header">
                                <h5 class="modal-title" id="modal_forgot_password_label">
                                  Lupa Kata Laluan
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                  aria-label="Close"></button>
                              </div>
                              <form id="form_forgot_password">
                              <div class="modal-body">
                                <div class="form-wrap">
                                             <div class="form-body overflow-hide">
                                                <div class="form-group" id="forgot_pwd_body">
                                                   <label class="form-label" for="forgot_password_id">No. Kad Pengenalan / No. Passport</label>
                                                   <input type="text" class="form-control" id="forgot_password_id" name="forgot_password_id" placeholder="Masukkan maklumat untuk tetapan semula kata laluan" required="">
                                                </div>
                                                <div id="forgot_pwd_loading_OTP">
                                                  <div class="spinners-container text-center">
                                                    <div class="spinner-grow text-green" role="status">
                                                      <span class="visually-hidden">Loading...</span>
                                                    </div>
                                                    <div class="spinner-grow text-red" role="status">
                                                      <span class="visually-hidden">Loading...</span>
                                                    </div>
                                                    <div class="spinner-grow text-blue" role="status">
                                                      <span class="visually-hidden">Loading...</span>
                                                    </div>
                                                    <div class="spinner-grow text-blue" role="status">
                                                      <span class="visually-hidden">Loading...</span>
                                                    </div>
                                                    <div class="spinner-grow text-red" role="status">
                                                      <span class="visually-hidden">Loading...</span>
                                                    </div>
                                                    <div class="spinner-grow text-green" role="status">
                                                      <span class="visually-hidden">Loading...</span>
                                                    </div>
                                                  </div>
                                                  <center><p>Sila tunggu sebentar. Permohonan OTP sedang diproses.</p></center>
                                                </div>
                                             </div>
                                          </div>
                              </div>
                              <div class="modal-footer" id="forgot_pwd_footer">
                                <button type="button" class="btn btn-dark" data-bs-dismiss="modal">
                                  Tutup
                                </button>
                                <button type="submit" class="btn btn-info">
                                  Set Semula
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
                                  One Time Passcode (OTP) 
                                </h5>
                              </div>
                              <form id="form_otp">
                              <div class="modal-body">
                                
                                <div class="row">
                           <div class="col-lg-12">

                              <div id="otp_modal_loading_OTP">
                                                  <div class="spinners-container text-center">
                                                    <div class="spinner-grow text-green" role="status">
                                                      <span class="visually-hidden">Loading...</span>
                                                    </div>
                                                    <div class="spinner-grow text-red" role="status">
                                                      <span class="visually-hidden">Loading...</span>
                                                    </div>
                                                    <div class="spinner-grow text-blue" role="status">
                                                      <span class="visually-hidden">Loading...</span>
                                                    </div>
                                                    <div class="spinner-grow text-blue" role="status">
                                                      <span class="visually-hidden">Loading...</span>
                                                    </div>
                                                    <div class="spinner-grow text-red" role="status">
                                                      <span class="visually-hidden">Loading...</span>
                                                    </div>
                                                    <div class="spinner-grow text-green" role="status">
                                                      <span class="visually-hidden">Loading...</span>
                                                    </div>
                                                  </div>
                                <center><p>Sila tunggu sebentar. Permohonan OTP sedang diproses.</p></center>
                                
                              </div>
                              <div  id="otp_modal_body">
                                 <div class="panel-wrapper">
                                    <div class="panel-body pa-0" style="display: flex; justify-content: center; align-items: center; flex-direction: column;">
                                       <div class="col-sm-12 col-xs-12" style="display: flex; flex-direction: column; align-items: center;">
                                          <div class="form-wrap">
                                             <div class="form-body overflow-hide" style="display: flex; flex-direction: column; align-items: center;">
                                                  <div class="form-group">
                                                    <div id="otp_inputs">
                                                      <input type="text" maxlength="1" class="otp-input form-control" required="">
                                                      <input type="text" maxlength="1" class="otp-input form-control" required="">
                                                      <input type="text" maxlength="1" class="otp-input form-control" required="">
                                                      <input type="text" maxlength="1" class="otp-input form-control" required="">
                                                      <input type="text" maxlength="1" class="otp-input form-control" required="">
                                                      <input type="text" maxlength="1" class="otp-input form-control" required="">
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
                                  Tutup
                                </button>
                                <button type="submit" class="btn btn-info" id="btn_otp_submit">Hantar OTP</button>
                                <button type="button" class="btn btn-info" id="btn_otp_request" onclick="resend_request_OTP();">Mohon OTP</button>
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
        <h5 class="modal-title" id="modal_reset_password_label">Tetapkan Kata Laluan Baharu</h5>
      </div>
      <form id="form_reset_password">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="reset_password_new">Kata Laluan Baharu</label>
            <input type="password" class="form-control" id="reset_password_new" name="reset_password_new" minlength="12" autocomplete="new-password" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="reset_password_confirm">Sahkan Kata Laluan Baharu</label>
            <input type="password" class="form-control" id="reset_password_confirm" name="reset_password_confirm" minlength="12" autocomplete="new-password" required>
          </div>
          <small>Minimum 12 aksara serta mengandungi huruf besar, huruf kecil, nombor dan simbol.</small>
          <div id="reset_password_message" class="mt-3"></div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-info">Simpan Kata Laluan</button>
        </div>
      </form>
    </div>
  </div>
</div>

                        <div class="modal fade" id="faqModal" tabindex="-1" aria-labelledby="faqModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="faqModalLabel">Soalan Lazim</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">

         <div class="accordion" id="faqAccordion">

  <!-- FAQ 1 -->
  <div class="accordion-item">
    <h2 class="accordion-header" id="heading1">
      <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
        Apakah OneID@UPNM?
      </button>
    </h2>
    <div id="collapse1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
      <div class="accordion-body">
        OneID@UPNM ialah satu platform Single Sign-On (SSO) yang memudahkan pengguna mengakses pelbagai sistem dengan satu log masuk sahaja. Sistem atau aplikasi yang belum diintegrasikan akan disediakan dalam bentuk pautan agar tetap boleh diakses melalui OneID@UPNM.
      </div>
    </div>
  </div>

  <!-- FAQ 2 -->
  <div class="accordion-item">
    <h2 class="accordion-header" id="heading2">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
        Siapakah yang boleh menggunakan OneID@UPNM ini?
      </button>
    </h2>
    <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
      <div class="accordion-body">
        Semua warga UPNM – iaitu staf yang berdaftar dalam Sistem Maklumat Staf dan pelajar yang berdaftar dalam Sistem Maklumat Pelajar.
      </div>
    </div>
  </div>

  <!-- FAQ 3 -->
  <div class="accordion-item">
    <h2 class="accordion-header" id="heading3">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
        Bagaimanakah cara untuk log masuk ke OneID@UPNM?
      </button>
    </h2>
    <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
      <div class="accordion-body">
        Log masuk nombor staf (format sebenar) atau nombor pelajar sebagai ID pengguna, dan nombor kad pengenalan (tanpa sengkang) sebagai kata laluan awal. Selepas log masuk pertama, anda perlu menukar kata laluan mengikut piawaian keselamatan.
      </div>
    </div>
  </div>

  <!-- FAQ 4 -->
  <div class="accordion-item">
    <h2 class="accordion-header" id="heading4">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4">
        Adakah sistem ini selamat?
      </button>
    </h2>
    <div id="collapse4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
      <div class="accordion-body">
        Ya. OneID@UPNM dibangunkan dengan piawaian keselamatan terkini, termasuk penggunaan token API untuk pengesahan, ciri log keluar automatik (session timeout) bagi mengelakkan akses tanpa kebenaran, serta pengesahan akaun OneID melalui emel rasmi UPNM untuk memastikan identiti pengguna yang sah.
      </div>
    </div>
  </div>

  <!-- FAQ 5 -->
  <div class="accordion-item">
    <h2 class="accordion-header" id="heading5">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse5">
        Bolehkah saya log masuk di lebih dari satu peranti pada masa yang sama?
      </button>
    </h2>
    <div id="collapse5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
      <div class="accordion-body">
        Ya, anda boleh log masuk pada beberapa peranti dalam masa yang sama. Namun, demi keselamatan data, log keluar (logout) dari peranti yang tidak digunakan amat digalakkan.
      </div>
    </div>
  </div>

  <!-- FAQ 6 -->
  <div class="accordion-item">
    <h2 class="accordion-header" id="heading6">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse6">
        Jika saya keluar/log out dari satu aplikasi, adakah saya akan log out dari semua?
      </button>
    </h2>
    <div id="collapse6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
      <div class="accordion-body">
        Tidak. Log keluar dari satu aplikasi hanya akan menamatkan sesi untuk aplikasi tersebut sahaja. Aplikasi lain yang anda akses melalui OneID masih kekal aktif.
      </div>
    </div>
  </div>

  <!-- FAQ 7 -->
  <div class="accordion-item">
    <h2 class="accordion-header" id="heading7">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse7">
        Apa yang perlu saya buat jika terlupa kata laluan?
      </button>
    </h2>
    <div id="collapse7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
      <div class="accordion-body">
        Jika anda terlupa kata laluan, klik butang “Forgot Password” di laman utama OneID@UPNM. Sistem akan menghantar kod OTP ke e-mel rasmi UPNM anda. Selepas anda masukkan kod tersebut, sistem akan membawa anda ke halaman untuk menetapkan semula kata laluan baharu sebelum boleh teruskan akses ke sistem.
      </div>
    </div>
  </div>

  <!-- FAQ 8 -->
  <div class="accordion-item">
    <h2 class="accordion-header" id="heading8">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse8">
        Apa syarat kata laluan yang dibenarkan?
      </button>
    </h2>
    <div id="collapse8" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
      <div class="accordion-body">
        Kata laluan mestilah minimum 12 aksara, mengandungi kombinasi huruf besar dan huruf kecil, nombor, serta simbol khas.
      </div>
    </div>
  </div>

</div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>






  <!-- JavaScript dependencies -->
  <script src="assetsM/js/jquery.min.js"></script>
  <script src="assetsM/js/bootstrap.bundle.min.js"></script>
  <script src="assetsM/js/modernizr.js"></script>
  <script src="assetsM/js/moment.js"></script>
  <script src="assetsM/vendor/overlay-scroll/jquery.overlayScrollbars.min.js"></script>
  <script src="assetsM/vendor/overlay-scroll/custom-scrollbar.js"></script>
  <script src="vendors/bower_components/jquery-toast-plugin/dist/jquery.toast.min.js"></script>
  <script src="assetsM/js/oneid-notifications.js?v=20260716-1"></script>
  <script src="assetsM/js/main.js"></script>


<script>
$.ajaxSetup({
  headers: {'X-CSRF-Token': <?php echo json_encode(oneid_csrf_token()); ?>}
});

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
    $msg.html('<div class="alert alert-warning p-2 m-0">Terlalu banyak cubaan. Akaun anda telah dikunci. Sila log masuk semula selepas 2 minit.</div>');
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
      $msg.html('<div class="alert alert-danger p-2 m-0">ID/Kata laluan salah. Baki percubaan: '+left+'.</div>');
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

    // === NEW: abort if currently locked ===
    if (LoginLimiter.check(username) || $loginform.data('submitting')) return;
    if (!String(username || '').trim()) {
      swal('Log masuk tidak berjaya', 'Sila masukkan ID Pengguna.', 'error');
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
                  $('#login_status').html('<div class="alert alert-info alert-dismissable alert-style-1">Log masuk sedang diproses. Sila tunggu sebentar.</div>');
                },
                success: function (response) {
                    if (response['login_status'] == 0){
                      // === NEW: count a failure ===
                      LoginLimiter.onFailure(username);
                        $('#login_status').html('<div class="alert alert-danger alert-dismissable"><i class="zmdi zmdi-block pr-15 pull-left"></i><p class="pull-left">Log masuk tidak berjaya.</p><div class="clearfix"></div></div>');

                    }else{
                        // === NEW: clear counter on success ===
                        LoginLimiter.onSuccess(username);
                        $('#login_status').html('<div class="alert alert-success alert-dismissable"><i class="zmdi zmdi-check pr-15 pull-left"></i><p class="pull-left">Log masuk berjaya.</p><div class="clearfix"></div></div>');
                        window.location.href = response['redirect_uri'];
                    }

            },
            error: function (xhr, error, thrown) {
                swal('Log masuk tidak berjaya', error === 'timeout' ? 'Permintaan log masuk tamat tempoh. Cuba semula.' : 'Respons pelayan tidak dapat diterima.', 'error');
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
           return response && response.correlation_id ? '\nReference: ' + response.correlation_id : '';
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
                    swal('Permintaan diterima', response['msg'] + recoveryReference(response), 'success');
                     OTP_startCountdown();
                     $("#btn_otp_request").hide();
                     $("#btn_otp_submit").show();
                }else{                 
                // alert();       
                     swal('Permintaan tidak dapat diproses', response['msg'] + recoveryReference(response), 'error');
                             }
                
            $('#forgot_pwd_body').show();
            $('#forgot_pwd_footer').show();
            $('#forgot_pwd_loading_OTP').hide();
            $('#otp_modal_body').show();
            $('#otp_modal_footer').show();
            $('#otp_modal_loading_OTP').hide();
                     },
                     error: function (xhr, error, thrown) {
                       swal('Permintaan tergendala', error === 'timeout' ? 'Penghantaran mengambil masa terlalu lama. Status kejayaan tidak diandaikan.' : 'Respons pelayan tidak dapat diterima. Status kejayaan tidak diandaikan.', 'error');
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

        $('#countdown_OTP').text("OTP Expires in " + remaining + " Second");

        if (remaining <= 0) {
            clearInterval(countdownTimer);
            countdownTimer = null;
            $('#countdown_OTP').text("OTP had expired. Please request another OTP.");
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
                    swal('Permintaan diterima', response['msg'] + recoveryReference(response), 'success');
                     OTP_startCountdown();
                     $("#btn_otp_request").hide();
                     $("#btn_otp_submit").show();
                 
                }else{                 
                // alert();       
                     swal('Permintaan tidak dapat diproses', response['msg'] + recoveryReference(response), 'error');
                             }

              $('#otp_modal_body').show();
              $('#otp_modal_footer').show();
              $('#otp_modal_loading_OTP').hide();
         
                     },
                     error: function (xhr, error, thrown) {
                       swal('Permintaan tergendala', error === 'timeout' ? 'Penghantaran mengambil masa terlalu lama. Status kejayaan tidak diandaikan.' : 'Respons pelayan tidak dapat diterima. Status kejayaan tidak diandaikan.', 'error');
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
          $('#otp_message').css('color', 'red').text('Please complete all 6 digits.');
          return;
        } else {
          $('#otp_message').css('color', 'green').text('OTP lengkap dan sedia untuk dihantar.');
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
          var message = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Unable to reset password.';
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
