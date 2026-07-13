<?php 
require_once 'lib/SSO_IDP_INC.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>UPNM | SALAM SSO</title>

  <!-- Favicon -->
  <link rel="shortcut icon" href="img/favicon.png" />

  <!-- Styles -->
  <link rel="stylesheet" href="assetsM/css/animate.css" />
  <link rel="stylesheet" href="assetsM/fonts/icomoon/icomoon.css" />
  <link rel="stylesheet" href="assetsM/css/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="assetsM/css/main.min.css" />
  <link rel="stylesheet" href="assetsM/css/custom.css" />
  <link rel="stylesheet" href="vendors/bower_components/jquery-toast-plugin/dist/jquery.toast.min.css" />
</head>

<body class="login-container">
<!-- Top Menu -->
<!-- <div class="bg-white border-bottom">
  <div class="container py-3 d-flex justify-content-center gap-5">
    <a class="custom_link" href="#" class="topnav-link"  data-bs-toggle="modal" data-bs-target="#modal_read_manual">Manual Pengguna</a>
    <a class="custom_link" href="#" class="topnav-link" data-bs-toggle="modal" data-bs-target="#faqModal">Soalan Lazim</a>
    <a class="custom_link" href="https://directory.upnm.edu.my/" target="_blank" class="topnav-link">Direktori UPNM</a>
  </div>
</div> -->

<div class="container py-5">
  <div class="row shadow-lg rounded-4 overflow-hidden" style="background: white;">
  
    <!-- LEFT SIDE: Slider + Contact -->
    <div class="col-md-8 bg-light px-4 py-4">
       <!-- MENU ATAS -->
      <div class="mb-3 pb-2 border-bottom">
        <div class="d-flex justify-content-start gap-3 txt-heading">
          <a href="#" class="menu_link" data-bs-toggle="modal" data-bs-target="#modal_read_manual">Manual Pengguna</a>
          <a href="#" class="menu_link" data-bs-toggle="modal" data-bs-target="#faqModal">Soalan Lazim</a>
          <a href="https://directory.upnm.edu.my/" target="_blank" class="menu_link">Direktori UPNM</a>
        </div>
      </div>

      <!-- Slider -->
      <div id="carouselExample" class="carousel slide mb-4" data-bs-ride="carousel">
        <div class="carousel-inner rounded">
          <div class="carousel-item active">
            <img src="assetsM/images/banner1.jpg" class="d-block w-100 slider-img img-fluid" alt="Slider">
          </div>
          <div class="carousel-item">
            <img src="assetsM/images/banner2.webp" class="d-block w-100 slider-img img-fluid" alt="Slider">
          </div>
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
          <div class="col-md-4"><strong>Puan Sari Nashikim binti Radin Iskandar</strong><br><small><i class="fa-solid fa-phone fa-corporate-red "></i> 03-9051 3400 (ext 762 2004)</small></div>
          <div class="col-md-4"><strong>Puan Zaila Binti Kamisan</strong><br><small><i class="fa-solid fa-phone fa-corporate-red "></i> 03-9051 3400 (ext 762 1077)</small></div>
          <div class="col-md-4"><strong>Puan Nur Hamidah Binti Rahim</strong><br><small><i class="fa-solid fa-phone fa-corporate-red "></i>  03-9051 3400 (ext 762 2112)</small></div>
        </div>
        <div class="row" style="margin-bottom:10px">
          <div class="col-md-12">
            <i class="fa-solid fa-location-dot fa-corporate-red"></i> 
            <small>Bahagian Teknologi Maklumat & Komunikasi, Universiti Pertahanan Nasional Malaysia (UPNM), </small>
            <br>
            <i class="fa-solid"></i> 
            <small>Kem Perdana Sungai Besi, 57000 Kuala Lumpur</small>
          </div>
        </div>
        <div class="row" style="margin-bottom:10px">
          <div class="col-md-12"><i class="fa-solid fa-envelope fa-corporate-red "></i><a class="custom_link" href="mailto:bkp@upnm.edu.my"> sso@upnm.edu.my</a></div>
        </div>	
	
      </div>
    </div>

    <!-- RIGHT SIDE: Login -->
    <div class="col-md-4 bg-white p-4">
      <div class="mb-3 pb-2">
      </div>   

      <form id="loginform">
        <div style="text-align: center; margin-bottom: 12px;">
			    <img src="img/logoupnm_2.png" alt="UPNM Logo" style="width: 80%; height: auto !important;" />
		    </div>

        <div class="text-center mb-4">
          <h5 class="txt-heading text-center txt-dark mb-5 "><u><font class="custom_link">S</font></u>atu <u><font class="custom_link">L</font></u>ogin, <u><font class="custom_link">S</font></u>emua <u><font class="custom_link">A</font></u>kses (SSO)</h5>
        </div>

        <div id="login_status"></div>

        <div class="login-form-block" style="display: flex; flex-direction: column; gap: 4px;margin-bottom: 20px;">
          <label for="username" class="login-form-label" style="margin-bottom: 4px; font-weight: 500; color: #2c2c2c;"> ID Pengguna </label>
          <input id="username" name="username" type="text" class="login-form-control custom_input" />
        </div>

        <div class="login-form-block" style="display: flex; flex-direction: column; gap: 4px; margin-bottom: 20px;">
          <label for="password" class="login-form-label" style="margin-bottom: 4px; font-weight: 500; color: #2c2c2c;"> Kata Laluan </label>
          <input id="password" name="password" type="password" class="login-form-control custom_input" />
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3" >
          <a style="cursor: pointer;" class="text-primary" onclick="open_forgot_password()">Lupa Kata Laluan?</a>
          <button type="submit" class="btn btn-warning px-4">
            <i class="icon-login me-1 animate__animated animate__swing animate__infinite infinite"></i> Log Masuk
          </button>
        </div>

        <input type="hidden" name="auth" value="auth">
      </form>
      
        <!-- New version row -->
        <div class="row mt-3 justify-content-center">
          <div class="col-auto" style="font-size: 11px; color: #b0b0b0;" align="center">
            Salam &copy; 2025 v1.0.0 <br>Powered by BTMK, Universiti Pertahanan Nasional Malaysia
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

                        <!-- Modal 9 -->
                        <div class="modal fade" id="modal_read_manual" tabindex="-1"
                          aria-labelledby="modal_read_manualLabel" aria-hidden="true">
                          <div class="modal-dialog modal-fullscreen">
                            <div class="modal-content">
                              <div class="modal-header">
                                <h5 class="modal-title" id="modal_read_manualLabel">
                                  SALAM: Manual Pengguna UPNM SSO
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                  aria-label="Close"></button>
                              </div>
                              <div class="modal-body" style="flex-grow: 1; padding: 0;">
								<iframe 
								  src="./public_docs/MANUAL_SALAM.pdf" 
								  style="width: 100%; height: 100%; border: none;">
								</iframe>
							  </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-dark" data-bs-dismiss="modal">
                                  Close
                                </button>
                              </div>
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
          <div class="accordion-item">
            <h2 class="accordion-header" id="headingOne">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                How to login to current system?
              </button>
            </h2>
            <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
              <div class="accordion-body">
                You are required to key in Username and Password then click Login.
              </div>
            </div>
          </div>

          <div class="accordion-item">
            <h2 class="accordion-header" id="headingTwo">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                What happens if i forgot my password?
              </button>
            </h2>
            <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
              <div class="accordion-body">
                Click "Forget Password" link and follow the instructions.
              </div>
            </div>
          </div>

          <!-- Add more FAQ items here -->
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Close</button>
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
  <script src="assetsM/js/main.js"></script>

 
 <script>
    //----Login
var fallback_sp_id=getUrlParameter('site_id');
var $loginform = $('#loginform');
$loginform.on('submit', function(ev){
    ev.preventDefault();

    var data = $('#loginform').serializeArray();
    data.push({name: "site_id", value: fallback_sp_id});
console.log(data);
        $.ajax({
                type: 'POST',
                url: './lib/q_func',
                dataType: "json",
                data: data,
                beforeSend: function(){
                  $('#login_status').html('<div class="alert alert-info alert-dismissable alert-style-1">Signing on. Checking info. Wait a moment.</div>');
                },
                success: function (response) {
                    if (response['login_status'] == 0){
                        $('#login_status').html('<div class="alert alert-danger alert-dismissable"><i class="zmdi zmdi-block pr-15 pull-left"></i><p class="pull-left">Login error.</p><div class="clearfix"></div></div>');

                    }else{
                        $('#login_status').html('<div class="alert alert-success alert-dismissable"><i class="zmdi zmdi-check pr-15 pull-left"></i><p class="pull-left">Login success.</p><div class="clearfix"></div></div>');
                        window.location.href = response['redirect_uri'];
                    }

            },
            error: function (xhr, error, thrown) {
                console.log(xhr);
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
         form_forgot_password.on('submit', function(ev){
             ev.preventDefault();

             var data = $('#form_forgot_password').serializeArray();
             data.push({name: 'action_forgot_password', value: ''});
                 $.ajax({
                         type: 'POST',
                         url: './lib/q_func',
                         dataType: "json",
                         data:data,
                         beforeSend: function(){
              $('#forgot_pwd_body').hide();
              $('#forgot_pwd_footer').hide();
              $('#forgot_pwd_loading_OTP').show();
                         },
                         success: function (response) {
                             if (response['result'] == "true"){  
                              $('#modal_forgot_password').modal('hide');
                              setTimeout(function() {
                    $('#modal_OTP').modal('show');
                }, 500);
                    $.toast().reset('all');                    
                     $.toast({
                      heading: '',
                      text: response['msg'],
                      position: 'bottom-center',
                      loaderBg:'#fec107',
                      icon: 'success',
                      hideAfter: 3500, 
                      stack: 6
                     });  
                     OTP_startCountdown();
                     $("#btn_otp_request").hide();
                     $("#btn_otp_submit").show();
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
                
            $('#forgot_pwd_body').show();
            $('#forgot_pwd_footer').show();
            $('#forgot_pwd_loading_OTP').hide();
            $('#otp_modal_body').show();
            $('#otp_modal_footer').show();
            $('#otp_modal_loading_OTP').hide();
                     },
                     error: function (xhr, error, thrown) {
                         console.log(xhr);
                     }
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

        otpEndTime = new Date().getTime() + 60000; // 60 seconds from now

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
      $.ajax({
                         type: 'POST',
                         url: './lib/q_func',
                         dataType: "json",
                         data:{action_forgot_password:"",forgot_password_id:$("#forgot_password_id").val()},
                         beforeSend: function(){
              $('#otp_modal_body').hide();
              $('#otp_modal_footer').hide();
              $('#otp_modal_loading_OTP').show();
                         },
                         success: function (response) {
                             if (response['result'] == "true"){  
                              $('#modal_forgot_password').modal('hide');
                              setTimeout(function() {
                    $('#modal_OTP').modal('show');
                }, 500);
                    $.toast().reset('all');                    
                     $.toast({
                      heading: '',
                      text: response['msg'],
                      position: 'bottom-center',
                      loaderBg:'#fec107',
                      icon: 'success',
                      hideAfter: 3500, 
                      stack: 6
                     });  
                     OTP_startCountdown();
                     $("#btn_otp_request").hide();
                     $("#btn_otp_submit").show();
                 
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

              $('#otp_modal_body').show();
              $('#otp_modal_footer').show();
              $('#otp_modal_loading_OTP').hide();
         
                     },
                     error: function (xhr, error, thrown) {
                         console.log(xhr);
                     }
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

        console.log("Entered OTP:", otp);
        if (otp.length < 6) {
          $('#otp_message').css('color', 'red').text('Please complete all 6 digits.');
          return;
        } else {
          $('#otp_message').css('color', 'green').text('OTP submitted: ' + otp);
          // You can now send `otp` to server
        }

             var data = $('#form_otp').serializeArray();
             data.push({name: 'action_submit_OTP', value: ''});
             data.push({name: 'u_id', value: $("#forgot_password_id").val()});
             data.push({name: 'otp_id', value: otp});
             
                 $.ajax({
                         type: 'POST',
                         url: './lib/q_func',
                         dataType: "json",
                         data:data,
                         beforeSend: function(){
                         },
                         success: function (response) {
                             if (response['result'] == "true"){  
                    window.location.href = response['redirect_uri'];
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
                         console.log(xhr);
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


function read_manual(){
  $("#modal_read_manual").modal('show');
}



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
</style>

</body>
</html>
