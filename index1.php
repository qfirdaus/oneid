<?php 
require_once 'lib/SSO_IDP_INC.php';
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />

    <!-- Meta -->
    <meta name="description" content="Responsive Bootstrap 5 Admin Dashboards" />
    <meta name="author" content="Bootstrap Gallery" />
    <link rel="canonical" href="https://www.bootstrap.gallery/">
    <meta property="og:url" content="https://www.bootstrap.gallery">
    <meta property="og:title" content="Admin Templates - Dashboard Templates | Bootstrap Gallery">
    <meta property="og:description" content="Marketplace for Bootstrap Admin Dashboards">
    <meta property="og:type" content="Website">
    <meta property="og:site_name" content="Bootstrap Gallery">
    <link rel="shortcut icon" href="assetsM/images/favicon.svg" />

    <!-- Title -->
    <title>UPNM | SALAM SSO</title>

    <!-- *************
			************ Common Css Files *************
		************ -->
    <!-- Animated css -->
    <link rel="stylesheet" href="assetsM/css/animate.css" />

    <!-- Icomoon Font Icons css -->
    <link rel="stylesheet" href="assetsM/fonts/icomoon/icomoon.css" />

    <link href="./vendors/bower_components/jquery-toast-plugin/dist/jquery.toast.min.css" rel="stylesheet" type="text/css">
    <!-- Main css -->
    <link rel="stylesheet" href="assetsM/css/main.min.css" />
  </head>

  <body class="login-container">


    <!-- Login box start -->
    <div class="container">
      <div class="d-flex align-items-center justify-content-center">
        <form id="loginform">
          <div class="login-box">
            <div class="login-blocks-img animate__animated animate__pulse animate__infinite infinite"></div>
            <div class="login-form">
              <a class="login-logo text-center ">
                <img src="img/logoupnm_1.png" alt="Admin Dashboard Templates" />
              </a>
              <div class="login-welcome">
                <h5 class="text-center txt-dark mb-5 "><u>S</u>atu <u>L</u>ogin, <u>S</u>emua <u>A</u>kses (SSO)</h5>

              </div>

              <div id="login_status">
                                          </div>
              <div class="login-form-block">
                <label for="username" class="login-form-label">Username</label>
                <input id="username"  name="username" type="text" class="login-form-control" />
              </div>
              <div class="login-form-block">
                <label for="password" class="login-form-label">Password</label>
                <input id="password" name="password" type="password" class="login-form-control" />
              </div>
              <div class="login-form-actions">
                <a style="cursor: pointer;" class="btn-link" onclick="open_forgot_password()">Forgot password?</a>
                <button type="submit" class="btn">
                  <span class="icon"> <i class="icon-login animate__animated animate__swing animate__infinite infinite"></i> </span> Sign On
                </button>
              </div>
              <div class="login-form-footer">
                <div class="additional-link">
                  Need a help?
                  <a class="btn" onclick="read_manual()"> Read Manual</a>
                </div>
              </div>
            </div>
          </div>
          <input type="hidden" name="auth" value="auth">
        </form>
      </div>
    </div>
    <!-- Login box end -->


<!-- Modal 1 -->
                      <div class="modal fade" id="modal_forgot_password" tabindex="-1" aria-labelledby="modal_forgot_password_label"
                          aria-hidden="true">
                          <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                              <div class="modal-header">
                                <h5 class="modal-title" id="modal_forgot_password_label">
                                  Forgot Password
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                  aria-label="Close"></button>
                              </div>
                              <form id="form_forgot_password">
                              <div class="modal-body">
                                <div class="form-wrap">
                                             <div class="form-body overflow-hide">
                                                <div class="form-group" id="forgot_pwd_body">
                                                   <label class="form-label" for="forgot_password_id">NIRC or Passport ID</label>
                                                   <input type="text" class="form-control" id="forgot_password_id" name="forgot_password_id" placeholder="Key in to reset password" required="">
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
                                                  <center><p>Wait a moment. Requesting OTP.</p></center>
                                                </div>
                                             </div>
                                          </div>
                              </div>
                              <div class="modal-footer" id="forgot_pwd_footer">
                                <button type="button" class="btn btn-dark" data-bs-dismiss="modal">
                                  Close
                                </button>
                                <button type="submit" class="btn btn-info">
                                  Reset
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
                                <center><p>Wait a moment. Requesting OTP.</p></center>
                                
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
                                  Close
                                </button>
                                <button type="submit" class="btn btn-info" id="btn_otp_submit">Submit OTP</button>
                                <button type="button" class="btn btn-info" id="btn_otp_request" onclick="resend_request_OTP();">Request OTP</button>
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
                                  SALAM: UPNM SSO Manual
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

    <!-- *************
			************ Required JavaScript Files *************
		************* -->
    <!-- Required jQuery first, then Bootstrap Bundle JS -->
    <script src="assetsM/js/jquery.min.js"></script>
    <script src="assetsM/js/bootstrap.bundle.min.js"></script>
    <script src="assetsM/js/modernizr.js"></script>
    <script src="assetsM/js/moment.js"></script>

    <!-- *************
      ************ Vendor Js Files *************
    ************* -->

    <!-- Overlay Scroll JS -->
    <script src="assetsM/vendor/overlay-scroll/jquery.overlayScrollbars.min.js"></script>
    <script src="assetsM/vendor/overlay-scroll/custom-scrollbar.js"></script>
    <script src="vendors/bower_components/jquery-toast-plugin/dist/jquery.toast.min.js"></script>

    <!-- Main Js Required -->
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
  </style>

  </body>

</html>