<?php
   require_once __DIR__ . '/../lib/session_security.php';
   oneid_start_secure_session();
   require_once __DIR__ . '/../lib/config.php';
   //return;
   require_once __DIR__ . '/../lib/SSO_IDP_INC.php';
   require_once __DIR__ . '/../lib/request_security.php';
   oneid_require_admin_page();
   
   $widget_data = $operation->admin_widget_count();
   $sys_config = $operation->get_system_config();
   ?>
<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
      <title>ONEID@UPNM - Gerbang Tunggal ke Sistem Digital UPNM</title>
      <!-- Favicon -->
      <link rel="shortcut icon" href="/favicon.ico">
      <link rel="icon" href="/favicon.ico" type="image/x-icon">
      <!-- Bootstrap Dropify CSS -->
      <link href="../vendors/bower_components/dropify/dist/css/dropify.min.css" rel="stylesheet" type="text/css"/>
      <!-- Morris Charts CSS -->
      <link href="../vendors/bower_components/morris.js/morris.css" rel="stylesheet" type="text/css"/>
      <!-- vector map CSS -->
      <link href="../vendors/vectormap/jquery-jvectormap-2.0.2.css" rel="stylesheet" type="text/css"/>
      <!-- Calendar CSS -->
      <link href="../vendors/bower_components/fullcalendar/dist/fullcalendar.css" rel="stylesheet" type="text/css"/>
      <!-- Data table CSS -->
      <link href="../vendors/bower_components/datatables/media/css/jquery.dataTables.min.css" rel="stylesheet" type="text/css"/>

      <!-- Bootstrap Daterangepicker CSS -->
      <link href="../vendors/bower_components/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet" type="text/css"/>
       
      <!-- Bootstrap Switches CSS -->
      <link href="../vendors/bower_components/bootstrap-switch/dist/css/bootstrap3/bootstrap-switch.min.css" rel="stylesheet" type="text/css"/>
      <link href="../vendors/bower_components/sweetalert/dist/sweetalert.css" rel="stylesheet" type="text/css">
      <link href="../vendors/bower_components/jquery-toast-plugin/dist/jquery.toast.min.css" rel="stylesheet" type="text/css">
      <!-- Custom CSS -->
      <link href="../dist/css/style.css" rel="stylesheet" type="text/css">
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
         <div id="modal_user_profile" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="aria_modal_edit_app" aria-hidden="true">
            <div class="modal-dialog">
               <div class="modal-content">
                  <div class="modal-header">
                     <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                     <h5 class="modal-title" id="aria_modal_edit_app">User Info</h5>
                  </div>
                  <form id="form_editXss_app">
                     <div class="modal-body">
                        <!-- Row -->
                        <div class="row">
                           <div class="col-lg-12">
						   <button type="button" class="btn  btn-danger btn-outline btn-rounded btn-xs pull-right" onclick="modal_user_profile_resetPwd()"><i class="fa  fa-refresh"></i> Reset Password</button>
                              <button type="button" class="btn  btn-primary btn-outline btn-rounded btn-xs pull-right" onclick="modal_user_profile_resync()" id="btn_close_"><i class="fa  fa-refresh"></i> Resync User Info</button>
                              <span class="text_primary txt-grey font-12 ">User info last sync:  <small id="modal_user_profile_lastupdate_text"></small></span>
                              <!-- <span class="block txt-grey font-12 capitalize-font">yesterday</span> -->
                              <div class="panel-wrapper collapse in">
                                 <div class="panel-body row">
                                    <div class="user-others-details pl-15 pr-15">
                                       <div class="container">
                                          <div class="row  mr-10">
                                             <div class="form-group">
                                                <label class="control-label mr-10" for="modal_user_profile_id">User ID:</label>
                                                <input type="text" class="form-control modal_user_profile_input" id="modal_user_profile_id" disabled="">
                                             </div>
                                             <div class="progress modal_user_profile_loading_text" class="col-sm-7" >
                                                <div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
                                                </div>
                                             </div>
                                          </div>
                                          <div class="row mr-10">
                                             <div class="form-group">
                                                <label class="control-label mr-10" for="modal_user_profile_name">Name</label>
                                                <input type="text" class="form-control modal_user_profile_input modal_user_profile_input_enable" id="modal_user_profile_name">
                                             </div>
                                             <!-- <span class="col-sm-3"><i class="zmdi zmdi-accounts-list inline-block"> User ID:</i></span> -->
                                             <div class="progress modal_user_profile_loading_text" class="col-sm-7" >
                                                <div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
                                                </div>
                                             </div>
                                             <!-- <span class="inline-block txt-dark" id="modal_user_profile_id">-</span> -->
                                          </div>
                                          <div class="row mr-10">
                                             <div class="form-group">
                                                <label class="control-label mr-10" for="modal_user_profile_name">Category</label>
                                                <select class="form-control modal_user_profile_input modal_user_profile_input_enable" id="modal_user_profile_category">
                                                   <option>1</option>
                                                </select>
                                             </div>
                                          </div>
                                          <div class="row mr-10">
                                             <div class="form-group" id="modal_user_profile_status">
                                                <div class="alert alert-success alert-dismissable mt-10">-
                                                </div>
                                             </div>
                                             <div class="progress modal_user_profile_loading_text" class="col-sm-7" >
                                                <div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
                                                </div>
                                             </div>
                                          </div>
                                       </div>
                                    </div>
                                    <hr class="light-grey-hr mt-20 mb-20">
                                 </div>
                              </div>
                              <div class="panel-heading">
                                 <div class="pull-left">
                                    <h6 class="panel-title txt-dark" id="text_lst_of_accible_app">List of Accessible apps</h6>
                                 </div>
                                 <div class="pull-right">
                                    <a class="pull-left inline-block mr-15" data-toggle="collapse" href="#collapse_1" aria-expanded="true">
                                    <button class="btn  btn-primary btn-outline btn-rounded btn-xs" onclick="add_new_specific_apps_to_accessible()" id="btn_add_new_specific_apps_to_accessible">+ Add Accessible Apps</button>
                                    <button class="btn  btn-primary btn-outline btn-rounded btn-xs" onclick="close_add_new_specific_apps_to_accessible()" id="btn_close_add_new_specific_apps_to_accessible"><i class="fa  fa-reply"></i> Back to Accessible Apps</button>
                                    </a>
                                 </div>
                                 <div class="clearfix"></div>
                              </div>
                              <div class="panel-wrapper collapse in">
                                 <div class="panel-body pa-0">
                                    <div class="col-sm-12 col-xs-12" id="modal_user_profile_sp_add_new_list_div">
                                       <div class="form-wrap">
                                          <div class="form-body overflow-hide">
                                             <div class="list-group mb-0"  id="modal_user_profile_sp_add_new_list">
                                             </div>
                                          </div>
                                       </div>
                                    </div>
                                    <div class="col-sm-12 col-xs-12" id="modal_user_profile_sp_list_div">
                                       <div class="form-wrap">
                                          <div class="form-body overflow-hide">
                                             <div class="list-group mb-0"  id="modal_user_profile_sp_list">
                                             </div>
                                             <div class="progress" id="modal_user_profile_sp_list_loading" class="col-sm-7" >
                                                <div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
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
                     <input type="hidden" id="modal_user_profile_user_id" name="modal_user_profile_user_id">
                     <div class="modal-footer">
                        <button type="button" class="btn btn-danger waves-effect user_info_btn" onclick="user_info_deactivate_user();">Remove User</button>
                        <button type="button" class="btn btn-primary waves-effect user_info_btn_reactivate" onclick="user_info_reactivate_user();">Reactivate User</button>
                        <button type="button" class="btn btn-primary waves-effect user_info_btn" onclick="">Save</button>
                        <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Close</button>
                     </div>
                  </form>
               </div>
               <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
         </div>
         <div id="modal_add_new_app" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="aria_modal_add_new_app" aria-hidden="true">
            <div class="modal-dialog">
               <div class="modal-content">
                  <div class="modal-header">
                     <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                     <h5 class="modal-title" id="aria_modal_add_new_app">Add New App</h5>
                  </div>
                  <form id="form_add_new_app">
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
                                                   <label class="control-label mb-10" for="add_new_app_name">App Name</label>
                                                   <input type="text" class="form-control" id="add_new_app_name" name="add_new_app_name" placeholder="Name of the application" required="">
                                                </div>
                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="add_new_app_desc">Description</label>
                                                   <textarea class="form-control" rows="3" id="add_new_app_desc" name="add_new_app_desc" placeholder="Describe what the app does" required=""></textarea>
                                                </div>

                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="add_new_app_icon">App Icon (65x65)</label>
                                                   <div class="mt-1">
                                                      <input type="file" id="add_new_app_icon" name="add_new_app_icon" class="dropify" data-default-file="../img/thumb-1.jpg" data-height="100" />
                                                   </div>  
                                                </div> 


                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="add_new_app_url">Url</label>
                                                   <input type="text" class="form-control" id="add_new_app_url" name="add_new_app_url" placeholder="Url of the app" required="">
                                                   <i style="font-style: italic;"><small class="text-muted mb-10">Example: https://domain.com.my or https://subdomain.domain.com.my</small></i>
                                                </div>
                                                <div class="form-group mb-10">
													<label class="control-label mb-10 text-left">App Category</label>
													<select class="form-control" id="add_new_app_category" name="add_new_app_category">
													</select>
												</div>
                                                <div class="checkbox checkbox-primary">
														<input id="add_new_app_sso_checkbox" name="add_new_app_sso_checkbox" type="checkbox">
														<label for="add_new_app_sso_checkbox">
															Apps does not support SSO
														</label>
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
                     <div class="modal-footer">
                        <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary waves-effect">Add</button>
                     </div>
                  </form>
               </div>
               <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
         </div>


         <div id="modal_add_new_webapp_category" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="aria_modal_add_new_webapp_category" aria-hidden="true">
            <div class="modal-dialog">
               <div class="modal-content">
                  <div class="modal-header">
                     <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                     <h5 class="modal-title" id="aria_modal_add_new_category">New Apps Category</h5>
                  </div>
                  <form id="form_add_new_webapp_category">
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
                                                   <label class="control-label mb-10" for="add_new_webapp_category_name">Category title</label>
                                                   <input type="text" class="form-control" id="add_new_webapp_category_name" name="add_new_webapp_category_name" placeholder="Title of the category" required="">
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
                     <div class="modal-footer">
                        <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary waves-effect">Add</button>
                     </div>
                  </form>
               </div>
               <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
         </div>



         <div id="modal_edit_app" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="aria_modal_edit_app" aria-hidden="true">
            <div class="modal-dialog">
               <div class="modal-content">
                  <div class="modal-header">
                     <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                     <h5 class="modal-title" id="aria_modal_edit_app">App Info</h5>
                  </div>
                  <form id="form_edit_app">
                     <div class="modal-body">
                        <!-- Row -->
                        <div class="row" id="modal_edit_row_loading">
                           <br/>
                           <br/>
                           <br/>
                           <br/>
                           <br/>
                           <br/>
                           <div class="col-lg-12">
                              <div class="progress progress-lg">
                                 <div class="progress-bar progress-bar-primary active progress-bar-striped" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%" role="progressbar"> Loading App info.. Wait a moment..</div>
                              </div>
                           </div>
                           <br/>
                           <br/>
                           <br/>
                           <br/>
                           <br/>
                           <br/>
                        </div>
                        <div class="row" id="modal_edit_row_main">
                           <div class="col-lg-12">
                              <div class="">
                                 <div class="panel-wrapper collapse in">
                                    <div class="panel-body pa-0">
                                       <div class="col-sm-12 col-xs-12">
                                          <div class="form-wrap">
                                             <div class="form-body overflow-hide">
                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="edit_app_name">App Name</label>
                                                   <input type="text" class="form-control" id="edit_app_name" name="edit_app_name" placeholder="Name of the application">
                                                </div>
                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="edit_app_desc">Description</label>
                                                   <textarea class="form-control" rows="3" id="edit_app_desc" name="edit_app_desc" placeholder="Describe what the app does"></textarea>
                                                </div>
                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="edit_new_app_icon">App Icon (65x65)</label>
                                                   <div class="mt-1">
                                                      <input type="file" id="edit_new_app_icon" name="edit_new_app_icon" class="dropify" data-default-file="../img/thumb-1.jpg" data-height="100" />
                                                      <input type="hidden" name="edit_existing_app_icon" id="edit_existing_app_icon" value="" />
                                                   </div>  
                                                </div> 
                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="edit_app_url">Url</label>
                                                   <input type="text" class="form-control" id="edit_app_url" name="edit_app_url" placeholder="Url of the app">
                                                </div>
                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="edit_app_desc">Site API Code</label>
                                                   <span class="input-group-btn">
                                                   <div class="input-group mb-15"> <span class="input-group-btn">
                                                      <button type="button" class="btn  btn-primary" onclick="copyToClipboard('edit_app_code');"><i class="fa fa-copy"></i> Copy</button>
                                                      </span>
                                                      <input type="text" id="edit_app_code" class="form-control" disabled="">
                                                   </div>
                                                </div>
                                                <div class="form-group mb-10">
													<label class="control-label mb-10 text-left">App Category</label>
													<select class="form-control" id="edit_app_category" name="edit_app_category">
													</select>
												</div>
                                                <div class="checkbox checkbox-primary">
														<input id="app_info_sso_checkbox" type="checkbox" name="app_info_sso_checkbox">
														<label for="app_info_sso_checkbox">
															Apps does not support SSO
														</label>
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
                     <input type="hidden" id="edit_app_id" name="edit_app_id">
                     <div class="modal-footer"  id="modal_edit_btn">
                        <button type="button" class="btn btn-danger waves-effect" onclick="remove_app();">Remove</button>
                        <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary waves-effect">Save</button>
                     </div>
                  </form>
               </div>
               <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
         </div>
         <div id="modal_acl_blacklist" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="aria_modal_edit_app" aria-hidden="true">
            <div class="modal-dialog">
               <div class="modal-content">
                  <div class="modal-header">
                     <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                     <h5 class="modal-title" id="aria_modal_edit_app">Deny Access</h5>
                  </div>
                  <form id="form_blacklist_app">
                     <div class="modal-body">
                        <!-- Row -->
                        <div class="row">
                           <div class="col-lg-12">
                              <div class="panel-heading">
                                 <div class="clearfix"></div>
                              </div>
                              <div class="">
                                 <div class="panel-wrapper collapse in">
                                    <div class="panel-body pa-0">
                                       <div class="col-sm-12 col-xs-12">
                                          <div class="form-wrap">
                                             <div class="form-body overflow-hide">
                                                <div class="table-wrap">
                                                   <div class="table-responsive">
                                                      <table class="table mb-0 table-hover">
                                                         <thead>
                                                            <tr>
                                                               <th>#</th>
                                                               <th>User Info</th>
                                                               <th>Site</th>
                                                               <th>Action</th>
                                                            </tr>
                                                         </thead>
                                                         <tbody id="tbody_blacklist_record">
                                                            <tr>
                                                               <td colspan="4">-</td>
                                                            </tr>
                                                         </tbody>
                                                      </table>
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
                     <input type="hidden" id="" name="">
                     <div class="modal-footer">
                        <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Close</button>
                     </div>
                  </form>
               </div>
               <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
         </div>
         <div id="modal_acl_add_blacklist" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="aria_modal_edit_app" aria-hidden="true">
            <div class="modal-dialog">
               <div class="modal-content">
                  <div class="modal-header">
                     <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                     <h5 class="modal-title" id="aria_modal_edit_app">Add Blacklist Record</h5>
                  </div>
                  <form id="form_editXss_app">
                     <div class="modal-body">
                        <!-- Row -->
                        <div class="row">
                           <div class="col-lg-12">
                              <div class="">
                                 <div class="panel-wrapper collapse in">
                                    <div class="panel-body pa-0">
                                       <div class="col-sm-12 col-xs-12">
                                          <div class="form-wrap">
                                             <div class="form-body overflow-hide">
                                                <div class="input-group mb-15">
                                                   <input type="email" id="search_user_add_blacklist_id" class="form-control" placeholder="Search user id">
                                                   <span class="input-group-btn">
                                                   <button onclick="search_user_add_blacklist();" type="button" class="btn btn-orange "><span class="btn-text">Search</span></button>
                                                   </span> 
                                                </div>
                                                <hr/>
                                                <div class="todo-box-wrap">
                                                   <!-- Todo-List -->
                                                   <div class="slimScrollDiv" style="position: relative; overflow: hidden; width: auto; height: 365px;">
                                                      <ul class="todo-list todo-box-nicescroll-bar" style="overflow: hidden; width: auto; height: 365px;" id="acl_add_blacklist_search_result">
                                                         <li class="todo-item">
                                                            <div class="checkbox checkbox-purple">
                                                               <input type="checkbox" id="checkbox006" >
                                                               <label for="checkbox006">Finish Infinity Project</label>
                                                            </div>
                                                         </li>
                                                         <li>
                                                            <hr class="light-grey-hr">
                                                         </li>
                                                      </ul>
                                                      <div class="slimScrollBar" style="background: rgb(135, 135, 135); width: 4px; position: absolute; top: 0px; opacity: 0.4; display: none; border-radius: 0px; z-index: 99; right: 1px; height: 365px;"></div>
                                                      <div class="slimScrollRail" style="width: 4px; height: 100%; position: absolute; top: 0px; display: none; border-radius: 7px; background: rgb(51, 51, 51); opacity: 0.2; z-index: 90; right: 1px;"></div>
                                                   </div>
                                                   <!-- /Todo-List -->
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
                     <input type="hidden" id="" name="">
                     <div class="modal-footer">
                        <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Close</button>
                     </div>
                  </form>
               </div>
               <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
         </div>
         <div id="modal_add_new_category" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="aria_modal_add_new_category" aria-hidden="true">
            <div class="modal-dialog">
               <div class="modal-content">
                  <div class="modal-header">
                     <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                     <h5 class="modal-title" id="aria_modal_add_new_category">New User Category</h5>
                  </div>
                  <form id="form_add_new_category">
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
                                                   <label class="control-label mb-10" for="add_new_category_name">Category title</label>
                                                   <input type="text" class="form-control" id="add_new_category_name" name="add_new_category_name" placeholder="Name of the category" required="">
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
                     <div class="modal-footer">
                        <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary waves-effect">Add</button>
                     </div>
                  </form>
               </div>
               <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
         </div>
         <div id="modal_open_add_user_option" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="aria_modal_open_add_user_option" aria-hidden="true">
            <div class="modal-dialog modal-sm" >
               <div class="modal-content">
                  <div class="modal-body">
                     <div class="row">
                        <div class="panel panel-primary card-view">
                           <div class="panel-heading">
                              <div class="pull-left">
                                 <h6 class="panel-title txt-light">Add User</h6>
                              </div>
                              <div class="clearfix"></div>
                           </div>
                        </div>
                     </div>
                     <div class="row">
                        <div class="col-lg-12">
                           <div class="">
                              <div class="panel-wrapper collapse in">
                                 <div class="panel-body pa-0">
                                    <div class="col-sm-12 col-xs-12">
                                       <div class="form-wrap">
                                          <div class="form-body overflow-hide">
                                             <div class="form-group">
                                                <button id="btn_sync" class="btn  btn-primary btn-outline btn-block" type="button" onclick="pick_preview_sync_user();"><i class="fa fa-search"></i> Preview external sync</button>
                                                <p id="sync_status_msg" class="text-muted text-center mt-10" style="display:none;"></p>
                                             </div>
                                             <div class="form-group">
                                                <button class="btn  btn-primary btn-outline btn-block" type="button" onclick="pick_add_single_user();"><i class="fa fa-plus"></i> Manual Add User</button>
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
               <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
         </div>
         <div id="modal_add_new_single_user" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="aria_modal_add_new_single_user" aria-hidden="true">
            <div class="modal-dialog">
               <div class="modal-content">
                  <div class="modal-header">
                     <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                     <h5 class="modal-title" id="aria_modal_add_new_single_user">External Sync Preview (Read-only)</h5>
                  </div>
                  <form id="form_add_new_category">
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
                                                   <div class="progress progress-lg" id="sync_progress_id">
                                                      <div class="progress-bar progress-bar-primary active progress-bar-striped" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%" role="progressbar"> Generating read-only preview... </div>
                                                   </div>
                                                   <div id="sync_result_div">
                                                      <h6 class="mb-1"><i></i> Proposed changes (nothing has been applied):</h6>
                                                      <table class="table table-borderless">
                                                         <tbody>
                                                            <tr>
                                                               <td>External rows:</td>
                                                               <td><span id="sync_preview_source_rows"></span></td>
                                                            </tr>
                                                            <tr>
                                                               <td>New / Update:</td>
                                                               <td><span id="sync_preview_new_update"></span></td>
                                                            </tr>
                                                            <tr>
                                                               <td>Deactivate / Reactivate:</td>
                                                               <td><span id="sync_preview_deactivate_reactivate"></span></td>
                                                            </tr>
                                                            <tr>
                                                               <td>Protected manual / collisions:</td>
                                                               <td><span id="sync_preview_protected"></span></td>
                                                            </tr>
                                                            <tr>
                                                               <td>Plan hash / expiry:</td>
                                                               <td><span id="sync_preview_hash_expiry"></span></td>
                                                            </tr>
                                                            <tr>
                                                               <td>Preview status:</td>
                                                               <td><span id="sync_preview_status" class="users-view-status"></span></td>
                                                            </tr>
                                                            <tr>
                                                               <td>Warnings:</td>
                                                               <td><ul id="sync_preview_warnings" class="pl-15"></ul></td>
                                                            </tr>
                                                         </tbody>
                                                      </table>
                                                      <p class="text-muted">S4D readiness preview only. Approval may be prepared server-side, but there is no Apply action on this screen.</p>
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
                     <div class="modal-footer">
                        <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Close</button>
                     </div>
                  </form>
               </div>
               <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
         </div>
         <div id="modal_add_new_user_manual" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="aria_modal_add_new_user_manual" aria-hidden="true">
            <div class="modal-dialog">
               <div class="modal-content">
                  <div class="modal-header">
                     <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                     <h5 class="modal-title" id="aria_modal_add_new_user_manual">Add User - Manual</h5>
                  </div>
                  <form id="form_add_new_user_manual">
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
                                                   <label class="control-label mb-10" for="add_new_category_name">Name @ Data 1</label>
                                                   <input type="text" class="form-control add_new_manual_user_input" id="add_new_manual_user_name" name="add_new_manual_user_name" placeholder="Staff/Student Name" maxlength="100" required="">
                                                </div>
                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="add_new_category_name">No KP / User ID / Username</label>
                                                   <input type="text" class="form-control add_new_manual_user_input" id="add_new_manual_user_id" name="add_new_manual_user_id" placeholder="User Unique ID, Staff/Student ID" maxlength="20" pattern="[A-Za-z0-9][A-Za-z0-9._@-]*" required="" disabled="">
                                                </div>
                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="add_new_manual_user_category">Category</label>
                                                   <select class="form-control add_new_manual_user_input" id="add_new_manual_user_category" name="add_new_manual_user_category" required="">
                                                   </select>
                                                </div>
                                                <div class="form-group">
                                                   <div class="panel-group accordion-struct accordion-style-1" id="accordion_2" role="tablist" aria-multiselectable="true">
                                                      <div class="panel panel-default">
                                                         <div class="panel-heading" role="tab" id="heading_10">
                                                            <a role="button" data-toggle="collapse" data-parent="#accordion_2" href="#collapse_10" aria-expanded="false" class="collapsed">
                                                               <div class="icon-ac-wrap pr-20"><span class="plus-ac"><i class="ti-plus"></i></span><span class="minus-ac"><i class="ti-minus"></i></span></div>
                                                               Add Additional Data
                                                            </a>
                                                         </div>
                                                         <div id="collapse_10" class="panel-collapse collapse" role="tabpanel" aria-expanded="false" style="height: 0px;">
                                                            <div class="panel-body pa-15">
                                                               <div class="form-group">
                                                                  <label class="control-label mb-10" for="add_new_user_data2">Data 2</label>
                                                                  <input type="text" class="form-control add_new_manual_user_input" id="add_new_user_data2" name="add_new_user_data2" placeholder="idpekerja / no_matrik">
                                                               </div>
                                                               <div class="form-group">
                                                                  <label class="control-label mb-10" for="add_new_user_data3">Data 3</label>
                                                                  <input type="text" class="form-control add_new_manual_user_input" id="add_new_user_data3" name="add_new_user_data3" placeholder="nopekerja / full id pekerja">
                                                               </div>
                                                               <div class="form-group">
                                                                  <label class="control-label mb-10" for="add_new_user_data5">Email @ Data 5 (required for OTP)</label>
                                                                  <input type="email" class="form-control add_new_manual_user_input" id="add_new_user_data5" name="add_new_user_data5" placeholder="nama@domain.edu.my" maxlength="100" required="">
                                                               </div>
                                                               <div class="form-group">
                                                                  <label class="control-label mb-10" for="add_new_user_data6">Data 6</label>
                                                                  <input type="text" class="form-control add_new_manual_user_input" id="add_new_user_data6" name="add_new_user_data6" placeholder="jabatansemasa / nama_ptj">
                                                               </div>
                                                               <div class="form-group">
                                                                  <label class="control-label mb-10" for="add_new_user_data7">Data 7</label>
                                                                  <input type="text" class="form-control add_new_manual_user_input" id="add_new_user_data7" name="add_new_user_data7" placeholder="jawatansemasa / program">
                                                               </div>
                                                               <div class="form-group">
                                                                  <label class="control-label mb-10" for="add_new_user_data8">Data 8</label>
                                                                  <input type="text" class="form-control add_new_manual_user_input" id="add_new_user_data8" name="add_new_user_data8" placeholder="reserved / not used">
                                                               </div>
                                                               <div class="form-group">
                                                                  <label class="control-label mb-10" for="add_new_user_data9">Data 9</label>
                                                                  <input type="text" class="form-control add_new_manual_user_input" id="add_new_user_data9" name="add_new_user_data9" placeholder="reserved / not used">
                                                               </div>
                                                               <div class="form-group">
                                                                  <label class="control-label mb-10" for="add_new_user_data10">Data 10</label>
                                                                  <input type="text" class="form-control add_new_manual_user_input" id="add_new_user_data10" name="add_new_user_data10" placeholder="reserved / not used">
                                                               </div>
                                                               <div class="form-group">
                                                                  <label class="control-label mb-10" for="add_new_user_data11">Data 11</label>
                                                                  <input type="text" class="form-control add_new_manual_user_input" id="add_new_user_data11" name="add_new_user_data11" placeholder="reserved / not used">
                                                               </div>
                                                               <div class="form-group">
                                                                  <label class="control-label mb-10" for="add_new_user_data12">Data 12</label>
                                                                  <input type="text" class="form-control add_new_manual_user_input" id="add_new_user_data12" name="add_new_user_data12" placeholder="reserved / not used">
                                                               </div>
                                                            </div>
                                                         </div>
                                                      </div>
                                                   </div>
                                                </div>
                                                <div class="alert alert-warning alert-dismissable alert-style-1 add_new_manual_user_input_loading_text">
                                                   <i class="zmdi zmdi-alert-circle-o"></i><span class="text-center">Checking User ID, wait a moment..</span>
                                                   <div class="progress" class="col-sm-7" >
                                                      <div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
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
                     <div class="modal-footer">
                        <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary waves-effect">Add</button>
                     </div>
                  </form>
               </div>
               <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
         </div>
         <div id="modal_category_user_list_view" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="aria_modal_category_user_list_view" aria-hidden="true">
            <div class="modal-dialog">
               <div class="modal-content">
                  <div class="modal-header">
                     <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                     <h5 class="modal-title" id="aria_modal_category_user_list_view">App List - <span id="modal_category_user_list_view_title"></span></h5>
                  </div>
                  <form id="form_blacklist_app">
                     <div class="modal-body">
                        <div  class="tab-struct custom-tab-1">
                           <ul role="tablist" class="nav nav-tabs" id="myTabs_7">
                              <!-- <li role="presentation"><a aria-expanded="true"  data-toggle="tab" role="tab" id="modal_category_tab_1" href="#category_tab_1">User Listing</a></li> -->
                              <li class="active" role="presentation" class=""><a  data-toggle="tab" id="modal_category_tab_2" role="tab" href="#category_tab_2" aria-expanded="false">Allowed Site (ACL)</a></li>
                           </ul>
                           <div class="tab-content" id="myTabContent_7">
                              <!-- <div  id="category_tab_1" class="tab-pane fade active in" role="tabpanel">
                                 <div class="table-responsive">
                                 	<table class="table mb-0 table-hover">
                                 		<thead>
                                 		  <tr>
                                 			<th>#</th>
                                 			<th>User Id</th>
                                 			<th>Name</th>
                                 			<th>Action</th>
                                 		  </tr>
                                 		</thead>
                                 		<tbody id="tbody_category_listing">
                                 		</tbody>
                                 	</table>
                                 </div>
                                 </div> -->
                              <div  id="category_tab_2" class="tab-pane fade active in" role="tabpanel">
                                 <div class="panel-heading">
                                    <div class="pull-left">
                                       <h6 class="panel-title txt-dark" id="text_lst_of_accible_category_app">List of Accessible apps</h6>
                                    </div>
                                    <div class="pull-right">
                                       <a class="pull-left inline-block mr-15" data-toggle="collapse" href="#collapse_1" aria-expanded="true">
                                       <button class="btn  btn-primary btn-outline btn-rounded btn-xs" onclick="add_new_apps_to_category()" id="btn_add_new_apps_to_category">+ Add Accessible Apps</button>
                                       <button class="btn  btn-primary btn-outline btn-rounded btn-xs" onclick="close_add_new_apps_to_category()" id="btn_close_add_new_apps_to_category" style="display: none;"><i class="fa  fa-reply"></i> Back to accessible app list</button>
                                       </a>
                                    </div>
                                    <div class="clearfix"></div>
                                 </div>
                                 <div class="progress progress-lg" id="tbl_category_site_listing_loading">
                                    <div class="progress-bar progress-bar-primary active progress-bar-striped" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%" role="progressbar"> Loading App list.. Wait a moment..</div>
                                 </div>
                                 <div class="table-responsive" id="tbl_category_site_listing">
                                    <table class="table mb-0 table-hover">
                                       <thead>
                                          <tr>
                                             <th>#</th>
                                             <th>Site</th>
                                             <th style="text-align:right">Action</th>
                                          </tr>
                                       </thead>
                                       <tbody id="tbody_category_site_listing">
                                       </tbody>
                                    </table>
                                 </div>
                                 <div class="progress progress-lg" id="tbl_category_site_listing_add_new_loading">
                                    <div class="progress-bar progress-bar-primary active progress-bar-striped" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%" role="progressbar"> Loading App list.. Wait a moment..</div>
                                 </div>
                                 <div class="table-responsive" id="tbl_category_site_listing_add_new">
                                    <table class="table mb-0 table-hover">
                                       <thead>
                                          <tr>
                                             <th>#</th>
                                             <th>Site</th>
                                             <th style="text-align:right">Action</th>
                                          </tr>
                                       </thead>
                                       <tbody id="tbody_category_site_listing_add_new">
                                       </tbody>
                                    </table>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>
                     <input type="hidden" id="modal_category_user_list_view_id" name="modal_category_user_list_view_id">
                     <div class="modal-footer">
                        <button type="button" class="btn btn-danger waves-effect" id="btn_remove_category" onclick="remove_category();">Remove Category</button>
                        <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Close</button>
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
                           <div class="panel panel-default card-view bg-twitter">
                              <div class="panel-heading">
                                 <div class="text-center">
                                    <h6 class="panel-title txt-light ">ADMINISTRATOR</h6>
                                 </div>
                                 <div class="clearfix"></div>
                              </div>
                              <div class="panel-wrapper collapse in">
                                 <div  class="panel-body row pa-0">
                                    <!--Instagram-->
                                    <ul class="instagram-lite"></ul>
                                    <!--/Instagram-->
                                 </div>
                              </div>
                           </div>
                        </div>
                        <div class="col-sm-12">
                           <div class="panel panel-default card-view  pa-0">
                              <div class="panel-wrapper collapse in">
                                 <div class="panel-body  pa-0">
                                    <div class="profile-box">
                                       <div class="profile-info text-center mb-15">
                                          <h5 class="block mt-10 weight-500 capitalize-font txt-dark"><?php echo $_SESSION['user']; ?></h5>
                                          <h6 class="block capitalize-font"><?php echo $_SESSION['login_user']; ?></h6>
                                          <!--<span class="time block truncate txt-grey">Your session will expire in <span id="demo">- - -</span></span> -->
                                       </div>
                                       <div class="social-info">
                                          <div class="row">
                                             <div class="col-xs-6 text-center">
                                                <span class="counts block head-font"><span class="counter-anim"><?php echo $widget_data['total_sp']; ?></span></span>
                                                <span class="counts-text block">Web App</span>
                                             </div>
                                             <div class="col-xs-6 text-center">
                                                <span class="counts block head-font"><span class="counter-anim"><?php echo $widget_data['total_user']; ?></span></span>
                                                <span class="counts-text block">User</span>
                                             </div>
                                          </div>
                                       </div>
                                    </div>


                                    <div  class="pills-struct vertical-pills mt-40">
                                       <ul role="tablist" class="nav nav-pills ver-nav-pills" id="myTabs_8">
                                          <li  role="presentation" class="active"><a aria-expanded="true"  data-toggle="tab" role="tab" id="follo_tab_8" href="#follo_8"><span>Web Apps <span class="inline-block" id='follo_data_list_count_text'></span></span></a></li>
                                          <li  role="presentation" class="next"><a aria-expanded="true"  data-toggle="tab" role="tab" id="tab_user_menu" href="#tab_user"><span>User Account</span></a></li>
                                          <li  role="presentation" class="next"><a aria-expanded="true"  data-toggle="tab" role="tab" id="tab_user_active_sessions" href="#tab_active_sessions"><span>Active Sessions</span></a></li>
                                          <li  role="presentation" class="next"><a aria-expanded="true"  data-toggle="tab" role="tab" id="tab_audit_log" href="#tab_auditlog"><span>Audit Log</span></a></li>
                                          <li  role="presentation" class="next"><a aria-expanded="true"  data-toggle="tab" role="tab" id="tab_sync_log" href="#tab_synclog"><span>Sync Log</span></a></li>
										  
                                          <li  role="presentation" class="next"><a aria-expanded="true"  data-toggle="tab" role="tab" id="tab_acl_menu" href="#tab_settings"><span>Configuration</span></a></li>
                                          <li  role="presentation" class="next"><a aria-expanded="true"  data-toggle="tab" role="tab" id="tab_ver" href="#tab_versioning"><span>Version Releases</span></a></li>
                                          <?php if($_SESSION['login_user_type'] == 1){ ?>   
                                             <li role="presentation" style="cursor: pointer !important;" >
                                                <a id="tab_faq" href="../page/dashboard">
                                                  <span>Back to My Account<span class="inline-block"></span></span>
                                                </a>
                                             </li>
                                          <?php } ?>
                                           <li role="presentation" style="cursor: pointer !important;" >
                                             <a id="tab_faq" href="logout">
                                               <span>Log Out<span class="inline-block"></span></span>
                                             </a>
                                           </li>
                                           
                                       </ul>
                                       
                                    </div>


                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
                  <div class="col-sm-8">
                     <div class="row">
                        <div class="col-sm-12">
                           <div class="panel panel-default card-view pa-0">
                              <div class="panel-wrapper collapse in">
                                 <div  class="panel-body pb-0">


                                    <div class="tab-content" id="myTabContent_8">
                                          <div  id="follo_8" class="tab-pane fade active in" role="tabpanel">
                                             <div class="row">
                                                <div class="col-lg-12">
                                                   <div class="panel-heading">
                                                      <div class="pull-left">
                                                         <h6 class="panel-title txt-dark">List of available apps</h6>
                                                      </div>
                                                      <div class="pull-right">
                                                         <button class="btn btn-xs btn-primary btn-outline btn-rounded " type="button" onclick="open_remove_webapp_category();">Remove Category</button>
                                                         <button class="btn btn-xs btn-primary btn-outline btn-rounded " type="button" onclick="open_add_new_webapp_category();">Add Category</button>
                                                         <button class="btn btn-xs btn-primary btn-outline btn-rounded " type="button" onclick="open_add_new_webapp();">Add App</button>
                                                         <button class="btn btn-xs btn-primary btn-outline btn-rounded " type="button" onclick="get_service_provider_list();"><i class="zmdi zmdi-replay text-primary"></i></button>
                                                         
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
                                                   <div class="followers-wrap">
                                                      <ul class="followers-list-wrap">
                                                         <li class="follow-list" id="tab_available_apps_list_loading">
                                                            <div class="col-lg-12">
                                                               <div class="progress progress-lg">
                                                                  <div class="progress-bar progress-bar-primary active progress-bar-striped" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%" role="progressbar"> Loading App list.. Wait a moment..</div>
                                                               </div>
                                                            </div>
                                                         </li>
                                                         <li class="follow-list" id="tab_available_apps_list">
                                                            <div class="follo-body" id="follo_data_list">
                                                            </div>
                                                         </li>
                                                      </ul>
                                                   </div>
                                                </div>
                                             </div>
                                          </div>
                                          <div  id="tab_user" class="tab-pane fade in" role="tabpanel">
                                             <div class="row">
                                                <div class="col-lg-12">
                                                   <div class="panel-heading">
                                                      <div class="input-group">
                                                         <div id="the-basics">
                                                            <input class="typeahead form-control" id="search_user_input" type="text" placeholder="Search by Name, Staff/Student ID, or NIRC">
                                                         </div>
                                                         <span class="input-group-btn">
                                                         <button type="button" class="btn  btn-primary" onclick="clear_search();" data-toggle="collapse" aria-label="Close" aria-expanded="true">Clear</button>
                                                         </span>
                                                      </div>
                                                      <img class="Typeahead-spinner" src="../img/Spinner-1s-51px.gif" hidden>
                                                      <blockquote id="search_user_account_main_progress_bar">
                                                         <div class="col-lg-12">
                                                            <br/>
                                                            <div class="progress progress-lg">
                                                               <div class="progress-bar progress-bar-primary active progress-bar-striped" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%" role="progressbar"> Searching... Please Wait...</div>
                                                            </div>
                                                         </div>
                                                      </blockquote>
                                                      <blockquote id="search_user_account_main_search_result">
                                                         <p>Search Result.</p>
                                                         <hr class="light-grey-hr">
                                                         <div class="label-chatrs">
                                                            <div id="search_user_account_main_result_data">
                                                               <span class="clabels-text font-12 inline-block txt-dark capitalize-font pull-left">
                                                               <span class="block font-15 weight-500 mb-5">Nurul Farhana Haron</span>
                                                               <span class="block txt-grey">Category: Staff Akademik</span>
                                                               <span class="badge transparent-badge badge-success capitalize-font">Registered</span>
                                                               </span>
                                                               <button class="btn btn-primary pull-right btn-xs fixed-btn"><span class="btn-text">View</span></button>
                                                               <div class="clearfix"></div>
                                                            </div>
                                                         </div>
                                                      </blockquote>
                                                   </div>
                                                </div>
                                             </div>
                                             <div class="row">
                                                <div class="col-lg-12">
                                                   <hr class="light-grey-hr">
                                                   <div class="panel-heading">
                                                      <div class="pull-left">
                                                         <h6 class="panel-title txt-dark">User Category</h6>
                                                      </div>
                                                      <div class="pull-right">
                                                         <a href="#" class="pull-left inline-block mr-15">
                                                            <!-- <i class="fa fa-plus-square text-primary"></i> -->
                                                            <button class="btn btn-xs btn-primary btn-outline btn-rounded" type="button" onclick="add_new_category();">New User Category</button>
                                                            <button class="btn btn-xs btn-primary btn-outline btn-rounded" type="button" onclick="add_new_user_option();">Add User</button>
                                                            <button class="btn btn-xs btn-primary btn-outline btn-rounded " type="button" onclick="admin_get_all_user_category(0);"><i class="zmdi zmdi-replay text-primary"></i></button>
                                                         </a>
                                                      </div>
                                                      <div class="clearfix"></div>
                                                   </div>
                                                   <div class="followers-wrap">
                                                      <ul class="followers-list-wrap">
                                                         <li class="follow-list">
                                                            <div class="follo-body" id="user_account_type_list">
                                                            </div>
                                                         </li>
                                                      </ul>
                                                   </div>
                                                </div>
                                             </div>
                                          </div>
                                          <div  id="tab_active_sessions" class="tab-pane fade in" role="tabpanel">
                                             <div  id="follo_8" class="tab-pane fade active in" role="tabpanel">
                                                <div class="row">
                                                   <div class="col-lg-12">
                                                      <div class="panel-heading">
                                                         <div class="pull-left">
                                                            <h6 class="panel-title txt-dark">List of active sessions</h6>
                                                         </div>
                                                         <div class="pull-right">                              
                                                            <a href="#" class="pull-left inline-block refresh mr-15" onclick="get_all_user_activ_session()">
                                                            <i class="zmdi zmdi-replay text-primary"></i>
                                                            </a>  
                                                         </div>
                                                         <div class="clearfix"></div>
                                                      </div>
                                                      <div class="followers-wrap" id="app_security_session_list">
                                                        <table class="table table-sm mb-0">
    <thead>
        <tr>
            <th class="p-0"><small>Session</small></th>
        </tr>
    </thead>
    <tbody id="security_tab_session">
        <!-- rows injected here -->
    </tbody>
</table>


                                                      </div>
                                                   </div>
                                                </div>
                                             </div>
                                          </div>
                                          <div  id="tab_auditlog" class="tab-pane fade in" role="tabpanel">
                                             <div  id="follo_8" class="tab-pane fade active in" role="tabpanel">
                                                <div class="row">

                                                   <div class="col-lg-12">
                                                      <div class="panel-heading">
                                                                        <label class="control-label mb-10 text-left">Pick Start and End Date</label>
                                                                  <form class="form-inline">
                                                                     <div class="form-group">
                                                                        <input class="form-control input-daterange-datepicker" type="text" id="audit_search_daterange" name="audit_search_daterange" value="01/01/2016 - 01/31/2016" style="width: 200px;"/>
                                                                     </div>
                                                                     <button type="button" class="btn btn-orange " onclick="search_audit_date_range()"><span class="btn-text">Search</span></button>
                                                                  </form>
                                                                  
                                                      </div>
                                                   </div>


                                                   <div class="col-lg-12">
                                                      <div class="panel-heading">
                                                         <div class="pull-left">
                                                            <h6 class="panel-title txt-dark">Logs Result</h6>
                                                         </div>
                                                         <div class="clearfix"></div>
                                                         <div class="panel-wrapper collapse in">
                                                            <div class="panel-body row pa-0">
                                                               <div class="table-wrap">
                                                                  <div class="table-responsive">
                                                                     <table class="table table-hover mb-0">
                                                                        <thead>
                                                                           <tr>
                                                                              <th>Date Time</th>
                                                                              <th>Log Type</th>
                                                                              <th>Log Details</th>
                                                                              <th>IP Address</th>
                                                                           </tr>
                                                                        </thead>
                                                                        <tbody id="audit_search_result_tbody">
                                                                           <tr>
                                                                              <td colspan="4">Start searching to get results</td>
                                                                           </tr>
                                                                        </tbody>
                                                                     </table>
                                                                  </div>
                                                               </div>   
                                                            </div>   
                                                         </div>
                                                      </div>


                                                   </div>
                                                </div>
                                             </div>
                                          </div>
                                          <div id="tab_synclog" class="tab-pane fade in" role="tabpanel">
                                             <div class="tab-pane fade active in" role="tabpanel">
                                                <div class="row">
                                                   <div class="col-lg-12" id="sync_session_panel">
                                                      <div class="panel-heading">
                                                         <div class="pull-left">
                                                            <h6 class="panel-title txt-dark">Sync Sessions</h6>
                                                         </div>
                                                         <div class="pull-right">
                                                            <button type="button" class="btn btn-xs btn-default btn-outline btn-rounded" onclick="load_sync_sessions();"><i class="fa fa-refresh"></i> Refresh</button>
                                                         </div>
                                                         <div class="clearfix"></div>
                                                         <div class="panel-wrapper collapse in">
                                                            <div class="panel-body row pa-0">
                                                               <div class="table-wrap">
                                                                  <div class="table-responsive">
                                                                     <table class="table table-hover mb-0">
                                                                        <thead>
                                                                           <tr>
                                                                              <th>Session ID</th>
                                                                              <th>Date/Time</th>
                                                                              <th>Triggered By</th>
                                                                              <th>New</th>
                                                                              <th>Updated</th>
                                                                              <th>Deactivated</th>
                                                                              <th>Reactivated</th>
                                                                              <th>Status</th>
                                                                              <th>Action</th>
                                                                           </tr>
                                                                        </thead>
                                                                        <tbody id="sync_session_tbody">
                                                                           <tr>
                                                                              <td colspan="9">Loading...</td>
                                                                           </tr>
                                                                        </tbody>
                                                                     </table>
                                                                  </div>
                                                                  <div id="sync_session_pagination" class="text-center mt-10" style="padding:10px 0;"></div>
                                                               </div>
                                                            </div>
                                                         </div>
                                                      </div>
                                                   </div>
                                                   <div class="col-lg-12" id="sync_detail_panel" style="display:none;">
                                                      <div class="panel-heading">
                                                         <div class="pull-left">
                                                            <button type="button" class="btn btn-xs btn-default btn-outline btn-rounded mr-15" onclick="show_sync_session_list();"><span class="btn-text">&larr; Back</span></button>
                                                            <h6 class="panel-title txt-dark inline-block" id="sync_detail_header">Changes in Session</h6>
                                                         </div>
                                                         <div class="clearfix"></div>
                                                         <div class="panel-wrapper collapse in">
                                                            <div class="panel-body row pa-0">
                                                               <div class="table-wrap">
                                                                  <div class="table-responsive">
                                                                     <table class="table table-hover mb-0">
                                                                        <thead>
                                                                           <tr>
                                                                              <th>#</th>
                                                                              <th>User ID</th>
                                                                              <th>Action</th>
                                                                              <th>Changed Fields</th>
                                                                              <th>Old Data</th>
                                                                              <th>New Data</th>
                                                                              <th>Time</th>
                                                                           </tr>
                                                                        </thead>
                                                                        <tbody id="sync_detail_tbody">
                                                                           <tr>
                                                                              <td colspan="7">No changes recorded</td>
                                                                           </tr>
                                                                        </tbody>
                                                                     </table>
                                                                  </div>
                                                                  <div id="sync_detail_pagination" class="text-center mt-10" style="padding:10px 0;"></div>
                                                               </div>
                                                            </div>
                                                         </div>
                                                      </div>
                                                   </div>
                                                </div>
                                             </div>
                                          </div>
										  <div  id="tab_versioning" class="tab-pane fade in" role="tabpanel">
                                             <div  id="follo_8" class="tab-pane fade active in" role="tabpanel">
                                                <div class="row">


                                                   <div class="col-lg-12">
                                                      <div class="panel-heading">
                                                         <div class="pull-left">
                                                            <h6 class="panel-title txt-dark">Version</h6>
                                                         </div>
                                                         <div class="clearfix"></div>
                                                         <div class="panel-wrapper collapse in">
                                                            <div class="panel-body row pa-0">
                                                               <div class="table-wrap">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Release Version</th>
          <th>Change Log</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody id="release_notes_tbody">
        <!-- JS will render rows here -->
      </tbody>
    </table>
  </div>
</div>
  
                                                            </div>   
                                                         </div>
                                                      </div>


                                                   </div>
                                                </div>
                                             </div>
                                          </div>
                                          <div  id="tab_settings" class="tab-pane fade in" role="tabpanel">
                                             <div  id="follo_8" class="tab-pane fade active in" role="tabpanel">
                                                <div class="row">
                                                   <div class="col-lg-12">
                                                      <div class="panel-heading">
                                                         <div class="pull-left">
                                                            <h6 class="panel-title txt-dark">SSO Configuration</h6>
                                                         </div>
                                                         <div class="pull-right">
                                                            <a href="#" class="pull-left inline-block mr-15">
                                                               <!-- <i class="fa fa-plus-square text-primary"></i> -->
                                                               <button class="btn btn-xs btn-primary btn-outline btn-rounded " type="button" onclick="update_configuration();">Update</button>
                                                            </a>
                                                         </div>
                                                         <div class="clearfix"></div>
                                                         <br/>
                                                         <br/>
                                                         <div class="form-group mb-0">
                                                            <div class="col-sm-2">
                                                               <label class="control-label mb-10 " for="example-input1-group2">Token Session Timeout</label>
                                                            </div>
                                                            <div class="col-sm-10 mb-20">
                                                               <div class="form-group mb-15">
                                                                  <select class="form-control" id="sso_settings_token_session_timeout">
                                                                     <option value="0.5">30 Minute</option>
                                                                     <option value="1">1 hour</option>
                                                                     <option value="2">2 hour</option>
                                                                     <option value="12">1/2 Day</option>
                                                                     <option value="24">1 Day</option>
                                                                     <option value="48">2 Days</option>
                                                                     <option value="72">3 Days</option>
                                                                     <option value="168">1 Week</option>
                                                                  </select>
                                                               </div>
                                                            </div>
                                                         </div>
                                                         <div class="form-group mb-0">
                                                            <div class="col-sm-2">
                                                               <label class="control-label mb-10 " for="example-input1-group2">Allow Multiple Session</label>
                                                            </div>
                                                            <div class="col-sm-10 mb-20">
                                                               <div class="form-group mb-15">
                                                                  No 
                                                                  <input type="checkbox"  class="js-switch js-switch-1" id="sso_settings_multi_session" style="margin-right: 0px !important;" data-color="#0092ee" data-size=""/>   
                                                                  Yes
                                                               </div>
                                                            </div>
                                                         </div>
                                                         <div class="form-group mb-0">
                                                            <div class="col-sm-2">
                                                               <label class="control-label mb-10 " >OTP Email to User</label>
                                                            </div>
                                                            <div class="col-sm-10 mb-20">
                                                               <div class="form-group mb-15">
                                                                  No 
                                                                  <input type="checkbox"  class="js-switch js-switch-1" id="sso_settings_otp_email" style="margin-right: 0px !important;" data-color="#0092ee" data-size=""/>   
                                                                  Yes
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
                        <p>2022 &copy; BTMK. Version 1.0.3</p>
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
      <!-- Switchery JavaScript -->
      <script src="../vendors/bower_components/switchery/dist/switchery.min.js"></script>
      <!-- Sweet-Alert  -->
      <script src="../vendors/bower_components/sweetalert/dist/sweetalert.min.js"></script>
      <script src="../vendors/bower_components/jquery-toast-plugin/dist/jquery.toast.min.js"></script>
      <!-- Init JavaScript -->
      <script src="../dist/js/init.js"></script>
      <script src="../vendors/typeahead.js"></script>
      <!-- Form Flie Upload Data JavaScript -->
      <script src="../vendors/bower_components/dropify/dist/js/dropify.min.js"></script>
      <script src="../dist/js/form-file-upload-data.js"></script>

      <!-- Moment JavaScript -->
      <script type="text/javascript" src="../vendors/bower_components/moment/min/moment-with-locales.min.js"></script>
      
      <!-- Bootstrap Daterangepicker JavaScript -->
      <script src="../vendors/bower_components/bootstrap-daterangepicker/daterangepicker.js"></script>
      
      <!-- <script src="../dist/js/widgets-data.js"></script> -->
      <script>
         $.ajaxSetup({
            headers: {'X-CSRF-Token': <?php echo json_encode(oneid_csrf_token()); ?>}
         });

         /* Switchery Init*/
         // var elems = Array.prototype.slice.call(document.querySelectorAll('.js-switch'));
         // $('.js-switch-1').each(function() {
         // 	new Switchery($(this)[0], $(this).data());
         // });
         $(document).ready(function() {
            $('.input-daterange-datepicker').daterangepicker({
               startDate: moment(),
    endDate: moment(),
     buttonClasses: ['btn', 'btn-sm'],
         applyClass: 'btn-info',
         cancelClass: 'btn-default'
   });

         $('#search_user_account_main_progress_bar').hide();
         $('#search_user_account_main_search_result').hide();
         
         
         get_service_provider_list();
         admin_get_all_user_category(0);		
         admin_get_settings();	
         get_all_user_activ_session();
		 
             startTokenRefresh();

             $('a[href="#tab_synclog"]').on('shown.bs.tab', function(){
                load_sync_sessions();
             });
         });
         
         $('#the-basics .typeahead').typeahead(
         {
         hint: true,
         highlight: true,
         minLength: 3,
         items: 10
         },
         {
         // name: 'name',
         source: function (keyword, process, asyncprocess) {
         	$.ajax({
         		type: 'POST',
         		url: '../lib/q_func',
         		dataType: "json",
         		data: {admin_search_keyword_user:"",search_key:keyword},
         		success: function (response) {       
         			asyncprocess(response);
         		},
         		error: function (xhr, error, thrown) {
         		}
         	});
         },
         templates: {
         	empty: [
         	'<p class="tt-suggestion">Opps! Sorry we were unable to find any record</p>'
         	],
         	suggestion: function(name) {
         		var f1 = "";
         		var f2 = "";
         		var f3 = "";
         		if(name.data1 != "" && name.data1 != " "){
         			f1=name.data1;
         		}    	
         		if(name.data3 != "" && name.data3 != " "){
         			f2=" ("+name.data3+") ";
         		}    	
         
         		if(name.data6 != "" && name.data6 != " "){
         			f3=' – ' +name.data6;
         		}
         
         		return '<p>' + f1 + f2+  f3  + '</p>';
         	}
         },
         display: function(name){ 
         	return name.data1
         }
         
         }).on('typeahead:asyncrequest', function() {
         $('.Typeahead-spinner').show();
         }).on('typeahead:asynccancel typeahead:asyncreceive', function() {
         $('.Typeahead-spinner').hide();
         });
         
         $('#the-basics .typeahead').bind('typeahead:select', function(ev, suggestion) {
         search_user_account_main(suggestion['data4']);
         });
         
         
         
         //--------
         
         
         
           //----Login
           function get_service_provider_list(){
            var href = $('#WebAppsTabsHeader li.active a').attr('href'); // e.g. "#SumberManusia_1_tab"
           	$.ajax({
           		type: 'POST',
           		url: '../lib/q_func',
           		dataType: "json",
           		data: {admin_get_all_service_provider:""},
           		beforeSend: function(){
           			$('#tab_available_apps_list_loading').show();
           			$('#tab_available_apps_list').hide();
                        $('#WebAppsTabsHeader').html('');
                        $('#WebAppsTabsContent').html('');
           		},
           		success: function (response) {
           			$('#tab_available_apps_list_loading').hide();
           			$('#tab_available_apps_list').show();
         
           			
           			var list_count = 0;
                       	var tr ='';
                       	var li = '';
                       	var div = '';
                       	$('#WebAppsTabsHeader').html('');
                       	$('#WebAppsTabsContent').html('');
         
                           //generate tabs
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
                           				div += img_sp+'<div class="user-data"><span class="name block capitalize-font"><span class="label label-primary">'+(k+1)+'</span> <b>'+response[i]['data'][k]['sp_name']+'</b></span><span class="time  txt-grey">'+response[i]['data'][k]['sp_description']+'</span><br/><span class="time  txt-primary">URL : '+response[i]['data'][k]['sp_domain']+'</span></div>';
                           			}else{
                           				div += img_sp+'<div class="user-data"><span class="name block capitalize-font"><span class="label label-primary">'+(k+1)+'</span> <b>'+response[i]['data'][k]['sp_name']+' <span class="label label-warning">SSO Not Supported</span></b></span><span class="time  txt-grey">'+response[i]['data'][k]['sp_description']+'</span><br/><span class="time  txt-primary">URL : '+response[i]['data'][k]['sp_domain']+'</span></div>';
                           			}
                           			
                           			div += '<button class="btn btn-primary pull-right btn-xs fixed-btn  x" onclick="open_edit_webapp(&quot;'+response[i]['data'][k]['sp_id']+'&quot;);"><span class="btn-text">View</span></button>';
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
                           			}else{
                           				div += img_sp+'<div class="user-data"><span class="name block capitalize-font"><span class="label label-primary">'+(k+1)+'</span> <b>'+response[i]['data'][k]['sp_name']+' <span class="label label-warning">SSO Not Supported</span></b></span><span class="time  txt-grey">'+response[i]['data'][k]['sp_description']+'</span><br/><span class="time  txt-primary">URL : '+response[i]['data'][k]['sp_domain']+'</span></div>';
                           			}
                           			
                           			div += '<button class="btn btn-primary pull-right btn-xs fixed-btn  " onclick="open_edit_webapp(&quot;'+response[i]['data'][k]['sp_id']+'&quot;);"><span class="btn-text">View</span></button>';
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
         // $('#follo_data_list_count_text').html('');
         $('#follo_data_list').html('<div class="follo-data"><div class="user-data"><span class="time  txt-grey">No apps available.</span></div><div class="clearfix"></div></div>');
         return;
         }else{                		
         // $('#follo_data_list_count_text').html('('+list_count+')');
         }
         
         
         $('#WebAppsTabsHeader').html(li);
         $('#WebAppsTabsContent').html(div);
         if(href!="undefined"){
            $('a[href="'+href+'"]').tab('show');
         }
         
         },
         error: function (xhr, error, thrown) {
         }
         });
         }
         
         
         function admin_get_all_user_category(type){
         $.ajax({
         type: 'POST',
         url: '../lib/q_func',
         dataType: "json",
         data: {admin_get_all_user_category:""},
         beforeSend: function(){
                         // $('#login_status').html('<div class="alert alert-info alert-dismissable alert-style-1"><i class="zmdi zmdi-info-outline"></i>Signing on. Checking info. Wait a moment.</div>');
                     },
                     success: function (response) {
                     	var list_count = 0;
                     	var tr ='';
                     	var select_opt = "";
                     	$('#user_account_type_list').html('');
                     	select_opt = '<option value="">Choose Category</option>';
                     	$.each( response, function( i, value ) {
                     		tr += '<div class="follo-data">';
                     		tr += '<div class="user-data"><span class="name block capitalize-font">'+response[i]['uc_name']+'</span></div>';
                     		tr += '<button class="btn btn-primary pull-right btn-xs fixed-btn" onclick="view_category_user_list(&quot;'+response[i]['uc_id']+'&quot;,&quot;'+response[i]['uc_name']+'&quot;);"><span class="btn-text">User ('+response[i]['total']+')</span></button><button class="btn btn-primary pull-right btn-xs fixed-btn" onclick="open_category_listing(&quot;'+response[i]['uc_id']+'&quot;,&quot;'+response[i]['uc_name']+'&quot;);"><span class="btn-text">App List ('+response[i]['site_count']+')</span></button>';
                     		tr += '<div class="clearfix"></div>';
                     		tr += '</div>';
                     		if(response[i]['uc_id']!="0"){
                     			select_opt += '<option value="'+response[i]['uc_id']+'">'+response[i]['uc_name']+'</option>';
                     		}
                     	});
         
                           $('#user_account_type_list').html(tr);
                           $('#add_new_manual_user_category').html(select_opt);
                           if(type == 0){                    	
                           	$('#modal_user_profile_category').html(select_opt);
                           	$('#add_new_single_user_category').html(select_opt);
                           }
         
         
                       },
                       error: function (xhr, error, thrown) {
                       }
                   });
         }
         
         
         
         function admin_get_settings(){
         $.ajax({
         type: 'POST',
         url: '../lib/q_func',
         dataType: "json",
         data: {admin_get_sso_settings:""},
         beforeSend: function(){
                         // $('#login_status').html('<div class="alert alert-info alert-dismissable alert-style-1"><i class="zmdi zmdi-info-outline"></i>Signing on. Checking info. Wait a moment.</div>');
                     },
                     success: function (response) {
                     	var time_out = Number(response['token_timeout']);
                     	switch(time_out){
                           case 0.5:
                           $('#sso_settings_token_session_timeout').val(response['token_timeout']);
                           case 1:
                           $('#sso_settings_token_session_timeout').val(response['token_timeout']);
                     		break;
                     		case 2:
                     		$('#sso_settings_token_session_timeout').val(response['token_timeout']);
                     		break;
                     		case 12:
                     		$('#sso_settings_token_session_timeout').val(response['token_timeout']);
                     		break;
                     		case 24:
                     		$('#sso_settings_token_session_timeout').val(response['token_timeout']);
                     		break;
                     		case 48:
                     		$('#sso_settings_token_session_timeout').val(response['token_timeout']);
                     		break;
                     		case 72:
                     		$('#sso_settings_token_session_timeout').val(response['token_timeout']);
                     		break;
                     		case 168:
                     		$('#sso_settings_token_session_timeout').val(response['token_timeout']);
                     		break;
                     		default:
                     		$('#sso_settings_token_session_timeout').val(24);
                     		break;
                     	}
         
                     	if(response['multi_session'] == "0"){
                     		$("#sso_settings_multi_session").attr("checked", false);
                     	}else{
                     		$("#sso_settings_multi_session").attr("checked", true);
                     	}
                        if(response['email_OTP'] == "0"){
                           $("#sso_settings_otp_email").attr("checked", false);
                        }else{
                           $("#sso_settings_otp_email").attr("checked", true);
                        }
                     	var elems = Array.prototype.slice.call(document.querySelectorAll('.js-switch'));
                     	elems.forEach(function (html) {
                     		var switchery = new Switchery(html, {});
                     	});
         
                     },
                     error: function (xhr, error, thrown) {
                     }
                 });
         }
         
         function update_configuration(){
         var isChecked=document.getElementById("sso_settings_multi_session").checked;
         var sso_settings_multi_session = 0;

         if(isChecked==true){
         sso_settings_multi_session = 1;
         }else{         
         sso_settings_multi_session = 0;
         }
         var isChecked_OTP_email=document.getElementById("sso_settings_otp_email").checked;
         var sso_settings_OTP_email = 0;

         if(isChecked_OTP_email==true){
         sso_settings_OTP_email = 1;
         }else{         
         sso_settings_OTP_email = 0;
         }
         var token_timeout = 0;
         
         token_timeout = $('#sso_settings_token_session_timeout').val();
         
         $.ajax({
         type: 'POST',
         url: '../lib/q_func',
         dataType: "json",
         data: {update_configuration:"",sso_settings_multi_session:sso_settings_multi_session,token_timeout,token_timeout,sso_settings_OTP_email:sso_settings_OTP_email},
         beforeSend: function(){
                         // $('#login_status').html('<div class="alert alert-info alert-dismissable alert-style-1"><i class="zmdi zmdi-info-outline"></i>Signing on. Checking info. Wait a moment.</div>');
                     },
                     success: function (response) {
                     	if(response == 1){
                     		$.toast().reset('all');                    
                     		$.toast({
                     			heading: '',
                     			text: 'SSO configuration updated',
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
                     			text: 'No update had been made',
                     			position: 'bottom-center',
                     			loaderBg:'#fec107',
                     			icon: 'warning',
                     			hideAfter: 3500, 
                     			stack: 6
                     		}); 
                     	} 					
                     },
                     error: function (xhr, error, thrown) {
                     }
                 });
         }
         
         function open_category_listing(uc_id,uc_text){
         
         $('#text_lst_of_accible_category_app').text('LIST OF ACCESSIBLE APPS');
         $('#tbl_category_site_listing').show();
         $('#tbl_category_site_listing_add_new').hide();
         $('#btn_add_new_apps_to_category').show();
         $('#btn_close_add_new_apps_to_category').hide();
         
         
         
         
         $('#modal_category_user_list_view_id').val(uc_id);
         $('#modal_category_user_list_view_title').text(uc_text);
         $('#modal_category_user_list_view').modal('show');
           	// admin_get_category_user_listing(uc_id);	
           	admin_get_category_site_listing(uc_id);
           }
         
         
           function add_new_apps_to_category(){
           	admin_get_category_site_listing_add_new_site($('#modal_category_user_list_view_id').val());
           	$('#btn_add_new_apps_to_category').hide();
           	$('#btn_close_add_new_apps_to_category').show();
           	$('#text_lst_of_accible_category_app').text('Add New App Access');
           	$('#tbl_category_site_listing').hide();
           	$('#tbl_category_site_listing_add_new').show();
           }
         
           function close_add_new_apps_to_category(){
           	admin_get_category_site_listing($('#modal_category_user_list_view_id').val());
           	$('#btn_add_new_apps_to_category').show();
           	$('#btn_close_add_new_apps_to_category').hide();
           	$('#text_lst_of_accible_category_app').text('LIST OF ACCESSIBLE APPS');
           	// $('#tbl_category_site_listing').show();
           	$('#tbl_category_site_listing_add_new').hide();
           }
         
           
         
         
         
           function admin_get_category_user_listing(uc_id){
           	$.ajax({
           		type: 'POST',
           		url: '../lib/q_func',
           		dataType: "json",
           		data: {admin_get_specific_category_user_listing:"",uc_id:uc_id},
           		beforeSend: function(){
                         // $('#login_status').html('<div class="alert alert-info alert-dismissable alert-style-1"><i class="zmdi zmdi-info-outline"></i>Signing on. Checking info. Wait a moment.</div>');
                     },
                     success: function (response) {
                     	if(uc_id == 0){
                     		$('#btn_remove_category').hide();
                     	}else{                		
                     		$('#btn_remove_category').show();
                     	}
                     	var tr ='';
                     	if(response.length==0){
                     		$('#tbody_category_listing').html('<tr><td colspan="4">No record</td></tr>');
                     		return;
                     	}else{
         
                     	}
                     	$.each( response, function( i, value ) {
                     		tr += '<tr>';
                     		tr += '<td>'+(i+1)+'. </td>';
                     		tr += '<td>'+response[i]['u_id']+'</td>';
                     		tr += '<td>'+response[i]['data1']+'</td>';
                     		tr += '<td><button class="btn btn-primary pull-right btn-xs fixed-btn" type="button" onclick="view_user_profile_from_category_listing(&quot;'+response[i]['u_id']+'&quot;)"><span class="btn-text">View</span></button></td>';
                     		tr += '</tr>';
                     	});
                     	$('#tbody_category_listing').html(tr);
                     },
                     error: function (xhr, error, thrown) {
                     }
                 });
           }
         
         
           function admin_get_category_site_listing(uc_id){
           	$.ajax({
           		type: 'POST',
           		url: '../lib/q_func',
           		dataType: "json",
           		data: {admin_get_category_site_listing:"",uc_id:uc_id},
           		beforeSend: function(){
           			$('#tbl_category_site_listing_add_new_loading').hide();
           			$('#tbl_category_site_listing').hide();
           			$('#tbl_category_site_listing_loading').show();
           			
           		},
           		success: function (response) {
           			$('#tbl_category_site_listing').show();
           			$('#tbl_category_site_listing_loading').hide();
           			var tr ='';
           			if(response.length==0){
           				$('#tbody_category_site_listing').html('<tr><td colspan="3">No record</td></tr>');
           				return;
           			}else{
         
           			}
           			$.each( response, function( i, value ) {
           				tr += '<tr>';
           				tr += '<td>'+(i+1)+'. </td>';
           				tr += '<td>'+response[i]['sp_name']+'</td>';
           				tr += '<td><button class="btn btn-danger pull-right btn-xs fixed-btn" type="button" onclick="remove_acl_category(&quot;'+response[i]['aclgp_id']+'&quot;)"><i class="fa fa-trash"></i></button></td>';
           				tr += '</tr>';
           			});
           			$('#tbody_category_site_listing').html(tr);
           		},
           		error: function (xhr, error, thrown) {
           		}
           	});
           }
         
         
           function admin_get_category_site_listing_add_new_site(uc_id){
           	$.ajax({
           		type: 'POST',
           		url: '../lib/q_func',
           		dataType: "json",
           		data: {admin_get_category_site_listing_add_new_site:"",uc_id:uc_id},
           		beforeSend: function(){
           			$('#tbl_category_site_listing_loading').hide();
           			$('#tbl_category_site_listing_add_new_loading').show();
           			$('#tbody_category_site_listing_add_new').hide();
           		},
           		success: function (response) {
           			$('#tbl_category_site_listing_add_new_loading').hide();
           			$('#tbody_category_site_listing_add_new').show();
           			var tr ='';
           			if(response.length==0){
           				$('#tbody_category_site_listing_add_new').html('<tr><td colspan="3">No site available</td></tr>');
           				return;
           			}else{
         
           			}
           			$.each( response, function( i, value ) {
           				tr += '<tr>';
           				tr += '<td>'+(i+1)+'. </td>';
           				tr += '<td>'+response[i]['sp_name']+'</td>';
           				tr += '<td><button class="btn btn-primary pull-right btn-xs fixed-btn" type="button" onclick="add_acl_category(&quot;'+response[i]['sp_id']+'&quot;)"><i class="fa fa-plus"></i> Add</button></td>';
           				tr += '</tr>';
           			});
           			$('#tbody_category_site_listing_add_new').html(tr);
           		},
           		error: function (xhr, error, thrown) {
           		}
           	});
           }
         
         
           function add_acl_category(sp_id){
           	swal({   
           		title: "Add Allowed Site",   
           		text: "Are you sure you want to Add this site to this category?",   
           		type: "warning",   
           		confirmButtonColor: "#DD6B55",   
           		confirmButtonText: "Yes!",   
           		showCancelButton: true,   
           		closeOnConfirm: false 
           	}, function(){   
         
           		$.ajax({
           			type: 'POST',
           			url: '../lib/q_func',
           			dataType: "json",
           			data: {add_acl_category:'',sp_id:sp_id,uc_id:$('#modal_category_user_list_view_id').val()},     
           			beforeSend: function(){
           			},
           			success: function (response) {
           				if (response == 1){
           					admin_get_category_site_listing_add_new_site($('#modal_category_user_list_view_id').val())
           					admin_get_category_site_listing($('#modal_category_user_list_view_id').val());
           					swal("Add Allowed Site", "Success", "success"); 
           				}else{
           					swal("Add Allowed Site", "Error", "error"); 
           				}
         
           			},
           			error: function (xhr, error, thrown) {
           			}
           		});
           	});
           }
         
           function remove_acl_category(aclgp_id){
           	swal({   
           		title: "Remove Allowed Site",   
           		text: "Are you sure you want to remove this site from this category?",   
           		type: "warning",   
           		confirmButtonColor: "#DD6B55",   
           		confirmButtonText: "Yes!",   
           		showCancelButton: true,   
           		closeOnConfirm: false 
           	}, function(){   
         
           		$.ajax({
           			type: 'POST',
           			url: '../lib/q_func',
           			dataType: "json",
           			data: {remove_acl_category:'',aclgp_id:aclgp_id},     
           			beforeSend: function(){
           			},
           			success: function (response) {
           				if (response == 1){
           					admin_get_category_site_listing($('#modal_category_user_list_view_id').val());
           					swal("Remove Allowed Site", "Success", "success"); 
           				}else{
           					swal("Remove Allowed Site", "Error", "error"); 
           				}
         
           			},
           			error: function (xhr, error, thrown) {
           			}
           		});
           	});
           }
         
         
           function remove_category(){
           	swal({   
           		title: "Remove Cateogry",   
           		text: "You are about to remove this category, by doing so, all user registerd under this category will be set to pending category. Are you sure you want to proceed?",   
           		type: "warning",   
           		confirmButtonColor: "#DD6B55",   
           		confirmButtonText: "Yes!",   
           		showCancelButton: true,   
           		closeOnConfirm: false 
           	}, function(){   
         
           		$.ajax({
           			type: 'POST',
           			url: '../lib/q_func',
           			dataType: "json",
           			data: {admin_remove_category:'',uc_id:$('#modal_category_user_list_view_id').val()},     
           			beforeSend: function(){
           			},
           			success: function (response) {
           				if (response == 1){
           					$('#modal_category_user_list_view').modal('hide');
           					admin_get_all_user_category(0)
           					swal("Remove Category", "Success", "success"); 
           				}else{
           					swal("Remove Category", "Error", "error"); 
           				}
         
           			},
           			error: function (xhr, error, thrown) {
           			}
           		});
           	});
           }
         
           function view_user_profile_from_category_listing(u_id){
           	$('#modal_category_user_list_view').modal('hide');
           	$('#modal_user_profile_sp_list_loading').hide();
           	view_user_profile(u_id,1);
         
           }
         
         
           function clear_search(){
           	$('#the-basics .typeahead').typeahead('val', '');
           	$('#search_user_input').val('');
           	$('#search_user_account_main_search_result').hide();
           }
         
         
           function search_user_account_main(user_id){
           	// var user_id = $('#search_user_account_main').val();
           	$.ajax({
           		type: 'POST',
           		url: '../lib/q_func',
           		dataType: "json",
           		data: {admin_search_user_account:"",user_id:user_id},
           		beforeSend: function(){
           			$('#search_user_account_main_progress_bar').show();
           			$('#search_user_account_main_search_result').hide();
           		},
           		success: function (response) {
           			var tr = "";
           			$('#search_user_account_main_progress_bar').hide();
           			$('#search_user_account_main_search_result').show();
           			if(response.length==0){            
           				tr += '<span class="clabels-text font-12 inline-block txt-dark capitalize-font pull-left">';
           				tr += '<span class="block txt-danger">Sorry, Unable to find any record matches your search</span>';
           				tr += '<div class="clearfix"></div>';
           				$('#search_user_account_main_result_data').html(tr);
           				return;
           			}
           			tr += '<span class="clabels-text font-12 inline-block txt-dark capitalize-font pull-left">';
           			tr += '<span class="block font-15 weight-500 mb-5">'+response['data1']+'</span>';
           			tr += '<span class="block txt-grey">Category: '+response['uc_name']+'</span>';
           			switch(response['source']){
         				case "1": //reg
         				if(response['avail_status']=="0"){
         					tr += '<span class="badge transparent-badge badge-danger capitalize-font">Removed</span>';
         
         				}else{
         					tr += '<span class="badge transparent-badge badge-success capitalize-font">Registered</span>';
         				}
         				break;
         				case "2": //unreg
         				tr += '<span class="badge transparent-badge badge-danger capitalize-font">Unregistered</span>';
         				break;
         			}
         			tr += '</span>';
         			tr += '<button class="btn btn-primary pull-right btn-xs fixed-btn" onclick="view_user_profile(&quot;'+response['u_id']+'&quot;,&quot;'+response['source']+'&quot;)"><span class="btn-text">View</span></button>';
         			tr += '<div class="clearfix"></div>';
         			$('#search_user_account_main_result_data').html(tr);
         			$('#modal_user_profile_lastupdate_text').text(response['u_update_datetime']);
         
         		},
         		error: function (xhr, error, thrown) {
         		}
         	});
           }
         

           function open_add_new_webapp_category(){
               $('#modal_add_new_webapp_category').modal('show');
           }


           var form_add_new_webapp_category = $('#form_add_new_webapp_category');
           form_add_new_webapp_category.on('submit', function(ev){
            ev.preventDefault();
            var data = $('#form_add_new_webapp_category').serializeArray();
            data.push({name: 'action_add_new_webapp_category', value: ''});
            $.ajax({
               type: 'POST',
               url: '../lib/q_func',
               dataType: "json",
               data:data,
               beforeSend: function(){
               },
               success: function (response) {
                  if (response == 1){        
                     get_service_provider_list();   
                     $.toast().reset('all');                    
                     $.toast({
                        heading: '',
                        text: 'App category successfully created',
                        position: 'bottom-center',
                        loaderBg:'#fec107',
                        icon: 'success',
                        hideAfter: 3500, 
                        stack: 6
                     });                        
                     $('#add_new_webapp_category_name').val('');
                  }else{                        
                     $.toast().reset('all');            
                     $.toast({
                        heading: '',
                        text: 'Opps! there is some problem.',
                        position: 'bottom-center',
                        loaderBg:'#fec107',
                        icon: 'warning',
                        hideAfter: 3500, 
                        stack: 6
                     });  
                  }
         
               },
               error: function (xhr, error, thrown) {
               }
            });
           });

           function open_remove_webapp_category(){
            var href = $('#WebAppsTabsHeader li.active a').attr('href'); // e.g. "#SumberManusia_1_tab"
            var number = href.split('_')[1]; // "1"
            if(number == 0){
                  swal("This category could not be remove", "Reason: Defult Category", "error");
               return;
            }
            swal({   
               title: "Remove App Category",   
               text: "Are you sure you want to remove this Category?",   
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
                  data: {action_remove_app_category:'',app_category_id:number},     
                  beforeSend: function(){
                  },
                  success: function (response) {
                     if (response == 1){
                        get_service_provider_list();   
                        swal("App Category Removed", "", "success"); 
                     }else{
                        swal("App Category Remove", "Error", "error"); 
                     }
         
                  },
                  error: function (xhr, error, thrown) {
                  }
               });
            });

           }
         
         
           function open_add_new_webapp(){

           	$.ajax({
           		type: 'POST',
           		url: '../lib/q_func',
           		dataType: "json",
           		data: {admin_get_app_all_group:""},
           		beforeSend: function(){
           		},
           		success: function (response) {

           			$('#add_new_app_category').html('');
           			var option = "";
           			$.each( response, function( i, value ) {
           				option += '<option value="'+response[i]['sp_group_id']+'">'+response[i]['sp_group_name']+'</option>';
           			});
           			$('#add_new_app_category').html(option);
		         	$('#add_new_app_name').val('');
		           	$('#add_new_app_desc').val('');
		           	$('#add_new_app_url').val('');
		           	$('#modal_add_new_app').modal('show');
         		},
         		error: function (xhr, error, thrown) {
         		}
         	});
           }
         
         
           var form_add_new_app = $('#form_add_new_app');
           form_add_new_app.on('submit', function(ev){
           	ev.preventDefault();

            var data = new FormData();

              // 1. Add serialized form fields
              var serializedData = $(this).serializeArray();
              $.each(serializedData, function(i, field) {
                data.append(field.name, field.value);
              });

              // 2. Add file input
              var fileInput = $('#add_new_app_icon')[0];
              if (fileInput.files.length > 0) {
                data.append('app_icon', fileInput.files[0]);
              }

              // 3. Optional extra field
              data.append('action_add_new_app', '');
              // return;

           	// var data = $('#form_add_new_app').serializeArray();
           	// data.push({name: 'action_add_new_app', value: ''});
           	$.ajax({
           		type: 'POST',
           		url: '../lib/q_func',
           		dataType: "json",
           		data:data,
               processData: false, // important
               contentType: false, // important
           		beforeSend: function(){
           		},
           		success: function (response) {
           			if (response.status == 1){        
           				get_service_provider_list();   
           				$.toast().reset('all');                    
           				$.toast({
           					heading: '',
           					text: 'App successfully added',
           					position: 'bottom-center',
           					loaderBg:'#fec107',
           					icon: 'success',
           					hideAfter: 3500, 
           					stack: 6
           				});  								
           				$('#add_new_app_name').val('');
           				$('#add_new_app_desc').val('');
           				$('#add_new_app_url').val('');

                     var input = $('#add_new_app_icon');
                     // 1. Destroy existing instance
                     if (input.data('dropify')) {
                         input.data('dropify').destroy();
                     }
                     // 2. Remove Dropify wrapper elements
                     input.closest('.dropify-wrapper').remove(); // Dropify doesn’t remove this automatically
                     // 3. Replace with clean input
                     var newInput = $('<input type="file" id="add_new_app_icon" name="app_icon" class="dropify" data-default-file="../img/thumb-1.jpg" data-height="100">');
                     $('#add_new_app_icon').replaceWith(newInput); // swap old input
                     // 4. Re-initialize Dropify
                     newInput.dropify();

           			}else{                        
           				$.toast().reset('all');            
           				$.toast({
           					heading: '',
           					text: 'Opps! there is some problem.',
           					position: 'bottom-center',
           					loaderBg:'#fec107',
           					icon: 'warning',
           					hideAfter: 3500, 
           					stack: 6
           				});  
           			}
         
           		},
           		error: function (xhr, error, thrown) {
           		}
           	});
           });
         
         
         // edit_app_category
           function open_edit_webapp(sp_id){
           	$.ajax({
           		type: 'POST',
           		url: '../lib/q_func',
           		dataType: "json",
           		data: {admin_get_app_all_group:""},
           		beforeSend: function(){
           		},
           		success: function (response) {
           			get_specific_app_info(sp_id);
           			$('#edit_app_category').html('');
           			var option = "";
           			$.each( response, function( i, value ) {
           				option += '<option value="'+response[i]['sp_group_id']+'">'+response[i]['sp_group_name']+'</option>';
           			});
           			$('#edit_app_category').html(option);
         		},
         		error: function (xhr, error, thrown) {
         		}
         	});
           	
           }
         
         
           function get_specific_app_info(sp_id){
           	$.ajax({
           		type: 'POST',
           		url: '../lib/q_func',
           		dataType: "json",
           		data: {admin_get_specific_service_provider:"",sp_id:sp_id},
           		beforeSend: function(){
           			$('#modal_edit_app').modal('show');
           			$('#modal_edit_row_loading').show();
           			$('#modal_edit_row_main').hide();
           			$('#modal_edit_btn').hide();
         
           		},
           		success: function (response) {                
           			$('#modal_edit_row_loading').hide();
           			$('#modal_edit_row_main').fadeIn();	
           			$('#modal_edit_btn').fadeIn();	
           			$('#edit_app_id').val(sp_id);         	
           			$('#edit_app_name').val(response['sp_name']);
           			$('#edit_app_desc').val(response['sp_description']);
           			$('#edit_app_url').val(response['sp_domain']);
           			$('#edit_app_code').val(sp_id);
                  $('#edit_existing_app_icon').val(response['sp_image']);
           			if(+response['sp_sso_support']==0){
           				$('#app_info_sso_checkbox').prop('checked', false);
           			}else{
           				$('#app_info_sso_checkbox').prop('checked', true);
           			}
                  if(response['sp_image']!=""){
                     var input = $('#edit_new_app_icon');
                     // 1. Destroy existing instance
                     if (input.data('dropify')) {
                         input.data('dropify').destroy();
                     }

                     // 2. Remove Dropify wrapper elements
                     input.closest('.dropify-wrapper').remove(); // Dropify doesn’t remove this automatically

                     // 3. Replace with clean input
                     var newInput = $('<input type="file" id="edit_new_app_icon" name="app_icon" class="dropify" data-default-file="../public_img/'+response['sp_image']+'" data-height="100">');
                     $('#edit_new_app_icon').replaceWith(newInput); // swap old input

                     // 4. Re-initialize Dropify
                     newInput.dropify();
                  }else{
                     var input = $('#edit_new_app_icon');
                     // 1. Destroy existing instance
                     if (input.data('dropify')) {
                         input.data('dropify').destroy();
                     }

                     // 2. Remove Dropify wrapper elements
                     input.closest('.dropify-wrapper').remove(); // Dropify doesn’t remove this automatically

                     // 3. Replace with clean input
                     var newInput = $('<input type="file" id="edit_new_app_icon" name="app_icon" class="dropify" data-default-file="../img/thumb-1.jpg" data-height="100">');
                     $('#edit_new_app_icon').replaceWith(newInput); // swap old input

                     // 4. Re-initialize Dropify
                     newInput.dropify();
                  }


           			$('#edit_app_category').val(response['sp_group_id']);

           			
         	    	// $('#modal_edit_app').modal('show');
         
         	    },
         	    error: function (xhr, error, thrown) {
         	    }
         	});
           }
         
         
           var form_edit_app = $('#form_edit_app');
           form_edit_app.on('submit', function(ev){
           	ev.preventDefault();
           	// var data = $('#form_edit_app').serializeArray();
           	// data.push({name: 'action_edit_app_info', value: ''});
               var data = new FormData();

              // 1. Add serialized form fields
              var serializedData = $(this).serializeArray();
              $.each(serializedData, function(i, field) {
                data.append(field.name, field.value);
              });

              // 2. Add file input
              var fileInput = $('#edit_new_app_icon')[0];
              if (fileInput.files.length > 0) {
                data.append('app_icon', fileInput.files[0]);
              }else {
                 // No new image uploaded — send info about the existing one
                 var existingIcon = $('#edit_existing_app_icon').val();
                 data.append('existing_app_icon', existingIcon);
               }

              // 3. Optional extra field
              data.append('action_edit_app_info', '');

           	$.ajax({
           		type: 'POST',
           		url: '../lib/q_func',
           		dataType: "json",
           		data:data,
               processData: false, // important
               contentType: false, // important
           		beforeSend: function(){
           		},
           		success: function (response) {
           			if (response.status == 1){        
           				get_service_provider_list();   
           				$.toast().reset('all');                    
           				$.toast({
           					heading: '',
           					text: 'Record updated',
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
           					text: 'No changes detected',
           					position: 'bottom-center',
           					loaderBg:'#fec107',
           					icon: 'warning',
           					hideAfter: 3500, 
           					stack: 6
           				});  
           			}
         
           		},
           		error: function (xhr, error, thrown) {
           		}
           	});
           });
         
         
           function remove_app(){
           	var app_id = $('#edit_app_code').val();
           	swal({   
           		title: "Remove App",   
           		text: "Are you sure you want to remove this app?",   
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
           			data: {action_remove_app:'',app_id:app_id},     
           			beforeSend: function(){
           			},
           			success: function (response) {
           				if (response == 1){
           					$('#modal_edit_app').modal('hide');
           					get_service_provider_list();   
           					swal("App Removed", "", "success"); 
           				}else{
           					swal("App Remove", "Error", "error"); 
           				}
         
           			},
           			error: function (xhr, error, thrown) {
           			}
           		});
           	});
           }
         
         
           function view_user_profile(user_id,status){
           	$('#text_lst_of_accible_app').text('List of Accessible apps');
           	$('#btn_add_new_specific_apps_to_accessible').show();
           	$('#btn_close_add_new_specific_apps_to_accessible').hide();
               // $('#modal_user_profile_sp_add_new_list_div').hide();
         
               $('#modal_user_profile_sp_list_div').show();
               $('#modal_user_profile_sp_add_new_list_div').hide();
               $('#modal_user_profile').modal('show');
               get_specific_user_profile_info(user_id,status);
               get_specific_user_sp_access_list(user_id);
           	// $('#modal_user_profile_sp_list_loading').hide();
           }
         
         
           var temp_prev_category = "";
           function get_specific_user_profile_info(user_id,status){
           	$.ajax({
           		type: 'POST',
           		url: '../lib/q_func',
           		dataType: "json",
           		data: {admin_get_get_specific_user_profile_info:"",user_id,source:status},
           		beforeSend: function(){
           			$('.modal_user_profile_loading_text').show();
           			$('.modal_user_profile_input').hide();
         
           			$('#modal_user_profile_name').val('');
           			$('#modal_user_profile_id').val('');
           			$('#modal_user_profile_status').html('-');
           		},
           		success: function (response) {       
         
           			$('.modal_user_profile_loading_text').hide();
           			$('.modal_user_profile_input').show();
           			switch(response['source']){
         				case "1": //reg
                     var u_id_text= "";
                     if(response['data3'] != " "){
                        u_id_text = response['data3'];
                     }else{
                        u_id_text = response['data4'];
                     }
         				$('#modal_user_profile_name').val(response['data1']);
         				$('#modal_user_profile_id').val(u_id_text);
         				if(response['u_category'] == "0"){
         					$('#modal_user_profile_category').val('');
         					temp_prev_category = '';
         				}else{
         					$('#modal_user_profile_category').val(response['u_category']);
         					temp_prev_category = response['u_category'];
         				}
         				$('#modal_user_profile_user_id').val(response['u_id']);
         				if(response['avail_status']=="0"){
         					$('#modal_user_profile_status').html('<div class="alert alert-danger alert-dismissable mt-10">Status : Removed</div></div>');	
         					$('.user_info_btn').hide();
         					$('.user_info_btn_reactivate').show();                				
         					$(".modal_user_profile_input_enable").prop('disabled', true);
         				}else{
         					$('#modal_user_profile_status').html('<div class="alert alert-success alert-dismissable mt-10">Status : Registered</div></div>');
         					$('.user_info_btn').show();	                		
         					$('.user_info_btn_reactivate').hide();	
         					$(".modal_user_profile_input_enable").prop('disabled', false);
         					$('#modal_user_profile_lastupdate_text').text(response['u_update_datetime']);
         				}
         				break;
         				case "2": //unreg
         				$('#modal_user_profile_status').html('<div class="alert alert-danger alert-dismissable mt-10">Status: Unregistered</div></div>');
         				$('#modal_user_profile_name').val(response['data1']);
         				$('#modal_user_profile_id').val(u_id_text);
         				$('#modal_user_profile_category').val('');
         				$('#modal_user_profile_user_id').val(response['u_id']);
         				temp_prev_category = '';
         				break;
         			}
         		},
         		error: function (xhr, error, thrown) {
         		}
         	});
           }
         
           function get_specific_user_sp_access_list(user_id){
           	$.ajax({
           		type: 'POST',
           		url: '../lib/q_func',
           		dataType: "json",
           		data: {get_specific_user_sp_access_list:"",u_id:user_id},
           		beforeSend: function(){
           			$('#modal_user_profile_sp_list_loading').show();
           			$('#modal_user_profile_sp_list').html('');
                         // $('#login_status').html('<div class="alert alert-info alert-dismissable alert-style-1"><i class="zmdi zmdi-info-outline"></i>Signing on. Checking info. Wait a moment.</div>');
                     },
                     success: function (response) {
         
                     	var tr = '';
                     	$.each( response, function( i, value ) {
                     		var checked = '';
                     		var disable_label = "";
         	        	if(response[i]['status']!=1){ //blockedv
         	        		tr += '<a href="#" class="list-group-item" onclick="uplift_blacklist(&quot;'+response[i]['aclblk_id']+'&quot;)">';
         	        		tr += '<span class="badge transparent-badge badge-success capitalize-font"><span class="label label-danger">Denied</span></span>';
         	        		tr += '<p class=" pull-left">'+(Number(i)+Number(1))+'. '+response[i]['sp_name']+'</p>';
         	        		tr += '<div class="clearfix"></div>';
         	        		tr += '</a>';
         	        	}else{
         	        		tr += '<a href="#" class="list-group-item" onclick="deny_access(&quot;'+response[i]['sp_id']+'&quot;)">';
         	        		tr += '<span class="badge transparent-badge badge-success capitalize-font"><span class="label label-success">Allowed</span></span>';
         	        		tr += '<p class=" pull-left">'+(Number(i)+Number(1))+'. '+response[i]['sp_name']+'</p>';
         	        		tr += '<div class="clearfix"></div>';
         	        		tr += '</a>';			        		
         	        	}
         	        });
         
                     	$('#modal_user_profile_sp_list_loading').hide();
                     	$('#modal_user_profile_sp_list').html(tr);
         
                     },
                     error: function (xhr, error, thrown) {
                     }
                 });
           }
         
         
           function add_new_specific_apps_to_accessible(){
         
           	$('#text_lst_of_accible_app').text('Add new app access');
           	get_add_new_specific_apps_to_accissible_list();
           	$('#btn_add_new_specific_apps_to_accessible').hide();
           	$('#btn_close_add_new_specific_apps_to_accessible').show();
           	$('#modal_user_profile_sp_list_div').fadeOut();
           	$('#modal_user_profile_sp_add_new_list_div').fadeIn();
           }
         
           function close_add_new_specific_apps_to_accessible(){
           	$('#text_lst_of_accible_app').text('LIST OF ACCESSIBLE APPS');
           	$('#btn_add_new_specific_apps_to_accessible').show();
           	$('#btn_close_add_new_specific_apps_to_accessible').hide();
           	$('#modal_user_profile_sp_list_div').fadeIn();
           	$('#modal_user_profile_sp_add_new_list_div').fadeOut();
           }
         
         
           function get_add_new_specific_apps_to_accissible_list(){
           	$.ajax({
           		type: 'POST',
           		url: '../lib/q_func',
           		dataType: "json",
           		data:{get_add_new_specific_apps_to_accissible_list:"",u_id:$('#modal_user_profile_user_id').val()},
           		beforeSend: function(){
           			$('#modal_user_profile_sp_list_loading').show();
           		},
           		success: function (response) {
           			var tr = '';
           			if(response.length == 0){
         
           				$('#modal_user_profile_sp_add_new_list_div').html('No Available Apps');
           				$('#modal_user_profile_sp_list_loading').hide();
           				return;
           			}
           			$.each( response, function( i, value ) {
           				tr += '<a href="#" class="list-group-item" onclick="add_new_specific_apps_to_user(&quot;'+response[i]['sp_id']+'&quot;)">';
           				tr += '<span class="badge transparent-badge badge-success capitalize-font"><span class="label label-success">+ Add</span></span>';
           				tr += '<p class=" pull-left">'+(i+1)+'. '+response[i]['sp_name']+'</p>';
           				tr += '<div class="clearfix"></div>';
           				tr += '</a>';		
           			});
           			$('#modal_user_profile_sp_list_loading').hide();
           			$('#modal_user_profile_sp_add_new_list_div').html(tr);
         
           		},
           		error: function (xhr, error, thrown) {
           		}
           	});
           }
         
         
           function add_new_specific_apps_to_user(sp_id){
           	swal({   
           		title: "Add Access",   
           		text: "Are you sure you want to add access to this user?",   
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
           			data: {add_new_specific_apps_to_user:'',sp_id:sp_id,u_id:$('#modal_user_profile_user_id').val()},     
           			beforeSend: function(){
           			},
           			success: function (response) {
           				if (response == 1){
           					get_specific_user_sp_access_list($('#modal_user_profile_user_id').val());
           					$('#text_lst_of_accible_app').text('LIST OF ACCESSIBLE APPS');
           					$('#btn_add_new_specific_apps_to_accessible').show();
           					$('#btn_close_add_new_specific_apps_to_accessible').hide();
           					$('#modal_user_profile_sp_list_div').fadeIn();
           					$('#modal_user_profile_sp_add_new_list_div').fadeOut();
           					swal("Add Access", "Success", "success"); 
           				}else{
           					swal("Add Access", "Error", "error"); 
           				}
         
           			},
           			error: function (xhr, error, thrown) {
           			}
           		});
           	});
           }
         
         
           function open_blacklist_modal(){
           	admin_get_all_blacklist_record();
           	$('#modal_acl_blacklist').modal('show');
           }
         
         
         
           function admin_get_all_blacklist_record(){
           	$.ajax({
           		type: 'POST',
           		url: '../lib/q_func',
           		dataType: "json",
           		data: {admin_get_all_blacklist_record:""},
           		beforeSend: function(){
                         // $('#login_status').html('<div class="alert alert-info alert-dismissable alert-style-1"><i class="zmdi zmdi-info-outline"></i>Signing on. Checking info. Wait a moment.</div>');
                     },
                     success: function (response) {       
                     	var tr = '';
                     	if(response.length == 0){
                     		$('#tbody_blacklist_record').html('<tr><td colspan="4">No record yet</td></tr>');
                     		return;
                     	}else{
         
                     	}
                     	$.each( response, function( i, value ) {
                     		tr += '<tr>';
                     		tr += '<td>'+(i+1)+'</td>';
                     		tr += '<td>'+response[i]['data1']+'</td>';
                     		tr += '<td>'+response[i]['sp_name']+'</td>';
                     		tr += '<td><button type="button" class="btn btn-danger btn-rounded btn-xs" onclick=uplift_blacklist(&quot;'+response[i]['aclblk_id']+'&quot;)>Uplift</button></td>';
                     		tr += '</tr>';
                     	});
                     	$('#tbody_blacklist_record').html(tr);
         
                     },
                     error: function (xhr, error, thrown) {
                     }
                 });
           }
         
         
           function deny_access(sp_id,user_id){
           	swal({   
           		title: "Deny Access",   
           		text: "Are you sure you want to deny this user from accessing this apps?",   
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
           			data: {admin_set_deny_access_record:'',sp_id:sp_id, user_id:$('#modal_user_profile_user_id').val()},     
           			beforeSend: function(){
           			},
           			success: function (response) {
           				if (response == 1){
           					get_specific_user_sp_access_list($('#modal_user_profile_user_id').val());
           					swal("Deny Access", "Success", "success"); 
           				}else{
           					swal("Deny Access", "Error", "error"); 
           				}
         
           			},
           			error: function (xhr, error, thrown) {
           			}
           		});
           	});
           }
         
         
           function uplift_blacklist(aclblk_id){
           	swal({   
           		title: "Uplift Deny Access",   
           		text: "Are you sure you want to uplift this user access record?",   
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
           			data: {admin_uplift_blacklist_record:'',aclblk_id:aclblk_id},     
           			beforeSend: function(){
           			},
           			success: function (response) {
           				if (response == 1){
           					admin_get_all_blacklist_record();
           					get_specific_user_sp_access_list($('#modal_user_profile_user_id').val());
           					swal("Uplift", "Success", "success"); 
           				}else{
           					swal("Uplift", "Error", "error"); 
           				}
         
           			},
           			error: function (xhr, error, thrown) {
           			}
           		});
           	});
           }
         
           function open_add_blacklist(){
           	$('#modal_acl_blacklist').modal('hide');
           	$('#modal_acl_add_blacklist').modal('show');
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
                     		window.location.href = response['domain'];
                     	}else{
                     		get_service_provider_list();
                     	}
         
                     },
                     error: function (xhr, error, thrown) {
                     }
                 });
           }
         
         
           function copyToClipboard(element) {
           	/* Get the text field */
           	var copyText = document.getElementById(element);
         
           	/* Select the text field */
           	copyText.select();
           	copyText.setSelectionRange(0, 99999); /* For mobile devices */
         
           	/* Copy the text inside the text field */
           	navigator.clipboard.writeText(copyText.value);
           	
           	$.toast().reset('all');       
           	$.toast({
           		heading: '',
           		text: 'API Code copied',
           		position: 'bottom-center',
           		loaderBg:'#fec107',
           		icon: 'success',
           		hideAfter: 3500, 
           		stack: 6
           	});
           	/* Alert the copied text */
         // alert("Copied the text: " + copyText.value);
         }
         
         
         
         $('body').on('change', '#modal_user_profile_category', function (event) {
         var curr_selected_val = this.value;
         switch(this.value){
                 case "": //back
                 // get_recipient_type_list();
                 // alert();
                 return;
                 break;
                 default:			
                 swal({   
                 	title: "Change Category",   
                 	text: "Are you sure you want to change this user category?",   
                 	type: "warning",   
                 	showCancelButton: true,   
                 	confirmButtonColor: "#DD6B55",   
                 	confirmButtonText: "Yes!",   
                 	closeOnConfirm: true,
                 	closeOnCancel: true
                 }, function(inputValue){   
                 	//Use the "Strict Equality Comparison" to accept the user's input "false" as string)
                 	if (inputValue===false) {
                 		$('#modal_user_profile_category').val(temp_prev_category);
                 	} else {
                 		temp_prev_category = curr_selected_val;
                 		$.ajax({
                 			type: 'POST',
                 			url: '../lib/q_func',
                 			dataType: "json",
                 			data: {admin_change_user_category:'',category_id:curr_selected_val,user_id:$('#modal_user_profile_user_id').val()},     
                 			beforeSend: function(){
                 			},
                 			success: function (response) {
                 				if (response == 1){
                 					get_specific_user_sp_access_list($('#modal_user_profile_user_id').val());
                 					admin_get_all_user_category(1);
                 					swal("Category Changed", "Success", "success"); 
                 				}else{
                 					swal("Category Changed", "Error", "error"); 
                 				}
         
                 			},
                 			error: function (xhr, error, thrown) {
                 			}
                 		});
         
                 	}
         
         
                 	
                 });
                 break;
             }        
         });
         
         
         function add_new_category(){
         $('#modal_add_new_category').modal('show');
         $('#add_new_category_name').val('');
         }
         
         
         
         var form_add_new_category = $('#form_add_new_category');
         form_add_new_category.on('submit', function(ev){
         ev.preventDefault();
         var data = $('#form_add_new_category').serializeArray();
         data.push({name: 'action_add_new_category', value: ''});
         $.ajax({
         type: 'POST',
         url: '../lib/q_func',
         dataType: "json",
         data:data,
         beforeSend: function(){
         },
         success: function (response) {
         	if (response == 1){        
         		admin_get_all_user_category(0);		   
         		$.toast().reset('all');                    
         		$.toast({
         			heading: '',
         			text: 'Category successfully added',
         			position: 'bottom-center',
         			loaderBg:'#fec107',
         			icon: 'success',
         			hideAfter: 3500, 
         			stack: 6
         		});  								
         		$('#add_new_category_name').val('');
         	}else{                        
         		$.toast().reset('all');            
         		$.toast({
         			heading: '',
         			text: 'Opps! there is some problem.',
         			position: 'bottom-center',
         			loaderBg:'#fec107',
         			icon: 'warning',
         			hideAfter: 3500, 
         			stack: 6
         		});  
         	}
         
         },
         error: function (xhr, error, thrown) {
         }
         });
         });
         
         function add_new_user_option(){
         $('#modal_open_add_user_option').modal('show');
         $(".add_new_manual_user_input").prop('disabled', false);
         $(".add_new_manual_user_input_loading_text").hide();
         }
         
         function pick_preview_sync_user(){
            $.ajax({
               type: 'POST',
               url: '../lib/q_func',
               dataType: "json",
               data: {admin_preview_sync_user:''},
               beforeSend: function(){
                  $('#btn_sync').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Previewing...');
                  $('#sync_status_msg').show().text('Generating read-only preview...');
                  $('#modal_open_add_user_option').modal('hide');
                  $('#modal_add_new_single_user').modal('show');
                  $('#sync_progress_id').show();
                  $('#sync_result_div').hide();
               },
               success: function (response) {
                  $('#sync_progress_id').hide();
                  $('#sync_result_div').show();
                  if(!response || response.status !== 1 || response.mode !== 'preview'){
                     var reference = response && response.correlation_id ? ' Reference: ' + response.correlation_id : '';
                     $('#sync_preview_status').text('Preview failed.' + reference).addClass('badge badge-danger');
                     return;
                  }
                  var counts = response.counts || {};
                  $('#sync_preview_source_rows').text(response.source_rows || 0);
                  $('#sync_preview_new_update').text((counts.New || 0) + ' / ' + (counts.Update || 0));
                  $('#sync_preview_deactivate_reactivate').text((counts.Deactivate || 0) + ' / ' + (counts.Reactivate || 0));
                  $('#sync_preview_protected').text((response.protected_manual_users || 0) + ' / ' + (response.discarded_protected_collisions || 0));
                  $('#sync_preview_hash_expiry').text(String(response.plan_hash || '').substring(0, 12) + '... / ' + (response.expires_at || '-'));
                  $('#sync_preview_status')
                     .removeClass('badge-danger badge-warning badge-success')
                     .addClass(response.risk_level === 'blocked' ? 'badge badge-danger' : (response.risk_level === 'warning' ? 'badge badge-warning' : 'badge badge-success'))
                     .text(response.risk_level === 'blocked'
                        ? 'BLOCKED — anomaly or baseline requires review'
                        : (response.approval_ready === true
                           ? 'READY FOR CONTROLLED PILOT — Apply remains disabled'
                           : 'PREVIEW ONLY — no changes applied'));
                  var warningList = $('#sync_preview_warnings').empty();
                  (response.warnings || []).forEach(function(warning){
                     $('<li>').text(warning).appendTo(warningList);
                  });
                  if((response.warnings || []).length === 0){
                     $('<li>').text('No planner warning detected.').appendTo(warningList);
                  }
               },
               error: function () {
                  $('#sync_progress_id').hide();
                  $('#sync_result_div').show();
                  $('#sync_preview_status').text('Preview failed. Please try again.').addClass('badge badge-danger');
               },
               complete: function(){
                  $('#btn_sync').prop('disabled', false).html('<i class="fa fa-search"></i> Preview external sync');
                  $('#sync_status_msg').hide().text('');
               }
            });
         }
         
         function pick_add_single_user(){        	
         $('#modal_open_add_user_option').modal('hide');
         $('#modal_add_new_user_manual').modal('show');
         }
         
         
         var form_add_new_user_manual = $('#form_add_new_user_manual');
         form_add_new_user_manual.on('submit', function(ev){
         ev.preventDefault();
         var data = $('#form_add_new_user_manual').serializeArray();
         data.push({name: 'action_add_new_user_manual_check_user_id', value: ''});
         $.ajax({
         type: 'POST',
         url: '../lib/q_func',
         dataType: "json",
         data:data,
         beforeSend: function(){
         	$(".add_new_manual_user_input").prop('disabled', true);
         	$(".add_new_manual_user_input_loading_text").show();
         },
         success: function (response) {
         	if (response['status'] == 0){                                    	
         		$(".add_new_manual_user_input").prop('disabled', false);
         		$(".add_new_manual_user_input_loading_text").hide();
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
         	}else{                
         		$('#add_new_manual_user_id').val('');
         		$('#add_new_manual_user_name').val('');
         		$('#add_new_manual_user_category').val('');
         
         		$('#add_new_user_data2').val('');
         		$('#add_new_user_data3').val('');
         		$('#add_new_user_data5').val('');
         		$('#add_new_user_data6').val('');
         		$('#add_new_user_data7').val('');
         		$('#add_new_user_data8').val('');
         		$('#add_new_user_data9').val('');
         		$('#add_new_user_data10').val('');
         		$('#add_new_user_data11').val('');
         		$('#add_new_user_data12').val('');
         
         		$(".add_new_manual_user_input_loading_text").hide();
         		$(".add_new_manual_user_input").prop('disabled', false);
         		admin_get_all_user_category(0);
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
         	}
         
         },
         error: function (xhr, error, thrown) {
            $(".add_new_manual_user_input").prop('disabled', false);
            $(".add_new_manual_user_input_loading_text").hide();
            $.toast().reset('all');
            $.toast({
               heading: '',
               text: 'User tidak dapat ditambah. Sila cuba semula atau semak log menggunakan ID rujukan.',
               position: 'bottom-center',
               loaderBg:'#fec107',
               icon: 'error',
               hideAfter: 5000,
               stack: 6
            });
         }
         });
         });
         
         
         function nav_back_to_category_listing(){
         $('#modal_user_profile').modal('hide');
         $('#modal_category_user_list_view').modal('show');
         }
         
         
         
         
         function user_info_reactivate_user(){
         var user_info_id = $('#modal_user_profile_user_id').val();
         // return;
         swal({   
         	title: "Reactivate user",   
         	text: "Are you sure you want to reactivate this user?",   
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
         		data: {admin_reactivate_user_record:'',user_info_id:user_info_id},     
         		beforeSend: function(){
         		},
         		success: function (response) {
         			if (response['status'] == 1){
         				get_specific_user_profile_info(user_info_id,response['source_status']);
         				get_specific_user_sp_access_list(user_info_id);
         				swal("Reactivate", "Success", "success");                                 
         				get_service_provider_list();
         				admin_get_all_user_category(1);		
         			}else{
         				swal("Reactivate", "Error", "error"); 
         			}
         
         		},
         		error: function (xhr, error, thrown) {
         		}
         	});
         });
         }
         
         
         function user_info_deactivate_user(){
         var user_info_id = $('#modal_user_profile_user_id').val();
         // return;
         swal({   
         	title: "Remove user",   
         	text: "Are you sure you want to remove this user?",   
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
         		data: {admin_deactivate_user_record:'',user_info_id:user_info_id},     
         		beforeSend: function(){
         		},
         		success: function (response) {
         			if (response['status'] == 1){
         				get_specific_user_profile_info(user_info_id,response['source_status']);
         				get_specific_user_sp_access_list(user_info_id);
         				swal("Remove", "Success", "success");                                 
         				get_service_provider_list();
         				admin_get_all_user_category(1);		
         			}else{
         				swal("Reactivate", "Error", "error"); 
         			}
         
         		},
         		error: function (xhr, error, thrown) {
         		}
         	});
         });
         }
         
         function view_category_user_list(cat_id,cat_name){
         window.open("./user_list.php?category_id="+cat_id+"&category_name="+encodeURIComponent(cat_name), '_blank');
         }
         
         function modal_user_profile_resync(){
         var user_id = 	$('#modal_user_profile_user_id').val();
         $.ajax({
         	type: 'POST',
         	url: '../lib/q_func',
         	dataType: "json",
         	data: {admin_resync_specific_user:'',user_id:user_id},     
         	beforeSend: function(){
         	},
         	success: function (response) {
         		if(response['status']==0){
         			$.toast().reset('all');            
         			$.toast({
         				heading: '',
         				text: "Data already sync",
         				position: 'bottom-center',
         				loaderBg:'#fec107',
         				icon: 'warning',
         				hideAfter: 3500, 
         				stack: 6
         			});  
         		}else{
         			$.toast().reset('all');            
         			$.toast({
         				heading: '',
         				text: "Sync Success",
         				position: 'bottom-center',
         				loaderBg:'#fec107',
         				icon: 'success',
         				hideAfter: 3500, 
         				stack: 6
         			});  
         			get_specific_user_profile_info(user_id,1);
         		}
         
         	},
         	error: function (xhr, error, thrown) {
         	}
         });
         }
         
         
         function get_all_user_activ_session(){
         $.ajax({
         	type: 'POST',
         	url: '../lib/q_func',
         	dataType: "json",
         	data: {admin_get_all_token_for_all_active_user:""},
         	beforeSend: function(){
         		$('#app_security_session_loading').fadeIn();
         		$('#app_security_session_list').hide();                 
         	},
         	success: function (response) {
         		
				
$('#app_security_session_loading').hide();
$('#app_security_session_list').fadeIn();


var $tbody = $('#security_tab_session');
$tbody.empty();

if (!Array.isArray(response) || response.length === 0) {
    $tbody.append(
        '<tr><td class="text-muted p-1"><small>No active sessions.</small></td></tr>'
    );
} else {
    $.each(response, function (i, session) {
        var isCurrent   = session.current_token === "1";
        var currentIcon = isCurrent
            ? '<i class="fa fa-check-circle text-primary ms-1"></i>'
            : '';
        var label = isCurrent ? 'currently on' : 'on';

        var cellText =
            (i + 1) + '. [' + session.token_datetime + '] ' +
            session.name + ' ' + label + ' : ' +
            session.device_info;

        var rowHtml =
            '<tr>' +
                '<td class="p-0 align-middle" style="padding:0px;">' +
                    '<small class="text-muted">' + cellText + '</small>' +
                    currentIcon +
                '</td>' +
            '</tr>';

        $tbody.append(rowHtml);
    });
}


         
                       },
                       error: function (xhr, error, thrown) {
                       }
                   });
         }
         
         

         function search_audit_date_range(){
            $.ajax({
            type: 'POST',
            url: '../lib/q_func',
            dataType: "json",
            data: {admin_get_audit_range:'',audit_search_daterange:$('#audit_search_daterange').val()},     
            beforeSend: function(){
            },
            success: function (response) {
               if(response.length == 0){
                  $.toast().reset('all');            
                  $.toast({
                     heading: '',
                     text: "No result found",
                     position: 'bottom-center',
                     loaderBg:'#fec107',
                     icon: 'warning',
                     hideAfter: 3500, 
                     stack: 6
                  });  
                  $('#audit_search_result_tbody').html('<tr><td col="4">No result found</td></tr>');
               }else{
                  $.toast().reset('all');            
                  $.toast({
                     heading: '',
                     text: "Result found",
                     position: 'bottom-center',
                     loaderBg:'#fec107',
                     icon: 'success',
                     hideAfter: 3500, 
                     stack: 6
                  });  
                  var tr="";
                  $.each( response, function( i, value ) {
                           tr += '<tr>';
                           tr += '<td>'+response[i]['datetime']+'</td>';
                           tr += '<td>'+response[i]['log_type']+'</td>';
                           tr += '<td>'+response[i]['log_detail']+'</td>';
                           tr += '<td>'+response[i]['ip_addr']+'</td>';
                           tr += '</tr>';
                        });
                  $('#audit_search_result_tbody').html(tr);

               }
         
            },
            error: function (xhr, error, thrown) {
            }
         });
         }

         function sync_status_badge(status){
            var labels = {
               '0': ['In Progress', 'badge-warning'],
               '1': ['Temp Only', 'badge-info'],
               '2': ['Complete', 'badge-success'],
               '3': ['No Data', 'badge-default'],
               '4': ['No Changes', 'badge-default']
            };
            var info = labels[String(status)] || ['Unknown', 'badge-default'];
            return '<span class="badge ' + info[1] + '">' + info[0] + '</span>';
         }

         function sync_format_triggered_by(row){
            var uid = row.triggered_by || '';
            if(!uid){ return '-'; }
            var name = String(row.triggered_by_name || '').trim();
            if(name){ return name + ' (' + uid + ')'; }
            return uid;
         }

         function sync_action_badge(action){
            var classes = {
               'NEW': 'badge-success',
               'UPDATE': 'badge-warning',
               'DEACTIVATE': 'badge-danger',
               'REACTIVATE': 'badge-info'
            };
            var cls = classes[action] || 'badge-default';
            return '<span class="badge ' + cls + '">' + action + '</span>';
         }

         function sync_format_json_cell(value){
            if(value === null || value === undefined || value === ''){
               return '-';
            }
            try {
               var parsed = (typeof value === 'string') ? JSON.parse(value) : value;
               if(Array.isArray(parsed)){
                  return parsed.join(', ');
               }
               if(typeof parsed === 'object'){
                  var parts = [];
                  $.each(parsed, function(key, val){
                     parts.push(key + ': ' + val);
                  });
                  return parts.join('<br>');
               }
               return String(parsed);
            } catch(e){
               return String(value);
            }
         }

         function show_sync_session_list(){
            $('#sync_detail_panel').hide();
            $('#sync_session_panel').show();
         }

         var syncSessionsData = [];
         var SYNC_SESSIONS_PAGE_SIZE = 10;
         var syncLogDetailData = [];
         var SYNC_DETAIL_PAGE_SIZE = 10;

         function render_sync_pagination(currentPage, totalPages){
            if(totalPages <= 1){
               $('#sync_session_pagination').html('');
               return;
            }
            var html = '<ul class="pagination pagination-sm">';
            if(currentPage <= 1){
               html += '<li class="disabled"><a href="javascript:void(0)">Prev</a></li>';
            }else{
               html += '<li><a href="javascript:void(0)" onclick="render_sync_sessions_page(' + (currentPage - 1) + ')">Prev</a></li>';
            }
            var startPage = Math.max(1, currentPage - 2);
            var endPage = Math.min(totalPages, startPage + 4);
            startPage = Math.max(1, endPage - 4);
            for(var p = startPage; p <= endPage; p++){
               if(p === currentPage){
                  html += '<li class="active"><a href="javascript:void(0)">' + p + '</a></li>';
               }else{
                  html += '<li><a href="javascript:void(0)" onclick="render_sync_sessions_page(' + p + ')">' + p + '</a></li>';
               }
            }
            if(currentPage >= totalPages){
               html += '<li class="disabled"><a href="javascript:void(0)">Next</a></li>';
            }else{
               html += '<li><a href="javascript:void(0)" onclick="render_sync_sessions_page(' + (currentPage + 1) + ')">Next</a></li>';
            }
            html += '</ul>';
            $('#sync_session_pagination').html(html);
         }

         function render_sync_sessions_page(page){
            var totalPages = Math.ceil(syncSessionsData.length / SYNC_SESSIONS_PAGE_SIZE) || 1;
            if(page < 1){ page = 1; }
            if(page > totalPages){ page = totalPages; }
            var start = (page - 1) * SYNC_SESSIONS_PAGE_SIZE;
            var rows = syncSessionsData.slice(start, start + SYNC_SESSIONS_PAGE_SIZE);
            var tr = '';
            $.each(rows, function(i, row){
               var dtStart = row.ext_head_dt_start || '-';
               var dtEnd = row.ext_head_dt_end ? ' — ' + row.ext_head_dt_end : '';
               tr += '<tr>';
               tr += '<td>' + row.ext_head_id + '</td>';
               tr += '<td>' + dtStart + dtEnd + '</td>';
               tr += '<td>' + sync_format_triggered_by(row) + '</td>';
               tr += '<td>' + (row.total_new || 0) + '</td>';
               tr += '<td>' + (row.total_updated || 0) + '</td>';
               tr += '<td>' + (row.total_deactivated || 0) + '</td>';
               tr += '<td>' + (row.total_reactivated || 0) + '</td>';
               tr += '<td>' + sync_status_badge(row.ext_head_status) + '</td>';
               tr += '<td><button type="button" class="btn btn-xs btn-primary btn-outline btn-rounded" onclick="load_sync_log_detail(' + row.ext_head_id + ', \'' + String(dtStart).replace(/'/g, "\\'") + '\');"><span class="btn-text">View</span></button></td>';
               tr += '</tr>';
            });
            $('#sync_session_tbody').html(tr || '<tr><td colspan="9">No sync sessions found</td></tr>');
            render_sync_pagination(page, totalPages);
         }

         function render_sync_detail_pagination(currentPage, totalPages){
            if(totalPages <= 1){
               $('#sync_detail_pagination').html('');
               return;
            }
            var html = '<ul class="pagination pagination-sm">';
            if(currentPage <= 1){
               html += '<li class="disabled"><a href="javascript:void(0)">Prev</a></li>';
            }else{
               html += '<li><a href="javascript:void(0)" onclick="render_sync_detail_page(' + (currentPage - 1) + ')">Prev</a></li>';
            }
            var startPage = Math.max(1, currentPage - 2);
            var endPage = Math.min(totalPages, startPage + 4);
            startPage = Math.max(1, endPage - 4);
            for(var p = startPage; p <= endPage; p++){
               if(p === currentPage){
                  html += '<li class="active"><a href="javascript:void(0)">' + p + '</a></li>';
               }else{
                  html += '<li><a href="javascript:void(0)" onclick="render_sync_detail_page(' + p + ')">' + p + '</a></li>';
               }
            }
            if(currentPage >= totalPages){
               html += '<li class="disabled"><a href="javascript:void(0)">Next</a></li>';
            }else{
               html += '<li><a href="javascript:void(0)" onclick="render_sync_detail_page(' + (currentPage + 1) + ')">Next</a></li>';
            }
            html += '</ul>';
            $('#sync_detail_pagination').html(html);
         }

         function render_sync_detail_page(page){
            var totalPages = Math.ceil(syncLogDetailData.length / SYNC_DETAIL_PAGE_SIZE) || 1;
            if(page < 1){ page = 1; }
            if(page > totalPages){ page = totalPages; }
            var start = (page - 1) * SYNC_DETAIL_PAGE_SIZE;
            var rows = syncLogDetailData.slice(start, start + SYNC_DETAIL_PAGE_SIZE);
            var tr = '';
            $.each(rows, function(i, row){
               tr += '<tr>';
               tr += '<td>' + (start + i + 1) + '. </td>';
               tr += '<td>' + row.u_id + '</td>';
               tr += '<td>' + sync_action_badge(row.action) + '</td>';
               tr += '<td>' + sync_format_json_cell(row.changed_fields) + '</td>';
               tr += '<td>' + sync_format_json_cell(row.old_data) + '</td>';
               tr += '<td>' + sync_format_json_cell(row.new_data) + '</td>';
               tr += '<td>' + (row.logged_at || '-') + '</td>';
               tr += '</tr>';
            });
            $('#sync_detail_tbody').html(tr || '<tr><td colspan="7">No changes recorded for this session</td></tr>');
            render_sync_detail_pagination(page, totalPages);
         }

         function load_sync_sessions(){
            $.ajax({
               type: 'POST',
               url: '../lib/q_func',
               dataType: 'json',
               data: { admin_get_sync_sessions: '' },
               beforeSend: function(){
                  $('#sync_session_tbody').html('<tr><td colspan="9">Loading...</td></tr>');
                  $('#sync_session_pagination').html('');
               },
               success: function(response){
                  if(!response || response.length === 0){
                     syncSessionsData = [];
                     $('#sync_session_tbody').html('<tr><td colspan="9">No sync sessions found</td></tr>');
                     $('#sync_session_pagination').html('');
                     return;
                  }
                  syncSessionsData = response;
                  render_sync_sessions_page(1);
               },
               error: function(xhr){
                  syncSessionsData = [];
                  $('#sync_session_tbody').html('<tr><td colspan="9">Failed to load sync sessions</td></tr>');
                  $('#sync_session_pagination').html('');
               }
            });
         }

         function load_sync_log_detail(ext_head_id, sessionDate){
            $.ajax({
               type: 'POST',
               url: '../lib/q_func',
               dataType: 'json',
               data: { admin_get_sync_log_detail: '', ext_head_id: ext_head_id },
               beforeSend: function(){
                  syncLogDetailData = [];
                  $('#sync_detail_tbody').html('<tr><td colspan="7">Loading...</td></tr>');
                  $('#sync_detail_pagination').html('');
                  $('#sync_detail_header').text('Changes in Session #' + ext_head_id + (sessionDate ? ' — ' + sessionDate : ''));
                  $('#sync_session_panel').hide();
                  $('#sync_detail_panel').show();
               },
               success: function(response){
                  if(!response || response.length === 0){
                     syncLogDetailData = [];
                     $('#sync_detail_tbody').html('<tr><td colspan="7">No changes recorded for this session</td></tr>');
                     $('#sync_detail_pagination').html('');
                     return;
                  }
                  syncLogDetailData = response;
                  render_sync_detail_page(1);
               },
               error: function(xhr){
                  syncLogDetailData = [];
                  $('#sync_detail_tbody').html('<tr><td colspan="7">Failed to load session details</td></tr>');
                  $('#sync_detail_pagination').html('');
               }
            });
         }
         
         
         
         function countdownTimeStart(){
         
         var countDownDate = new Date("<?php echo date('M d, Y  H:i:s', strtotime('+30 minute', strtotime(LOCAL_COOKIES_HANDLER()->sso_dt)));?>").getTime();
         // Update the count down every 1 second
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
         

         // Delegated so it works for dynamic inputs too
$(document).on('click', '.dropify-wrapper .dropify-clear', function (e) {
  e.preventDefault();             // stop Dropify's default clear
  e.stopPropagation();

  const $wrapper = $(this).closest('.dropify-wrapper');
  const $input   = $wrapper.find('input[type="file"].dropify');
  const api      = $input.data('dropify');   // Dropify instance

  // Your custom code here (log, confirm, etc.)

  // Example async confirm (SweetAlert2):
  // Swal.fire({title:'Remove?', showCancelButton:true}).then(res => {
  //   if (res.isConfirmed) api.clearElement();  // this will also trigger before/afterClear
  // });

  // Simple confirm:
  if (confirm('Remove the selected file?')) {
    api.clearElement();            // proceed; will still fire the Dropify events
    $('#edit_existing_app_icon').val('');
  }
});
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
	  
	  function modal_user_profile_resetPwd(){
		  var user_id = 	$('#modal_user_profile_user_id').val();
		  swal({   
           		title: "Force Reset Password",   
           		text: "Are you sure you want to Force Reset Password?",   
           		type: "warning",   
           		confirmButtonColor: "#DD6B55",   
           		confirmButtonText: "Yes!",   
           		showCancelButton: true,   
           		closeOnConfirm: false 
           	}, function(){   
         
           		$.ajax({
           			type: 'POST',
           			url: '../lib/q_func',
           			dataType: "json",
           			data: {admin_reset_password_user:'',user_id:user_id},     
           			beforeSend: function(){
           			},
           			success: function (response) {
           				if (response == 1){
           					
					swal("Password Reset", "Sessions revoked. User must use Forgot Password to set a new password.", "success");
           				}else{
           					swal("Password Reset", "Password had already been reset.", "error"); 
           				}
         
           			},
           			error: function (xhr, error, thrown) {
           			}
           		});
           	});
	  }
	  
	   const releaseNotes = [
    {
      version: "1.0.3",
      date: "2026-06-17",
      changes: [
        "Carian pengguna di panel admin kini <b>lebih pantas</b> — hasil carian nama, No. Staf/Pelajar, atau No. K/P dipaparkan dengan lebih cepat semasa taip."
      ]
    },
    {
      version: "1.0.2",
      date: "2026-06-16",
      changes: [
        "Menu baharu <b>Sync Log</b> — semak sejarah sync, statistik setiap sesi (baharu, dikemaskini, dinyahaktifkan, diaktifkan semula), dan butiran perubahan mengikut sesi.",
        "Penjadual sync automatik berjalan setiap hari pada <b>12:00 tengah malam (00:00)</b>.",
        "Pembaikan bug kritikal pada penjadual sync — rekod sesi lebih tepat, elak sync berulang akaun yang sama, dan kestabilan proses harian.",
        "Penambahbaikan <b>Sync Pengguna</b>: sistem hanya kemaskini akaun yang benar-benar berubah; sync lebih pantas dan tepat.",
        "Akaun staf/pelajar yang muncul semula dalam sumber data akan <b>diaktifkan semula</b> secara automatik.",
        "Data sync sebelum ini telah <b>dikosongkan</b> (truncate). <b>17 Jun 2026</b> menjadi tarikh mula sesi sync <b>Generasi 2</b>. Sync Generasi 1 mengandungi banyak bug dan tidak lagi dirujuk."
      ]
    },
    {
      version: "1.0.1",
      date: "2025-11-12", // change if you want
      changes: [
        // Language / copy updates
        "Bahasa & istilah diseragamkan: Application → <b>Sistem Aplikasi</b>, Directory → <b>Direktori Staf</b>, FAQ → <b>Soalan Lazim (FAQ)</b>, Logout → <b>Log Keluar</b>, Close → <b>Tutup</b>, Change → <b>Tukar</b>.",
        "‘List of accessible apps’ → <b>Senarai Sistem Aplikasi</b>.",
        
        // Password screen copy & rules
        "<b>Kata Laluan Semasa</b> (No. KP/No. Pasport), <b>Kata Laluan Baharu</b>, dan <b>Sahkan Kata Laluan Baharu</b>.",
        "Keperluan kata laluan: ≥8 aksara, ≥1 huruf kecil (a–z), ≥1 huruf besar (A–Z), ≥1 nombor (0–9), ≥1 aksara khas (cth: ! @ # $ %).",

        // Login / security
        "Counter multiple login yang tersangkut: <b>dimatikan</b> & mesej: “Akaun dikunci. Sila log semula selepas 2 minit.”",
        "Rekod multiple attempt login dihantar ke modul <b>Audit</b>.",

        // Username input format (you asked earlier)
        "Had input login: <b>Max 10 karakter</b>, hanya <b>A–Z, 0–9</b> dan <b>“-”</b>.",

        // Tabs / navigation
        "Kategori tab “Non-SSO” ditukar nama kepada <b>Pautan Terus</b>.",
        "Kategori <b>Pautan Terus</b> dialihkan ke <b>akhir</b> senarai tab.",

        // UI actions
        "Buang butang <b>Logout</b> pada menu <b>Tukar Password</b> (Dashboard).",
        "Alert bertukar kepada <b>SweetAlert</b> (pusat skrin).",

        // Login guidance
        "Templat placeholder Login: <b>No.Staf (XXXX-XX) / No.Pelajar</b>. Password kali pertama: <b>No. K/P</b>."
      ]
    }
  ];

  const tbody = document.getElementById("release_notes_tbody");
  tbody.innerHTML = releaseNotes.map(r => `
    <tr>
      <td><b>v${r.version}</b></td>
      <td>
        <ul class="mb-0" style="list-style:none;padding-left:0;">
          ${r.changes.map((item, i) => `<li>${i + 1}. ${item}</li>`).join("")}
        </ul>
      </td>
      <td>${r.date}</td>
    </tr>
  `).join("");
      </script>
      <style>
         #the-basics .tt-dropdown-menu {
         max-height: 150px;
         overflow-y: auto;
         }
         .modal { overflow-y: auto !important; }



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
}/* Place items */
.follo-data .name{ grid-row:2; }
.follo-data .time.txt-grey{ grid-row:3; }      /* description */
.follo-data .time.txt-primary{ 
  grid-row:4;                                   /* URL line below desc */
  display:block !important;
  margin-top:4px;
  overflow-wrap:anywhere;                        /* long URLs won't overflow */
}
.follo-data .time{ grid-row:3; white-space:normal; overflow:visible; text-overflow:clip; }
/* If your markup has a <br> between them, hide it */
.follo-data .user-data:nth-child(2) > br{ display:none; }

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
      </style>
   </body>
</html>
