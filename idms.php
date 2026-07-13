<?php
header('Content-Type: application/json');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$query            = $data['query'] ?? $_GET['query'] ?? null;
$limit            = $data['limit'] ?? $_GET['limit'] ?? null;
$keyword          = $data['keyword'] ?? $_GET['keyword'] ?? null;
$kdjabatansemasa  = $data['kdjabatansemasa'] ?? $_GET['kdjabatansemasa'] ?? null;

if($query){

    switch($query){

        case "GET_LIST_OF_STAFF":
            echo json_encode(GET_LIST_OF_STAFF($limit, $keyword, $kdjabatansemasa));
        break;

        case "GET_LIST_OF_JABATAN":
            echo json_encode(GET_LIST_OF_JABATAN($limit, $keyword));
        break;

        default:
            echo json_encode([
                "status" => "error",
                "message" => "Invalid query"
            ]);
        break;
    }

} else {
    echo json_encode([
        "status" => "error",
        "message" => "No query provided"
    ]);
}



// ================= FUNCTIONS =================

function db_connect(){

    $connection = odbc_connect(
        "Driver={Adaptive Server Enterprise};Server=172.16.2.14;Port=5004;Database=ehrmdb",
        "idms",
        "idm$@upnm"
    );

    if (!$connection) {
        echo json_encode([
            "status"=>"error",
            "message"=>"Database connection failed",
            "odbc_error"=>odbc_errormsg()
        ]);
        exit;
    }

    return $connection;
}

function GET_LIST_OF_STAFF($limit = null, $keyword = null, $kdjabatansemasa = null){
    $connection = db_connect();

    $limit = (int)$limit;
    if($limit <= 0){
        $limit = 10;
    }

    odbc_exec($connection, "SET ROWCOUNT $limit");

    $sql = "SELECT idpekerja, nopekerja, nama, email, jabatansemasa, jawatansemasa, kdjabatansemasa, status
            FROM idms_staff
            WHERE status <> 'Berhenti'";

    if(!empty($kdjabatansemasa)){
        $kdjabatansemasa = trim($kdjabatansemasa);
        $kdjabatansemasa = str_replace("'", "''", $kdjabatansemasa);

        $sql .= " AND kdjabatansemasa = '".$kdjabatansemasa."'";
    }

    if(!empty($keyword)){
        $keyword = trim($keyword);
        $keyword = strtolower($keyword);
        $keyword = str_replace("'", "''", $keyword);

        $sql .= " AND (
                    LOWER(nama) LIKE '%".$keyword."%'
                    OR LOWER(nopekerja) LIKE '%".$keyword."%'
                  )";
    }

    $sql .= " ORDER BY nama ASC";

    $rs = odbc_exec($connection, $sql);

    $rows = [];

    if($rs){
        while($myRow = odbc_fetch_array($rs)){
            $rows[] = [
                "id" => (int)$myRow["idpekerja"],
                "text" => trim($myRow["nama"])." (".trim($myRow["nopekerja"]).")",
                "nama" => trim($myRow["nama"]),
                "nopekerja" => trim($myRow["nopekerja"]),
                "email" => trim($myRow["email"]),
                "jabatan" => trim($myRow["jabatansemasa"]),
                "jawatan" => trim($myRow["jawatansemasa"]),
                "kdjabatansemasa" => trim($myRow["kdjabatansemasa"])
            ];
        }
    }

    odbc_exec($connection, "SET ROWCOUNT 0");
    odbc_close($connection);

    return [
        "status" => "success",
        "count" => count($rows),
        "data" => $rows
    ];
}

function GET_LIST_OF_JABATAN($limit = null, $keyword = null){
    $connection = db_connect();

    $limit = (int)$limit;

    // apply limit hanya kalau user pass limit > 0
    if($limit > 0){
        odbc_exec($connection, "SET ROWCOUNT $limit");
    }

    $sql = "SELECT kod_ptj, nama_ptj, singkat
            FROM idms_jabatan
            WHERE 1=1";

    if(!empty($keyword)){
        $keyword = trim($keyword);
        $keyword = strtolower($keyword);
        $keyword = str_replace("'", "''", $keyword);

        $sql .= " AND (
                    LOWER(nama_ptj) LIKE '%".$keyword."%'
                    OR LOWER(singkat) LIKE '%".$keyword."%'
                    OR LOWER(kod_ptj) LIKE '%".$keyword."%'
                  )";
    }

    $sql .= " ORDER BY nama_ptj ASC";

    $rs = odbc_exec($connection, $sql);

    if(!$rs){
        $err = odbc_errormsg($connection);

        if($limit > 0){
            odbc_exec($connection, "SET ROWCOUNT 0");
        }

        odbc_close($connection);

        return [
            "status" => "error",
            "message" => "Query failed",
            "sql" => $sql,
            "odbc_error" => $err
        ];
    }

    $rows = [];

    while($myRow = odbc_fetch_array($rs)){
        $id    = trim($myRow["kod_ptj"]);
        $nama  = trim($myRow["nama_ptj"]);
        $short = trim($myRow["singkat"]);

        $text = $nama;
        if($short !== ""){
            $text .= " (".$short.")";
        }

        $rows[] = [
            "id" => $id,
            "text" => $text,
            "kdjabatan" => $id,
            "jabatan" => $nama,
            "shortjabatan" => $short
        ];
    }

    if($limit > 0){
        odbc_exec($connection, "SET ROWCOUNT 0");
    }

    odbc_close($connection);

    return [
        "status" => "success",
        "count" => count($rows),
        "data" => $rows
    ];
}

?>