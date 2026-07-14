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
						   <button type="button" class="btn  btn-danger btn-outline btn-rounded btn-xs pull-right" onclick="modal_user_profile_resetPwd()" id="btn_user_profile_reset_password"><i class="fa  fa-refresh"></i> Reset Password</button>
                              <button type="button" class="btn btn-primary btn-outline btn-rounded btn-xs pull-right" onclick="modal_user_profile_resync()" id="btn_user_profile_resync"><i class="fa fa-refresh"></i> Resync User Info</button>
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
                        <button type="button" class="btn btn-danger waves-effect user_info_btn" onclick="user_info_deactivate_user();" id="btn_user_profile_deactivate">Remove User</button>
                        <button type="button" class="btn btn-primary waves-effect user_info_btn_reactivate" onclick="user_info_reactivate_user();" id="btn_user_profile_reactivate">Reactivate User</button>
                        <button type="button" class="btn btn-primary waves-effect user_info_btn" onclick="modal_user_profile_save();" id="btn_user_profile_save"><i class="fa fa-save"></i> Save Profile</button>
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
                                                   <div class="web-app-panel">
                                                      <div class="web-app-header">
                                                         <div>
                                                            <span class="web-app-eyebrow">Application directory</span>
                                                            <h4 class="web-app-title">Web Apps</h4>
                                                            <p class="web-app-intro">Urus aplikasi berdaftar, kategori dan konfigurasi sambungan SSO.</p>
                                                         </div>
                                                         <div class="web-app-count" aria-live="polite">
                                                            <span>Applications</span>
                                                            <strong id="web_app_count">&mdash;</strong>
                                                         </div>
                                                      </div>

                                                      <div class="web-app-toolbar">
                                                         <div>
                                                            <h5>Application categories</h5>
                                                            <p>Pilih kategori untuk melihat aplikasi yang berkaitan.</p>
                                                         </div>
                                                         <div class="web-app-actions">
                                                            <button class="web-app-action is-danger" type="button" onclick="open_remove_webapp_category();">
                                                               <i class="fa fa-folder-open-o" aria-hidden="true"></i>
                                                               <span>Remove category</span>
                                                            </button>
                                                            <button class="web-app-action" type="button" onclick="open_add_new_webapp_category();">
                                                               <i class="fa fa-folder-o" aria-hidden="true"></i>
                                                               <span>Add category</span>
                                                            </button>
                                                            <button class="web-app-action is-primary" type="button" onclick="open_add_new_webapp();">
                                                               <i class="fa fa-plus" aria-hidden="true"></i>
                                                               <span>Add app</span>
                                                            </button>
                                                            <button class="web-app-refresh" type="button" onclick="get_service_provider_list();" title="Refresh web apps" aria-label="Refresh web apps">
                                                               <i class="fa fa-refresh" aria-hidden="true"></i>
                                                            </button>
                                                         </div>
                                                      </div>

                                                      <div class="web-app-category-nav">
                                                         <ul role="tablist" class="nav" id="WebAppsTabsHeader"></ul>
                                                      </div>

                                                      <div id="tab_available_apps_list_loading" class="web-app-state is-loading">
                                                         <span><i class="fa fa-circle-o-notch fa-spin" aria-hidden="true"></i></span>
                                                         <strong>Loading web applications</strong>
                                                         <small>Please wait while application data is retrieved.</small>
                                                      </div>
                                                      <div id="tab_available_apps_list" class="web-app-directory">
                                                         <div class="tab-content" id="WebAppsTabsContent"></div>
                                                         <div id="follo_data_list"></div>
                                                      </div>
                                                      </div>
                                                   </div>
                                                </div>
                                             </div>
                                          <div  id="tab_user" class="tab-pane fade in" role="tabpanel">
                                             <div class="row">
                                                <div class="col-lg-12">
                                                   <div class="user-account-panel">
                                                      <div class="user-account-header">
                                                         <div>
                                                            <span class="user-account-eyebrow">Identity management</span>
                                                            <h4 class="user-account-title">User Accounts</h4>
                                                            <p class="user-account-intro">Cari akaun, semak kategori pengguna dan urus akses sistem dari satu paparan.</p>
                                                         </div>
                                                         <div class="user-category-count" aria-live="polite">
                                                            <span>Categories</span>
                                                            <strong id="user_category_count">&mdash;</strong>
                                                         </div>
                                                      </div>

                                                      <div class="user-search-card">
                                                         <div class="user-search-copy">
                                                            <span class="user-search-icon" aria-hidden="true"><i class="fa fa-search"></i></span>
                                                            <div>
                                                               <h5>Find user account</h5>
                                                               <p>Search by name, staff or student ID, or identity number.</p>
                                                            </div>
                                                         </div>
                                                         <div class="user-search-controls">
                                                            <div id="the-basics" class="user-search-input-wrap">
                                                               <i class="fa fa-user-o" aria-hidden="true"></i>
                                                               <input class="typeahead form-control" id="search_user_input" type="text" placeholder="Name, Staff/Student ID, or NIRC" aria-label="Search user account">
                                                            </div>
                                                            <button type="button" class="user-search-clear" onclick="clear_search();" title="Clear search" aria-label="Clear search">
                                                               <i class="fa fa-times" aria-hidden="true"></i>
                                                            </button>
                                                            <img class="Typeahead-spinner" src="../img/Spinner-1s-51px.gif" alt="Searching" hidden>
                                                         </div>

                                                         <div id="search_user_account_main_progress_bar" class="user-search-state is-loading">
                                                            <span><i class="fa fa-circle-o-notch fa-spin" aria-hidden="true"></i></span>
                                                            <strong>Searching user account</strong>
                                                            <small>Please wait while the directory is searched.</small>
                                                         </div>
                                                         <div id="search_user_account_main_search_result" class="user-search-result">
                                                            <div class="user-search-result-heading">
                                                               <span>Search result</span>
                                                            </div>
                                                            <div id="search_user_account_main_result_data"></div>
                                                         </div>
                                                      </div>

                                                      <div class="user-category-card">
                                                         <div class="user-category-header">
                                                            <div>
                                                               <h5>User categories</h5>
                                                               <p>Semak jumlah akaun dan aplikasi yang diberikan kepada setiap kategori.</p>
                                                            </div>
                                                            <div class="user-category-actions">
                                                               <button class="user-category-action" type="button" onclick="add_new_category();">
                                                                  <i class="fa fa-folder-o" aria-hidden="true"></i>
                                                                  <span>New category</span>
                                                               </button>
                                                               <button class="user-category-action is-primary" type="button" onclick="add_new_user_option();">
                                                                  <i class="fa fa-user-plus" aria-hidden="true"></i>
                                                                  <span>Add user</span>
                                                               </button>
                                                               <button class="user-category-refresh" type="button" onclick="admin_get_all_user_category(0);" title="Refresh user categories" aria-label="Refresh user categories">
                                                                  <i class="fa fa-refresh" aria-hidden="true"></i>
                                                               </button>
                                                            </div>
                                                         </div>
                                                         <div id="user_account_type_list" class="user-category-list">
                                                            <div class="user-category-state is-loading">
                                                               <span><i class="fa fa-circle-o-notch fa-spin" aria-hidden="true"></i></span>
                                                               <strong>Loading user categories</strong>
                                                               <small>Please wait while category data is retrieved.</small>
                                                            </div>
                                                         </div>
                                                      </div>
                                                   </div>
                                                </div>
                                             </div>
                                          </div>
                                          <div  id="tab_active_sessions" class="tab-pane fade in" role="tabpanel">
                                             <div class="admin-section-inner">
                                                <div class="row">
                                                   <div class="col-lg-12">
                                                      <div class="active-session-panel">
                                                         <div class="active-session-header">
                                                            <div>
                                                               <span class="active-session-eyebrow">Access &amp; security</span>
                                                               <h4 class="active-session-title">Active Sessions</h4>
                                                               <p class="active-session-intro">Pantau sesi pengguna yang masih aktif, masa token dan peranti yang digunakan.</p>
                                                            </div>
                                                            <div class="active-session-actions">
                                                               <div class="active-session-count" aria-live="polite">
                                                                  <span>Active</span>
                                                                  <strong id="active_session_count">&mdash;</strong>
                                                               </div>
                                                               <button type="button" class="active-session-refresh" onclick="get_all_user_activ_session();" title="Refresh active sessions" aria-label="Refresh active sessions">
                                                                  <i class="fa fa-refresh" aria-hidden="true"></i>
                                                               </button>
                                                            </div>
                                                         </div>

                                                         <div class="active-session-card" id="app_security_session_list">
                                                            <div class="active-session-card-heading">
                                                               <div>
                                                                  <h5>Session list</h5>
                                                                  <p>Senarai ini hanya untuk pemantauan dan tidak menamatkan sesi pengguna.</p>
                                                               </div>
                                                            </div>
                                                            <div class="active-session-table-wrap">
                                                               <table class="table active-session-table mb-0">
                                                                  <colgroup>
                                                                     <col class="active-col-time">
                                                                     <col class="active-col-user">
                                                                     <col class="active-col-device">
                                                                     <col class="active-col-status">
                                                                  </colgroup>
                                                                  <thead>
                                                                     <tr>
                                                                        <th scope="col">Token Date / Time</th>
                                                                        <th scope="col">User</th>
                                                                        <th scope="col">Device</th>
                                                                        <th scope="col">Status</th>
                                                                     </tr>
                                                                  </thead>
                                                                  <tbody id="security_tab_session">
                                                                     <tr class="active-session-state-row is-loading">
                                                                        <td colspan="4">
                                                                           <span class="active-session-state-icon"><i class="fa fa-circle-o-notch fa-spin" aria-hidden="true"></i></span>
                                                                           <strong>Loading active sessions</strong>
                                                                           <small>Please wait while session data is retrieved.</small>
                                                                        </td>
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
                                          <div  id="tab_auditlog" class="tab-pane fade in" role="tabpanel">
                                             <div class="admin-section-inner">
                                                <div class="row">
                                                   <div class="col-lg-12">
                                                      <div class="audit-log-panel">
                                                         <div class="audit-log-header">
                                                            <div>
                                                               <span class="audit-log-eyebrow">Security &amp; activity</span>
                                                               <h4 class="audit-log-title">Audit Log</h4>
                                                               <p class="audit-log-intro">Semak rekod aktiviti sistem mengikut julat tarikh, jenis log dan alamat IP.</p>
                                                            </div>
                                                            <div class="audit-result-count" aria-live="polite">
                                                               <span>Results</span>
                                                               <strong id="audit_result_count">&mdash;</strong>
                                                            </div>
                                                         </div>

                                                         <div class="audit-filter-card">
                                                            <div class="audit-filter-copy">
                                                               <span class="audit-filter-icon" aria-hidden="true"><i class="fa fa-calendar"></i></span>
                                                               <div>
                                                                  <label for="audit_search_daterange">Date range</label>
                                                                  <p>Pilih julat tarikh.</p>
                                                               </div>
                                                            </div>
                                                            <form class="audit-filter-form" onsubmit="search_audit_date_range(); return false;">
                                                               <div class="audit-date-input-wrap">
                                                                  <i class="fa fa-calendar-o" aria-hidden="true"></i>
                                                                  <input class="form-control input-daterange-datepicker" type="text" id="audit_search_daterange" name="audit_search_daterange" value="01/01/2016 - 01/31/2016" aria-label="Audit log date range"/>
                                                               </div>
                                                               <button type="submit" class="audit-search-button" aria-label="Search audit logs" title="Search audit logs">
                                                                  <i class="fa fa-search" aria-hidden="true"></i>
                                                               </button>
                                                            </form>
                                                         </div>

                                                         <div class="audit-results-card">
                                                            <div class="audit-results-heading">
                                                               <div>
                                                                  <h5>Log results</h5>
                                                                  <p>Semua data disusun dari bahagian kiri dan atas untuk bacaan yang lebih jelas.</p>
                                                               </div>
                                                            </div>
                                                            <div class="audit-table-wrap">
                                                               <table class="table audit-log-table mb-0">
                                                                  <colgroup>
                                                                     <col class="audit-col-datetime">
                                                                     <col class="audit-col-type">
                                                                     <col class="audit-col-details">
                                                                     <col class="audit-col-ip">
                                                                  </colgroup>
                                                                  <thead>
                                                                     <tr>
                                                                        <th scope="col">Date / Time</th>
                                                                        <th scope="col">Log Type</th>
                                                                        <th scope="col">Activity Details</th>
                                                                        <th scope="col">IP Address</th>
                                                                     </tr>
                                                                  </thead>
                                                                  <tbody id="audit_search_result_tbody">
                                                                     <tr class="audit-state-row">
                                                                        <td colspan="4">
                                                                           <span class="audit-state-icon"><i class="fa fa-search" aria-hidden="true"></i></span>
                                                                           <strong>Ready to search</strong>
                                                                           <small>Select a date range to display audit records.</small>
                                                                        </td>
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
                                          <div id="tab_synclog" class="tab-pane fade in" role="tabpanel">
                                             <div class="tab-pane fade active in" role="tabpanel">
                                                <div class="row">
                                                   <div class="col-lg-12" id="sync_session_panel">
                                                      <div class="sync-log-panel">
                                                         <div class="sync-log-header">
                                                            <div>
                                                               <span class="sync-log-eyebrow">External data activity</span>
                                                               <h4 class="sync-log-title">Sync Sessions</h4>
                                                               <p class="sync-log-intro">Semak sejarah setiap sesi, jumlah perubahan dan status pemprosesan external sync.</p>
                                                            </div>
                                                            <button type="button" class="sync-log-refresh" onclick="load_sync_sessions();">
                                                               <i class="fa fa-refresh" aria-hidden="true"></i>
                                                               <span>Refresh data</span>
                                                            </button>
                                                         </div>
                                                         <div class="sync-log-summary" aria-label="Sync session summary">
                                                            <div class="sync-summary-item">
                                                               <span>Sessions shown</span>
                                                               <strong id="sync_summary_total">—</strong>
                                                            </div>
                                                            <div class="sync-summary-item">
                                                               <span>Completed</span>
                                                               <strong id="sync_summary_complete">—</strong>
                                                            </div>
                                                            <div class="sync-summary-item">
                                                               <span>Recorded changes</span>
                                                               <strong id="sync_summary_changes">—</strong>
                                                            </div>
                                                         </div>
                                                         <div class="sync-log-table-card">
                                                            <div class="sync-table-wrap">
                                                                     <table class="table sync-log-table mb-0">
                                                                        <thead>
                                                                           <tr>
                                                                              <th class="sync-col-session">Session</th>
                                                                              <th>Date/Time</th>
                                                                              <th>Triggered By</th>
                                                                              <th>Changes</th>
                                                                              <th>Status</th>
                                                                              <th>Action</th>
                                                                           </tr>
                                                                        </thead>
                                                                        <tbody id="sync_session_tbody">
                                                                           <tr class="sync-empty-row">
                                                                              <td colspan="6"><i class="fa fa-circle-o-notch fa-spin" aria-hidden="true"></i> Loading sessions...</td>
                                                                           </tr>
                                                                        </tbody>
                                                                     </table>
                                                            </div>
                                                            <div id="sync_session_pagination" class="sync-log-pagination text-center"></div>
                                                         </div>
                                                      </div>
                                                   </div>
                                                   <div class="col-lg-12" id="sync_detail_panel" style="display:none;">
                                                      <div class="sync-log-panel sync-detail-view">
                                                         <div class="sync-log-header sync-detail-header">
                                                            <div>
                                                               <button type="button" class="sync-log-back" onclick="show_sync_session_list();">
                                                                  <i class="fa fa-arrow-left" aria-hidden="true"></i>
                                                                  <span>Back to sessions</span>
                                                               </button>
                                                               <span class="sync-log-eyebrow">Session audit trail</span>
                                                               <h4 class="sync-log-title" id="sync_detail_header">Changes in Session</h4>
                                                               <p class="sync-log-intro">Butiran perubahan yang direkodkan untuk sesi sync terpilih.</p>
                                                            </div>
                                                         </div>
                                                         <div class="sync-log-table-card">
                                                            <div class="sync-table-wrap">
                                                                     <table class="table sync-log-table sync-detail-table mb-0">
                                                                        <thead>
                                                                           <tr>
                                                                              <th>#</th>
                                                                              <th>User ID</th>
                                                                              <th>Action</th>
                                                                              <th>Change Details</th>
                                                                              <th>Time</th>
                                                                           </tr>
                                                                        </thead>
                                                                        <tbody id="sync_detail_tbody">
                                                                           <tr class="sync-empty-row">
                                                                              <td colspan="5">No changes recorded for this session.</td>
                                                                           </tr>
                                                                        </tbody>
                                                                     </table>
                                                            </div>
                                                            <div id="sync_detail_pagination" class="sync-log-pagination text-center"></div>
                                                         </div>
                                                      </div>
                                                   </div>
                                                </div>
                                             </div>
                                          </div>
										  <div  id="tab_versioning" class="tab-pane fade in" role="tabpanel">
                                             <div class="admin-section-inner">
                                                <div class="row">
                                                   <div class="col-lg-12">
                                                      <div class="version-release-panel">
                                                         <div class="version-release-header">
                                                            <div>
                                                               <span class="version-release-eyebrow">Release history</span>
                                                               <h4 class="version-release-title">Version Releases</h4>
                                                               <p class="version-release-intro">Ringkasan perubahan, pembaikan dan penambahbaikan yang telah dikeluarkan untuk OneID.</p>
                                                            </div>
                                                            <div id="current_release_badge" class="version-current-release" aria-label="Current release"></div>
                                                         </div>
                                                         <div id="release_notes_list" class="version-release-list" aria-live="polite">
                                                            <!-- Release cards are rendered from the static release notes below. -->
                                                         </div>
                                                      </div>
                                                   </div>
                                                </div>
                                             </div>
                                          </div>
                                          <div  id="tab_settings" class="tab-pane fade in" role="tabpanel">
                                             <div class="admin-section-inner">
                                                <div class="row">
                                                   <div class="col-lg-12">
                                                      <div class="sso-config-panel">
                                                         <div class="sso-config-header">
                                                            <div>
                                                               <span class="sso-config-eyebrow">Identity &amp; session</span>
                                                               <h4 class="sso-config-title">SSO Configuration</h4>
                                                               <p class="sso-config-intro">Urus tempoh sesi, akses berbilang peranti dan penghantaran OTP untuk pengguna OneID.</p>
                                                            </div>
                                                            <button class="sso-config-save" type="button" onclick="update_configuration();">
                                                               <i class="fa fa-check" aria-hidden="true"></i>
                                                               <span>Save changes</span>
                                                            </button>
                                                         </div>
                                                         <div class="sso-config-body">
                                                            <div class="sso-config-row">
                                                               <div class="sso-config-copy">
                                                                  <span class="sso-config-index">01</span>
                                                                  <div>
                                                                     <label for="sso_settings_token_session_timeout">Session timeout</label>
                                                                     <p>Tempoh sah sesi sebelum pengguna perlu membuat pengesahan semula.</p>
                                                                  </div>
                                                               </div>
                                                               <div class="sso-config-control sso-config-select-wrap">
                                                                  <select class="form-control" id="sso_settings_token_session_timeout">
                                                                     <option value="0.5">30 minit</option>
                                                                     <option value="1">1 jam</option>
                                                                     <option value="2">2 jam</option>
                                                                     <option value="12">12 jam</option>
                                                                     <option value="24">1 hari</option>
                                                                     <option value="48">2 hari</option>
                                                                     <option value="72">3 hari</option>
                                                                     <option value="168">1 minggu</option>
                                                                  </select>
                                                               </div>
                                                            </div>

                                                            <div class="sso-config-row">
                                                               <div class="sso-config-copy">
                                                                  <span class="sso-config-index">02</span>
                                                                  <div>
                                                                     <label for="sso_settings_multi_session">Multiple sessions</label>
                                                                     <p>Benarkan akaun yang sama mempunyai lebih daripada satu sesi aktif.</p>
                                                                  </div>
                                                               </div>
                                                               <div class="sso-config-control">
                                                                  <div class="sso-config-switch" aria-label="Allow multiple sessions">
                                                                     <span>Disabled</span>
                                                                     <input type="checkbox" class="js-switch js-switch-1" id="sso_settings_multi_session" data-color="#11a8df" data-size="small"/>
                                                                     <span>Enabled</span>
                                                                  </div>
                                                               </div>
                                                            </div>

                                                            <div class="sso-config-row">
                                                               <div class="sso-config-copy">
                                                                  <span class="sso-config-index">03</span>
                                                                  <div>
                                                                     <label for="sso_settings_otp_email">OTP email delivery</label>
                                                                     <p>Hantar kod OTP ke alamat e-mel pengguna bagi proses pengesahan berkaitan.</p>
                                                                  </div>
                                                               </div>
                                                               <div class="sso-config-control">
                                                                  <div class="sso-config-switch" aria-label="Send OTP email to user">
                                                                     <span>Disabled</span>
                                                                     <input type="checkbox" class="js-switch js-switch-1" id="sso_settings_otp_email" data-color="#11a8df" data-size="small"/>
                                                                     <span>Enabled</span>
                                                                  </div>
                                                               </div>
                                                            </div>

                                                            <div class="sso-config-note">
                                                               <i class="fa fa-info-circle" aria-hidden="true"></i>
                                                               <p>Perubahan konfigurasi memberi kesan kepada flow authentication dan sesi pengguna. Semak pilihan sebelum menyimpan.</p>
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
						$('#web_app_count').text('\u2014');
           			$('#tab_available_apps_list_loading').show();
           			$('#tab_available_apps_list').hide();
                        $('#WebAppsTabsHeader').html('');
                        $('#WebAppsTabsContent').html('');
						$('#follo_data_list').html('');
           		},
           		success: function (response) {
           			$('#tab_available_apps_list_loading').hide();
           			$('#tab_available_apps_list').show();

						var appText = function(value){
							return $('<div>').text(value == null ? '' : value).html();
						};
						var listCount = 0;
						var tabs = '';
						var panes = '';

						if (!Array.isArray(response) || response.length === 0) {
							$('#web_app_count').text('0');
							$('#follo_data_list').html(
								'<div class="web-app-state">' +
								'<span><i class="fa fa-th-large" aria-hidden="true"></i></span>' +
								'<strong>No applications available</strong>' +
								'<small>Add an application to begin building the directory.</small>' +
								'</div>'
							);
							return;
						}

						$.each(response, function(i, group) {
							var tabName = appText(group['tabname']);
							var groupName = appText(group['sp_group_name']);
							var applications = Array.isArray(group['data']) ? group['data'] : [];
							var activeClass = i === 0 ? 'active' : '';
							var paneClass = i === 0 ? 'tab-pane fade active in' : 'tab-pane fade';

							tabs += '<li class="'+activeClass+'" role="presentation">';
							tabs += '<a aria-expanded="'+(i === 0 ? 'true' : 'false')+'" data-toggle="tab" role="tab" href="#'+tabName+'"><span>'+groupName+'</span><strong>'+applications.length+'</strong></a>';
							tabs += '</li>';

							panes += '<div id="'+tabName+'" class="'+paneClass+'" role="tabpanel">';
							panes += '<div class="web-app-card-list">';

							if (applications.length === 0) {
								panes += '<div class="web-app-category-empty"><i class="fa fa-inbox" aria-hidden="true"></i><span>No applications in this category.</span></div>';
							}

							$.each(applications, function(k, application) {
								listCount++;
								var appId = appText(application['sp_id']);
								var appName = appText(application['sp_name']);
								var appDescription = appText(application['sp_description']);
								var appDomain = appText(application['sp_domain']);
								var appImage = appText(application['sp_image']);
								var imageSource = appImage === '' ? '../img/thumb-1.jpg' : '../public_img/' + appImage;
								var ssoUnsupported = application['sp_sso_support'] !== "0";

								panes += '<article class="web-app-card">';
								panes += '<div class="web-app-card-index">'+(k + 1)+'</div>';
								panes += '<div class="web-app-card-image"><img src="'+imageSource+'" alt="" loading="lazy"></div>';
								panes += '<div class="web-app-card-content">';
								panes += '<div class="web-app-card-name"><strong title="'+appName+'">'+appName+'</strong>'+(ssoUnsupported ? '<span class="web-app-sso-badge">Direct link</span>' : '<span class="web-app-sso-badge is-enabled">SSO</span>')+'</div>';
								panes += '<p title="'+appDescription+'">'+appDescription+'</p>';
								panes += '<span class="web-app-domain" title="'+appDomain+'"><i class="fa fa-link" aria-hidden="true"></i>'+appDomain+'</span>';
								panes += '</div>';
								panes += '<button type="button" class="web-app-view" data-app-id="'+appId+'" onclick="open_edit_webapp(this.dataset.appId);" title="View application" aria-label="View application"><i class="fa fa-eye" aria-hidden="true"></i></button>';
								panes += '</article>';
							});

							panes += '</div></div>';
						});

						$('#web_app_count').text(listCount);
						$('#WebAppsTabsHeader').html(tabs);
						$('#WebAppsTabsContent').html(panes);
						if (href && $('#WebAppsTabsHeader a[href="'+href+'"]').length) {
							$('#WebAppsTabsHeader a[href="'+href+'"]').tab('show');
						}

					},
					error: function (xhr, error, thrown) {
						$('#web_app_count').text('\u2014');
						$('#tab_available_apps_list_loading').hide();
						$('#tab_available_apps_list').show();
						$('#follo_data_list').html(
							'<div class="web-app-state is-error">' +
							'<span><i class="fa fa-exclamation-triangle" aria-hidden="true"></i></span>' +
							'<strong>Unable to load web applications</strong>' +
							'<small>Please retry or check the application log.</small>' +
							'</div>'
						);
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
						$('#user_category_count').text('\u2014');
						$('#user_account_type_list').html(
							'<div class="user-category-state is-loading">' +
							'<span><i class="fa fa-circle-o-notch fa-spin" aria-hidden="true"></i></span>' +
							'<strong>Loading user categories</strong>' +
							'<small>Please wait while category data is retrieved.</small>' +
							'</div>'
						);
                     },
                     success: function (response) {
						var tr = '';
						var select_opt = '<option value="">Choose Category</option>';
						var categoryText = function(value){
							return $('<div>').text(value == null ? '' : value).html();
						};

						if (!Array.isArray(response) || response.length === 0) {
							$('#user_category_count').text('0');
							$('#user_account_type_list').html(
								'<div class="user-category-state">' +
								'<span><i class="fa fa-folder-open-o" aria-hidden="true"></i></span>' +
								'<strong>No user categories</strong>' +
								'<small>Create a category to begin organising user access.</small>' +
								'</div>'
							);
							return;
						}

						$.each(response, function(i, value) {
							var categoryId = categoryText(value['uc_id']);
							var categoryName = categoryText(value['uc_name']);
							var userTotal = categoryText(value['total']);
							var siteTotal = categoryText(value['site_count']);
							tr += '<div class="user-category-row">';
							tr += '<div class="user-category-identity"><span class="user-category-avatar"><i class="fa fa-users" aria-hidden="true"></i></span><div><strong title="'+categoryName+'">'+categoryName+'</strong><small>Access group</small></div></div>';
							tr += '<div class="user-category-metrics"><span><strong>'+userTotal+'</strong><small>Users</small></span><span><strong>'+siteTotal+'</strong><small>Apps</small></span></div>';
							tr += '<div class="user-category-row-actions">';
							tr += '<button type="button" class="user-category-row-button" data-category-id="'+categoryId+'" data-category-name="'+categoryName+'" onclick="view_category_user_list(this.dataset.categoryId, this.dataset.categoryName);" title="View users"><i class="fa fa-users" aria-hidden="true"></i><span>Users</span></button>';
							tr += '<button type="button" class="user-category-row-button" data-category-id="'+categoryId+'" data-category-name="'+categoryName+'" onclick="open_category_listing(this.dataset.categoryId, this.dataset.categoryName);" title="View application access"><i class="fa fa-th-large" aria-hidden="true"></i><span>Apps</span></button>';
							tr += '</div>';
							tr += '</div>';
							if(value['uc_id'] != "0"){
								select_opt += '<option value="'+categoryId+'">'+categoryName+'</option>';
							}
						});

						$('#user_category_count').text(response.length);
						$('#user_account_type_list').html(tr);
						$('#add_new_manual_user_category').html(select_opt);
						if(type == 0){
							$('#modal_user_profile_category').html(select_opt);
							$('#add_new_single_user_category').html(select_opt);
						}

                       },
                       error: function (xhr, error, thrown) {
						$('#user_category_count').text('\u2014');
						$('#user_account_type_list').html(
							'<div class="user-category-state is-error">' +
							'<span><i class="fa fa-exclamation-triangle" aria-hidden="true"></i></span>' +
							'<strong>Unable to load user categories</strong>' +
							'<small>Please retry or check the application log.</small>' +
							'</div>'
						);
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
						var searchText = function(value){
							return $('<div>').text(value == null ? '' : value).html();
						};
           			$('#search_user_account_main_progress_bar').hide();
           			$('#search_user_account_main_search_result').show();
           			if(response.length==0){            
							tr += '<div class="user-result-empty"><i class="fa fa-user-times" aria-hidden="true"></i><div><strong>No matching account</strong><small>Try another name or identification number.</small></div></div>';
           				$('#search_user_account_main_result_data').html(tr);
           				return;
           			}
						var resultName = searchText(response['data1']);
						var resultCategory = searchText(response['uc_name']);
						var resultUserId = searchText(response['u_id']);
						var resultSource = searchText(response['source']);
						tr += '<div class="user-result-card"><div class="user-result-profile"><span class="user-result-avatar"><i class="fa fa-user" aria-hidden="true"></i></span><div><strong title="'+resultName+'">'+resultName+'</strong><small>Category: '+resultCategory+'</small>';
           			switch(response['source']){
         				case "1": //reg
         				if(response['avail_status']=="0"){
							tr += '<span class="user-result-status is-removed">Removed</span>';
         
         				}else{
							tr += '<span class="user-result-status is-registered">Registered</span>';
         				}
         				break;
         				case "2": //unreg
						tr += '<span class="user-result-status is-unregistered">Unregistered</span>';
         				break;
         			}
						tr += '</div></div>';
						tr += '<button type="button" class="user-result-view" data-user-id="'+resultUserId+'" data-user-source="'+resultSource+'" onclick="view_user_profile(this.dataset.userId, this.dataset.userSource);" title="View user profile" aria-label="View user profile"><i class="fa fa-eye" aria-hidden="true"></i></button>';
						tr += '</div>';
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
           var m3ProfileSource = "legacy";

           function m3EscapeHtml(value){
            return $('<div>').text(value == null ? '' : String(value)).html();
           }

           function m3FailureText(response, fallback){
            var code = response && response.code ? String(response.code) : 'M3_REQUEST_FAILED';
            var reference = response && response.correlation_id ? String(response.correlation_id) : '';
            return (fallback || 'The request was not completed.') + '\nCode: ' + code + (reference ? '\nReference: ' + reference : '');
           }

           function m3TransportFailure(xhr, fallback){
            var detail = xhr && xhr.status ? ' HTTP ' + xhr.status + '.' : '';
            swal('Request failed', (fallback || 'The server could not complete the request.') + detail, 'error');
           }

           function modal_user_profile_save(){
            var userId = $('#modal_user_profile_user_id').val();
            var categoryId = $('#modal_user_profile_category').val();
            var name = $('#modal_user_profile_name').val();
            if(!userId || categoryId === ''){
              swal('Profile not ready', 'Select an active category before saving.', 'warning');
              return;
            }
            swal({
              title: 'Save profile?',
              text: 'This saves the validated profile and category. Administrator role is not changed by category.',
              type: 'warning',
              showCancelButton: true,
              confirmButtonText: 'Save profile',
              closeOnConfirm: false
            }, function(){
              $.ajax({
                type: 'POST',
                url: '../lib/q_func',
                dataType: 'json',
                data: {admin_save_user_profile:'', user_id:userId, name:name, category_id:categoryId},
                beforeSend: function(){ $('#btn_user_profile_save').prop('disabled', true); },
                complete: function(){ $('#btn_user_profile_save').prop('disabled', false); },
                success: function(response){
                  if(Number(response.status) === 1){
                    temp_prev_category = categoryId;
                    $('#btn_user_profile_save').addClass('btn-primary').removeClass('btn-warning');
                    get_specific_user_sp_access_list(userId);
                    admin_get_all_user_category(1);
                    var unchanged = response.code === 'M3_PROFILE_UNCHANGED';
                    swal(unchanged ? 'No changes' : 'Profile saved', (unchanged ? 'The profile already matches the saved values.' : 'Profile and category policy were applied.') + '\nReference: ' + response.correlation_id, 'success');
                  }else{
                    swal('Profile not saved', m3FailureText(response, 'The profile failed validation or policy checks.'), 'error');
                  }
                },
                error: function(xhr){ m3TransportFailure(xhr, 'The profile was not saved.'); }
              });
            });
           }
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
                    $('#btn_user_profile_save').addClass('btn-primary').removeClass('btn-warning');
                    switch(response['source']){
                     case "1": //reg
                     var u_id_text= "";
                     if(response['data3'] != " "){
                        u_id_text = response['data3'];
                     }else{
                        u_id_text = response['data4'];
                     }
                      $('#modal_user_profile_name').val(response['data1']);
                        m3ProfileSource = String(response['account_source'] || 'legacy').toLowerCase();
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
                        $('#modal_user_profile_category').prop('disabled', false);
                              $('#modal_user_profile_name').prop('disabled', m3ProfileSource !== 'manual')
                                .attr('title', m3ProfileSource === 'manual' ? 'Manual account name may be edited.' : 'External account name is maintained through Safe Resync.');
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
                              m3TransportFailure(xhr, 'The user profile could not be loaded.');
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
                       var safeName = m3EscapeHtml(response[i]['sp_name']);
                       if(response[i]['status']!=1){ //blockedv
                          tr += '<a href="#" class="list-group-item" onclick="uplift_blacklist(&quot;'+response[i]['aclblk_id']+'&quot;)">';
                          tr += '<span class="badge transparent-badge badge-success capitalize-font"><span class="label label-danger">Denied</span></span>';
                          tr += '<p class=" pull-left">'+(Number(i)+Number(1))+'. '+safeName+'</p>';
                          tr += '<div class="clearfix"></div>';
                          tr += '</a>';
                       }else{
                          tr += '<a href="#" class="list-group-item" onclick="deny_access(&quot;'+response[i]['sp_id']+'&quot;)">';
                          tr += '<span class="badge transparent-badge badge-success capitalize-font"><span class="label label-success">Allowed</span></span>';
                          tr += '<p class=" pull-left">'+(Number(i)+Number(1))+'. '+safeName+'</p>';
                          tr += '<div class="clearfix"></div>';
                          tr += '</a>';
                       }
                    });

                        $('#modal_user_profile_sp_list_loading').hide();
                        $('#modal_user_profile_sp_list').html(tr);

                     },
                     error: function (xhr, error, thrown) {
                        $('#modal_user_profile_sp_list_loading').hide();
                        m3TransportFailure(xhr, 'Application access could not be loaded.');
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
                     tr += '<p class=" pull-left">'+(i+1)+'. '+m3EscapeHtml(response[i]['sp_name'])+'</p>';
                       tr += '<div class="clearfix"></div>';
                       tr += '</a>';
                    });
                    $('#modal_user_profile_sp_list_loading').hide();
                    $('#modal_user_profile_sp_add_new_list_div').html(tr);

                 },
                  error: function (xhr, error, thrown) {
                              $('#modal_user_profile_sp_list_loading').hide();
                              m3TransportFailure(xhr, 'Available applications could not be loaded.');
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
                  beforeSend: function(){ $('#btn_add_new_specific_apps_to_accessible').prop('disabled', true); },
                              complete: function(){ $('#btn_add_new_specific_apps_to_accessible').prop('disabled', false); },
                  success: function (response) {
                     if (Number(response.status) === 1){
                          get_specific_user_sp_access_list($('#modal_user_profile_user_id').val());
                          $('#text_lst_of_accible_app').text('LIST OF ACCESSIBLE APPS');
                          $('#btn_add_new_specific_apps_to_accessible').show();
                          $('#btn_close_add_new_specific_apps_to_accessible').hide();
                          $('#modal_user_profile_sp_list_div').fadeIn();
                          $('#modal_user_profile_sp_add_new_list_div').fadeOut();
                        swal("Access added", "The user sessions were revoked so the ACL takes effect immediately.\nReference: " + response.correlation_id, "success");
                     }else{
                        swal("Access not added", m3FailureText(response, "The ACL change was rejected."), "error");
                       }

                    },
                  error: function (xhr, error, thrown) { m3TransportFailure(xhr, 'Application access was not added.'); }
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
                           tr += '<td>'+m3EscapeHtml(response[i]['data1'])+'</td>';
                           tr += '<td>'+m3EscapeHtml(response[i]['sp_name'])+'</td>';
                           tr += '<td><button type="button" class="btn btn-danger btn-rounded btn-xs" onclick="uplift_blacklist(&quot;'+response[i]['aclblk_id']+'&quot;,&quot;'+response[i]['u_id']+'&quot;)">Uplift</button></td>';
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
                     beforeSend: function(){},
                     success: function (response) {
                        if (Number(response.status) === 1){
                           get_specific_user_sp_access_list($('#modal_user_profile_user_id').val());
                           swal("Access denied", "The user sessions were revoked so the deny takes effect immediately.\nReference: " + response.correlation_id, "success");
                        }else{
                           swal("Access not denied", m3FailureText(response, "The ACL change was rejected."), "error");
                       }

                    },
                     error: function (xhr, error, thrown) { m3TransportFailure(xhr, 'Application access was not denied.'); }
                 });
              });
           }


           function uplift_blacklist(aclblk_id,user_id){
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
                     data: {admin_uplift_blacklist_record:'',aclblk_id:aclblk_id,user_id:user_id || $('#modal_user_profile_user_id').val()},
                    beforeSend: function(){
                    },
                    success: function (response) {
                        if (Number(response.status) === 1){
                          admin_get_all_blacklist_record();
                          get_specific_user_sp_access_list($('#modal_user_profile_user_id').val());
                           swal("Deny uplifted", "Access policy was updated and existing sessions were revoked.\nReference: " + response.correlation_id, "success");
                        }else{
                           swal("Deny not uplifted", m3FailureText(response, "The ACL change was rejected."), "error");
                       }

                    },
                     error: function (xhr, error, thrown) { m3TransportFailure(xhr, 'The deny record was not uplifted.'); }
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
         
         
         
         // M3 stages category edits locally. Persistence occurs only through
         // the explicit Save Profile confirmation above.
         $('body').on('change input', '#modal_user_profile_category, #modal_user_profile_name', function () {
            $('#btn_user_profile_save').addClass('btn-warning').removeClass('btn-primary');
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
         
         
         
         
         function m2SecurityFailureText(response, fallback){
         var code = response && response.code ? response.code : 'M2_REQUEST_FAILED';
         var reference = response && response.correlation_id ? '\nReference: ' + response.correlation_id : '';
         var messages = {
            M2_SELF_ACTION_FORBIDDEN: 'Use your own account security workflow instead of this administrator action.',
            M2_USER_NOT_FOUND: 'The selected OneID account no longer exists.',
            M2_USER_INACTIVE: 'Reactivate the account before forcing a password reset.',
            M2_ALREADY_INACTIVE: 'The account is already inactive. No changes were made.',
            M2_ALREADY_ACTIVE: 'The account is already active. No changes were made.',
            M2_PASSWORD_NOT_RESET: 'The password row was not changed. No success has been assumed.',
            M2_STATUS_NOT_CHANGED: 'The account status row was not changed. No success has been assumed.',
            M2_AUDIT_NOT_WRITTEN: 'The audit event could not be written, so the whole action was rolled back.',
            M2_OPERATION_FAILED: 'The operation failed and was rolled back.'
         };
         return (messages[code] || fallback) + '\nCode: ' + code + reference;
         }

         function user_info_reactivate_user(){
         var user_info_id = $('#modal_user_profile_user_id').val();
         var $button = $('#btn_user_profile_reactivate');
         swal({
            title: 'Reactivate user',
            text: 'Reactivate this account? Existing sessions and active OTP will remain revoked.',
            type: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#DD6B55',
            confirmButtonText: 'Reactivate',
            closeOnConfirm: false
         }, function(){
            $.ajax({
               type: 'POST', url: '../lib/q_func', dataType: 'json',
               data: {admin_reactivate_user_record:'',user_info_id:user_info_id},
               beforeSend: function(){ $button.prop('disabled', true); },
               success: function(response){
                  if (response && Number(response.status) === 1) {
                     get_specific_user_profile_info(user_info_id,response.source_status);
                     get_specific_user_sp_access_list(user_info_id);
                     get_service_provider_list();
                     admin_get_all_user_category(1);
                     swal('User reactivated', 'Sessions and active OTP were revoked.\nReference: ' + response.correlation_id, 'success');
                  } else {
                     swal('Reactivate failed', m2SecurityFailureText(response, 'The account was not reactivated.'), 'error');
                  }
               },
               error: function(xhr){ swal('Reactivate failed', 'No success has been assumed.\nHTTP status: ' + xhr.status, 'error'); },
               complete: function(){ $button.prop('disabled', false); }
            });
         });
         }

         function user_info_deactivate_user(){
         var user_info_id = $('#modal_user_profile_user_id').val();
         var $button = $('#btn_user_profile_deactivate');
         swal({
            title: 'Remove user',
            text: 'Deactivate this account and revoke every active session and OTP?',
            type: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#DD6B55',
            confirmButtonText: 'Remove access',
            closeOnConfirm: false
         }, function(){
            $.ajax({
               type: 'POST', url: '../lib/q_func', dataType: 'json',
               data: {admin_deactivate_user_record:'',user_info_id:user_info_id},
               beforeSend: function(){ $button.prop('disabled', true); },
               success: function(response){
                  if (response && Number(response.status) === 1) {
                     get_specific_user_profile_info(user_info_id,response.source_status);
                     get_specific_user_sp_access_list(user_info_id);
                     get_service_provider_list();
                     admin_get_all_user_category(1);
                     swal('User removed', 'Account access, sessions and active OTP were revoked.\nReference: ' + response.correlation_id, 'success');
                  } else {
                     swal('Remove failed', m2SecurityFailureText(response, 'The account was not deactivated.'), 'error');
                  }
               },
               error: function(xhr){ swal('Remove failed', 'No success has been assumed.\nHTTP status: ' + xhr.status, 'error'); },
               complete: function(){ $button.prop('disabled', false); }
            });
         });
         }
         
         function view_category_user_list(cat_id,cat_name){
         window.open("./user_list.php?category_id="+cat_id+"&category_name="+encodeURIComponent(cat_name), '_blank');
         }
         
         function modal_user_profile_resync(){
         var user_id = $('#modal_user_profile_user_id').val();
         var $button = $('#btn_user_profile_resync');
         var resyncFailureText = function(response, fallback){
            var code = response && response.code ? response.code : 'RESYNC_REQUEST_FAILED';
            var reference = response && response.correlation_id
               ? '\nReference: ' + response.correlation_id
               : '';
            var messages = {
               RESYNC_MANUAL_PROTECTED: 'This is a protected manual account and cannot be overwritten from the external source.',
               RESYNC_ACCOUNT_SOURCE_NOT_EXTERNAL: 'Only externally managed accounts can use Resync.',
               RESYNC_USER_INACTIVE: 'Reactivate the account before running Resync.',
               RESYNC_EXTERNAL_USER_NOT_FOUND: 'No matching active record was found in the external source.',
               RESYNC_EXTERNAL_USER_AMBIGUOUS: 'More than one external record matched this identity. No data was changed.',
               RESYNC_EXTERNAL_IDENTITY_MISMATCH: 'The external identity did not match this OneID account.',
               RESYNC_SOURCE_IDENTITY_UNDETERMINED: 'The account does not contain enough identity information to select its authoritative external source.',
               RESYNC_SOURCE_UNAVAILABLE: 'The external source is currently unavailable.',
               RESYNC_APPROVAL_EXPIRED: 'The preview has expired. Run Resync again to generate a fresh preview.',
               RESYNC_PREVIEW_MISMATCH: 'The account or external record changed after preview. Generate a fresh preview.',
               RESYNC_APPROVAL_NOT_AVAILABLE: 'This preview has already been used or is no longer available.'
            };
            return (messages[code] || fallback) + '\nCode: ' + code + reference;
         };
         var applyApprovedResync = function(approvalId){
            $.ajax({
               type: 'POST',
               url: '../lib/q_func',
               dataType: 'json',
               data: {admin_apply_specific_user_resync:'', approval_id:approvalId},
               beforeSend: function(){
                  $button.prop('disabled', true);
               },
               success: function(response){
                  if (!response || Number(response.status) !== 1) {
                     swal('Resync not applied', resyncFailureText(response, 'The approved resync could not be applied.'), 'error');
                     return;
                  }
                  get_specific_user_profile_info(user_id, 1);
                  get_specific_user_sp_access_list(user_id);
                  swal('Resync complete', 'The approved fields were updated and the action was recorded in the audit log.\nReference: ' + response.correlation_id, 'success');
               },
               error: function(xhr){
                  swal('Resync not applied', 'The server rejected or could not complete the request. No success has been assumed.\nHTTP status: ' + xhr.status, 'error');
               },
               complete: function(){
                  $button.prop('disabled', false);
               }
            });
         };

         $.ajax({
            type: 'POST',
            url: '../lib/q_func',
            dataType: 'json',
            data: {admin_preview_specific_user_resync:'', user_id:user_id},
            beforeSend: function(){
               $button.prop('disabled', true);
            },
            success: function(response){
               if (!response || Number(response.status) !== 1) {
                  swal('Preview unavailable', resyncFailureText(response, 'A safe resync preview could not be prepared.'), 'error');
                  return;
               }
               if (!response.can_apply || !Array.isArray(response.changes) || response.changes.length === 0) {
                  swal('No changes', 'The OneID profile already matches the external source. No data was changed.\nReference: ' + response.correlation_id, 'info');
                  return;
               }

               var lines = response.changes.map(function(change){
                  var oldValue = String(change.old || '(empty)').replace(/\s+/g, ' ').substring(0, 90);
                  var newValue = String(change.new || '(empty)').replace(/\s+/g, ' ').substring(0, 90);
                  return '• ' + change.label + ': ' + oldValue + ' → ' + newValue;
               });
               var previewText = 'Review the proposed external-source changes:\n\n'
                  + lines.join('\n')
                  + '\n\nOnly these fields will be updated. Continue?';
               swal({
                  title: 'Apply resync preview?',
                  text: previewText,
                  type: 'warning',
                  showCancelButton: true,
                  confirmButtonColor: '#DD6B55',
                  confirmButtonText: 'Apply changes',
                  cancelButtonText: 'Cancel',
                  closeOnConfirm: true
               }, function(confirmed){
                  if (confirmed === true) {
                     applyApprovedResync(response.approval_id);
                  }
               });
            },
            error: function(xhr){
               swal('Preview unavailable', 'The external preview request failed. No data was changed.\nHTTP status: ' + xhr.status, 'error');
            },
            complete: function(){
               $button.prop('disabled', false);
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
				$('#active_session_count').text('\u2014');
				$('#app_security_session_list').show();
				$('#security_tab_session').html(
					'<tr class="active-session-state-row is-loading"><td colspan="4">' +
					'<span class="active-session-state-icon"><i class="fa fa-circle-o-notch fa-spin" aria-hidden="true"></i></span>' +
					'<strong>Loading active sessions</strong>' +
					'<small>Please wait while session data is retrieved.</small>' +
					'</td></tr>'
				);
          },
          success: function (response) {
				var $tbody = $('#security_tab_session');
				var sessionText = function(value){
					return $('<div>').text(value == null ? '' : value).html();
				};

				if (!Array.isArray(response) || response.length === 0) {
					$('#active_session_count').text('0');
					$tbody.html(
						'<tr class="active-session-state-row"><td colspan="4">' +
						'<span class="active-session-state-icon"><i class="fa fa-user-times" aria-hidden="true"></i></span>' +
						'<strong>No active sessions</strong>' +
						'<small>No user session is currently available.</small>' +
						'</td></tr>'
					);
				} else {
					var rows = '';
					$.each(response, function (i, session) {
						var isCurrent = session.current_token === "1";
						var tokenDateTime = sessionText(session.token_datetime);
						var userName = sessionText(session.name);
						var deviceInfo = sessionText(session.device_info);
						var statusText = isCurrent ? 'Current' : 'Active';

						rows += '<tr>';
						rows += '<td data-label="Token Date / Time"><span class="active-session-cell active-session-time" title="'+tokenDateTime+'">'+tokenDateTime+'</span></td>';
						rows += '<td data-label="User"><span class="active-session-cell active-session-user" title="'+userName+'"><i class="fa fa-user-circle-o" aria-hidden="true"></i>'+userName+'</span></td>';
						rows += '<td data-label="Device"><span class="active-session-cell active-session-device" title="'+deviceInfo+'"><i class="fa fa-desktop" aria-hidden="true"></i>'+deviceInfo+'</span></td>';
						rows += '<td data-label="Status"><span class="active-session-status '+(isCurrent ? 'is-current' : '')+'"><i class="fa '+(isCurrent ? 'fa-check-circle' : 'fa-circle')+'" aria-hidden="true"></i>'+statusText+'</span></td>';
						rows += '</tr>';
					});

					$('#active_session_count').text(response.length);
					$tbody.html(rows);
				}


         
                       },
                       error: function (xhr, error, thrown) {
						$('#active_session_count').text('\u2014');
						$('#security_tab_session').html(
							'<tr class="active-session-state-row is-error"><td colspan="4">' +
							'<span class="active-session-state-icon"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i></span>' +
							'<strong>Unable to load active sessions</strong>' +
							'<small>Please retry or check the application log.</small>' +
							'</td></tr>'
						);
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
               $('#audit_result_count').text('\u2014');
               $('#audit_search_result_tbody').html(
                  '<tr class="audit-state-row is-loading"><td colspan="4">' +
                  '<span class="audit-state-icon"><i class="fa fa-circle-o-notch fa-spin" aria-hidden="true"></i></span>' +
                  '<strong>Searching audit records</strong>' +
                  '<small>Please wait while the selected date range is processed.</small>' +
                  '</td></tr>'
               );
            },
            success: function (response) {
               if(!Array.isArray(response) || response.length == 0){
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
                  $('#audit_result_count').text('0');
                  $('#audit_search_result_tbody').html(
                     '<tr class="audit-state-row"><td colspan="4">' +
                     '<span class="audit-state-icon"><i class="fa fa-folder-open-o" aria-hidden="true"></i></span>' +
                     '<strong>No records found</strong>' +
                     '<small>Try selecting a different date range.</small>' +
                     '</td></tr>'
                  );
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
                  var auditText = function(value){
                     return $('<div>').text(value == null ? '' : value).html();
                  };
                  $.each( response, function( i, value ) {
                           var auditDateTime = auditText(value['datetime']);
                           var auditLogType = auditText(value['log_type']);
                           var auditLogDetail = auditText(value['log_detail']);
                           var auditIpAddress = auditText(value['ip_addr']);
                           tr += '<tr>';
                           tr += '<td data-label="Date / Time"><span class="audit-cell-text audit-log-time" title="'+auditDateTime+'">'+auditDateTime+'</span></td>';
                           tr += '<td data-label="Log Type"><span class="audit-cell-text audit-type-badge" title="'+auditLogType+'">'+auditLogType+'</span></td>';
                           tr += '<td data-label="Activity Details"><span class="audit-cell-text audit-log-details" title="'+auditLogDetail+'">'+auditLogDetail+'</span></td>';
                           tr += '<td data-label="IP Address"><code class="audit-cell-text audit-ip-address" title="'+auditIpAddress+'">'+auditIpAddress+'</code></td>';
                           tr += '</tr>';
                        });
                  $('#audit_result_count').text(response.length);
                  $('#audit_search_result_tbody').html(tr);

               }
         
            },
            error: function (xhr, error, thrown) {
               $('#audit_result_count').text('\u2014');
               $('#audit_search_result_tbody').html(
                  '<tr class="audit-state-row is-error"><td colspan="4">' +
                  '<span class="audit-state-icon"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i></span>' +
                  '<strong>Unable to load audit records</strong>' +
                  '<small>Please retry the search or check the application log.</small>' +
                  '</td></tr>'
               );
            }
         });
         }

         function sync_status_badge(status){
            var labels = {
               '0': ['In Progress', 'sync-status-progress'],
               '1': ['Temp Only', 'sync-status-temp'],
               '2': ['Complete', 'sync-status-complete'],
               '3': ['No Data', 'sync-status-empty'],
               '4': ['No Changes', 'sync-status-unchanged']
            };
            var info = labels[String(status)] || ['Unknown', 'sync-status-unknown'];
            return '<span class="sync-status-badge ' + info[1] + '">' + info[0] + '</span>';
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
               'NEW': 'sync-action-new',
               'UPDATE': 'sync-action-update',
               'DEACTIVATE': 'sync-action-deactivate',
               'REACTIVATE': 'sync-action-reactivate'
            };
            var cls = classes[action] || 'sync-status-unknown';
            return '<span class="sync-action-badge ' + cls + '">' + action + '</span>';
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

         function sync_update_summary(rows){
            if(!Array.isArray(rows)){
               $('#sync_summary_total, #sync_summary_complete, #sync_summary_changes').text('—');
               return;
            }
            var completed = 0;
            var changes = 0;
            $.each(rows, function(i, row){
               if(String(row.ext_head_status) === '2'){
                  completed++;
               }
               changes += parseInt(row.total_new || 0, 10);
               changes += parseInt(row.total_updated || 0, 10);
               changes += parseInt(row.total_deactivated || 0, 10);
               changes += parseInt(row.total_reactivated || 0, 10);
            });
            $('#sync_summary_total').text(rows.length);
            $('#sync_summary_complete').text(completed);
            $('#sync_summary_changes').text(changes);
         }

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
               tr += '<tr class="sync-session-row">';
               tr += '<td data-label="Session"><span class="sync-session-id">#' + row.ext_head_id + '</span></td>';
               tr += '<td data-label="Date / Time"><span class="sync-session-time">' + dtStart + dtEnd + '</span></td>';
               tr += '<td data-label="Triggered By"><span class="sync-session-trigger">' + sync_format_triggered_by(row) + '</span></td>';
               tr += '<td data-label="Changes"><div class="sync-change-metrics">';
               tr += '<span><small>New</small><strong>' + (row.total_new || 0) + '</strong></span>';
               tr += '<span><small>Updated</small><strong>' + (row.total_updated || 0) + '</strong></span>';
               tr += '<span><small>Deactivated</small><strong>' + (row.total_deactivated || 0) + '</strong></span>';
               tr += '<span><small>Reactivated</small><strong>' + (row.total_reactivated || 0) + '</strong></span>';
               tr += '</div></td>';
               tr += '<td data-label="Status">' + sync_status_badge(row.ext_head_status) + '</td>';
               tr += '<td data-label="Action"><button type="button" class="sync-view-button" title="View session details" aria-label="View session details" onclick="load_sync_log_detail(' + row.ext_head_id + ', \'' + String(dtStart).replace(/'/g, "\\'") + '\');"><i class="fa fa-eye" aria-hidden="true"></i><span class="sr-only">View session details</span></button></td>';
               tr += '</tr>';
            });
            $('#sync_session_tbody').html(tr || '<tr class="sync-empty-row"><td colspan="6">No sync sessions found.</td></tr>');
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
               tr += '<td data-label="#"><span class="sync-row-number">' + (start + i + 1) + '</span></td>';
               tr += '<td data-label="User ID"><span class="sync-detail-user">' + row.u_id + '</span></td>';
               tr += '<td data-label="Action">' + sync_action_badge(row.action) + '</td>';
               tr += '<td data-label="Change Details"><div class="sync-detail-data">';
               tr += '<div><span>Changed fields</span><p>' + sync_format_json_cell(row.changed_fields) + '</p></div>';
               tr += '<div><span>Old data</span><p>' + sync_format_json_cell(row.old_data) + '</p></div>';
               tr += '<div><span>New data</span><p>' + sync_format_json_cell(row.new_data) + '</p></div>';
               tr += '</div></td>';
               tr += '<td data-label="Time"><span class="sync-session-time">' + (row.logged_at || '-') + '</span></td>';
               tr += '</tr>';
            });
            $('#sync_detail_tbody').html(tr || '<tr class="sync-empty-row"><td colspan="5">No changes recorded for this session.</td></tr>');
            render_sync_detail_pagination(page, totalPages);
         }

         function load_sync_sessions(){
            $.ajax({
               type: 'POST',
               url: '../lib/q_func',
               dataType: 'json',
               data: { admin_get_sync_sessions: '' },
               beforeSend: function(){
                  sync_update_summary(null);
                  $('#sync_session_tbody').html('<tr class="sync-empty-row"><td colspan="6"><i class="fa fa-circle-o-notch fa-spin" aria-hidden="true"></i> Loading sessions...</td></tr>');
                  $('#sync_session_pagination').html('');
               },
               success: function(response){
                  if(!response || response.length === 0){
                     syncSessionsData = [];
                     sync_update_summary([]);
                     $('#sync_session_tbody').html('<tr class="sync-empty-row"><td colspan="6">No sync sessions found.</td></tr>');
                     $('#sync_session_pagination').html('');
                     return;
                  }
                  syncSessionsData = response;
                  sync_update_summary(syncSessionsData);
                  render_sync_sessions_page(1);
               },
               error: function(xhr){
                  syncSessionsData = [];
                  sync_update_summary([]);
                  $('#sync_session_tbody').html('<tr class="sync-empty-row"><td colspan="6">Failed to load sync sessions.</td></tr>');
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
                  $('#sync_detail_tbody').html('<tr class="sync-empty-row"><td colspan="5"><i class="fa fa-circle-o-notch fa-spin" aria-hidden="true"></i> Loading session details...</td></tr>');
                  $('#sync_detail_pagination').html('');
                  $('#sync_detail_header').text('Changes in Session #' + ext_head_id + (sessionDate ? ' — ' + sessionDate : ''));
                  $('#sync_session_panel').hide();
                  $('#sync_detail_panel').show();
               },
               success: function(response){
                  if(!response || response.length === 0){
                     syncLogDetailData = [];
                     $('#sync_detail_tbody').html('<tr class="sync-empty-row"><td colspan="5">No changes recorded for this session.</td></tr>');
                     $('#sync_detail_pagination').html('');
                     return;
                  }
                  syncLogDetailData = response;
                  render_sync_detail_page(1);
               },
               error: function(xhr){
                  syncLogDetailData = [];
                  $('#sync_detail_tbody').html('<tr class="sync-empty-row"><td colspan="5">Failed to load session details.</td></tr>');
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
         var user_id = $('#modal_user_profile_user_id').val();
         var $button = $('#btn_user_profile_reset_password');
         swal({
            title: 'Force Reset Password',
            text: 'Replace the current password, require Forgot Password, and revoke every active session and OTP?',
            type: 'warning',
            confirmButtonColor: '#DD6B55',
            confirmButtonText: 'Force reset',
            showCancelButton: true,
            closeOnConfirm: false
         }, function(){
            $.ajax({
               type: 'POST',
               url: '../lib/q_func',
               dataType: 'json',
               data: {admin_reset_password_user:'', user_id:user_id},
               beforeSend: function(){ $button.prop('disabled', true); },
               success: function(response){
                  if (response && Number(response.status) === 1) {
                     swal('Password reset', 'Password reset requirement recorded. Sessions and active OTP were revoked. The user must use Forgot Password.\nReference: ' + response.correlation_id, 'success');
                  } else {
                     swal('Password reset failed', m2SecurityFailureText(response, 'The password was not reset.'), 'error');
                  }
               },
               error: function(xhr){
                  swal('Password reset failed', 'No success has been assumed.\nHTTP status: ' + xhr.status, 'error');
               },
               complete: function(){ $button.prop('disabled', false); }
            });
         });
      }
	  
	   const releaseNotes = [
    {
      version: <?php echo json_encode(ONEID_APP_VERSION); ?>,
      date: "2026-07-14",
      changes: [
        "Dashboard pengguna kini mempunyai <b>carian aplikasi merentas kategori</b> berdasarkan nama dan fungsi aplikasi, dengan kategori padanan dipilih secara automatik.",
        "Tab <b>Favourite</b> berikon bintang ditambah pada kedudukan pertama untuk mengumpulkan aplikasi yang kerap digunakan tanpa mengeluarkannya daripada kategori asal.",
        "Pilihan Favourite disimpan secara persistent mengikut akaun melalui jadual preference khusus dan kekal selepas logout atau login semula.",
        "Favourite dikawal oleh session dan effective ACL; preference tidak menambah akses serta tidak boleh memintas category allow, direct allow atau blacklist deny.",
        "Setiap kad aplikasi kini menyediakan tindakan Favourite berkeadaan kelabu/kuning dan tindakan akses yang lebih jelas serta responsive.",
        "Aplikasi OneID SSO menggunakan label <b>Login</b>, manakala aplikasi NON SSO menggunakan label <b>Akses</b> dan badge <b>Akses terus</b>.",
        "Tab <b>NON SSO</b> diberi identiti warna berbeza supaya pautan terus mudah dibezakan daripada aplikasi berintegrasi OneID.",
        "Migration, rollback, characterization contract dan runbook UAT U1 ditambah; smoke HTTP, structure, M2, M3 dan release regression kekal lulus.",
        "Gate live Apply-path M1 direkod sebagai ditangguhkan oleh owner sehingga akaun external ujian yang sesuai tersedia; penangguhan ini tidak mengaktifkan External Sync Apply S4E."
      ]
    },
    {
      version: "2.0.3",
      date: "2026-07-14",
      changes: [
        "<b>Profile Save, category policy dan ACL hardening</b> disiapkan melalui fasa M3 dengan validation, explicit confirmation, transaction, rollback dan correlated audit trail.",
        "Butang <b>Save Profile</b> kini menjadi satu-satunya laluan menyimpan nama dan kategori; perubahan dropdown tidak lagi terus mengubah database.",
        "Nama akaun external-managed dijadikan read-only dan hanya boleh dikemas kini melalui Safe Resync, manakala nama akaun manual boleh disimpan selepas validation.",
        "Kategori pengguna dipisahkan daripada role administrator; hardcoded category ID 9 dan mutator category/role legacy telah dibuang supaya <b>u_type</b> sentiasa dikekalkan.",
        "ACL khusus pengguna untuk <b>Allow, Deny dan Uplift</b> kini mengesahkan user, aplikasi, duplicate state dan ownership deny record sebelum mutation.",
        "Perubahan kategori dan ACL membatalkan sesi aktif pengguna supaya policy baharu berkuat kuasa serta-merta.",
        "Nama aplikasi dinyahkod secara selamat sebelum dimasukkan ke DOM dan kegagalan AJAX kini memaparkan code serta correlation reference.",
        "Manual UAT profile/category, Forbidden admin route, ACL allow/deny/uplift, session revocation dan Audit Log telah disahkan lulus menggunakan akaun ujian terkawal.",
        "Defense-in-depth consumer turut disahkan: direct allow OneID tidak memintas authorization dalaman aplikasi sasaran."
      ]
    },
    {
      version: "2.0.2",
      date: "2026-07-14",
      changes: [
        "Menyelaraskan identiti release kepada <b>Version 2.0.2</b> melalui satu source metadata untuk login, dashboard pengguna, dashboard admin, footer dan latest release badge.",
        "Copyright aplikasi distandardkan kepada <b>2026 © PTMK | Aplikasi Digital</b> pada semua paparan utama.",
        "UI <b>Version Releases</b> dibina semula menggunakan release cards, metadata release dan changelog yang lebih tersusun serta responsive.",
        "UI <b>SSO Configuration</b>, <b>Sync Log</b>, <b>Audit Log</b>, <b>Active Sessions</b>, <b>User Accounts</b>, <b>Web Apps</b> dan dashboard pengguna disusun semula dengan hierarchy, compact table/card serta responsive state yang konsisten.",
        "Maklumat peranti sesi diperbetulkan: kurungan brand kosong dibuang dan login baharu merekod jenis peranti, browser serta sistem operasi daripada User-Agent.",
        "Single-user <b>Resync User Info</b> diperkukuh dengan external SELECT-only lookup, provenance protection, preview perubahan, one-time approval, confirmation, transaction, rollback dan correlated audit trail.",
        "Action modal <b>Force Reset Password, Remove User dan Reactivate User</b> diperkukuh dengan row lock, verified mutation, session/OTP revocation, mandatory correlated audit, transaction rollback dan perlindungan self-lockout."
      ]
    },
    {
      version: "2.0.1",
      date: "2026-07-14",
      changes: [
        "Memperkenalkan <b>External Sync Preview</b> yang read-only — memaparkan jumlah sumber, cadangan akaun baharu/kemas kini, deactivate/reactivate, perlindungan akaun manual, collision, plan hash dan tempoh sah tanpa mengubah database.",
        "Menambah lapisan keselamatan sync merangkumi <b>single-run lock, transaction boundary, source completeness, blast-radius policy</b> dan reconciliation sebelum commit.",
        "Preview dan Apply kini direka dengan <b>server-bound approval</b>, strict feature flags dan perlindungan replay; Apply kekal disabled sehingga semua gate operasi mendapat kelulusan.",
        "Akaun yang ditambah secara manual mempunyai <b>provenance</b> dan perlindungan supaya external sync tidak menimpa akaun manual secara tidak sengaja.",
        "Verification sync diperkukuh dengan regression contracts, external SELECT-only evidence, backup penuh dan isolated restore rehearsal.",
        "Flow admin preview diperbetulkan supaya menggunakan token CSRF baharu selepas login dan session rotation.",
        "Paparan <b>Version Releases</b> direka semula menggunakan release cards yang lebih kemas, mudah dibaca dan responsive."
      ]
    },
    {
      version: "2.0.0",
      date: "2026-07-14",
      changes: [
        "<b>Major security hardening</b> untuk authentication dan authorization: server-side admin guard, default-deny action mapping, CSRF protection dan session regeneration.",
        "Password legacy dimigrasikan secara terkawal kepada hash moden; reset password, OTP, rate limiting, session cookie dan token SSO diperkukuh.",
        "Secrets database, SMTP dan integrasi dipindahkan daripada source code kepada runtime secret configuration dengan permission yang lebih ketat.",
        "Upload icon diperketat menggunakan validation MIME/kandungan, allowlist format, nama rawak dan larangan script execution dalam direktori upload.",
        "Endpoint API, IDMS dan SKP diperkukuh melalui validation, parameterized query, TLS verification, response yang lebih selamat dan kawalan akses integrasi.",
        "Document root dimigrasikan sepenuhnya ke <b>public/</b>; source aplikasi, konfigurasi, docs, storage, tools dan database dump tidak lagi terdedah melalui web.",
        "Struktur projek disusun semula kepada boundary <b>app/, bootstrap/, config/, public/, resources/, storage/, tests/ dan tools/</b> dengan compatibility wrapper untuk URL legacy yang masih sah.",
        "Fail lama, diagnostic endpoint, duplicate implementation dan aset transitional melalui inventori, quarantine dan cleanup terkawal tanpa memutuskan login, API atau SSO consumer.",
        "Kod application layer mula diekstrak kepada service, adapter, planner dan orchestrator yang boleh diuji tanpa mengubah caller production secara terus.",
        "Manual Add User diperkukuh dengan validation, transaction, audit, provenance dan perlindungan collision dengan external source.",
        "Automated characterization, contract tests, smoke tests, rollback runbook dan dokumentasi berfasa ditambah untuk menyokong deployment serta audit yang boleh diulang."
      ]
    },
    {
      version: "1.0.4",
      date: "2026-07-13",
      changes: [
        "Release penyelenggaraan terakhir untuk siri <b>1.x.x</b> dan baseline sebelum program security hardening serta restructuring bermula.",
        "Tiada patch baharu akan dikeluarkan untuk siri <b>1.x.x</b>; versi ini dikekalkan sebagai rujukan legacy sahaja.",
        "Semua pembangunan seterusnya diteruskan melalui major upgrade <b>v2.0.0</b>."
      ]
    },
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

  const releaseList = document.getElementById("release_notes_list");
  const currentReleaseBadge = document.getElementById("current_release_badge");
  const formatReleaseDate = date => {
    const parsedDate = new Date(`${date}T00:00:00`);
    return new Intl.DateTimeFormat('ms-MY', {
      day: '2-digit',
      month: 'short',
      year: 'numeric'
    }).format(parsedDate);
  };

  if (releaseNotes.length > 0) {
    currentReleaseBadge.innerHTML = `
      <span>Current release</span>
      <strong>v${releaseNotes[0].version}</strong>
    `;
  }

  releaseList.innerHTML = releaseNotes.map((release, releaseIndex) => `
    <article class="version-release-card${releaseIndex === 0 ? ' is-current' : ''}">
      <div class="version-release-meta">
        ${releaseIndex === 0 ? '<span class="version-latest-label">Latest</span>' : ''}
        <span class="version-number">v${release.version}</span>
        <time class="version-release-date" datetime="${release.date}">${formatReleaseDate(release.date)}</time>
      </div>
      <div class="version-release-content">
        <h5>${releaseIndex === 0 ? 'Latest updates' : `Release ${release.version}`}</h5>
        <ol class="version-change-list">
          ${release.changes.map(item => `<li>${item}</li>`).join("")}
        </ol>
      </div>
    </article>
  `).join("");
      </script>
      <style>
         #the-basics .tt-dropdown-menu {
         max-height: 150px;
         overflow-y: auto;
         }
         .modal { overflow-y: auto !important; }

      #follo_8 .web-app-panel {
        min-height: 620px;
        padding: 30px;
        background: #f7f9fc;
      }

      #follo_8 .web-app-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        padding-bottom: 22px;
        margin-bottom: 18px;
        border-bottom: 1px solid #e3e8ef;
      }

      #follo_8 .web-app-eyebrow {
        display: block;
        margin-bottom: 5px;
        color: #168fcb;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .11em;
        text-transform: uppercase;
      }

      #follo_8 .web-app-title {
        margin: 0 0 7px;
        color: #1f2937;
        font-size: 24px;
        font-weight: 600;
        line-height: 1.25;
      }

      #follo_8 .web-app-intro {
        max-width: 620px;
        margin: 0;
        color: #687386;
        font-size: 14px;
        line-height: 1.6;
      }

      #follo_8 .web-app-count {
        flex: 0 0 auto;
        min-width: 112px;
        padding: 10px 14px;
        border: 1px solid #cfe8f6;
        border-radius: 7px;
        background: #eef8fd;
        text-align: right;
      }

      #follo_8 .web-app-count span,
      #follo_8 .web-app-count strong {
        display: block;
      }

      #follo_8 .web-app-count span {
        margin-bottom: 2px;
        color: #627386;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: .06em;
        text-transform: uppercase;
      }

      #follo_8 .web-app-count strong {
        color: #087eaf;
        font-size: 17px;
        font-weight: 700;
      }

      #follo_8 .web-app-toolbar,
      #follo_8 .web-app-category-nav,
      #follo_8 .web-app-directory,
      #follo_8 .web-app-state {
        border: 1px solid #e1e6ed;
        background: #fff;
        box-shadow: 0 2px 7px rgba(31, 41, 55, .04);
      }

      #follo_8 .web-app-toolbar {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        padding: 18px 20px;
        border-radius: 8px 8px 0 0;
      }

      #follo_8 .web-app-toolbar h5 {
        margin: 0 0 4px;
        color: #29384b;
        font-size: 14px;
        font-weight: 600;
      }

      #follo_8 .web-app-toolbar p {
        margin: 0;
        color: #7a8696;
        font-size: 12px;
        line-height: 1.45;
      }

      #follo_8 .web-app-actions {
        display: flex;
        align-items: center;
        flex: 0 0 auto;
        gap: 8px;
      }

      #follo_8 .web-app-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 36px;
        padding: 0 13px;
        border: 1px solid #b9ddeb;
        border-radius: 6px;
        background: #fff;
        color: #168fcb;
        font-size: 11px;
        font-weight: 600;
        gap: 7px;
      }

      #follo_8 .web-app-action:hover,
      #follo_8 .web-app-action:focus {
        background: #eef8fd;
        color: #087eaf;
      }

      #follo_8 .web-app-action.is-primary {
        border-color: #11a8df;
        background: #11a8df;
        color: #fff;
      }

      #follo_8 .web-app-action.is-primary:hover,
      #follo_8 .web-app-action.is-primary:focus {
        border-color: #0c91c2;
        background: #0c91c2;
      }

      #follo_8 .web-app-action.is-danger {
        border-color: #e8c7c4;
        color: #b85b53;
      }

      #follo_8 .web-app-action.is-danger:hover,
      #follo_8 .web-app-action.is-danger:focus {
        background: #fdf3f2;
        color: #a44b44;
      }

      #follo_8 .web-app-refresh,
      #follo_8 .web-app-view {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 36px;
        width: 36px;
        height: 36px;
        padding: 0;
        border: 1px solid #dce4ec;
        border-radius: 7px;
        background: #fff;
        color: #168fcb;
        transition: background .18s ease, border-color .18s ease;
      }

      #follo_8 .web-app-refresh:hover,
      #follo_8 .web-app-refresh:focus,
      #follo_8 .web-app-view:hover,
      #follo_8 .web-app-view:focus {
        border-color: #b9ddeb;
        background: #eef8fd;
        color: #087eaf;
      }

      #follo_8 .web-app-category-nav {
        padding: 13px 14px;
        border-top: 0;
        border-bottom-color: #dfe5ec;
        box-shadow: none;
      }

      #follo_8 #WebAppsTabsHeader {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 0;
        padding: 0;
        border: 0;
      }

      #follo_8 #WebAppsTabsHeader > li {
        float: none;
        margin: 0;
      }

      #follo_8 #WebAppsTabsHeader > li > a {
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

      #follo_8 #WebAppsTabsHeader > li > a strong {
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

      #follo_8 #WebAppsTabsHeader > li.active > a,
      #follo_8 #WebAppsTabsHeader > li.active > a:hover,
      #follo_8 #WebAppsTabsHeader > li.active > a:focus {
        border-color: #11a8df;
        background: #11a8df;
        color: #fff;
      }

      #follo_8 #WebAppsTabsHeader > li.active > a strong {
        background: rgba(255, 255, 255, .22);
        color: #fff;
      }

      #follo_8 .web-app-directory {
        overflow: hidden;
        border-top: 0;
        border-radius: 0 0 8px 8px;
      }

      #follo_8 .web-app-card-list {
        padding: 0;
      }

      #follo_8 .web-app-card {
        display: grid;
        grid-template-columns: 26px 52px minmax(0, 1fr) 36px;
        align-items: start;
        gap: 13px;
        padding: 16px 20px;
        border-bottom: 1px solid #edf0f4;
        background: #fff;
      }

      #follo_8 .web-app-card:last-child {
        border-bottom: 0;
      }

      #follo_8 .web-app-card:hover {
        background: #fbfdff;
      }

      #follo_8 .web-app-card-index {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        margin-top: 15px;
        border-radius: 50%;
        background: #eaf6fc;
        color: #168fcb;
        font-size: 9px;
        font-weight: 700;
      }

      #follo_8 .web-app-card-image {
        width: 52px;
        height: 52px;
        overflow: hidden;
        border: 1px solid #e1e6eb;
        border-radius: 10px;
        background: #f4f6f8;
      }

      #follo_8 .web-app-card-image img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
      }

      #follo_8 .web-app-card-content {
        min-width: 0;
        padding-top: 1px;
      }

      #follo_8 .web-app-card-name {
        display: flex;
        align-items: center;
        min-width: 0;
        gap: 8px;
        margin-bottom: 4px;
      }

      #follo_8 .web-app-card-name strong {
        min-width: 0;
        overflow: hidden;
        color: #2f3e52;
        font-size: 13px;
        font-weight: 600;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      #follo_8 .web-app-sso-badge {
        flex: 0 0 auto;
        padding: 3px 7px;
        border-radius: 20px;
        background: #fff3df;
        color: #a86c15;
        font-size: 8px;
        font-weight: 700;
        letter-spacing: .035em;
        line-height: 1.35;
        text-transform: uppercase;
      }

      #follo_8 .web-app-sso-badge.is-enabled {
        background: #e7f7ee;
        color: #22844f;
      }

      #follo_8 .web-app-card-content p,
      #follo_8 .web-app-domain {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      #follo_8 .web-app-card-content p {
        margin: 0 0 4px;
        color: #758193;
        font-size: 11px;
      }

      #follo_8 .web-app-domain {
        color: #168fcb;
        font-size: 10px;
      }

      #follo_8 .web-app-domain i {
        width: 14px;
        margin-right: 4px;
        color: #8ab9cb;
      }

      #follo_8 .web-app-view {
        margin-top: 8px;
      }

      #follo_8 .web-app-state,
      #follo_8 .web-app-category-empty {
        padding: 38px 20px;
        color: #6f7c8c;
        text-align: center;
      }

      #follo_8 .web-app-state {
        border-radius: 0 0 8px 8px;
        border-top: 0;
      }

      #follo_8 .web-app-state span,
      #follo_8 .web-app-state strong,
      #follo_8 .web-app-state small {
        display: block;
      }

      #follo_8 .web-app-state > span {
        margin-bottom: 9px;
        color: #27a8d8;
        font-size: 18px;
      }

      #follo_8 .web-app-state strong {
        margin-bottom: 4px;
        color: #425166;
        font-size: 13px;
      }

      #follo_8 .web-app-state small {
        color: #8994a2;
        font-size: 11px;
      }

      #follo_8 .web-app-state.is-error > span {
        color: #d46b62;
      }

      #follo_8 .web-app-category-empty {
        border-bottom: 0;
        font-size: 11px;
      }

      #follo_8 .web-app-category-empty i,
      #follo_8 .web-app-category-empty span {
        display: block;
      }

      #follo_8 .web-app-category-empty i {
        margin-bottom: 8px;
        color: #91a0b1;
        font-size: 18px;
      }

      @media (max-width: 900px) {
        #follo_8 .web-app-toolbar {
          display: block;
        }

        #follo_8 .web-app-actions {
          flex-wrap: wrap;
          margin-top: 16px;
        }
      }

      @media (max-width: 767px) {
        #follo_8 .web-app-panel {
          padding: 20px 15px;
        }

        #follo_8 .web-app-header {
          display: block;
        }

        #follo_8 .web-app-count {
          width: max-content;
          margin-top: 16px;
          text-align: left;
        }

        #follo_8 .web-app-action span {
          display: none;
        }

        #follo_8 .web-app-action {
          width: 36px;
          padding: 0;
        }

        #follo_8 .web-app-card {
          grid-template-columns: 22px 46px minmax(0, 1fr) 36px;
          gap: 10px;
          padding: 14px;
        }

        #follo_8 .web-app-card-image {
          width: 46px;
          height: 46px;
        }

        #follo_8 .web-app-card-name {
          align-items: flex-start;
          flex-direction: column;
          gap: 4px;
        }

        #follo_8 .web-app-card-index {
          margin-top: 12px;
        }

        #follo_8 .web-app-view {
          margin-top: 5px;
        }
      }

      #tab_user .user-account-panel {
        min-height: 620px;
        padding: 30px;
        background: #f7f9fc;
      }

      #tab_user .user-account-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        padding-bottom: 22px;
        margin-bottom: 18px;
        border-bottom: 1px solid #e3e8ef;
      }

      #tab_user .user-account-eyebrow {
        display: block;
        margin-bottom: 5px;
        color: #168fcb;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .11em;
        text-transform: uppercase;
      }

      #tab_user .user-account-title {
        margin: 0 0 7px;
        color: #1f2937;
        font-size: 24px;
        font-weight: 600;
        line-height: 1.25;
      }

      #tab_user .user-account-intro {
        max-width: 620px;
        margin: 0;
        color: #687386;
        font-size: 14px;
        line-height: 1.6;
      }

      #tab_user .user-category-count {
        flex: 0 0 auto;
        min-width: 108px;
        padding: 10px 14px;
        border: 1px solid #cfe8f6;
        border-radius: 7px;
        background: #eef8fd;
        text-align: right;
      }

      #tab_user .user-category-count span,
      #tab_user .user-category-count strong {
        display: block;
      }

      #tab_user .user-category-count span {
        margin-bottom: 2px;
        color: #627386;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: .06em;
        text-transform: uppercase;
      }

      #tab_user .user-category-count strong {
        color: #087eaf;
        font-size: 17px;
        font-weight: 700;
      }

      #tab_user .user-search-card,
      #tab_user .user-category-card {
        border: 1px solid #e1e6ed;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 2px 7px rgba(31, 41, 55, .04);
      }

      #tab_user .user-search-card {
        padding: 19px 20px;
        margin-bottom: 18px;
      }

      #tab_user .user-search-copy {
        display: flex;
        align-items: flex-start;
        gap: 13px;
        margin-bottom: 15px;
      }

      #tab_user .user-search-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 38px;
        width: 38px;
        height: 38px;
        border-radius: 8px;
        background: #eaf6fc;
        color: #149dce;
        font-size: 15px;
      }

      #tab_user .user-search-copy h5,
      #tab_user .user-category-header h5 {
        margin: 0 0 4px;
        color: #29384b;
        font-size: 14px;
        font-weight: 600;
      }

      #tab_user .user-search-copy p,
      #tab_user .user-category-header p {
        margin: 0;
        color: #7a8696;
        font-size: 12px;
        line-height: 1.45;
      }

      #tab_user .user-search-controls {
        position: relative;
        display: flex;
        align-items: flex-start;
        gap: 9px;
      }

      #tab_user .user-search-input-wrap {
        position: relative;
        flex: 1 1 auto;
        min-width: 0;
      }

      #tab_user .user-search-input-wrap > i {
        position: absolute;
        top: 50%;
        left: 14px;
        z-index: 4;
        color: #8995a4;
        transform: translateY(-50%);
        pointer-events: none;
      }

      #tab_user .user-search-input-wrap .twitter-typeahead,
      #tab_user .user-search-input-wrap .typeahead {
        width: 100%;
      }

      #tab_user .user-search-input-wrap .tt-dropdown-menu,
      #tab_user .user-search-input-wrap .tt-menu {
        width: 100% !important;
        min-width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box;
      }

      #tab_user .user-search-input-wrap .tt-suggestion {
        width: 100%;
        box-sizing: border-box;
        overflow-wrap: anywhere;
        white-space: normal;
        text-align: left;
        vertical-align: top;
      }

      #tab_user #search_user_input {
        height: 42px;
        padding: 9px 14px 9px 39px;
        border: 1px solid #dce2e9;
        border-radius: 6px;
        background: #fff;
        color: #344256;
        box-shadow: none;
      }

      #tab_user #search_user_input:focus {
        border-color: #58b9e4;
        box-shadow: 0 0 0 3px rgba(17, 168, 223, .11);
      }

      #tab_user .user-search-clear,
      #tab_user .user-category-refresh,
      #tab_user .user-result-view {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 42px;
        width: 42px;
        height: 42px;
        padding: 0;
        border: 1px solid #dce4ec;
        border-radius: 7px;
        background: #fff;
        color: #168fcb;
        transition: background .18s ease, border-color .18s ease;
      }

      #tab_user .user-search-clear:hover,
      #tab_user .user-search-clear:focus,
      #tab_user .user-category-refresh:hover,
      #tab_user .user-category-refresh:focus,
      #tab_user .user-result-view:hover,
      #tab_user .user-result-view:focus {
        border-color: #b9ddeb;
        background: #eef8fd;
        color: #087eaf;
      }

      #tab_user .Typeahead-spinner {
        position: absolute;
        top: 8px;
        right: 55px;
        z-index: 5;
        width: 26px;
        height: 26px;
      }

      #tab_user .user-search-state,
      #tab_user .user-search-result {
        margin: 15px 0 0;
        padding: 0;
        border: 1px solid #e7ebf0;
        border-radius: 7px;
        background: #fbfcfd;
      }

      #tab_user .user-search-state {
        padding: 25px 18px;
        text-align: center;
      }

      #tab_user .user-search-state span,
      #tab_user .user-search-state strong,
      #tab_user .user-search-state small {
        display: block;
      }

      #tab_user .user-search-state span {
        margin-bottom: 8px;
        color: #27a8d8;
        font-size: 17px;
      }

      #tab_user .user-search-state strong {
        margin-bottom: 3px;
        color: #425166;
        font-size: 13px;
      }

      #tab_user .user-search-state small {
        color: #8994a2;
        font-size: 11px;
      }

      #tab_user .user-search-result-heading {
        padding: 10px 14px;
        border-bottom: 1px solid #e7ebf0;
        color: #788597;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
      }

      #tab_user .user-result-card,
      #tab_user .user-result-empty {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 14px;
      }

      #tab_user .user-result-profile,
      #tab_user .user-result-empty {
        min-width: 0;
      }

      #tab_user .user-result-profile {
        display: flex;
        align-items: flex-start;
        gap: 11px;
      }

      #tab_user .user-result-avatar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 38px;
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: #eef4f8;
        color: #52849b;
      }

      #tab_user .user-result-profile > div {
        min-width: 0;
      }

      #tab_user .user-result-profile strong,
      #tab_user .user-result-profile small {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      #tab_user .user-result-profile strong {
        color: #344256;
        font-size: 13px;
        font-weight: 600;
      }

      #tab_user .user-result-profile small {
        margin: 2px 0 6px;
        color: #7a8696;
        font-size: 11px;
      }

      #tab_user .user-result-status {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 20px;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .035em;
        line-height: 1.4;
        text-transform: uppercase;
      }

      #tab_user .user-result-status.is-registered {
        background: #e7f7ee;
        color: #22844f;
      }

      #tab_user .user-result-status.is-removed,
      #tab_user .user-result-status.is-unregistered {
        background: #fceceb;
        color: #b34d45;
      }

      #tab_user .user-result-empty {
        justify-content: flex-start;
        color: #d46b62;
      }

      #tab_user .user-result-empty > i {
        font-size: 18px;
      }

      #tab_user .user-result-empty strong,
      #tab_user .user-result-empty small {
        display: block;
      }

      #tab_user .user-result-empty strong {
        color: #4d5b6e;
        font-size: 12px;
      }

      #tab_user .user-result-empty small {
        margin-top: 2px;
        color: #8994a2;
        font-size: 11px;
      }

      #tab_user .user-category-card {
        overflow: hidden;
      }

      #tab_user .user-category-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        padding: 18px 20px;
        border-bottom: 1px solid #e8ecf1;
      }

      #tab_user .user-category-actions {
        display: flex;
        align-items: center;
        flex: 0 0 auto;
        gap: 8px;
      }

      #tab_user .user-category-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 36px;
        padding: 0 13px;
        border: 1px solid #b9ddeb;
        border-radius: 6px;
        background: #fff;
        color: #168fcb;
        font-size: 11px;
        font-weight: 600;
        gap: 7px;
      }

      #tab_user .user-category-action:hover,
      #tab_user .user-category-action:focus {
        background: #eef8fd;
        color: #087eaf;
      }

      #tab_user .user-category-action.is-primary {
        border-color: #11a8df;
        background: #11a8df;
        color: #fff;
      }

      #tab_user .user-category-action.is-primary:hover,
      #tab_user .user-category-action.is-primary:focus {
        border-color: #0c91c2;
        background: #0c91c2;
      }

      #tab_user .user-category-refresh {
        flex-basis: 36px;
        width: 36px;
        height: 36px;
      }

      #tab_user .user-category-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 180px 150px;
        align-items: center;
        gap: 20px;
        padding: 14px 20px;
        border-bottom: 1px solid #edf0f4;
      }

      #tab_user .user-category-row:last-child {
        border-bottom: 0;
      }

      #tab_user .user-category-row:hover {
        background: #fbfdff;
      }

      #tab_user .user-category-identity {
        display: flex;
        align-items: center;
        min-width: 0;
        gap: 11px;
      }

      #tab_user .user-category-avatar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 36px;
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: #eef6fb;
        color: #258fba;
      }

      #tab_user .user-category-identity > div {
        min-width: 0;
      }

      #tab_user .user-category-identity strong,
      #tab_user .user-category-identity small {
        display: block;
      }

      #tab_user .user-category-identity strong {
        overflow: hidden;
        color: #344256;
        font-size: 13px;
        font-weight: 600;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      #tab_user .user-category-identity small {
        margin-top: 2px;
        color: #8994a2;
        font-size: 10px;
      }

      #tab_user .user-category-metrics {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
      }

      #tab_user .user-category-metrics > span {
        padding: 6px 9px;
        border-radius: 6px;
        background: #f5f7fa;
        text-align: left;
      }

      #tab_user .user-category-metrics strong,
      #tab_user .user-category-metrics small {
        display: block;
      }

      #tab_user .user-category-metrics strong {
        color: #344256;
        font-size: 12px;
        font-weight: 700;
      }

      #tab_user .user-category-metrics small {
        color: #8994a2;
        font-size: 9px;
        text-transform: uppercase;
      }

      #tab_user .user-category-row-actions {
        display: flex;
        justify-content: flex-end;
        gap: 7px;
      }

      #tab_user .user-category-row-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 32px;
        padding: 0 10px;
        border: 1px solid #d5e6ee;
        border-radius: 6px;
        background: #fff;
        color: #248bb5;
        font-size: 10px;
        font-weight: 600;
        gap: 6px;
      }

      #tab_user .user-category-row-button:hover,
      #tab_user .user-category-row-button:focus {
        border-color: #aad6e8;
        background: #eef8fd;
      }

      #tab_user .user-category-state {
        padding: 34px 20px;
        color: #6f7c8c;
        text-align: center;
      }

      #tab_user .user-category-state span,
      #tab_user .user-category-state strong,
      #tab_user .user-category-state small {
        display: block;
      }

      #tab_user .user-category-state span {
        margin-bottom: 9px;
        color: #27a8d8;
        font-size: 18px;
      }

      #tab_user .user-category-state strong {
        margin-bottom: 4px;
        color: #425166;
        font-size: 13px;
      }

      #tab_user .user-category-state small {
        color: #8994a2;
        font-size: 11px;
      }

      #tab_user .user-category-state.is-error span {
        color: #d46b62;
      }

      @media (max-width: 900px) {
        #tab_user .user-category-row {
          grid-template-columns: minmax(0, 1fr) 150px;
        }

        #tab_user .user-category-row-actions {
          grid-column: 1 / -1;
          justify-content: flex-start;
          padding-left: 47px;
        }
      }

      @media (max-width: 767px) {
        #tab_user .user-account-panel {
          padding: 20px 15px;
        }

        #tab_user .user-account-header,
        #tab_user .user-category-header {
          display: block;
        }

        #tab_user .user-category-count {
          width: max-content;
          margin-top: 16px;
          text-align: left;
        }

        #tab_user .user-category-actions {
          flex-wrap: wrap;
          margin-top: 16px;
        }

        #tab_user .user-category-row {
          grid-template-columns: 1fr;
          gap: 12px;
        }

        #tab_user .user-category-row-actions {
          grid-column: auto;
          padding-left: 0;
        }

        #tab_user .user-category-metrics {
          max-width: 180px;
        }
      }

      #tab_active_sessions .active-session-panel {
        min-height: 560px;
        padding: 30px;
        background: #f7f9fc;
      }

      #tab_active_sessions .active-session-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        padding-bottom: 22px;
        margin-bottom: 18px;
        border-bottom: 1px solid #e3e8ef;
      }

      #tab_active_sessions .active-session-eyebrow {
        display: block;
        margin-bottom: 5px;
        color: #168fcb;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .11em;
        text-transform: uppercase;
      }

      #tab_active_sessions .active-session-title {
        margin: 0 0 7px;
        color: #1f2937;
        font-size: 24px;
        font-weight: 600;
        line-height: 1.25;
      }

      #tab_active_sessions .active-session-intro {
        max-width: 620px;
        margin: 0;
        color: #687386;
        font-size: 14px;
        line-height: 1.6;
      }

      #tab_active_sessions .active-session-actions {
        display: flex;
        align-items: stretch;
        flex: 0 0 auto;
        gap: 9px;
      }

      #tab_active_sessions .active-session-count {
        min-width: 96px;
        padding: 10px 14px;
        border: 1px solid #cfe8f6;
        border-radius: 7px;
        background: #eef8fd;
        text-align: right;
      }

      #tab_active_sessions .active-session-count span,
      #tab_active_sessions .active-session-count strong {
        display: block;
      }

      #tab_active_sessions .active-session-count span {
        margin-bottom: 2px;
        color: #627386;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: .06em;
        text-transform: uppercase;
      }

      #tab_active_sessions .active-session-count strong {
        color: #087eaf;
        font-size: 17px;
        font-weight: 700;
      }

      #tab_active_sessions .active-session-refresh {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        border: 1px solid #dce4ec;
        border-radius: 7px;
        background: #fff;
        color: #168fcb;
        font-size: 14px;
        transition: background .18s ease, border-color .18s ease;
      }

      #tab_active_sessions .active-session-refresh:hover,
      #tab_active_sessions .active-session-refresh:focus {
        border-color: #b9ddeb;
        background: #eef8fd;
        color: #087eaf;
      }

      #tab_active_sessions .active-session-card {
        overflow: hidden;
        border: 1px solid #e1e6ed;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 2px 7px rgba(31, 41, 55, .04);
      }

      #tab_active_sessions .active-session-card-heading {
        padding: 18px 20px 15px;
        border-bottom: 1px solid #e8ecf1;
      }

      #tab_active_sessions .active-session-card-heading h5 {
        margin: 0 0 4px;
        color: #29384b;
        font-size: 14px;
        font-weight: 600;
      }

      #tab_active_sessions .active-session-card-heading p {
        margin: 0;
        color: #7a8696;
        font-size: 12px;
      }

      #tab_active_sessions .active-session-table-wrap {
        width: 100%;
        overflow: visible;
      }

      #tab_active_sessions .active-session-table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
      }

      #tab_active_sessions .active-col-time { width: 22%; }
      #tab_active_sessions .active-col-user { width: 28%; }
      #tab_active_sessions .active-col-device { width: 34%; }
      #tab_active_sessions .active-col-status { width: 16%; }

      #tab_active_sessions .active-session-table thead th {
        padding: 13px 15px;
        border: 0;
        border-bottom: 1px solid #e8ecf1;
        background: #f8fafc;
        color: #657286;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .055em;
        line-height: 1.4;
        text-align: left;
        text-transform: uppercase;
        vertical-align: top;
      }

      #tab_active_sessions .active-session-table tbody td {
        padding: 16px 15px;
        overflow: hidden;
        border: 0;
        border-bottom: 1px solid #edf0f4;
        color: #596678;
        font-size: 12px;
        line-height: 1.5;
        text-align: left;
        vertical-align: top;
        white-space: nowrap;
      }

      #tab_active_sessions .active-session-table tbody tr:last-child td {
        border-bottom: 0;
      }

      #tab_active_sessions .active-session-table tbody tr:not(.active-session-state-row):hover td {
        background: #fbfdff;
      }

      #tab_active_sessions .active-session-cell {
        display: block;
        width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      #tab_active_sessions .active-session-cell i {
        width: 16px;
        margin-right: 5px;
        color: #8b98a8;
        text-align: center;
      }

      #tab_active_sessions .active-session-time,
      #tab_active_sessions .active-session-user {
        color: #405066;
        font-weight: 500;
      }

      #tab_active_sessions .active-session-status {
        display: inline-flex;
        align-items: center;
        max-width: 100%;
        padding: 4px 9px;
        overflow: hidden;
        border-radius: 20px;
        background: #eef2f6;
        color: #657286;
        font-size: 10px;
        font-weight: 700;
        gap: 5px;
        line-height: 1.3;
        text-overflow: ellipsis;
        text-transform: uppercase;
        white-space: nowrap;
      }

      #tab_active_sessions .active-session-status.is-current {
        background: #e7f7ee;
        color: #22844f;
      }

      #tab_active_sessions .active-session-status .fa-circle {
        font-size: 6px;
      }

      #tab_active_sessions .active-session-state-row td {
        padding: 34px 20px !important;
        background: #fff !important;
        color: #6f7c8c;
        text-align: center !important;
      }

      #tab_active_sessions .active-session-state-icon,
      #tab_active_sessions .active-session-state-row strong,
      #tab_active_sessions .active-session-state-row small {
        display: block;
      }

      #tab_active_sessions .active-session-state-icon {
        margin-bottom: 9px;
        color: #27a8d8;
        font-size: 18px;
      }

      #tab_active_sessions .active-session-state-row strong {
        margin-bottom: 4px;
        color: #425166;
        font-size: 13px;
        font-weight: 600;
      }

      #tab_active_sessions .active-session-state-row small {
        color: #8994a2;
        font-size: 11px;
      }

      #tab_active_sessions .active-session-state-row.is-error .active-session-state-icon {
        color: #d46b62;
      }

      @media (max-width: 767px) {
        #tab_active_sessions .active-session-panel {
          padding: 20px 15px;
        }

        #tab_active_sessions .active-session-header {
          display: block;
        }

        #tab_active_sessions .active-session-actions {
          width: max-content;
          margin-top: 16px;
        }

        #tab_active_sessions .active-session-count {
          text-align: left;
        }

        #tab_active_sessions .active-session-card-heading p {
          line-height: 1.5;
        }

        #tab_active_sessions .active-session-table,
        #tab_active_sessions .active-session-table tbody,
        #tab_active_sessions .active-session-table tr,
        #tab_active_sessions .active-session-table td {
          display: block;
          width: 100%;
        }

        #tab_active_sessions .active-session-table colgroup,
        #tab_active_sessions .active-session-table thead {
          display: none;
        }

        #tab_active_sessions .active-session-table tbody tr:not(.active-session-state-row) {
          padding: 10px 15px;
          border-bottom: 1px solid #e8ecf1;
        }

        #tab_active_sessions .active-session-table tbody tr:not(.active-session-state-row) td {
          display: grid;
          grid-template-columns: 112px minmax(0, 1fr);
          gap: 12px;
          padding: 8px 0;
          border: 0;
        }

        #tab_active_sessions .active-session-table tbody tr:not(.active-session-state-row) td::before {
          content: attr(data-label);
          color: #7b8797;
          font-size: 9px;
          font-weight: 700;
          letter-spacing: .05em;
          text-transform: uppercase;
        }
      }

      #tab_auditlog .audit-log-panel {
        min-height: 560px;
        padding: 30px;
        background: #f7f9fc;
      }

      #tab_auditlog .audit-log-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        padding-bottom: 22px;
        margin-bottom: 18px;
        border-bottom: 1px solid #e3e8ef;
      }

      #tab_auditlog .audit-log-eyebrow {
        display: block;
        margin-bottom: 5px;
        color: #168fcb;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .11em;
        text-transform: uppercase;
      }

      #tab_auditlog .audit-log-title {
        margin: 0 0 7px;
        color: #1f2937;
        font-size: 24px;
        font-weight: 600;
        line-height: 1.25;
      }

      #tab_auditlog .audit-log-intro {
        max-width: 620px;
        margin: 0;
        color: #687386;
        font-size: 14px;
        line-height: 1.6;
      }

      #tab_auditlog .audit-result-count {
        flex: 0 0 auto;
        min-width: 104px;
        padding: 10px 14px;
        border: 1px solid #cfe8f6;
        border-radius: 7px;
        background: #eef8fd;
        text-align: right;
      }

      #tab_auditlog .audit-result-count span,
      #tab_auditlog .audit-result-count strong {
        display: block;
      }

      #tab_auditlog .audit-result-count span {
        margin-bottom: 2px;
        color: #627386;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: .06em;
        text-transform: uppercase;
      }

      #tab_auditlog .audit-result-count strong {
        color: #087eaf;
        font-size: 17px;
        font-weight: 700;
      }

      #tab_auditlog .audit-filter-card,
      #tab_auditlog .audit-results-card {
        border: 1px solid #e1e6ed;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 2px 7px rgba(31, 41, 55, .04);
      }

      #tab_auditlog .audit-filter-card {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        padding: 18px 20px;
        margin-bottom: 18px;
      }

      #tab_auditlog .audit-filter-copy {
        display: flex;
        align-items: flex-start;
        min-width: 220px;
        gap: 13px;
      }

      #tab_auditlog .audit-filter-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 38px;
        width: 38px;
        height: 38px;
        border-radius: 8px;
        background: #eaf6fc;
        color: #149dce;
        font-size: 15px;
      }

      #tab_auditlog .audit-filter-copy label {
        display: block;
        margin: 0 0 3px;
        color: #29384b;
        font-size: 13px;
        font-weight: 600;
      }

      #tab_auditlog .audit-filter-copy p {
        margin: 0;
        color: #7a8696;
        font-size: 12px;
        line-height: 1.45;
      }

      #tab_auditlog .audit-filter-form {
        display: flex;
        align-items: flex-start;
        justify-content: flex-end;
        flex: 1 1 auto;
        gap: 10px;
        margin: 0;
      }

      #tab_auditlog .audit-date-input-wrap {
        position: relative;
        flex: 0 1 300px;
        min-width: 240px;
      }

      #tab_auditlog .audit-date-input-wrap > i {
        position: absolute;
        top: 50%;
        left: 13px;
        z-index: 2;
        color: #7d8998;
        transform: translateY(-50%);
        pointer-events: none;
      }

      #tab_auditlog #audit_search_daterange {
        width: 100%;
        height: 40px;
        padding: 8px 12px 8px 37px;
        border: 1px solid #dce2e9;
        border-radius: 6px;
        background: #fff;
        color: #344256;
        box-shadow: none;
      }

      #tab_auditlog #audit_search_daterange:focus {
        border-color: #58b9e4;
        box-shadow: 0 0 0 3px rgba(17, 168, 223, .11);
      }

      #tab_auditlog .audit-search-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 40px;
        flex: 0 0 40px;
        width: 40px;
        padding: 0;
        border: 1px solid #11a8df;
        border-radius: 6px;
        background: #11a8df;
        color: #fff;
        font-size: 12px;
        font-weight: 600;
        transition: background .18s ease, border-color .18s ease;
      }

      #tab_auditlog .audit-search-button:hover,
      #tab_auditlog .audit-search-button:focus {
        border-color: #0c91c2;
        background: #0c91c2;
        color: #fff;
      }

      #tab_auditlog .audit-results-card {
        overflow: hidden;
      }

      #tab_auditlog .audit-results-heading {
        padding: 18px 20px 15px;
        border-bottom: 1px solid #e8ecf1;
      }

      #tab_auditlog .audit-results-heading h5 {
        margin: 0 0 4px;
        color: #29384b;
        font-size: 14px;
        font-weight: 600;
      }

      #tab_auditlog .audit-results-heading p {
        margin: 0;
        color: #7a8696;
        font-size: 12px;
      }

      #tab_auditlog .audit-table-wrap {
        width: 100%;
        overflow: visible;
      }

      #tab_auditlog .audit-log-table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
      }

      #tab_auditlog .audit-col-datetime { width: 20%; }
      #tab_auditlog .audit-col-type { width: 18%; }
      #tab_auditlog .audit-col-details { width: 44%; }
      #tab_auditlog .audit-col-ip { width: 18%; }

      #tab_auditlog .audit-log-table thead th {
        padding: 13px 15px;
        border: 0;
        border-bottom: 1px solid #e8ecf1;
        background: #f8fafc;
        color: #657286;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .055em;
        line-height: 1.4;
        text-align: left;
        text-transform: uppercase;
        vertical-align: top;
      }

      #tab_auditlog .audit-log-table tbody td {
        padding: 16px 15px;
        border: 0;
        border-bottom: 1px solid #edf0f4;
        color: #596678;
        font-size: 12px;
        line-height: 1.5;
        text-align: left;
        vertical-align: top;
        overflow: hidden;
        white-space: nowrap;
      }

      #tab_auditlog .audit-log-table tbody tr:last-child td {
        border-bottom: 0;
      }

      #tab_auditlog .audit-log-table tbody tr:not(.audit-state-row):hover td {
        background: #fbfdff;
      }

      #tab_auditlog .audit-log-time {
        color: #405066;
        font-weight: 500;
      }

      #tab_auditlog .audit-cell-text {
        display: block;
        width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      #tab_auditlog .audit-type-badge {
        width: fit-content;
        max-width: 100%;
        padding: 4px 8px;
        border-radius: 4px;
        background: #edf6fb;
        color: #197fa8;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .025em;
        line-height: 1.35;
        text-transform: uppercase;
      }

      #tab_auditlog .audit-log-details {
        display: block;
        color: #536174;
      }

      #tab_auditlog .audit-ip-address {
        padding: 0;
        background: transparent;
        color: #3f5066;
        font-family: Consolas, Monaco, monospace;
        font-size: 11px;
      }

      #tab_auditlog .audit-state-row td {
        padding: 34px 20px !important;
        background: #fff !important;
        color: #6f7c8c;
        text-align: center !important;
      }

      #tab_auditlog .audit-state-icon,
      #tab_auditlog .audit-state-row strong,
      #tab_auditlog .audit-state-row small {
        display: block;
      }

      #tab_auditlog .audit-state-icon {
        margin-bottom: 9px;
        color: #27a8d8;
        font-size: 18px;
      }

      #tab_auditlog .audit-state-row strong {
        margin-bottom: 4px;
        color: #425166;
        font-size: 13px;
        font-weight: 600;
      }

      #tab_auditlog .audit-state-row small {
        color: #8994a2;
        font-size: 11px;
      }

      #tab_auditlog .audit-state-row.is-error .audit-state-icon {
        color: #d46b62;
      }

      @media (max-width: 767px) {
        #tab_auditlog .audit-log-panel {
          padding: 20px 15px;
        }

        #tab_auditlog .audit-log-header,
        #tab_auditlog .audit-filter-card {
          display: block;
        }

        #tab_auditlog .audit-result-count {
          width: max-content;
          margin-top: 16px;
          text-align: left;
        }

        #tab_auditlog .audit-filter-form {
          display: block;
          margin-top: 16px;
        }

        #tab_auditlog .audit-date-input-wrap {
          width: 100%;
          min-width: 0;
          margin-bottom: 10px;
        }

        #tab_auditlog .audit-search-button {
          width: 40px;
        }

        #tab_auditlog .audit-results-heading p {
          line-height: 1.5;
        }

        #tab_auditlog .audit-log-table,
        #tab_auditlog .audit-log-table tbody,
        #tab_auditlog .audit-log-table tr,
        #tab_auditlog .audit-log-table td {
          display: block;
          width: 100%;
        }

        #tab_auditlog .audit-log-table colgroup,
        #tab_auditlog .audit-log-table thead {
          display: none;
        }

        #tab_auditlog .audit-log-table tbody tr:not(.audit-state-row) {
          padding: 10px 15px;
          border-bottom: 1px solid #e8ecf1;
        }

        #tab_auditlog .audit-log-table tbody tr:not(.audit-state-row) td {
          display: grid;
          grid-template-columns: 112px minmax(0, 1fr);
          gap: 12px;
          padding: 8px 0;
          border: 0;
          white-space: nowrap;
        }

        #tab_auditlog .audit-log-table tbody tr:not(.audit-state-row) td::before {
          content: attr(data-label);
          color: #7b8797;
          font-size: 9px;
          font-weight: 700;
          letter-spacing: .05em;
          text-transform: uppercase;
        }
      }

      #tab_synclog .sync-log-panel {
        background: #f7f9fc;
        padding: 30px;
        min-height: 560px;
      }

      #tab_synclog .sync-log-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        padding-bottom: 22px;
        margin-bottom: 18px;
        border-bottom: 1px solid #e3e8ef;
      }

      #tab_synclog .sync-log-eyebrow {
        display: block;
        margin-bottom: 5px;
        color: #168fcb;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .11em;
        text-transform: uppercase;
      }

      #tab_synclog .sync-log-title {
        margin: 0 0 8px;
        color: #1f2937;
        font-size: 24px;
        font-weight: 600;
        line-height: 1.25;
      }

      #tab_synclog .sync-log-intro {
        max-width: 640px;
        margin: 0;
        color: #687386;
        font-size: 14px;
        line-height: 1.6;
      }

      #tab_synclog .sync-log-refresh,
      #tab_synclog .sync-log-back {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.2;
        transition: background .18s ease, border-color .18s ease, color .18s ease;
      }

      #tab_synclog .sync-log-refresh {
        flex: 0 0 auto;
        min-height: 40px;
        padding: 9px 17px;
        border: 1px solid #d5dde7;
        background: #fff;
        color: #415066;
      }

      #tab_synclog .sync-log-refresh:hover,
      #tab_synclog .sync-log-refresh:focus {
        border-color: #54b9df;
        background: #eef8fd;
        color: #087eaf;
        outline: none;
      }

      #tab_synclog .sync-log-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 18px;
      }

      #tab_synclog .sync-summary-item {
        padding: 15px 18px;
        border: 1px solid #e1e6ed;
        border-radius: 7px;
        background: #fff;
        box-shadow: 0 2px 7px rgba(31, 41, 55, .03);
      }

      #tab_synclog .sync-summary-item span,
      #tab_synclog .sync-summary-item strong {
        display: block;
      }

      #tab_synclog .sync-summary-item span {
        margin-bottom: 5px;
        color: #7a8797;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
      }

      #tab_synclog .sync-summary-item strong {
        color: #263447;
        font-size: 21px;
        font-weight: 700;
        line-height: 1.2;
      }

      #tab_synclog .sync-summary-item:first-child {
        border-top: 3px solid #11a8df;
      }

      #tab_synclog .sync-summary-item:nth-child(2) {
        border-top: 3px solid #34a853;
      }

      #tab_synclog .sync-summary-item:nth-child(3) {
        border-top: 3px solid #f2a93b;
      }

      #tab_synclog .sync-log-table-card {
        overflow: hidden;
        border: 1px solid #e1e6ed;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 2px 7px rgba(31, 41, 55, .04);
      }

      #tab_synclog .sync-table-wrap {
        width: 100%;
        overflow: visible;
      }

      #tab_synclog .sync-log-table {
        width: 100%;
        table-layout: fixed;
      }

      #tab_synclog .sync-log-table thead th {
        padding: 14px 13px;
        border-bottom: 1px solid #dde3ea;
        background: #f8fafc;
        color: #667386;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .045em;
        line-height: 1.35;
        text-transform: uppercase;
        text-align: left !important;
        vertical-align: top;
        white-space: normal;
      }

      #tab_synclog .sync-log-table tbody td {
        padding: 16px 13px;
        border-top: 1px solid #edf0f4;
        color: #5f6c7c;
        font-size: 12px;
        line-height: 1.45;
        text-align: left !important;
        vertical-align: top;
        overflow-wrap: anywhere;
      }

      #tab_synclog .sync-log-table:not(.sync-detail-table) th:nth-child(1) { width: 9%; }
      #tab_synclog .sync-log-table:not(.sync-detail-table) th:nth-child(2) { width: 20%; }
      #tab_synclog .sync-log-table:not(.sync-detail-table) th:nth-child(3) { width: 16%; }
      #tab_synclog .sync-log-table:not(.sync-detail-table) th:nth-child(4) { width: 34%; }
      #tab_synclog .sync-log-table:not(.sync-detail-table) th:nth-child(5) { width: 14%; }
      #tab_synclog .sync-log-table:not(.sync-detail-table) th:nth-child(6) { width: 7%; }

      #tab_synclog .sync-log-table tbody tr:first-child td {
        border-top: 0;
      }

      #tab_synclog .sync-log-table tbody tr:hover td {
        background: #fbfdfe;
      }

      #tab_synclog .sync-session-id {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 42px;
        padding: 5px 9px;
        border-radius: 5px;
        background: #eef8fd;
        color: #087eaf;
        font-weight: 700;
      }

      #tab_synclog .sync-session-time,
      #tab_synclog .sync-session-trigger {
        display: block;
        color: #4f5e70;
      }

      #tab_synclog .sync-session-time {
        min-width: 155px;
      }

      #tab_synclog .sync-metric-value {
        display: inline-block;
        min-width: 24px;
        color: #3e4b5d;
        font-weight: 600;
        text-align: center;
      }

      #tab_synclog .sync-change-metrics {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 6px;
      }

      #tab_synclog .sync-change-metrics > span {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 5px;
        min-width: 0;
        padding: 6px 7px;
        border-radius: 5px;
        background: #f5f7f9;
      }

      #tab_synclog .sync-change-metrics small {
        overflow: hidden;
        color: #7b8796;
        font-size: 8px;
        font-weight: 700;
        letter-spacing: .025em;
        line-height: 1.25;
        text-overflow: ellipsis;
        text-transform: uppercase;
        white-space: nowrap;
      }

      #tab_synclog .sync-change-metrics strong {
        flex: 0 0 auto;
        color: #354357;
        font-size: 11px;
        font-weight: 700;
      }

      #tab_synclog .sync-status-badge,
      #tab_synclog .sync-action-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 78px;
        padding: 5px 9px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 700;
        line-height: 1.2;
        white-space: nowrap;
      }

      #tab_synclog .sync-status-progress,
      #tab_synclog .sync-action-update {
        background: #fff5df;
        color: #9a6710;
      }

      #tab_synclog .sync-status-temp,
      #tab_synclog .sync-action-reactivate {
        background: #eaf7fc;
        color: #087eaf;
      }

      #tab_synclog .sync-status-complete,
      #tab_synclog .sync-action-new {
        background: #eaf7ee;
        color: #247a3d;
      }

      #tab_synclog .sync-status-empty,
      #tab_synclog .sync-status-unchanged,
      #tab_synclog .sync-status-unknown {
        background: #eef1f4;
        color: #687486;
      }

      #tab_synclog .sync-action-deactivate {
        background: #fceced;
        color: #ad3540;
      }

      #tab_synclog .sync-view-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        padding: 0;
        border: 1px solid #b8dff0;
        border-radius: 5px;
        background: #fff;
        color: #087eaf;
        font-size: 13px;
      }

      #tab_synclog .sync-view-button:hover,
      #tab_synclog .sync-view-button:focus {
        border-color: #159bce;
        background: #eef8fd;
        color: #087eaf;
        outline: none;
      }

      #tab_synclog .sync-empty-row td {
        padding: 36px 20px !important;
        color: #8a95a3 !important;
        text-align: center;
      }

      #tab_synclog .sync-empty-row i {
        margin-right: 6px;
        color: #159bce;
      }

      #tab_synclog .sync-log-pagination {
        min-height: 16px;
        padding: 12px 16px;
        border-top: 1px solid #edf0f4;
        background: #fbfcfd;
      }

      #tab_synclog .sync-log-pagination:empty {
        display: none;
      }

      #tab_synclog .sync-log-pagination .pagination {
        margin: 0;
      }

      #tab_synclog .sync-detail-header {
        display: block;
      }

      #tab_synclog .sync-log-back {
        margin-bottom: 20px;
        padding: 7px 11px;
        border: 1px solid #d7dee7;
        background: #fff;
        color: #556477;
      }

      #tab_synclog .sync-log-back:hover,
      #tab_synclog .sync-log-back:focus {
        border-color: #b8dff0;
        background: #eef8fd;
        color: #087eaf;
        outline: none;
      }

      #tab_synclog .sync-detail-table th:nth-child(1) { width: 5%; }
      #tab_synclog .sync-detail-table th:nth-child(2) { width: 14%; }
      #tab_synclog .sync-detail-table th:nth-child(3) { width: 13%; }
      #tab_synclog .sync-detail-table th:nth-child(4) { width: 49%; }
      #tab_synclog .sync-detail-table th:nth-child(5) { width: 19%; }

      #tab_synclog .sync-row-number,
      #tab_synclog .sync-detail-user {
        color: #3f4d60;
        font-weight: 600;
      }

      #tab_synclog .sync-detail-data {
        display: grid;
        gap: 8px;
      }

      #tab_synclog .sync-detail-data > div {
        min-width: 0;
        padding: 8px 9px;
        border-left: 2px solid #d8e7ef;
        background: #f8fafc;
      }

      #tab_synclog .sync-detail-data span {
        display: block;
        margin-bottom: 3px;
        color: #7a8797;
        font-size: 8px;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
      }

      #tab_synclog .sync-detail-data p {
        margin: 0;
        color: #536174;
        font-size: 10px;
        line-height: 1.45;
        overflow-wrap: anywhere;
      }

      @media (max-width: 767px) {
        #tab_synclog .sync-log-panel {
          padding: 20px 15px;
        }

        #tab_synclog .sync-log-header {
          display: block;
        }

        #tab_synclog .sync-log-refresh {
          width: 100%;
          margin-top: 16px;
        }

        #tab_synclog .sync-log-summary {
          grid-template-columns: 1fr;
        }

        #tab_synclog .sync-summary-item {
          display: flex;
          align-items: center;
          justify-content: space-between;
        }

        #tab_synclog .sync-summary-item span {
          margin-bottom: 0;
        }

        #tab_synclog .sync-log-table,
        #tab_synclog .sync-log-table tbody,
        #tab_synclog .sync-log-table tr,
        #tab_synclog .sync-log-table td {
          display: block;
          width: 100%;
        }

        #tab_synclog .sync-log-table thead {
          display: none;
        }

        #tab_synclog .sync-log-table tbody {
          padding: 10px;
          background: #f7f9fc;
        }

        #tab_synclog .sync-log-table tbody tr {
          margin-bottom: 10px;
          overflow: hidden;
          border: 1px solid #e1e6ed;
          border-radius: 7px;
          background: #fff;
        }

        #tab_synclog .sync-log-table tbody tr:last-child {
          margin-bottom: 0;
        }

        #tab_synclog .sync-log-table tbody td {
          position: relative;
          min-height: 42px;
          padding: 11px 12px 11px 112px;
          border-top: 1px solid #edf0f4;
        }

        #tab_synclog .sync-log-table tbody td::before {
          content: attr(data-label);
          position: absolute;
          top: 12px;
          left: 12px;
          width: 88px;
          color: #7a8797;
          font-size: 9px;
          font-weight: 700;
          letter-spacing: .04em;
          line-height: 1.35;
          text-transform: uppercase;
        }

        #tab_synclog .sync-log-table .sync-empty-row td {
          padding: 28px 16px !important;
        }

        #tab_synclog .sync-log-table .sync-empty-row td::before {
          display: none;
        }

        #tab_synclog .sync-change-metrics {
          max-width: 280px;
        }
      }

      #tab_settings .sso-config-panel {
        background: #f7f9fc;
        padding: 30px;
        min-height: 560px;
      }

      #tab_settings .sso-config-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        padding-bottom: 22px;
        margin-bottom: 20px;
        border-bottom: 1px solid #e3e8ef;
      }

      #tab_settings .sso-config-eyebrow {
        display: block;
        margin-bottom: 5px;
        color: #168fcb;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .11em;
        text-transform: uppercase;
      }

      #tab_settings .sso-config-title {
        margin: 0 0 8px;
        color: #1f2937;
        font-size: 24px;
        font-weight: 600;
        line-height: 1.25;
      }

      #tab_settings .sso-config-intro {
        max-width: 610px;
        margin: 0;
        color: #687386;
        font-size: 14px;
        line-height: 1.6;
      }

      #tab_settings .sso-config-save {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        flex: 0 0 auto;
        min-height: 40px;
        padding: 9px 18px;
        border: 1px solid #0d95ca;
        border-radius: 6px;
        background: #11a8df;
        color: #fff;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.2;
        transition: background .18s ease, border-color .18s ease, box-shadow .18s ease;
      }

      #tab_settings .sso-config-save:hover,
      #tab_settings .sso-config-save:focus {
        border-color: #087eaf;
        background: #087eaf;
        color: #fff;
        box-shadow: 0 3px 9px rgba(8, 126, 175, .18);
        outline: none;
      }

      #tab_settings .sso-config-body {
        overflow: hidden;
        border: 1px solid #e1e6ed;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 2px 7px rgba(31, 41, 55, .04);
      }

      #tab_settings .sso-config-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 260px;
        align-items: center;
        gap: 30px;
        min-height: 112px;
        padding: 22px 26px;
        border-bottom: 1px solid #edf0f4;
      }

      #tab_settings .sso-config-copy {
        display: flex;
        align-items: flex-start;
        gap: 16px;
      }

      #tab_settings .sso-config-index {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 34px;
        width: 34px;
        height: 34px;
        border-radius: 7px;
        background: #eef8fd;
        color: #138dbd;
        font-size: 11px;
        font-weight: 700;
      }

      #tab_settings .sso-config-copy label {
        display: block;
        margin: 1px 0 5px;
        color: #273548;
        font-size: 14px;
        font-weight: 600;
        line-height: 1.35;
      }

      #tab_settings .sso-config-copy p {
        max-width: 540px;
        margin: 0;
        color: #788495;
        font-size: 12px;
        line-height: 1.55;
      }

      #tab_settings .sso-config-control {
        justify-self: stretch;
      }

      #tab_settings .sso-config-select-wrap {
        position: relative;
      }

      #tab_settings .sso-config-select-wrap .form-control {
        height: 42px;
        border-color: #dce2e9;
        border-radius: 6px;
        background-color: #fbfcfd;
        color: #364356;
        font-size: 13px;
        box-shadow: none;
      }

      #tab_settings .sso-config-select-wrap .form-control:focus {
        border-color: #54b9df;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(17, 168, 223, .08);
      }

      #tab_settings .sso-config-switch {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 11px;
        color: #748092;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
      }

      #tab_settings .sso-config-note {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 15px 26px;
        background: #fbfcfd;
        color: #657386;
      }

      #tab_settings .sso-config-note i {
        margin-top: 2px;
        color: #159bce;
      }

      #tab_settings .sso-config-note p {
        margin: 0;
        font-size: 11px;
        line-height: 1.55;
      }

      @media (max-width: 767px) {
        #tab_settings .sso-config-panel {
          padding: 20px 15px;
        }

        #tab_settings .sso-config-header {
          display: block;
        }

        #tab_settings .sso-config-save {
          width: 100%;
          margin-top: 16px;
        }

        #tab_settings .sso-config-row {
          grid-template-columns: 1fr;
          gap: 18px;
          padding: 20px;
        }

        #tab_settings .sso-config-control {
          justify-self: stretch;
        }

        #tab_settings .sso-config-switch {
          justify-content: flex-start;
          padding-left: 50px;
        }

        #tab_settings .sso-config-note {
          padding: 15px 20px;
        }
      }

      #tab_versioning .version-release-panel {
        background: #f7f9fc;
        padding: 30px;
        min-height: 560px;
      }

      #tab_versioning .version-release-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        padding-bottom: 22px;
        margin-bottom: 20px;
        border-bottom: 1px solid #e3e8ef;
      }

      #tab_versioning .version-release-eyebrow {
        display: block;
        margin-bottom: 5px;
        color: #168fcb;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .11em;
        text-transform: uppercase;
      }

      #tab_versioning .version-release-title {
        margin: 0 0 8px;
        color: #1f2937;
        font-size: 24px;
        font-weight: 600;
        line-height: 1.25;
      }

      #tab_versioning .version-release-intro {
        max-width: 620px;
        margin: 0;
        color: #687386;
        font-size: 14px;
        line-height: 1.6;
      }

      #tab_versioning .version-current-release {
        flex: 0 0 auto;
        min-width: 132px;
        padding: 10px 14px;
        border: 1px solid #cfe8f6;
        border-radius: 7px;
        background: #eef8fd;
        text-align: right;
      }

      #tab_versioning .version-current-release span,
      #tab_versioning .version-current-release strong {
        display: block;
      }

      #tab_versioning .version-current-release span {
        margin-bottom: 2px;
        color: #627386;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: .06em;
        text-transform: uppercase;
      }

      #tab_versioning .version-current-release strong {
        color: #087eaf;
        font-size: 17px;
        font-weight: 700;
      }

      #tab_versioning .version-release-card {
        display: grid;
        grid-template-columns: 150px minmax(0, 1fr);
        margin-bottom: 14px;
        overflow: hidden;
        border: 1px solid #e1e6ed;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 2px 7px rgba(31, 41, 55, .04);
      }

      #tab_versioning .version-release-card.is-current {
        border-left: 4px solid #11a8df;
      }

      #tab_versioning .version-release-meta {
        padding: 22px 20px;
        border-right: 1px solid #edf0f4;
        background: #fbfcfd;
      }

      #tab_versioning .version-latest-label {
        display: inline-block;
        margin-bottom: 12px;
        padding: 4px 9px;
        border-radius: 20px;
        background: #11a8df;
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .05em;
        line-height: 1.2;
        text-transform: uppercase;
      }

      #tab_versioning .version-number,
      #tab_versioning .version-release-date {
        display: block;
      }

      #tab_versioning .version-number {
        margin-bottom: 7px;
        color: #263447;
        font-size: 20px;
        font-weight: 700;
        line-height: 1.2;
      }

      #tab_versioning .version-release-date {
        color: #7b8797;
        font-size: 12px;
        font-weight: 500;
      }

      #tab_versioning .version-release-content {
        padding: 22px 26px 24px;
      }

      #tab_versioning .version-release-content h5 {
        margin: 0 0 13px;
        color: #273548;
        font-size: 15px;
        font-weight: 600;
      }

      #tab_versioning .version-change-list {
        margin: 0;
        padding-left: 20px;
        color: #5e6a79;
      }

      #tab_versioning .version-change-list li {
        padding-left: 5px;
        margin-bottom: 8px;
        line-height: 1.55;
      }

      #tab_versioning .version-change-list li:last-child {
        margin-bottom: 0;
      }

      #tab_versioning .version-change-list li::marker {
        color: #139dce;
        font-weight: 700;
      }

      #tab_versioning .version-change-list b {
        color: #37465a;
        font-weight: 600;
      }

      @media (max-width: 767px) {
        #tab_versioning .version-release-panel {
          padding: 20px 15px;
        }

        #tab_versioning .version-release-header {
          display: block;
        }

        #tab_versioning .version-current-release {
          width: max-content;
          margin-top: 16px;
          text-align: left;
        }

        #tab_versioning .version-release-card {
          grid-template-columns: 1fr;
        }

        #tab_versioning .version-release-meta {
          padding: 16px 18px;
          border-right: 0;
          border-bottom: 1px solid #edf0f4;
        }

        #tab_versioning .version-latest-label,
        #tab_versioning .version-number,
        #tab_versioning .version-release-date {
          display: inline-block;
          margin: 0 10px 0 0;
          vertical-align: middle;
        }

        #tab_versioning .version-release-content {
          padding: 19px 20px 22px;
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
