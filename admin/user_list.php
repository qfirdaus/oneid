<?php
require_once __DIR__ . '/../lib/session_security.php';
oneid_start_secure_session();
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/SSO_IDP_INC.php';
require_once __DIR__ . '/../lib/request_security.php';
oneid_require_admin_page();
oneid_require_active_sso_page($operation);

$categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
if ($categoryId === false || $categoryId === null) {
    http_response_code(400);
    exit('Invalid category');
}
$categoryName = htmlspecialchars((string) ($_GET['category_name'] ?? ''), ENT_QUOTES, 'UTF-8');
$userlist = $operation->admin_get_specific_category_user_listing($categoryId);
//Merge remaining $get_move_info into unique item 
// echo json_encode($get_move_info);

// return;
// echo json_encode($get_current_asset_info);
// echo json_encode($get_move_info);
?>
<!DOCTYPE html>
<html>
<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <!-- Page title -->
    <title>UPNM SSO</title>

  

</head>
<body class="blank tooltip-demo">

<div class="row" id="printThis">
        <div class="col-lg-12 main_recipt_body" > <!-- Hidden -->
            <div class="hpanel">
                <div class="panel-body">

                <!-- table-condensed --> 
                    <div class="report_header">

                        <table class="table table-striped" id="recipt_result_view" style="table-layout:fixed; overflow: auto;">

                    <tr>
                    <div class="row">
                        <div class="col-sm-12 text-center">


                            <center>                            
                                <p><b>Category : <?php echo $categoryName; ?></b></p>
                                <p><b>User List</b></p> 
                            </center>                                    
                        </div>
                    </div>
                    <hr  style="border: 0.5px solid #000;" />
                    <div style="position:relative;top: -20px;">
                    <h6>Report generated on <?php $dates = new DateTime();
                                echo $dates->format( 'Y.m.d' );?></h6>
                    </div>




                    <table id="export_this" style="width:100%;height:100%;border-collapse:collapse;">
                        <tbody>
                            <tr style="height: 15px;"/>
                            <tr style="height: 15px;"/>
                            <tr>
                                <td align="left"><b>#</b></td>
                                <td align="left"><b>ID</b></td>
                                <td align="left"><b>Name</b></td>
                                <td align='left'><b>Description</b></td>
                            </tr>
                            <tr style="height: 15px;"/>  
                            <?php if(count($userlist) == 0) { ?>
                                
                                <tr style="border-bottom: dotted 1px black;">
                                    <td align="left" colspan="3">No data yet</td>
                                </tr>
                                
                            <?php }else{  $count = 1; ?>

                            <?php foreach ($userlist as $m => $mm) { ?>

                           
                                <tr style="border-bottom: dotted 1px black;">
                                    <td align="left"><?php echo $count . ". "; ?></td>
                                    <td align="left"><?php echo  $userlist[$m]['data4']; ?></td>
                                    <td align="left"><?php echo  $userlist[$m]['data1']; ?></td>
                                    <td align="left"><?php echo  $userlist[$m]['data6'] . " " . $userlist[$m]['data7']; ?></td>
                                </tr> 

                            <?php $count++;} ?>
                            <?php } ?>

                        </tbody>
                    </table>

                </div> <!-- end of panel body -->
            </div>

        </div>
    </div>
    </div>


<style>
 body {counter-reset:section;}
.count:before
{
counter-increment:section;
content:counter(section);
}

.my-background {
  background: #000000;
}

.table-light thead > tr > th {
    border-top: 2px solid !important;
    border-bottom: 2px solid !important;
}
</style>
</body>
</html>

