<?php
   require_once __DIR__ . '/../lib/session_security.php';
   oneid_start_secure_session();
   require_once __DIR__ . '/../lib/config.php';
   //return;
   require_once __DIR__ . '/../lib/SSO_IDP_INC.php';
   require_once __DIR__ . '/../lib/request_security.php';
   oneid_require_admin_page();
   oneid_require_active_sso_page($operation);
   oneid_require_admin_step_up($operation, 'ADMIN_ACCESS', false);
   
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
      <!-- Bootstrap Daterangepicker CSS -->
      <link href="../vendors/bower_components/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet" type="text/css"/>
      <link href="../assetsM/css/sweetalert.css" rel="stylesheet" type="text/css">
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
                                                <label class="control-label mr-10" for="modal_user_profile_category">Category</label>
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
                                                   <label class="control-label mb-10" for="add_new_app_icon">App Icon (optional)</label>
                                                   <div class="mt-1">
                                                      <input type="file" id="add_new_app_icon" name="add_new_app_icon" class="dropify" data-default-file="../img/thumb-1.jpg" data-height="100" accept="image/jpeg,image/png,image/gif,image/webp" />
                                                      <small class="text-muted">JPEG, PNG, GIF or WebP, maximum 5 MB and 4096×4096. The server converts it to a static 256×256 PNG.</small>
                                                   </div>  
                                                </div> 


                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="add_new_app_url">Url</label>
                                                   <input type="text" class="form-control" id="add_new_app_url" name="add_new_app_url" placeholder="Url of the app" required="">
                                                   <i style="font-style: italic;"><small class="text-muted mb-10">Example: https://domain.com.my or https://subdomain.domain.com.my</small></i>
                                                </div>
                                                <div class="form-group mb-10">
											<label class="control-label mb-10 text-left" for="add_new_app_category">App Category</label>
													<select class="form-control" id="add_new_app_category" name="add_new_app_category">
													</select>
												</div>
                                                <div class="checkbox checkbox-primary">
														<input id="add_new_app_sso_checkbox" name="add_new_app_sso_checkbox" type="checkbox">
												<label for="add_new_app_sso_checkbox">
													Direct link only (this app does not support OneID SSO)
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
                        <button type="submit" class="btn btn-primary waves-effect" id="btn_add_app_submit"><span class="submit-label">Add App</span></button>
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

         <div id="modal_manage_webapp_categories" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="aria_modal_manage_webapp_categories" aria-hidden="true">
            <div class="modal-dialog">
               <div class="modal-content">
                  <div class="modal-header">
                     <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                     <h5 class="modal-title" id="aria_modal_manage_webapp_categories">Manage Application Categories</h5>
                  </div>
                  <div class="modal-body">
                     <p class="category-manage-intro">Kategori hanya boleh dipadam apabila tiada aplikasi aktif atau inactive masih assigned kepadanya.</p>
                     <div id="category_manage_loading" class="category-manage-state">
                        <i class="fa fa-circle-o-notch fa-spin" aria-hidden="true"></i>
                        <span>Loading categories</span>
                     </div>
                     <div id="category_manage_list" class="category-manage-list" aria-live="polite"></div>
                  </div>
                  <div class="modal-footer">
                     <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Close</button>
                  </div>
               </div>
            </div>
         </div>

         <div id="modal_edit_webapp_category" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="aria_modal_edit_webapp_category" aria-hidden="true">
            <div class="modal-dialog">
               <div class="modal-content">
                  <div class="modal-header">
                     <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                     <h5 class="modal-title" id="aria_modal_edit_webapp_category">Edit Application Category</h5>
                  </div>
                  <form id="form_edit_webapp_category">
                     <div class="modal-body">
                        <input type="hidden" id="edit_webapp_category_id" name="app_category_id">
                        <div class="form-group">
                           <label class="control-label mb-10" for="edit_webapp_category_name">Category title</label>
                           <input type="text" class="form-control" id="edit_webapp_category_name" name="app_category_name" maxlength="100" autocomplete="off" required>
                        </div>
                     </div>
                     <div class="modal-footer">
                        <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary waves-effect" id="btn_save_webapp_category">Save changes</button>
                     </div>
                  </form>
               </div>
            </div>
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
                                                   <label class="control-label mb-10" for="edit_new_app_icon">Replace App Icon (optional)</label>
                                                   <div class="mt-1">
                                                      <input type="file" id="edit_new_app_icon" name="edit_new_app_icon" class="dropify" data-default-file="../img/thumb-1.jpg" data-height="100" accept="image/jpeg,image/png,image/gif,image/webp" />
                                                      <input type="hidden" name="edit_existing_app_icon" id="edit_existing_app_icon" value="" />
                                                      <small class="text-muted">Leave empty to retain the current icon. Maximum 5 MB and 4096×4096; converted to a static 256×256 PNG.</small>
                                                   </div>  
                                                </div> 
                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="edit_app_url">Url</label>
                                                   <input type="text" class="form-control" id="edit_app_url" name="edit_app_url" placeholder="Url of the app">
                                                </div>
                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="edit_app_code">Site API Code</label>
                                                   <span class="input-group-btn">
                                                   <div class="input-group mb-15"> <span class="input-group-btn">
                                                      <button type="button" class="btn  btn-primary" onclick="copyToClipboard('edit_app_code');"><i class="fa fa-copy"></i> Copy</button>
                                                      </span>
                                                      <input type="text" id="edit_app_code" class="form-control" disabled="">
                                                   </div>
                                                </div>
                                                <div class="form-group mb-10">
											<label class="control-label mb-10 text-left" for="edit_app_category">App Category</label>
													<select class="form-control" id="edit_app_category" name="edit_app_category">
													</select>
												</div>
                                                <div class="checkbox checkbox-primary">
														<input id="app_info_sso_checkbox" type="checkbox" name="app_info_sso_checkbox">
												<label for="app_info_sso_checkbox">
													Direct link only (this app does not support OneID SSO)
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
                        <button type="submit" class="btn btn-primary waves-effect" id="btn_edit_app_submit"><span class="submit-label">Save Changes</span></button>
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
            <div class="modal-dialog modal-md oneid-add-user-dialog" >
               <div class="modal-content oneid-sync-parent-modal">
                  <div class="oneid-sync-parent-header">
                     <div class="oneid-sync-parent-heading">
                        <span class="oneid-sync-parent-heading-icon"><i class="fa fa-refresh"></i></span>
                        <div>
                           <h5 id="aria_modal_open_add_user_option">Sinkronisasi Pengguna</h5>
                           <p>Semak dan selaraskan akaun OneID mengikut sumber data.</p>
                        </div>
                     </div>
                     <button type="button" class="close oneid-sync-parent-close" data-dismiss="modal" aria-label="Tutup">×</button>
                  </div>
                  <div class="modal-body oneid-sync-parent-body">
                     <div class="oneid-sync-menu-section">
                        <span class="oneid-sync-menu-section-label">Ringkasan</span>
                        <button id="btn_external_summary" class="btn btn-block oneid-sync-choice oneid-sync-menu-card oneid-sync-menu-summary" type="button" onclick="preview_external_sync_view('SUMMARY');">
                           <span class="oneid-sync-menu-icon"><i class="fa fa-dashboard"></i></span>
                           <span class="oneid-sync-menu-copy">
                              <strong>Ringkasan Sinkronisasi Pengguna</strong>
                              <small>Lihat status dan tindakan bagi semua sumber.</small>
                           </span>
                           <span id="external_notice_summary" class="external-action-notice" style="display:none"></span>
                           <i class="fa fa-chevron-right oneid-sync-menu-arrow"></i>
                        </button>
                        <p id="sync_status_msg" class="text-muted text-center mt-10" style="display:none;"></p>
                     </div>

                     <div class="oneid-sync-menu-section">
                        <span class="oneid-sync-menu-section-label">Sinkronisasi mengikut sumber</span>
                        <button class="btn btn-block oneid-sync-choice oneid-sync-menu-card oneid-sync-menu-source external-source-preview-button" type="button" onclick="pick_preview_sync_user('STAFF_HR');">
                           <span class="oneid-sync-menu-icon"><i class="fa fa-briefcase"></i></span>
                           <span class="oneid-sync-menu-copy">
                              <strong>Sinkronisasi Pengguna Staf</strong>
                              <small>Semak perubahan daripada sumber data staf.</small>
                           </span>
                           <span id="external_notice_staff" class="external-action-notice" style="display:none"></span>
                           <i class="fa fa-chevron-right oneid-sync-menu-arrow"></i>
                        </button>
                        <button class="btn btn-block oneid-sync-choice oneid-sync-menu-card oneid-sync-menu-source external-source-preview-button" type="button" onclick="pick_preview_sync_user('STUDENT_UG');">
                           <span class="oneid-sync-menu-icon"><i class="fa fa-graduation-cap"></i></span>
                           <span class="oneid-sync-menu-copy">
                              <strong>Sinkronisasi Pelajar Prasiswazah</strong>
                              <small>Semak perubahan daripada sumber pelajar prasiswazah.</small>
                           </span>
                           <span id="external_notice_ug" class="external-action-notice" style="display:none"></span>
                           <i class="fa fa-chevron-right oneid-sync-menu-arrow"></i>
                        </button>
                        <button class="btn btn-block oneid-sync-choice oneid-sync-menu-card oneid-sync-menu-source external-source-preview-button" type="button" onclick="pick_preview_sync_user('STUDENT_ODL_PG');">
                           <span class="oneid-sync-menu-icon"><i class="fa fa-refresh"></i></span>
                           <span class="oneid-sync-menu-copy">
                              <strong>Sinkronisasi Pelajar ODL</strong>
                              <small>Semak perubahan daripada sumber pelajar ODL.</small>
                           </span>
                           <span id="external_notice_odl" class="external-action-notice" style="display:none"></span>
                           <i class="fa fa-chevron-right oneid-sync-menu-arrow"></i>
                        </button>
                     </div>

                     <div class="oneid-sync-menu-section oneid-sync-menu-section-last">
                        <span class="oneid-sync-menu-section-label">Tindakan manual</span>
                        <button class="btn btn-block oneid-sync-menu-card oneid-sync-menu-manual" type="button" onclick="pick_add_single_user();">
                           <span class="oneid-sync-menu-icon"><i class="fa fa-user-plus"></i></span>
                           <span class="oneid-sync-menu-copy">
                              <strong>Tambah Pengguna Secara Manual</strong>
                              <small>Daftar satu akaun yang tiada dalam sumber sinkronisasi.</small>
                           </span>
                           <i class="fa fa-chevron-right oneid-sync-menu-arrow"></i>
                        </button>
                     </div>
                  </div>
               </div>
               <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
         </div>
         <div id="modal_add_new_single_user" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="aria_modal_add_new_single_user" aria-hidden="true">
            <div class="modal-dialog modal-lg oneid-sync-preview-dialog">
               <div class="modal-content oneid-sync-child-modal">
                  <div class="modal-header oneid-sync-child-header">
                     <div class="oneid-sync-child-heading">
                        <span class="oneid-sync-child-heading-icon"><i class="fa fa-refresh"></i></span>
                        <div>
                           <h5 class="modal-title" id="aria_modal_add_new_single_user">Semakan Sinkronisasi Pengguna</h5>
                           <p>Semak semua perubahan dengan teliti sebelum melaksanakan sinkronisasi.</p>
                        </div>
                     </div>
                     <button type="button" class="close oneid-sync-child-close oneid-return-add-user-options" data-dismiss="modal" aria-hidden="true">×</button>
                  </div>
                  <form id="form_add_new_category">
                     <div class="modal-body oneid-sync-child-body">
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
                                                      <div class="progress-bar progress-bar-primary active progress-bar-striped" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%" role="progressbar"> Sedang menyediakan semakan... </div>
                                                   </div>
                                                   <div id="sync_result_div">
                                                      <div id="sync_admin_summary" class="alert alert-info oneid-sync-admin-summary" role="status"></div>
                                                      <h6 class="mb-1"><i></i> Perubahan yang dikenal pasti (belum dilaksanakan):</h6>
                                                      <div class="sync-preview-table-wrap">
                                                      <table class="table table-borderless sync-preview-table">
                                                         <tbody>
                                                            <tr>
                                                               <td>Jumlah rekod daripada sumber:</td>
                                                               <td><span id="sync_preview_source_rows"></span></td>
                                                            </tr>
                                                            <tr>
                                                               <td>Pengguna baharu:</td>
                                                               <td><span id="sync_preview_new"></span></td>
                                                            </tr>
                                                            <tr>
                                                               <td>Maklumat perlu dikemas kini:</td>
                                                               <td><span id="sync_preview_update"></span></td>
                                                            </tr>
                                                            <tr>
                                                               <td>Akaun perlu dinyahaktifkan:</td>
                                                               <td><span id="sync_preview_deactivate"></span></td>
                                                            </tr>
                                                            <tr>
                                                               <td>Akaun perlu diaktifkan semula:</td>
                                                               <td><span id="sync_preview_reactivate"></span></td>
                                                            </tr>
                                                            <tr>
                                                               <td>Akaun manual yang dilindungi:</td>
                                                               <td><span id="sync_preview_protected_manual"></span></td>
                                                            </tr>
                                                            <tr>
                                                               <td>Konflik identiti:</td>
                                                               <td><span id="sync_preview_collisions"></span></td>
                                                            </tr>
                                                            <tr>
                                                               <td>Status semakan:</td>
                                                               <td><span id="sync_preview_status" class="users-view-status"></span></td>
                                                            </tr>
                                                            <tr>
                                                               <td>Perhatian:</td>
                                                               <td><ul id="sync_preview_warnings" class="pl-15"></ul></td>
                                                            </tr>
                                                         </tbody>
                                                      </table>
                                                      </div>
                                                      <details class="oneid-sync-technical-details">
                                                         <summary><i class="fa fa-info-circle"></i> Maklumat teknikal dan rujukan audit</summary>
                                                         <div class="sync-preview-table-wrap mt-10">
                                                            <table class="table table-borderless sync-preview-table">
                                                               <tbody>
                                                                  <tr>
                                                                     <td>Rujukan pelan / sah sehingga:</td>
                                                                     <td><span id="sync_preview_hash_expiry"></span></td>
                                                                  </tr>
                                                               </tbody>
                                                            </table>
                                                         </div>
                                                      </details>
                                                      <p id="sync_pilot_notice" class="text-muted">Readiness preview only. Controlled Pilot Apply remains disabled.</p>
                                                      <div id="sync_full_confirmation_group" class="form-group" style="display:none">
                                                         <label for="sync_full_confirmation">Untuk mengesahkan tindakan ini, masukkan frasa berikut tepat seperti dipaparkan:</label>
                                                         <input type="text" id="sync_full_confirmation" name="sync_full_confirmation" class="form-control" autocomplete="off" spellcheck="false">
                                                         <small id="sync_full_confirmation_hint" class="text-muted"></small>
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
                     <div class="modal-footer oneid-sync-child-footer">
                        <button type="button" id="btn_apply_sync_pilot" class="btn btn-danger waves-effect" style="display:none">Apply controlled pilot (2 New + 1 Update)</button>
                        <button type="button" id="btn_apply_sync_full" class="btn btn-danger waves-effect" style="display:none" disabled>Laksanakan sinkronisasi yang diluluskan</button>
                        <button type="button" id="btn_apply_sync_operational" class="btn btn-primary waves-effect" style="display:none" disabled>Laksanakan sinkronisasi</button>
                        <button type="button" class="btn btn-default waves-effect oneid-return-add-user-options" data-dismiss="modal">Tutup</button>
                     </div>
                  </form>
               </div>
               <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
         </div>
         <div id="modal_odl_shadow_preview" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="aria_modal_odl_shadow_preview" aria-hidden="true">
            <div class="modal-dialog modal-lg oneid-sync-preview-dialog">
               <div class="modal-content oneid-sync-child-modal">
                  <div class="modal-header oneid-sync-child-header oneid-sync-summary-header">
                     <div class="oneid-sync-child-heading">
                        <span class="oneid-sync-child-heading-icon"><i class="fa fa-dashboard"></i></span>
                        <div>
                           <h5 class="modal-title" id="aria_modal_odl_shadow_preview">Ringkasan Sinkronisasi Pengguna</h5>
                           <p>Paparan menyeluruh status sumber dan tindakan yang memerlukan perhatian.</p>
                        </div>
                     </div>
                     <button type="button" class="close oneid-sync-child-close oneid-return-add-user-options" data-dismiss="modal" aria-hidden="true">×</button>
                  </div>
                  <div class="modal-body oneid-sync-child-body">
                     <div id="odl_shadow_progress" class="progress progress-lg">
                        <div id="external_preview_progress_text" class="progress-bar progress-bar-info active progress-bar-striped" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width:100%" role="progressbar">Sedang menyediakan ringkasan...</div>
                     </div>
                     <div id="odl_shadow_result" style="display:none">
                        <div id="external_preview_admin_summary" class="alert alert-info oneid-sync-admin-summary" role="status"></div>
                        <h6 class="mb-1">Status setiap sumber:</h6>
                        <div class="sync-preview-table-wrap">
                           <table class="table table-borderless sync-preview-table external-summary-source-table">
                              <tbody>
                                 <tr><td>Sumber pengguna:</td><td>Jumlah rekod</td><td>Perlu tindakan</td><td>Status</td></tr>
                                 <tr><td>Staf</td><td id="external_summary_staff_rows">-</td><td id="external_summary_staff_actions">-</td><td id="external_summary_staff_status">-</td></tr>
                                 <tr><td>Pelajar Prasiswazah</td><td id="external_summary_ug_rows">-</td><td id="external_summary_ug_actions">-</td><td id="external_summary_ug_status">-</td></tr>
                                 <tr><td>Pelajar ODL</td><td id="external_summary_odl_rows">-</td><td id="external_summary_odl_actions">-</td><td id="external_summary_odl_status">-</td></tr>
                              </tbody>
                           </table>
                        </div>
                        <details class="oneid-sync-technical-details">
                           <summary><i class="fa fa-info-circle"></i> Maklumat teknikal dan rujukan audit</summary>
                           <div class="sync-preview-table-wrap mt-10">
                              <table class="table table-borderless sync-preview-table">
                                 <tbody>
                                    <tr><td id="external_preview_rows_label">Jumlah rekod sumber:</td><td id="odl_shadow_rows">-</td></tr>
                                    <tr><td>Status sumber / perubahan berbanding semakan terdahulu:</td><td id="external_preview_health">-</td></tr>
                                    <tr><td>Perubahan memerlukan semakan:</td><td id="external_preview_sync_actions">-</td></tr>
                                    <tr><td>Keahlian dikekalkan / ditambah:</td><td id="odl_shadow_membership">-</td></tr>
                                    <tr><td>Calon baharu / nyahaktif:</td><td id="odl_shadow_candidates">-</td></tr>
                                    <tr><td>Akaun kekal aktif:</td><td id="odl_shadow_keep_active">-</td></tr>
                                    <tr><td>Tahap risiko / Apply / perubahan data:</td><td id="odl_shadow_safety">-</td></tr>
                                    <tr><td>Rujukan audit Preview:</td><td id="odl_shadow_digest">-</td></tr>
                                    <tr><td>Status teknikal:</td><td><span id="odl_shadow_status" class="users-view-status">-</span></td></tr>
                                    <tr><td>Sebab proses disekat:</td><td><ul id="odl_shadow_blocks" class="pl-15"></ul></td></tr>
                                 </tbody>
                              </table>
                           </div>
                        </details>
                        <p id="external_preview_boundary_note" class="text-muted">Ringkasan ini untuk semakan sahaja. Tiada perubahan data boleh dilaksanakan dari paparan ini.</p>
                     </div>
                  </div>
                  <div class="modal-footer oneid-sync-child-footer">
                     <button type="button" class="btn btn-default waves-effect oneid-return-add-user-options" data-dismiss="modal">Tutup</button>
                  </div>
               </div>
            </div>
         </div>
         <div id="modal_add_new_user_manual" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="aria_modal_add_new_user_manual" aria-hidden="true">
            <div class="modal-dialog oneid-manual-user-dialog">
               <div class="modal-content oneid-sync-child-modal oneid-manual-user-modal">
                  <div class="modal-header oneid-sync-child-header oneid-sync-manual-header">
                     <div class="oneid-sync-child-heading">
                        <span class="oneid-sync-child-heading-icon"><i class="fa fa-user-plus"></i></span>
                        <div>
                           <h5 class="modal-title" id="aria_modal_add_new_user_manual">Tambah Pengguna Secara Manual</h5>
                           <p>Daftar satu akaun yang tiada dalam sumber sinkronisasi.</p>
                        </div>
                     </div>
                     <button type="button" class="close oneid-sync-child-close oneid-return-add-user-options" data-dismiss="modal" aria-hidden="true">×</button>
                  </div>
                  <form id="form_add_new_user_manual">
                     <div class="modal-body oneid-sync-child-body">
                        <div class="row">
                           <div class="col-lg-12">
                              <div class="">
                                 <div class="panel-wrapper collapse in">
                                    <div class="panel-body pa-0">
                                       <div class="col-sm-12 col-xs-12">
                                          <div class="form-wrap">
                                             <div class="form-body overflow-hide">
                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="add_new_manual_user_name">Nama penuh</label>
                                                   <input type="text" class="form-control add_new_manual_user_input" id="add_new_manual_user_name" name="add_new_manual_user_name" placeholder="Nama penuh staf atau pelajar" maxlength="100" required="">
                                                </div>
                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="add_new_manual_user_id">No. KP / ID pengguna / nama pengguna</label>
                                                   <input type="text" class="form-control add_new_manual_user_input" id="add_new_manual_user_id" name="add_new_manual_user_id" placeholder="ID unik staf atau pelajar" maxlength="20" pattern="[A-Za-z0-9][A-Za-z0-9._@\-]*" required="" disabled="">
                                                </div>
                                                <div class="form-group">
                                                   <label class="control-label mb-10" for="add_new_manual_user_category">Kategori pengguna</label>
                                                   <select class="form-control add_new_manual_user_input" id="add_new_manual_user_category" name="add_new_manual_user_category" required="">
                                                   </select>
                                                </div>
                                                <div class="form-group">
                                                   <div class="panel-group accordion-struct accordion-style-1" id="accordion_2" role="tablist" aria-multiselectable="true">
                                                      <div class="panel panel-default">
                                                         <div class="panel-heading" role="tab" id="heading_10">
                                                            <a role="button" data-toggle="collapse" data-parent="#accordion_2" href="#collapse_10" aria-expanded="false" class="collapsed">
                                                               <div class="icon-ac-wrap pr-20"><span class="plus-ac"><i class="ti-plus"></i></span><span class="minus-ac"><i class="ti-minus"></i></span></div>
                                                               Maklumat tambahan
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
                                                   <i class="zmdi zmdi-alert-circle-o"></i><span class="text-center">Sedang menyemak ID pengguna...</span>
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
                     <div class="modal-footer oneid-sync-child-footer">
                        <button type="button" class="btn btn-default waves-effect oneid-return-add-user-options" data-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-primary waves-effect"><i class="fa fa-plus"></i> Tambah pengguna</button>
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
                                             <li role="presentation" class="pill-yellow" style="cursor: pointer !important;" >
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
                                                         <div class="web-app-summary" aria-live="polite" aria-label="Application summary">
                                                            <div class="web-app-count">
                                                               <span>Total</span>
                                                               <strong id="web_app_count">&mdash;</strong>
                                                            </div>
                                                            <div class="web-app-count is-sso">
                                                               <span>Full SSO</span>
                                                               <strong id="web_app_sso_count">&mdash;</strong>
                                                            </div>
                                                            <div class="web-app-count is-non-sso">
                                                               <span>Non SSO</span>
                                                               <strong id="web_app_non_sso_count">&mdash;</strong>
                                                            </div>
                                                         </div>
                                                      </div>

                                                      <div class="web-app-toolbar">
                                                         <div>
                                                            <h5>Application categories</h5>
                                                            <p>Pilih kategori untuk melihat aplikasi yang berkaitan.</p>
                                                         </div>
                                                         <div class="web-app-actions">
                                                            <button class="web-app-action is-danger" type="button" onclick="open_manage_webapp_categories();">
                                                               <i class="fa fa-folder-open-o" aria-hidden="true"></i>
                                                               <span>Manage categories</span>
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

                                                      <div class="web-app-search-row">
                                                         <div class="web-app-search">
                                                            <i class="fa fa-search" aria-hidden="true"></i>
                                                            <label class="sr-only" for="admin_web_app_search">Cari semua aplikasi</label>
                                                            <input type="search" id="admin_web_app_search" autocomplete="off" placeholder="Cari nama, fungsi, URL atau App ID">
                                                            <button type="button" id="admin_web_app_search_clear" title="Kosongkan carian" aria-label="Kosongkan carian" hidden>
                                                               <i class="fa fa-times" aria-hidden="true"></i>
                                                            </button>
                                                         </div>
                                                         <span class="sr-only" id="admin_web_app_search_status" aria-live="polite">Carian sedia digunakan.</span>
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
                                                                  <i class="fa fa-refresh" aria-hidden="true"></i>
                                                                  <span>Sync User</span>
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
                                                               <p class="active-session-intro">Pantau lifecycle sesi pengguna secara read-only berdasarkan issuance, aktiviti terakhir dan polisi revocation.</p>
                                                            </div>
                                                            <div class="active-session-actions">
                                                               <div class="active-session-count" aria-live="polite">
                                                                  <span>Results</span>
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
                                                                  <p>Refresh, carian, filter dan pagination tidak menamatkan atau mengubah sesi pengguna.</p>
                                                               </div>
                                                            </div>
                                                            <div class="active-session-filter" role="search">
                                                               <div class="active-session-search-box">
                                                                  <i class="fa fa-search" aria-hidden="true"></i>
                                                                  <input type="search" id="active_session_query" maxlength="80" placeholder="Search user or device" aria-label="Search active sessions">
                                                               </div>
                                                               <select id="active_session_status" aria-label="Filter session status">
                                                                  <option value="all">All statuses</option>
                                                                  <option value="current">Current</option>
                                                                  <option value="active">Active</option>
                                                                  <option value="refresh">Refresh window</option>
                                                                  <option value="grace">Grace period</option>
                                                                  <option value="due">Due for revocation</option>
                                                                  <option value="expired">Expired</option>
                                                               </select>
                                                               <select id="active_session_page_size" aria-label="Results per page">
                                                                  <option value="10">10 per page</option>
                                                                  <option value="25" selected>25 per page</option>
                                                                  <option value="50">50 per page</option>
                                                               </select>
                                                               <button type="button" id="active_session_search_button" class="active-session-filter-button" title="Apply session filters" aria-label="Apply session filters"><i class="fa fa-search" aria-hidden="true"></i></button>
                                                            </div>
                                                            <div class="active-session-metrics" id="active_session_metrics" aria-live="polite">
                                                               <span><b id="active_metric_current">0</b>Current</span>
                                                               <span><b id="active_metric_active">0</b>Active</span>
                                                               <span><b id="active_metric_refresh">0</b>Refresh</span>
                                                               <span><b id="active_metric_grace">0</b>Grace</span>
                                                               <span><b id="active_metric_due">0</b>Due</span>
                                                               <span><b id="active_metric_expired">0</b>Expired</span>
                                                            </div>
                                                            <div class="active-session-table-wrap">
                                                               <table class="table active-session-table mb-0">
                                                                  <colgroup>
                                                                     <col class="active-col-issued">
                                                                     <col class="active-col-activity">
                                                                     <col class="active-col-user">
                                                                     <col class="active-col-device">
                                                                     <col class="active-col-status">
                                                                  </colgroup>
                                                                  <thead>
                                                                     <tr>
                                                                        <th scope="col">Issued At</th>
                                                                        <th scope="col">Last Heartbeat</th>
                                                                        <th scope="col">User</th>
                                                                        <th scope="col">Device</th>
                                                                        <th scope="col">Status</th>
                                                                     </tr>
                                                                  </thead>
                                                                  <tbody id="security_tab_session">
                                                                     <tr class="active-session-state-row is-loading">
                                                                        <td colspan="5">
                                                                           <span class="active-session-state-icon"><i class="fa fa-circle-o-notch fa-spin" aria-hidden="true"></i></span>
                                                                           <strong>Loading active sessions</strong>
                                                                           <small>Please wait while session data is retrieved.</small>
                                                                        </td>
                                                                     </tr>
                                                                  </tbody>
                                                               </table>
                                                            </div>
                                                            <div id="active_session_pagination" class="active-session-pagination" aria-live="polite"></div>
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
                                                                  <input class="form-control input-daterange-datepicker" type="text" id="audit_search_daterange" name="audit_search_daterange" value="01/01/2016 - 31/01/2016" aria-label="Audit log date range"/>
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
                                                            <div id="audit_log_pagination" class="audit-log-pagination text-center" aria-label="Audit log pages"></div>
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
                                                                              <th class="sync-action-column">#</th>
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
                                                      <nav class="configuration-tabs" aria-label="Configuration sections">
                                                         <ul class="nav nav-tabs" role="tablist">
                                                            <li class="active" role="presentation">
                                                               <a href="#configuration_authentication" id="configuration_authentication_tab" role="tab" data-toggle="tab" aria-controls="configuration_authentication" aria-selected="true">
                                                                  <i class="fa fa-shield" aria-hidden="true"></i>
                                                                  <span>Authentication Policy</span>
                                                               </a>
                                                            </li>
                                                            <li role="presentation">
                                                               <a href="#configuration_recovery" id="configuration_recovery_tab" role="tab" data-toggle="tab" aria-controls="configuration_recovery" aria-selected="false">
                                                                  <i class="fa fa-envelope-o" aria-hidden="true"></i>
                                                                  <span>Account Recovery</span>
                                                               </a>
                                                            </li>
                                                            <li role="presentation">
                                                               <a href="#configuration_admin_2fa" id="configuration_admin_2fa_tab" role="tab" data-toggle="tab" aria-controls="configuration_admin_2fa" aria-selected="false">
                                                                  <i class="fa fa-mobile" aria-hidden="true"></i>
                                                                  <span>Admin 2FA</span>
                                                               </a>
                                                            </li>
                                                            <li role="presentation">
                                                               <a href="#configuration_audit" id="configuration_audit_tab" role="tab" data-toggle="tab" aria-controls="configuration_audit" aria-selected="false">
                                                                  <i class="fa fa-history" aria-hidden="true"></i>
                                                                  <span>Audit History</span>
                                                               </a>
                                                            </li>
                                                         </ul>
                                                      </nav>
                                                      <div class="tab-content configuration-tab-content">
                                                         <section class="tab-pane fade active in" id="configuration_authentication" role="tabpanel" aria-labelledby="configuration_authentication_tab">
                                                      <div class="sso-config-panel">
                                                         <div class="sso-config-header">
                                                            <div>
                                                               <span class="sso-config-eyebrow">Authentication policy</span>
                                                               <h4 class="sso-config-title">Authentication &amp; SSO Token Policy</h4>
                                                               <p class="sso-config-intro">Urus hayat token SSO dan token aktif pada beberapa peranti. Password Recovery dikawal secara berasingan di bawah.</p>
                                                            </div>
                                                            <button class="sso-config-save" id="sso_config_save_button" type="button" onclick="update_configuration();" disabled aria-busy="true">
                                                               <i class="fa fa-check" aria-hidden="true"></i>
                                                               <span id="sso_config_save_label">Loading settings...</span>
                                                            </button>
                                                         </div>
                                                         <div class="sso-config-body">
                                                            <div class="sso-config-row">
                                                               <div class="sso-config-copy">
                                                                  <span class="sso-config-index">01</span>
                                                                  <div>
                                                                     <label for="sso_settings_token_session_timeout">SSO token lifetime</label>
                                                                     <p>Tempoh nominal token SSO sebelum validation atau legacy refresh berlaku. PHP session OneID kekal berasingan: tamat selepas 30 minit tanpa aktiviti atau maksimum 8 jam.</p>
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
                                                                     <label for="sso_settings_multi_session">Allow multiple active SSO tokens</label>
                                                                     <p>Benarkan token daripada beberapa browser atau peranti kekal aktif. Jika dimatikan, behavior semasa hanya membatalkan token lama pada login pengguna yang berikutnya.</p>
                                                                  </div>
                                                               </div>
                                                               <div class="sso-config-control">
                                                                  <div class="sso-config-switch" aria-label="Allow multiple active SSO tokens">
                                                                     <input type="checkbox" class="js-switch js-switch-1" id="sso_settings_multi_session" data-color="#11a8df" data-size="small"/>
                                                                  </div>
                                                               </div>
                                                            </div>

                                                            <div class="sso-config-note sso-config-note-warning">
                                                               <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                                                               <p>Token sehingga satu minggu meningkatkan tempoh pendedahan jika token dicuri.</p>
                                                            </div>
                                                            <div class="sso-config-note">
                                                               <i class="fa fa-info-circle" aria-hidden="true"></i>
                                                               <p id="sso_config_operational_status" role="status" aria-live="polite">Loading current policy...</p>
                                                            </div>
                                                            <div class="sso-config-row">
                                                               <div class="sso-config-copy"><span class="sso-config-index">03</span><div><label for="sso_config_change_reason">Change reason</label><p>Wajib untuk setiap perubahan. Masukkan sebab operasi tanpa password, token, OTP atau data sensitif.</p></div></div>
                                                               <div class="sso-config-control"><textarea class="form-control" id="sso_config_change_reason" maxlength="500" rows="3" placeholder="Minimum 10 characters"></textarea></div>
                                                            </div>
                                                            <div class="sso-config-note"><i class="fa fa-history" aria-hidden="true"></i><p id="sso_config_last_changed">No recorded configuration change.</p></div>
                                                         </div>
                                                      </div>
                                                         </section>
                                                         <section class="tab-pane fade" id="configuration_recovery" role="tabpanel" aria-labelledby="configuration_recovery_tab">
                                                      <div class="sso-config-panel">
                                                         <div class="sso-config-header"><div><span class="sso-config-eyebrow">Account recovery</span><h4 class="sso-config-title">Password Recovery</h4><p class="sso-config-intro">Polisi penghantaran OTP Forgot Password. Ia bukan login MFA atau Admin Step-Up 2FA.</p></div><button class="sso-config-save" id="recovery_config_save_button" type="button" onclick="updatePasswordRecovery();" disabled><i class="fa fa-check"></i> <span id="recovery_config_save_label">Loading settings...</span></button></div>
                                                         <div class="sso-config-body">
                                                            <div class="sso-config-row"><div class="sso-config-copy"><span class="sso-config-index">01</span><div><label for="password_reset_email_enabled">Send password-reset OTP by email</label><p>Apabila OFF, Forgot Password tidak mencipta challenge kerana tiada saluran recovery manual yang diluluskan.</p></div></div><div class="sso-config-control"><div class="sso-config-switch"><input type="checkbox" class="js-switch js-switch-1" id="password_reset_email_enabled" data-color="#11a8df" data-size="small"/></div></div></div>
                                                            <div class="sso-config-note"><i class="fa fa-heartbeat"></i><p id="recovery_smtp_status" role="status">Checking SMTP configuration...</p></div>
                                                            <div class="sso-config-row"><div class="sso-config-copy"><div><label for="password_recovery_test_email">Test delivery</label><p>Masukkan mailbox UAT yang diluluskan. Alamat penuh dan credential tidak direkod dalam audit.</p></div></div><div class="sso-config-control"><input type="email" class="form-control" id="password_recovery_test_email" placeholder="Mailbox UAT"><button type="button" class="btn btn-default btn-sm mt-10" onclick="testPasswordRecoveryEmail();">Send test</button></div></div>
                                                            <div class="sso-config-note sso-config-note-warning"><i class="fa fa-exclamation-triangle"></i><p>Recovery bagi pengguna tanpa e-mel sah adalah fail-closed. Prosedur manual belum tersedia dalam sistem.</p></div>
                                                         </div>
                                                      </div>
                                                         </section>
                                                         <section class="tab-pane fade" id="configuration_admin_2fa" role="tabpanel" aria-labelledby="configuration_admin_2fa_tab">
                                                      <div class="sso-config-panel">
                                                         <div class="sso-config-header">
                                                            <div><span class="sso-config-eyebrow">Administrator security</span><h4 class="sso-config-title">Kaedah 2FA Pilihan</h4><p class="sso-config-intro">Tetapkan kaedah yang akan dipaparkan dahulu apabila akaun anda perlu membuat pengesahan Administrator.</p></div>
                                                            <button class="sso-config-save" id="admin_mfa_preference_save_button" type="button" onclick="saveAdminMfaPreference();" disabled><i class="fa fa-check"></i> <span id="admin_mfa_preference_save_label">Loading settings...</span></button>
                                                         </div>
                                                         <div class="sso-config-body">
                                                            <div class="sso-config-row"><div class="sso-config-copy"><span class="sso-config-index">01</span><div><label for="admin_mfa_preferred_factor">Kaedah yang dipaparkan dahulu</label><p>Pilihan ini tidak mengunci kaedah authentication. Microsoft Authenticator dan OTP e-mel masih boleh dipilih pada halaman pengesahan jika kedua-duanya tersedia.</p></div></div><div class="sso-config-control sso-config-select-wrap"><select class="form-control" id="admin_mfa_preferred_factor"><option value="TOTP">Microsoft Authenticator</option><option value="EMAIL_OTP">OTP e-mel</option></select></div></div>
                                                            <div class="sso-config-note"><i class="fa fa-info-circle"></i><p id="admin_mfa_preference_status" role="status" aria-live="polite">Loading current preference...</p></div>
                                                            <div class="sso-config-row"><div class="sso-config-copy"><span class="sso-config-index">02</span><div><label for="admin_step_up_lifetime_minutes">Tempoh pengesahan Administrator</label><p>Grant baharu kekal sah untuk purpose yang sama dalam pelayar ini. Perubahan tidak memanjangkan grant yang telah dikeluarkan.</p></div></div><div class="sso-config-control"><select class="form-control" id="admin_step_up_lifetime_minutes"><option value="5">5 minit</option><option value="10">10 minit</option><option value="15">15 minit (disyorkan)</option><option value="30">30 minit</option></select><textarea class="form-control mt-10" id="admin_step_up_lifetime_reason" maxlength="500" rows="2" placeholder="Sebab perubahan (minimum 10 aksara)"></textarea><button type="button" class="btn btn-default btn-sm mt-10" id="admin_step_up_lifetime_save_button" onclick="saveAdminStepUpLifetime();" disabled><i class="fa fa-clock-o"></i> <span id="admin_step_up_lifetime_save_label">Loading policy...</span></button></div></div>
                                                            <div class="sso-config-note"><i class="fa fa-clock-o"></i><p id="admin_step_up_lifetime_status" role="status" aria-live="polite">Loading current lifetime...</p></div>
                                                            <div class="sso-config-row" id="admin_mfa_enrollment_action" style="display:none"><div class="sso-config-copy"><span class="sso-config-index">03</span><div><label>Microsoft Authenticator belum didaftarkan</label><p>Mulakan enrollment baharu. Sistem akan meminta OTP e-mel sebelum memaparkan QR dan setup key.</p></div></div><div class="sso-config-control"><button type="button" class="btn btn-primary" onclick="window.location.href='../page/admin-step-up?purpose=SECURITY_CONFIGURATION_CHANGE&amp;intent=totp_enroll';"><i class="fa fa-qrcode"></i> Daftar Authenticator</button></div></div>
                                                            <div class="sso-config-row" id="admin_mfa_reset_action" style="display:none"><div class="sso-config-copy"><span class="sso-config-index">03</span><div><label>Reset Microsoft Authenticator</label><p>Gunakan OTP e-mel untuk revoke faktor semasa secara beraudit, kemudian daftar QR baharu untuk environment ini.</p></div></div><div class="sso-config-control"><button type="button" class="btn btn-danger" onclick="window.location.href='../page/admin-step-up?purpose=SECURITY_CONFIGURATION_CHANGE&amp;intent=totp_reset';"><i class="fa fa-refresh"></i> Reset Authenticator</button></div></div>
                                                            <div class="sso-config-note sso-config-note-warning"><i class="fa fa-shield"></i><p>Menyimpan kaedah pilihan atau tempoh grant memerlukan pengesahan <strong>Security Configuration Change</strong>. Tempoh hanya boleh ditetapkan kepada 5, 10, 15 atau 30 minit.</p></div>
                                                         </div>
                                                      </div>
                                                         </section>
                                                         <section class="tab-pane fade" id="configuration_audit" role="tabpanel" aria-labelledby="configuration_audit_tab">
                                                      <div class="sso-config-panel">
                                                         <div class="sso-config-header"><div><span class="sso-config-eyebrow">Audit history</span><h4 class="sso-config-title">Configuration History</h4><p class="sso-config-intro">Success and rejected attempts, newest first. Sensitive authentication material is never displayed.</p></div><button type="button" class="sso-config-save" onclick="loadSsoConfigHistory(1)"><i class="fa fa-refresh"></i><span>Refresh</span></button></div>
                                                         <div class="configuration-history-table-wrap">
                                                            <table class="table configuration-history-table mb-0">
                                                               <colgroup>
                                                                  <col class="configuration-history-col-event">
                                                                  <col class="configuration-history-col-result">
                                                                  <col class="configuration-history-col-change">
                                                                  <col class="configuration-history-col-reason">
                                                               </colgroup>
                                                               <thead><tr><th scope="col">Event</th><th scope="col">Result</th><th scope="col">Changes</th><th scope="col">Reason &amp; Reference</th></tr></thead>
                                                               <tbody id="sso_config_history_body"><tr class="configuration-history-state-row"><td colspan="4">Loading history...</td></tr></tbody>
                                                            </table>
                                                         </div>
                                                         <div id="sso_config_history_pagination" class="active-session-pagination"></div>
                                                      </div>
                                                         </section>
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
      <!-- Sweet-Alert  -->
      <script src="../vendors/bower_components/sweetalert/dist/sweetalert.min.js"></script>
      <script src="../vendors/bower_components/jquery-toast-plugin/dist/jquery.toast.min.js"></script>
      <script src="../assetsM/js/oneid-notifications.js?v=20260716-1"></script>
      <!-- Init JavaScript -->
      <script src="../dist/js/init.js?v=20260716-1"></script>
      <script src="../vendors/typeahead.js"></script>
      <!-- Form Flie Upload Data JavaScript -->
      <script src="../vendors/bower_components/dropify/dist/js/dropify.min.js"></script>
      <script src="../dist/js/form-file-upload-data.js?v=20260716-1"></script>

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
            var auditDatePicker = $('.input-daterange-datepicker').daterangepicker({
               startDate: moment(),
               endDate: moment(),
               locale: {
                  format: 'DD/MM/YYYY',
                  separator: ' - '
               },
               buttonClasses: ['btn', 'btn-sm'],
               applyClass: 'btn-info',
               cancelClass: 'btn-default'
            });
            auditDatePicker.data('daterangepicker').container.addClass('oneid-audit-daterangepicker');
            auditDatePicker
               .on('show.daterangepicker', function(event, picker){
                  picker.container.addClass('is-open');
               })
               .on('hide.daterangepicker', function(event, picker){
                  picker.container.removeClass('is-open');
               });

         $('#search_user_account_main_progress_bar').hide();
         $('#search_user_account_main_search_result').hide();
         
         
         get_service_provider_list();
         admin_get_all_user_category(0);		
         admin_get_settings();	
         loadPasswordRecovery();
         loadAdminMfaPreference();
         get_all_user_activ_session();
		 
             startTokenRefresh();

             $('a[href="#tab_synclog"]').on('shown.bs.tab', function(){
                load_sync_sessions();
             });

             $('#tab_settings .configuration-tabs a[data-toggle="tab"]').on('shown.bs.tab', function(event){
                $('#tab_settings .configuration-tabs a[data-toggle="tab"]').attr('aria-selected', 'false');
                $(event.target).attr('aria-selected', 'true');
                if ($(event.target).attr('href') === '#configuration_audit') {
                   loadSsoConfigHistory(1);
                }
                if ($(event.target).attr('href') === '#configuration_admin_2fa') {
                   loadAdminMfaPreference();
                }
             });
             var requestedConfiguration=new URLSearchParams(window.location.search).get('configuration');
             if(requestedConfiguration==='admin_2fa'){
                $('a[href="#tab_settings"]').tab('show');
                $('#configuration_admin_2fa_tab').tab('show');
                if(window.history&&window.history.replaceState){window.history.replaceState({},document.title,window.location.pathname);}
             }
             if(requestedConfiguration==='account_recovery'){
                $('a[href="#tab_settings"]').tab('show');
                $('#configuration_recovery_tab').tab('show');
                var pendingRecoveryTest=sessionStorage.getItem('oneid_password_recovery_test_email');
                if(pendingRecoveryTest){
                   $('#password_recovery_test_email').val(pendingRecoveryTest);
                   sessionStorage.removeItem('oneid_password_recovery_test_email');
                   swal('Pengesahan berjaya','Alamat ujian dikekalkan. Klik Send test sekali lagi untuk menghantar e-mel.','success');
                }
                if(window.history&&window.history.replaceState){window.history.replaceState({},document.title,window.location.pathname);}
             }
         });
         
         $('#the-basics .typeahead').typeahead(
         {
         hint: false,
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
         
         
         
           var adminWebAppGroups = [];
           var adminWebAppSearchTerm = '';
           var adminWebAppActiveTab = '';

           function adminWebAppText(value){
             return $('<div>').text(value == null ? '' : value).html();
           }

           function adminWebAppMatches(application, term){
             if (term === '') return true;
             return [application.sp_name, application.sp_description, application.sp_domain, application.sp_id]
               .map(function(value){ return String(value || '').toLocaleLowerCase(); })
               .join(' ')
               .indexOf(term) !== -1;
           }

           function renderAdminWebAppDirectory(){
             var term = adminWebAppSearchTerm.trim().toLocaleLowerCase();
             var tabs = '';
             var panes = '';
             var totalCount = 0;
             var matchingCount = 0;
             var matchingTabs = [];
             var uniqueApplications = {};

             $.each(adminWebAppGroups, function(i, group){
               var tabName = adminWebAppText(group.tabname);
               var groupName = adminWebAppText(group.sp_group_name);
               var allApplications = Array.isArray(group.data) ? group.data : [];
               var applications = allApplications.filter(function(application){
                 return adminWebAppMatches(application, term);
               });
               totalCount += allApplications.length;
               $.each(allApplications, function(_, application){
                 var uniqueId = String(application.sp_id || '');
                 if (uniqueId !== '') uniqueApplications[uniqueId] = application;
               });
               matchingCount += applications.length;
               if (applications.length > 0) matchingTabs.push('#' + tabName);

               tabs += '<li role="presentation"><a aria-expanded="false" data-toggle="tab" role="tab" href="#'+tabName+'"><span>'+groupName+'</span><strong>'+applications.length+'</strong></a></li>';
               panes += '<div id="'+tabName+'" class="tab-pane fade" role="tabpanel"><div class="web-app-card-list">';
               if (applications.length === 0) {
                 panes += '<div class="web-app-category-empty"><i class="fa fa-inbox" aria-hidden="true"></i><span>'+(term === '' ? 'No applications in this category.' : 'Tiada aplikasi sepadan dengan carian dalam kategori ini.')+'</span></div>';
               }
               $.each(applications, function(k, application){
                 var appId = adminWebAppText(application.sp_id);
                 var appName = adminWebAppText(application.sp_name);
                 var appDescription = adminWebAppText(application.sp_description);
                 var appDomain = adminWebAppText(application.sp_domain);
                 var appImage = adminWebAppText(application.sp_image);
                 var imageSource = appImage === '' ? '../img/thumb-1.jpg' : '../public_img/' + appImage;
                 var ssoUnsupported = application.sp_sso_support !== '0';
                 panes += '<article class="web-app-card">';
                 panes += '<div class="web-app-card-index">'+(k + 1)+'</div>';
                 panes += '<div class="web-app-card-image"><img src="'+imageSource+'" alt="" loading="lazy" onerror="this.onerror=null;this.src=\'../img/thumb-1.jpg\';"></div>';
                 panes += '<div class="web-app-card-content"><div class="web-app-card-name"><strong title="'+appName+'">'+appName+'</strong>'+(ssoUnsupported ? '<span class="web-app-sso-badge">Direct link</span>' : '<span class="web-app-sso-badge is-enabled">SSO</span>')+'</div>';
                 panes += '<p title="'+appDescription+'">'+appDescription+'</p><span class="web-app-domain" title="'+appDomain+'"><i class="fa fa-link" aria-hidden="true"></i>'+appDomain+'</span></div>';
                 panes += '<button type="button" class="web-app-view" data-app-id="'+appId+'" onclick="open_edit_webapp(this.dataset.appId);" title="View application" aria-label="View application"><i class="fa fa-eye" aria-hidden="true"></i></button></article>';
               });
               panes += '</div></div>';
             });

             var uniqueList = Object.keys(uniqueApplications).map(function(id){ return uniqueApplications[id]; });
             var ssoCount = uniqueList.filter(function(application){
               return String(application.sp_sso_support) === '0';
             }).length;
             totalCount = uniqueList.length;
             $('#web_app_count').text(totalCount);
             $('#web_app_sso_count').text(ssoCount);
             $('#web_app_non_sso_count').text(totalCount - ssoCount);
             $('#WebAppsTabsHeader').html(tabs);
             $('#WebAppsTabsContent').html(panes);
             $('#admin_web_app_search_status').text(term === '' ? 'Carian merentas semua kategori aplikasi.' : 'Memaparkan ' + matchingCount + ' daripada ' + totalCount + ' aplikasi.');

             var requestedTab = adminWebAppActiveTab;
             if (matchingTabs.indexOf(requestedTab) === -1) requestedTab = matchingTabs.length ? matchingTabs[0] : $('#WebAppsTabsHeader a').first().attr('href');
             var $link = $('#WebAppsTabsHeader a[href="'+requestedTab+'"]');
             if ($link.length) {
               $link.tab('show');
               adminWebAppActiveTab = requestedTab;
             }
           }

           function get_service_provider_list(){
            var href = $('#WebAppsTabsHeader li.active a').attr('href');
            if (href) adminWebAppActiveTab = href;
           	$.ajax({
           		type: 'POST',
           		url: '../lib/q_func',
           		dataType: "json",
           		data: {admin_get_all_service_provider:""},
			beforeSend: function(){
						adminWebAppGroups = [];
						$('#web_app_count, #web_app_sso_count, #web_app_non_sso_count').text('\u2014');
           			$('#tab_available_apps_list_loading').show();
           			$('#tab_available_apps_list').hide();
                        $('#WebAppsTabsHeader').html('');
                        $('#WebAppsTabsContent').html('');
						$('#follo_data_list').html('');
           		},
           		success: function (response) {
           			$('#tab_available_apps_list_loading').hide();
           			$('#tab_available_apps_list').show();

						if (!Array.isArray(response) || response.length === 0) {
							adminWebAppGroups = [];
							$('#web_app_count').text('0');
							$('#web_app_sso_count, #web_app_non_sso_count').text('0');
							$('#admin_web_app_search_status').text('Tiada aplikasi tersedia untuk carian.');
							$('#follo_data_list').html(
								'<div class="web-app-state">' +
								'<span><i class="fa fa-th-large" aria-hidden="true"></i></span>' +
								'<strong>No applications available</strong>' +
								'<small>Add an application to begin building the directory.</small>' +
								'</div>'
							);
							return;
						}

						adminWebAppGroups = response;
						renderAdminWebAppDirectory();

					},
					error: function (xhr, error, thrown) {
						$('#web_app_count, #web_app_sso_count, #web_app_non_sso_count').text('\u2014');
						$('#tab_available_apps_list_loading').hide();
						$('#tab_available_apps_list').show();
						$('#admin_web_app_search_status').text('Carian tidak tersedia kerana direktori gagal dimuatkan.');
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

         $(document).on('input', '#admin_web_app_search', function(){
           adminWebAppSearchTerm = String(this.value || '');
           $('#admin_web_app_search_clear').prop('hidden', adminWebAppSearchTerm === '');
           renderAdminWebAppDirectory();
         });

         $(document).on('click', '#admin_web_app_search_clear', function(){
           adminWebAppSearchTerm = '';
           $('#admin_web_app_search').val('').focus();
           $(this).prop('hidden', true);
           renderAdminWebAppDirectory();
         });

         $(document).on('shown.bs.tab', '#follo_8 #WebAppsTabsHeader a[data-toggle="tab"]', function(){
           adminWebAppActiveTab = $(this).attr('href');
         });
         
         
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
         
         
         
         var ssoConfigOriginal = null;
         var ssoConfigSaving = false;

         function ssoConfigValues(){
            return {
               token_timeout: String($('#sso_settings_token_session_timeout').val() || ''),
               multi_session: document.getElementById('sso_settings_multi_session').checked ? '1' : '0'
            };
         }

         function ssoConfigTimeoutLabel(value){
            var option = $('#sso_settings_token_session_timeout option[value="' + value + '"]');
            return option.length ? option.text() : value + ' jam';
         }

         function ssoConfigChangeSummary(current){
            var changes = [];
            if (!ssoConfigOriginal) {
               return changes;
            }
            if (current.token_timeout !== ssoConfigOriginal.token_timeout) {
               changes.push('SSO token lifetime: ' + ssoConfigTimeoutLabel(ssoConfigOriginal.token_timeout) + ' -> ' + ssoConfigTimeoutLabel(current.token_timeout));
            }
            if (current.multi_session !== ssoConfigOriginal.multi_session) {
               changes.push('Multiple active SSO tokens: ' + (ssoConfigOriginal.multi_session === '1' ? 'Allowed' : 'Not allowed') + ' -> ' + (current.multi_session === '1' ? 'Allowed' : 'Not allowed'));
            }
            return changes;
         }

         function setSsoConfigSaving(isSaving){
            ssoConfigSaving = isSaving;
            $('#sso_config_save_button').prop('disabled', isSaving || !ssoConfigOriginal).attr('aria-busy', isSaving ? 'true' : 'false');
            $('#sso_config_save_label').text(isSaving ? 'Saving...' : 'Review & save');
            $('#sso_settings_token_session_timeout, #sso_settings_multi_session').prop('disabled', isSaving);
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
                        if (!response || Number(response.status) !== 1 || response.code !== 'SC2_CONFIG_LOADED' || !response.data) {
                           ssoConfigOriginal = null;
                           $('#sso_config_save_button').prop('disabled', true).attr('aria-busy', 'false');
                           $('#sso_config_save_label').text('Settings unavailable');
                           var loadReference = response && response.correlation_id ? '\nReference: ' + response.correlation_id : '';
                           $('#sso_config_operational_status').text('Current policy could not be loaded. No changes can be saved.');
                           swal('Settings unavailable', 'The server rejected or could not complete the load request.' + loadReference, 'error');
                           return;
                        }
                        var lastChanged=response.last_changed||null;
                        response = response.data;
                        ssoConfigVersion=Number(response.configuration_version||0);
                        $('#sso_config_last_changed').text(lastChanged?'Last changed by '+lastChanged.actor_id+' at '+lastChanged.created_at+' (revision '+lastChanged.configuration_version_after+').':'No recorded configuration change.');
                        loadSsoConfigHistory(1);
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
                           $("#sso_settings_multi_session").prop("checked", false);
                        }else{
                           $("#sso_settings_multi_session").prop("checked", true);
                        }
                        var elems = Array.prototype.slice.call(document.querySelectorAll('.js-switch:not(#password_reset_email_enabled)'));
                        elems.forEach(function (html) {
                           if (!html.getAttribute('data-switchery')) { new Switchery(html, {}); }
                        });
                        ssoConfigOriginal = ssoConfigValues();
                        setSsoConfigSaving(false);
                        $('#sso_config_operational_status').text('Current policy loaded. Review all changes before saving; existing token enforcement behavior is unchanged in this phase.');
                     },
                     error: function (xhr, error, thrown) {
                        ssoConfigOriginal = null;
                        $('#sso_config_save_button').prop('disabled', true).attr('aria-busy', 'false');
                        $('#sso_config_save_label').text('Settings unavailable');
                        $('#sso_config_operational_status').text('Current policy could not be loaded. No changes can be saved. HTTP ' + xhr.status + '.');
                        swal('Settings unavailable', 'The current policy could not be loaded. No changes have been made.\nHTTP status: ' + xhr.status, 'error');
                     }
                 });
         }
         
         var ssoConfigVersion=0;
         function update_configuration(){
            if (ssoConfigSaving || !ssoConfigOriginal) {
               return;
            }
            var current = ssoConfigValues();
            var changeReason=$.trim($('#sso_config_change_reason').val()||'');
            if(changeReason.length<10){swal('Change reason required','Enter at least 10 characters describing why this policy must change.','warning');return;}
            var changes = ssoConfigChangeSummary(current);
            if (changes.length === 0) {
               swal('No changes', 'The selected policy already matches the saved values. No request was sent.', 'info');
               return;
            }
            var warning = '';
            if (Number(current.token_timeout) >= 168) {
               warning += '\n\nWarning: the selected token lifetime is one week.';
            }
            setSsoConfigSaving(true);
            $('#sso_config_operational_status').text('Calculating affected users and tokens...');
            $.ajax({
               type:'POST',url:'../lib/q_func',dataType:'json',
               data:{preview_configuration_update:'',sso_settings_multi_session:current.multi_session,token_timeout:current.token_timeout,change_reason:changeReason},
               success:function(preview){
                  if(!preview || preview.code!=='SC5_PREVIEW_CREATED'){
                     swal('Preview failed','No changes were made.\nCode: '+(preview&&preview.code?preview.code:'SC5_PREVIEW_INVALID'),'error');
                     return;
                  }
                  var impact=preview.impact||{};
                  swal({title:'Confirm policy and impact',text:changes.join('\n')+warning+'\n\nAffected users: '+Number(impact.affected_users||0)+'\nAffected tokens: '+Number(impact.affected_tokens||0)+'\nGrace period: 15 minutes',type:'warning',showCancelButton:true,confirmButtonColor:'#11a8df',confirmButtonText:'Save policy',cancelButtonText:'Cancel',closeOnConfirm:false},function(){
                     submitSsoConfigUpdate(current,preview.preview_id,preview.configuration_version,changeReason);
                  });
               },
               error:function(xhr){swal('Preview failed','No changes were made. HTTP '+xhr.status+'.','error');},
               complete:function(){setSsoConfigSaving(false);}
            });
         }

         function submitSsoConfigUpdate(current,previewId,configurationVersion,changeReason){
            setSsoConfigSaving(true);
            $('#sso_config_operational_status').text('Saving policy. Do not close this page.');
            $.ajax({type:'POST',url:'../lib/q_func',dataType:'json',
               data:{update_configuration:'',policy_preview_id:previewId,sso_settings_multi_session:current.multi_session,token_timeout:current.token_timeout,configuration_version:configurationVersion,change_reason:changeReason},
                     success: function (response) {
                        if(response && Number(response.status) === 1 && response.code === 'SC2_CONFIG_UPDATED'){
                           ssoConfigOriginal = current;
                           ssoConfigVersion=Number(response.data&&response.data.configuration_version||configurationVersion+1);$('#sso_config_change_reason').val('');admin_get_settings();
                           var enforcement=response.enforcement||{};
                           $('#sso_config_operational_status').text('Policy saved. Scheduled tokens: '+Number(enforcement.scheduled_tokens||0)+(enforcement.revoke_at?' at '+enforcement.revoke_at:'.'));
                           swal('Policy saved', 'Scheduled tokens: '+Number(enforcement.scheduled_tokens||0)+'\nGrace period: 15 minutes\nReference: ' + response.correlation_id, 'success');
                        }else if(response && Number(response.status) === 1 && response.code === 'SC2_CONFIG_UNCHANGED'){
                           ssoConfigOriginal = current;
                           $('#sso_config_operational_status').text('No database value changed; the saved policy already matched the selection.');
                           swal('No changes', 'The database already contained the selected values.\nReference: ' + response.correlation_id, 'info');
                        }else{
                           var errorCode = response && response.code ? response.code : 'SC2_RESPONSE_INVALID';
                           var errorReference = response && response.correlation_id ? response.correlation_id : 'Unavailable';
                           $('#sso_config_operational_status').text('Policy was not saved. Code: ' + errorCode + '.');
                           swal('Policy not saved', 'The request was rejected or returned an invalid result.\nCode: ' + errorCode + '\nReference: ' + errorReference, 'error');
                        }
                     },
                     error: function (xhr, error, thrown) {
                        $('#sso_config_operational_status').text('Save failed. The previous loaded policy remains the baseline. HTTP ' + xhr.status + '.');
                        swal('Policy not saved', 'The server request failed. No success has been assumed.\nHTTP status: ' + xhr.status, 'error');
                     },
                     complete: function () {
                        setSsoConfigSaving(false);
                     }
                 });
         }

         function loadSsoConfigHistory(page){
            $.post('../lib/q_func',{admin_get_configuration_history:'',page:page||1,page_size:10},function(response){
               if(!response||Number(response.status)!==1){renderSsoConfigHistoryState('History unavailable.');return;}
               var rows='';
               $.each(response.data||[],function(i,item){
                  var outcome=String(item.outcome||'').toUpperCase();
                  var outcomeClass=outcome==='SUCCESS'?'is-success':'is-rejected';
                  var revision=item.version_before===null?'-':item.version_before+' → '+item.version_after;
                  var reason=item.change_reason||'No change reason recorded.';
                  var changes='<span class="configuration-history-empty">No policy mutation</span>';
                  if(item.before&&item.after){
                     changes='<span class="configuration-history-change"><b>Token</b> '+sessionTextValue(item.before.token_timeout)+' → '+sessionTextValue(item.after.token_timeout)+'</span>'+
                        '<span class="configuration-history-change"><b>Multiple</b> '+(Number(item.before.multi_session)===1?'Allowed':'Not allowed')+' → '+(Number(item.after.multi_session)===1?'Allowed':'Not allowed')+'</span>';
                  }
                  rows+='<tr>'+
                     '<td data-label="Event"><span class="configuration-history-primary">'+sessionTextValue(item.created_at)+'</span><span class="configuration-history-secondary"><i class="fa fa-user-o" aria-hidden="true"></i>'+sessionTextValue(item.actor)+'</span></td>'+
                     '<td data-label="Result"><span class="configuration-history-outcome '+outcomeClass+'">'+sessionTextValue(outcome||'UNKNOWN')+'</span><span class="configuration-history-secondary">Revision '+sessionTextValue(revision)+'</span></td>'+
                     '<td data-label="Changes">'+changes+'</td>'+
                     '<td data-label="Reason &amp; Reference"><span class="configuration-history-reason" title="'+sessionAttributeValue(reason)+'">'+sessionTextValue(reason)+'</span><span class="configuration-history-secondary">'+sessionTextValue(item.reason_code)+' · '+sessionTextValue(item.correlation_id)+'</span></td>'+
                     '</tr>';
               });
               if(rows){$('#sso_config_history_body').html(rows);}else{renderSsoConfigHistoryState('No configuration history recorded.');}
               var meta=response.meta||{};var p=Number(meta.page||1),pages=Number(meta.total_pages||1);$('#sso_config_history_pagination').html('<button type="button" '+(p<=1?'disabled':'')+' onclick="loadSsoConfigHistory('+(p-1)+')"><i class="fa fa-chevron-left"></i></button><span>Page '+p+' of '+pages+'</span><button type="button" '+(p>=pages?'disabled':'')+' onclick="loadSsoConfigHistory('+(p+1)+')"><i class="fa fa-chevron-right"></i></button>');
            },'json').fail(function(){renderSsoConfigHistoryState('History unavailable.');});
         }
         function renderSsoConfigHistoryState(message){$('#sso_config_history_body').html('<tr class="configuration-history-state-row"><td colspan="4">'+sessionTextValue(message)+'</td></tr>');}
         function sessionTextValue(value){return $('<div>').text(value==null?'':value).html();}
         function sessionAttributeValue(value){return sessionTextValue(value).replace(/"/g,'&quot;').replace(/'/g,'&#39;');}

         var recoveryConfigOriginal=null;
         function loadPasswordRecovery(){
            $.post('../lib/q_func',{admin_get_password_recovery_settings:''},function(r){
               if(!r||r.code!=='SC6_RECOVERY_LOADED'){ $('#recovery_config_save_label').text('Settings unavailable'); return; }
               recoveryConfigOriginal=String(r.data.password_reset_email_enabled);
               var recoveryToggle=document.getElementById('password_reset_email_enabled');recoveryToggle.checked=recoveryConfigOriginal==='1';
               if(!recoveryToggle.getAttribute('data-switchery')){new Switchery(recoveryToggle,{});}
               $('#recovery_config_save_button').prop('disabled',false);$('#recovery_config_save_label').text('Review & save');
               var h=r.data.smtp_health||{};$('#recovery_smtp_status').text('SMTP configuration: '+(h.status==='configured'?'configured; live delivery not yet verified.':'not configured.'));
            },'json').fail(function(){ $('#recovery_config_save_label').text('Settings unavailable'); });
         }
         function updatePasswordRecovery(){
            if(recoveryConfigOriginal===null)return;var value=$('#password_reset_email_enabled').prop('checked')?'1':'0';
            if(value===recoveryConfigOriginal){swal('No changes','Password Recovery policy already matches.','info');return;}
            var text=value==='1'?'Enable email OTP delivery?':'Disable email OTP delivery? Forgot Password will fail closed and create no challenge.';
            swal({title:'Confirm Password Recovery policy',text:text,type:'warning',showCancelButton:true,confirmButtonText:'Save policy',closeOnConfirm:false},function(){
               $('#recovery_config_save_button').prop('disabled',true);$.post('../lib/q_func',{update_password_recovery:'',password_reset_email_enabled:value},function(r){
                  if(r&&Number(r.status)===1){recoveryConfigOriginal=value;swal('Recovery policy saved','Reference: '+r.correlation_id,'success');}else{swal('Policy not saved','Code: '+(r&&r.code?r.code:'SC6_RESPONSE_INVALID'),'error');}
               },'json').fail(function(){swal('Policy not saved','Server request failed.','error');}).always(function(){$('#recovery_config_save_button').prop('disabled',false);});
            });
         }
         function testPasswordRecoveryEmail(){
            var recipient=$.trim($('#password_recovery_test_email').val());if(!recipient){swal('Recipient required','Enter an approved UAT mailbox.','warning');return;}
            swal({title:'Send recovery test?',text:'A non-OTP test email will be sent to the entered mailbox.',type:'warning',showCancelButton:true,confirmButtonText:'Send test',closeOnConfirm:false},function(){
               $.post('../lib/q_func',{test_password_recovery_email:'',recipient_email:recipient},function(r){swal(Number(r.status)===1?'Accepted by SMTP':'Test failed',(Number(r.status)===1?'The SMTP server accepted the message. Check Inbox, Junk and Quarantine; this is not proof of mailbox delivery.':'The SMTP server did not accept the message.')+'\nCode: '+r.code+(r.message_id?'\nMessage-ID: '+r.message_id:'')+'\nReference: '+r.correlation_id,Number(r.status)===1?'success':'error');},'json').fail(function(xhr){
                  var code=xhr.responseJSON&&xhr.responseJSON.code?xhr.responseJSON.code:'';
                  if(xhr.status===403&&(code==='STEP_UP_REQUIRED'||code==='STEP_UP_EXPIRED'||code==='STEP_UP_PURPOSE_MISMATCH')){
                     sessionStorage.setItem('oneid_password_recovery_test_email',recipient);
                     swal({title:'Pengesahan diperlukan',text:'Sahkan Security Configuration Change sebelum menghantar e-mel ujian.',type:'warning',confirmButtonText:'Authenticate now',closeOnConfirm:true},function(){window.location.href='../page/admin-step-up?purpose=SECURITY_CONFIGURATION_CHANGE&return=account_recovery';});
                  }else{
                     swal('Test failed','Server request failed. HTTP '+xhr.status+(code?'\nCode: '+code:''),'error');
                  }
               });
            });
         }

         var adminMfaPreferenceOriginal=null,adminMfaSecurityGrantValid=false,adminMfaPreferenceSaving=false,adminStepUpLifetimeOriginal=null,adminMfaConfigurationVersion=0,adminStepUpLifetimeSaving=false;
         function adminMfaPreferenceLabel(value){return value==='TOTP'?'Microsoft Authenticator':'OTP e-mel';}
         function loadAdminMfaPreference(){
            $('#admin_mfa_preference_save_button').prop('disabled',true);
            $('#admin_mfa_preference_save_label').text('Loading settings...');
            $.post('../lib/q_func',{admin_step_up_status:'',purpose:'SECURITY_CONFIGURATION_CHANGE'},function(r){
               if(!r||Number(r.status)!==1){adminMfaPreferenceOriginal=null;$('#admin_mfa_preference_save_label').text('Settings unavailable');$('#admin_mfa_preference_status').text('Kaedah pilihan semasa tidak dapat dimuatkan.');return;}
               adminMfaPreferenceOriginal=r.preferred_factor||'EMAIL_OTP';
               adminStepUpLifetimeOriginal=Number(r.admin_step_up_lifetime_minutes||15);
               adminMfaConfigurationVersion=Number(r.configuration_version||0);
               adminMfaSecurityGrantValid=Boolean(r.grant_valid);
               $('#admin_mfa_preferred_factor').val(adminMfaPreferenceOriginal);
               $('#admin_step_up_lifetime_minutes').val(String(adminStepUpLifetimeOriginal));
               $('#admin_step_up_lifetime_status').text('Tempoh semasa: '+adminStepUpLifetimeOriginal+' minit. Hanya grant baharu menggunakan nilai ini.');
               $('#admin_step_up_lifetime_save_button').prop('disabled',false);$('#admin_step_up_lifetime_save_label').text('Save lifetime');
               $('#admin_mfa_preferred_factor option[value="TOTP"]').prop('disabled',!r.totp_available);
               $('#admin_mfa_enrollment_action').toggle(!r.totp_available);
               $('#admin_mfa_reset_action').toggle(Boolean(r.totp_available));
               $('#admin_mfa_preference_status').text('Kaedah pilihan semasa: '+adminMfaPreferenceLabel(adminMfaPreferenceOriginal)+'. Authenticator: '+(r.totp_available?'aktif':'tidak tersedia')+'; e-mel: '+(r.masked_email||'tidak tersedia')+'.');
               var pendingFactor=sessionStorage.getItem('oneid_mfa_pending_factor');
               if(adminMfaSecurityGrantValid&&(pendingFactor==='EMAIL_OTP'||(pendingFactor==='TOTP'&&r.totp_available))){$('#admin_mfa_preferred_factor').val(pendingFactor);$('#admin_mfa_preference_status').append(' Pengesahan selesai; preference sedang disimpan.');setTimeout(function(){persistAdminMfaPreference(pendingFactor);},0);}
               var pendingLifetime=sessionStorage.getItem('oneid_admin_step_up_pending_lifetime'),pendingLifetimeReason=sessionStorage.getItem('oneid_admin_step_up_pending_reason');
               if(adminMfaSecurityGrantValid&&pendingLifetime&&pendingLifetimeReason){$('#admin_step_up_lifetime_minutes').val(pendingLifetime);$('#admin_step_up_lifetime_reason').val(pendingLifetimeReason);$('#admin_step_up_lifetime_status').append(' Pengesahan selesai; polisi sedang disimpan.');setTimeout(function(){persistAdminStepUpLifetime(Number(pendingLifetime),pendingLifetimeReason);},0);}
               $('#admin_mfa_preference_save_button').prop('disabled',false);
               $('#admin_mfa_preference_save_label').text('Save preference');
            },'json').fail(function(){adminMfaPreferenceOriginal=null;$('#admin_mfa_preference_save_label').text('Settings unavailable');$('#admin_mfa_preference_status').text('Kaedah pilihan semasa tidak dapat dimuatkan.');});
         }
         function saveAdminStepUpLifetime(){
            if(adminStepUpLifetimeOriginal===null||adminStepUpLifetimeSaving)return;
            var minutes=Number($('#admin_step_up_lifetime_minutes').val()),reason=$.trim($('#admin_step_up_lifetime_reason').val());
            if(minutes===adminStepUpLifetimeOriginal){swal('No changes','Tempoh pengesahan Administrator sudah '+minutes+' minit.','info');return;}
            if(reason.length<10){swal('Reason required','Masukkan sebab perubahan sekurang-kurangnya 10 aksara.','warning');return;}
            swal({title:'Ubah tempoh pengesahan?',text:'Grant baharu akan sah selama '+minutes+' minit. Grant sedia ada tidak berubah.',type:'warning',showCancelButton:true,confirmButtonText:'Save lifetime',closeOnConfirm:false},function(){
               if(!adminMfaSecurityGrantValid){sessionStorage.setItem('oneid_admin_step_up_pending_lifetime',String(minutes));sessionStorage.setItem('oneid_admin_step_up_pending_reason',reason);window.location.href='../page/admin-step-up?purpose=SECURITY_CONFIGURATION_CHANGE&return=admin_2fa';return;}
               persistAdminStepUpLifetime(minutes,reason);
            });
         }
         function persistAdminStepUpLifetime(minutes,reason){
            if(adminStepUpLifetimeSaving)return;adminStepUpLifetimeSaving=true;$('#admin_step_up_lifetime_save_button').prop('disabled',true);$('#admin_step_up_lifetime_save_label').text('Saving...');
            $.post('../lib/q_func',{admin_2fa_update_lifetime:'',lifetime_minutes:minutes,configuration_version:adminMfaConfigurationVersion,change_reason:reason},function(r){
               if(r&&Number(r.status)===1&&(r.code==='STEP_UP_LIFETIME_UPDATED'||r.code==='STEP_UP_LIFETIME_UNCHANGED')){adminStepUpLifetimeOriginal=Number(r.lifetime_minutes);adminMfaConfigurationVersion=Number(r.configuration_version);sessionStorage.removeItem('oneid_admin_step_up_pending_lifetime');sessionStorage.removeItem('oneid_admin_step_up_pending_reason');$('#admin_step_up_lifetime_reason').val('');$('#admin_step_up_lifetime_status').text('Tempoh semasa: '+adminStepUpLifetimeOriginal+' minit. Hanya grant baharu menggunakan nilai ini.');swal(r.code==='STEP_UP_LIFETIME_UPDATED'?'Lifetime saved':'No changes',(r.code==='STEP_UP_LIFETIME_UPDATED'?'Tempoh baharu terpakai pada grant selepas pengesahan berikutnya.':'Polisi sudah menggunakan nilai ini.')+'\nReference: '+r.correlation_id,r.code==='STEP_UP_LIFETIME_UPDATED'?'success':'info');}
               else{sessionStorage.removeItem('oneid_admin_step_up_pending_lifetime');sessionStorage.removeItem('oneid_admin_step_up_pending_reason');swal('Lifetime not saved','Code: '+(r&&r.code?r.code:'STEP_UP_RESPONSE_INVALID'),'error');}
            },'json').fail(function(xhr){var code=xhr.responseJSON&&xhr.responseJSON.code?xhr.responseJSON.code:'';if(xhr.status===403&&(code==='STEP_UP_REQUIRED'||code==='STEP_UP_EXPIRED'||code==='STEP_UP_PURPOSE_MISMATCH')){sessionStorage.setItem('oneid_admin_step_up_pending_lifetime',String(minutes));sessionStorage.setItem('oneid_admin_step_up_pending_reason',reason);window.location.href='../page/admin-step-up?purpose=SECURITY_CONFIGURATION_CHANGE&return=admin_2fa';}else{sessionStorage.removeItem('oneid_admin_step_up_pending_lifetime');sessionStorage.removeItem('oneid_admin_step_up_pending_reason');swal('Lifetime not saved','Server request failed. HTTP '+xhr.status+(code?'\nCode: '+code:''),'error');}}).always(function(){adminStepUpLifetimeSaving=false;$('#admin_step_up_lifetime_save_button').prop('disabled',false);$('#admin_step_up_lifetime_save_label').text('Save lifetime');});
         }
         function saveAdminMfaPreference(){
            if(adminMfaPreferenceOriginal===null||adminMfaPreferenceSaving)return;
            var factor=$('#admin_mfa_preferred_factor').val();
            if(factor===adminMfaPreferenceOriginal){swal('No changes','Kaedah pilihan sudah menggunakan '+adminMfaPreferenceLabel(factor)+'.','info');return;}
            if(!adminMfaSecurityGrantValid){sessionStorage.setItem('oneid_mfa_pending_factor',factor);window.location.href='../page/admin-step-up?purpose=SECURITY_CONFIGURATION_CHANGE&return=admin_2fa';return;}
            swal({title:'Simpan kaedah 2FA pilihan?',text:adminMfaPreferenceLabel(factor)+' akan dipaparkan dahulu pada pengesahan Administrator. Kaedah lain masih boleh dipilih.',type:'warning',showCancelButton:true,confirmButtonText:'Save preference',closeOnConfirm:false},function(){
               persistAdminMfaPreference(factor);
            });
         }
         function persistAdminMfaPreference(factor){
               if(adminMfaPreferenceSaving)return;adminMfaPreferenceSaving=true;
               $('#admin_mfa_preference_save_button').prop('disabled',true);$('#admin_mfa_preference_save_label').text('Saving...');
               $.post('../lib/q_func',{admin_mfa_set_preference:'',factor:factor},function(r){
                  if(r&&Number(r.status)===1&&r.code==='MFA_PREFERENCE_UPDATED'){adminMfaPreferenceOriginal=factor;adminMfaSecurityGrantValid=true;sessionStorage.removeItem('oneid_mfa_pending_factor');$('#admin_mfa_preference_status').text('Kaedah pilihan semasa: '+adminMfaPreferenceLabel(factor)+'.');swal('Preference saved','Kaedah ini akan dipaparkan dahulu pada pengesahan berikutnya.\nReference: '+r.correlation_id,'success');}
                  else{swal('Preference not saved','Code: '+(r&&r.code?r.code:'MFA_PREFERENCE_RESPONSE_INVALID'),'error');}
               },'json').fail(function(xhr){
                  var code=xhr.responseJSON&&xhr.responseJSON.code?xhr.responseJSON.code:'';
                  if(xhr.status===403&&(code==='STEP_UP_REQUIRED'||code==='STEP_UP_EXPIRED'||code==='STEP_UP_PURPOSE_MISMATCH')){swal({title:'Pengesahan diperlukan',text:'Sahkan Security Configuration Change sebelum menyimpan tetapan ini.',type:'warning',confirmButtonText:'Authenticate now',closeOnConfirm:true},function(){window.location.href='../page/admin-step-up?purpose=SECURITY_CONFIGURATION_CHANGE&return=admin_2fa';});}
                  else{swal('Preference not saved','Server request failed. HTTP '+xhr.status+'.','error');}
               }).always(function(){adminMfaPreferenceSaving=false;$('#admin_mfa_preference_save_button').prop('disabled',false);$('#admin_mfa_preference_save_label').text('Save preference');});
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
						$('#modal_user_profile_lastupdate_text').text(admin_format_datetime(response['u_update_datetime']));
         
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
                  if (Number(response.status) === 1){
                     get_service_provider_list();   
                     $.toast().reset('all');                    
                     $.toast({
                        heading: '',
                        text: 'App category successfully created. Reference: ' + response.correlation_id,
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
                        text: (response.code || 'Category was not created') + (response.correlation_id ? ' — Reference: ' + response.correlation_id : ''),
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

           function webAppManagementText(value){
            return $('<div>').text(value == null ? '' : String(value)).html();
           }

           function webAppManagementAttribute(value){
            return String(value == null ? '' : value).replace(/[&<>"']/g, function(character){
               return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character];
            });
           }

           function open_manage_webapp_categories(){
            $('#modal_manage_webapp_categories').modal('show');
            $('#category_manage_loading').show();
            $('#category_manage_list').hide().html('');
            $.ajax({
               type: 'POST',
               url: '../lib/q_func',
               dataType: 'json',
               data: {admin_get_app_all_group: ''},
               success: function(response){
                  $('#category_manage_loading').hide();
                  $('#category_manage_list').show();
                  if (!Array.isArray(response) || response.length === 0) {
                     $('#category_manage_list').html('<div class="category-manage-state"><i class="fa fa-folder-open-o" aria-hidden="true"></i><span>No categories available.</span></div>');
                     return;
                  }
                  var rows = '';
                  $.each(response, function(_, category){
                     var categoryId = webAppManagementAttribute(category.sp_group_id);
                     var categoryName = webAppManagementText(category.sp_group_name);
                     var categoryNameAttribute = webAppManagementAttribute(category.sp_group_name);
                     var activeCount = Number(category.active_count || 0);
                     var inactiveCount = Number(category.inactive_count || 0);
                     var assignedCount = Number(category.assigned_count || 0);
                     var isSystem = String(category.sp_group_id) === '0';
                     var canRemove = !isSystem && assignedCount === 0;
                     var reason = isSystem
                        ? 'System category — protected'
                        : (assignedCount > 0 ? assignedCount + ' assigned app(s) must be moved first' : 'Empty category — eligible for removal');
                     rows += '<div class="category-manage-row">';
                     rows += '<div class="category-manage-name"><strong title="'+categoryName+'">'+categoryName+'</strong><small>'+webAppManagementText(reason)+'</small></div>';
                     rows += '<div class="category-manage-counts"><span><strong>'+activeCount+'</strong><small>Active</small></span><span><strong>'+inactiveCount+'</strong><small>Inactive</small></span></div>';
                     rows += '<div class="category-manage-actions">';
                     rows += '<button type="button" class="category-manage-edit" data-category-id="'+categoryId+'" data-category-name="'+categoryNameAttribute+'" '+(isSystem ? 'disabled' : '')+' title="'+(isSystem ? 'System category — protected' : 'Edit category name')+'" aria-label="'+(isSystem ? 'System category — protected' : 'Edit '+categoryNameAttribute)+'"><i class="fa fa-pencil" aria-hidden="true"></i></button>';
                     rows += '<button type="button" class="category-manage-remove" data-category-id="'+categoryId+'" data-category-name="'+categoryNameAttribute+'" '+(canRemove ? '' : 'disabled')+' title="'+(canRemove ? 'Remove empty category' : webAppManagementAttribute(reason))+'" aria-label="'+(canRemove ? 'Remove '+categoryNameAttribute : webAppManagementAttribute(reason))+'"><i class="fa fa-trash" aria-hidden="true"></i></button>';
                     rows += '</div>';
                     rows += '</div>';
                  });
                  $('#category_manage_list').html(rows);
               },
               error: function(xhr){
                  $('#category_manage_loading').hide();
                  $('#category_manage_list').show().html('<div class="category-manage-state is-error"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i><span>Unable to load categories. HTTP '+xhr.status+'.</span></div>');
               }
            });
           }

           $(document).on('click', '.category-manage-edit:not(:disabled)', function(){
            $('#edit_webapp_category_id').val(String($(this).data('category-id') || ''));
            $('#edit_webapp_category_name').val(String($(this).data('category-name') || ''));
            $('#modal_manage_webapp_categories').modal('hide');
            $('#modal_edit_webapp_category').modal('show');
           });

           $('#modal_edit_webapp_category').on('hidden.bs.modal', function(){
            open_manage_webapp_categories();
           });

           $('#form_edit_webapp_category').on('submit', function(event){
            event.preventDefault();
            var categoryId = $('#edit_webapp_category_id').val();
            var categoryName = $('#edit_webapp_category_name').val().replace(/\s+/g, ' ').trim();
            if (!categoryName || categoryName.length > 100) {
               oneidToast('Category not renamed', 'Enter a category title between 1 and 100 characters.', 'error');
               return;
            }
            oneidConfirm(
               'Rename application category?',
               'Save the category name as "' + categoryName + '"? Assigned applications will remain in this category.',
               'Save changes',
               function(){
                  var button = $('#btn_save_webapp_category').prop('disabled', true);
                  $.ajax({
                     type: 'POST',
                     url: '../lib/q_func',
                     dataType: 'json',
                     data: {
                        action_rename_webapp_category: '',
                        app_category_id: categoryId,
                        app_category_name: categoryName
                     },
                     success: function(response){
                        if (Number(response.status) === 1 && response.code === 'W5_CATEGORY_RENAMED') {
                           $('#modal_edit_webapp_category').modal('hide');
                           oneidToast('Category renamed', 'The category name was updated. Reference: ' + response.correlation_id, 'success');
                           get_service_provider_list();
                           return;
                        }
                        oneidToast('Category not renamed', 'Code: ' + (response.code || 'W5_REQUEST_FAILED') + '. Reference: ' + (response.correlation_id || 'Unavailable'), 'error');
                     },
                     error: function(xhr){
                        oneidToast('Category not renamed', 'The server request failed. HTTP ' + xhr.status + '.', 'error');
                     },
                     complete: function(){ button.prop('disabled', false); }
                  });
               }
            );
           });

           $(document).on('click', '.category-manage-remove:not(:disabled)', function(){
            var categoryId = String($(this).data('category-id') || '');
            var categoryName = String($(this).data('category-name') || '');
            swal({
               title: 'Remove empty category?',
               text: 'Category: ' + categoryName + '. The server will reject this action if any application is assigned.',
               type: 'warning',
               showCancelButton: true,
               confirmButtonColor: '#DD6B55',
               confirmButtonText: 'Remove category',
               closeOnConfirm: false
            }, function(){
               $.ajax({
                  type: 'POST',
                  url: '../lib/q_func',
                  dataType: 'json',
                  data: {action_remove_app_category: '', app_category_id: categoryId},
                  success: function(response){
                     if (Number(response.status) === 1 && response.code === 'W1_CATEGORY_REMOVED') {
                        swal('Category removed', 'Reference: ' + response.correlation_id, 'success');
                        get_service_provider_list();
                        open_manage_webapp_categories();
                        return;
                     }
                     var assigned = response.context && response.context.assigned_count
                        ? '\nAssigned applications: ' + response.context.assigned_count : '';
                     swal('Category not removed', 'Code: ' + (response.code || 'W1_REQUEST_FAILED') + assigned + '\nReference: ' + (response.correlation_id || 'Unavailable'), 'error');
                     open_manage_webapp_categories();
                  },
                  error: function(xhr){
                     swal('Category not removed', 'The server request failed. HTTP ' + xhr.status + '.', 'error');
                  }
               });
            });
           });
         
         
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
         
         
           function setAppFormSubmitting(buttonSelector, submitting, idleLabel, busyLabel){
              var button = $(buttonSelector);
              button.prop('disabled', submitting).attr('aria-busy', submitting ? 'true' : 'false');
              button.find('.submit-label').text(submitting ? busyLabel : idleLabel);
           }

           function showAppOperationalAlert(type, title, message, response){
              var code = response && response.code ? String(response.code) : 'WA1_REQUEST_FAILED';
              var reference = response && response.correlation_id ? String(response.correlation_id) : 'Unavailable';
              swal(title, message + '\nCode: ' + code + '\nReference: ' + reference, type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'error'));
           }

           function appClientReference(){
              if (window.crypto && window.crypto.getRandomValues) {
                 var bytes = new Uint8Array(8);
                 window.crypto.getRandomValues(bytes);
                 return Array.prototype.map.call(bytes, function(value){ return ('0' + value.toString(16)).slice(-2); }).join('');
              }
              return 'client-' + String(Date.now());
           }

           function validateSelectedAppIcon(file){
              if (!file) {
                 return true;
              }
              var allowed = ['image/jpeg','image/png','image/gif','image/webp'];
              if (allowed.indexOf(String(file.type || '').toLowerCase()) === -1) {
                 showAppOperationalAlert('error', 'Icon was not accepted.', 'Select a JPEG, PNG, GIF or WebP image. No application changes were sent.', {code:'WA3_CLIENT_ICON_TYPE_REJECTED',correlation_id:appClientReference()});
                 return false;
              }
              if (Number(file.size || 0) <= 0 || Number(file.size) > 5242880) {
                 showAppOperationalAlert('error', 'Icon was not accepted.', 'The selected image must not exceed 5 MB. No application changes were sent.', {code:'WA3_CLIENT_ICON_SIZE_REJECTED',correlation_id:appClientReference()});
                 return false;
              }
              return true;
           }

           function appTransportErrorResponse(xhr){
              if (xhr && Number(xhr.status) === 413) {
                 return {code:'WA3_UPLOAD_REQUEST_TOO_LARGE',correlation_id:appClientReference()};
              }
              return xhr && xhr.responseJSON ? xhr.responseJSON : null;
           }

           var addAppSubmitting = false;
           var form_add_new_app = $('#form_add_new_app');
           form_add_new_app.on('submit', function(ev){
              ev.preventDefault();

            if (addAppSubmitting) {
               return;
            }

            var data = new FormData();

              // 1. Add serialized form fields
              var serializedData = $(this).serializeArray();
              $.each(serializedData, function(i, field) {
                data.append(field.name, field.value);
              });

              // 2. Add file input
              var fileInput = $('#add_new_app_icon')[0];
              if (fileInput.files.length > 0) {
                if (!validateSelectedAppIcon(fileInput.files[0])) {
                  return;
                }
                data.append('app_icon', fileInput.files[0]);
              }

              // 3. Optional extra field
              data.append('action_add_new_app', '');
              // return;

			// var data = $('#form_add_new_app').serializeArray();
			// data.push({name: 'action_add_new_app', value: ''});
            swal({
               title: 'Add App',
               text: 'Create this application using the entered metadata and optional icon?',
               type: 'warning',
               showCancelButton: true,
               confirmButtonText: 'Yes, add app',
               closeOnConfirm: true
            }, function(confirmed){
              if (!confirmed || addAppSubmitting) {
                 return;
              }
			  $.ajax({
           		type: 'POST',
           		url: '../lib/q_func',
           		dataType: "json",
           		data:data,
               processData: false, // important
               contentType: false, // important
				beforeSend: function(){
                  addAppSubmitting = true;
                  setAppFormSubmitting('#btn_add_app_submit', true, 'Add App', 'Adding...');
				},
				success: function (response) {
					if (Number(response.status) === 1){
						get_service_provider_list();
						var iconRejected = response.icon_status === 'rejected';
                  showAppOperationalAlert(iconRejected ? 'warning' : 'success', iconRejected ? 'App added without the selected icon.' : 'App successfully added.', iconRejected ? 'The selected icon was rejected. The application record was created without a custom icon.' : (response.icon_status === 'stored' ? 'The application metadata and icon were saved.' : 'The application metadata was saved using the default icon.'), response);
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
                     var newInput = $('<input type="file" id="add_new_app_icon" name="app_icon" class="dropify" data-default-file="../img/thumb-1.jpg" data-height="100" accept="image/jpeg,image/png,image/gif,image/webp">');
                     $('#add_new_app_icon').replaceWith(newInput); // swap old input
                     // 4. Re-initialize Dropify
                     newInput.dropify();

					}else{
                  showAppOperationalAlert('error', 'App was not added.', 'The server did not create the application record.', response);
					}
         
           		},
				error: function (xhr, error, thrown) {
					showAppOperationalAlert('error', 'App was not added.', Number(xhr && xhr.status) === 413 ? 'The web server rejected the request because it exceeded the configured request limit.' : 'The request failed before a valid result was received.', appTransportErrorResponse(xhr));
				},
               complete: function(){
                  addAppSubmitting = false;
                  setAppFormSubmitting('#btn_add_app_submit', false, 'Add App', 'Adding...');
				}
			  });
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
                     var newInput = $('<input type="file" id="edit_new_app_icon" name="app_icon" class="dropify" data-default-file="../public_img/'+response['sp_image']+'" data-height="100" accept="image/jpeg,image/png,image/gif,image/webp">');
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
                     var newInput = $('<input type="file" id="edit_new_app_icon" name="app_icon" class="dropify" data-default-file="../img/thumb-1.jpg" data-height="100" accept="image/jpeg,image/png,image/gif,image/webp">');
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
         
         
           var editAppSubmitting = false;
           var form_edit_app = $('#form_edit_app');
           form_edit_app.on('submit', function(ev){
              ev.preventDefault();
            if (editAppSubmitting) {
               return;
            }
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
                if (!validateSelectedAppIcon(fileInput.files[0])) {
                  return;
                }
                data.append('app_icon', fileInput.files[0]);
              }else {
                 // No new image uploaded — send info about the existing one
                 var existingIcon = $('#edit_existing_app_icon').val();
                 data.append('existing_app_icon', existingIcon);
               }

              // 3. Optional extra field
              data.append('action_edit_app_info', '');

            swal({
               title: 'Save App Changes',
               text: 'Apply these metadata and optional icon changes to this application?',
               type: 'warning',
               showCancelButton: true,
               confirmButtonText: 'Yes, save changes',
               closeOnConfirm: true
            }, function(confirmed){
              if (!confirmed || editAppSubmitting) {
                 return;
              }
			  $.ajax({
           		type: 'POST',
           		url: '../lib/q_func',
           		dataType: "json",
           		data:data,
               processData: false, // important
               contentType: false, // important
				beforeSend: function(){
                  editAppSubmitting = true;
                  setAppFormSubmitting('#btn_edit_app_submit', true, 'Save Changes', 'Saving...');
				},
				success: function (response) {
					if (Number(response.status) === 1){
						get_service_provider_list();
						var iconRejected = response.icon_status === 'rejected';
                  showAppOperationalAlert(iconRejected ? 'warning' : 'success', iconRejected ? 'Metadata saved; selected icon not changed.' : 'App successfully updated.', iconRejected ? 'The selected icon was rejected and the previous icon was retained.' : (response.icon_status === 'stored' ? 'The metadata and replacement icon were saved.' : 'The metadata was saved and the existing icon was retained.'), response);
					}else{
                  var unchanged = response && response.code === 'WA3_APP_UNCHANGED';
                  showAppOperationalAlert(unchanged ? 'warning' : 'error', unchanged ? 'No changes saved.' : 'App changes were not saved.', unchanged ? 'The stored record already matched the submitted values.' : 'The operation was rejected or rolled back. No completed update was recorded.', response);
					}
         
           		},
				error: function (xhr, error, thrown) {
					showAppOperationalAlert('error', 'App changes were not saved.', Number(xhr && xhr.status) === 413 ? 'The web server rejected the request because it exceeded the configured request limit.' : 'The request failed before a valid result was received.', appTransportErrorResponse(xhr));
				},
               complete: function(){
                  editAppSubmitting = false;
                  setAppFormSubmitting('#btn_edit_app_submit', false, 'Save Changes', 'Saving...');
				}
			  });
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
                  if (Number(response.status) === 1){
                    $('#modal_edit_app').modal('hide');
                    get_service_provider_list();
                    swal("App Removed", "Access references were revoked. Reference: " + response.correlation_id, "success");
                  }else{
                    swal("App Not Removed", (response.code || "Request failed") + (response.correlation_id ? "\nReference: " + response.correlation_id : ""), "error");
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
                        $('#modal_user_profile_lastupdate_text').text(admin_format_datetime(response['u_update_datetime']));
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
         refresh_external_sync_notifications();
         }

         function external_action_total(actionCounts, sourceCode){
            var total = 0;
            var counts = actionCounts[sourceCode] || {};
            var actionable = [
               'NEW',
               'CANDIDATE_NEW',
               'UPDATE',
               'REACTIVATE',
               'DEACTIVATE',
               'CANDIDATE_DEACTIVATE',
               'ADD_MEMBERSHIP'
            ];
            actionable.forEach(function(action){
               total += Number(counts[action] || 0);
            });
            return total;
         }

         function show_external_action_notice(selector, count, blocked){
            var notice = $(selector);
            if(count < 1 && blocked !== true){
               notice.hide().empty();
               return;
            }
            notice
               .toggleClass('external-action-notice-danger', blocked === true)
               .attr('title', blocked === true
                  ? 'Sumber memerlukan semakan admin'
                  : count + ' perubahan memerlukan tindakan admin')
               .html(
                  '<i class="fa '
                  + (blocked === true ? 'fa-exclamation-triangle' : 'fa-bell')
                  + '"></i> ' + (count > 0 ? String(count) : '!')
               )
               .show();
         }

         function sync_admin_warning_text(warning){
            var messages = {
               'SOURCE_BASELINE_UNAVAILABLE': 'Perbandingan dengan jumlah rekod terdahulu tidak tersedia.',
               'Invalid external rows were excluded from the preview.': 'Sebahagian rekod sumber tidak lengkap atau tidak sah dan tidak akan diproses.',
               'Policy-excluded identities were excluded from the preview.': 'Sebahagian rekod tidak memenuhi polisi sinkronisasi dan tidak akan diproses.',
               'Deactivation threshold exceeded; apply must remain blocked.': 'Jumlah akaun yang akan dinyahaktifkan melebihi had keselamatan. Proses tidak boleh diteruskan.',
               'External identities colliding with protected manual accounts were excluded.': 'Terdapat konflik dengan akaun yang ditambah secara manual. Rekod tersebut dilindungi dan tidak akan diproses.'
            };
            return messages[String(warning)] || String(warning);
         }

         function refresh_external_sync_notifications(){
            $.ajax({
               type: 'POST',
               url: '../lib/q_func',
               dataType: 'json',
               data: {admin_preview_odl_shadow:''},
               success: function(response){
                  if(!response || response.status !== 1){
                     $('.external-action-notice').hide().empty();
                     return;
                  }
                  var actions = response.sync_action_counts || {};
                  var blocks = response.blocking_codes || [];
                  var staff = external_action_total(actions, 'STAFF_HR');
                  var ug = external_action_total(actions, 'STUDENT_UG');
                  var odl = external_action_total(actions, 'STUDENT_ODL_PG');
                  var staffBlocked = blocks.some(function(code){
                     return String(code).indexOf('STAFF_HR_') === 0;
                  });
                  var ugBlocked = blocks.some(function(code){
                     return String(code).indexOf('STUDENT_UG_') === 0;
                  });
                  var odlBlocked = blocks.some(function(code){
                     return String(code).indexOf('ODL_') === 0;
                  });
                  show_external_action_notice('#external_notice_staff', staff, staffBlocked);
                  show_external_action_notice('#external_notice_ug', ug, ugBlocked);
                  show_external_action_notice('#external_notice_odl', odl, odlBlocked);
                  show_external_action_notice(
                     '#external_notice_summary',
                     staff + ug + odl,
                     blocks.length > 0
                  );
               },
               error: function(){
                  $('.external-action-notice').hide().empty();
               }
            });
         }

         $(document).on('click', '.oneid-return-add-user-options', function(){
            $(this).closest('.modal').data('return-add-user-options', true);
         });

         $('#modal_add_new_single_user, #modal_odl_shadow_preview, #modal_add_new_user_manual')
            .on('hidden.bs.modal', function(){
               var childModal = $(this);
               if(childModal.data('return-add-user-options') !== true){
                  return;
               }
               childModal.removeData('return-add-user-options');
               window.setTimeout(function(){
                  $('#modal_open_add_user_option').modal('show');
               }, 100);
            });
         
         function pick_preview_sync_user(sourceCode){
            var sourceLabels = {
               STAFF_HR: 'Sinkronisasi Pengguna Staf',
               STUDENT_UG: 'Sinkronisasi Pelajar Prasiswazah',
               STUDENT_ODL_PG: 'Sinkronisasi Pelajar ODL'
            };
            if(!sourceLabels[sourceCode]){
               return;
            }
            var pilotApprovalId = '';
            var fullApprovalId = '';
            var fullConfirmation = '';
            var operationalApprovalId = '';
            var operationalConfirmation = '';
            $.ajax({
               type: 'POST',
               url: '../lib/q_func',
               dataType: "json",
               data: {
                  admin_preview_sync_user:'',
                  sync_source_code:sourceCode
               },
               beforeSend: function(){
                  $('#btn_sync').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Previewing...');
                  $('#sync_status_msg').show().text('Generating read-only preview...');
                  $('#modal_open_add_user_option').modal('hide');
                  $('#modal_add_new_single_user').modal('show');
                  $('#aria_modal_add_new_single_user').text(
                     sourceLabels[sourceCode] + ' — Semak dan Laksanakan'
                  );
                  $('#sync_progress_id').show();
                  $('#sync_result_div').hide();
               },
               success: function (response) {
                  $('#sync_progress_id').hide();
                  $('#sync_result_div').show();
                  if(!response || response.status !== 1 || response.mode !== 'preview'){
                     var reference = response && response.correlation_id ? ' Rujukan: ' + response.correlation_id : '';
                     $('#sync_admin_summary').removeClass('alert-info alert-warning alert-success')
                        .addClass('alert-danger')
                        .html('<strong>Semakan tidak berjaya.</strong> Tiada perubahan data dilaksanakan.' + reference);
                     $('#sync_preview_status').text('SEMAKAN GAGAL' + reference).addClass('badge badge-danger');
                     return;
                  }
                  var counts = response.counts || {};
                  var pilotCounts = response.pilot_counts || {};
                  var safetyMetrics = response.safety_metrics || {};
                  var totalChanges = Number(counts.New || 0)
                     + Number(counts.Update || 0)
                     + Number(counts.Deactivate || 0)
                     + Number(counts.Reactivate || 0);
                  $('#sync_preview_source_rows').text(
                     (response.source_rows || 0) + ' (' + sourceLabels[sourceCode] + ')'
                  );
                  $('#sync_preview_new').text(counts.New || 0);
                  $('#sync_preview_update').text(counts.Update || 0);
                  $('#sync_preview_deactivate').text(counts.Deactivate || 0);
                  $('#sync_preview_reactivate').text(counts.Reactivate || 0);
                  $('#sync_preview_protected_manual').text(response.protected_manual_users || 0);
                  $('#sync_preview_collisions').text(response.discarded_protected_collisions || 0);
                  $('#sync_preview_hash_expiry')
                     .css({'overflow-wrap':'anywhere','word-break':'break-all'})
                     .text(String(response.plan_hash || '-') + ' / ' + (response.expires_at || '-'));
                  $('#sync_preview_status')
                     .removeClass('badge-danger badge-warning badge-success')
                     .addClass(response.risk_level === 'blocked' ? 'badge badge-danger' : (response.risk_level === 'warning' ? 'badge badge-warning' : 'badge badge-success'))
                     .text(response.risk_level === 'blocked'
                        ? 'PROSES DISEKAT — semakan lanjut diperlukan'
                        : (totalChanges === 0
                           ? 'TIADA PERUBAHAN DIPERLUKAN'
                           : (response.approval_ready === true
                           ? (sourceCode === 'STUDENT_ODL_PG'
                              ? 'PERUBAHAN TERSEDIA — KELULUSAN DIPERLUKAN'
                              : 'PERUBAHAN TERSEDIA — PELAKSANAAN BELUM DIBENARKAN')
                           : 'SEMAKAN SAHAJA — TIADA PERUBAHAN DILAKSANAKAN')));
                  $('#sync_admin_summary')
                     .removeClass('alert-danger alert-warning alert-success alert-info')
                     .addClass(response.risk_level === 'blocked'
                        ? 'alert-danger'
                        : (totalChanges === 0 ? 'alert-success' : 'alert-warning'))
                     .html(response.risk_level === 'blocked'
                        ? '<strong>Sinkronisasi tidak boleh diteruskan.</strong> Sila semak bahagian Perhatian atau hubungi pegawai teknikal.'
                        : (totalChanges === 0
                           ? '<strong>Data telah diselaraskan.</strong> Tiada tindakan diperlukan oleh admin.'
                           : '<strong>' + totalChanges + ' perubahan memerlukan semakan admin.</strong> Pastikan semua bilangan di bawah adalah betul sebelum meneruskan.'));
                  var warningList = $('#sync_preview_warnings').empty();
                  (response.warnings || []).forEach(function(warning){
                     $('<li>').text(sync_admin_warning_text(warning)).appendTo(warningList);
                  });
                  if((response.warnings || []).length === 0){
                     $('<li>').text('Tiada perkara yang memerlukan perhatian.').appendTo(warningList);
                  }
                  $('#btn_apply_sync_pilot').hide().prop('disabled', true);
                  $('#btn_apply_sync_full').hide().prop('disabled', true);
                  $('#btn_apply_sync_operational').hide().prop('disabled', true);
                  $('#sync_full_confirmation_group').hide();
                  $('#sync_full_confirmation').val('');
                  pilotApprovalId = '';
                  fullApprovalId = '';
                  fullConfirmation = '';
                  operationalApprovalId = '';
                  operationalConfirmation = '';
                  if(response.pilot_apply_available === true
                     && typeof response.approval_id === 'string'
                     && response.approval_id.length === 64
                     && (pilotCounts.New || 0) === 2
                     && (pilotCounts.Update || 0) === 1
                     && (pilotCounts.Deactivate || 0) === 0
                     && (pilotCounts.Reactivate || 0) === 0){
                     pilotApprovalId = response.approval_id;
                     $('#btn_apply_sync_pilot').show().prop('disabled', false);
                     $('#sync_pilot_notice').text('Controlled pilot scope: exactly 2 New + 1 Update; no Deactivate or Reactivate. Approval expires at ' + (response.expires_at || '-') + '.');
                  } else {
                     $('#sync_pilot_notice').text(totalChanges === 0
                        ? 'Data sumber dan OneID telah diselaraskan. Tiada tindakan diperlukan.'
                        : 'Semakan sahaja. Pelaksanaan perubahan masih belum dibenarkan.');
                  }

                  if(response.full_apply_available === true
                     && typeof response.approval_id === 'string'
                     && response.approval_id.length === 64
                     && typeof response.full_confirmation === 'string'
                     && response.full_confirmation.length > 12){
                     fullApprovalId = response.approval_id;
                     fullConfirmation = response.full_confirmation;
                     $('#sync_full_confirmation_group').show();
                     $('#sync_full_confirmation_hint').text(fullConfirmation);
                     $('#btn_apply_sync_full').show();
                     $('#sync_preview_status').text('SEDIA UNTUK SINKRONISASI YANG DILULUSKAN');
                     $('#sync_pilot_notice').text('Kelulusan ini terikat pada bilangan perubahan dan rujukan pelan yang dipaparkan. Kelulusan sah sehingga ' + (response.expires_at || '-') + '.');
                  }

                  if(response.operational_apply_available === true
                     && typeof response.approval_id === 'string'
                     && response.approval_id.length === 64
                     && typeof response.operational_confirmation === 'string'
                     && response.operational_confirmation.length > 12){
                     operationalApprovalId = response.approval_id;
                     operationalConfirmation = response.operational_confirmation;
                     $('#sync_full_confirmation_group').show();
                     $('#sync_full_confirmation_hint').text(operationalConfirmation);
                     $('#btn_apply_sync_operational').show();
                     $('#sync_preview_status')
                        .removeClass('badge-success badge-danger badge-warning')
                        .addClass(response.operational_large_batch === true ? 'badge badge-warning' : 'badge badge-success')
                        .text(response.operational_large_batch === true
                           ? 'PERUBAHAN BESAR — PENGESAHAN TAMBAHAN DIPERLUKAN'
                           : ((counts.Deactivate || 0) > 0
                              ? 'SEDIA DILAKSANAKAN — PENGESAHAN NYAHAKTIF DIPERLUKAN'
                              : 'SEDIA UNTUK DILAKSANAKAN'));
                     $('#sync_pilot_notice').text((response.operational_large_batch === true
                        ? 'Jumlah perubahan adalah besar. Semak semua bilangan dengan teliti. '
                        : '') + 'Kelulusan sah sehingga ' + (response.expires_at || '-') + '.');
                  } else if(response.operational_hard_blocked === true){
                     var thresholds = response.operational_thresholds || {};
                     $('#sync_preview_status')
                        .removeClass('badge-success badge-warning')
                        .addClass('badge badge-danger')
                        .text('PROSES DISEKAT — JUMLAH NYAHAKTIF MELEBIHI HAD');
                     $('#sync_pilot_notice').text('Jumlah akaun yang akan dinyahaktifkan ialah ' + (counts.Deactivate || 0)
                        + ', melebihi had ' + (thresholds.max_deactivate || 0)
                        + '. Kelulusan khas diperlukan sebelum proses boleh diteruskan.');
                  }

                  $('#sync_full_confirmation').off('input').on('input', function(){
                     var typed = $(this).val().trim();
                     $('#btn_apply_sync_full').prop('disabled', typed !== fullConfirmation);
                     $('#btn_apply_sync_operational').prop('disabled', typed !== operationalConfirmation);
                  });

                  $('#btn_apply_sync_pilot').off('click').on('click', function(){
                     if(!pilotApprovalId){ return; }
                     var button = $(this);
                     oneidConfirm(
                        'Apply controlled pilot?',
                        'Apply exactly 2 New and 1 Update? No Deactivate or Reactivate will be allowed. This approval can be used once only.',
                        'Apply pilot',
                        function(){
                     button.prop('disabled', true).text('Applying controlled pilot...');
                     $.ajax({
                        type: 'POST',
                        url: '../lib/q_func',
                        dataType: 'json',
                        data: {
                           admin_add_sync_user:'',
                           sync_approval_id:pilotApprovalId,
                           sync_source_code:sourceCode
                        },
                        success: function(applyResponse){
                           pilotApprovalId = '';
                           button.hide();
                           if(applyResponse && applyResponse.status === 1){
                              var applied = applyResponse.counts || {};
                              oneidToast('Controlled pilot completed', 'Header ' + applyResponse.header_id + '; New=' + (applied.New || 0) + ', Update=' + (applied.Update || 0) + ', Deactivate=' + (applied.Deactivate || 0) + ', Reactivate=' + (applied.Reactivate || 0) + '.', 'success', {hideAfter: 7000});
                           } else {
                              var code = applyResponse && applyResponse.code ? applyResponse.code : 'SYNC_APPLY_FAILED';
                              oneidToast('Controlled pilot was not applied', 'Code: ' + code + '. Generate a fresh preview before retrying.', 'error');
                           }
                        },
                        error: function(){
                           pilotApprovalId = '';
                           button.hide();
                           oneidToast('Controlled pilot request failed', 'Generate a fresh preview and inspect server logs.', 'error');
                        }
                     });
                        }
                     );
                  });

                  $('#btn_apply_sync_full').off('click').on('click', function(){
                     var typedConfirmation = $('#sync_full_confirmation').val().trim();
                     if(!fullApprovalId || typedConfirmation !== fullConfirmation){ return; }
                     var button = $(this);
                     var summary = 'New=' + (counts.New || 0)
                        + ', Update=' + (counts.Update || 0)
                        + ', Deactivate=' + (counts.Deactivate || 0)
                        + ', Reactivate=' + (counts.Reactivate || 0) + '.';
                     oneidConfirm(
                        'Apply approved full sync?',
                        summary + ' The plan will be fetched and verified again before any database transaction.',
                        'Apply full sync',
                        function(){
                           button.prop('disabled', true).text('Applying full sync...');
                           $.ajax({
                              type: 'POST',
                              url: '../lib/q_func',
                              dataType: 'json',
                              data: {
                                 admin_apply_full_sync: '',
                                 sync_approval_id: fullApprovalId,
                                 full_sync_confirmation: typedConfirmation,
                                 sync_source_code: sourceCode
                              },
                              success: function(applyResponse){
                                 fullApprovalId = '';
                                 button.hide();
                                 $('#sync_full_confirmation_group').hide();
                                 if(applyResponse && applyResponse.status === 1){
                                    var applied = applyResponse.counts || {};
                                    var auditWarning = applyResponse.audit_marker_recorded === false ? ' Secondary audit marker failed; run reconciliation immediately.' : '';
                                    oneidToast('Full sync committed', 'Header ' + applyResponse.header_id + '; New=' + (applied.New || 0) + ', Update=' + (applied.Update || 0) + ', Deactivate=' + (applied.Deactivate || 0) + ', Reactivate=' + (applied.Reactivate || 0) + '.' + auditWarning, applyResponse.audit_marker_recorded === false ? 'warning' : 'success', {hideAfter: 10000});
                                 } else {
                                    var code = applyResponse && applyResponse.code ? applyResponse.code : 'SYNC_FULL_APPLY_FAILED';
                                    oneidToast('Full sync was not applied', 'Code: ' + code + '. Generate a fresh preview and review the plan.', 'error', {hideAfter: 8000});
                                 }
                              },
                              error: function(){
                                 fullApprovalId = '';
                                 button.hide();
                                 $('#sync_full_confirmation_group').hide();
                                 oneidToast('Full sync request failed', 'No success has been assumed. Inspect server logs before generating another preview.', 'error', {hideAfter: 8000});
                              }
                           });
                        }
                     );
                  });

                  $('#btn_apply_sync_operational').off('click').on('click', function(){
                     var typedConfirmation = $('#sync_full_confirmation').val().trim();
                     if(!operationalApprovalId || typedConfirmation !== operationalConfirmation){ return; }
                     var button = $(this);
                     var summary = 'New=' + (counts.New || 0)
                        + ', Update=' + (counts.Update || 0)
                        + ', Deactivate=' + (counts.Deactivate || 0)
                        + ', Reactivate=' + (counts.Reactivate || 0) + '.';
                     oneidConfirm(
                        response.operational_large_batch === true ? 'Laksanakan sinkronisasi berskala besar?' : 'Laksanakan sinkronisasi?',
                        summary + (response.operational_large_batch === true ? ' Jumlah perubahan adalah besar dan memerlukan pengesahan tambahan.' : '') + ' Sistem akan menyemak semula data sebelum melaksanakan perubahan. Kelulusan ini hanya boleh digunakan sekali.',
                        'Laksanakan',
                        function(){
                           button.prop('disabled', true).text('Sedang melaksanakan sinkronisasi...');
                           $.ajax({
                              type: 'POST',
                              url: '../lib/q_func',
                              dataType: 'json',
                              data: {
                                 admin_apply_operational_sync: '',
                                 sync_approval_id: operationalApprovalId,
                                 operational_sync_confirmation: typedConfirmation,
                                 sync_source_code: sourceCode
                              },
                              success: function(applyResponse){
                                 operationalApprovalId = '';
                                 button.hide();
                                 $('#sync_full_confirmation_group').hide();
                                 if(applyResponse && applyResponse.status === 1){
                                    var applied = applyResponse.counts || {};
                                    var auditWarning = applyResponse.audit_marker_recorded === false ? ' Rekod audit tambahan gagal direkodkan; jalankan semakan rekonsiliasi dengan segera.' : '';
                                    oneidToast('Sinkronisasi berjaya dilaksanakan', 'Rujukan ' + applyResponse.header_id + '; Baharu=' + (applied.New || 0) + ', Kemas kini=' + (applied.Update || 0) + ', Nyahaktif=' + (applied.Deactivate || 0) + ', Aktif semula=' + (applied.Reactivate || 0) + '.' + auditWarning, applyResponse.audit_marker_recorded === false ? 'warning' : 'success', {hideAfter: 10000});
                                 } else {
                                    var code = applyResponse && applyResponse.code ? applyResponse.code : 'SYNC_OPERATIONAL_APPLY_FAILED';
                                    oneidToast('Sinkronisasi tidak dilaksanakan', 'Rujukan teknikal: ' + code + '. Buat semakan baharu sebelum mencuba semula.', 'error', {hideAfter: 8000});
                                 }
                              },
                              error: function(){
                                 operationalApprovalId = '';
                                 button.hide();
                                 $('#sync_full_confirmation_group').hide();
                                 oneidToast('Permintaan sinkronisasi gagal', 'Tiada perubahan dianggap berjaya. Hubungi pegawai teknikal sebelum membuat semakan baharu.', 'error', {hideAfter: 8000});
                              }
                           });
                        }
                     );
                  });
               },
               error: function () {
                  $('#sync_progress_id').hide();
                  $('#sync_result_div').show();
                  $('#sync_admin_summary').removeClass('alert-info alert-warning alert-success')
                     .addClass('alert-danger')
                     .html('<strong>Semakan tidak berjaya.</strong> Tiada perubahan data dilaksanakan.');
                  $('#sync_preview_status').text('SEMAKAN GAGAL — SILA CUBA SEMULA').addClass('badge badge-danger');
               },
               complete: function(){
                  $('#btn_sync').prop('disabled', false).html(
                     '<i class="fa fa-search"></i> ' + sourceLabels[sourceCode]
                  );
                  $('#sync_status_msg').hide().text('');
               }
            });
         }

         function preview_external_sync_view(sourceView){
            var viewLabels = {
               SUMMARY: 'Ringkasan Sinkronisasi Pengguna',
               STAFF_HR: 'Sinkronisasi Pengguna Staf',
               STUDENT_UG: 'Sinkronisasi Pelajar Prasiswazah',
               STUDENT_ODL_PG: 'Sinkronisasi Pelajar ODL'
            };
            var progressLabels = {
               SUMMARY: 'Sedang menyediakan ringkasan sinkronisasi...',
               STAFF_HR: 'Sedang menyemak data Staf...',
               STUDENT_UG: 'Sedang menyemak data Pelajar Prasiswazah...',
               STUDENT_ODL_PG: 'Sedang menyemak data Pelajar ODL...'
            };
            var selectedView = viewLabels[sourceView] ? sourceView : 'SUMMARY';
            $.ajax({
               type: 'POST',
               url: '../lib/q_func',
               dataType: 'json',
               data: {admin_preview_odl_shadow:''},
               beforeSend: function(){
                  $('#btn_external_summary, .external-source-preview-button')
                     .prop('disabled', true);
                  $('#modal_open_add_user_option').modal('hide');
                  $('#modal_odl_shadow_preview').modal('show');
                  $('#aria_modal_odl_shadow_preview').text(
                     viewLabels[selectedView] + ' — Semakan Sahaja'
                  );
                  $('#external_preview_progress_text').text(
                     progressLabels[selectedView]
                  );
                  $('#external_preview_boundary_note').text(
                     selectedView === 'SUMMARY'
                        ? 'Ringkasan ini untuk semakan sahaja. Tiada perubahan data boleh dilaksanakan dari paparan ini.'
                        : 'Semakan sumber sahaja. Tiada perubahan data dilaksanakan.'
                  );
                  $('#odl_shadow_progress').show();
                  $('#odl_shadow_result').hide();
               },
               success: function(response){
                  $('#odl_shadow_progress').hide();
                  $('#odl_shadow_result').show();
                  if(!response || response.status !== 1 || response.mode !== 'odl_shadow_preview'){
                     var code = response && response.code ? response.code : 'ODL_SHADOW_PREVIEW_FAILED';
                     $('#external_preview_admin_summary')
                        .removeClass('alert-info alert-warning alert-success')
                        .addClass('alert-danger')
                        .html('<strong>Ringkasan tidak dapat disediakan.</strong> Tiada perubahan data dilaksanakan. Rujukan teknikal tersedia di bawah.');
                     $('#odl_shadow_status').removeClass('badge-success badge-warning')
                        .addClass('badge badge-danger').text('SEMAKAN GAGAL — TIADA DATA DIUBAH');
                     $('#odl_shadow_blocks').empty().append($('<li>').text(code));
                     return;
                  }
                  var rows = response.source_rows || {};
                  var metrics = response.metrics || {};
                  var sourceMetrics = metrics.sources || {};
                  var actionCounts = response.action_counts || {};
                  var syncActionCounts = response.sync_action_counts || {};
                  var membershipActions = actionCounts.membership || {};
                  var accountActions = actionCounts.account || {};
                  var membershipBySource = membershipActions.by_source || {};
                  var accountBySource = accountActions.by_source || {};
                  var membershipCounts = selectedView === 'SUMMARY'
                     ? (membershipActions.total || {})
                     : (membershipBySource[selectedView] || {});
                  var accountCounts = selectedView === 'SUMMARY'
                     ? (accountActions.total || {})
                     : (accountBySource[selectedView] || {});
                  var blocks = response.blocking_codes || [];
                  var selectedSyncActions = {};
                  if(selectedView === 'SUMMARY'){
                     Object.keys(syncActionCounts).forEach(function(sourceCode){
                        var sourceActions = syncActionCounts[sourceCode] || {};
                        Object.keys(sourceActions).forEach(function(action){
                           selectedSyncActions[action] =
                              Number(selectedSyncActions[action] || 0)
                              + Number(sourceActions[action] || 0);
                        });
                     });
                  } else {
                     selectedSyncActions = syncActionCounts[selectedView] || {};
                  }
                  var sourceSummary = [
                     {code: 'STAFF_HR', row: 'staff', blockPrefix: 'STAFF_HR_'},
                     {code: 'STUDENT_UG', row: 'ug', blockPrefix: 'STUDENT_UG_'},
                     {code: 'STUDENT_ODL_PG', row: 'odl', blockPrefix: 'ODL_'}
                  ];
                  var totalSummaryActions = 0;
                  var blockedSourceCount = 0;
                  sourceSummary.forEach(function(source){
                     var actionTotal = external_action_total(syncActionCounts, source.code);
                     var isBlocked = blocks.some(function(code){
                        return String(code).indexOf(source.blockPrefix) === 0;
                     });
                     totalSummaryActions += actionTotal;
                     blockedSourceCount += isBlocked ? 1 : 0;
                     $('#external_summary_' + source.row + '_rows').text(Number(rows[source.code] || 0));
                     $('#external_summary_' + source.row + '_actions').text(actionTotal);
                     $('#external_summary_' + source.row + '_status')
                        .removeClass('text-danger text-warning text-success')
                        .addClass(isBlocked
                           ? 'text-danger'
                           : (actionTotal > 0 ? 'text-warning' : 'text-success'))
                        .html(isBlocked
                           ? '<i class="fa fa-exclamation-circle"></i> Semakan diperlukan'
                           : (actionTotal > 0
                              ? '<i class="fa fa-bell"></i> Tindakan diperlukan'
                              : '<i class="fa fa-check-circle"></i> Terkini'));
                  });
                  $('#external_preview_admin_summary')
                     .removeClass('alert-danger alert-warning alert-success alert-info')
                     .addClass(blockedSourceCount > 0
                        ? 'alert-danger'
                        : (totalSummaryActions > 0 ? 'alert-warning' : 'alert-success'))
                     .html(blockedSourceCount > 0
                        ? '<strong>' + blockedSourceCount + ' sumber memerlukan semakan.</strong> Jangan jalankan sinkronisasi bagi sumber yang bermasalah.'
                        : (totalSummaryActions > 0
                           ? '<strong>' + totalSummaryActions + ' perubahan memerlukan tindakan admin.</strong> Buka sumber berkenaan untuk membuat semakan.'
                           : '<strong>Semua sumber telah disemak.</strong> Tiada perubahan yang perlu dilaksanakan.'));
                  if(selectedView === 'SUMMARY'){
                     $('#external_preview_rows_label').text('Jumlah rekod (Staf / Prasiswazah / ODL):');
                     $('#odl_shadow_rows').text(
                        Number(rows.STAFF_HR || 0) + ' / '
                        + Number(rows.STUDENT_UG || 0) + ' / '
                        + Number(rows.STUDENT_ODL_PG || 0)
                     );
                     $('#external_preview_health').text(
                        'Staff ' + String((sourceMetrics.STAFF_HR || {}).status || 'unknown').toUpperCase()
                        + ' (' + Number((sourceMetrics.STAFF_HR || {}).shrink_percent || 0) + '%)'
                        + ' / UG ' + String((sourceMetrics.STUDENT_UG || {}).status || 'unknown').toUpperCase()
                        + ' (' + Number((sourceMetrics.STUDENT_UG || {}).shrink_percent || 0) + '%)'
                        + ' / ODL ' + String((sourceMetrics.STUDENT_ODL_PG || {}).status || 'unknown').toUpperCase()
                        + ' (' + Number((sourceMetrics.STUDENT_ODL_PG || {}).shrink_percent || 0) + '%)'
                     );
                  } else {
                     var selectedMetrics = sourceMetrics[selectedView] || {};
                     $('#external_preview_rows_label').text('Source rows:');
                     $('#odl_shadow_rows').text(Number(rows[selectedView] || 0));
                     $('#external_preview_health').text(
                        String(selectedMetrics.status || 'unknown').toUpperCase()
                        + ' / ' + Number(selectedMetrics.shrink_percent || 0) + '%'
                     );
                  }
                  $('#external_preview_sync_actions').text(
                     'Baharu ' + Number(
                        Number(selectedSyncActions.NEW || 0)
                        + Number(selectedSyncActions.CANDIDATE_NEW || 0)
                     )
                     + ' / Kemas kini ' + Number(selectedSyncActions.UPDATE || 0)
                     + ' / Aktif semula ' + Number(selectedSyncActions.REACTIVATE || 0)
                     + ' / Nyahaktif ' + Number(
                        Number(selectedSyncActions.DEACTIVATE || 0)
                        + Number(selectedSyncActions.CANDIDATE_DEACTIVATE || 0)
                     )
                  );
                  $('#odl_shadow_membership').text(
                     Number(membershipCounts.KEEP_MEMBERSHIP_ACTIVE || 0) + ' / '
                     + Number(membershipCounts.ADD_MEMBERSHIP || 0)
                  );
                  $('#odl_shadow_candidates').text(
                     Number(accountCounts.CANDIDATE_NEW || 0) + ' / '
                     + Number(accountCounts.CANDIDATE_DEACTIVATE || 0)
                  );
                  $('#odl_shadow_keep_active').text(
                     Number(accountCounts.KEEP_ACCOUNT_ACTIVE || 0)
                  );
                  $('#odl_shadow_safety').text(
                     String(response.risk_level || 'blocked').toUpperCase()
                     + ' / DISABLED / ' + Number(response.mutation_statements || 0)
                  );
                  $('#odl_shadow_digest').css({
                     'overflow-wrap':'anywhere',
                     'word-break':'break-all'
                  }).text(String(response.preview_digest || '-'));
                  $('#odl_shadow_status')
                     .removeClass('badge-danger badge-warning badge-success')
                     .addClass(response.risk_level === 'normal'
                        ? 'badge badge-success' : 'badge badge-warning')
                     .text(response.risk_level === 'normal'
                        ? 'SEMAKAN BERJAYA — BACA SAHAJA'
                        : 'PROSES DISEKAT — SEMAKAN DIPERLUKAN');
                  var blockList = $('#odl_shadow_blocks').empty();
                  if(blocks.length === 0){
                     $('<li>').text('Tiada sebab sekatan dikesan.').appendTo(blockList);
                  } else {
                     blocks.forEach(function(block){
                        $('<li>').text(block).appendTo(blockList);
                     });
                  }
               },
               error: function(xhr){
                  $('#odl_shadow_progress').hide();
                  $('#odl_shadow_result').show();
                  var response = xhr.responseJSON || null;
                  if(!response && xhr.responseText){
                     try {
                        response = JSON.parse(xhr.responseText);
                     } catch(ignore) {}
                  }
                  var code = response && response.code
                     ? response.code
                     : 'HTTP_' + String(xhr.status || 0);
                  var correlation = response && response.correlation_id
                     ? ' — Reference: ' + response.correlation_id : '';
                  $('#odl_shadow_status').removeClass('badge-success badge-warning')
                     .addClass('badge badge-danger').text('SEMAKAN GAGAL — TIADA DATA DIUBAH');
                  $('#external_preview_admin_summary')
                     .removeClass('alert-info alert-warning alert-success')
                     .addClass('alert-danger')
                     .html('<strong>Ringkasan tidak dapat disediakan.</strong> Tiada perubahan data dilaksanakan. Hubungi pegawai teknikal dengan rujukan di bawah.');
                  $('#odl_shadow_blocks').empty()
                     .append($('<li>').text(code + correlation));
               },
               complete: function(){
                  $('#btn_external_summary, .external-source-preview-button')
                     .prop('disabled', false);
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
         
         
         function admin_format_datetime(value){
            if(value === null || value === undefined || value === '' || value === '-'){
               return '-';
            }
            var text = String(value).trim();
            var match = text.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
            if(match){
               return match[3] + '/' + match[2] + '/' + match[1] + ' ' + (match[4] || '00') + ':' + (match[5] || '00') + ':' + (match[6] || '00');
            }
            match = text.match(/^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?/);
            if(match){
               return match[1] + '/' + match[2] + '/' + match[3] + ' ' + (match[4] || '00') + ':' + (match[5] || '00') + ':' + (match[6] || '00');
            }
            return text;
         }

         var activeSessionPage = 1;

         function render_active_session_pagination(meta){
            var page = Number(meta.page || 1);
            var pages = Number(meta.total_pages || 1);
            var total = Number(meta.total || 0);
            if(total === 0){
               $('#active_session_pagination').html('');
               return;
            }
            $('#active_session_pagination').html(
               '<button type="button" data-active-page="'+(page-1)+'" '+(page <= 1 ? 'disabled' : '')+' aria-label="Previous session page"><i class="fa fa-chevron-left" aria-hidden="true"></i></button>'+
               '<span>Page '+page+' of '+pages+'</span>'+
               '<button type="button" data-active-page="'+(page+1)+'" '+(page >= pages ? 'disabled' : '')+' aria-label="Next session page"><i class="fa fa-chevron-right" aria-hidden="true"></i></button>'
            );
         }

         function render_active_session_metrics(metrics){
            $.each(['current','active','refresh','grace','due','expired'], function(i, state){
               $('#active_metric_'+state).text(Number((metrics || {})[state] || 0));
            });
         }

         function get_all_user_activ_session(page){
         activeSessionPage = Number(page || activeSessionPage || 1);
         $.ajax({
          type: 'POST',
          url: '../lib/q_func',
          dataType: "json",
          data: {
             admin_get_all_token_for_all_active_user:"",
             page:String(activeSessionPage),
             page_size:String($('#active_session_page_size').val() || '25'),
             query:String($('#active_session_query').val() || '').trim(),
             status:String($('#active_session_status').val() || 'all')
          },
          beforeSend: function(){
				$('#active_session_count').text('\u2014');
				$('#active_session_pagination').html('');
				render_active_session_metrics({});
				$('#app_security_session_list').show();
				$('#security_tab_session').html(
					'<tr class="active-session-state-row is-loading"><td colspan="5">' +
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
				var sessionAttribute = function(value){
					return sessionText(value).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
				};

				if (!response || Number(response.status) !== 1 || !Array.isArray(response.data) || !response.meta) {
					$('#active_session_count').text('\u2014');
					$('#active_session_pagination').html('');
					render_active_session_metrics({});
					$tbody.html('<tr class="active-session-state-row is-error"><td colspan="5"><span class="active-session-state-icon"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i></span><strong>Unable to load active sessions</strong><small>' + sessionText(response && response.code ? response.code : 'Invalid server response') + '</small></td></tr>');
					return;
				}
				var sessions = response.data;
				render_active_session_metrics(response.meta.metrics || {});
				if (sessions.length === 0) {
					$('#active_session_count').text('0');
					$tbody.html(
						'<tr class="active-session-state-row"><td colspan="5">' +
						'<span class="active-session-state-icon"><i class="fa fa-user-times" aria-hidden="true"></i></span>' +
						'<strong>No active sessions</strong>' +
						'<small>No user session is currently available.</small>' +
						'</td></tr>'
					);
				} else {
					var rows = '';
					var statusMap = {
						current:{label:'Current',icon:'fa-check-circle'},
						active:{label:'Active',icon:'fa-circle'},
						refresh:{label:'Refresh window',icon:'fa-refresh'},
						grace:{label:'Grace period',icon:'fa-clock-o'},
						due:{label:'Due',icon:'fa-exclamation-circle'},
						expired:{label:'Expired',icon:'fa-times-circle'}
					};
					$.each(sessions, function (i, session) {
						var issuedAt = sessionText(admin_format_datetime(session.issued_at));
						var lastActivity = sessionText(admin_format_datetime(session.last_activity_at));
						var userName = sessionText(session.name);
						var userId = sessionText(session.user_id);
						var deviceInfo = sessionText(session.device_info);
						var status = statusMap[session.status] || statusMap.expired;
						var statusTitle = status.label;
						if ((session.status === 'grace' || session.status === 'due') && session.revoke_at) {
							statusTitle += ' - '+sessionText(admin_format_datetime(session.revoke_at));
						}

						rows += '<tr>';
						rows += '<td data-label="Issued At"><span class="active-session-cell active-session-time" title="'+sessionAttribute(issuedAt)+'">'+issuedAt+'</span></td>';
						rows += '<td data-label="Last Heartbeat"><span class="active-session-cell active-session-time" title="'+sessionAttribute(lastActivity)+'">'+lastActivity+'</span></td>';
						rows += '<td data-label="User"><span class="active-session-cell active-session-user" title="'+sessionAttribute(userName+' ('+userId+')')+'"><i class="fa fa-user-circle-o" aria-hidden="true"></i><span>'+userName+'</span></span></td>';
						rows += '<td data-label="Device"><span class="active-session-cell active-session-device" title="'+sessionAttribute(deviceInfo)+'"><i class="fa fa-desktop" aria-hidden="true"></i>'+deviceInfo+'</span></td>';
						rows += '<td data-label="Status"><span class="active-session-status is-'+session.status+'" title="'+sessionAttribute(statusTitle)+'"><i class="fa '+status.icon+'" aria-hidden="true"></i><span>'+status.label+'</span></span></td>';
						rows += '</tr>';
					});

					$('#active_session_count').text(response.meta.total);
					$tbody.html(rows);
				}
				render_active_session_pagination(response.meta);
                       },
                       error: function (xhr, error, thrown) {
						$('#active_session_count').text('\u2014');
						$('#active_session_pagination').html('');
						render_active_session_metrics({});
						$('#security_tab_session').html(
							'<tr class="active-session-state-row is-error"><td colspan="5">' +
							'<span class="active-session-state-icon"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i></span>' +
							'<strong>Unable to load active sessions</strong>' +
							'<small>Please retry or check the application log.</small>' +
							'</td></tr>'
						);
                       }
                   });
         }

         $(document).on('click', '#active_session_search_button', function(){ activeSessionPage=1;get_all_user_activ_session(1); });
         $(document).on('change', '#active_session_status, #active_session_page_size', function(){ activeSessionPage=1;get_all_user_activ_session(1); });
         $(document).on('keydown', '#active_session_query', function(event){ if(event.key === 'Enter'){event.preventDefault();activeSessionPage=1;get_all_user_activ_session(1);} });
         $(document).on('click', '#active_session_pagination button[data-active-page]', function(){ if(!this.disabled)get_all_user_activ_session($(this).data('active-page')); });
         
         

         var auditLogData = [];
         var AUDIT_LOG_PAGE_SIZE = 10;

         function render_audit_pagination(currentPage, totalPages){
            if(totalPages <= 1){
               $('#audit_log_pagination').html('');
               return;
            }
            var html = '<ul class="pagination pagination-sm">';
            html += currentPage <= 1
               ? '<li class="disabled"><a href="javascript:void(0)">Prev</a></li>'
               : '<li><a href="javascript:void(0)" onclick="render_audit_log_page(' + (currentPage - 1) + ')">Prev</a></li>';
            var startPage = Math.max(1, currentPage - 2);
            var endPage = Math.min(totalPages, startPage + 4);
            startPage = Math.max(1, endPage - 4);
            for(var p = startPage; p <= endPage; p++){
               html += p === currentPage
                  ? '<li class="active"><a href="javascript:void(0)">' + p + '</a></li>'
                  : '<li><a href="javascript:void(0)" onclick="render_audit_log_page(' + p + ')">' + p + '</a></li>';
            }
            html += currentPage >= totalPages
               ? '<li class="disabled"><a href="javascript:void(0)">Next</a></li>'
               : '<li><a href="javascript:void(0)" onclick="render_audit_log_page(' + (currentPage + 1) + ')">Next</a></li>';
            html += '</ul>';
            $('#audit_log_pagination').html(html);
         }

         function audit_log_text(value){
            return $('<div>').text(value == null ? '' : value).html();
         }

         function render_audit_log_page(page){
            var totalPages = Math.ceil(auditLogData.length / AUDIT_LOG_PAGE_SIZE) || 1;
            page = Math.max(1, Math.min(page, totalPages));
            var start = (page - 1) * AUDIT_LOG_PAGE_SIZE;
            var rows = auditLogData.slice(start, start + AUDIT_LOG_PAGE_SIZE);
            var tr = '';
            $.each(rows, function(i, value){
               var auditDateTime = audit_log_text(admin_format_datetime(value.datetime));
               var auditLogType = audit_log_text(value.log_type);
               var auditLogDetail = audit_log_text(value.log_detail);
               var auditIpAddress = audit_log_text(value.ip_addr);
               tr += '<tr>';
               tr += '<td data-label="Date / Time"><span class="audit-cell-text audit-log-time" title="'+auditDateTime+'">'+auditDateTime+'</span></td>';
               tr += '<td data-label="Log Type"><span class="audit-cell-text audit-type-badge" title="'+auditLogType+'">'+auditLogType+'</span></td>';
               tr += '<td data-label="Activity Details"><span class="audit-cell-text audit-log-details" title="'+auditLogDetail+'">'+auditLogDetail+'</span></td>';
               tr += '<td data-label="IP Address"><code class="audit-cell-text audit-ip-address" title="'+auditIpAddress+'">'+auditIpAddress+'</code></td>';
               tr += '</tr>';
            });
            $('#audit_search_result_tbody').html(tr);
            render_audit_pagination(page, totalPages);
         }

         function search_audit_date_range(){
            $.ajax({
            type: 'POST',
            url: '../lib/q_func',
            dataType: "json",
            data: {admin_get_audit_range:'',audit_search_daterange:$('#audit_search_daterange').val()},     
            beforeSend: function(){
               auditLogData = [];
               $('#audit_log_pagination').html('');
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
                  auditLogData = [];
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
                  response.sort(function(a, b){
                     var dateOrder = String(b.datetime || '').localeCompare(String(a.datetime || ''));
                     return dateOrder !== 0 ? dateOrder : Number(b.audit_id || 0) - Number(a.audit_id || 0);
                  });
                  auditLogData = response;
                  $('#audit_result_count').text(response.length);
                  render_audit_log_page(1);

               }
         
            },
            error: function (xhr, error, thrown) {
               auditLogData = [];
               $('#audit_log_pagination').html('');
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
               var dtStart = admin_format_datetime(row.ext_head_dt_start);
               var dtEnd = row.ext_head_dt_end ? ' — ' + admin_format_datetime(row.ext_head_dt_end) : '';
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
               tr += '<td class="sync-action-column" data-label="#"><button type="button" class="sync-view-button" title="View session details" aria-label="View session details" onclick="load_sync_log_detail(' + row.ext_head_id + ', \'' + String(dtStart).replace(/'/g, "\\'") + '\');"><i class="fa fa-eye" aria-hidden="true"></i><span class="sr-only">View session details</span></button></td>';
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
               tr += '<td data-label="Time"><span class="sync-session-time">' + admin_format_datetime(row.logged_at) + '</span></td>';
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

  oneidConfirm('Remove selected file?', 'The selected file will be cleared from this form.', 'Remove file', function () {
    api.clearElement();
    $('#edit_existing_app_icon').val('');
  });
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
      date: "2026-07-24",
      changes: [
        "ODL Fasa 7 Controlled Pilot selesai dengan tiga akaun NEW, provenance STUDENT_ODL_PG, reconciliation tepat serta login dan ACL smoke test PASS.",
        "ODL Fasa 8 Controlled Full Apply selesai untuk 50 akaun NEW; keseluruhan 53 membership ODL aktif dan semua tindakan bukan NEW kekal sifar.",
        "Cross-source isolation kini meliputi Staff, Undergraduate, ODL dan akaun manual supaya Preview serta Apply tidak mencampurkan ownership sumber.",
        "Fasa 9 menyediakan ODL Manual Operational Preview melalui modal Admin yang sama seperti Undergraduate dengan plan hash, expiry dan action counts.",
        "ODL operational Apply mempunyai private gate berasingan dan kekal disabled sehingga exact-plan authorization diterima.",
        "Matrik, IC dan external membership collision diblock sebelum approval; persistence turut menyemak semula ownership dalam transaction.",
        "E-mel kosong daripada ODL tidak memadam e-mel OneID sedia ada, manakala akaun manual kekal protected.",
        "External Sync Summary kekal read-only dan notifikasi parent modal dipaparkan hanya apabila sesuatu sumber mempunyai tindakan atau block.",
        "Badge External Sync hanya mengira tindakan sebenar; rekod KEEP yang telah synchronized tidak lagi menghasilkan loceng palsu.",
        "Automatic scheduler, unattended mutation dan production rollout ODL kekal disabled.",
        "Dokumen audit merekod Fasa 0 hingga 9 PASS/CLOSED; F9 menambah 18 akaun melalui header 50 dan ditutup di bawah ONEID-ODL-F9-20260724-02.",
        "F9A melengkapkan manual action coverage: UPDATE dan DEACTIVATE melalui header 52 serta REACTIVATE melalui header 53 dengan reconciliation, rollback readiness dan smoke test PASS."
      ]
    },
    {
      version: "2.6.0",
      date: "2026-07-23",
      changes: [
        "External Sync dipisahkan kepada Summary, Staff, Undergraduate dan ODL supaya status serta tindakan setiap sumber tidak bercampur.",
        "Staff dan Undergraduate menggunakan Preview serta Operational Apply berasingan yang diikat kepada source, one-time approval dan fresh writer plan.",
        "ODL postgraduate diperkenalkan melalui provenance schema, adapter MySQL read-only, TLS fail-closed, data-quality audit dan Shadow Preview.",
        "Parent modal menerima notifikasi source-specific, Preview modal diperluas dan child modal kembali kepada pilihan utama apabila ditutup.",
        "ODL Apply dan automatic scheduler kekal disabled pada baseline release ini."
      ]
    },
    {
      version: "2.5.4",
      date: "2026-07-22",
      changes: [
        "Admin Step-Up 2FA kini melindungi akses Administrator dan perubahan konfigurasi sensitif menggunakan OTP e-mel atau Microsoft Authenticator.",
        "Lifecycle TOTP lengkap merangkumi enrollment QR lokal, secret terenkripsi, confirmation, anti-replay, preference per-admin serta reset dan revoke beraudit.",
        "Grant keselamatan diikat kepada admin, session, browser dan purpose; session serta CSRF dirotasi selepas verification berjaya.",
        "Controlled bootstrap, lifetime grant, audit berstruktur, recovery OTP e-mel, monitoring F7.6 dan rollback fail-closed telah dilaksanakan dan diuji di staging.",
        "Issuer Authenticator boleh dibezakan mengikut environment, manakala keyring kekal di luar Git dan mesti boleh dibaca oleh akaun PHP-FPM.",
        "Reset Authenticator menggunakan SweetAlert dan enrollment kini memberikan diagnosis keyring yang selamat tanpa mendedahkan path, secret atau OTP."
      ]
    },
    {
      version: "2.5.3",
      date: "2026-07-19",
      changes: [
        "Gambar profil dashboard pengguna kini melalui resolver same-origin supaya Firefox tidak lagi membuat probe terus ke domain gambar luar.",
        "Akaun tanpa ID gambar, ID tidak sah atau akaun TEST terus menerima fallback profile lokal tanpa request upstream.",
        "Resolver menguatkuasakan active session, HTTPS/TLS verification, timeout, had 2MB dan decoded MIME validation sebelum gambar dipaparkan.",
        "Kegagalan upstream, gambar tiada atau respons bukan imej menghasilkan silhouette lokal tanpa OpaqueResponseBlocking pada browser."
      ]
    },
    {
      version: "2.5.2",
      date: "2026-07-19",
      changes: [
        "Menu Back to My Account dalam Administrator kini menggunakan warna kuning yang sama dengan menu Administrator pada dashboard pengguna.",
        "Warna normal, hover dan focus menggunakan class pill-yellow sedia ada tanpa mengubah navigasi atau authorization."
      ]
    },
    {
      version: "2.5.1",
      date: "2026-07-19",
      changes: [
        "Audit induk Admin Step-Up 2FA kini menyatukan keperluan Configuration, SC7-SC8, Password Recovery, token lifecycle dan Active Sessions.",
        "Purpose ADMIN_ACCESS, SECURITY_CONFIGURATION_CHANGE dan ACTIVE_SESSION_REVOCATION diasingkan dengan kontrak authorization fail-closed.",
        "Dokumen meliputi controlled bootstrap, encrypted TOTP lifecycle, structured rejection, break-glass, session revocation, UAT, monitoring dan rollout gate.",
        "Kontrak dokumentasi SC7 memastikan audit induk dan dokumen handoff berkaitan kekal selaras; implementasi masih on hold."
      ]
    },
    {
      version: "2.5.0",
      date: "2026-07-19",
      changes: [
        "Pagination Audit History kini sepadan dengan Active Sessions dan berada di kanan bawah pada desktop.",
        "Ruang selepas jadual, saiz butang, hover, focus dan disabled state diseragamkan untuk paparan yang lebih kemas.",
        "Pada skrin kecil pagination kembali ke tengah bagi mengekalkan kawalan yang mudah dicapai."
      ]
    },
    {
      version: "2.4.4",
      date: "2026-07-19",
      changes: [
        "Audit History dipadatkan daripada tujuh kolum kepada empat kumpulan maklumat yang mudah diimbas.",
        "Semua header dan data audit menggunakan top-left alignment, lebar kolum stabil, ellipsis serta tooltip bagi reason yang panjang.",
        "Outcome, revision, perubahan, actor, reason code dan reference disusun secara hierarki dengan paparan responsif seperti Active Sessions."
      ]
    },
    {
      version: "2.4.3",
      date: "2026-07-19",
      changes: [
        "Configuration kini menggunakan tiga tab khusus: Authentication Policy, Account Recovery dan Audit History.",
        "Setiap tab mengekalkan form serta kawalan keselamatan sedia ada sambil mengurangkan panjang halaman dan memudahkan navigasi.",
        "Tab responsif boleh discroll pada skrin kecil, menggunakan status aksesibiliti tab, dan memuat semula audit history apabila dibuka."
      ]
    },
    {
      version: "2.4.2",
      date: "2026-07-19",
      changes: [
        "Fasa 3 Configuration kini mewajibkan change reason dan mengikat setiap preview kepada <code>configuration_version</code> semasa.",
        "Optimistic locking menolak Apply daripada preview lama supaya perubahan dua admin tidak boleh saling menindih tanpa amaran.",
        "Structured Configuration History merekod success/rejection, actor, revision, before/after, reason code, change reason dan correlation tanpa token atau credential.",
        "Halaman Configuration memaparkan Last Changed serta history read-only newest-first dengan pagination.",
        "Forward/down migration dan concurrency contract tersedia; activation staging memerlukan migration check/apply sebelum reload PHP-FPM."
      ]
    },
    {
      version: "2.4.1",
      date: "2026-07-19",
      changes: [
        "Semua nama dan kandungan dokumen release aktif telah diaudit supaya menggunakan penomboran baharu; nombor legacy hanya dikekalkan dalam jadual migrasi rasmi.",
        "Contract dokumentasi baharu menolak nama fail atau kandungan Markdown yang memperkenalkan semula release <code>2.0.5</code> dan ke atas di luar polisi migrasi.",
        "Dokumen release lama kini menggunakan canonical path <code>v2.1.0</code> hingga <code>v2.1.3</code> mengikut urutan release sebenar.",
        "Metadata pusat dan <code>package.json</code> kini menggunakan v2.4.1, release pertama selepas baseline normalisasi v2.4.0.",
        "Penomboran dependency pihak ketiga dan alamat IP bertitik tidak dianggap sebagai versi aplikasi OneID."
      ]
    },
    {
      version: "2.4.0",
      date: "2026-07-19",
      changes: [
        "Penomboran release 2.x dinormalisasi kepada lima patch setiap minor: <code>.0</code> hingga <code>.4</code>, kemudian minor seterusnya bermula semula pada <code>.0</code>.",
        "Browser UAT AS2 bagi <code>multi_session=1</code>, revoked token tanpa perubahan polisi global dan <code>multi_session=0</code> telah dilaporkan PASS oleh owner.",
        "UAT mengesahkan multiple session berfungsi apabila dibenarkan dan browser lama memerlukan login semula selepas token ditamatkan.",
        "AS3 notification, idle warning, absolute-timeout warning dan revoked reason direkod sebagai UX follow-up yang ditangguhkan oleh owner.",
        "Hard session cap, Controlled Admin Revoke, housekeeping Apply, retention 90 hari, monitoring dan penamatan compatibility refresh kekal pending dengan gate berasingan.",
        "Release ini menutup evidence dan dokumentasi UAT AS2 tanpa mengubah database, runtime configuration atau enforcement yang telah lulus."
      ]
    },
    {
      version: "2.3.4",
      date: "2026-07-18",
      changes: [
        "Setiap action OneID yang terlindung kini mengikat PHP session kepada cookie SSO token yang masih aktif untuk pengguna tersebut.",
        "Apabila login baharu merevoke token lama semasa multiple session dimatikan, browser lama menerima HTTP 401 pada action atau heartbeat seterusnya.",
        "Revoked browser membersihkan cookie SSO, authenticated session state dan merotasi PHP session ID sebelum kembali ke login.",
        "Dashboard user, dashboard admin dan report user list turut menolak direct page access menggunakan token yang telah tidak aktif.",
        "Dokumen AS2 merekodkan baki UAT dua browser/PC, hard session cap, Admin Revoke, housekeeping, retention dan monitoring sebagai gate berasingan."
      ]
    },
    {
      version: "2.3.3",
      date: "2026-07-18",
      changes: [
        "Jadual Admin <b>Active Sessions</b> kini memastikan setiap nilai Issued At, Last Heartbeat, User, Device dan Status dipaparkan dalam satu baris.",
        "Kolum User hanya memaparkan nama; ID/IC penuh kekal tersedia melalui tooltip apabila tetikus berada di atas nama.",
        "Nilai panjang menggunakan ellipsis tanpa mengubah tinggi row, dan kandungan penuh bagi masa serta peranti kekal tersedia melalui tooltip.",
        "Masa revocation untuk status Grace atau Due dipindahkan ke tooltip status supaya badge kekal satu baris.",
        "Contract AS0 melindungi paparan nama sahaja dan memastikan detail sensitif yang diperlukan tidak diwujudkan sebagai baris kedua."
      ]
    },
    {
      version: "2.3.2",
      date: "2026-07-18",
      changes: [
        "Heartbeat teknikal lima minit kini mengekalkan liveness token tanpa memperbaharui idle activity PHP; idle 30 minit dan absolute timeout 8 jam kekal berasingan.",
        "Admin <b>Active Sessions</b> menambah state <b>Refresh Window</b> dan metrik Current, Active, Refresh, Grace, Due serta Expired.",
        "Timestamp UI diperjelas sebagai <b>Issued At</b> dan <b>Last Heartbeat</b>, selari dengan lifecycle absolute token dan compatibility window 60 minit.",
        "Tool housekeeping menyediakan mod <code>--check</code> read-only serta Apply fail-closed dengan batch 500, advisory lock, transaction, typed confirmation dan exact reconciliation.",
        "Housekeeping Apply, retention purge, cron scheduler, hard multi-session cap dan controlled admin revoke kekal disabled sehingga gate operasi masing-masing diluluskan."
      ]
    },
    {
      version: "2.3.1",
      date: "2026-07-18",
      changes: [
        "Admin <b>Active Sessions</b> kini menggunakan listing read-only sebenar; Refresh, carian, filter dan pagination tidak lagi menukar status token secara tersembunyi.",
        "Response browser menggunakan explicit projection tanpa <code>token_id</code>, token hash atau policy correlation material.",
        "Lifecycle sesi membezakan <b>Current, Active, Grace, Due</b> dan <b>Expired</b> berdasarkan absolute issuance serta jadual revocation SC5.",
        "UI memisahkan <b>Issued At</b> daripada <b>Last Activity</b> serta menambah carian pengguna/peranti, status filter dan page size 10, 25 atau 50.",
        "Contract dan preflight AS0 mengesahkan bounded query, zero mutation dan forbidden-field protection; controlled revoke kekal deferred sehingga Admin Step-Up tersedia."
      ]
    },
    {
      version: "2.3.0",
      date: "2026-07-18",
      changes: [
        "Konfigurasi dan secrets kini menggunakan satu runtime file resolver; <code>ONEID_SECRETS_FILE</code> kekal sebagai alias legacy tetapi path bercanggah akan ditolak.",
        "Tool <code>configuration_audit.php</code> memeriksa 66 key, duplicate source key, permission, URL, timezone, SMTP, API, credentials dan mode Sync secara read-only.",
        "Template private runtime dikemas kini dan disusun mengikut kumpulan Application, API, database, SMTP, Sync, external source dan diagnostics.",
        "Template Nginx serta PHP-FPM UAT diselaraskan kepada project path staging <code>/var/www/oneid-uat</code>.",
        "Configuration contract membuktikan resolver fail-closed, template lengkap, audit tanpa mutation dan perlindungan private/public kekal lulus."
      ]
    },
    {
      version: "2.2.4",
      date: "2026-07-18",
      changes: [
        "Operational Sync kini menggunakan soft warning bagi New melebihi 500, Update melebihi 1,000, Reactivate melebihi 100 atau jumlah perubahan melebihi 1,500.",
        "Batch besar kekal boleh di-Apply selepas semakan dan typed confirmation yang mengikat exact New, Update, Deactivate, Reactivate serta plan hash.",
        "Deactivate melebihi 50 disekat pada preview dan server Apply; plan tersebut mesti melalui Controlled Full Sync dengan kelulusan khusus.",
        "Nilai ambang boleh ditetapkan dalam private runtime, divalidasi secara ketat dan dipaparkan oleh preflight tanpa mendedahkan rahsia.",
        "Runbook dan characterization contract dikemas kini untuk membezakan batch biasa, batch besar dan hard block."
      ]
    },
    {
      version: "2.2.3",
      date: "2026-07-18",
      changes: [
        "Identiti IC/pasport pelajar dinormalisasi kepada format alfanumerik tanpa ruang atau sengkang sebelum Preview dan Apply.",
        "Matching planner menggunakan identiti canonical pada snapshot external dan akaun sedia ada supaya cleanup menghasilkan Update pada matrik sama, bukan Deactivate dan New.",
        "Dry-run read-only terhadap 6,485 source rows menghasilkan tepat 137 Update pada <code>data2</code>, tanpa New, Deactivate atau Reactivate.",
        "Dua akaun staf dengan ID alternatif yang telah disahkan kekal tidak disentuh oleh cleanup khusus Pelajar.",
        "Protected manual collision matching turut menerima bentuk identiti canonical supaya akaun manual kekal fail-closed selepas normalisasi."
      ]
    },
    {
      version: "2.2.2",
      date: "2026-07-18",
      changes: [
        "<b>Operational External Sync</b> membolehkan Administrator menjalankan Apply berulang selepas fresh preview tanpa count/hash private baharu atau full database dump bagi setiap batch biasa.",
        "Setiap Apply kekal diikat kepada approval session sekali guna, exact plan fingerprint, admin aktif dan expiry 5 minit; fresh snapshot mesti sepadan sebelum transaction bermula.",
        "Plan yang mempunyai Deactivate memerlukan typed confirmation dengan exact Deactivate count, manakala source anomaly, collision, invalid rows dan blast-radius threshold terus menyekat Apply.",
        "Writer selamat mengekalkan advisory lock, transaction, reconciliation dan audit marker; Operational, Pilot dan Full Cutover tidak boleh aktif serentak.",
        "Preflight dan runbook S4G ditambah untuk activation sekali sahaja, operasi setiap batch, backup berjadual serta disable segera melalui private runtime."
      ]
    },
    {
      version: "2.2.1",
      date: "2026-07-18",
      changes: [
        "Pengurusan kategori Web Apps kini menyediakan tindakan <b>Edit</b> untuk membetulkan nama kategori dengan validation, duplicate protection, transaction dan audit log yang wajib.",
        "Modal Edit Category kembali ke <b>Manage Categories</b> selepas Cancel, tutup atau simpan supaya aliran kerja pentadbir kekal lancar.",
        "Kolum nombor pada <b>External Sync Log</b> dilebarkan dan dikekalkan pada satu baris untuk menyokong nombor sehingga tiga digit.",
        "Logo header pengguna dan pentadbir dikemas kini kepada identiti <b>UPNM 30 Tahun</b> menggunakan aset PNG tempatan tanpa kebergantungan kepada pelayan luar.",
        "Full External Sync yang diluluskan selesai dengan reconciliation tepat: 70 New, 33 Update, 1 Deactivate dan 0 Reactivate; runtime Apply dikembalikan kepada disabled selepas operasi."
      ]
    },
    {
      version: "2.2.0",
      date: "2026-07-18",
      changes: [
        "Fasa <b>S4F Full External Sync</b> menyediakan endpoint dan UI Apply berasingan yang kekal disabled secara default serta hanya tersedia dalam maintenance window yang diluluskan.",
        "Full Apply diikat kepada exact New, Update, Deactivate dan Reactivate counts, full 64-character plan hash, admin session, approval sekali guna dan typed confirmation.",
        "Writer mengambil fresh external snapshot sebelum transaction, mengesahkan semula plan, menggunakan advisory lock dan mewajibkan transaction serta reconciliation audit sebelum commit.",
        "Preflight, post-run result audit, characterization contract, cutover/rollback runbook dan gate register ditambah untuk operasi full sync yang fail-closed.",
        "Semakan private menerima 33 Update dan 1 Deactivate; backup baharu disahkan melalui checksum serta isolated restore 18 jadual tanpa mengubah source database."
      ]
    },
    {
      version: "2.1.4",
      date: "2026-07-18",
      changes: [
        "Keserasian Chrome dan Firefox dipertingkat dengan atribut <b>autocomplete</b>, username tersembunyi serta identiti medan yang lengkap untuk login, password recovery, OTP dan pertukaran kata laluan.",
        "Content Security Policy, cookie pihak ketiga dan logo MyDigital ID diperbaiki dengan polisi aktif serta aset logo tempatan untuk mengelakkan warning dan permintaan luaran.",
        "CSS login, dashboard pengguna dan dashboard pentadbir dibersihkan daripada selector, prefix, filter dan at-rule browser legacy yang tidak lagi sah tanpa mengubah fungsi antaramuka.",
        "Aset ikon dan font diperkemas, termasuk Font Awesome, Icomoon, Dropify dan font dashboard, bagi menghapuskan warning parser serta glyph dalam browser moden.",
        "Modal password, label borang, input OTP dan pemuatan halaman diperbaiki untuk accessibility, autofill serta prestasi layout yang lebih konsisten."
      ]
    },
    {
      version: "2.1.3",
      date: "2026-07-17",
      changes: [
        "Dashboard pengguna dan Administrator kini memaparkan pecahan <b>Jumlah, Full SSO dan Non SSO</b> berdasarkan aplikasi unik serta kontrak <code>sp_sso_support</code> yang sama dengan tindakan akses.",
        "Kad ringkasan aplikasi menggunakan identiti warna berbeza dan susun atur responsive, termasuk state loading, kosong dan kegagalan data.",
        "Audit Fasa 7 <b>Admin Step-Up 2FA</b> diperluas dengan pilihan OTP e-mel atau Microsoft Authenticator melalui TOTP, server-side enforcement, enrollment, recovery dan audit tanpa melaksanakan feature tersebut lagi.",
        "Audit serta pelan pelaksanaan <b>multi-language Bahasa Melayu dan English</b> disusun dalam ML0 hingga ML9 meliputi Configuration default language, preference pengguna, UI, API, e-mel, accessibility, metadata, UAT dan rollback."
      ]
    },
    {
      version: "2.1.2",
      date: "2026-07-17",
      changes: [
        "Konfigurasi SSO pentadbir diperkukuh melalui validation, audit correlation, integriti database, token lifecycle dan pemisahan polisi <b>Password Recovery</b>.",
        "Penghantaran test email dan OTP Password Recovery telah disahkan sehingga mailbox; OTP kekal sah 5 minit dan hanya boleh digunakan sekali.",
        "Aliran <b>Tukar Kata Laluan</b> kini memberikan feedback SweetAlert/toast yang jelas, mengesahkan password semasa dan kualiti password, merotasi session serta membatalkan session/token lain.",
        "Halaman login memaparkan logo MyDigital ID sebagai preview tanpa mengaktifkan fungsi authentication baharu.",
        "Admin Web Apps Add/Edit kini menggunakan validation HTTPS, App ID kriptografi, confirmation, double-submit protection, atomic persistence dan audit correlation.",
        "Admin Web Apps kini mempunyai <b>carian semua aplikasi</b> merentas kategori berdasarkan nama, fungsi, URL dan App ID, dengan kiraan hasil serta clear action.",
        "Icon Web Apps disimpan mengikut environment <code>local</code>/<code>staging</code> walaupun database dikongsi; setiap filesystem kekal berasingan dan missing asset jatuh kepada placeholder.",
        "Upload icon baharu didecode dan dinormalisasi kepada static PNG 256×256; metadata dibuang, animated image dan input melebihi had keselamatan ditolak.",
        "Login dan Password Recovery kini mempunyai request timeout, double-submit protection, session-lock release, correlation audit serta feedback SweetAlert apabila respons tergendala.",
        "WA6 menyediakan reconciliation read-only dengan SHA-256 bagi missing reference dan orphan candidate; tiada quarantine atau deletion dibenarkan tanpa kelulusan owner."
      ]
    },
    {
      version: "2.1.1",
      date: "2026-07-16",
      changes: [
        "<b>Controlled Pilot External Sync</b> berjaya melaksanakan subset terkawal 2 akaun baharu dan 1 kemas kini tanpa Deactivate atau Reactivate; Apply kemudiannya dikembalikan kepada disabled.",
        "Backup penuh <code>oneiddb</code>, restore rehearsal dan isolated pilot rehearsal disahkan melalui checksum, row reconciliation serta cleanup database sementara tanpa mengubah sumber.",
        "Struktur deployment memisahkan public root, konfigurasi runtime persekitaran dan secret store di dalam direktori projek tetapi di luar capaian web.",
        "Semua notifikasi aplikasi distandardkan sebagai <b>toast top-right</b>; native alert diganti dengan toast dan tindakan berisiko menggunakan SweetAlert confirmation.",
        "Audit Log kini menyediakan pagination 10 rekod setiap halaman serta date picker yang lebih padat dengan Apply dan Cancel di bawah kalendar.",
        "Aset CSS legacy yang tidak digunakan dibuang daripada dashboard untuk mengurangkan warning browser tanpa mengubah Dropify, SweetAlert atau fungsi aktif.",
        "Paparan <b>Version Releases</b> kini menggunakan accordion eksklusif: release terkini terbuka secara default dan hanya satu release dipaparkan pada satu masa."
      ]
    },
    {
      version: "2.1.0",
      date: "2026-07-14",
      changes: [
        "Audit Log kini memaparkan rekod <b>terbaharu di bahagian paling atas</b> menggunakan susunan stabil <code>datetime DESC, id DESC</code>.",
        "Jika beberapa aktiviti direkod pada saat yang sama, ID audit terbaharu menentukan susunan supaya paparan tidak berubah-ubah selepas reload.",
        "Julat tarikh Audit Log kini merangkumi keseluruhan hari akhir yang dipilih dan tidak lagi berhenti pada jam 00:00:00.",
        "UI melaksanakan susunan defensif selepas data dimuatkan, manakala database kekal sebagai source of truth untuk urutan dan had 50 rekod terkini."
      ]
    },
    {
      version: "2.0.4",
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
        "Gate live Apply-path M1 direkod sebagai ditangguhkan oleh owner sehingga akaun external ujian yang sesuai tersedia; penangguhan ini tidak mengaktifkan External Sync Apply S4E.",
        "Paparan <b>Version Releases</b> dihadkan kepada 10 versi terkini dan menyediakan tindakan untuk melihat release terdahulu secara berperingkat."
        ,"Direktori admin kini hanya memaparkan tab kategori yang mempunyai aplikasi aktif; inventori penuh dan sebab kategori tidak boleh dipadam tersedia melalui <b>Manage Categories</b>."
        ,"Kategori sistem dilindungi dan kategori berisi tidak boleh dipadam; create/delete menggunakan validation, transaction, rollback serta correlated audit trail."
        ,"Remove App kini mengarkib aplikasi ke kategori sistem dan membersihkan group ACL, direct ACL, blacklist serta Favourite secara atomic; aplikasi inactive ditolak oleh effective ACL."
        ,"Integriti schema diperkukuh dengan nama kategori unik dan foreign key <code>sp_list.sp_group_id</code> berpolisi <code>ON DELETE RESTRICT</code>."
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

  const renderReleaseNotes = () => {
    releaseList.innerHTML = releaseNotes.map((release, releaseIndex) => `
      <article class="version-release-card${releaseIndex === 0 ? ' is-current is-open' : ''}">
        <button type="button" class="version-release-toggle" aria-expanded="${releaseIndex === 0 ? 'true' : 'false'}" aria-controls="release-content-${releaseIndex}">
          <span class="version-release-meta">
            <span class="version-number">v${release.version}</span>
            <time class="version-release-date" datetime="${release.date}">${formatReleaseDate(release.date)}</time>
          </span>
          <span class="version-release-summary">
            ${releaseIndex === 0 ? '<span class="version-latest-label">Current Release</span>' : ''}
            <span>${releaseIndex === 0 ? 'Latest updates' : `Release ${release.version}`}</span>
          </span>
          <i class="fa fa-chevron-down version-release-chevron" aria-hidden="true"></i>
        </button>
        <div id="release-content-${releaseIndex}" class="version-release-content"${releaseIndex === 0 ? '' : ' hidden'}>
          <h5>${releaseIndex === 0 ? 'Latest updates' : `Release ${release.version}`}</h5>
          <ol class="version-change-list">
            ${release.changes.map(item => `<li>${item}</li>`).join("")}
          </ol>
        </div>
      </article>
    `).join("");
  };

  releaseList.addEventListener('click', event => {
    const toggle = event.target.closest('.version-release-toggle');
    if (!toggle) return;
    const selectedCard = toggle.closest('.version-release-card');
    const selectedContent = document.getElementById(toggle.getAttribute('aria-controls'));
    const shouldOpen = toggle.getAttribute('aria-expanded') !== 'true';
    releaseList.querySelectorAll('.version-release-card').forEach(card => {
      card.classList.remove('is-open');
      card.querySelector('.version-release-toggle').setAttribute('aria-expanded', 'false');
      card.querySelector('.version-release-content').hidden = true;
    });
    if (shouldOpen) {
      selectedCard.classList.add('is-open');
      toggle.setAttribute('aria-expanded', 'true');
      selectedContent.hidden = false;
    }
  });

  renderReleaseNotes();
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

      #follo_8 .web-app-summary {
        display: flex;
        align-items: stretch;
        flex: 0 0 auto;
        gap: 7px;
      }

      #follo_8 .web-app-count span,
      #follo_8 .web-app-count strong {
        display: block;
      }

      #follo_8 .web-app-count.is-sso {
        border-color: #cbe9d8;
        background: #edf9f2;
      }

      #follo_8 .web-app-count.is-sso strong {
        color: #22844f;
      }

      #follo_8 .web-app-count.is-non-sso {
        border-color: #f2dfbd;
        background: #fff8eb;
      }

      #follo_8 .web-app-count.is-non-sso strong {
        color: #a86c15;
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

      #follo_8 .web-app-search-row {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 14px;
        border-right: 1px solid #e1e6ed;
        border-left: 1px solid #e1e6ed;
        background: #fff;
      }

      #follo_8 .web-app-search {
        position: relative;
        flex: 1 1 100%;
        max-width: none;
      }

      #follo_8 .web-app-search > i {
        position: absolute;
        top: 50%;
        left: 13px;
        color: #8b99aa;
        transform: translateY(-50%);
        pointer-events: none;
      }

      #follo_8 .web-app-search input {
        width: 100%;
        height: 38px;
        padding: 0 42px 0 38px;
        border: 1px solid #dce4ec;
        border-radius: 7px;
        background: #fbfcfd;
        color: #29384b;
        font-size: 12px;
        outline: none;
      }

      #follo_8 .web-app-search input:focus {
        border-color: #39aeda;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(17, 168, 223, .10);
      }

      #follo_8 .web-app-search button {
        position: absolute;
        top: 50%;
        right: 7px;
        width: 28px;
        height: 28px;
        padding: 0;
        border: 0;
        border-radius: 50%;
        background: transparent;
        color: #7c8999;
        transform: translateY(-50%);
      }

      #follo_8 .web-app-search button:hover,
      #follo_8 .web-app-search button:focus {
        background: #edf4f7;
        color: #168fcb;
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
        margin-bottom: 2px;
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
        margin: 0 0 2px;
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

      .category-manage-intro {
        margin: 0 0 15px;
        color: #68778a;
        font-size: 12px;
        line-height: 1.55;
      }

      .category-manage-list {
        overflow: hidden;
        border: 1px solid #e1e6ed;
        border-radius: 8px;
        background: #fff;
      }

      .category-manage-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto 38px;
        align-items: start;
        gap: 14px;
        padding: 14px 15px;
        border-bottom: 1px solid #edf0f4;
      }

      .category-manage-row:last-child {
        border-bottom: 0;
      }

      .category-manage-name strong,
      .category-manage-name small {
        display: block;
      }

      .category-manage-name strong {
        overflow: hidden;
        color: #2f3e52;
        font-size: 12px;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      .category-manage-name small {
        margin-top: 4px;
        color: #7d8998;
        font-size: 10px;
      }

      .category-manage-counts {
        display: flex;
        gap: 6px;
      }

      .category-manage-counts span {
        min-width: 58px;
        padding: 6px 8px;
        border-radius: 6px;
        background: #f4f7fa;
      }

      .category-manage-counts strong,
      .category-manage-counts small {
        display: block;
      }

      .category-manage-counts strong {
        color: #344358;
        font-size: 12px;
      }

      .category-manage-counts small {
        color: #8793a2;
        font-size: 8px;
        text-transform: uppercase;
      }

      .category-manage-actions {
        display: flex;
        gap: 6px;
      }

      .category-manage-edit,
      .category-manage-remove {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 34px;
        padding: 0;
        border: 1px solid #efc7c3;
        border-radius: 6px;
        background: #fff;
        color: #c85b52;
      }

      .category-manage-edit {
        border-color: #b8dff0;
        color: #087eaf;
      }

      .category-manage-edit:disabled,
      .category-manage-remove:disabled {
        border-color: #e3e8ed;
        background: #f5f7f9;
        color: #aeb7c1;
        cursor: not-allowed;
      }

      #modal_add_new_single_user .sync-preview-table-wrap,
      #modal_odl_shadow_preview .sync-preview-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }

      #modal_open_add_user_option .oneid-add-user-dialog {
         width: min(680px, calc(100vw - 30px));
         max-width: 680px;
      }

      #modal_open_add_user_option .oneid-sync-parent-modal {
         overflow: hidden;
         border: 0;
         border-radius: 12px;
         background: #fff;
         box-shadow: 0 20px 55px rgba(28, 46, 66, .28);
      }

      #modal_open_add_user_option .oneid-sync-parent-header {
         display: flex;
         align-items: flex-start;
         justify-content: space-between;
         padding: 22px 24px;
         background: linear-gradient(135deg, #087eaf 0%, #1398d0 100%);
         color: #fff;
      }

      #modal_open_add_user_option .oneid-sync-parent-heading {
         display: flex;
         align-items: center;
         min-width: 0;
      }

      #modal_open_add_user_option .oneid-sync-parent-heading-icon {
         display: inline-flex;
         align-items: center;
         justify-content: center;
         flex: 0 0 42px;
         width: 42px;
         height: 42px;
         margin-right: 14px;
         border: 1px solid rgba(255, 255, 255, .35);
         border-radius: 10px;
         background: rgba(255, 255, 255, .15);
         font-size: 18px;
      }

      #modal_open_add_user_option .oneid-sync-parent-heading h5 {
         margin: 0 0 4px;
         color: #fff;
         font-size: 17px;
         font-weight: 700;
         letter-spacing: .01em;
      }

      #modal_open_add_user_option .oneid-sync-parent-heading p {
         margin: 0;
         color: rgba(255, 255, 255, .85);
         font-size: 12px;
         line-height: 1.45;
      }

      #modal_open_add_user_option .oneid-sync-parent-close {
         margin: -4px -4px 0 12px;
         color: #fff;
         font-size: 25px;
         font-weight: 300;
         line-height: 1;
         opacity: .85;
         text-shadow: none;
      }

      #modal_open_add_user_option .oneid-sync-parent-close:hover,
      #modal_open_add_user_option .oneid-sync-parent-close:focus {
         color: #fff;
         opacity: 1;
      }

      #modal_open_add_user_option .oneid-sync-parent-body {
         padding: 22px 24px 24px;
         background: #f6f8fb;
      }

      #modal_open_add_user_option .oneid-sync-menu-section {
         margin-bottom: 20px;
      }

      #modal_open_add_user_option .oneid-sync-menu-section-last {
         margin-bottom: 0;
      }

      #modal_open_add_user_option .oneid-sync-menu-section-label {
         display: block;
         margin: 0 0 8px 2px;
         color: #778493;
         font-size: 10px;
         font-weight: 700;
         letter-spacing: .08em;
         text-transform: uppercase;
      }

      #modal_open_add_user_option .oneid-sync-menu-card {
         display: flex;
         align-items: center;
         width: 100%;
         min-height: 62px;
         margin-top: 9px;
         padding: 10px 14px;
         overflow: visible;
         border: 1px solid #dce4ec;
         border-radius: 8px;
         background: #fff;
         color: #344358;
         text-align: left;
         white-space: normal;
         box-shadow: 0 2px 7px rgba(45, 64, 82, .05);
         transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
      }

      #modal_open_add_user_option .oneid-sync-menu-card:hover,
      #modal_open_add_user_option .oneid-sync-menu-card:focus {
         transform: translateY(-1px);
         outline: 0;
         box-shadow: 0 7px 18px rgba(45, 64, 82, .12);
      }

      #modal_open_add_user_option .oneid-sync-menu-icon {
         display: inline-flex;
         align-items: center;
         justify-content: center;
         flex: 0 0 40px;
         width: 40px;
         height: 40px;
         margin-right: 13px;
         border-radius: 9px;
         font-size: 16px;
      }

      #modal_open_add_user_option .oneid-sync-menu-copy {
         display: block;
         flex: 1 1 auto;
         min-width: 0;
      }

      #modal_open_add_user_option .oneid-sync-menu-copy strong,
      #modal_open_add_user_option .oneid-sync-menu-copy small {
         display: block;
      }

      #modal_open_add_user_option .oneid-sync-menu-copy strong {
         margin-bottom: 3px;
         color: #2e3e50;
         font-size: 13px;
         font-weight: 700;
         line-height: 1.35;
      }

      #modal_open_add_user_option .oneid-sync-menu-copy small {
         color: #7b8794;
         font-size: 11px;
         font-weight: 400;
         line-height: 1.4;
      }

      #modal_open_add_user_option .oneid-sync-menu-arrow {
         flex: 0 0 auto;
         margin-left: 12px;
         color: #a8b3bf;
         font-size: 11px;
      }

      #modal_open_add_user_option .oneid-sync-menu-summary {
         border-color: #d9d2f6;
         background: #faf9ff;
      }

      #modal_open_add_user_option .oneid-sync-menu-summary:hover,
      #modal_open_add_user_option .oneid-sync-menu-summary:focus {
         border-color: #8170d6;
      }

      #modal_open_add_user_option .oneid-sync-menu-summary .oneid-sync-menu-icon {
         background: #eeeafd;
         color: #6753c5;
      }

      #modal_open_add_user_option .oneid-sync-menu-source {
         border-color: #cfe5f1;
      }

      #modal_open_add_user_option .oneid-sync-menu-source:hover,
      #modal_open_add_user_option .oneid-sync-menu-source:focus {
         border-color: #1594cc;
      }

      #modal_open_add_user_option .oneid-sync-menu-source .oneid-sync-menu-icon {
         background: #e6f5fb;
         color: #087eaf;
      }

      #modal_open_add_user_option .oneid-sync-menu-manual {
         border-color: #cde8dc;
         background: #f8fcfa;
      }

      #modal_open_add_user_option .oneid-sync-menu-manual:hover,
      #modal_open_add_user_option .oneid-sync-menu-manual:focus {
         border-color: #32a071;
      }

      #modal_open_add_user_option .oneid-sync-menu-manual .oneid-sync-menu-icon {
         background: #e5f5ed;
         color: #25865e;
      }

      #modal_open_add_user_option .external-action-notice {
         display: inline-flex;
         align-items: center;
         justify-content: center;
         min-width: 26px;
         height: 22px;
         flex: 0 0 auto;
         margin-left: 10px;
         padding: 0 7px;
         border-radius: 11px;
         background: #f0a325;
         color: #fff;
         font-size: 11px;
         font-weight: 700;
         line-height: 1;
         box-shadow: 0 2px 6px rgba(240, 163, 37, .35);
         vertical-align: middle;
      }

      #modal_open_add_user_option .external-action-notice-danger {
         background: #d9534f;
         box-shadow: 0 2px 6px rgba(217, 83, 79, .35);
      }

      #modal_add_new_single_user .oneid-sync-preview-dialog,
      #modal_odl_shadow_preview .oneid-sync-preview-dialog {
         width: min(880px, calc(100vw - 30px));
         max-width: 880px;
      }

      #modal_add_new_user_manual .oneid-manual-user-dialog {
         width: min(680px, calc(100vw - 30px));
         max-width: 680px;
      }

      #modal_add_new_single_user .modal-content,
      #modal_odl_shadow_preview .modal-content {
         overflow-wrap: anywhere;
      }

      .oneid-sync-child-modal {
         overflow: hidden;
         border: 0;
         border-radius: 12px;
         background: #fff;
         box-shadow: 0 20px 55px rgba(28, 46, 66, .28);
      }

      .oneid-sync-child-header {
         display: flex;
         align-items: flex-start;
         justify-content: space-between;
         min-height: 86px;
         padding: 20px 22px;
         border: 0;
         background: linear-gradient(135deg, #087eaf 0%, #1398d0 100%);
         color: #fff;
      }

      .oneid-sync-summary-header {
         background: linear-gradient(135deg, #5d4bb7 0%, #7662d0 100%);
      }

      .oneid-sync-manual-header {
         background: linear-gradient(135deg, #247956 0%, #32a071 100%);
      }

      .oneid-sync-child-heading {
         display: flex;
         align-items: center;
         min-width: 0;
      }

      .oneid-sync-child-heading-icon {
         display: inline-flex;
         align-items: center;
         justify-content: center;
         flex: 0 0 42px;
         width: 42px;
         height: 42px;
         margin-right: 13px;
         border: 1px solid rgba(255, 255, 255, .35);
         border-radius: 10px;
         background: rgba(255, 255, 255, .15);
         font-size: 17px;
      }

      .oneid-sync-child-heading h5 {
         margin: 0 0 4px;
         color: #fff;
         font-size: 16px;
         font-weight: 700;
         line-height: 1.35;
      }

      .oneid-sync-child-heading p {
         margin: 0;
         color: rgba(255, 255, 255, .84);
         font-size: 11px;
         line-height: 1.45;
      }

      .oneid-sync-child-close {
         margin: -3px -3px 0 12px;
         color: #fff;
         font-size: 25px;
         font-weight: 300;
         line-height: 1;
         opacity: .85;
         text-shadow: none;
      }

      .oneid-sync-child-close:hover,
      .oneid-sync-child-close:focus {
         color: #fff;
         opacity: 1;
      }

      .oneid-sync-child-body {
         padding: 22px;
         background: #f6f8fb;
      }

      #modal_add_new_single_user .oneid-sync-child-body > .row,
      #modal_add_new_user_manual .oneid-sync-child-body > .row {
         margin: 0;
      }

      #modal_add_new_single_user .oneid-sync-child-body .col-lg-12,
      #modal_add_new_single_user .oneid-sync-child-body .col-sm-12,
      #modal_add_new_user_manual .oneid-sync-child-body .col-lg-12,
      #modal_add_new_user_manual .oneid-sync-child-body .col-sm-12 {
         padding-right: 0;
         padding-left: 0;
      }

      #modal_add_new_single_user #sync_result_div,
      #modal_odl_shadow_preview #odl_shadow_result,
      #modal_add_new_user_manual .form-body {
         padding: 20px;
         border: 1px solid #e0e7ee;
         border-radius: 9px;
         background: #fff;
         box-shadow: 0 2px 8px rgba(45, 64, 82, .05);
      }

      #modal_add_new_single_user #sync_result_div > h6,
      #modal_odl_shadow_preview #odl_shadow_result > h6 {
         margin: 2px 0 10px;
         color: #3b4b5d;
         font-size: 12px;
         font-weight: 700;
      }

      .oneid-sync-child-footer {
         display: flex;
         align-items: center;
         justify-content: flex-end;
         min-height: 68px;
         padding: 14px 22px;
         border-top: 1px solid #e4e9ef;
         background: #fff;
      }

      .oneid-sync-child-footer .btn {
         min-width: 105px;
         min-height: 38px;
         margin-left: 8px;
         border-radius: 6px;
         font-weight: 600;
      }

      .oneid-sync-child-footer .btn-default {
         border-color: #d7dfe7;
         background: #f5f7f9;
         color: #52606d;
      }

      #modal_add_new_user_manual .control-label {
         color: #445366;
         font-size: 12px;
         font-weight: 600;
      }

      #modal_add_new_user_manual .form-control,
      #modal_add_new_single_user #sync_full_confirmation {
         min-height: 42px;
         border-color: #d8e1e9;
         border-radius: 6px;
         box-shadow: none;
      }

      #modal_add_new_user_manual .form-control:focus,
      #modal_add_new_single_user #sync_full_confirmation:focus {
         border-color: #1594cc;
         box-shadow: 0 0 0 3px rgba(21, 148, 204, .10);
      }

      #modal_add_new_user_manual .accordion-struct .panel {
         overflow: hidden;
         border: 1px solid #dce4eb;
         border-radius: 7px;
         box-shadow: none;
      }

      #modal_add_new_user_manual .accordion-struct .panel-heading {
         background: #f5f8fa;
      }

      .oneid-sync-admin-summary {
         margin-bottom: 18px;
         font-size: 14px;
         line-height: 1.55;
      }

      .oneid-sync-technical-details {
         margin: 16px 0;
         border: 1px solid #dfe6ec;
         border-radius: 5px;
         background: #f8fafb;
      }

      .oneid-sync-technical-details summary {
         padding: 12px 14px;
         color: #52606d;
         font-weight: 600;
         cursor: pointer;
      }

      .oneid-sync-technical-details[open] summary {
         border-bottom: 1px solid #dfe6ec;
      }

      .oneid-sync-technical-details .sync-preview-table-wrap {
         padding: 0 14px 8px;
      }

      #modal_odl_shadow_preview .external-summary-source-table tr:first-child td {
         color: #33475b;
         font-weight: 700;
         background: #f4f7fa;
      }

      #modal_odl_shadow_preview .external-summary-source-table td:nth-child(2),
      #modal_odl_shadow_preview .external-summary-source-table td:nth-child(3) {
         text-align: center !important;
      }

      #modal_odl_shadow_preview .external-summary-source-table td:nth-child(4) {
         min-width: 180px;
      }

      #modal_odl_shadow_preview .oneid-sync-technical-details .sync-preview-table td {
         text-align: left !important;
      }

      @media (max-width: 640px) {
         .oneid-sync-child-header,
         .oneid-sync-child-body,
         .oneid-sync-child-footer {
            padding-left: 15px;
            padding-right: 15px;
         }

         .oneid-sync-child-heading p {
            display: none;
         }

         #modal_add_new_single_user #sync_result_div,
         #modal_odl_shadow_preview #odl_shadow_result,
         #modal_add_new_user_manual .form-body {
            padding: 14px;
         }

         .oneid-sync-child-footer {
            flex-wrap: wrap;
         }

         .oneid-sync-child-footer .btn {
            flex: 1 1 auto;
            margin-top: 5px;
         }

         #modal_open_add_user_option .oneid-sync-parent-header,
         #modal_open_add_user_option .oneid-sync-parent-body {
            padding-left: 16px;
            padding-right: 16px;
         }

         #modal_open_add_user_option .oneid-sync-menu-card {
            padding: 10px;
         }

         #modal_open_add_user_option .oneid-sync-menu-icon {
            margin-right: 10px;
         }

         #modal_open_add_user_option .oneid-sync-menu-copy small {
            display: none;
         }
      }

      #modal_add_new_single_user .sync-preview-table,
      #modal_odl_shadow_preview .sync-preview-table {
        width: 100%;
        min-width: 520px;
        table-layout: fixed;
      }

      #modal_add_new_single_user .sync-preview-table td,
      #modal_odl_shadow_preview .sync-preview-table td {
        vertical-align: top !important;
        text-align: left !important;
      }

      #modal_add_new_single_user .sync-preview-table td:first-child,
      #modal_odl_shadow_preview .sync-preview-table td:first-child {
        width: 230px;
        white-space: nowrap;
        font-weight: 500;
      }

      #modal_add_new_single_user .sync-preview-table td:last-child,
      #modal_odl_shadow_preview .sync-preview-table td:last-child {
        overflow-wrap: anywhere;
        word-break: break-word;
      }

      #modal_odl_shadow_preview .oneid-sync-technical-details .sync-preview-table {
         min-width: 0;
         table-layout: fixed;
      }

      #modal_odl_shadow_preview .oneid-sync-technical-details .sync-preview-table td:first-child {
         width: 320px;
         padding-right: 28px;
         white-space: normal;
         line-height: 1.45;
      }

      #modal_odl_shadow_preview .oneid-sync-technical-details .sync-preview-table td:last-child {
         padding-left: 14px;
         line-height: 1.45;
      }

      @media (max-width: 640px) {
         #modal_odl_shadow_preview .oneid-sync-technical-details .sync-preview-table,
         #modal_odl_shadow_preview .oneid-sync-technical-details .sync-preview-table tbody,
         #modal_odl_shadow_preview .oneid-sync-technical-details .sync-preview-table tr,
         #modal_odl_shadow_preview .oneid-sync-technical-details .sync-preview-table td {
            display: block;
            width: 100%;
         }

         #modal_odl_shadow_preview .oneid-sync-technical-details .sync-preview-table td:first-child {
            width: 100%;
            padding: 12px 8px 4px;
         }

         #modal_odl_shadow_preview .oneid-sync-technical-details .sync-preview-table td:last-child {
            padding: 4px 8px 12px;
         }
      }

      .category-manage-state {
        padding: 25px 15px;
        color: #718093;
        text-align: center;
      }

      .category-manage-state i,
      .category-manage-state span {
        display: block;
      }

      .category-manage-state i {
        margin-bottom: 8px;
        color: #168fcb;
      }

      .category-manage-state.is-error i {
        color: #c85b52;
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

        #follo_8 .web-app-search-row {
          display: block;
        }

        #follo_8 .web-app-search {
          max-width: none;
        }

      }

      @media (max-width: 767px) {
        #follo_8 .web-app-panel {
          padding: 20px 15px;
        }

        #follo_8 .web-app-header {
          display: block;
        }

        #follo_8 .web-app-summary {
          width: 100%;
          flex-wrap: wrap;
          margin-top: 16px;
        }

        #follo_8 .web-app-count {
          flex: 1 1 88px;
          min-width: 88px;
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

      #tab_active_sessions .active-session-filter {
        display: grid;
        grid-template-columns: minmax(210px, 1fr) 180px 140px 42px;
        gap: 9px;
        padding: 14px 20px;
        border-bottom: 1px solid #e8ecf1;
        background: #fbfcfe;
      }

      #tab_active_sessions .active-session-search-box {
        position: relative;
      }

      #tab_active_sessions .active-session-search-box i {
        position: absolute;
        top: 50%;
        left: 12px;
        color: #8793a3;
        transform: translateY(-50%);
      }

      #tab_active_sessions .active-session-filter input,
      #tab_active_sessions .active-session-filter select {
        width: 100%;
        height: 38px;
        border: 1px solid #d9e1ea;
        border-radius: 6px;
        background: #fff;
        color: #425166;
        font-size: 12px;
      }

      #tab_active_sessions .active-session-filter input {
        padding: 8px 12px 8px 34px;
      }

      #tab_active_sessions .active-session-filter select {
        padding: 7px 10px;
      }

      #tab_active_sessions .active-session-filter-button,
      #tab_active_sessions .active-session-pagination button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 38px;
        border: 1px solid #cfe1ec;
        border-radius: 6px;
        background: #fff;
        color: #168fcb;
      }

      #tab_active_sessions .active-session-filter-button:hover,
      #tab_active_sessions .active-session-filter-button:focus,
      #tab_active_sessions .active-session-pagination button:hover:not(:disabled),
      #tab_active_sessions .active-session-pagination button:focus:not(:disabled) {
        border-color: #9dcede;
        background: #eef8fd;
      }

      #tab_active_sessions .active-session-metrics {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        border-bottom: 1px solid #e8ecf1;
        background: #fff;
      }

      #tab_active_sessions .active-session-metrics span {
        padding: 10px 12px;
        border-right: 1px solid #edf0f4;
        color: #748094;
        font-size: 10px;
        text-align: center;
        text-transform: uppercase;
      }

      #tab_active_sessions .active-session-metrics span:last-child {
        border-right: 0;
      }

      #tab_active_sessions .active-session-metrics b {
        display: block;
        margin-bottom: 2px;
        color: #34465b;
        font-size: 15px;
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

      #tab_active_sessions .active-col-issued { width: 16%; }
      #tab_active_sessions .active-col-activity { width: 16%; }
      #tab_active_sessions .active-col-user { width: 24%; }
      #tab_active_sessions .active-col-device { width: 27%; }
      #tab_active_sessions .active-col-status { width: 17%; }

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

      #tab_active_sessions .active-session-user {
        display: flex;
        align-items: center;
      }

      #tab_active_sessions .active-session-user > span {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
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

      #tab_active_sessions .active-session-status.is-grace {
        background: #fff7dd;
        color: #8a6815;
      }

      #tab_active_sessions .active-session-status.is-refresh {
        background: #e9f4fb;
        color: #197ca8;
      }

      #tab_active_sessions .active-session-status.is-due,
      #tab_active_sessions .active-session-status.is-expired {
        background: #fceceb;
        color: #ad4a43;
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

      #tab_active_sessions .active-session-pagination {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        min-height: 58px;
        gap: 12px;
        padding: 10px 20px;
        border-top: 1px solid #e8ecf1;
        color: #657286;
        font-size: 11px;
      }

      #tab_active_sessions .active-session-pagination button {
        width: 34px;
        height: 34px;
      }

      #tab_active_sessions .active-session-pagination button:disabled {
        cursor: not-allowed;
        opacity: .45;
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

        #tab_active_sessions .active-session-filter {
          grid-template-columns: 1fr 1fr;
          padding: 12px 15px;
        }

        #tab_active_sessions .active-session-search-box {
          grid-column: 1 / -1;
        }

        #tab_active_sessions .active-session-filter-button {
          width: 42px;
        }

        #tab_active_sessions .active-session-pagination {
          justify-content: center;
        }

        #tab_active_sessions .active-session-metrics {
          grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        #tab_active_sessions .active-session-metrics span:nth-child(3) {
          border-right: 0;
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

      .daterangepicker.oneid-audit-daterangepicker.is-open {
        display: flex !important;
        flex-wrap: wrap;
        width: 548px;
        max-width: calc(100vw - 24px);
        padding: 4px;
      }

      .daterangepicker.oneid-audit-daterangepicker .calendar {
        float: none !important;
        flex: 1 1 260px;
        max-width: 270px;
      }

      .daterangepicker.oneid-audit-daterangepicker .ranges {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        order: 3;
        float: none !important;
        clear: both;
        flex: 0 0 100%;
        width: 100% !important;
        margin: 0;
        padding: 10px 8px 6px;
        border-top: 1px solid #e6ebf0;
        text-align: right;
      }

      .daterangepicker.oneid-audit-daterangepicker .ranges ul:empty {
        display: none;
      }

      .daterangepicker.oneid-audit-daterangepicker .range_inputs {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        width: 100%;
      }

      .daterangepicker.oneid-audit-daterangepicker .range_inputs .btn {
        float: none !important;
        min-width: 72px;
        margin: 0;
      }

      #tab_auditlog .audit-log-pagination {
        min-height: 16px;
        padding: 12px 16px;
        border-top: 1px solid #edf0f4;
        background: #fbfcfd;
      }

      #tab_auditlog .audit-log-pagination:empty {
        display: none;
      }

      #tab_auditlog .audit-log-pagination .pagination {
        margin: 0;
      }

      @media (max-width: 767px) {
        .daterangepicker.oneid-audit-daterangepicker.is-open {
          display: block !important;
          width: 278px;
        }

        .daterangepicker.oneid-audit-daterangepicker .calendar {
          max-width: 270px;
        }

        .daterangepicker.oneid-audit-daterangepicker .ranges {
          display: block;
        }

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

      #tab_synclog .sync-log-table .sync-action-column {
        text-align: left !important;
      }

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

      #tab_synclog .sync-detail-table th:nth-child(1) { width: 7%; }
      #tab_synclog .sync-detail-table th:nth-child(2) { width: 14%; }
      #tab_synclog .sync-detail-table th:nth-child(3) { width: 13%; }
      #tab_synclog .sync-detail-table th:nth-child(4) { width: 47%; }
      #tab_synclog .sync-detail-table th:nth-child(5) { width: 19%; }

      #tab_synclog .sync-detail-table th:nth-child(1),
      #tab_synclog .sync-detail-table td:nth-child(1) {
        min-width: 48px;
        overflow-wrap: normal;
        white-space: nowrap;
        word-break: normal;
      }

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

      #tab_settings .configuration-tabs {
        overflow-x: auto;
        border-bottom: 1px solid #dfe5ec;
        background: #fff;
        scrollbar-width: thin;
      }

      #tab_settings .configuration-tabs .nav-tabs {
        display: flex;
        width: max-content;
        min-width: 100%;
        margin: 0;
        border: 0;
      }

      #tab_settings .configuration-tabs .nav-tabs > li {
        float: none;
        margin: 0;
      }

      #tab_settings .configuration-tabs .nav-tabs > li > a {
        display: flex;
        align-items: center;
        gap: 9px;
        min-height: 52px;
        margin: 0;
        padding: 15px 22px 13px;
        border: 0;
        border-bottom: 3px solid transparent;
        border-radius: 0;
        background: transparent;
        color: #667386;
        font-size: 13px;
        font-weight: 600;
        white-space: nowrap;
      }

      #tab_settings .configuration-tabs .nav-tabs > li > a:hover,
      #tab_settings .configuration-tabs .nav-tabs > li > a:focus {
        border-color: #b9dcea;
        background: #f6fafc;
        color: #176e94;
        outline: none;
      }

      #tab_settings .configuration-tabs .nav-tabs > li.active > a,
      #tab_settings .configuration-tabs .nav-tabs > li.active > a:hover,
      #tab_settings .configuration-tabs .nav-tabs > li.active > a:focus {
        border: 0;
        border-bottom: 3px solid #159bce;
        background: #f7fbfd;
        color: #116f98;
      }

      #tab_settings .configuration-tabs i {
        width: 16px;
        color: #159bce;
        text-align: center;
      }

      #tab_settings .configuration-tab-content > .tab-pane {
        min-height: 560px;
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

      #tab_settings .sso-config-save:disabled {
        border-color: #aeb9c5;
        background: #aeb9c5;
        box-shadow: none;
        cursor: not-allowed;
        opacity: .78;
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
        align-items: start;
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
        align-self: start;
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

      #tab_settings .sso-config-note-warning {
        border-bottom: 1px solid #f5e4b8;
        background: #fffaf0;
        color: #795d20;
      }

      #tab_settings .sso-config-note-warning i {
        color: #d49b18;
      }

      #tab_settings .configuration-history-table-wrap {
        width: 100%;
        overflow-x: auto;
        border: 1px solid #e1e6ed;
        border-radius: 8px;
        background: #fff;
      }

      #tab_settings .configuration-history-table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
      }

      #tab_settings .configuration-history-col-event { width: 24%; }
      #tab_settings .configuration-history-col-result { width: 16%; }
      #tab_settings .configuration-history-col-change { width: 28%; }
      #tab_settings .configuration-history-col-reason { width: 32%; }

      #tab_settings .configuration-history-table thead th {
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

      #tab_settings .configuration-history-table tbody td {
        padding: 15px;
        overflow: hidden;
        border: 0;
        border-bottom: 1px solid #edf0f4;
        color: #596678;
        font-size: 11px;
        line-height: 1.45;
        text-align: left;
        vertical-align: top;
      }

      #tab_settings .configuration-history-table tbody tr:last-child td {
        border-bottom: 0;
      }

      #tab_settings .configuration-history-table tbody tr:not(.configuration-history-state-row):hover td {
        background: #fbfdff;
      }

      #tab_settings .configuration-history-primary,
      #tab_settings .configuration-history-secondary,
      #tab_settings .configuration-history-change,
      #tab_settings .configuration-history-reason {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      #tab_settings .configuration-history-primary,
      #tab_settings .configuration-history-reason {
        color: #34465b;
        font-weight: 600;
      }

      #tab_settings .configuration-history-secondary {
        margin-top: 5px;
        color: #8490a0;
        font-size: 10px;
      }

      #tab_settings .configuration-history-secondary i {
        width: 14px;
        margin-right: 4px;
        color: #8b98a8;
        text-align: left;
      }

      #tab_settings .configuration-history-change + .configuration-history-change {
        margin-top: 4px;
      }

      #tab_settings .configuration-history-change b {
        display: inline-block;
        min-width: 52px;
        color: #435269;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
      }

      #tab_settings .configuration-history-outcome {
        display: inline-flex;
        align-items: center;
        min-height: 22px;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 9px;
        font-weight: 700;
        line-height: 1;
      }

      #tab_settings .configuration-history-outcome.is-success {
        background: #e7f7ef;
        color: #168354;
      }

      #tab_settings .configuration-history-outcome.is-rejected {
        background: #fceceb;
        color: #b1473f;
      }

      #tab_settings .configuration-history-empty,
      #tab_settings .configuration-history-state-row td {
        color: #8995a4;
      }

      #tab_settings .configuration-history-state-row td {
        padding: 28px 15px;
        text-align: center;
      }

      #tab_settings #sso_config_history_pagination {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        min-height: 48px;
        gap: 12px;
        margin-top: 14px;
        padding: 6px 0 0;
        color: #657286;
        font-size: 11px;
      }

      #tab_settings #sso_config_history_pagination button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        padding: 0;
        border: 1px solid #cfe1ec;
        border-radius: 6px;
        background: #fff;
        color: #168fcb;
      }

      #tab_settings #sso_config_history_pagination button:hover:not(:disabled),
      #tab_settings #sso_config_history_pagination button:focus:not(:disabled) {
        border-color: #9dcede;
        background: #eef8fd;
        outline: none;
      }

      #tab_settings #sso_config_history_pagination button:disabled {
        cursor: not-allowed;
        opacity: .45;
      }

      @media (max-width: 767px) {
        #tab_settings .configuration-tabs .nav-tabs > li > a {
          min-height: 48px;
          padding: 13px 16px 11px;
          font-size: 12px;
        }

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
          padding-left: 0;
        }

        #tab_settings .sso-config-note {
          padding: 15px 20px;
        }

        #tab_settings .configuration-history-table,
        #tab_settings .configuration-history-table tbody,
        #tab_settings .configuration-history-table tr,
        #tab_settings .configuration-history-table td {
          display: block;
          width: 100%;
        }

        #tab_settings .configuration-history-table colgroup,
        #tab_settings .configuration-history-table thead {
          display: none;
        }

        #tab_settings .configuration-history-table tbody tr:not(.configuration-history-state-row) {
          padding: 10px 15px;
          border-bottom: 1px solid #e8ecf1;
        }

        #tab_settings .configuration-history-table tbody tr:not(.configuration-history-state-row) td {
          display: grid;
          grid-template-columns: 112px minmax(0, 1fr);
          gap: 12px;
          padding: 8px 0;
          border: 0;
        }

        #tab_settings .configuration-history-table tbody tr:not(.configuration-history-state-row) td::before {
          content: attr(data-label);
          color: #7b8797;
          font-size: 9px;
          font-weight: 700;
          letter-spacing: .05em;
          text-transform: uppercase;
        }

        #tab_settings .configuration-history-table tbody tr:not(.configuration-history-state-row) td > span {
          grid-column: 2;
        }

        #tab_settings #sso_config_history_pagination {
          justify-content: center;
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

      #tab_versioning .version-release-toggle {
        display: grid;
        grid-template-columns: 132px minmax(0, 1fr) 34px;
        align-items: center;
        width: 100%;
        padding: 0 12px 0 0;
        border: 0;
        background: #fff;
        color: inherit;
        text-align: left;
        cursor: pointer;
      }

      #tab_versioning .version-release-toggle:hover,
      #tab_versioning .version-release-toggle:focus {
        background: #f8fbfd;
        outline: none;
      }

      #tab_versioning .version-release-meta {
        display: block;
        padding: 14px 16px;
        border-right: 1px solid #edf0f4;
        background: #fbfcfd;
      }

      #tab_versioning .version-latest-label {
        display: inline-block;
        margin: 0;
        padding: 3px 8px;
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
        margin-bottom: 4px;
        color: #263447;
        font-size: 17px;
        font-weight: 700;
        line-height: 1.2;
      }

      #tab_versioning .version-release-date {
        color: #7b8797;
        font-size: 11px;
        font-weight: 500;
      }

      #tab_versioning .version-release-content {
        border-top: 1px solid #edf0f4;
        padding: 22px 26px 24px;
      }

      #tab_versioning .version-release-content[hidden] {
        display: none;
      }

      #tab_versioning .version-release-summary {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0 18px;
        color: #526176;
        font-size: 14px;
        font-weight: 600;
      }

      #tab_versioning .version-release-chevron {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        margin-right: 4px;
        border-radius: 50%;
        background: #f1f6f9;
        color: #7f8b9a;
        transition: transform .18s ease;
      }

      #tab_versioning .version-release-card.is-open .version-release-chevron {
        transform: rotate(180deg);
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

      #tab_versioning .version-release-controls {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        padding-top: 5px;
      }

      #tab_versioning .version-release-controls[hidden],
      #tab_versioning .version-release-controls button[hidden] {
        display: none;
      }

      #tab_versioning .version-release-controls button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 38px;
        padding: 8px 14px;
        border: 1px solid #cfe0eb;
        border-radius: 7px;
        background: #fff;
        color: #168fcb;
        font-size: 11px;
        font-weight: 600;
        gap: 7px;
        transition: border-color .18s ease, background .18s ease, color .18s ease;
      }

      #tab_versioning .version-release-controls button:hover,
      #tab_versioning .version-release-controls button:focus {
        border-color: #a9d4e7;
        background: #eef8fd;
        color: #087eaf;
      }

      #tab_versioning .version-release-latest {
        border-color: #dce4ec;
        color: #68778a;
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

        #tab_versioning .version-release-toggle {
          grid-template-columns: minmax(0, 1fr) 34px;
          padding-right: 8px;
        }

        #tab_versioning .version-release-meta {
          padding: 12px 14px;
          border-right: 0;
        }

        #tab_versioning .version-release-summary {
          grid-column: 1 / -1;
          grid-row: 2;
          padding: 0 14px 12px;
        }

        #tab_versioning .version-release-chevron {
          grid-column: 2;
          grid-row: 1;
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
