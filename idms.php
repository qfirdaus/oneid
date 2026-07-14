<?php
require_once __DIR__ . '/lib/secrets.php';
require_once __DIR__ . '/lib/integration_security.php';

header('Content-Type: application/json; charset=utf-8');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$query            = $data['query'] ?? $_GET['query'] ?? null;
$limit            = $data['limit'] ?? $_GET['limit'] ?? null;
$keyword          = $data['keyword'] ?? $_GET['keyword'] ?? null;
$kdjabatansemasa  = $data['kdjabatansemasa'] ?? $_GET['kdjabatansemasa'] ?? null;

oneid_integration_guard('idms', 'idms:read');

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
        oneid_secret('ONEID_IDMS_ODBC_CONNECTION'),
        oneid_secret('ONEID_IDMS_ODBC_USERNAME'),
        oneid_secret('ONEID_IDMS_ODBC_PASSWORD')
    );

    if (!$connection) {
        oneid_integration_audit('upstream_connection_failed', ['endpoint' => 'idms']);
        oneid_integration_json_error(503, 'upstream_unavailable', 'Upstream service is unavailable.');
    }

    return $connection;
}

function GET_LIST_OF_STAFF($limit = null, $keyword = null, $kdjabatansemasa = null){
    $connection = db_connect();

    $limit = (int)$limit;
    if($limit <= 0){
        $limit = 10;
    }
    $limit = min($limit, 100);

    odbc_exec($connection, "SET ROWCOUNT $limit");

    $sql = "SELECT idpekerja, nopekerja, nama, email, jabatansemasa, jawatansemasa, kdjabatansemasa, status
            FROM idms_staff
            WHERE status <> 'Berhenti'";

    $params = [];
    if(!empty($kdjabatansemasa)){
        $sql .= " AND kdjabatansemasa = ?";
        $params[] = trim((string) $kdjabatansemasa);
    }

    if(!empty($keyword)){
        $sql .= " AND (
                    LOWER(nama) LIKE ?
                    OR LOWER(nopekerja) LIKE ?
                  )";
        $keywordParam = '%' . strtolower(trim((string) $keyword)) . '%';
        $params[] = $keywordParam;
        $params[] = $keywordParam;
    }

    $sql .= " ORDER BY nama ASC";

    $statement = odbc_prepare($connection, $sql);
    $rs = $statement ? odbc_execute($statement, $params) : false;

    $rows = [];

    if($rs){
        while($myRow = odbc_fetch_array($statement)){
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
    } else {
        oneid_integration_audit('upstream_query_failed', ['endpoint' => 'idms', 'operation' => 'staff_list']);
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

    // Had tetap mengelakkan response tanpa batas.
    if($limit <= 0){
        $limit = 100;
    }
    $limit = min($limit, 100);
    if($limit > 0){
        odbc_exec($connection, "SET ROWCOUNT $limit");
    }

    $sql = "SELECT kod_ptj, nama_ptj, singkat
            FROM idms_jabatan
            WHERE 1=1";

    $params = [];
    if(!empty($keyword)){
        $sql .= " AND (
                    LOWER(nama_ptj) LIKE ?
                    OR LOWER(singkat) LIKE ?
                    OR LOWER(kod_ptj) LIKE ?
                  )";
        $keywordParam = '%' . strtolower(trim((string) $keyword)) . '%';
        $params = [$keywordParam, $keywordParam, $keywordParam];
    }

    $sql .= " ORDER BY nama_ptj ASC";

    $statement = odbc_prepare($connection, $sql);
    $rs = $statement ? odbc_execute($statement, $params) : false;

    if(!$rs){
        if($limit > 0){
            odbc_exec($connection, "SET ROWCOUNT 0");
        }

        odbc_close($connection);

        oneid_integration_audit('upstream_query_failed', ['endpoint' => 'idms', 'operation' => 'department_list']);
        return [
            "status" => "error",
            "message" => "Upstream query failed",
            "request_id" => oneid_integration_request_id()
        ];
    }

    $rows = [];

    while($myRow = odbc_fetch_array($statement)){
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
