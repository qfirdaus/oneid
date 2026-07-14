<?php 
require_once __DIR__ . '/lib/secrets.php';
require_once __DIR__ . '/lib/integration_security.php';

header('Content-Type: application/json; charset=utf-8');

ini_set('always_populate_raw_post_data', -1);
$json = file_get_contents('php://input');
$json = str_replace('"{"', "{", $json);
$json = str_replace('"}"', "}", $json);
$data = json_decode($json,true);

$requestedScope = isset($data['query']) && is_array($data['query']) ? 'skp:sync' : 'skp:profile';
oneid_integration_guard('skp', $requestedScope);
if (!is_array($data)) {
	oneid_integration_json_error(400, 'invalid_json', 'A valid JSON request body is required.');
}
if (!array_key_exists('query', $data)) {
	oneid_integration_json_error(400, 'missing_query', 'A query is required.');
}



if(isset($data['query'])){
	if(is_array($data['query'])){
		
		switch($data['query']['action']){
			case "GET_CHUNK":
			$sync_data = (EXTERNAL_SKP_SYNC());
			$chunk = array_chunk($sync_data, 5000);
			
			
			
			
			echo count($chunk);
			break;
			case "GET_SYNC":
				$sync_data = (EXTERNAL_SKP_SYNC());
				$chunk = array_chunk($sync_data, 5000);
				$chunkIndex = filter_var($data['query']['CHUNK_SIZE'] ?? null, FILTER_VALIDATE_INT, [
					'options' => ['min_range' => 0],
				]);
				if ($chunkIndex === false || !isset($chunk[$chunkIndex])) {
					oneid_integration_json_error(400, 'invalid_chunk', 'The requested chunk does not exist.');
				}
				$data['query']['CHUNK_SIZE'] = $chunkIndex;
				
				foreach ($chunk[$chunkIndex] as $i => $ii) {
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['matrik'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['matrik']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['nokp'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['nokp']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['notentera'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['notentera']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['nama'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['nama']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['jantina'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['jantina']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['bangsa_detail'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['bangsa_detail']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['agama'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['agama']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['negeri'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['negeri']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['sesimasuk'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['sesimasuk']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['alamat1'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['alamat1']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['alamat2'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['alamat2']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['alamat3'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['alamat3']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['alamat4'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['alamat4']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['telno'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['telno']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['hpno'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['hpno']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['email'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['email']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['kdprogram'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['kdprogram']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['program'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['program']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['semsemasa'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['semsemasa']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['nosem'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['nosem']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['kategori_kadet'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['kategori_kadet']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['fakulti'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['fakulti']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['tahap_pengajian'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['tahap_pengajian']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['nokpibu'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['nokpibu']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['nokpbapa'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['nokpbapa']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['namaibu'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['namaibu']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['namabapa'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['namabapa']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['kdtahap'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['kdtahap']);
				  $chunk[$data['query']['CHUNK_SIZE']][$i]['user_type'] = utf8_encode($chunk[$data['query']['CHUNK_SIZE']][$i]['user_type']);
				  
				  
				}
				
				echo json_encode(($chunk[$chunkIndex]));
			break;
			
		}
		
	}else{
			$q_r = EXTERNAL_SKP_INFO_QUERY($data['query']); //query result
			//Check empty or not
				if(!empty($q_r)){
					//count data
					if(count($q_r) > 1){
							//foreach ($q_r as $i => $ii) {
								echo json_encode(get_highest($q_r));
								//echo json_encode($q_r[0]);
							//}
					}else{
						echo json_encode($q_r[0]);
					}
				}else{
					echo json_encode($q_r);
				}
	}
	
}




//echo json_encode();

function get_highest($arr) {
    $max = $arr[0]; // set the highest object to the first one in the array
    foreach($arr as $obj) { // loop through every object in the array
	//echo json_encode($obj);
	//echo $obj['kdtahap'];
	//return;
        $num = $obj['kdtahap']; // get the number from the current object
        if($num > $max['kdtahap']) { // If the number of the current object is greater than the maxs number:
            $max = $obj; // set the max to the current object
        }
    }
    return $max; // Loop is complete, so we have found our max and can return the max object
}


function EXTERNAL_SKP_INFO_QUERY($user_id){
	$connection = odbc_connect(oneid_secret('ONEID_SKP_ODBC_DSN'), oneid_secret('ONEID_SKP_ODBC_USERNAME'), oneid_secret('ONEID_SKP_ODBC_PASSWORD'));
	
		if (!$connection) {
		oneid_integration_audit('upstream_connection_failed', ['endpoint' => 'skp']);
		oneid_integration_json_error(503, 'upstream_unavailable', 'Upstream service is unavailable.');
		}
		
		$sql = 'SELECT matrik,nokp,notentera,nama,jantina,bangsa_detail,agama,negeri,sesimasuk,alamat1,alamat2,alamat3,alamat4,telno,hpno,email,kdprogram,program,semsemasa,nosem,kategori_kadet,fakulti,tahap_pengajian,nokpibu,nokpbapa,namaibu,namabapa,kdtahap,user_type="STUDENT" FROM v210 WHERE nokp=? AND status = "02"';
	
    $statement = odbc_prepare($connection, $sql);
    $rs = $statement ? odbc_execute($statement, [(string) $user_id]) : false;
	$rows = array();

	while($rs && ($myRow = odbc_fetch_array($statement))){
		$rows[] = $myRow;
	}
	if (!$rs) {
		oneid_integration_audit('upstream_query_failed', ['endpoint' => 'skp', 'operation' => 'profile']);
	}
	odbc_close($connection);
	
	
	
	
	return $rows;
}


function EXTERNAL_SKP_SYNC(){
	$connection = odbc_connect(oneid_secret('ONEID_SKP_ODBC_DSN'), oneid_secret('ONEID_SKP_ODBC_USERNAME'), oneid_secret('ONEID_SKP_ODBC_PASSWORD'));
	
		if (!$connection) {
		oneid_integration_audit('upstream_connection_failed', ['endpoint' => 'skp']);
		oneid_integration_json_error(503, 'upstream_unavailable', 'Upstream service is unavailable.');
		}
		
		$sql = 'SELECT matrik,nokp,notentera,nama,jantina,bangsa_detail,agama,negeri,sesimasuk,alamat1,alamat2,alamat3,alamat4,telno,hpno,email,kdprogram,program,semsemasa,nosem,kategori_kadet,fakulti,tahap_pengajian,nokpibu,nokpbapa,namaibu,namabapa,kdtahap,user_type="STUDENT" FROM v210 where nokp <> "" AND status = "02"';
		
	
    $rs = odbc_exec($connection, $sql);
	$rows = array();

	while($myRow = odbc_fetch_array( $rs )){ //<--lots of rows
		array_push($rows,$myRow);
		//$rows[] = $myRow;
	}
	odbc_close($connection);
	return $rows;
}
?>
