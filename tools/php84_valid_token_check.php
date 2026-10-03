<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Secret input is accepted only over stdin by the interactive Python wrapper.
ini_set('display_errors', '0');
try {
    require dirname(__DIR__) . '/bootstrap/app.php';
    require dirname(__DIR__) . '/lib/secrets.php';
    require dirname(__DIR__) . '/lib/auth_security.php';
    require dirname(__DIR__) . '/app/Auth/SsoTokenLifetimePolicy.php';
    date_default_timezone_set((string) oneid_config('ONEID_TIMEZONE'));
    if (!in_array(oneid_config('ONEID_ENVIRONMENT'), ['staging','uat'], true)) {
        throw new RuntimeException('UAT_ONLY');
    }
    $input = json_decode(stream_get_contents(STDIN), true, 8, JSON_THROW_ON_ERROR);
    $token = trim((string)($input['token'] ?? ''));
    if (strlen($token) < 32 || strlen($token) > 512) throw new RuntimeException('MODERN_RAW_TOKEN_REQUIRED');
    $pdo = new PDO(oneid_secret('ONEID_DB_DSN'), oneid_secret('ONEID_DB_USERNAME'), oneid_secret('ONEID_DB_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    $hash = oneid_token_hash($token);
    $q = $pdo->prepare('SELECT A.*, B.u_id AS account_id, B.data3 AS staff_id, B.avail_status AS account_active FROM token_tbl A JOIN user_tbl B ON B.u_id=A.user_id WHERE A.token_id=:hash LIMIT 1');
    $q->execute(['hash'=>$hash]); $before = $q->fetch(PDO::FETCH_ASSOC);
    if (!$before) throw new RuntimeException('TOKEN_NOT_FOUND');
    if (trim((string)$before['staff_id']) !== '0530-09') throw new RuntimeException('TOKEN_NOT_FOR_APPROVED_TEST_ACCOUNT');
    $hours = (float)$pdo->query('SELECT token_timeout FROM sys_config WHERE singleton_key=1')->fetchColumn();
    $policy = new \OneId\App\Auth\SsoTokenLifetimePolicy();
    $assertFresh = static function () use ($before, $policy, $hours): void {
        $state = $policy->evaluate($before['token_issued_at'], date('Y-m-d H:i:s'), $hours);
        if ((int)$before['status'] !== 1 || (int)$before['account_active'] !== 1 || $state['state'] !== 'active'
            || $state['lifetime_seconds']-$state['age_seconds'] < 600 || !empty($before['policy_revoke_at'])) {
            throw new RuntimeException('FRESH_ACTIVE_TOKEN_WITHOUT_PENDING_REVOCATION_REQUIRED');
        }
    };
    $assertFresh();
    $benchmarkMode=($input['mode']??'')==='benchmark';
    $timings=['8.3'=>[],'8.4'=>[]];
    $applicationMode=($input['mode']??'')==='applications';
    $cases=[['name'=>'IDP','site_code'=>'IDP','expected'=>'1']];
    if($applicationMode){ require __DIR__.'/php84_application_cases.php'; $cases=php84ApplicationCases($pdo); }
    $caseResults=[];
    for($round=0;$round<($benchmarkMode?6:1);$round++){
    foreach($cases as $case){
    $responses = [];
    $order=$benchmarkMode && $round%2===1 ? ['8.4'=>24484,'8.3'=>443] : ['8.3'=>443,'8.4'=>24484];
    foreach ($order as $version=>$port) {
        $assertFresh();
        $headers = [];
        $curl = curl_init('https://oneid-uat.upnm.edu.my/api.php');
        curl_setopt_array($curl, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode(['flag'=>'1','data'=>['site_id'=>$case['site_code'],'token'=>$token]], JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15,
            CURLOPT_PROXY=>'', CURLOPT_FOLLOWLOCATION=>false, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_CONNECT_TO=>['oneid-uat.upnm.edu.my:443:127.0.0.1:'.$port],
            CURLOPT_HEADERFUNCTION=>static function ($c, string $line) use (&$headers): int {
                if (stripos($line,'X-OneID-Test-Runtime:')===0) $headers['runtime']=trim(substr($line,strpos($line,':')+1));
                return strlen($line);
            }]);
        $body = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $elapsedMs=curl_getinfo($curl,CURLINFO_TOTAL_TIME)*1000; curl_close($curl);
        if($benchmarkMode && $round>0) $timings[$version][]=$elapsedMs;
        if ($body === false) throw new RuntimeException('HTTP_TRANSPORT_FAILED');
        $data = json_decode($body,true,64,JSON_THROW_ON_ERROR);
        if ($status !== 200 || ($data['respond_flag']??null)!=='1' || ($data['respond']??null)!==$case['expected'] || ($case['expected']==='1' && !is_array($data['respond_user_packet']??null)) || ($case['expected']==='0' && (isset($data['respond_user_packet']) || !str_contains((string)($data['respond_description']??''),'Site not allowed to access')))) throw new RuntimeException('API_DID_NOT_ACCEPT_EXISTING_TOKEN');
        if ($version==='8.4' && ($headers['runtime']??'')!=='php84-isolated') throw new RuntimeException('MISSING_PHP84_RUNTIME_MARKER');
        $responses[$version]=$data;
    }
    $caseResults[]=['application'=>$case['name'],'expected'=>$case['expected']==='1'?'allow':'deny','response_keys_match'=>array_keys($responses['8.3'])===array_keys($responses['8.4']),'user_packet_matches'=>($responses['8.3']['respond_user_packet']??null)===($responses['8.4']['respond_user_packet']??null),'php84_marker'=>true,'expected_result_on_both'=>true];
    }
    }
    $q->execute(['hash'=>$hash]); $after=$q->fetch(PDO::FETCH_ASSOC);
    if($benchmarkMode){
        $ok=$before===$after; foreach($caseResults as $c) $ok=$ok && $c['response_keys_match'] && $c['user_packet_matches'];
        $summary=[];
        foreach($timings as $version=>$samples){
            $sorted=$samples; sort($sorted,SORT_NUMERIC);
            $summary[$version]=['samples_ms'=>array_map(static fn($v)=>round($v,3),$samples),
                'median_ms'=>round($sorted[2],3),'mean_ms'=>round(array_sum($samples)/count($samples),3),
                'min_ms'=>round(min($samples),3),'max_ms'=>round(max($samples),3)];
        }
        $delta=$summary['8.4']['median_ms']-$summary['8.3']['median_ms'];
        echo json_encode(['status'=>$ok?'MEASURED':'FAIL','functional_checks_pass'=>$ok,
            'scope'=>'Authenticated IDP token validation API over loopback TLS; not browser login, application ACL latency or load capacity',
            'method'=>'One warmup and five measured requests per runtime; alternating order; fresh cURL connection per request; TLS verified',
            'results'=>$summary,'median_delta_ms'=>round($delta,3),
            'median_delta_percent'=>$summary['8.3']['median_ms']>0?round($delta/$summary['8.3']['median_ms']*100,2):null,
            'token_record_unchanged'=>$before===$after,'performance_acceptance'=>'NOT_ASSESSED: no agreed threshold; small serial sample'])."\n";
        exit($ok?0:1);
    }
    if($applicationMode){
        $ok=$before===$after; foreach($caseResults as $c) $ok=$ok && $c['response_keys_match'] && $c['user_packet_matches'];
        echo json_encode(['status'=>$ok?'PASS':'FAIL','scope'=>'Registered application credential/ACL API parity; not remote runtime execution','cases'=>$caseResults,'denied_case_available'=>count($cases)>3,'token_record_unchanged'=>$before===$after])."\n"; exit($ok?0:1);
    }
    $checks = ['accepted_on_both'=>true,'php84_marker'=>true,'response_keys_match'=>array_keys($responses['8.3'])===array_keys($responses['8.4']),
        'user_packet_matches'=>$responses['8.3']['respond_user_packet']===$responses['8.4']['respond_user_packet'], 'token_record_unchanged'=>$before===$after];
    echo json_encode(['status'=>in_array(false,$checks,true)?'FAIL':'PASS','scope'=>'Existing active token validation using IDP API contract; not per-application ACL or remote downstream runtime','checks'=>$checks], JSON_THROW_ON_ERROR)."\n";
    exit(in_array(false,$checks,true)?1:0);
} catch (Throwable $error) {
    // Never print exception messages from database/cURL/JSON or raw API responses.
    $safe = ['APPROVED_ACCOUNT_NOT_UNIQUE','APPLICATION_CREDENTIAL_NOT_RETRIEVABLE','APPLICATION_CREDENTIAL_HASH_MISMATCH','EXPECTED_APPLICATION_ACCESS_NOT_PRESENT','APPLICATION_TARGETS_NOT_UNIQUE','UAT_ONLY','MODERN_RAW_TOKEN_REQUIRED','TOKEN_NOT_FOUND','TOKEN_NOT_FOR_APPROVED_TEST_ACCOUNT','FRESH_ACTIVE_TOKEN_WITHOUT_PENDING_REVOCATION_REQUIRED','HTTP_TRANSPORT_FAILED','API_DID_NOT_ACCEPT_EXISTING_TOKEN','MISSING_PHP84_RUNTIME_MARKER'];
    $reason = in_array($error->getMessage(),$safe,true)?$error->getMessage():'PREFLIGHT_OR_EXECUTION_FAILED';
    echo json_encode(['status'=>'STOP','reason'=>$reason])."\n"; exit(1);
}
