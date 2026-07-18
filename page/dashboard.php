<?php
   require_once __DIR__ . '/../lib/session_security.php';
   oneid_start_secure_session();
   require_once __DIR__ . '/../lib/config.php';
   require_once __DIR__ . '/../lib/SSO_IDP_INC.php';
   require_once __DIR__ . '/../lib/request_security.php';
   oneid_require_authenticated_page();
   oneid_require_active_sso_page($operation);
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
      <link href="../assetsM/css/sweetalert.css" rel="stylesheet" type="text/css">
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
     class="modal"
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
                        <input type="text" name="username" value="<?php echo htmlspecialchars((string) $_SESSION['login_user'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="username" hidden>
                        <div class="row">
                           <div class="col-lg-12">
                              <div class="">
                                 <div class="panel-wrapper collapse in">
                                    <div class="panel-body pa-0">
                                       <div class="col-sm-12 col-xs-12">
                                          <div class="form-wrap">
                                             <div class="form-body overflow-hide">
                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="change_password_current"><span id="default_pwd_text">Kata Laluan Semasa</span></label>
                                                   <input type="password" class="form-control" id="change_password_current" name="change_password_current" autocomplete="current-password" required>
                                                </div>
                                                <hr/>
                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="change_password_new">Kata Laluan Baharu</label>
                                                   <input type="password" class="form-control" id="change_password_new" name="change_password_new" autocomplete="new-password" minlength="12" required>
                                                </div>
                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="change_password_new_reconfirm">Sahkan Kata Laluan Baharu</label>
                                                   <input type="password" class="form-control" id="change_password_new_reconfirm" name="change_password_new_reconfirm" autocomplete="new-password" minlength="12" required>
                                                </div>
                                                <ul id="password-requirements" style="list-style: none; padding-left: 0; margin-top: 10px;">
														  <li id="p_length">❌ Sekurang-kurangnya 12 aksara</li>
																<li id="p_lowercase">❌ Sekurang-kurangnya satu huruf kecil</li>
																<li id="p_uppercase">❌ Sekurang-kurangnya satu huruf besar</li>
																<li id="p_number">❌ Sekurang-kurangnya satu nombor</li>
																<li id="p_special">❌ Sekurang-kurangnya satu aksara khas</li>
            												</ul>
                                             </div>
                                             <div id="password_change_feedback" class="alert" role="alert" aria-live="assertive" style="display:none;user-select:text;-webkit-user-select:text;">
                                                <p id="password_change_feedback_text" style="white-space:pre-wrap;margin-bottom:8px;"></p>
                                                <button type="button" class="btn btn-xs btn-default" id="password_change_copy_button" onclick="copyPasswordChangeFeedback();"><i class="fa fa-copy" aria-hidden="true"></i> Salin mesej</button>
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
                        <button type="submit" class="btn btn-primary waves-effect" id="btn_change_password_submit"><span id="change_password_submit_label">Tukar</span></button>
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
                                       <div class="user-app-panel">
                                          <div class="user-app-header">
                                             <div>
                                                <span class="user-app-eyebrow">My application directory</span>
                                                <h4 class="user-app-title">Sistem Aplikasi</h4>
                                                <p class="user-app-intro">Akses semua sistem yang telah diberikan kepada akaun anda.</p>
                                             </div>
                                             <div class="user-app-header-actions">
                                                <div class="user-app-summary" aria-live="polite" aria-label="Ringkasan aplikasi">
                                                   <div class="user-app-count">
                                                      <span>Jumlah</span>
                                                      <strong id="user_app_count">&mdash;</strong>
                                                   </div>
                                                   <div class="user-app-count is-sso">
                                                      <span>Full SSO</span>
                                                      <strong id="user_app_sso_count">&mdash;</strong>
                                                   </div>
                                                   <div class="user-app-count is-non-sso">
                                                      <span>Non SSO</span>
                                                      <strong id="user_app_non_sso_count">&mdash;</strong>
                                                   </div>
                                                </div>
                                                <button type="button" class="user-app-refresh" onclick="get_specific_user_app_list();" title="Refresh applications" aria-label="Refresh applications">
                                                   <i class="fa fa-refresh" aria-hidden="true"></i>
                                                </button>
                                             </div>
                                          </div>

                                          <div class="user-app-category-card">
                                             <div>
                                                <h5>Application categories</h5>
                                             </div>
                                             <div class="user-app-search">
                                                <i class="fa fa-search" aria-hidden="true"></i>
                                                <label class="sr-only" for="user_app_search">Cari aplikasi</label>
                                                <input type="search" id="user_app_search" autocomplete="off" placeholder="Cari nama atau fungsi aplikasi">
                                                <button type="button" id="user_app_search_clear" title="Kosongkan carian" aria-label="Kosongkan carian" hidden>
                                                   <i class="fa fa-times" aria-hidden="true"></i>
                                                </button>
                                             </div>
                                             <ul role="tablist" class="nav" id="WebAppsTabsHeader"></ul>
                                          </div>

                                          <div id="app_list_loading" class="user-app-state is-loading" style="display:none;">
                                             <span><i class="fa fa-circle-o-notch fa-spin" aria-hidden="true"></i></span>
                                             <strong>Loading applications</strong>
                                             <small>Please wait while your access list is retrieved.</small>
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
      <script src="../vendors/bower_components/jquery-toast-plugin/dist/jquery.toast.min.js"></script>
      <script src="../assetsM/js/oneid-notifications.js?v=20260716-1"></script>
      <!-- Init JavaScript -->
      <script src="../dist/js/init.js?v=20260716-1"></script>
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
         
         
         var userAppDirectoryGroups = [];
         var userAppSearchTerm = '';
         var userAppActiveTab = '#user_app_favourites_tab';

         function userAppText(value){
            return $('<div>').text(value == null ? '' : value).html();
         }

         function userAppMatches(application, term){
            if (term === '') {
               return true;
            }
            var searchable = [application.sp_name, application.sp_description]
               .map(function(value){ return String(value || '').toLocaleLowerCase(); })
               .join(' ');
            return searchable.indexOf(term) !== -1;
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

         function userAppCard(application, index){
            var appId = userAppText(application.sp_id);
            var appName = userAppText(application.sp_name);
            var appDescription = userAppText(application.sp_description);
            var appImage = userAppText(application.sp_image);
            var imageSource = appImage === '' ? '../img/thumb-1.jpg' : '../public_img/' + appImage;
            var isDirect = String(application.sp_sso_support) !== '0';
            var isFavourite = Number(application.is_favourite) === 1;
            var favouriteTitle = isFavourite ? 'Buang daripada Favourite' : 'Tambah ke Favourite';
            var accessLabel = isDirect ? 'Akses terus' : 'OneID SSO';
            var buttonLabel = isDirect ? 'Akses' : 'Login';
            var buttonTitle = isDirect ? 'Akses aplikasi' : 'Login dengan OneID';

            var card = '<article class="user-app-card">';
            card += '<div class="user-app-index">'+index+'</div>';
            card += '<div class="user-app-image"><img src="'+imageSource+'" alt="" loading="lazy" onerror="this.onerror=null;this.src=\'../img/thumb-1.jpg\';"></div>';
            card += '<div class="user-app-content"><div class="user-app-name"><strong title="'+appName+'">'+appName+'</strong><span class="user-app-access '+(isDirect ? 'is-direct' : '')+'">'+accessLabel+'</span></div>';
            card += '<p title="'+appDescription+'">'+appDescription+'</p></div>';
            card += '<div class="user-app-actions">';
            card += '<button type="button" class="user-app-favourite '+(isFavourite ? 'is-selected' : '')+'" data-app-id="'+appId+'" data-favourite="'+(isFavourite ? '1' : '0')+'" aria-pressed="'+(isFavourite ? 'true' : 'false')+'" title="'+favouriteTitle+'" aria-label="'+favouriteTitle+'"><i class="fa fa-star" aria-hidden="true"></i></button>';
            card += '<button type="button" class="user-app-open '+(isDirect ? 'is-direct' : '')+'" data-app-id="'+appId+'" title="'+buttonTitle+'"><i class="fa '+(isDirect ? 'fa-external-link' : 'fa-sign-in')+'" aria-hidden="true"></i><span>'+buttonLabel+'</span></button>';
            card += '</div></article>';
            return card;
         }

         function renderUserAppDirectory(){
            var term = userAppSearchTerm.trim().toLocaleLowerCase();
            var allApplications = userAppUniqueApplications();
            var favouriteApplications = allApplications.filter(function(application){
               return Number(application.is_favourite) === 1 && userAppMatches(application, term);
            });
            var tabs = '';
            var panes = '';
            var matchingTabs = [];
            var requestedTab = userAppActiveTab;

            if (allApplications.length === 0) {
               $('#user_app_count').text('0');
               $('#user_app_sso_count, #user_app_non_sso_count').text('0');
               $('#WebAppsTabsHeader, #WebAppsTabsContent').html('');
               $('#follo_data_list').html(
                  '<div class="user-app-state"><span><i class="fa fa-th-large" aria-hidden="true"></i></span>' +
                  '<strong>No accessible applications</strong>' +
                  '<small>Please contact PTMK if you require access to a system.</small></div>'
               );
               return;
            }

            tabs += '<li class="is-favourite-tab" role="presentation">';
            tabs += '<a data-toggle="tab" role="tab" href="#user_app_favourites_tab" title="Favourite" aria-label="Favourite"><i class="fa fa-star" aria-hidden="true"></i><strong>'+favouriteApplications.length+'</strong></a></li>';
            panes += '<div id="user_app_favourites_tab" class="tab-pane fade" role="tabpanel"><div class="user-app-list">';
            if (favouriteApplications.length === 0) {
               panes += '<div class="user-app-category-empty"><i class="fa fa-star-o" aria-hidden="true"></i><span>'+(term === '' ? 'Belum ada aplikasi Favourite. Pilih ikon bintang pada aplikasi yang kerap digunakan.' : 'Tiada aplikasi Favourite sepadan dengan carian.')+'</span></div>';
            } else {
               matchingTabs.push('#user_app_favourites_tab');
               $.each(favouriteApplications, function(index, application){ panes += userAppCard(application, index + 1); });
            }
            panes += '</div></div>';

            $.each(userAppDirectoryGroups, function(index, group){
               var groupId = String(group.sp_group_id || index);
               var paneId = 'user_app_group_' + groupId.replace(/[^A-Za-z0-9_-]/g, '');
               var groupNameRaw = String(group.sp_group_name || 'Uncategorized');
               var groupName = userAppText(groupNameRaw);
               var isNonSso = groupNameRaw.replace(/\s+/g, ' ').trim().toUpperCase() === 'NON SSO';
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
                  panes += '<div class="user-app-category-empty"><i class="fa fa-inbox" aria-hidden="true"></i><span>'+(term === '' ? 'Tiada aplikasi dalam kategori ini.' : 'Tiada aplikasi sepadan dengan carian dalam kategori ini.')+'</span></div>';
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
            $('#WebAppsTabsHeader').html(tabs);
            $('#WebAppsTabsContent').html(panes);

            if (matchingTabs.indexOf(requestedTab) === -1) {
               requestedTab = matchingTabs.length > 0 ? matchingTabs[0] : '#user_app_favourites_tab';
            }
            var $requestedLink = $('#WebAppsTabsHeader a[href="'+requestedTab+'"]');
            if ($requestedLink.length) {
               $requestedLink.tab('show');
               userAppActiveTab = requestedTab;
            }
         }

         //----Login
         function get_specific_user_app_list(){
            var href = $('#WebAppsTabsHeader li.active a').attr('href');
            if (href) {
               userAppActiveTab = href;
            }
         $.ajax({
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
				   renderUserAppDirectory();
				},
				error: function (xhr, error, thrown) {
                  $('#user_app_count, #user_app_sso_count, #user_app_non_sso_count').text('\u2014');
				   $('#app_list_loading').hide();
				   $('#app_list').show();
				   $('#follo_data_list').html(
					  '<div class="user-app-state is-error">' +
					  '<span><i class="fa fa-exclamation-triangle" aria-hidden="true"></i></span>' +
					  '<strong>Unable to load applications</strong>' +
					  '<small>Please retry or contact BTMK if the issue continues.</small>' +
					  '</div>'
				   );
				}
         });
         }

         $(document).on('input', '#user_app_search', function(){
            userAppSearchTerm = String(this.value || '');
            $('#user_app_search_clear').prop('hidden', userAppSearchTerm === '');
            renderUserAppDirectory();
         });

         $(document).on('click', '#user_app_search_clear', function(){
            userAppSearchTerm = '';
            $('#user_app_search').val('').focus();
            $(this).prop('hidden', true);
            renderUserAppDirectory();
         });

         $(document).on('shown.bs.tab', '#WebAppsTabsHeader a[data-toggle="tab"]', function(){
            userAppActiveTab = $(this).attr('href');
         });

         $(document).on('click', '.user-app-open', function(){
            go_to_service_provider(String($(this).data('app-id') || ''));
         });

         $(document).on('click', '.user-app-favourite', function(){
            var $button = $(this);
            var appId = String($button.data('app-id') || '');
            var enabled = String($button.data('favourite')) === '1' ? '0' : '1';
            $button.prop('disabled', true).addClass('is-saving');

            $.ajax({
               type: 'POST',
               url: '../lib/q_func',
               dataType: 'json',
               data: {user_set_app_favourite: '', sp_id: appId, enabled: enabled},
               success: function(response){
                  if (Number(response.status) !== 1) {
                     return;
                  }
                  $.each(userAppDirectoryGroups, function(_, group){
                     $.each(Array.isArray(group.data) ? group.data : [], function(__, application){
                        if (String(application.sp_id) === appId) {
                           application.is_favourite = Number(response.is_favourite) === 1 ? 1 : 0;
                        }
                     });
                  });
                  renderUserAppDirectory();
               },
               error: function(){
                  $button.prop('disabled', false).removeClass('is-saving');
                  $.toast().reset('all');
                  $.toast({
                     heading: 'Favourite tidak dapat disimpan',
                     text: 'Sila cuba semula atau hubungi PTMK jika masalah berterusan.',
                     position: 'bottom-center',
                     loaderBg: '#fec107',
                     icon: 'error',
                     hideAfter: 3500,
                     stack: 4
                  });
               }
            });
         });
         
         
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
             $('#default_pwd_text').text("Kata Laluan Semasa");

            $('#modal_change_first_time_password').modal('hide');
         } else {
             // Modal is closed
             $('#default_pwd_text').text("Kata Laluan Semasa");
         }
         $('#change_password_current').val('');
         $('#change_password_new').val('');
         $('#change_password_new_reconfirm').val('');
         $('#password_change_feedback').hide().removeClass('alert-success alert-danger alert-info');
         $('#password_change_feedback_text').text('');
         resetPasswordChecks();
         $('#modal_change_password').modal('show');
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
		    $('#p_length').html('❌ At least 12 characters');
		    $('#p_lowercase').html('❌ At least one lowercase letter');
		    $('#p_uppercase').html('❌ At least one uppercase letter');
		    $('#p_number').html('❌ At least one number');
		    $('#p_special').html('❌ At least one special character');
		}

         var passwordChangeSubmitting = false;
         function setPasswordChangeSubmitting(submitting){
            passwordChangeSubmitting = submitting;
            $('#btn_change_password_submit').prop('disabled', submitting).attr('aria-busy', submitting ? 'true' : 'false');
            $('#change_password_submit_label').text(submitting ? 'Menukar...' : 'Tukar');
            $('#change_password_current, #change_password_new, #change_password_new_reconfirm').prop('disabled', submitting);
         }
         function passwordChangeFeedback(response, success){
            var code=response&&response.code?response.code:'UC1_RESPONSE_INVALID';
            var reference=response&&response.correlation_id?response.correlation_id:'Unavailable';
            return (response&&response.msg?response.msg:(success?'Password successfully changed.':'Password was not changed.'))+' Code: '+code+'. Reference: '+reference+'.';
         }
         function showPasswordChangeFeedback(message,type){
            var panel=$('#password_change_feedback');panel.removeClass('alert-success alert-danger alert-info').addClass(type==='success'?'alert-success':(type==='info'?'alert-info':'alert-danger'));
            $('#password_change_feedback_text').text(message);panel.show();
         }
         function copyPasswordChangeFeedback(){
            var text=$('#password_change_feedback_text').text();if(!text){return;}
            if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(text).then(function(){$('#password_change_copy_button').text('Disalin');});return;}
            var area=$('<textarea>').val(text).css({position:'fixed',left:'-9999px'}).appendTo('body');area[0].select();document.execCommand('copy');area.remove();$('#password_change_copy_button').text('Disalin');
         }

         var form_change_password = $('#form_change_password');
         form_change_password.on('submit', function(ev){
             ev.preventDefault();
             if(passwordChangeSubmitting){return;}

              var password = $('#change_password_new').val();
              var password2 = $('#change_password_new_reconfirm').val();
			  var checking = checkPasswordStrength(password);
			  if(checking.status == 0){
				showPasswordChangeFeedback(checking.message,'error');
			  	return;
			  }
			  if(password != password2){
				showPasswordChangeFeedback('New password and confirmation password do not match.','error');
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
                           setPasswordChangeSubmitting(true);
                         },
                         success: function (response) {
                             if (response['status'] == 1){  
                                if(response.csrf_token){$.ajaxSetup({headers:{'X-CSRF-Token':response.csrf_token}});}
								showPasswordChangeFeedback(passwordChangeFeedback(response,true),'success');
								$('#change_password_current, #change_password_new, #change_password_new_reconfirm').val('');
								if(response.reauthentication_required&&response.redirect_uri){showPasswordChangeFeedback(passwordChangeFeedback(response,true)+'\nYou will be redirected to sign in again.','success');setTimeout(function(){window.location.href=response.redirect_uri;},3500);}
								                             }else{
								showPasswordChangeFeedback(passwordChangeFeedback(response,false),'error');
                             }
         
                     },
                     error: function (xhr, error, thrown) {
                        showPasswordChangeFeedback('Password was not changed. The request failed with HTTP '+xhr.status+'.','error');
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

      #WebAppsTabsHeader {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 0;
        padding: 0;
        border: 0;
      }

      #WebAppsTabsHeader > li {
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

      @media (max-width: 767px) {
        .user-app-panel {
          padding: 20px 15px;
        }

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
  object-fit: cover; /* Prevent image distortion. */
}




   </style>
   </body>
</html>
