<?php

//connection to sybase
/* 		$connection = odbc_connect("dbserver","ssoupnm","ss0UPNM**"); 
		if (!$connection) {
		echo "Couldn't make a connection kaunselor!"; 
		exit;
		}
		$connection_student = odbc_connect("dbserver_student","ssoupnm","ss0UPNM**"); 
		if (!$connection_student) {
		echo "Couldn't make a connection kaunselor!"; 
		exit;
		} */
		
		echo PHP_INT_SIZE == 8 ? '64-bit' : '32-bit';

		
		
		
		return;
		
		$date_s = date("YmdHis");
	$sql = 'SELECT TOP 0 (gelaran + " " + nama)  as data1,idpekerja as data2, nopekerja as data3, "NULL-'.$date_s.'" as data4, email as data5, jabatansemasa as data6, jawatansemasa as data7,  "" as data8, "" as data9, "" as data10, "" as data11, "" as data12, jenis as ext_data_source_category  FROM SSO_Staf_Aktif';
	
    $rs = odbc_exec($connection, $sql);
	$rows = array();

	while($myRow = odbc_fetch_array( $rs )){ //<--lots of rows
		$rows[] = $myRow;
	}
	odbc_close($connection);
							
	$sql = 'SELECT TOP 5 nama  as data1,no_matrik as data2, "" as data3, nokp as data4, email as data5, nama_ptj as data6, program as data7,  "" as data8, "" as data9, "" as data10, "" as data11, "" as data12, "Pelajar" as ext_data_source_category  FROM v210_sso_student_aktif where no_matrik = 3211404';
    $rs = odbc_exec($connection_student, $sql);
	$rows_student = array();

	while($myRow = odbc_fetch_array( $rs )){ //<--lots of rows
		$rows_student[] = $myRow;
	}
	odbc_close($connection_student);
	
	$final_data = array_merge($rows,$rows_student);
	
	
              foreach ($final_data as $i => $ii) {          
                if(isset($final_data[$i]['data4'])){
					if($final_data[$i]['data4'] == "" || $final_data[$i]['data4'] == " "){
						$final_data[$i]['data4']='NULL-'.date('YmdHis');
					}
				}else{
					$final_data[$i]['data4']='NULL-'.date('YmdHis');
				}  
			  }
	echo json_encode($final_data);


?>