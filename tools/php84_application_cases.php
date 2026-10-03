<?php
declare(strict_types=1);
// Private CLI harness helper; no output of identifiers or credential material.
function php84ApplicationCases(PDO $pdo): array {
    require_once dirname(__DIR__).'/vendor/autoload.php';
    $users=$pdo->prepare('SELECT u_id,u_category FROM user_tbl WHERE TRIM(data3)=:staff AND avail_status=1');
    $users->execute(['staff'=>'0530-09']); $rows=$users->fetchAll(PDO::FETCH_ASSOC);
    if(count($rows)!==1) throw new RuntimeException('APPROVED_ACCOUNT_NOT_UNIQUE');
    $u=$rows[0];
    $acl=$pdo->prepare('SELECT S.sp_id FROM sp_list S WHERE S.avail_status=1 AND (EXISTS (SELECT 1 FROM acl_group G WHERE G.sp_id=S.sp_id AND G.uc_id=:category) OR EXISTS (SELECT 1 FROM acl_single A WHERE A.sp_id=S.sp_id AND A.u_id=:uid)) AND NOT EXISTS (SELECT 1 FROM acl_blacklist B WHERE B.sp_id=S.sp_id AND B.u_id=:blocked_uid)');
    $acl->execute(['category'=>$u['u_category'],'uid'=>$u['u_id'],'blocked_uid'=>$u['u_id']]);
    $allowed=$acl->fetchAll(PDO::FETCH_COLUMN);
    $apps=$pdo->query('SELECT S.sp_id,S.sp_name,S.sp_domain,C.sp_id AS credential_id,C.code_hash,C.code_ciphertext,C.code_nonce,C.key_version FROM sp_list S LEFT JOIN sp_api_credential C ON C.sp_id=S.sp_id WHERE S.avail_status=1 AND S.sp_sso_support=0 ORDER BY S.sp_name')->fetchAll(PDO::FETCH_ASSOC);
    $wanted=['dev-odl.upnm.edu.my'=>'ODL','sap-uat.upnm.edu.my'=>'SAP','ebdr-uat.upnm.edu.my'=>'e-BDR'];
    $cases=[];$denied=null;
    foreach($apps as $app){
        $host=parse_url($app['sp_domain'],PHP_URL_HOST);
        $permit=in_array($app['sp_id'],$allowed,true);
        if(!isset($wanted[$host]) && ($permit || $denied!==null)) continue;
        if($app['credential_id']===null) $code=$app['sp_id'];
        else {
            if(empty($app['code_ciphertext'])) {
                if(isset($wanted[$host])) throw new RuntimeException('APPLICATION_CREDENTIAL_NOT_RETRIEVABLE');
                continue;
            }
            $cipher=new \OneId\App\Admin\SiteApiCodeCipher(\OneId\App\Auth\TotpKeyring::fromFile((string)oneid_config('ONEID_TOTP_KEYRING_PATH','')));
            $code=$cipher->decrypt($app['code_ciphertext'],$app['code_nonce'],(string)$app['key_version']);
            if(!hash_equals($app['code_hash'],hash('sha256',$code))) throw new RuntimeException('APPLICATION_CREDENTIAL_HASH_MISMATCH');
        }
        if(isset($wanted[$host])){
            if(!$permit) throw new RuntimeException('EXPECTED_APPLICATION_ACCESS_NOT_PRESENT');
            $cases[]=['name'=>$wanted[$host],'site_code'=>$code,'expected'=>'1'];
        } else $denied=['name'=>'Existing denied application','site_code'=>$code,'expected'=>'0'];
    }
    if(count($cases)!==3 || count(array_unique(array_column($cases,'name')))!==3) throw new RuntimeException('APPLICATION_TARGETS_NOT_UNIQUE');
    if($denied!==null) $cases[]=$denied;
    return $cases;
}
