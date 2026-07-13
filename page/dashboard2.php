<?php
   session_start(); // Starting Session
   require_once '../lib/config.php';
   require_once '../lib/SSO_IDP_INC.php';
   $user_info = $operation->admin_search_user_account($_SESSION['login_user']);
   // echo "Xxxxx" . $_SESSION['user'];
   //echo json_encode($user_info);
 
   ?>
<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
      <title>UPNM SSO</title>
      <!-- Favicon -->
      <link rel="shortcut icon" href="../img/favicon.png">
      <link rel="icon" href="../img/favicon.png" type="image/x-icon">
      <!-- Morris Charts CSS -->
      <link href="../vendors/bower_components/morris.js/morris.css" rel="stylesheet" type="text/css"/>
      <!-- vector map CSS -->
      <link href="../vendors/vectormap/jquery-jvectormap-2.0.2.css" rel="stylesheet" type="text/css"/>
      <!-- Calendar CSS -->
      <link href="../vendors/bower_components/fullcalendar/dist/fullcalendar.css" rel="stylesheet" type="text/css"/>
      <!-- Data table CSS -->
      <link href="../vendors/bower_components/datatables/media/css/jquery.dataTables.min.css" rel="stylesheet" type="text/css"/>
      <link href="../vendors/bower_components/sweetalert/dist/sweetalert.css" rel="stylesheet" type="text/css">
      <link href="../vendors/bower_components/jquery-toast-plugin/dist/jquery.toast.min.css" rel="stylesheet" type="text/css">
      <!-- Custom CSS -->
      <link href="../dist/css/style.css" rel="stylesheet" type="text/css">
      <link href="../dist/css/custom.css" rel="stylesheet" type="text/css">
      <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

   </head>
   <body>
      <!--Preloader-->
      <div class="preloader-it">
         <div class="la-anim-1"></div>
      </div>
      <!--/Preloader-->

      <div class="wrapper theme-2-active navbar-top-light vertical-nav">
         <?php include 'const/top.php'; ?>      
         <?php include 'const/left.php'; ?>
         <div id="modal_change_first_time_password" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" style="display: none;" data-backdrop="static" data-keyboard="false">
            <div class="modal-dialog">
               <div class="modal-content">
                  <div class="modal-body">
                     <div class="alert alert-info alert-style-1">
                        <i class="zmdi zmdi-info-outline"></i>Hi there! You’re using the default password — changing it is required.
                     </div>
                     <h5 class="modal-title"></h5>
                  </div>
                  <div class="modal-footer">
                     <button type="button" class="btn btn-danger" onclick="open_change_password();">OK</button>
                  </div>
               </div>
            </div>
         </div>
         <div id="modal_change_password" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="aria_modal_change_password" aria-hidden="true">
            <div class="modal-dialog">
               <div class="modal-content">
                  <div class="modal-header">
                     <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                     <h5 class="modal-title" id="aria_modal_change_password">Change Password</h5>
                  </div>
                  <form id="form_change_password">
                     <div class="modal-body">
                        <div class="row">
                           <div class="col-lg-12">
                              <div class="">
                                 <div class="panel-wrapper collapse in">
                                    <div class="panel-body pa-0">
                                       <div class="col-sm-12 col-xs-12">
                                          <div class="form-wrap">
                                             <div class="form-body overflow-hide">
                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="change_password_current">Current Password <span id="default_pwd_text"></span></label>
                                                   <input type="password" class="form-control" id="change_password_current" name="change_password_current" placeholder="Key in for password verification" required="">
                                                </div>
                                                <hr/>
                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="change_password_new">New password</label>
                                                   <input type="password" class="form-control" id="change_password_new" name="change_password_new" placeholder="Keyin New password" required="">
                                                </div>
                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="change_password_new_reconfirm">Confirm new password</label>
                                                   <input type="password" class="form-control" id="change_password_new_reconfirm" name="change_password_new_reconfirm" placeholder="Reconfirm new password" required="">
                                                </div>
                                                <ul id="password-requirements" style="list-style: none; padding-left: 0; margin-top: 10px;">
            												  <li id="p_length"    >❌ At least 8 characters</li>
            												  <li id="p_lowercase" >❌ At least one lowercase letter</li>
            												  <li id="p_uppercase" >❌ At least one uppercase letter</li>
            												  <li id="p_number"    >❌ At least one number</li>
            												  <li id="p_special"   >❌ At least one special character</li>
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
                     <div class="modal-footer">
                        <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary waves-effect">Change</button>
                     </div>
                  </form>
               </div>
               <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
         </div>

         <!-- Main Content -->      
         <div class="page-wrapper">
            <div class="container">             
               <!-- Row -->
               <div class="row">
                  <div class="col-sm-6">
                     <div class="row">
                        <div class="col-sm-12">
                           <div class="panel panel-default card-view  pa-0">
                              <div class="panel-wrapper collapse in">
                                 <div class="panel-body  pa-0">
                                    <div class="profile-box">
                                       	<div class="profile-cover-pic">
                                          <div class="profile-image-overlay"></div>
                                          </div>
                                          <div class="profile-info text-center mb-15">
                                             <div class="widget-user-image">
                                                <img id="user_photos" class="img-circle elevation-2" style="width: 98px;height: 98px;" src="../img/mock1.jpg" alt="user">
                                             </div>
                                             <h5 class="block mt-10 weight-500 capitalize-font txt-dark"><?php echo $_SESSION['user']; ?></h5>
                                             <h6 class="block capitalize-font"><?php echo $user_info['data6']; ?></h6>
                                             <h6 class="block capitalize-font"><?php echo $user_info['data7']; ?></h6>
                                             <span class="time block truncate txt-grey"></span>
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
                        <div class="col-sm-12">
                           <div class="panel panel-default card-view pa-0">
                              <div class="panel-wrapper collapse in">
                                 <div  class="panel-body pb-0">
                                    <div  class="tab-struct custom-tab-1">
                                       <ul role="tablist" class="nav nav-tabs nav-tabs-responsive" id="myTabs_8">
                                          <li  role="presentation" class="active"><a aria-expanded="true"  data-toggle="tab" role="tab" id="follo_tab_8" href="#follo_8"><span>Applications <span class="inline-block" id='follo_data_list_count_text'></span></span></a></li>
                                          <li role="presentation" class="next"><a  data-toggle="tab" id="security_tab_1" role="tab" href="#security_tab" aria-expanded="false"><span>Signed Devices</span></a></li>
                                       </ul>
                                       <div class="tab-content" id="myTabContent_8">
                                          <div  id="follo_8" class="tab-pane fade active in" role="tabpanel">
                                             <div class="row">
                                                <div class="col-lg-12">
                                                   <div class="panel-heading">
                                                      <div class="pull-left">
                                                         <h6 class="panel-title txt-dark">List of accessible apps</h6>
                                                         <span class="block txt-primary font-12 pt-5 capitalize-font">Your session will expire in <span id="demo">- - -</span></span>
                                                      </div>
                                                      <div class="pull-right">										
                                                         <a href="#" class="pull-left inline-block refresh mr-15" onclick="get_specific_user_app_list()">
                                                         <i class="zmdi zmdi-replay text-primary"></i>
                                                         </a>	
                                                      </div>
                                                      <div class="clearfix"></div>
                                                   </div>

                                                   <div class="row">
                                                      <div class="col-lg-12">
                                                         <div  class="pills-struct mt-20 pl-20">
                                                            <ul role="tablist" class="nav nav-pills nav-pills-rounded" id="WebAppsTabsHeader">
                                                            </ul>
                                                            <div class="tab-content" id="WebAppsTabsContent">
                                                            </div>
                                                         </div>
                                                      </div>
                                                   </div>

                                                   <div id="app_list_loading">
                                                      <br/>
                                                      <div class="col-lg-12">
                                                         <div class="progress progress-lg">
                                                            <div class="progress-bar progress-bar-primary active progress-bar-striped" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%" role="progressbar"> Loading App.. Wait a moment..</div>
                                                         </div>
                                                      </div>
                                                      <br/>
                                                      <br/>
                                                   </div>
                                                   <div class="followers-wrap" id="app_list">
                                                      <ul class="followers-list-wrap">
                                                         <li class="follow-list">
                                                            <div class="follo-body" id="follo_data_list">
                                                            </div>
                                                         </li>
                                                      </ul>
                                                   </div>


                                                </div>
                                             </div>
                                          </div>
                                          <div  id="security_tab" class="tab-pane fade in" role="tabpanel">
                                             <div class="row">
                                                <div class="col-lg-12">
                                                   <div class="panel-heading">
                                                      <div class="pull-left">
                                                         <h6 class="panel-title txt-dark">Active sessions currently signed to your account</h6>
                                                      </div>
                                                      <div class="pull-right">										
                                                         <a href="#" class="pull-left inline-block refresh mr-15" onclick="get_specific_user_activ_session()">
                                                         <i class="zmdi zmdi-replay text-primary"></i>
                                                         </a>	
                                                      </div>
                                                      <div class="clearfix"></div>
                                                   </div>
                                                   <div id="app_security_session_loading">
                                                      <br/>
                                                      <div class="col-lg-12">
                                                         <div class="progress progress-lg">
                                                            <div class="progress-bar progress-bar-primary active progress-bar-striped" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%" role="progressbar"> Loading Active Session.. Wait a moment..</div>
                                                         </div>
                                                      </div>
                                                      <br/>
                                                      <br/>
                                                   </div>
                                                   <div class="followers-wrap" id="app_security_session_list">
                                                      <ul class="followers-list-wrap">
                                                         <li class="follow-list">
                                                            <div class="follo-body" id="security_tab_session">
                                                            </div>
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
                        <p> &copy; 2025 Powered by BTMK, Universiti Pertahanan Nasional Malaysia.</p>
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
      <script src="../vendors/bower_components/jquery-toast-plugin/dist/jquery.toast.min.js"></script>
      <!-- Init JavaScript -->
      <script src="../dist/js/init.js"></script>
      <script src="../dist/js/widgets-data.js"></script>
      <script>
          var user_id = "<?php echo $user_info['data2']; ?>";
          var stu_id="<?php echo $user_info['u_id']; ?>";
         $(document).ready(function() {
            init_start();
             get_specific_user_app_list();
             get_specific_user_activ_session();
             check_default_password();

         });

         function init_start(){
            checkImageExists('https://esmartcard.upnm.edu.my/img/staf/'+user_id+'.jpg', function(exists) {
                 if (exists) {
                   console.log('Staff!');
                   // You can set it dynamically here if needed
                    $('#user_photos').attr('src', 'https://esmartcard.upnm.edu.my/img/staf/'+user_id+'.jpg');
                 } else {
                   checkImageExists('https://kemasukan.upnm.edu.my/tawaran/pelajar/student_image/'+stu_id+'.jpg', function(exists) {
                     // alert(stu_id)
                       if (exists) {
                         console.log('Student!');
                         // You can set it dynamically here if needed
                          $('#user_photos').attr('src', 'https://kemasukan.upnm.edu.my/tawaran/pelajar/student_image/'+stu_id+'.jpg');
                       } else {
                         console.log('Image NOT found.');
                          $('#user_photos').attr('src', '../img/gallery/mock1.jpg');
                       }
                     });
                 }
               });
         }
          function checkImageExists(url, callback) {
           $('<img>', {
             src: url,
             error: function() {
               callback(false);
             },
             load: function() {
               callback(true);
             }
           });
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
                  if(response['result']=="change_pwd"){ 
                     //open_change_password();
                      $('#modal_change_first_time_password').modal('show');
                  }else{
                     
                              }
         
             },
             error: function (xhr, error, thrown) {
                 console.log(xhr);
             }
         });
         }
         
         
         //----Login
         function get_specific_user_app_list(){
            var href = $('#WebAppsTabsHeader li.active a').attr('href'); // e.g. "#SumberManusia_1_tab"
         $.ajax({
                 type: 'POST',
                 url: '../lib/q_func',
                 dataType: "json",
                 data: {get_specific_user_app_list:""},
                 beforeSend: function(){
                   $('#app_list_loading').fadeIn();
                   $('#app_list').hide();

                     $('#WebAppsTabsHeader').html('');
                     $('#WebAppsTabsContent').html('');
                 },
                 success: function (response) {
         
                   var tr ='';
                   $('#WebAppsTabsHeader').html('');
                   var li = '';
                   var div = '';


                   $('#app_list_loading').hide();
                   $('#app_list').fadeIn();
                 	var list_count = 0;
                 	if(response.length == 0){
                 		$('#follo_data_list_count_text').html('');
         
                tr += '<div class="follo-data">';
                // tr += '<img class="user-img img-circle"  src="../img/user.png" alt="user"/>';
         
                tr += '<div class="user-data"><span class="name block capitalize-font"></span><span class="time  txt-grey">You do not have any accessible apps at the moment. Please contact BTMK for further assistance.</span></div>';
         
                tr += '<div class="clearfix"></div>';
                tr += '</div>';
                         $('#follo_data_list').html(tr);
                 		return;
                 	}else{                		
                 		$('#follo_data_list_count_text').html(response.length);
                 	}
                     $('#follo_data_list').html('');
                     var count = 0;



            $.each( response, function( i, value ) {
               if(i == 0){
                  li += '<li class="active" role="presentation"><a aria-expanded="true"  data-toggle="tab" role="tab" href="#'+response[i]['tabname']+'">'+response[i]['sp_group_name']+' <span class="label label-warning">'+response[i]['data'].length+'</span>'+'</a></li>';
               }else{
                  li += '<li role="presentation"><a aria-expanded="true"  data-toggle="tab" role="tab" href="#'+response[i]['tabname']+'">'+response[i]['sp_group_name']+' <span class="label label-warning">'+response[i]['data'].length+'</span>'+'</a></li>';
               }

               if(i == 0){
                                 div += '<div  id="'+response[i]['tabname']+'" class="tab-pane fade active in" role="tabpanel">';
                                 div += '<div class="followers-wrap">';
                                 div += '<ul class="followers-list-wrap">';
                                 div += '<li class="follow-list" style="display: list-item;">';
                                 div += '<div class="follo-body">';
         
         
                                 $.each( response[i]['data'], function( k, value ) {
                                    list_count++;
                                    var img_sp = "";
                                    if(response[i]['data'][k]['sp_image']==""){
                                       img_sp = '<div class="user-data ml-5" style="padding-right: 15px;"><img src="../img/thumb-1.jpg" alt="thumbnail" style="width: 65px; height: 65px; object-fit: cover;"></div>';
                                    }else{
                                       img_sp = '<div class="user-data ml-5" style="padding-right: 15px;"><img src="../public_img/'+response[i]['data'][k]['sp_image']+'" alt="thumbnail" style="width: 65px; height: 65px; object-fit: cover;"></div>';
                                    }
                                    div += '<div class="follo-data">';
                                    if(response[i]['data'][k]['sp_sso_support']=="0"){
                                       div += img_sp+
                                          '<div class="user-data"><span class="name block capitalize-font"><span class="label label-primary">'+(k+1)+'</span> <b>'+response[i]['data'][k]['sp_name']+'</b></span><span class="time  txt-grey">'+response[i]['data'][k]['sp_description']+'</span><br/><span class="time  txt-primary">URL : '+response[i]['data'][k]['sp_domain']+'</span></div>';
                                       div += '<button class="btn btn-primary pull-right btn-xs fixed-btn  btn-anim" onclick="go_to_service_provider(&quot;'+response[i]['data'][k]['sp_id']+'&quot;);"><i class="fa fa-sign-in"></i><span class="btn-text">Go</span></button>';
                                    }else{
                                       div += img_sp+'<div class="user-data"><span class="name block capitalize-font"><span class="label label-primary">'+(k+1)+'</span> <b>'+response[i]['data'][k]['sp_name']+' <span class="label label-warning">SSO Not Supported</span></b></span><span class="time  txt-grey">'+response[i]['data'][k]['sp_description']+'</span><br/><span class="time  txt-primary">URL : '+response[i]['data'][k]['sp_domain']+'</span></div>';
                                       div += '<button class="btn btn-warning pull-right btn-xs fixed-btn  btn-anim" onclick="go_to_service_provider(&quot;'+response[i]['data'][k]['sp_id']+'&quot;);"><i class="fa fa-sign-in"></i><span class="btn-text">Login</span></button>';
                                    }
                                    
                                    
                                    div += '<div class="clearfix"></div>';
                                    div += '</div>';
                                 });
                                 
         
         
         
                                 div += '</div>';
                                 div += '</li>';
                                 div += '</ul>';
                                 div += '</div>';
                                 div += '</div>';
                              }else{
                                 
                                 div += '<div  id="'+response[i]['tabname']+'" class="tab-pane fade" role="tabpanel">';
                                 div += '<div class="followers-wrap">';
                                 div += '<ul class="followers-list-wrap">';
                                 div += '<li class="follow-list" style="display: list-item;">';
                                 div += '<div class="follo-body">';
         
         
                                 $.each( response[i]['data'], function( k, value ) {
                                    list_count++;
                                    var img_sp = "";
                                    if(response[i]['data'][k]['sp_image']==""){
                                       img_sp = '<div class="user-data ml-5" style="padding-right: 15px;"><img src="../img/thumb-1.jpg" alt="thumbnail" style="width: 65px; height: 65px; object-fit: cover;"></div>';
                                    }else{
                                       img_sp = '<div class="user-data ml-5" style="padding-right: 15px;"><img src="../public_img/'+response[i]['data'][k]['sp_image']+'" alt="thumbnail" style="width: 65px; height: 65px; object-fit: cover;"></div>';
                                    }
                                    div += '<div class="follo-data">';
                                    if(response[i]['data'][k]['sp_sso_support']=="0"){
                                       div += img_sp+'<div class="user-data"><span class="name block capitalize-font"><span class="label label-primary">'+(k+1)+'</span> <b>'+response[i]['data'][k]['sp_name']+'</b></span><span class="time  txt-grey">'+response[i]['data'][k]['sp_description']+'</span><br/><span class="time  txt-primary">URL : '+response[i]['data'][k]['sp_domain']+'</span></div>';
                                       div += '<button class="btn btn-primary pull-right btn-xs fixed-btn  btn-anim" onclick="go_to_service_provider(&quot;'+response[i]['data'][k]['sp_id']+'&quot;);"><i class="fa fa-sign-in"></i><span class="btn-text">Go</span></button>';
                                    }else{
                                       div += img_sp+'<div class="user-data"><span class="name block capitalize-font"><span class="label label-primary">'+(k+1)+'</span> <b>'+response[i]['data'][k]['sp_name']+' <span class="label label-warning">SSO Not Supported</span></b></span><span class="time  txt-grey">'+response[i]['data'][k]['sp_description']+'</span><br/><span class="time  txt-primary">URL : '+response[i]['data'][k]['sp_domain']+'</span></div>';
                                       div += '<button class="btn btn-warning pull-right btn-xs fixed-btn  btn-anim" onclick="go_to_service_provider(&quot;'+response[i]['data'][k]['sp_id']+'&quot;);"><i class="fa fa-sign-in"></i><span class="btn-text">Login</span></button>';
                                    }
                                    
                                    
                                    div += '<div class="clearfix"></div>';
                                    div += '</div>';
                                 });
                                 
         
         
         
                                 div += '</div>';
                                 div += '</li>';
                                 div += '</ul>';
                                 div += '</div>';
                                 div += '</div>';
                              }



                
            });


            if(response.length == 0){
            $('#follo_data_list_count_text').html('');
            $('#follo_data_list').html('<div class="follo-data"><div class="user-data"><span class="time  txt-grey">No apps available.</span></div><div class="clearfix"></div></div>');
            return;
            }else{                     
            $('#follo_data_list_count_text').html(list_count);
            }
            
                     console.log(tr)
                     $('#WebAppsTabsHeader').html(li);
                     $('#WebAppsTabsContent').html(div);
                     if(href!="undefined"){
                        $('a[href="'+href+'"]').tab('show');
                     }
                     // $('#follo_data_list').html(tr);
         
             },
             error: function (xhr, error, thrown) {
                 console.log(xhr);
             }
         });
         }
         
         
         function get_specific_user_activ_session(){
         $.ajax({
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
                 	console.log(response.length)
                 	var tr ='';
                     $('#security_tab_session').html('');
            $.each( response, function( i, value ) {
                tr += '<div class="follo-data">';
                var current_session = "";
                if(response[i]['current_token']=="1"){
                	current_session = '<i class="fa fa-check-circle text-primary"></i>';
                 tr += '<div class="user-data"><span class="name block capitalize-font">'+(i+1)+'. '+response[i]['device_info']+' '+current_session+'</span></div>';
                 tr += '<button class="btn btn-default pull-right btn-xs fixed-btn " ><span class="btn-text">Current</span></button>';
         
                }else{
                 tr += '<div class="user-data"><span class="name block capitalize-font">'+(i+1)+'. '+response[i]['device_info']+' '+current_session+'</span></div>';
                 tr += '<button class="btn btn-danger pull-right btn-xs fixed-btn  btn-anim" onclick="sign_off_token(&quot;'+response[i]['token_id']+'&quot;);"><i class="fa fa-sign-in"></i><span class="btn-text">Sign off</span></button>';
         
         
                }
                // tr += '<img class="user-img img-circle"  src="../img/user.png" alt="user"/>';
                //  fa-check-circle
                tr += '<div class="clearfix"></div>';
                tr += '</div>';
            });
                     console.log(tr)
         
                     $('#security_tab_session').html(tr);
         
             },
             error: function (xhr, error, thrown) {
                 console.log(xhr);
             }
         });
         }
         
         
         function go_to_service_provider(sp_id){
         $.ajax({
                 type: 'POST',
                 url: '../lib/q_func',
                 dataType: "json",
                 data: {go_to_service_provider:"",sp_id:sp_id},
                 beforeSend: function(){
                   // $('#login_status').html('<div class="alert alert-info alert-dismissable alert-style-1"><i class="zmdi zmdi-info-outline"></i>Signing on. Checking info. Wait a moment.</div>');
                 },
                 success: function (response) {
                 	if(response['status']==1){ 
                 		window.open(response['domain'], '_blank'); 
                 	}else{
                 		get_specific_user_app_list();
         $.toast().reset('all');            
         $.toast({
         	heading: '',
         	text: 'Sorry, you are unable to access the selected app.',
         	position: 'bottom-center',
         	loaderBg:'#fec107',
         	icon: 'danger',
         	hideAfter: 3500, 
         	stack: 6
         });  
                 	}
         
             },
             error: function (xhr, error, thrown) {
                 console.log(xhr);
             }
         });
         }
         
         
         function sign_off_token(token_id){
         swal({   
             title: "Sign Off Session",   
             text: "Are you sure you want to sign-off this session?",   
             type: "warning",   
             showCancelButton: true,   
             confirmButtonColor: "#DD6B55",   
             confirmButtonText: "Yes!",   
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
                                 swal("Sign-off", "Success", "success"); 
                             }else{
                                 swal("Sign-off", "Error", "error"); 
                             }
         
                     },
                     error: function (xhr, error, thrown) {
                         console.log(xhr);
                     }
                 });
         });
         }
         
         function open_change_password(){   
         if ($('#modal_change_first_time_password').hasClass('in')) {
             // Modal is open
             console.log('Modal is open!');
             $('#default_pwd_text').text("(Default password is NIRC or Passport ID)");

            $('#modal_change_first_time_password').modal('hide');
         } else {
             // Modal is closed
             console.log('Modal is closed.');
             $('#default_pwd_text').text("");
         }
         $('#change_password_current').val('');
         $('#change_password_new').val('');
         $('#change_password_new_reconfirm').val('');
         $('#modal_change_password').modal('show');
         resetPasswordChecks();
         }
         
         $('#change_password_new').on('input', function() {
		    var password = $(this).val();

		    // Check each requirement
		    $('#p_length').html((password.length >= 8 ? '✅' : '❌') + ' At least 8 characters');
		    $('#p_lowercase').html((/[a-z]/.test(password) ? '✅' : '❌') + ' At least one lowercase letter');
		    $('#p_uppercase').html((/[A-Z]/.test(password) ? '✅' : '❌') + ' At least one uppercase letter');
		    $('#p_number').html((/\d/.test(password) ? '✅' : '❌') + ' At least one number');
		    $('#p_special').html((/[\W_]/.test(password) ? '✅' : '❌') + ' At least one special character');
		});

         function checkPasswordStrength(password) {
		    var strongRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;

		    if (password.length === 0) {
		        return { message: '', color: '' };
		    } else if (!strongRegex.test(password)) {
		        return { status:0,message: 'Weak password: must include uppercase, lowercase, number, symbol and be at least 8 characters.', color: 'red' };
		    } else {
		        return { status:1,message: 'Strong password', color: 'green' };
		    }
		}

		function resetPasswordChecks() {
		    $('#length').html('❌ At least 8 characters');
		    $('#lowercase').html('❌ At least one lowercase letter');
		    $('#uppercase').html('❌ At least one uppercase letter');
		    $('#number').html('❌ At least one number');
		    $('#special').html('❌ At least one special character');
		}

         
         var form_change_password = $('#form_change_password');
         form_change_password.on('submit', function(ev){
             ev.preventDefault();

              var password = $('#change_password_new').val();
              var password2 = $('#change_password_new_reconfirm').val();
			  var checking = checkPasswordStrength(password);
			  if(checking.status == 0){
			  	$.toast().reset('all');                    
				$.toast({
							heading: '',
							text: checking.message,
							position: 'bottom-center',
							loaderBg:'#fec107',
							icon: 'error',
							hideAfter: 3500, 
							stack: 6
				});  
			  	return;
			  }
			  if(password != password2){
			  	$.toast().reset('all');                    
				$.toast({
							heading: '',
							text: "New password & confirmation password does not match",
							position: 'bottom-center',
							loaderBg:'#fec107',
							icon: 'error',
							hideAfter: 3500, 
							stack: 6
				});  
			  	return;
			  }


             var data = $('#form_change_password').serializeArray();
             data.push({name: 'action_change_password', value: ''});
                 $.ajax({
                         type: 'POST',
                         url: '../lib/q_func',
                         dataType: "json",
                         data:data,
                         beforeSend: function(){
                         },
                         success: function (response) {
                             if (response['status'] == 1){  
                             	$('#modal_change_password').modal('hide');      
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
						                             }else{                        
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

        
         
         function countdownTimeStart(){
         
         var countDownDate = new Date("<?php echo date('M d, Y  H:i:s', strtotime('+30 minute', strtotime(LOCAL_COOKIES_HANDLER()->sso_dt)));?>").getTime();
         console.log(countDownDate);// Update the count down every 1 second
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
         
         window.onload = function () {
         countdownTimeStart()
         };
         
         
      </script>
   </body>
</html>