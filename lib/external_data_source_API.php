<?php
require_once __DIR__ . '/secrets.php';
require_once dirname(__DIR__) . '/app/Sync/ExternalRowNormalizer.php';

//connection to sybase
function EXTERNAL_DATA_SOURCE_GET_ALL_USER(){
	/* echo "hai";
	return */;
		if (!function_exists('odbc_connect')) {
			throw new RuntimeException('ODBC_EXTENSION_UNAVAILABLE');
		}
		$connection = odbc_connect(oneid_secret('ONEID_STAFF_ODBC_DSN'), oneid_secret('ONEID_STAFF_ODBC_USERNAME'), oneid_secret('ONEID_STAFF_ODBC_PASSWORD'));
		if (!$connection) {
		throw new RuntimeException('EXTERNAL_STAFF_CONNECTION_FAILED');
		}
		$connection_student = odbc_connect(oneid_secret('ONEID_STUDENT_SYNC_ODBC_DSN'), oneid_secret('ONEID_STUDENT_SYNC_ODBC_USERNAME'), oneid_secret('ONEID_STUDENT_SYNC_ODBC_PASSWORD'));
		if (!$connection_student) {
		odbc_close($connection);
		throw new RuntimeException('EXTERNAL_STUDENT_CONNECTION_FAILED');
		}
	$sql = 'SELECT (gelaran + " " + nama)  as data1,idpekerja as data2, nopekerja as data3, ISNULL(nokp,"") as data4, ISNULL(email,"") as data5, ISNULL(jabatansemasa,"") as data6, ISNULL(jawatansemasa,"") as data7,  "" as data8, "" as data9, "" as data10, "" as data11, "" as data12, jenis as ext_data_source_category  FROM ehrmdb.dbo.SSO_Staf_Aktif';
	
    $rs = odbc_exec($connection, $sql);
	if ($rs === false) {
		odbc_close($connection);
		odbc_close($connection_student);
		throw new RuntimeException('EXTERNAL_STAFF_QUERY_FAILED');
	}
	$rows = array();

	while($myRow = odbc_fetch_array( $rs )){ //<--lots of rows
		$rows[] = \OneId\App\Sync\ExternalRowNormalizer::normalize($myRow);
	}
	odbc_close($connection);
							
	$sql = 'SELECT nama  as data1,no_matrik as data4, "" as data3, ISNULL(nokp,"") as data2, ISNULL(email,"") as data5, nama_ptj as data6, program as data7,  "" as data8, "" as data9, "" as data10, "" as data11, "" as data12, "Pelajar" as ext_data_source_category  FROM v210_sso_student_aktif';
    $rs = odbc_exec($connection_student, $sql);
	if ($rs === false) {
		odbc_close($connection_student);
		throw new RuntimeException('EXTERNAL_STUDENT_QUERY_FAILED');
	}
	$rows_student = array();

	while($myRow = odbc_fetch_array( $rs )){ //<--lots of rows
		$rows_student[] = \OneId\App\Sync\ExternalRowNormalizer::normalize($myRow);
	}
	odbc_close($connection_student);
	
	$final_data = array_merge($rows,$rows_student);
	return $final_data;
}

function EXTERNAL_DATA_SOURCE_GET_SPECIFIC_USER($user_id, $source_family = 'both'){
	$user_id = trim((string) $user_id);
	if ($user_id === '' || strlen($user_id) > 20 || preg_match('/^[A-Za-z0-9._@-]+$/', $user_id) !== 1) {
		throw new InvalidArgumentException('EXTERNAL_USER_ID_INVALID');
	}
	if (!function_exists('odbc_connect') || !function_exists('odbc_exec')) {
		throw new RuntimeException('ODBC_EXTENSION_UNAVAILABLE');
	}
	$source_family = strtolower(trim((string) $source_family));
	if (!in_array($source_family, ['staff', 'student', 'both'], true)) {
		throw new InvalidArgumentException('EXTERNAL_SOURCE_FAMILY_INVALID');
	}

	$connection = null;
	$connection_student = null;

	try {
		$rows = [];
		$rowsStudent = [];

		// SELECT-only lookup. Staff primary identity is NRIC in data4.
		// FreeTDS against this Sybase source returns IM001 for
		// SQLDescribeParameter/odbc_prepare. The identity has already passed a
		// strict ASCII allowlist that excludes quotes and SQL metacharacters.
		$identityLiteral = "'" . $user_id . "'";
		if ($source_family === 'staff' || $source_family === 'both') {
			$connection = odbc_connect(
				oneid_secret('ONEID_STAFF_ODBC_DSN'),
				oneid_secret('ONEID_STAFF_ODBC_USERNAME'),
				oneid_secret('ONEID_STAFF_ODBC_PASSWORD')
			);
			if (!$connection) {
				throw new RuntimeException('EXTERNAL_STAFF_CONNECTION_FAILED');
			}
			$sql = 'SELECT (gelaran + " " + nama) as data1,idpekerja as data2,nopekerja as data3,ISNULL(nokp,"") as data4,ISNULL(email,"") as data5,ISNULL(jabatansemasa,"") as data6,ISNULL(jawatansemasa,"") as data7,"" as data8,"" as data9,"" as data10,"" as data11,"" as data12,jenis as ext_data_source_category FROM ehrmdb.dbo.SSO_Staf_Aktif WHERE nokp=' . $identityLiteral;
			$statement = odbc_exec($connection, $sql);
			if (!$statement) {
				throw new RuntimeException('EXTERNAL_STAFF_LOOKUP_FAILED');
			}
			while ($myRow = odbc_fetch_array($statement)) {
				$rows[] = \OneId\App\Sync\ExternalRowNormalizer::normalize($myRow);
			}
		}

		// Match the full-sync mapping: student primary identity is no_matrik in data4.
		// This Sybase view exposes no_matrik as INT. Compare its explicit text
		// representation so a staff identity containing '-' cannot trigger an
		// implicit VARCHAR-to-INT conversion before returning an empty match.
		if ($source_family === 'student' || $source_family === 'both') {
			$connection_student = odbc_connect(
				oneid_secret('ONEID_STUDENT_SYNC_ODBC_DSN'),
				oneid_secret('ONEID_STUDENT_SYNC_ODBC_USERNAME'),
				oneid_secret('ONEID_STUDENT_SYNC_ODBC_PASSWORD')
			);
			if (!$connection_student) {
				throw new RuntimeException('EXTERNAL_STUDENT_CONNECTION_FAILED');
			}
			$sql = 'SELECT nama as data1,ISNULL(nokp,"") as data2,"" as data3,no_matrik as data4,ISNULL(email,"") as data5,nama_ptj as data6,program as data7,"" as data8,"" as data9,"" as data10,"" as data11,"" as data12,"Pelajar" as ext_data_source_category FROM v210_sso_student_aktif WHERE CONVERT(VARCHAR(64),no_matrik)=' . $identityLiteral;
			$statementStudent = odbc_exec($connection_student, $sql);
			if (!$statementStudent) {
				throw new RuntimeException('EXTERNAL_STUDENT_LOOKUP_FAILED');
			}
			while ($myRow = odbc_fetch_array($statementStudent)) {
				$rowsStudent[] = \OneId\App\Sync\ExternalRowNormalizer::normalize($myRow);
			}
		}

		return array_merge($rows, $rowsStudent);
	} finally {
		if ($connection !== null) {
			odbc_close($connection);
		}
		if ($connection_student !== null) {
			odbc_close($connection_student);
		}
	}
}

function SAMPLE_DATA_SOURCE_GET_ALL_USER(){
	 //----- 1. Script for Sample data USING CURL CALL
            $url = (string) oneid_config('ONEID_SAMPLE_DATA_URL');
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
            $url = (string) oneid_config('ONEID_SAMPLE_DATA_URL');
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
