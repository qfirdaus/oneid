<?php
require_once __DIR__ . '/secrets.php';

//connection to sybase
function EXTERNAL_DATA_SOURCE_GET_ALL_USER(){
		$connection = odbc_connect(oneid_secret('ONEID_STAFF_ODBC_DSN'), oneid_secret('ONEID_STAFF_ODBC_USERNAME'), oneid_secret('ONEID_STAFF_ODBC_PASSWORD'));
		if (!$connection) {
		echo "Couldn't make a connection kaunselor!"; 
		exit;
		}
		$connection_student = odbc_connect(oneid_secret('ONEID_STUDENT_LOOKUP_ODBC_DSN'), oneid_secret('ONEID_STUDENT_LOOKUP_ODBC_USERNAME'), oneid_secret('ONEID_STUDENT_LOOKUP_ODBC_PASSWORD'));
		if (!$connection_student) {
		echo "Couldn't make a connection kaunselor!"; 
		exit;
		}
	$sql = 'SELECT (gelaran + " " + nama)  as data1,idpekerja as data2, nopekerja as data3, ISNULL(nokp,"") as data4, ISNULL(email,"") as data5, ISNULL(jabatansemasa,"") as data6, ISNULL(jawatansemasa,"") as data7,  "" as data8, "" as data9, "" as data10, "" as data11, "" as data12, jenis as ext_data_source_category  FROM SSO_Staf_Aktif';
	
    $rs = odbc_exec($connection, $sql);
	$rows = array();

	while($myRow = odbc_fetch_array( $rs )){ //<--lots of rows
		$rows[] = $myRow;
	}
	odbc_close($connection);
							
	$sql = 'SELECT nama  as data1,no_matrik as data2, "" as data3, ISNULL(nokp,"") as data4, ISNULL(email,"") as data5, nama_ptj as data6, program as data7,  "" as data8, "" as data9, "" as data10, "" as data11, "" as data12, "Pelajar" as ext_data_source_category  FROM v210_sso_student_aktif';
    $rs = odbc_exec($connection_student, $sql);
	$rows_student = array();

	while($myRow = odbc_fetch_array( $rs )){ //<--lots of rows
		$rows_student[] = $myRow;
	}
	odbc_close($connection_student);
	
	$final_data = array_merge($rows,$rows_student);
	return $final_data;
}

function EXTERNAL_DATA_SOURCE_GET_SPECIFIC_USER($user_id){
		$connection = odbc_connect(oneid_secret('ONEID_STAFF_ODBC_DSN'), oneid_secret('ONEID_STAFF_ODBC_USERNAME'), oneid_secret('ONEID_STAFF_ODBC_PASSWORD'));
		if (!$connection) {
		echo "Couldn't make a connection kaunselor!"; 
		exit;
		}
		$connection_student = odbc_connect(oneid_secret('ONEID_STUDENT_LOOKUP_ODBC_DSN'), oneid_secret('ONEID_STUDENT_LOOKUP_ODBC_USERNAME'), oneid_secret('ONEID_STUDENT_LOOKUP_ODBC_PASSWORD'));
		if (!$connection_student) {
		echo "Couldn't make a connection kaunselor!"; 
		exit;
		}
	$sql = 'SELECT (gelaran + " " + nama)  as data1,idpekerja as data2, nopekerja as data3, ISNULL(nokp,"") as data4, ISNULL(email,"") as data5, ISNULL(jabatansemasa,"") as data6, ISNULL(jawatansemasa,"") as data7,  "" as data8, "" as data9, "" as data10, "" as data11, "" as data12, jenis as ext_data_source_category  FROM SSO_Staf_Aktif WHERE nokp=?';
	$statement = odbc_prepare($connection, $sql);
    $rs = $statement ? odbc_execute($statement, [(string) $user_id]) : false;
	$rows = array();

	while($rs && ($myRow = odbc_fetch_array($statement))){
		$rows[] = $myRow;
	}
	odbc_close($connection);
							
	$sql = 'SELECT nama  as data1,no_matrik as data2, "" as data3, ISNULL(nokp,"") as data4, ISNULL(email,"") as data5, nama_ptj as data6, program as data7,  "" as data8, "" as data9, "" as data10, "" as data11, "" as data12, "Pelajar" as ext_data_source_category  FROM v210_sso_student_aktif WHERE nokp=?';
	$statementStudent = odbc_prepare($connection_student, $sql);
    $rs = $statementStudent ? odbc_execute($statementStudent, [(string) $user_id]) : false;
	$rows_student = array();

	while($rs && ($myRow = odbc_fetch_array($statementStudent))){
		$rows_student[] = $myRow;
	}
	odbc_close($connection_student);
	
	$final_data = array_merge($rows,$rows_student);
	return $final_data;
}

function SAMPLE_DATA_SOURCE_GET_ALL_USER(){
	 //----- 1. Script for Sample data USING CURL CALL
            $url = "http://localhost/SSO_IDP/sample_data.php";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
            curl_setopt($ch,CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/x-www-form-urlencoded'));
            $result = curl_exec($ch);
            // also get the error and response code
            $errors = curl_error($ch);
            $response = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return json_decode($result, true);
            //----- END OF Curl Call
}

function SAMPLE_DATA_SOURCE_GET_SPECIFIC_USER($user_id){
	 //----- 1. Script for Sample data USING CURL CALL
            $url = "http://localhost/SSO_IDP/sample_data.php";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
            curl_setopt($ch,CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/x-www-form-urlencoded'));
            $result = curl_exec($ch);
            // also get the error and response code
            $errors = curl_error($ch);
            $response = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $data = json_decode($result, true);

            foreach ($data as $i => $ii) {
            	if($data[$i]['data4']==$user_id){
            		return $data[$i];
            	}
            }
            return [];

            //----- END OF Curl Call
}
?>
