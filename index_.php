<?php 
require_once 'lib/SSO_IDP_INC.php';
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
		<title>UPNM | SALAM SSO</title>
		<meta name="description" content="Admintres is a Dashboard & Admin Site Responsive Template by hencework." />
		<meta name="keywords" content="admin, admin dashboard, admin template, cms, crm, Admintres Admin, Admintresadmin, premium admin templates, responsive admin, sass, panel, software, ui, visualization, web app, application" />
		<meta name="author" content="hencework"/>
		
		<!-- Favicon -->
		<link rel="shortcut icon" href="favicon.ico">
		<link rel="icon" href="favicon.ico" type="image/x-icon">
		
		<!-- vector map CSS -->
		<link href="vendors/bower_components/jasny-bootstrap/dist/css/jasny-bootstrap.min.css" rel="stylesheet" type="text/css"/>
		<!-- Custom CSS -->
		<link href="./dist/css/style.css" rel="stylesheet" type="text/css">
      <link href="./vendors/bower_components/jquery-toast-plugin/dist/jquery.toast.min.css" rel="stylesheet" type="text/css">
	</head>
	<body>
		<!--Preloader-->
		<div class="preloader-it">
			<div class="la-anim-1"></div>
		</div>
		<!--/Preloader-->

		<div id="modal_forgot_password" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="aria_modal_change_password" aria-hidden="true">
            <div class="modal-dialog">
               <div class="modal-content">
                  <div class="modal-header">
                     <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                     <h5 class="modal-title" id="aria_modal_change_password">Forgot Password</h5>
                  </div>
                  <form id="form_forgot_password">
                     <div class="modal-body">
                        <div class="row">
                           <div class="col-lg-12">
                              <div class="">
                                 <div class="panel-wrapper collapse in">
                                    <div class="panel-body pa-0">
                                       <div class="col-sm-12 col-xs-12">
                                          <div class="form-wrap">
                                             <div class="form-body overflow-hide">
                                                <div class="form-group" id="forgot_pwd_body">
                                                   <label class="control-label mb-10" for="forgot_password_id">NIRC or Passport ID</label>
                                                   <input type="text" class="form-control" id="forgot_password_id" name="forgot_password_id" placeholder="Key in to reset password" required="">
                                                </div>
                                                <div id="forgot_pwd_loading_OTP">
                                                <center><p>Wait a moment. Requesting OTP.</p></center>
                                                <div class="progress progress-lg">
													<div class="progress-bar progress-bar-inverse active progress-bar-striped" aria-valuenow="85" aria-valuemin="0" aria-valuemax="100" style="width: 100%" role="progressbar"> <span class="sr-only">85% Complete (success)</span> </div>
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
                     </div>
                     <div class="modal-footer"  id="forgot_pwd_footer">
                        <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary waves-effect">Reset</button>
                     </div>
                  </form>
               </div>
               <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
         </div>


		<div id="modal_OTP" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="aria_modal_OTP" aria-hidden="true">
            <div class="modal-dialog">
               <div class="modal-content">
                  <!-- <div class="modal-header">
                     <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                     <h5 class="modal-title" id="aria_modal_OTP">Forgot Password</h5>
                  </div> -->

                  <form id="form_otp">
                     <div class="modal-body">
                     	<div class="row">
                           <div class="col-lg-12">
                     	  <div class="panel panel-primary card-view">
								<div class="panel-heading">
									<div class="pull-left">
										<h6 class="panel-title txt-light">One Time Passcode (OTP) 
											
										</h6>
									</div>
									<div class="clearfix"></div>
								</div>
							</div>
						</div>
					</div>


                        <div class="row">
                           <div class="col-lg-12">

                              <div id="otp_modal_loading_OTP">
                              	<center><p>Wait a moment. Requesting OTP.</p></center>
                              	<div class="progress progress-lg">
                              		<div class="progress-bar progress-bar-inverse active progress-bar-striped" aria-valuenow="85" aria-valuemin="0" aria-valuemax="100" style="width: 100%" role="progressbar"> <span class="sr-only">85% Complete (success)</span> </div>
                              	</div>
                              </div>
                              <div  id="otp_modal_body">
                                 <div class="panel-wrapper collapse in">
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
														<!-- <div id="otp_message"></div> -->
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
                        <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Close</button>
                        <button type="button" id="btn_otp_request" class="btn btn-primary waves-effect" onclick="resend_request_OTP();">Request OTP</button>
                        <button type="submit" id="btn_otp_submit" class="btn btn-primary waves-effect">Send</button>
                     </div>
                  </form>
               </div>
               <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
         </div>


		<div id="modal_read_manual" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="aria_modal_change_password" aria-hidden="true">
  <div class="modal-dialog" style="width: 100%; max-width: 100%; height: 100%; margin: 0; padding: 0;">
    <div class="modal-content" style="height: 100vh; border-radius: 0; display: flex; flex-direction: column;">
      
      <div class="modal-header" style="flex-shrink: 0;">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h5 class="modal-title" id="aria_modal_change_password">SALAM: UPNM SSO Manual</h5>
      </div>
      
      <div class="modal-body" style="flex-grow: 1; padding: 0;">
        <iframe 
          src="./public_docs/MANUAL_SALAM.pdf" 
          style="width: 100%; height: 100%; border: none;">
        </iframe>
      </div>
      
      <div class="modal-footer" id="forgot_pwd_footer" style="flex-shrink: 0;">
        <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Close</button>
      </div>
      
    </div>
  </div>
</div>


		
		<div class="wrapper  pa-0">
			<header class="sp-header">
				<div class="clearfix"></div>
			</header>
			
			<!-- Main Content -->
			<div class="page-wrapper pa-0 ma-0 auth-page">
				<div class="container">

					<div class="row">
					<div class="col-sm-6">
						<div class="row">
							<div class="col-sm-12 col-xs-12">
								<div class="panel panel-default border-panel card-view panel-refresh">
									<div class="refresh-container">
										<div class="la-anim-1"></div>
									</div>
									<div class="panel-wrapper">
										<div class="panel-body">
											<div class="row">
												<div class="col-md-12">
											<div class="item-big set-height-wrap auto-width">
												<!-- START carousel-->
												<div id="carousel-example-captions-1" data-ride="carousel" class="carousel slide">
													<ol class="carousel-indicators">
													   <li data-target="#carousel-example-captions-1" data-slide-to="0" class="active"></li>
													   <li data-target="#carousel-example-captions-1" data-slide-to="1"></li>
													</ol>
													<div role="listbox" class="carousel-inner">
													   <div class="item active"> <img src="./img/announce.jpg" alt="First slide image"> </div>
													   <div class="item"> <img src="./img/announce2.jpg" alt="Second slide image"> </div>
													</div>
												</div>
												<!-- END carousel-->
											</div>
										</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="col-sm-6">
						<div class="row">
							<div class="col-sm-12 col-xs-12">
								<div class="panel panel-default border-panel card-view panel-refresh">
									<div class="refresh-container">
										<div class="la-anim-1"></div>
									</div>
									<div class="panel-heading">
										<div class="pull-left">
											<h6 class="panel-title txt-dark"></h6>
										</div>
										<div class="clearfix"></div>
									</div>
									<div class="panel-wrapper collapse in">
										<div class="panel-body">
										<!-- Row -->
										<div class="table-struct full-width">
											<div class="table-cell">
												<div class="auth-form  ml-auto mr-auto no-float card-view pt-30 pb-30">
													<div class="row">
														<div class="col-sm-12 col-xs-12">
															<div class="mb-30">
																<img class="brand-img mr-10 img-responsive mb-10" src="img/logoupnm_1.png" alt="brand"/>
																<h3 class="text-center txt-dark mb-10">SALAM</h3>
																<h5 class="text-center txt-dark mb-5"><u>S</u>atu <u>L</u>ogin, <u>S</u>emua <u>A</u>kses (SSO)</h5>
															</div>	
															<div class="form-wrap">
																<form id="loginform">

											                    <div id="login_status">
											                    </div>
																
																	<div class="form-group">
																		<label class="control-label mb-10" for="username">Username</label>
																		<input type="text" class="form-control" required="" name="username" id="username" placeholder="Enter your username">
																	</div>
																	<div class="form-group">
																		<label class="pull-left control-label mb-10" for="password">Password</label>
																		<a style="cursor: pointer;" class="capitalize-font txt-orange block mb-10 pull-right font-12" onclick="open_forgot_password()">forgot password ?</a>
																		<div class="clearfix"></div>
																		<input type="password" class="form-control" required="" name="password" id="password" placeholder="Enter your password">
																	</div>
																	
																	<!-- <div class="form-group">
																		<div class="checkbox checkbox-primary pr-10 pull-left">
																			<input id="checkbox_2" required="" type="checkbox">
																			<label for="checkbox_2"> Keep me logged in</label>
																		</div>
																		<div class="clearfix"></div>
																	</div> -->
																	<div class="form-group text-center">
																		<button type="button" class="btn btn-default btn-rounded" onclick="read_manual()">SSO Manual</button>
																		<button type="submit" class="btn btn-primary btn-rounded">sign on</button>
																	</div>
					                  							<input type="hidden" name="auth" value="auth">
																</form>
															</div>

														</div>	
													</div>
												</div>
											</div>
										</div>
										<!-- /Row -->	
											
											
										</div>	
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>


				</div>
				


						<!-- Footer -->
			<footer class="footer pl-30 pr-30">
				<div class="container">
					<div class="row">
						<div class="col-sm-6">
							<p>2025 &copy; SALAM SSO . By BTMK</p>
						</div>
						<div class="col-sm-6 text-right">
							<p>Follow Us</p>
							<a href="#"><i class="fa fa-facebook"></i></a>
							<a href="#"><i class="fa fa-twitter"></i></a>
							<a href="#"><i class="fa fa-google-plus"></i></a>
						</div>
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
		<script src="vendors/bower_components/jquery/dist/jquery.min.js"></script>
		
		<!-- Bootstrap Core JavaScript -->
		<script src="vendors/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
		<script src="vendors/bower_components/jasny-bootstrap/dist/js/jasny-bootstrap.min.js"></script>

        <script src="vendors/bower_components/jquery-toast-plugin/dist/jquery.toast.min.js"></script>
		
		<!-- Slimscroll JavaScript -->
		<script src="dist/js/jquery.slimscroll.js"></script>
		
		<!-- Init JavaScript -->
		<script src="dist/js/init.js"></script>

    <script>
    //----Login
var fallback_sp_id=getUrlParameter('site_id');
var $loginform = $('#loginform');
$loginform.on('submit', function(ev){
    ev.preventDefault();

    var data = $('#loginform').serializeArray();
    data.push({name: "site_id", value: fallback_sp_id});
//console.log(data);
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
