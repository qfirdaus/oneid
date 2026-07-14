<?php
   require_once __DIR__ . '/../lib/session_security.php';
   oneid_start_secure_session();
   require_once __DIR__ . '/../lib/config.php';
   require_once __DIR__ . '/../lib/SSO_IDP_INC.php';
   require_once __DIR__ . '/../lib/request_security.php';
   oneid_require_authenticated_page();
   $user_info = $operation->admin_search_user_account($_SESSION['login_user']);
   // echo "Xxxxx" . $_SESSION['user'];
    // echo json_encode($user_info);
   ?>
<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
      <title>ONEID@UPNM - Gerbang Tunggal ke Sistem Digital UPNM</title>
      <!-- Favicon -->
      <link rel="shortcut icon" href="favicon.ico">
      <link rel="icon" href="favicon.ico" type="image/x-icon">
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


      </style>
   </head>
   <body>
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
                                             <i class="zmdi zmdi-info-outline"></i>Selamat Datang ke Portal OneID@UPNM. Sila tukar kata laluan baharu yang lebih selamat.
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
<div id="modal_faq" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="faqModalLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">

      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">×</button>
        <h5 class="modal-title" id="faqModalLabel">Soalan Lazim (FAQ) — OneID@UPNM</h5>
      </div>

      <div class="modal-body modal-body-scroll">
        <!-- Accordion (Bootstrap 3 panel-group) -->
        <div class="panel-group" id="faqAccordion" role="tablist" aria-multiselectable="false">

          <!-- FAQ 1 -->
          <div class="panel panel-default">
            <div class="panel-heading" role="tab" id="faq_h1">
              <h6 class="panel-title">
                <a role="button" data-toggle="collapse" data-parent="#faqAccordion" href="#faq_c1"
                   aria-expanded="true" aria-controls="faq_c1">
                  Apakah OneID@UPNM?
                </a>
              </h6>
            </div>
            <div id="faq_c1" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="faq_h1">
              <div class="panel-body">
                OneID@UPNM ialah satu platform Single Sign-On (SSO) yang memudahkan pengguna mengakses pelbagai sistem
                dengan satu log masuk sahaja. Sistem atau aplikasi yang belum diintegrasikan akan disediakan dalam bentuk
                pautan agar tetap boleh diakses melalui OneID@UPNM.
              </div>
            </div>
          </div>

          <!-- FAQ 2 -->
          <div class="panel panel-default">
            <div class="panel-heading" role="tab" id="faq_h2">
              <h6 class="panel-title">
                <a class="collapsed" role="button" data-toggle="collapse" data-parent="#faqAccordion" href="#faq_c2"
                   aria-expanded="false" aria-controls="faq_c2">
                  Siapakah yang boleh menggunakan OneID@UPNM ini?
                </a>
              </h6>
            </div>
            <div id="faq_c2" class="panel-collapse collapse" role="tabpanel" aria-labelledby="faq_h2">
              <div class="panel-body">
                Semua warga UPNM – iaitu staf yang berdaftar dalam Sistem Maklumat Staf dan pelajar yang berdaftar dalam
                Sistem Maklumat Pelajar.
              </div>
            </div>
          </div>

          <!-- FAQ 3 -->
          <div class="panel panel-default">
            <div class="panel-heading" role="tab" id="faq_h3">
              <h6 class="panel-title">
                <a class="collapsed" role="button" data-toggle="collapse" data-parent="#faqAccordion" href="#faq_c3"
                   aria-expanded="false" aria-controls="faq_c3">
                  Bagaimanakah cara untuk log masuk ke OneID@UPNM?
                </a>
              </h6>
            </div>
            <div id="faq_c3" class="panel-collapse collapse" role="tabpanel" aria-labelledby="faq_h3">
              <div class="panel-body">
                Log masuk nombor staf (format sebenar) atau nombor pelajar sebagai ID pengguna, dan nombor kad
                pengenalan (tanpa sengkang) sebagai kata laluan awal. Selepas log masuk pertama, anda perlu menukar
                kata laluan mengikut piawaian keselamatan.
              </div>
            </div>
          </div>

          <!-- FAQ 4 -->
          <div class="panel panel-default">
            <div class="panel-heading" role="tab" id="faq_h4">
              <h6 class="panel-title">
                <a class="collapsed" role="button" data-toggle="collapse" data-parent="#faqAccordion" href="#faq_c4"
                   aria-expanded="false" aria-controls="faq_c4">
                  Adakah sistem ini selamat?
                </a>
              </h6>
            </div>
            <div id="faq_c4" class="panel-collapse collapse" role="tabpanel" aria-labelledby="faq_h4">
              <div class="panel-body">
                Ya. OneID@UPNM dibangunkan dengan piawaian keselamatan terkini, termasuk penggunaan token API untuk
                pengesahan, ciri log keluar automatik (session timeout) bagi mengelakkan akses tanpa kebenaran, serta
                pengesahan akaun OneID melalui emel rasmi UPNM untuk memastikan identiti pengguna yang sah.
              </div>
            </div>
          </div>

          <!-- FAQ 5 -->
          <div class="panel panel-default">
            <div class="panel-heading" role="tab" id="faq_h5">
              <h6 class="panel-title">
                <a class="collapsed" role="button" data-toggle="collapse" data-parent="#faqAccordion" href="#faq_c5"
                   aria-expanded="false" aria-controls="faq_c5">
                  Bolehkah saya log masuk di lebih dari satu peranti pada masa yang sama?
                </a>
              </h6>
            </div>
            <div id="faq_c5" class="panel-collapse collapse" role="tabpanel" aria-labelledby="faq_h5">
              <div class="panel-body">
                Ya, anda boleh log masuk pada beberapa peranti dalam masa yang sama. Namun, demi keselamatan data, log
                keluar (logout) dari peranti yang tidak digunakan amat digalakkan.
              </div>
            </div>
          </div>

          <!-- FAQ 6 -->
          <div class="panel panel-default">
            <div class="panel-heading" role="tab" id="faq_h6">
              <h6 class="panel-title">
                <a class="collapsed" role="button" data-toggle="collapse" data-parent="#faqAccordion" href="#faq_c6"
                   aria-expanded="false" aria-controls="faq_c6">
                  Jika saya keluar/log out dari satu aplikasi, adakah saya akan log out dari semua?
                </a>
              </h6>
            </div>
            <div id="faq_c6" class="panel-collapse collapse" role="tabpanel" aria-labelledby="faq_h6">
              <div class="panel-body">
                Tidak. Log keluar dari satu aplikasi hanya akan menamatkan sesi untuk aplikasi tersebut sahaja. Aplikasi
                lain yang anda akses melalui OneID masih kekal aktif.
              </div>
            </div>
          </div>

          <!-- FAQ 7 -->
          <div class="panel panel-default">
            <div class="panel-heading" role="tab" id="faq_h7">
              <h6 class="panel-title">
                <a class="collapsed" role="button" data-toggle="collapse" data-parent="#faqAccordion" href="#faq_c7"
                   aria-expanded="false" aria-controls="faq_c7">
                  Apa yang perlu saya buat jika terlupa kata laluan?
                </a>
              </h6>
            </div>
            <div id="faq_c7" class="panel-collapse collapse" role="tabpanel" aria-labelledby="faq_h7">
              <div class="panel-body">
                Jika anda terlupa kata laluan, klik butang “Forgot Password” di laman utama OneID@UPNM. Sistem akan
                menghantar kod OTP ke e-mel rasmi UPNM anda. Selepas anda masukkan kod tersebut, sistem akan membawa
                anda ke halaman untuk menetapkan semula kata laluan baharu sebelum boleh teruskan akses ke sistem.
              </div>
            </div>
          </div>

          <!-- FAQ 8 -->
          <div class="panel panel-default">
            <div class="panel-heading" role="tab" id="faq_h8">
              <h6 class="panel-title">
                <a class="collapsed" role="button" data-toggle="collapse" data-parent="#faqAccordion" href="#faq_c8"
                   aria-expanded="false" aria-controls="faq_c8">
                  Apa syarat kata laluan yang dibenarkan?
                </a>
              </h6>
            </div>
            <div id="faq_c8" class="panel-collapse collapse" role="tabpanel" aria-labelledby="faq_h8">
              <div class="panel-body">
                Kata laluan mestilah minimum 12 aksara, mengandungi kombinasi huruf besar dan huruf kecil, nombor, serta
                simbol khas.
              </div>
            </div>
          </div>

        </div>
        <!-- /Accordion -->
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-info" data-dismiss="modal">Tutup</button>
      </div>

    </div>
  </div>
</div>


         <div id="modal_change_password"
     class="modal fade"
     tabindex="-1"
     role="dialog"
     aria-labelledby="aria_modal_change_password"
     aria-hidden="true"
     data-backdrop="static"
     data-keyboard="false">
            <div class="modal-dialog">
               <div class="modal-content">
                  <div class="modal-header">
                    <!-- <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button> -->
                     <h5 class="modal-title" id="aria_modal_change_password">Tukar Kata Laluan</h5>
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
                                                   <label class="control-label mb-10" for="change_password_current"><span id="default_pwd_text"></span></label>
                                                   <input type="password" class="form-control" id="change_password_current" name="change_password_current" placeholder="" required="">
                                                </div>
                                                <hr/>
                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="change_password_new">Kata Laluan Baharu</label>
                                                   <input type="password" class="form-control" id="change_password_new" name="change_password_new" placeholder="" required="">
                                                </div>
                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="change_password_new_reconfirm">Sahkan Kata Laluan Baharu</label>
                                                   <input type="password" class="form-control" id="change_password_new_reconfirm" name="change_password_new_reconfirm" placeholder="" required="">
                                                </div>
                                                <ul id="password-requirements" style="list-style: none; padding-left: 0; margin-top: 10px;">
            												  <li id="p_length">❌ Sekurang-kurangnya 8 aksara</li>
																<li id="p_lowercase">❌ Sekurang-kurangnya satu huruf kecil</li>
																<li id="p_uppercase">❌ Sekurang-kurangnya satu huruf besar</li>
																<li id="p_number">❌ Sekurang-kurangnya satu nombor</li>
																<li id="p_special">❌ Sekurang-kurangnya satu aksara khas</li>
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
                        <!--<button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Close</button>-->
						
                        <button type="button" class="btn btn-danger waves-effect" id="chge_pwd_logout" onclick="logout();">Log Keluar</button>
                        <button type="submit" class="btn btn-primary waves-effect">Tukar</button>
                        <button id="btn_close_changePW" type="button" class="btn btn-secondary text-primary" data-dismiss="modal">Tutup</button>
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
                  <div class="col-sm-4">
                     <div class="row">
                        <div class="col-sm-12">
                           <div class="panel panel-default card-view  pa-0">
                              <div class="panel-wrapper collapse in">
                                 <div class="panel-body  pa-0">
                                    <div class="profile-box">
                                       	<div class="profile-cover-pic">
                                          <!-- <div class="profile-image-overlay"></div> -->
                                          </div>
                                       <div class="profile-info text-center mb-15">
                                          <div class="profile-img-wrap">
                                             <img id="user_photos" class="inline-block mb-10" src="../img/mock1.jpg" alt="user"/>
                                             </div>	
                                          <h6 class="block mt-10 weight-500 capitalize-font txt-dark"><?php echo $_SESSION['user']; ?> (<?= (trim($user_info['data3']) == "") ? $user_info['data4'] : $user_info['data3']; ?>)</h6>
                                          <span class="block capitalize-font"><?php echo $user_info['data6']; ?></span>
                                          <span class="block capitalize-font"><?php echo $user_info['data7']; ?></span>
                                          <span class="time block truncate txt-grey"></span>
                                       </div>
                                    </div>





                                    <div class="pills-struct vertical-pills mt-40">
                                      <!-- Vertical nav -->
                                      <ul role="tablist" class="nav nav-pills ver-nav-pills" id="myTabs_8">
                                        <?php if($_SESSION['login_user_type'] == 1){ ?>
                                          <li role="presentation" class="pill-yellow" style="cursor: pointer !important;" >
                                          <a id="tab_faq" href="../admin/dashboard">
                                            <span>Administrator<span class="inline-block"></span></span>
                                          </a>
                                        </li>
                                       <?php } ?>
                                        <li class="active" role="presentation">
                                          <a aria-expanded="true" data-toggle="tab" role="tab" id="follo_tab_8" href="#follo_8">
                                            <span>Sistem Aplikasi <span class="inline-block" id="follo_data_list_count_text"></span></span>
                                          </a>
                                        </li>
                                        <li role="presentation" style="cursor: pointer !important;" onclick="window.open('https://directory.upnm.edu.my/', '_blank'); return false;">
                                          <a id="tab_faq" >
                                            <span>Direktori Staf <span class="inline-block"></span></span>
                                          </a>
                                        </li>
                                        <li role="presentation" style="cursor: pointer !important;" onclick="open_faq();">
                                          <a id="tab_faq" >
                                            <span>Soalan Lazim (FAQ) <span class="inline-block"></span></span>
                                          </a>
                                        </li>
                                        <!--<li role="presentation">
                                          <a data-toggle="tab" id="security_tab_1" role="tab" href="#security_tab" aria-expanded="false">
                                            <span>Signed Devices</span>
                                          </a>
                                        </li>-->
                                       <li role="presentation" style="cursor: pointer !important;" onclick="open_change_password(0);">
                                          <a id="tab_chang_pwd" >
                                            <span>Tukar Katalaluan <span class="inline-block"></span></span>
                                          </a>
                                        </li>
                                        <li role="presentation" style="cursor: pointer !important;" >
                                          <a id="tab_faq" href="logout">
                                            <span>Log Keluar<span class="inline-block"></span></span>
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
                                 <div  class="panel-body pb-0">


                                 <!-- Tab content -->
                                   <div class="tab-content" id="myTabContent_8">

                                     <!-- Applications -->
                                     <div id="follo_8" class="tab-pane fade active in" role="tabpanel">
                                       <div class="panel-heading">
                                         <div class="pull-left">
                                           <h6 class="panel-title txt-dark">Senarai Sistem Aplikasi</h6>
                                           <!-- <span class="block txt-primary font-12 pt-5 capitalize-font">
                                             Your session will expire in <span id="demo">- - -</span>
                                           </span> -->
                                         </div>
                                         <div class="pull-right">
                                           <a href="#" class="pull-left inline-block refresh mr-15" onclick="get_specific_user_app_list()">
                                             <i class="zmdi zmdi-replay text-primary"></i>
                                           </a>
                                         </div>
                                         <div class="clearfix"></div>
                                       </div>

                                       <div class="pills-struct mt-20 pl-20">
                                         <ul role="tablist" class="nav nav-pills nav-pills-rounded" id="WebAppsTabsHeader"></ul>
                                         <div class="tab-content" id="WebAppsTabsContent"></div>
                                       </div>

                                       <div id="app_list_loading" style="display:none;">
                                         <br/>
                                         <div class="col-lg-12">
                                           <div class="progress progress-lg">
                                             <div class="progress-bar progress-bar-primary active progress-bar-striped"
                                                  aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"
                                                  style="width: 100%" role="progressbar">
                                               Loading App.. Wait a moment..
                                             </div>
                                           </div>
                                         </div>
                                         <br/><br/>
                                       </div>

                                       <div class="followers-wrap" id="app_list">
                                         <ul class="followers-list-wrap">
                                           <li class="follow-list">
                                             <div class="follo-body" id="follo_data_list"></div>
                                           </li>
                                         </ul>
                                       </div>
                                     </div>

                                     <!-- Signed Devices -->
                                     <div id="security_tab" class="tab-pane fade" role="tabpanel">
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

                                       <div id="app_security_session_loading" style="display:none;">
                                         <br/>
                                         <div class="col-lg-12">
                                           <div class="progress progress-lg">
                                             <div class="progress-bar progress-bar-primary active progress-bar-striped"
                                                  aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"
                                                  style="width: 100%" role="progressbar">
                                               Loading Active Session.. Wait a moment..
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
                        <p><b>2025</b> © BTMK.UPNM Version 1.0.3</p>
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
         $.ajaxSetup({
            headers: {'X-CSRF-Token': <?php echo json_encode(oneid_csrf_token()); ?>}
         });

          var user_id = "<?php echo $user_info['data2']; ?>";
          var stu_id="<?php echo $user_info['data4']; ?>";
         $(document).ready(function() {
            init_start();
             get_specific_user_app_list();
             get_specific_user_activ_session();
             check_default_password();
             startTokenRefresh();

         });

         function init_start(){
            checkImageExists('https://esmartcard.upnm.edu.my/img/staf/'+user_id+'.jpg', function(exists) {
                 if (exists) {
                   // You can set it dynamically here if needed
                    $('#user_photos').attr('src', 'https://esmartcard.upnm.edu.my/img/staf/'+user_id+'.jpg');
                 } else {
                   checkImageExists('https://kemasukan.upnm.edu.my/tawaran/pelajar/student_image/'+stu_id+'.jpg', function(exists) {
                     // alert(stu_id)
                       if (exists) {
                         // You can set it dynamically here if needed
                          $('#user_photos').attr('src', 'https://kemasukan.upnm.edu.my/tawaran/pelajar/student_image/'+stu_id+'.jpg');
                       } else {
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
                     $('#btn_close_changePW').hide();
                  }else{
                     $('#btn_close_changePW').show();
                              }
         
             },
             error: function (xhr, error, thrown) {
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
                 		$('#follo_data_list_count_text').html('('+response.length+')');
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
                                          '<div class="user-data"><span class="name block capitalize-font"><span class="label label-primary">'+(k+1)+'</span> <b>'+response[i]['data'][k]['sp_name']+'</b></span><span class="time  txt-grey">'+response[i]['data'][k]['sp_description']+'</span></div>';
                                       div += '<button class="btn btn-primary pull-right btn-xs fixed-btn  " onclick="go_to_service_provider(&quot;'+response[i]['data'][k]['sp_id']+'&quot;);"><span class="btn-text">Go</span></button>';
                                    }else{
                                       div += img_sp+'<div class="user-data"><span class="name block capitalize-font"><span class="label label-primary">'+(k+1)+'</span> <b>'+response[i]['data'][k]['sp_name']+' <span class="label label-warning">SSO Not Supported</span></b></span><span class="time  txt-grey">'+response[i]['data'][k]['sp_description']+'</span></div>';
                                       div += '<button class="btn btn-warning pull-right btn-xs fixed-btn  " onclick="go_to_service_provider(&quot;'+response[i]['data'][k]['sp_id']+'&quot;);"><span class="btn-text">Login</span></button>';
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
                                       div += img_sp+'<div class="user-data"><span class="name block capitalize-font"><span class="label label-primary">'+(k+1)+'</span> <b>'+response[i]['data'][k]['sp_name']+'</b></span><span class="time  txt-grey">'+response[i]['data'][k]['sp_description']+'</span></div>';
                                       div += '<button class="btn btn-primary pull-right btn-xs fixed-btn  " onclick="go_to_service_provider(&quot;'+response[i]['data'][k]['sp_id']+'&quot;);"><span class="btn-text">Go</span></button>';
                                    }else{
                                       div += img_sp+'<div class="user-data"><span class="name block capitalize-font"><span class="label label-primary">'+(k+1)+'</span> <b>'+response[i]['data'][k]['sp_name']+' <span class="label label-warning">SSO Not Supported</span></b></span><span class="time  txt-grey">'+response[i]['data'][k]['sp_description']+'</span></div>';
                                       div += '<button class="btn btn-warning pull-right btn-xs fixed-btn  " onclick="go_to_service_provider(&quot;'+response[i]['data'][k]['sp_id']+'&quot;);"><span class="btn-text">Login</span></button>';
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
            $('#follo_data_list_count_text').html('('+list_count+')');
            }
            
                     $('#WebAppsTabsHeader').html(li);
                     $('#WebAppsTabsContent').html(div);
                     if(href!="undefined"){
                        $('a[href="'+href+'"]').tab('show');
                     }
                     // $('#follo_data_list').html(tr);
         
             },
             error: function (xhr, error, thrown) {
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
                 tr += '<button class="btn btn-danger pull-right btn-xs fixed-btn  " onclick="sign_off_token(&quot;'+response[i]['token_id']+'&quot;);"><span class="btn-text">Sign off</span></button>';
         
         
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
                     }
                 });
         });
         }

         function open_faq(){
         $('#modal_faq').modal('show');

         }
         
         function open_change_password(type){   
		 
		 if(type==0){
			 $('#chge_pwd_logout').hide();
		 }else{
			 $('#chge_pwd_logout').show();
		 }
		 
         if ($('#modal_change_first_time_password').hasClass('in')) {
             // Modal is open
             $('#default_pwd_text').text("Masukkan Kata Laluan Semasa");

            $('#modal_change_first_time_password').modal('hide');
         } else {
             // Modal is closed
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
		    $('#p_length').html((password.length >= 12 ? '✅' : '❌') + ' At least 12 characters');
		    $('#p_lowercase').html((/[a-z]/.test(password) ? '✅' : '❌') + ' At least one lowercase letter');
		    $('#p_uppercase').html((/[A-Z]/.test(password) ? '✅' : '❌') + ' At least one uppercase letter');
		    $('#p_number').html((/\d/.test(password) ? '✅' : '❌') + ' At least one number');
		    $('#p_special').html((/[\W_]/.test(password) ? '✅' : '❌') + ' At least one special character');
		});

         function checkPasswordStrength(password) {
		    var strongRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{12,}$/;

		    if (password.length === 0) {
		        return { message: '', color: '' };
		    } else if (!strongRegex.test(password)) {
		        return { status:0,message: 'Weak password: must include uppercase, lowercase, number, symbol and be at least 12 characters.', color: 'red' };
		    } else {
		        return { status:1,message: 'Strong password', color: 'green' };
		    }
		}

		function resetPasswordChecks() {
		    $('#length').html('❌ At least 12 characters');
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
                  // Handle response, e.g., update UI
                  // $('#result').html('Posted successfully: ' + JSON.stringify(response));
              },
              error: function(xhr, status, error) {
                  console.error('Error:', "Token Had Been Removed/Expired");
                  location.reload(true);
                  // Handle errors
              }
          });
      }
      </script>
      <style>
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
  width: 120px;              /* adjust as needed */
  height: 120px;
  border-radius: 50%;        /* makes it round */
  object-fit: cover;         /* crop nicely inside circle */
  border: 4px solid #ff6028; /* ring color and thickness */
  box-shadow: 0 0 8px rgba(0,0,0,0.2); /* optional glow */
}
.profile-img-wrap {
  width: 130px; 
  height: 130px; 
  border-radius: 50%;      // make wrapper round
  overflow: hidden;        // hide white square corners
  border: 4px solid #ff6028; // ringcolor
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: transparent !important;
}

.profile-img-wrap img {
  width: 100%; 
  height: 100%; 
  border-radius: 50%; 
  object-fit: cover; // prevent squashing
}




   </style>
   </body>
</html>
