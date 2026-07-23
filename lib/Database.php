<?php
 
class Database {
  
    protected $pdo;
    private ?bool $userProvenanceSupported = null;
    private ?bool $userAppFavouritesSupported = null;
    private string $environment;
    public function __construct()
    {
        try
        {
            $this->pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '" . DB_CHARACSET . "';"));
            $this->pdo->exec("SET CHARACTER SET " . DB_CHARACSET);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->query("set names " . DB_CHARACSET);
            $environment=strtolower(trim((string)oneid_config('ONEID_ENVIRONMENT','')));
            if(preg_match('/^[a-z][a-z0-9_-]{1,31}$/',$environment)!==1){
                throw new RuntimeException('ONEID_ENVIRONMENT is not configured safely.');
            }
            $this->environment=$environment;
        } catch (PDOException $e)
        {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed', 0, $e);
        }
     }


    private function authenticateByField($field,$username,$password){
        $allowedFields = ['u_id', 'data2', 'data3', 'data8'];
        if (!in_array($field, $allowedFields, true)) {
            return false;
        }
        $Q = "SELECT * FROM user_tbl WHERE {$field} = :username LIMIT 1";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':username', $username);
        $R->execute();
        $result = $R->fetch(PDO::FETCH_ASSOC);
        if (!$result || !oneid_password_verify($password, (string) $result['u_password'])) {
            return false;
        }

        $defaultPassword = trim((string) $result['data3']) !== ''
            ? (string) $result['data4']
            : (string) $result['data2'];
        $changeRequired = (int) ($result['password_change_required'] ?? 0);
        if ($defaultPassword !== '' && hash_equals($defaultPassword, $password)) {
            $changeRequired = 1;
        }

        if (oneid_password_needs_rehash((string) $result['u_password'])) {
            $modernHash = oneid_password_hash($password);
            $this->updatePasswordHash($result['u_id'], $modernHash, $changeRequired);
            $result['u_password'] = $modernHash;
        } elseif ($changeRequired !== (int) ($result['password_change_required'] ?? 0)) {
            $this->setPasswordChangeRequired($result['u_id'], $changeRequired);
        }
        $result['password_change_required'] = $changeRequired;
        return $result;
    }

    // Backward-compatible method names; password argument is now plaintext and
    // verification happens in PHP to support both MD5 and password_hash().
    public function func_authenticate($username,$password){
        return $this->authenticateByField('u_id', $username, $password);
    }
    public function func_authenticate2($username,$password){
        return $this->authenticateByField('data2', $username, $password);
    }
    public function func_authenticate3($username,$password){
        return $this->authenticateByField('data3', $username, $password);
    }
    public function func_authenticate4($username,$password){
        return $this->authenticateByField('data8', $username, $password);
    }

    public function verify_user_password($userId,$password){
        return $this->authenticateByField('u_id', $userId, $password) !== false;
    }

    private function updatePasswordHash($userId,$hash,$changeRequired){
        $Q = "UPDATE user_tbl SET u_password=:password, password_change_required=:required WHERE u_id=:user_id";
        $R = $this->pdo->prepare($Q);
        $R->execute([':password'=>$hash, ':required'=>$changeRequired, ':user_id'=>$userId]);
        return $R->rowCount();
    }

    public function set_user_password($userId,$password,$changeRequired=0){
        return $this->updatePasswordHash($userId, oneid_password_hash($password), (int) $changeRequired);
    }

    public function setPasswordChangeRequired($userId,$required){
        $Q = "UPDATE user_tbl SET password_change_required=:required WHERE u_id=:user_id";
        $R = $this->pdo->prepare($Q);
        $R->execute([':required'=>(int)$required, ':user_id'=>$userId]);
        return $R->rowCount();
    }

    public function get_user_password_change_for_update($userId){
        $Q="SELECT u_id,u_password,password_change_required,avail_status FROM user_tbl WHERE u_id=:user_id LIMIT 1 FOR UPDATE";
        $R=$this->pdo->prepare($Q);$R->execute([':user_id'=>$userId]);return $R->fetch(PDO::FETCH_ASSOC);
    }

    public function get_password_change_requirement($userId){
        $R=$this->pdo->prepare("SELECT password_change_required,avail_status FROM user_tbl WHERE u_id=:user_id LIMIT 1");$R->execute([':user_id'=>$userId]);return $R->fetch(PDO::FETCH_ASSOC);
    }

    public function count_recent_invalid_current_password_attempts($userId,$ipAddress,$minutes=15){
        $since=date('Y-m-d H:i:s',time()-((int)$minutes*60));$pattern='user='.$userId.' outcome=rejected reason=UC2_CURRENT_PASSWORD_INVALID%';
        $R=$this->pdo->prepare("SELECT COUNT(*) FROM syslog WHERE log_type=20 AND ip_addr=:ip AND datetime>=:since AND log_detail LIKE :pattern");$R->execute([':ip'=>$ipAddress,':since'=>$since,':pattern'=>$pattern]);return(int)$R->fetchColumn();
    }

    public function get_password_history_hashes($userId,$limit=5){
        $limit=max(1,min(10,(int)$limit));$R=$this->pdo->prepare("SELECT password_hash FROM user_password_history WHERE user_id=:user_id ORDER BY id DESC LIMIT {$limit}");$R->execute([':user_id'=>$userId]);return $R->fetchAll(PDO::FETCH_COLUMN,0);
    }
    public function record_password_history($userId,$hash){
        $R=$this->pdo->prepare("INSERT INTO user_password_history(user_id,password_hash,changed_at) VALUES(:user_id,:hash,NOW())");$R->execute([':user_id'=>$userId,':hash'=>$hash]);return $R->rowCount();
    }
    public function prune_password_history($userId,$keep=5){
        $keep=max(1,min(10,(int)$keep));$Q="DELETE FROM user_password_history WHERE user_id=:user_id AND id NOT IN (SELECT id FROM (SELECT id FROM user_password_history WHERE user_id=:inner_user ORDER BY id DESC LIMIT {$keep}) retained)";$R=$this->pdo->prepare($Q);$R->execute([':user_id'=>$userId,':inner_user'=>$userId]);return $R->rowCount();
    }


    public function func_search_uid($u_id){
        $Q = "SELECT * FROM user_tbl WHERE u_id = :u_id";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':u_id', $u_id);
        $R->execute();
        $result = $R->fetch(PDO::FETCH_ASSOC);
        return $result;
    }
	
	public function func_search_uid_pelajar($u_id){
        $Q = "SELECT * FROM user_tbl WHERE data2 = :u_id AND avail_status=1";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':u_id', $u_id);
        $R->execute();
        $result = $R->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

   

    public function get_system_config(){
        $Q = "SELECT id, configuration_version, token_timeout, multi_session, password_reset_email_enabled, admin_2fa_enabled, admin_step_up_lifetime_minutes FROM sys_config WHERE singleton_key = 1";
        $R = $this->pdo->prepare($Q);      
        $R->execute();
        $result = $R->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    public function get_system_config_for_update(){
        $Q = "SELECT id, configuration_version, token_timeout, multi_session, password_reset_email_enabled, admin_2fa_enabled, admin_step_up_lifetime_minutes FROM sys_config WHERE singleton_key = 1 FOR UPDATE";
        $R = $this->pdo->prepare($Q);
        $R->execute();
        return $R->fetch(PDO::FETCH_ASSOC);
    }

    public function update_configuration_by_id($configId,$token_timeout,$multi_session,$expectedVersion){
        $Q = "UPDATE sys_config SET token_timeout=:token_timeout,multi_session=:multi_session,configuration_version=configuration_version+1 WHERE id=:config_id AND singleton_key=1 AND configuration_version=:expected_version";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':config_id', $configId, PDO::PARAM_INT);
        $R->bindParam(':token_timeout', $token_timeout);
        $R->bindParam(':multi_session', $multi_session, PDO::PARAM_INT);
        $R->bindParam(':expected_version', $expectedVersion, PDO::PARAM_INT);
        $R->execute();
        return $R->rowCount();
    }

    public function configuration_history_record(array $entry){
        $Q="INSERT INTO configuration_change_history(configuration_version_before,configuration_version_after,actor_id,ip_address,action_name,outcome,reason_code,change_reason,before_json,after_json,correlation_id,created_at) VALUES(:version_before,:version_after,:actor_id,:ip_address,:action_name,:outcome,:reason_code,:change_reason,:before_json,:after_json,:correlation_id,NOW())";
        $R=$this->pdo->prepare($Q);$R->execute([
            ':version_before'=>$entry['version_before']??null,':version_after'=>$entry['version_after']??null,
            ':actor_id'=>$entry['actor_id'],':ip_address'=>$entry['ip_address'],':action_name'=>$entry['action_name'],
            ':outcome'=>$entry['outcome'],':reason_code'=>$entry['reason_code'],':change_reason'=>$entry['change_reason']??null,
            ':before_json'=>isset($entry['before'])?json_encode($entry['before'],JSON_THROW_ON_ERROR):null,
            ':after_json'=>isset($entry['after'])?json_encode($entry['after'],JSON_THROW_ON_ERROR):null,
            ':correlation_id'=>$entry['correlation_id'],
        ]);return $R->rowCount();
    }

    public function configuration_history_latest_success(){
        $R=$this->pdo->query("SELECT actor_id,created_at,configuration_version_after FROM configuration_change_history WHERE outcome='SUCCESS' ORDER BY history_id DESC LIMIT 1");
        return $R->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public function configuration_history_list($page,$pageSize){
        $page=max(1,(int)$page);$pageSize=in_array((int)$pageSize,[10,25,50],true)?(int)$pageSize:10;$offset=($page-1)*$pageSize;
        $total=(int)$this->pdo->query('SELECT COUNT(*) FROM configuration_change_history')->fetchColumn();
        $Q="SELECT history_id,configuration_version_before,configuration_version_after,actor_id,action_name,outcome,reason_code,change_reason,before_json,after_json,correlation_id,created_at FROM configuration_change_history ORDER BY history_id DESC LIMIT {$pageSize} OFFSET {$offset}";
        return ['rows'=>$this->pdo->query($Q)->fetchAll(PDO::FETCH_ASSOC),'total'=>$total];
    }

    public function update_password_recovery_by_id($configId,$enabled){
        $Q = "UPDATE sys_config SET password_reset_email_enabled=:enabled WHERE id=:config_id AND singleton_key=1";
        $R=$this->pdo->prepare($Q);$R->execute([':config_id'=>$configId,':enabled'=>$enabled]);
        return $R->rowCount();
    }

    public function update_admin_step_up_lifetime_by_version($configId,$minutes,$expectedVersion){
        $minutes=(int)$minutes;
        if(!in_array($minutes,[5,10,15,30],true)){throw new InvalidArgumentException('STEP_UP_LIFETIME_INVALID');}
        $Q="UPDATE sys_config SET admin_step_up_lifetime_minutes=:minutes,configuration_version=configuration_version+1 WHERE id=:config_id AND singleton_key=1 AND configuration_version=:expected_version";
        $R=$this->pdo->prepare($Q);$R->execute([':minutes'=>$minutes,':config_id'=>(int)$configId,':expected_version'=>(int)$expectedVersion]);return $R->rowCount();
    }

    public function preview_policy_revocation($newTimeoutHours,$timeoutReduced,$disableMultiple){
        $timeoutSeconds = (int) round((float) $newTimeoutHours * 3600);
        $timeoutSql = $timeoutReduced
            ? "status=1 AND TIMESTAMPDIFF(SECOND,token_issued_at,NOW()) > :timeout_seconds"
            : "0=1";
        $multiSql = $disableMultiple
            ? "token_id IN (SELECT token_id FROM (SELECT token_id,ROW_NUMBER() OVER(PARTITION BY user_id ORDER BY token_issued_at DESC,token_id DESC) rn FROM token_tbl WHERE status=1) ranked WHERE rn>1)"
            : "0=1";
        $Q = "SELECT COUNT(*) affected_tokens,COUNT(DISTINCT user_id) affected_users,
                     SUM(timeout_hit) timeout_tokens,SUM(multiple_hit) multiple_tokens
              FROM (SELECT user_id,CASE WHEN {$timeoutSql} THEN 1 ELSE 0 END timeout_hit,
                           CASE WHEN {$multiSql} THEN 1 ELSE 0 END multiple_hit
                    FROM token_tbl WHERE status=1) impact
              WHERE timeout_hit=1 OR multiple_hit=1";
        $R = $this->pdo->prepare($Q);
        if ($timeoutReduced) {
            $R->bindValue(':timeout_seconds', $timeoutSeconds, PDO::PARAM_INT);
        }
        $R->execute();
        $row = $R->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'affected_tokens'=>(int)($row['affected_tokens']??0),
            'affected_users'=>(int)($row['affected_users']??0),
            'timeout_tokens'=>(int)($row['timeout_tokens']??0),
            'multiple_tokens'=>(int)($row['multiple_tokens']??0),
        ];
    }

    public function schedule_policy_revocation($newTimeoutHours,$timeoutReduced,$disableMultiple,$revokeAt,$correlationId){
        $timeoutSeconds = (int) round((float)$newTimeoutHours*3600);
        $timeoutSql = $timeoutReduced ? "TIMESTAMPDIFF(SECOND,t.token_issued_at,NOW()) > :timeout_seconds" : "0=1";
        $multiSql = $disableMultiple ? "t.token_id IN (SELECT token_id FROM (SELECT token_id,ROW_NUMBER() OVER(PARTITION BY user_id ORDER BY token_issued_at DESC,token_id DESC) rn FROM token_tbl WHERE status=1) ranked WHERE rn>1)" : "0=1";
        $Q="UPDATE token_tbl t SET t.policy_revoke_at=:revoke_at,t.policy_revoke_correlation=:correlation WHERE t.status=1 AND (({$timeoutSql}) OR ({$multiSql}))";
        $R=$this->pdo->prepare($Q);
        $R->bindValue(':revoke_at',$revokeAt);$R->bindValue(':correlation',$correlationId);
        if($timeoutReduced)$R->bindValue(':timeout_seconds',$timeoutSeconds,PDO::PARAM_INT);
        $R->execute();return $R->rowCount();
    }

    public function enforce_due_token_revocation($tokenId){
        $Q="UPDATE token_tbl SET status=0 WHERE token_id=:token_id AND status=1 AND policy_revoke_at IS NOT NULL AND policy_revoke_at<=NOW()";
        $R=$this->pdo->prepare($Q);$R->execute([':token_id'=>$tokenId]);return $R->rowCount();
    }


//Dashboard

    
   public function action_add_new_user($u_id,$u_category,$u_password,$data1,$data2,$data3,$data4,$data5,$data6,$data7,$data8,$data9,$data10,$data11,$data12,$u_changes_hash,$account_source='manual',$sync_protected=1){
        if ($this->supportsUserProvenance()) {
            $Q = "INSERT INTO user_tbl(u_id,u_category,u_password,password_change_required,u_type,avail_status,account_source,sync_protected,data1,data2,data3,data4,data5,data6,data7,data8,data9,data10,data11,data12,u_update_datetime,u_changes_hash) VALUES (:u_id,:u_category,:u_password,1,0,1,:account_source,:sync_protected,:data1,:data2,:data3,:data4,:data5,:data6,:data7,:data8,:data9,:data10,:data11,:data12,NOW(),:u_changes_hash)";
        } else {
            $Q = "INSERT INTO user_tbl(u_id,u_category,u_password,password_change_required,u_type,avail_status,data1,data2,data3,data4,data5,data6,data7,data8,data9,data10,data11,data12,u_update_datetime,u_changes_hash) VALUES (:u_id,:u_category,:u_password,1,0,1,:data1,:data2,:data3,:data4,:data5,:data6,:data7,:data8,:data9,:data10,:data11,:data12,NOW(),:u_changes_hash)";
        }
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':u_id', $u_id);
        $R->bindParam(':u_category', $u_category);
        $R->bindParam(':u_password', $u_password);
        $R->bindParam(':data1', $data1);
        $R->bindParam(':data2', $data2);
        $R->bindParam(':data3', $data3);
        $R->bindParam(':data4', $data4);
        $R->bindParam(':data5', $data5);
        $R->bindParam(':data6', $data6);
        $R->bindParam(':data7', $data7);
        $R->bindParam(':data8', $data8);
        $R->bindParam(':data9', $data9);
        $R->bindParam(':data10', $data10);
        $R->bindParam(':data11', $data11);
        $R->bindParam(':data12', $data12);
        $R->bindParam(':u_changes_hash', $u_changes_hash);
        if ($this->supportsUserProvenance()) {
            $R->bindParam(':account_source', $account_source);
            $R->bindParam(':sync_protected', $sync_protected, PDO::PARAM_INT);
        }
        $R->execute();        
        $result = $R->rowCount();
        return $result;
    }
    
   public function action_add_new_user_from_external_source($u_id,$u_category,$u_password,$data1,$data2,$data3,$data4,$data5,$data6,$data7,$data8,$data9,$data10,$data11,$data12,$u_changes_hash){
        if ($this->supportsUserProvenance()) {
            $protectedCheck = $this->pdo->prepare(
                "SELECT 1 FROM user_tbl
                 WHERE u_id=:u_id AND account_source='manual' AND sync_protected=1
                 LIMIT 1 FOR UPDATE"
            );
            $protectedCheck->execute([':u_id'=>$u_id]);
            if ($protectedCheck->fetchColumn() !== false) {
                return 0;
            }
            $Q = "INSERT INTO user_tbl(u_id,u_category,u_password,password_change_required,u_type,avail_status,account_source,sync_protected,data1,data2,data3,data4,data5,data6,data7,data8,data9,data10,data11,data12,u_update_datetime,u_changes_hash) VALUES (:u_id,:u_category,:u_password,1,0,1,'external',0,:data1,:data2,:data3,:data4,:data5,:data6,:data7,:data8,:data9,:data10,:data11,:data12,NOW(),:u_changes_hash)
                ON DUPLICATE KEY UPDATE u_category=:u_category,avail_status=1,account_source='external',sync_protected=0,data1=:data1,data2=:data2,data3=:data3,data4=:data4,data5=:data5,data6=:data6,data7=:data7,data8=:data8,data9=:data9,data10=:data10,data11=:data11,data12=:data12,u_update_datetime=NOW(),u_changes_hash=:u_changes_hash;";
        } else {
            $Q = "INSERT INTO user_tbl(u_id,u_category,u_password,password_change_required,u_type,avail_status,data1,data2,data3,data4,data5,data6,data7,data8,data9,data10,data11,data12,u_update_datetime,u_changes_hash) VALUES (:u_id,:u_category,:u_password,1,0,1,:data1,:data2,:data3,:data4,:data5,:data6,:data7,:data8,:data9,:data10,:data11,:data12,NOW(),:u_changes_hash)
                ON DUPLICATE KEY UPDATE u_category=:u_category,avail_status=1,data1=:data1,data2=:data2,data3=:data3,data4=:data4,data5=:data5,data6=:data6,data7=:data7,data8=:data8,data9=:data9,data10=:data10,data11=:data11,data12=:data12,u_update_datetime=NOW(),u_changes_hash=:u_changes_hash;";
        }
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':u_id', $u_id);
        $R->bindParam(':u_category', $u_category);
        $R->bindParam(':u_password', $u_password);
        $R->bindParam(':data1', $data1);
        $R->bindParam(':data2', $data2);
        $R->bindParam(':data3', $data3);
        $R->bindParam(':data4', $data4);
        $R->bindParam(':data5', $data5);
        $R->bindParam(':data6', $data6);
        $R->bindParam(':data7', $data7);
        $R->bindParam(':data8', $data8);
        $R->bindParam(':data9', $data9);
        $R->bindParam(':data10', $data10);
        $R->bindParam(':data11', $data11);
        $R->bindParam(':data12', $data12);
        $R->bindParam(':u_changes_hash', $u_changes_hash);
        $R->execute();        
        $result = $R->rowCount();
        return $result;
    }

   public function action_add_new_ext_header($ext_head_type){
        $Q = "INSERT INTO  ext_data_temp_header(ext_head_type,ext_head_dt_start,ext_head_dt_end,ext_head_status,ext_head_initial_sourcedata,ext_head_uploaded_data) VALUES (:ext_head_type,NOW(),NOW(),0,0,0)";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':ext_head_type', $ext_head_type);
        $R->execute();        
        $result = $this->pdo->lastInsertId();
        return $result;
    }



    public function admin_search_keyword_user_func($keyword_search){
        $Q = "SELECT data1,data2,data3,data4,data5,data6,data7 FROM user_tbl WHERE data1 LIKE CONCAT('%', :keyword_search, '%')
                UNION
                SELECT data1,data2,data3,data4,data5,data6,data7 FROM user_tbl WHERE data2 LIKE CONCAT('%', :keyword_search, '%')
                UNION
                SELECT data1,data2,data3,data4,data5,data6,data7 FROM user_tbl WHERE data3 LIKE CONCAT('%', :keyword_search, '%')
                UNION
                SELECT data1,data2,data3,data4,data5,data6,data7 FROM user_tbl WHERE data4 LIKE CONCAT('%', :keyword_search, '%')
                ";
        $R = $this->pdo->prepare($Q);        
        $R->bindParam(':keyword_search', $keyword_search);  
        $R->execute();
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function action_get_ext_header($header_id){
        $Q = "SELECT * FROM ext_data_temp_header WHERE ext_head_id=:header_id";
        $R = $this->pdo->prepare($Q);        
        $R->bindParam(':header_id', $header_id);  
        $R->execute();
        $result = $R->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    public function sync_latest_completed_source_rows(): ?int{
        $Q = "SELECT ext_head_initial_sourcedata
              FROM ext_data_temp_header
              WHERE ext_head_status IN (2, 4)
                AND ext_head_initial_sourcedata > 0
              ORDER BY ext_head_id DESC
              LIMIT 1";
        $R = $this->pdo->prepare($Q);
        $R->execute();
        $value = $R->fetchColumn();
        return $value === false ? null : (int) $value;
    }



    public function admin_update_ext_header_status($ext_head_id,$status,$data_header,$data_count){
            $Q = "UPDATE ext_data_temp_header SET ext_head_dt_end = NOW(),ext_head_status=:status,$data_header=:data_count WHERE ext_head_id = :ext_head_id";
            $R = $this->pdo->prepare($Q);
        $R->bindParam(':ext_head_id', $ext_head_id);
        $R->bindParam(':status', $status);
        $R->bindParam(':data_count', $data_count);
        $R->execute();
        $result = $R->rowCount();
        return $result;
    }

    public function admin_update_specific_user_info_all_data($user_id,$data1,$data2,$data3,$data4,$data5,$data6,$data7,$data8,$data9,$data10,$data11,$data12,$u_changes_hash){
            $Q = "UPDATE user_tbl SET data1=:data1,data2=:data2,data3=:data3,data4=:data4,data5=:data5,data6=:data6,data7=:data7,data8=:data8,data9=:data9,data10=:data10,data11=:data11,data12=:data12,u_update_datetime=NOW(),u_changes_hash=:u_changes_hash WHERE u_id = :user_id";
            $R = $this->pdo->prepare($Q);
        $R->bindParam(':user_id', $user_id);
        $R->bindParam(':data1', $data1);
        $R->bindParam(':data2', $data2);
        $R->bindParam(':data3', $data3);
        $R->bindParam(':data4', $data4);
        $R->bindParam(':data5', $data5);
        $R->bindParam(':data6', $data6);
        $R->bindParam(':data7', $data7);
        $R->bindParam(':data8', $data8);
        $R->bindParam(':data9', $data9);
        $R->bindParam(':data10', $data10);
        $R->bindParam(':data11', $data11);
        $R->bindParam(':data12', $data12);
        $R->bindParam(':u_changes_hash', $u_changes_hash);
        $R->execute();
        $result = $R->rowCount();
        return $result;
    }



   public function action_add_external_temp_body($ext_head_id,$ext_data1,$ext_data2,$ext_data3,$ext_data4,$ext_data5,$ext_data6,$ext_data7,$ext_data8,$ext_data9,$ext_data10,$ext_data11,$ext_data12){
        $Q = "INSERT INTO  ext_data_temp_body(ext_head_id,ext_data1,ext_data2,ext_data3,ext_data4,ext_data5,ext_data6,ext_data7,ext_data8,ext_data9,ext_data10,ext_data11,ext_data12,ext_body_status) VALUES (:ext_head_id,:ext_data1,:ext_data2,:ext_data3,:ext_data4,:ext_data5,:ext_data6,:ext_data7,:ext_data8,:ext_data9,:ext_data10,:ext_data11,:ext_data12,1)";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':ext_head_id', $ext_head_id);
        $R->bindParam(':ext_data1', $ext_data1);
        $R->bindParam(':ext_data2', $ext_data2);
        $R->bindParam(':ext_data3', $ext_data3);
        $R->bindParam(':ext_data4', $ext_data4);
        $R->bindParam(':ext_data5', $ext_data5);
        $R->bindParam(':ext_data6', $ext_data6);
        $R->bindParam(':ext_data7', $ext_data7);
        $R->bindParam(':ext_data8', $ext_data8);
        $R->bindParam(':ext_data9', $ext_data9);
        $R->bindParam(':ext_data10', $ext_data10);
        $R->bindParam(':ext_data11', $ext_data11);
        $R->bindParam(':ext_data12', $ext_data12);
        $R->execute();        
        $result = $this->pdo->lastInsertId();
        return $result;
    }


    public function admin_update_ext_body_status($ext_head_id,$ext_body_id,$status){
            $Q = "UPDATE ext_data_temp_body SET ext_body_status=:status WHERE ext_body_id = :ext_body_id AND ext_head_id=:ext_head_id";
            $R = $this->pdo->prepare($Q);
        $R->bindParam(':ext_head_id', $ext_head_id);
        $R->bindParam(':ext_body_id', $ext_body_id);
        $R->bindParam(':status', $status);
        $R->execute();
        $result = $R->rowCount();
        return $result;
    }

    public function sync_get_all_sso_user(){
        $provenanceFields = $this->supportsUserProvenance()
            ? ", account_source, sync_protected"
            : ", 'legacy' AS account_source, 0 AS sync_protected";
        $Q = "SELECT u_id, u_category, avail_status,
                     data1, data2, data3, data4, data5, data6,
                     data7, data8, data9, data10, data11, data12,
                     u_changes_hash, '1' AS source" . $provenanceFields . "
              FROM user_tbl
              WHERE avail_status = 1";
        $R = $this->pdo->prepare($Q);
        $R->execute();
        return $R->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sync_get_inactive_user_ids(){
        $Q = "SELECT u_id FROM user_tbl WHERE avail_status = 0";
        $R = $this->pdo->prepare($Q);
        $R->execute();
        $rows = $R->fetchAll(PDO::FETCH_COLUMN, 0);
        return $rows ? $rows : [];
    }

    public function sync_get_active_user_ids_by_source(string $sourceCode){
        if (!preg_match('/^[A-Z0-9_]{1,64}$/', $sourceCode)) {
            throw new InvalidArgumentException('Invalid sync source code');
        }
        $Q = "SELECT u_id
              FROM user_external_identity
              WHERE source_code = :source_code AND source_active = 1";
        $R = $this->pdo->prepare($Q);
        $R->execute([':source_code' => $sourceCode]);
        $rows = $R->fetchAll(PDO::FETCH_COLUMN, 0);
        return $rows ? $rows : [];
    }

    public function sync_get_inactive_user_ids_by_source(string $sourceCode){
        if (!preg_match('/^[A-Z0-9_]{1,64}$/', $sourceCode)) {
            throw new InvalidArgumentException('Invalid sync source code');
        }
        $Q = "SELECT i.u_id FROM user_external_identity i
              JOIN user_tbl u ON u.u_id=i.u_id
              WHERE i.source_code=:source_code AND u.avail_status=0";
        $R = $this->pdo->prepare($Q);
        $R->execute([':source_code'=>$sourceCode]);
        return $R->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
    }

    public function sync_assert_source_identity_writable(string $userId,string $sourceCode){
        $Q = "SELECT u.u_id,
                     SUM(i.source_code=:source_code) AS same_source
              FROM user_tbl u
              LEFT JOIN user_external_identity i ON i.u_id=u.u_id
              WHERE u.u_id=:u_id GROUP BY u.u_id FOR UPDATE";
        $R = $this->pdo->prepare($Q);
        $R->execute([':u_id'=>$userId,':source_code'=>$sourceCode]);
        $row=$R->fetch(PDO::FETCH_ASSOC);
        if($row!==false&&(int)$row['same_source']<1){
            throw new RuntimeException('SYNC_CROSS_SOURCE_IDENTITY_COLLISION');
        }
        $Q = "SELECT u_id FROM user_external_identity
              WHERE source_code=:source_code AND external_user_id=:external_user_id
              FOR UPDATE";
        $R = $this->pdo->prepare($Q);
        $R->execute([
            ':source_code'=>$sourceCode,
            ':external_user_id'=>$userId,
        ]);
        $owner=$R->fetchColumn();
        if($owner!==false&&!hash_equals((string)$owner,$userId)){
            throw new RuntimeException('SYNC_SOURCE_MEMBERSHIP_CONFLICT');
        }
    }

    public function sync_upsert_source_membership(
        string $userId,string $sourceCode,string $externalUserId,string $sourceHash
    ){
        $Q = "INSERT INTO user_external_identity(
                  u_id,source_code,external_user_id,source_active,source_hash,
                  first_seen_at,last_seen_at,last_sync_at
              ) VALUES(
                  :u_id,:source_code,:external_user_id,1,:source_hash,
                  NOW(),NOW(),NOW()
              )
              ON DUPLICATE KEY UPDATE
                  source_active=1,source_hash=:source_hash,
                  last_seen_at=NOW(),last_sync_at=NOW()";
        $R=$this->pdo->prepare($Q);
        $R->execute([
            ':u_id'=>$userId,':source_code'=>$sourceCode,
            ':external_user_id'=>$externalUserId,':source_hash'=>$sourceHash,
        ]);
    }

    public function sync_deactivate_source_membership(string $userId,string $sourceCode){
        $Q="UPDATE user_external_identity SET source_active=0,last_sync_at=NOW()
            WHERE u_id=:u_id AND source_code=:source_code AND source_active=1";
        $R=$this->pdo->prepare($Q);
        $R->execute([':u_id'=>$userId,':source_code'=>$sourceCode]);
        if($R->rowCount()!==1)throw new RuntimeException('SYNC_SOURCE_MEMBERSHIP_DEACTIVATE_MISMATCH');
    }

    public function sync_has_other_active_source(string $userId,string $sourceCode):bool{
        $Q="SELECT COUNT(*) FROM user_external_identity
            WHERE u_id=:u_id AND source_code<>:source_code AND source_active=1";
        $R=$this->pdo->prepare($Q);
        $R->execute([':u_id'=>$userId,':source_code'=>$sourceCode]);
        return (int)$R->fetchColumn()>0;
    }

    public function beginTransaction(){
        return $this->pdo->beginTransaction();
    }

    public function commit(){
        return $this->pdo->commit();
    }

    public function rollback(){
        return $this->pdo->rollBack();
    }

    /**
     * Acquire a connection-scoped MySQL advisory lock for a sync run.
     * Dormant until the S4 feature-flagged orchestrator wiring is enabled.
     */
    public function sync_acquire_lock(string $lockName, int $waitSeconds = 0): bool{
        if (!preg_match('/^[A-Za-z0-9:_-]{1,64}$/', $lockName)) {
            throw new InvalidArgumentException('Invalid sync lock name');
        }

        $Q = "SELECT GET_LOCK(:lock_name, :wait_seconds)";
        $R = $this->pdo->prepare($Q);
        $R->bindValue(':lock_name', $lockName, PDO::PARAM_STR);
        $R->bindValue(':wait_seconds', max(0, $waitSeconds), PDO::PARAM_INT);
        $R->execute();
        return (int) $R->fetchColumn() === 1;
    }

    /** Release a connection-scoped advisory lock held by this connection. */
    public function sync_release_lock(string $lockName): void{
        if (!preg_match('/^[A-Za-z0-9:_-]{1,64}$/', $lockName)) {
            throw new InvalidArgumentException('Invalid sync lock name');
        }

        $Q = "SELECT RELEASE_LOCK(:lock_name)";
        $R = $this->pdo->prepare($Q);
        $R->bindValue(':lock_name', $lockName, PDO::PARAM_STR);
        $R->execute();
    }

    /**
     * Read the durable change-log totals used by the S3 reconciliation gate.
     *
     * @return array{New:int,Update:int,Deactivate:int,Reactivate:int}
     */
    public function sync_reconciliation_counts(int $headerId): array{
        $counts = [
            'New' => 0,
            'Update' => 0,
            'Deactivate' => 0,
            'Reactivate' => 0,
        ];
        $Q = "SELECT action, COUNT(*) AS total
              FROM sync_change_log
              WHERE ext_head_id = :ext_head_id
              GROUP BY action";
        $R = $this->pdo->prepare($Q);
        $R->bindValue(':ext_head_id', $headerId, PDO::PARAM_INT);
        $R->execute();

        $mapping = [
            'NEW' => 'New',
            'UPDATE' => 'Update',
            'DEACTIVATE' => 'Deactivate',
            'REACTIVATE' => 'Reactivate',
        ];
        foreach ($R->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = $mapping[strtoupper((string) ($row['action'] ?? ''))] ?? null;
            if ($key !== null) {
                $counts[$key] = (int) ($row['total'] ?? 0);
            }
        }
        return $counts;
    }

    public function supportsUserProvenance(): bool{
        if ($this->userProvenanceSupported !== null) {
            return $this->userProvenanceSupported;
        }

        $Q = "SELECT COUNT(*)
              FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'user_tbl'
                AND COLUMN_NAME IN ('account_source', 'sync_protected')";
        $R = $this->pdo->prepare($Q);
        $R->execute();
        $this->userProvenanceSupported = (int) $R->fetchColumn() === 2;
        return $this->userProvenanceSupported;
    }

    public function supportsUserAppFavourites(): bool{
        if ($this->userAppFavouritesSupported !== null) {
            return $this->userAppFavouritesSupported;
        }

        $Q = "SELECT COUNT(*)
              FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'user_app_favourite'";
        $R = $this->pdo->prepare($Q);
        $R->execute();
        $this->userAppFavouritesSupported = (int) $R->fetchColumn() === 1;
        return $this->userAppFavouritesSupported;
    }

    /** @return string[] */
    public function getUserAppFavouriteIds(string $userId): array{
        if (!$this->supportsUserAppFavourites()) {
            return [];
        }

        $Q = "SELECT sp_id FROM user_app_favourite WHERE u_id=:u_id ORDER BY created_at ASC";
        $R = $this->pdo->prepare($Q);
        $R->execute([':u_id' => $userId]);
        $rows = $R->fetchAll(PDO::FETCH_COLUMN, 0);
        return $rows ? array_map('strval', $rows) : [];
    }

    /**
     * Favourite never grants access. This mirrors the effective ACL rule used
     * by the dashboard: category or direct allow, minus an explicit deny.
     */
    public function userHasEffectiveAppAccess(string $userId, string $spId): bool{
        $Q = "SELECT 1
              FROM user_tbl u
              INNER JOIN sp_list sp ON sp.sp_id=:sp_id AND sp.avail_status=1
              WHERE u.u_id=:u_id
                AND u.avail_status=1
                AND NOT EXISTS (
                    SELECT 1 FROM acl_blacklist b
                    WHERE b.u_id=u.u_id AND b.sp_id=sp.sp_id
                )
                AND (
                    EXISTS (
                        SELECT 1 FROM acl_group g
                        WHERE g.uc_id=u.u_category AND g.sp_id=sp.sp_id
                    )
                    OR EXISTS (
                        SELECT 1 FROM acl_single s
                        WHERE s.u_id=u.u_id AND s.sp_id=sp.sp_id
                    )
                )
              LIMIT 1";
        $R = $this->pdo->prepare($Q);
        $R->execute([':u_id' => $userId, ':sp_id' => $spId]);
        return $R->fetchColumn() !== false;
    }

    public function hasUserAppFavourite(string $userId, string $spId): bool{
        if (!$this->supportsUserAppFavourites()) {
            return false;
        }

        $Q = "SELECT 1 FROM user_app_favourite
              WHERE u_id=:u_id AND sp_id=:sp_id LIMIT 1";
        $R = $this->pdo->prepare($Q);
        $R->execute([':u_id' => $userId, ':sp_id' => $spId]);
        return $R->fetchColumn() !== false;
    }

    public function setUserAppFavourite(string $userId, string $spId, bool $enabled): void{
        if (!$this->supportsUserAppFavourites()) {
            throw new RuntimeException('User app favourites storage is unavailable.');
        }

        if ($enabled) {
            $Q = "INSERT INTO user_app_favourite (u_id,sp_id,created_at,updated_at)
                  VALUES (:u_id,:sp_id,NOW(),NOW())
                  ON DUPLICATE KEY UPDATE updated_at=NOW()";
        } else {
            $Q = "DELETE FROM user_app_favourite WHERE u_id=:u_id AND sp_id=:sp_id";
        }
        $R = $this->pdo->prepare($Q);
        $R->execute([':u_id' => $userId, ':sp_id' => $spId]);
    }

    public function isActiveUserCategory($categoryId): bool{
        $Q = "SELECT 1 FROM user_category WHERE uc_id=:category_id AND avail_status=1 LIMIT 1";
        $R = $this->pdo->prepare($Q);
        $R->execute([':category_id'=>(int)$categoryId]);
        return $R->fetchColumn() !== false;
    }

    public function sync_log_change($ext_head_id, $u_id, $action, $old_data, $new_data, $changed_fields){
        $old_json = $old_data !== null ? json_encode($old_data, JSON_UNESCAPED_UNICODE) : null;
        $new_json = $new_data !== null ? json_encode($new_data, JSON_UNESCAPED_UNICODE) : null;
        $Q = "INSERT INTO sync_change_log (ext_head_id, u_id, action, old_data, new_data, changed_fields, logged_at)
              VALUES (:ext_head_id, :u_id, :action, :old_data, :new_data, :changed_fields, NOW())";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':ext_head_id', $ext_head_id);
        $R->bindParam(':u_id', $u_id);
        $R->bindParam(':action', $action);
        $R->bindParam(':old_data', $old_json);
        $R->bindParam(':new_data', $new_json);
        $R->bindParam(':changed_fields', $changed_fields);
        $R->execute();
        return $R->rowCount();
    }

    public function sync_log_change_batch(array $rows){
        if(empty($rows)){
            return 0;
        }
        $placeholders = [];
        $params = [];
        foreach ($rows as $r) {
            $placeholders[] = '(?,?,?,?,?,?,NOW())';
            $params[] = $r['ext_head_id'];
            $params[] = $r['u_id'];
            $params[] = $r['action'];
            $params[] = $r['old_data'] !== null ? json_encode($r['old_data'], JSON_UNESCAPED_UNICODE) : null;
            $params[] = $r['new_data'] !== null ? json_encode($r['new_data'], JSON_UNESCAPED_UNICODE) : null;
            $params[] = $r['changed_fields'];
        }
        $Q = "INSERT INTO sync_change_log (ext_head_id, u_id, action, old_data, new_data, changed_fields, logged_at)
              VALUES " . implode(',', $placeholders);
        $R = $this->pdo->prepare($Q);
        $R->execute($params);
        return $R->rowCount();
    }

    public function sync_update_header_summary($ext_head_id, $new, $updated, $deactivated, $reactivated, $triggered_by){
        $Q = "UPDATE ext_data_temp_header SET
                total_new = :total_new,
                total_updated = :total_updated,
                total_deactivated = :total_deactivated,
                total_reactivated = :total_reactivated,
                triggered_by = :triggered_by
              WHERE ext_head_id = :ext_head_id";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':ext_head_id', $ext_head_id);
        $R->bindParam(':total_new', $new);
        $R->bindParam(':total_updated', $updated);
        $R->bindParam(':total_deactivated', $deactivated);
        $R->bindParam(':total_reactivated', $reactivated);
        $R->bindParam(':triggered_by', $triggered_by);
        $R->execute();
        return $R->rowCount();
    }

    public function sync_get_change_log_by_session($ext_head_id){
        $Q = "SELECT log_id, ext_head_id, u_id, action, old_data, new_data, changed_fields, logged_at
              FROM sync_change_log
              WHERE ext_head_id = :ext_head_id
              ORDER BY logged_at ASC, log_id ASC";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':ext_head_id', $ext_head_id);
        $R->execute();
        return $R->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sync_get_all_sessions(){
        $Q = "SELECT h.ext_head_id, h.ext_head_dt_start, h.ext_head_dt_end,
                     h.ext_head_status, h.total_new, h.total_updated,
                     h.total_deactivated, h.total_reactivated, h.triggered_by,
                     u.data1 AS triggered_by_name
              FROM ext_data_temp_header h
              LEFT JOIN user_tbl u ON u.u_id = h.triggered_by
              ORDER BY h.ext_head_id DESC";
        $R = $this->pdo->prepare($Q);
        $R->execute();
        return $R->fetchAll(PDO::FETCH_ASSOC);
    }


    public function get_specific_user_info($u_id){
        $Q = "SELECT u_id,u_category,u_type,avail_status,password_change_required,data1,data2,data3,data4,data5,data6,data7,data8,data9,data10,data11,data12
                FROM user_tbl WHERE u_id=:u_id";
        $R = $this->pdo->prepare($Q);        
        $R->bindParam(':u_id', $u_id);  
        $R->execute();
        $result = $R->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

//u_password
    public function get_specific_user_info_withpassword($u_id){
        $Q = "SELECT u_id,u_password,password_change_required,u_category,u_type,avail_status,data1,data2,data3,data4,data5,data6,data7,data8,data9,data10,data11,data12
                FROM user_tbl WHERE u_id=:u_id";
        $R = $this->pdo->prepare($Q);        
        $R->bindParam(':u_id', $u_id);  
        $R->execute();
        $result = $R->fetch(PDO::FETCH_ASSOC);
        return $result;
    }


    //Source 1 = SSO DB (registered) , 2 = externad DB (ureg yet)
   public function admin_search_user_account($u_id){
        $provenanceFields = $this->supportsUserProvenance()
            ? ',A.account_source,A.sync_protected'
            : ",'legacy' AS account_source,1 AS sync_protected";
        $Q = "SELECT A.u_id,A.data1,A.data2,A.data3,A.data4,A.u_category,A.u_type,'1' as source,A.avail_status,B.uc_name,A.u_update_datetime,A.u_changes_hash,A.data5,A.data6,A.data7" . $provenanceFields . "
                FROM user_tbl A 
                LEFT JOIN user_category B ON B.uc_id=A.u_category
                WHERE A.u_id=:u_id";
        $R = $this->pdo->prepare($Q);        
        $R->bindParam(':u_id', $u_id);  
        $R->execute();
        $result = $R->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * Read the complete row required by the M1 single-user resync planner.
     * FOR UPDATE is permitted only inside the short apply transaction; the
     * external SELECT snapshot is obtained before that transaction starts.
     */
    public function admin_get_user_for_resync(string $u_id, bool $forUpdate = false){
        $provenanceFields = $this->supportsUserProvenance()
            ? ', account_source, sync_protected'
            : ", 'legacy' AS account_source, 0 AS sync_protected";
        $Q = "SELECT u_id,u_category,u_type,avail_status,u_changes_hash,
                     data1,data2,data3,data4,data5,data6,data7,data8,data9,
                     data10,data11,data12" . $provenanceFields . "
              FROM user_tbl WHERE u_id=:u_id";
        if ($forUpdate) {
            $Q .= ' LIMIT 1 FOR UPDATE';
        } else {
            $Q .= ' LIMIT 1';
        }
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':u_id', $u_id);
        $R->execute();
        return $R->fetch(PDO::FETCH_ASSOC);
    }

    /** Read and optionally lock the minimum row required by M2 actions. */
    public function admin_get_user_for_security_action(string $u_id, bool $forUpdate = false){
        $Q = "SELECT u_id,avail_status,password_change_required FROM user_tbl WHERE u_id=:u_id LIMIT 1";
        if ($forUpdate) {
            $Q .= ' FOR UPDATE';
        }
        $R = $this->pdo->prepare($Q);
        $R->execute([':u_id' => $u_id]);
        return $R->fetch(PDO::FETCH_ASSOC);
    }

    /** Read and optionally lock the account fields governed by M3. */
    public function admin_get_user_for_profile_action(string $u_id, bool $forUpdate = false){
        $provenanceFields = $this->supportsUserProvenance()
            ? ', account_source, sync_protected'
            : ", 'legacy' AS account_source, 1 AS sync_protected";
        $Q = "SELECT u_id,u_category,u_type,avail_status,data1" . $provenanceFields . "
              FROM user_tbl WHERE u_id=:u_id LIMIT 1";
        if ($forUpdate) {
            $Q .= ' FOR UPDATE';
        }
        $R = $this->pdo->prepare($Q);
        $R->execute([':u_id' => $u_id]);
        return $R->fetch(PDO::FETCH_ASSOC);
    }

    /** M3 deliberately preserves u_type; role changes are not category changes. */
    public function admin_update_user_profile_category(string $u_id, string $name, int $categoryId): int{
        $Q = "UPDATE user_tbl
              SET data1=:name, u_category=:category_id, u_update_datetime=NOW()
              WHERE u_id=:u_id";
        $R = $this->pdo->prepare($Q);
        $R->execute([
            ':name' => $name,
            ':category_id' => $categoryId,
            ':u_id' => $u_id,
        ]);
        return $R->rowCount();
    }

    public function admin_get_active_user_category(int $categoryId){
        $Q = "SELECT uc_id,uc_name FROM user_category
              WHERE uc_id=:category_id AND avail_status=1 LIMIT 1";
        $R = $this->pdo->prepare($Q);
        $R->execute([':category_id' => $categoryId]);
        return $R->fetch(PDO::FETCH_ASSOC);
    }

    public function admin_get_active_service_provider_for_acl(string $spId){
        $Q = "SELECT sp_id,sp_name FROM sp_list
              WHERE sp_id=:sp_id AND avail_status=1 LIMIT 1";
        $R = $this->pdo->prepare($Q);
        $R->execute([':sp_id' => $spId]);
        return $R->fetch(PDO::FETCH_ASSOC);
    }

    public function admin_get_user_acl_state(string $uId, string $spId): array{
        $Q = "SELECT
                EXISTS(SELECT 1 FROM acl_single WHERE u_id=:u_id_1 AND sp_id=:sp_id_1) AS direct_allow,
                EXISTS(SELECT 1 FROM acl_group g
                       INNER JOIN user_tbl u ON u.u_category=g.uc_id
                       WHERE u.u_id=:u_id_2 AND g.sp_id=:sp_id_2) AS category_allow,
                EXISTS(SELECT 1 FROM acl_blacklist WHERE u_id=:u_id_3 AND sp_id=:sp_id_3) AS denied";
        $R = $this->pdo->prepare($Q);
        $R->execute([
            ':u_id_1' => $uId, ':sp_id_1' => $spId,
            ':u_id_2' => $uId, ':sp_id_2' => $spId,
            ':u_id_3' => $uId, ':sp_id_3' => $spId,
        ]);
        $row = $R->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'direct_allow' => (int) ($row['direct_allow'] ?? 0),
            'category_allow' => (int) ($row['category_allow'] ?? 0),
            'denied' => (int) ($row['denied'] ?? 0),
        ];
    }

    public function admin_get_blacklist_record_for_action(int $blacklistId, bool $forUpdate = false){
        $Q = "SELECT aclblk_id,u_id,sp_id FROM acl_blacklist
              WHERE aclblk_id=:blacklist_id LIMIT 1";
        if ($forUpdate) {
            $Q .= ' FOR UPDATE';
        }
        $R = $this->pdo->prepare($Q);
        $R->execute([':blacklist_id' => $blacklistId]);
        return $R->fetch(PDO::FETCH_ASSOC);
    }


    public function admin_update_user_status($u_id,$status){
            $Q = "UPDATE user_tbl SET avail_status = :status WHERE u_id = :u_id";
            $R = $this->pdo->prepare($Q);
        $R->bindParam(':u_id', $u_id);
        $R->bindParam(':status', $status);
        $R->execute();
        $result = $R->rowCount();
        return $result;
    }


   public function add_new_specific_apps_to_user($u_id,$sp_id){
        $Q = "INSERT INTO  acl_single(u_id,sp_id) VALUES (:u_id,:sp_id)";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':u_id', $u_id);
        $R->bindParam(':sp_id', $sp_id);
        $R->execute();        
        $result = $R->rowCount();
        return $result;
    }


    public function specfic_user_get_sp_list_by_group($uc_id){
        $Q = "SELECT A.sp_id,B.sp_name,B.sp_description,B.sp_domain,COALESCE(NULLIF(E.image_filename,''),B.sp_image) AS sp_image,B.sp_group_id
                FROM acl_group A 
                LEFT JOIN sp_list B ON B.sp_id = A.sp_id
                LEFT JOIN sp_app_asset E ON E.sp_id=B.sp_id AND E.environment=:environment
                WHERE A.uc_id=:uc_id AND B.avail_status=1";
        $R = $this->pdo->prepare($Q);        
        $R->bindParam(':uc_id', $uc_id);  
        $R->bindValue(':environment',$this->environment);
        $R->execute();
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function specfic_user_get_sp_list_by_specific_sp($u_id){
        $Q = "SELECT A.sp_id,B.sp_name,B.sp_description,B.sp_domain,COALESCE(NULLIF(E.image_filename,''),B.sp_image) AS sp_image,B.sp_group_id
                FROM acl_single A
                LEFT JOIN sp_list B ON B.sp_id = A.sp_id
                LEFT JOIN sp_app_asset E ON E.sp_id=B.sp_id AND E.environment=:environment
                WHERE A.u_id=:u_id AND B.avail_status=1";
        $R = $this->pdo->prepare($Q);        
        $R->bindParam(':u_id', $u_id);  
        $R->bindValue(':environment',$this->environment);
        $R->execute();
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function specfic_user_get_sp_blacklist($u_id){
        $Q = "SELECT A.sp_id,B.sp_name,B.sp_description,B.sp_domain,COALESCE(NULLIF(E.image_filename,''),B.sp_image) AS sp_image,A.aclblk_id,B.sp_group_id
                FROM acl_blacklist A 
                LEFT JOIN sp_list B ON B.sp_id = A.sp_id
                LEFT JOIN sp_app_asset E ON E.sp_id=B.sp_id AND E.environment=:environment
                WHERE A.u_id=:u_id";
        $R = $this->pdo->prepare($Q);        
        $R->bindParam(':u_id', $u_id);  
        $R->bindValue(':environment',$this->environment);
        $R->execute();
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }


    public function admin_get_all_service_provider(){
        $Q = "SELECT S.sp_id,S.sp_name,S.sp_description,S.sp_domain,COALESCE(NULLIF(E.image_filename,''),S.sp_image) AS sp_image,S.sp_sso_support
                FROM sp_list S LEFT JOIN sp_app_asset E ON E.sp_id=S.sp_id AND E.environment=:environment WHERE S.avail_status = 1 AND S.sp_sso_support = 0";
        $R = $this->pdo->prepare($Q);   
        $R->execute([':environment'=>$this->environment]);
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function admin_get_all_service_provider_non_sso(){
        $Q = "SELECT S.sp_id,S.sp_name,S.sp_description,S.sp_domain,COALESCE(NULLIF(E.image_filename,''),S.sp_image) AS sp_image,S.sp_sso_support
                FROM sp_list S LEFT JOIN sp_app_asset E ON E.sp_id=S.sp_id AND E.environment=:environment WHERE S.avail_status = 1 AND S.sp_sso_support = 1";
        $R = $this->pdo->prepare($Q);   
        $R->execute([':environment'=>$this->environment]);
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

   public function action_add_new_webapp_category($sp_group_name){
        $Q = "INSERT INTO  sp_group(sp_group_name) VALUES (:sp_group_name)";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':sp_group_name', $sp_group_name);
        $R->execute();        
        $result = $R->rowCount();
        return $result;
    }

    public function admin_find_app_category_by_name_for_update(string $name): array|false{
        $Q = "SELECT sp_group_id,sp_group_name FROM sp_group
              WHERE LOWER(TRIM(sp_group_name))=LOWER(TRIM(:name)) LIMIT 1 FOR UPDATE";
        $R = $this->pdo->prepare($Q);
        $R->execute([':name'=>$name]);
        return $R->fetch(PDO::FETCH_ASSOC);
    }

    public function admin_find_other_app_category_by_name_for_update(string $name, int $categoryId): array|false{
        $Q = "SELECT sp_group_id,sp_group_name FROM sp_group
              WHERE LOWER(TRIM(sp_group_name))=LOWER(TRIM(:name))
                AND sp_group_id<>:category_id
              LIMIT 1 FOR UPDATE";
        $R = $this->pdo->prepare($Q);
        $R->execute([':name'=>$name, ':category_id'=>$categoryId]);
        return $R->fetch(PDO::FETCH_ASSOC);
    }

    public function admin_create_app_category(string $name): int{
        $Q = "INSERT INTO sp_group(sp_group_name,sp_group_seq)
              VALUES (:name,COALESCE((SELECT MAX(sequence_value)+1 FROM (SELECT sp_group_seq AS sequence_value FROM sp_group) seq),1))";
        $R = $this->pdo->prepare($Q);
        $R->execute([':name'=>$name]);
        return $R->rowCount();
    }

    public function admin_rename_app_category(int $categoryId, string $name): int{
        $Q = "UPDATE sp_group SET sp_group_name=:name
              WHERE sp_group_id=:category_id AND sp_group_id<>0";
        $R = $this->pdo->prepare($Q);
        $R->execute([':name'=>$name, ':category_id'=>$categoryId]);
        return $R->rowCount();
    }

  public function admin_get_specific_web_app_category_info($sp_id){
        $Q = "SELECT sp_group_id,sp_group_name,sp_group_seq
                FROM sp_group where sp_group_id = :sp_id";
        $R = $this->pdo->prepare($Q);   
        $R->bindParam(':sp_id', $sp_id);
        $R->execute();
        $result = $R->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    public function admin_get_app_category_for_update(int $categoryId){
        $Q = "SELECT sp_group_id,sp_group_name,sp_group_seq
              FROM sp_group WHERE sp_group_id=:category_id LIMIT 1 FOR UPDATE";
        $R = $this->pdo->prepare($Q);
        $R->execute([':category_id' => $categoryId]);
        return $R->fetch(PDO::FETCH_ASSOC);
    }

    public function admin_count_apps_assigned_to_category(int $categoryId): int{
        $Q = "SELECT COUNT(*) FROM sp_list WHERE sp_group_id=:category_id";
        $R = $this->pdo->prepare($Q);
        $R->execute([':category_id' => $categoryId]);
        return (int) $R->fetchColumn();
    }

    public function admin_delete_empty_app_category(int $categoryId): int{
        $Q = "DELETE FROM sp_group
              WHERE sp_group_id=:category_id
                AND sp_group_id<>0
                AND NOT EXISTS (
                    SELECT 1 FROM sp_list WHERE sp_group_id=:assigned_category_id
                )";
        $R = $this->pdo->prepare($Q);
        $R->execute([
            ':category_id' => $categoryId,
            ':assigned_category_id' => $categoryId,
        ]);
        return $R->rowCount();
    }

   public function action_add_new_app($sp_id,$sp_name,$sp_description,$sp_domain,$sp_image,$sp_group_id,$sp_sso_support){
        $Q = "INSERT INTO  sp_list(sp_id,sp_name,sp_description,sp_domain,sp_image,avail_status,sp_group_id,sp_sso_support) VALUES (:sp_id,:sp_name,:sp_description,:sp_domain,:sp_image,1,:sp_group_id,:sp_sso_support)";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':sp_id', $sp_id);
        $R->bindParam(':sp_name', $sp_name);
        $R->bindParam(':sp_description', $sp_description);
        $R->bindParam(':sp_domain', $sp_domain);
        $R->bindParam(':sp_image', $sp_image);
        $R->bindParam(':sp_group_id', $sp_group_id);
        $R->bindParam(':sp_sso_support', $sp_sso_support);
        $R->execute();        
        $result = $R->rowCount();
        return $result;
    }

    public function admin_app_category_exists(int $categoryId): bool{
        $R=$this->pdo->prepare("SELECT 1 FROM sp_group WHERE sp_group_id=:category_id LIMIT 1");
        $R->execute([':category_id'=>$categoryId]);
        return $R->fetchColumn()!==false;
    }

    public function admin_app_id_exists(string $appId): bool{
        $R=$this->pdo->prepare("SELECT 1 FROM sp_list WHERE sp_id=:app_id LIMIT 1");
        $R->execute([':app_id'=>$appId]);
        return $R->fetchColumn()!==false;
    }

    public function admin_upsert_app_asset(string $appId,string $filename,string $updatedBy): int{
        $Q="INSERT INTO sp_app_asset(sp_id,environment,image_filename,updated_by)
            VALUES(:app_id,:environment,:filename,:updated_by)
            ON DUPLICATE KEY UPDATE image_filename=VALUES(image_filename),updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP";
        $R=$this->pdo->prepare($Q);
        $R->execute([':app_id'=>$appId,':environment'=>$this->environment,':filename'=>$filename,':updated_by'=>$updatedBy]);
        return $R->rowCount();
    }

    public function admin_get_environment(): string{return $this->environment;}



    public function action_edit_app_info($sp_id,$sp_name,$sp_description,$sp_domain,$sp_image,$sp_group_id,$sp_sso_support){
            $Q = "UPDATE sp_list SET sp_name = :sp_name,sp_description = :sp_description,sp_domain = :sp_domain,sp_image = :sp_image,sp_sso_support=:sp_sso_support,sp_group_id=:sp_group_id WHERE sp_id = :sp_id";
            $R = $this->pdo->prepare($Q);
        $R->bindParam(':sp_id', $sp_id);
        $R->bindParam(':sp_name', $sp_name);
        $R->bindParam(':sp_description', $sp_description);
        $R->bindParam(':sp_domain', $sp_domain);
        $R->bindParam(':sp_image', $sp_image);
        $R->bindParam(':sp_group_id', $sp_group_id);
        $R->bindParam(':sp_sso_support', $sp_sso_support);
        $R->execute();
        $result = $R->rowCount();
        return $result;
    }

    public function admin_update_app_metadata($sp_id,$sp_name,$sp_description,$sp_domain,$sp_group_id,$sp_sso_support): int{
        $Q="UPDATE sp_list SET sp_name=:sp_name,sp_description=:sp_description,sp_domain=:sp_domain,sp_sso_support=:sp_sso_support,sp_group_id=:sp_group_id WHERE sp_id=:sp_id";
        $R=$this->pdo->prepare($Q);
        $R->execute([':sp_id'=>$sp_id,':sp_name'=>$sp_name,':sp_description'=>$sp_description,':sp_domain'=>$sp_domain,':sp_group_id'=>$sp_group_id,':sp_sso_support'=>$sp_sso_support]);
        return $R->rowCount();
    }


    public function action_update_app_status($app_id,$status){
        $Q = "UPDATE sp_list SET avail_status=:status
                WHERE sp_id = :app_id";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':app_id', $app_id);
        $R->bindParam(':status', $status);
        $R->execute();
        $result = $R->rowCount();
        return $result;
    }

    public function action_remove_app($app_id){
        $Q = "DELETE FROM sp_list
                WHERE sp_id = :app_id";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':app_id', $app_id);
        $R->execute();
        $result = $R->rowCount();
        return $result;
    }

    public function admin_get_service_provider_for_update(string $appId): array|false{
        $Q = "SELECT S.sp_id,S.sp_name,S.sp_description,S.sp_domain,
                     COALESCE(NULLIF(E.image_filename,''),S.sp_image) AS sp_image,
                     S.sp_image AS legacy_sp_image,E.image_filename AS environment_sp_image,
                     S.avail_status,S.sp_group_id,S.sp_sso_support
              FROM sp_list S
              LEFT JOIN sp_app_asset E ON E.sp_id=S.sp_id AND E.environment=:environment
              WHERE S.sp_id=:app_id FOR UPDATE";
        $R = $this->pdo->prepare($Q);
        $R->execute([':app_id'=>$appId,':environment'=>$this->environment]);
        return $R->fetch(PDO::FETCH_ASSOC);
    }

    public function admin_archive_service_provider(string $appId): int{
        $Q = "UPDATE sp_list SET avail_status=0,sp_group_id=0
              WHERE sp_id=:app_id AND avail_status=1";
        $R = $this->pdo->prepare($Q);
        $R->execute([':app_id'=>$appId]);
        return $R->rowCount();
    }

    public function admin_delete_app_access_references(string $table, string $appId): int{
        $allowed = ['acl_group','acl_single','acl_blacklist','user_app_favourite'];
        if (!in_array($table, $allowed, true)) {
            throw new InvalidArgumentException('Unsupported app access table.');
        }
        if ($table === 'user_app_favourite' && !$this->supportsUserAppFavourites()) {
            return 0;
        }
        $R = $this->pdo->prepare("DELETE FROM `{$table}` WHERE sp_id=:app_id");
        $R->execute([':app_id'=>$appId]);
        return $R->rowCount();
    }



    public function admin_get_specific_service_provider($sp_id){
        $Q = "SELECT S.*,COALESCE(NULLIF(E.image_filename,''),S.sp_image) AS sp_image
                FROM sp_list S LEFT JOIN sp_app_asset E ON E.sp_id=S.sp_id AND E.environment=:environment
                WHERE S.sp_id=:sp_id";
        $R = $this->pdo->prepare($Q);   
        $R->bindParam(':sp_id', $sp_id);  
        $R->bindValue(':environment',$this->environment);
        $R->execute();
        $result = $R->fetch(PDO::FETCH_ASSOC);
        return $result;
    }


    public function admin_get_all_blacklist_record(){
        $Q = "SELECT A.aclblk_id,A.u_id,B.data1,C.sp_name
                FROM acl_blacklist A
                LEFT JOIN user_tbl B ON B.u_id = A.u_id
                LEFT JOIN sp_list C ON C.sp_id = A.sp_id
                ";
        $R = $this->pdo->prepare($Q);   
        $R->execute();
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }


   public function action_add_new_category($uc_name){
        $Q = "INSERT INTO  user_category(uc_name,avail_status) VALUES (:uc_name,1)";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':uc_name', $uc_name);
        $R->execute();        
        $result = $R->rowCount();
        return $result;
    }


    public function admin_get_all_user_category(){
        $Q = "SELECT A.*, (SELECT COUNT(*) FROM user_tbl WHERE u_category=A.uc_id AND avail_status=1) as total
                FROM user_category A WHERE A.avail_status =1";
        $R = $this->pdo->prepare($Q);   
        $R->execute();
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }


    public function admin_get_specific_category_user_listing($uc_id){
        $Q = "SELECT u_id,data1,data2,data3,data4,data5,data6,data7,data8,data9,data10,data11,data12
                FROM user_tbl 
                WHERE u_category=:uc_id AND avail_status=1";
        $R = $this->pdo->prepare($Q);   
        $R->bindParam(':uc_id', $uc_id);
        $R->execute();
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function admin_get_category_site_listing($uc_id){
        $Q = "SELECT A.aclgp_id,A.uc_id,A.sp_id,B.sp_name,sp_description,sp_domain
                FROM acl_group A
                LEFT JOIN sp_list B ON B.sp_id = A.sp_id 
                WHERE uc_id=:uc_id";
        $R = $this->pdo->prepare($Q);   
        $R->bindParam(':uc_id', $uc_id);
        $R->execute();
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }


    public function admin_get_category_site_listing_add_new_site($uc_id){
        $Q = "SELECT A.* FROM sp_list A
                LEFT JOIN acl_group B ON B.sp_id = A.sp_id AND B.uc_id = :uc_id
                WHERE A.avail_status=1 AND B.sp_id IS NULL";
        $R = $this->pdo->prepare($Q);   
        $R->bindParam(':uc_id', $uc_id);
        $R->execute();
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

   public function add_acl_category($uc_id,$sp_id){
        $Q = "INSERT INTO  acl_group(uc_id,sp_id) VALUES (:uc_id,:sp_id)";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':uc_id', $uc_id);
        $R->bindParam(':sp_id', $sp_id);
        $R->execute();        
        $result = $R->rowCount();
        return $result;
    }


       public function remove_acl_category($aclgp_id){
        $Q = "DELETE FROM acl_group
                WHERE aclgp_id = :aclgp_id";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':aclgp_id', $aclgp_id);
        $R->execute();
        $result = $R->rowCount();
        return $result;
    }


       public function remove_acl_category_all_by_sp_id($sp_id){
        $Q = "DELETE FROM acl_group
                WHERE sp_id = :sp_id";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':sp_id', $sp_id);
        $R->execute();
        $result = $R->rowCount();
        return $result;
    }


    public function admin_remove_category($uc_id){
            $Q = "UPDATE user_category SET avail_status = 0 WHERE uc_id = :uc_id;UPDATE user_tbl SET u_category = 0 WHERE u_category = :uc_id;DELETE FROM acl_group WHERE uc_id = :uc_id;";
            $R = $this->pdo->prepare($Q);
        $R->bindParam(':uc_id', $uc_id);
        $R->bindParam(':status', $status);
        $R->execute();
        $result = $R->rowCount();
        return $result;
    }

    public function admin_widget_count(){
        $Q = "SELECT (SELECT COUNT(*) FROM user_tbl WHERE avail_status=1) as total_user, (SELECT COUNT(*) FROM sp_list WHERE avail_status=1) as total_sp";
        $R = $this->pdo->prepare($Q);   
        $R->execute();
        $result = $R->fetch(PDO::FETCH_ASSOC);
        return $result;
    }



   public function admin_set_deny_access_record($sp_id,$user_id){
        $Q = "INSERT INTO  acl_blacklist(sp_id,u_id) VALUES (:sp_id,:user_id)";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':sp_id', $sp_id);
        $R->bindParam(':user_id', $user_id);
        $R->execute();        
        $result = $R->rowCount();
        return $result;
    }

       public function admin_uplift_blacklist_record($aclblk_id){
        $Q = "DELETE FROM acl_blacklist
                WHERE aclblk_id = :aclblk_id";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':aclblk_id', $aclblk_id);
        $R->execute();
        $result = $R->rowCount();
        return $result;
    }



    public function check_token($token){
        $tokenHash = oneid_token_hash((string) $token);
        $legacyToken = strlen((string) $token) <= 25 ? (string) $token : '__not_legacy__';
        $Q = "SELECT A.*
                FROM token_tbl A
                LEFT JOIN user_tbl B ON B.u_id = A.user_id
                WHERE (A.token_id=:token_hash OR A.token_id=:legacy_token)
                  AND B.avail_status=1";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':token_hash', $tokenHash);
        $R->bindParam(':legacy_token', $legacyToken);
        $R->execute();
        $result = $R->fetch(PDO::FETCH_ASSOC);
        if ($result && hash_equals((string) $result['token_id'], $legacyToken)) {
            $migrate = $this->pdo->prepare("UPDATE token_tbl SET token_id=:token_hash WHERE token_id=:legacy_token");
            $migrate->execute([':token_hash'=>$tokenHash, ':legacy_token'=>$legacyToken]);
            $result['token_id'] = $tokenHash;
        }
        return $result;
    }


   public function add_new_token($token_id,$user_id,$device){
        $storedToken = oneid_token_hash((string) $token_id);
        $Q = "INSERT INTO token_tbl(token_id,token_datetime,token_issued_at,user_id,status,device_info,site_id) VALUES (:token_id,NOW(),NOW(),:user_id,1,:device,0)";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':token_id', $storedToken);
        $R->bindParam(':user_id', $user_id);
        $R->bindParam(':device', $device);
        $R->execute();        
        $result = $R->rowCount();
        return $result;
    }



    public function update_all_token_status($site_id,$user_id,$status){
            $Q = "UPDATE token_tbl SET status = :status WHERE site_id = :site_id AND user_id = :user_id";
            $R = $this->pdo->prepare($Q);
        $R->bindParam(':status', $status);
        $R->bindParam(':site_id', $site_id);
        $R->bindParam(':user_id', $user_id);
        $R->execute();
        $result = $R->rowCount();
        return $result;
    }

    public function update_whole_token_status($user_id,$status){
            $Q = "UPDATE token_tbl SET status = :status WHERE user_id = :user_id";
            $R = $this->pdo->prepare($Q);
        $R->bindParam(':status', $status);
        $R->bindParam(':user_id', $user_id);
        $R->execute();
        $result = $R->rowCount();
        return $result;
    }

    public function update_specific_token_status($user_id,$token_id,$status){
        $tokenHash = oneid_token_hash((string) $token_id);
            $Q = "UPDATE token_tbl SET status = :status WHERE user_id = :user_id AND (token_id=:token_hash OR token_id=:token_id)";
            $R = $this->pdo->prepare($Q);
        $R->bindParam(':status', $status);
        $R->bindParam(':user_id', $user_id);
        $R->bindParam(':token_hash', $tokenHash);
        $R->bindParam(':token_id', $token_id);
        $R->execute();
        $result = $R->rowCount();
        return $result;
    }

   public function update_specific_token_datetime($user_id,$token_id){
        $tokenHash = oneid_token_hash((string) $token_id);
            $Q = "UPDATE token_tbl SET token_datetime = NOW() WHERE user_id = :user_id AND (token_id=:token_hash OR token_id=:token_id) AND status=1";
            $R = $this->pdo->prepare($Q);
        $R->bindParam(':user_id', $user_id);
        $R->bindParam(':token_hash', $tokenHash);
        $R->bindParam(':token_id', $token_id);
        $R->execute();
        $result = $R->rowCount();
        return $result;
    }

    public function is_specific_token_active($user_id,$token_id){
        $tokenHash = oneid_token_hash((string)$token_id);
        $Q = "SELECT 1 FROM token_tbl WHERE user_id=:user_id AND status=1 AND (token_id=:token_hash OR token_id=:legacy_token) LIMIT 1";
        $R = $this->pdo->prepare($Q);
        $R->execute([':user_id'=>$user_id,':token_hash'=>$tokenHash,':legacy_token'=>$token_id]);
        return $R->fetchColumn() !== false;
    }

    public function get_all_token_for_specific_user($user_id){
        $Q = "SELECT *
                FROM token_tbl WHERE user_id=:user_id AND status=1";
        $R = $this->pdo->prepare($Q);        
        $R->bindParam(':user_id', $user_id);  
        $R->execute();
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    /** @param array<string, mixed> $filters */
    public function admin_list_active_sessions(array $filters): array{
        $pageSize=(int)($filters['page_size']??0);$offset=(int)($filters['offset']??-1);
        if(!in_array($pageSize,[10,25,50],true)||$offset<0)throw new InvalidArgumentException('AS0_PAGINATION_INVALID');
        $search=str_replace(['\\','%','_'],['\\\\','\\%','\\_'],trim((string)($filters['query']??'')));
        $params=[
            ':now_value'=>(string)($filters['now']??''),
            ':active_cutoff'=>(string)($filters['active_cutoff']??''),
            ':refresh_cutoff'=>(string)($filters['refresh_cutoff']??''),
            ':current_user_id'=>(string)($filters['current_user_id']??''),
            ':current_token'=>(string)($filters['current_token']??''),
            ':current_token_hash'=>(string)($filters['current_token_hash']??''),
            ':search_value'=>$search===''?'':'%'.$search.'%',
            ':requested_status'=>(string)($filters['status']??'all'),
        ];
        $base="SELECT A.user_id,COALESCE(NULLIF(B.data1,''),A.user_id) AS name,A.device_info,
                     A.token_issued_at AS issued_at,A.token_datetime AS last_activity_at,
                     A.policy_revoke_at AS revoke_at,P.search_value,P.requested_status,
                     CASE
                       WHEN A.token_issued_at>P.now_value OR A.token_issued_at<=P.refresh_cutoff THEN 'expired'
                       WHEN A.policy_revoke_at IS NOT NULL AND A.policy_revoke_at<=P.now_value THEN 'due'
                       WHEN A.policy_revoke_at IS NOT NULL AND A.policy_revoke_at>P.now_value THEN 'grace'
                       WHEN A.token_issued_at<P.active_cutoff THEN 'refresh'
                       WHEN A.user_id=P.current_user_id AND (A.token_id=P.current_token_hash OR A.token_id=P.current_token) THEN 'current'
                       ELSE 'active'
                     END AS lifecycle_status
              FROM token_tbl A
              LEFT JOIN user_tbl B ON B.u_id=A.user_id
              CROSS JOIN (SELECT CAST(:now_value AS DATETIME) AS now_value,
                                 CAST(:active_cutoff AS DATETIME) AS active_cutoff,
                                 CAST(:refresh_cutoff AS DATETIME) AS refresh_cutoff,
                                 :current_user_id AS current_user_id,
                                 :current_token AS current_token,
                                 :current_token_hash AS current_token_hash,
                                 :search_value AS search_value,
                                 :requested_status AS requested_status) P
              WHERE A.status=1";
        $filtered=" FROM (".$base.") S
                    WHERE (S.search_value='' OR S.user_id LIKE S.search_value ESCAPE '\\\\'
                           OR S.name LIKE S.search_value ESCAPE '\\\\'
                           OR S.device_info LIKE S.search_value ESCAPE '\\\\')
                      AND (S.requested_status='all' OR S.lifecycle_status=S.requested_status)";
        $count=$this->pdo->prepare('SELECT COUNT(*)'.$filtered);$count->execute($params);$total=(int)$count->fetchColumn();
        $metricSql="SELECT lifecycle_status,COUNT(*) total FROM (".$base.") M
                    WHERE (M.search_value='' OR M.user_id LIKE M.search_value ESCAPE '\\\\'
                           OR M.name LIKE M.search_value ESCAPE '\\\\'
                           OR M.device_info LIKE M.search_value ESCAPE '\\\\') GROUP BY lifecycle_status";
        $metricStatement=$this->pdo->prepare($metricSql);$metricStatement->execute($params);$metrics=[];
        foreach($metricStatement->fetchAll(PDO::FETCH_ASSOC) as$metric)$metrics[(string)$metric['lifecycle_status']]=(int)$metric['total'];
        $rows=$this->pdo->prepare('SELECT user_id,name,device_info,issued_at,last_activity_at,revoke_at,lifecycle_status'.$filtered.' ORDER BY last_activity_at DESC,user_id ASC LIMIT '.$pageSize.' OFFSET '.$offset);
        $rows->execute($params);
        return ['rows'=>$rows->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'metrics'=>$metrics];
    }

    public function remove_queue($no_matrix){
        $Q = "DELETE FROM checkpoint
                WHERE no_matrix = :no_matrix";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':no_matrix', $no_matrix);
        $R->execute();
        $result = $R->rowCount();
        return $result;
    }

    public function get_sp_group(){
        $Q = "SELECT g.sp_group_id,g.sp_group_name,g.sp_group_seq,
                     SUM(CASE WHEN s.avail_status=1 THEN 1 ELSE 0 END) AS active_count,
                     SUM(CASE WHEN s.avail_status=0 THEN 1 ELSE 0 END) AS inactive_count,
                     COUNT(s.sp_id) AS assigned_count
              FROM sp_group g
              LEFT JOIN sp_list s ON s.sp_group_id=g.sp_group_id
              GROUP BY g.sp_group_id,g.sp_group_name,g.sp_group_seq
              ORDER BY (g.sp_group_id=0) ASC,g.sp_group_seq DESC,g.sp_group_name ASC";
        $R = $this->pdo->prepare($Q);
        $R->execute();
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function admin_get_active_app_directory_rows(): array{
        $Q = "SELECT g.sp_group_id,g.sp_group_name,g.sp_group_seq,
                     s.sp_id,s.sp_name,s.sp_description,s.sp_domain,COALESCE(NULLIF(e.image_filename,''),s.sp_image) AS sp_image,s.sp_sso_support
              FROM sp_group g
              INNER JOIN sp_list s ON s.sp_group_id=g.sp_group_id AND s.avail_status=1
              LEFT JOIN sp_app_asset e ON e.sp_id=s.sp_id AND e.environment=:environment
              ORDER BY (g.sp_group_id=0) ASC,g.sp_group_seq DESC,g.sp_group_name ASC,s.sp_name ASC";
        $R = $this->pdo->prepare($Q);
        $R->execute([':environment'=>$this->environment]);
        return $R->fetchAll(PDO::FETCH_ASSOC);
    }


    public function admin_get_all_service_provider_byGroup($sp_group_id){
        $Q = "SELECT S.sp_id,S.sp_name,S.sp_description,S.sp_domain,COALESCE(NULLIF(E.image_filename,''),S.sp_image) AS sp_image,S.sp_sso_support,S.sp_group_id
                FROM sp_list S LEFT JOIN sp_app_asset E ON E.sp_id=S.sp_id AND E.environment=:environment WHERE S.avail_status = 1 AND S.sp_sso_support = 0 AND S.sp_group_id=:sp_group_id";
        $R = $this->pdo->prepare($Q);   
        $R->bindParam(':sp_group_id', $sp_group_id);
        $R->bindValue(':environment',$this->environment);
        $R->execute();
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function admin_get_all_service_provider_non_sso_byGroup($sp_group_id){
        $Q = "SELECT S.sp_id,S.sp_name,S.sp_description,S.sp_domain,COALESCE(NULLIF(E.image_filename,''),S.sp_image) AS sp_image,S.sp_sso_support,S.sp_group_id
                FROM sp_list S LEFT JOIN sp_app_asset E ON E.sp_id=S.sp_id AND E.environment=:environment WHERE S.avail_status = 1 AND S.sp_sso_support = 1 AND S.sp_group_id=:sp_group_id";
        $R = $this->pdo->prepare($Q);   
        $R->bindParam(':sp_group_id', $sp_group_id);
        $R->bindValue(':environment',$this->environment);
        $R->execute();
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function syslog_record($log_type,$log_detail,$ip_addr){
        $Q = "INSERT INTO  syslog(log_type,log_detail,ip_addr,datetime) VALUES (:log_type,:log_detail,:ip_addr,NOW())";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':log_type', $log_type);
        $R->bindParam(':log_detail', $log_detail);
        $R->bindParam(':ip_addr', $ip_addr);
        $R->execute();        
        $result = $R->rowCount();
        return $result;
    }

    public function admin_get_audit_range($date_start,$date_end){
        $Q = "SELECT A.id AS audit_id,A.log_detail,A.ip_addr,A.datetime,B.syslog_event_name as log_type
                FROM syslog A
                LEFT JOIN syslog_event_conf B ON B.syslog_event_id = A.log_type 
                WHERE A.datetime >= :date_start
                  AND A.datetime < DATE_ADD(:date_end, INTERVAL 1 DAY)
                ORDER BY A.datetime DESC,A.id DESC
                LIMIT 50";
        $R = $this->pdo->prepare($Q);   
        $R->bindParam(':date_start', $date_start);
        $R->bindParam(':date_end', $date_end);
        $R->execute();
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }


    public function otp_create($u_id,$otp_code){
        $otpHash = oneid_password_hash((string) $otp_code);
        $Q = "INSERT INTO otp_codes(u_id,otp_code,otp_create_date,otp_expires_at,otp_attempts,otp_consumed_at)
              VALUES (:u_id,:otp_code,NOW(),DATE_ADD(NOW(), INTERVAL 5 MINUTE),0,NULL)";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':u_id', $u_id);
        $R->bindParam(':otp_code', $otpHash);
        $R->execute();        
        $result = $R->rowCount();
        return $result;
    }

    public function otp_check($u_id){
        $Q = "SELECT *
                FROM otp_codes
                WHERE u_id = :u_id
                  AND otp_expires_at >= NOW()
                  AND otp_consumed_at IS NULL
                  AND otp_attempts < 5
                ORDER BY otp_create_date DESC
                LIMIT 1";
        $R = $this->pdo->prepare($Q);   
        $R->bindParam(':u_id', $u_id);
        $R->execute();
        $result = $R->fetch(PDO::FETCH_ASSOC);
        return $result;
    }



    public function otp_update_otp_create_date($otp_id){
            $Q = "UPDATE otp_codes SET otp_create_date = NOW(), otp_expires_at=DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE otp_id = :otp_id AND otp_consumed_at IS NULL";
            $R = $this->pdo->prepare($Q);
        $R->bindParam(':otp_id', $otp_id);
        $R->execute();
        $result = $R->rowCount();
        return $result;
    }

    public function otp_latest_request($u_id){
        $Q = "SELECT otp_id,otp_create_date FROM otp_codes WHERE u_id=:u_id ORDER BY otp_create_date DESC LIMIT 1";
        $R = $this->pdo->prepare($Q);
        $R->execute([':u_id'=>$u_id]);
        return $R->fetch(PDO::FETCH_ASSOC);
    }

    public function otp_count_last_day($u_id){
        $Q = "SELECT COUNT(*) FROM otp_codes WHERE u_id=:u_id AND otp_create_date >= (NOW() - INTERVAL 1 DAY)";
        $R = $this->pdo->prepare($Q);
        $R->execute([':u_id'=>$u_id]);
        return (int) $R->fetchColumn();
    }

    public function otp_invalidate_active($u_id){
        $Q = "UPDATE otp_codes SET otp_consumed_at=NOW() WHERE u_id=:u_id AND otp_consumed_at IS NULL";
        $R = $this->pdo->prepare($Q);
        $R->execute([':u_id'=>$u_id]);
        return $R->rowCount();
    }

    public function otp_record_failed_attempt($otp_id){
        $Q = "UPDATE otp_codes
              SET otp_attempts=otp_attempts+1,
                  otp_consumed_at=IF(otp_attempts+1 >= 5,NOW(),otp_consumed_at)
              WHERE otp_id=:otp_id AND otp_consumed_at IS NULL";
        $R = $this->pdo->prepare($Q);
        $R->execute([':otp_id'=>$otp_id]);
        return $R->rowCount();
    }

    public function otp_consume($otp_id){
        $Q = "UPDATE otp_codes SET otp_consumed_at=NOW() WHERE otp_id=:otp_id AND otp_consumed_at IS NULL";
        $R = $this->pdo->prepare($Q);
        $R->execute([':otp_id'=>$otp_id]);
        return $R->rowCount();
    }

    public function admin_step_up_request_context_for_update($adminId){
        $Q="SELECT u.u_type,u.avail_status,u.data1 display_name,u.data5 email,c.admin_2fa_enabled,c.admin_step_up_lifetime_minutes
            FROM user_tbl u CROSS JOIN sys_config c
            WHERE u.u_id=:admin_id AND c.singleton_key=1 FOR UPDATE";
        $R=$this->pdo->prepare($Q);$R->execute([':admin_id'=>$adminId]);
        return $R->fetch(PDO::FETCH_ASSOC);
    }

    public function admin_step_up_request_stats($adminId,$purpose,$sessionHash,$ipAddress){
        $Q="SELECT
              COALESCE(MAX(CASE WHEN admin_user_id=:admin_id AND purpose=:purpose
                                THEN GREATEST(0,60-TIMESTAMPDIFF(SECOND,created_at,NOW())) END),0) cooldown_seconds,
              SUM(admin_user_id=:admin_id AND purpose=:purpose AND created_at>=NOW()-INTERVAL 1 HOUR) admin_hour,
              SUM(admin_user_id=:admin_id AND purpose=:purpose AND created_at>=NOW()-INTERVAL 1 DAY) admin_day,
              SUM(session_binding_hash=:session_hash AND purpose=:purpose AND created_at>=NOW()-INTERVAL 1 HOUR) session_hour,
              SUM(requesting_ip=:ip_address AND purpose=:purpose AND created_at>=NOW()-INTERVAL 1 HOUR) ip_hour
            FROM admin_step_up_challenges";
        $R=$this->pdo->prepare($Q);$R->execute([':admin_id'=>$adminId,':purpose'=>$purpose,':session_hash'=>$sessionHash,':ip_address'=>$ipAddress]);
        return $R->fetch(PDO::FETCH_ASSOC);
    }

    public function admin_step_up_revoke_open_challenges($adminId,$purpose,$sessionHash){
        $Q="UPDATE admin_step_up_challenges SET revoked_at=NOW()
            WHERE admin_user_id=:admin_id AND purpose=:purpose
              AND session_binding_hash=:session_hash
              AND consumed_at IS NULL AND revoked_at IS NULL";
        $R=$this->pdo->prepare($Q);$R->execute([':admin_id'=>$adminId,':purpose'=>$purpose,':session_hash'=>$sessionHash]);
        return $R->rowCount();
    }

    public function admin_step_up_create_email_challenge(array $entry){
        $Q="INSERT INTO admin_step_up_challenges
            (challenge_id,admin_user_id,purpose,factor_type,otp_hash,session_binding_hash,
             browser_digest,requesting_ip,attempts,max_attempts,resend_count,created_at,
             sent_at,expires_at,consumed_at,revoked_at,correlation_id)
            VALUES(:challenge_id,:admin_user_id,:purpose,'EMAIL_OTP',:otp_hash,:session_binding_hash,
                   :browser_digest,:requesting_ip,0,5,0,NOW(),NULL,DATE_ADD(NOW(),INTERVAL 5 MINUTE),NULL,NULL,:correlation_id)";
        $R=$this->pdo->prepare($Q);$R->execute([
            ':challenge_id'=>$entry['challenge_id'],':admin_user_id'=>$entry['admin_user_id'],
            ':purpose'=>$entry['purpose'],':otp_hash'=>$entry['otp_hash'],
            ':session_binding_hash'=>$entry['session_binding_hash'],':browser_digest'=>$entry['browser_digest'],
            ':requesting_ip'=>$entry['requesting_ip'],':correlation_id'=>$entry['correlation_id'],
        ]);return $R->rowCount();
    }

    public function admin_step_up_mark_challenge_sent($challengeId){
        $Q="UPDATE admin_step_up_challenges SET sent_at=NOW()
            WHERE challenge_id=:challenge_id AND sent_at IS NULL
              AND consumed_at IS NULL AND revoked_at IS NULL AND expires_at>NOW()";
        $R=$this->pdo->prepare($Q);$R->execute([':challenge_id'=>$challengeId]);return $R->rowCount();
    }

    public function admin_step_up_revoke_challenge($challengeId){
        $Q="UPDATE admin_step_up_challenges SET revoked_at=COALESCE(revoked_at,NOW())
            WHERE challenge_id=:challenge_id AND consumed_at IS NULL";
        $R=$this->pdo->prepare($Q);$R->execute([':challenge_id'=>$challengeId]);return $R->rowCount();
    }

    public function admin_step_up_challenge_for_update($challengeId){
        $Q="SELECT ch.*,c.admin_2fa_enabled,c.admin_step_up_lifetime_minutes,(ch.expires_at<=NOW()) is_expired
            FROM admin_step_up_challenges ch CROSS JOIN sys_config c
            WHERE ch.challenge_id=:challenge_id AND c.singleton_key=1 FOR UPDATE";
        $R=$this->pdo->prepare($Q);$R->execute([':challenge_id'=>$challengeId]);return $R->fetch(PDO::FETCH_ASSOC);
    }

    public function admin_step_up_record_failed_attempt($challengeId){
        $Q="UPDATE admin_step_up_challenges
            SET attempts=attempts+1,revoked_at=IF(attempts+1>=max_attempts,NOW(),revoked_at)
            WHERE challenge_id=:challenge_id AND sent_at IS NOT NULL
              AND consumed_at IS NULL AND revoked_at IS NULL AND expires_at>NOW()";
        $R=$this->pdo->prepare($Q);$R->execute([':challenge_id'=>$challengeId]);return $R->rowCount();
    }

    public function admin_step_up_consume_challenge($challengeId){
        $Q="UPDATE admin_step_up_challenges SET consumed_at=NOW(),otp_hash=NULL
            WHERE challenge_id=:challenge_id AND sent_at IS NOT NULL
              AND consumed_at IS NULL AND revoked_at IS NULL AND expires_at>NOW()
              AND attempts<max_attempts";
        $R=$this->pdo->prepare($Q);$R->execute([':challenge_id'=>$challengeId]);return $R->rowCount();
    }

    public function admin_step_up_create_grant(array $entry){
        $minutes=(int)($entry['lifetime_minutes']??15);
        if(!in_array($minutes,[5,10,15,30],true)){throw new InvalidArgumentException('STEP_UP_LIFETIME_INVALID');}
        $Q="INSERT INTO admin_step_up_grants
            (grant_id,admin_user_id,session_binding_hash,browser_digest,purpose,verified_factor,
             issued_at,expires_at,revoked_at,correlation_id)
            VALUES(:grant_id,:admin_user_id,:session_binding_hash,:browser_digest,:purpose,
                   :verified_factor,NOW(),DATE_ADD(NOW(),INTERVAL {$minutes} MINUTE),NULL,:correlation_id)";
        $R=$this->pdo->prepare($Q);$R->execute([
            ':grant_id'=>$entry['grant_id'],':admin_user_id'=>$entry['admin_user_id'],
            ':session_binding_hash'=>$entry['session_binding_hash'],':browser_digest'=>$entry['browser_digest'],
            ':purpose'=>$entry['purpose'],':verified_factor'=>$entry['verified_factor'],
            ':correlation_id'=>$entry['correlation_id'],
        ]);return $R->rowCount();
    }

    public function admin_mfa_enrollment_context_for_update($adminId){
        $Q="SELECT u_type,avail_status,u_password,data5 email FROM user_tbl
            WHERE u_id=:admin_id FOR UPDATE";
        $R=$this->pdo->prepare($Q);$R->execute([':admin_id'=>$adminId]);return $R->fetch(PDO::FETCH_ASSOC);
    }

    public function admin_mfa_revoke_pending_factors($adminId){
        $Q="UPDATE admin_mfa_factors SET factor_status='REVOKED',revoked_at=NOW()
            WHERE admin_user_id=:admin_id AND factor_type='TOTP' AND factor_status='PENDING'";
        $R=$this->pdo->prepare($Q);$R->execute([':admin_id'=>$adminId]);return $R->rowCount();
    }

    public function admin_mfa_create_pending_factor(array $entry){
        $Q="INSERT INTO admin_mfa_factors
            (admin_user_id,factor_type,encrypted_secret,secret_nonce,key_version,factor_status,
             device_label,created_by,correlation_id,enrollment_session_hash,enrollment_browser_digest)
            VALUES(:admin_user_id,'TOTP',:encrypted_secret,:secret_nonce,:key_version,'PENDING',
                   :device_label,:created_by,:correlation_id,:enrollment_session_hash,:enrollment_browser_digest)";
        $R=$this->pdo->prepare($Q);$R->execute($entry);return (int)$this->pdo->lastInsertId();
    }

    public function admin_mfa_factor_for_update($factorId){
        $Q="SELECT * FROM admin_mfa_factors WHERE factor_id=:factor_id FOR UPDATE";
        $R=$this->pdo->prepare($Q);$R->execute([':factor_id'=>$factorId]);return $R->fetch(PDO::FETCH_ASSOC);
    }

    public function admin_mfa_confirm_factor($factorId,$timeStep){
        $Q="UPDATE admin_mfa_factors SET factor_status='ACTIVE',confirmed_at=NOW(),last_used_at=NOW(),last_used_time_step=:time_step
            WHERE factor_id=:factor_id AND factor_status='PENDING'";
        $R=$this->pdo->prepare($Q);$R->execute([':factor_id'=>$factorId,':time_step'=>$timeStep]);return $R->rowCount();
    }

    public function admin_mfa_record_factor_use($factorId,$timeStep){
        $Q="UPDATE admin_mfa_factors SET last_used_at=NOW(),last_used_time_step=:time_step
            WHERE factor_id=:factor_id AND factor_status='ACTIVE'
              AND (last_used_time_step IS NULL OR last_used_time_step<:time_step_guard)";
        $R=$this->pdo->prepare($Q);$R->execute([':factor_id'=>$factorId,':time_step'=>$timeStep,':time_step_guard'=>$timeStep]);return $R->rowCount();
    }

    public function admin_step_up_has_valid_email_recovery_grant($adminId,$sessionHash,$browserDigest){
        $Q="SELECT COUNT(*) FROM admin_step_up_grants WHERE admin_user_id=:admin_id
            AND session_binding_hash=:session_hash AND browser_digest=:browser_digest
            AND purpose='SECURITY_CONFIGURATION_CHANGE' AND verified_factor='EMAIL_OTP'
            AND revoked_at IS NULL AND expires_at>NOW()";
        $R=$this->pdo->prepare($Q);$R->execute([':admin_id'=>$adminId,':session_hash'=>$sessionHash,':browser_digest'=>$browserDigest]);return (int)$R->fetchColumn()>0;
    }

    public function admin_step_up_authorization_state($adminId,$sessionHash,$browserDigest,$purpose){
        $Q="SELECT c.admin_2fa_enabled,u.u_type,u.avail_status,
              SUM(g.purpose=:purpose AND g.revoked_at IS NULL AND g.expires_at>NOW()) exact_valid,
              MAX(CASE WHEN g.purpose=:purpose_remaining AND g.revoked_at IS NULL AND g.expires_at>NOW()
                       THEN TIMESTAMPDIFF(SECOND,NOW(),g.expires_at) ELSE 0 END) exact_remaining_seconds,
              SUM(g.purpose=:purpose AND g.revoked_at IS NULL AND g.expires_at<=NOW()) exact_expired,
              SUM(g.purpose<>:purpose_other AND g.revoked_at IS NULL AND g.expires_at>NOW()) other_valid
            FROM user_tbl u CROSS JOIN sys_config c
            LEFT JOIN admin_step_up_grants g ON g.admin_user_id=u.u_id
              AND g.session_binding_hash=:session_hash AND g.browser_digest=:browser_digest
            WHERE u.u_id=:admin_id AND c.singleton_key=1
            GROUP BY c.admin_2fa_enabled,u.u_type,u.avail_status";
        $R=$this->pdo->prepare($Q);$R->execute([':purpose'=>$purpose,':purpose_remaining'=>$purpose,':purpose_other'=>$purpose,':session_hash'=>$sessionHash,':browser_digest'=>$browserDigest,':admin_id'=>$adminId]);return $R->fetch(PDO::FETCH_ASSOC);
    }

    public function admin_mfa_revoke_factor($factorId){
        $Q="UPDATE admin_mfa_factors SET factor_status='REVOKED',revoked_at=NOW()
            WHERE factor_id=:factor_id AND factor_status IN ('PENDING','ACTIVE')";
        $R=$this->pdo->prepare($Q);$R->execute([':factor_id'=>$factorId]);return $R->rowCount();
    }

    public function admin_mfa_clear_totp_preference($adminId){
        $Q="DELETE FROM admin_mfa_preferences WHERE admin_user_id=:admin_id AND preferred_factor='TOTP'";
        $R=$this->pdo->prepare($Q);$R->execute([':admin_id'=>$adminId]);return $R->rowCount();
    }

    public function admin_step_up_factor_status($adminId){
        $Q="SELECT c.admin_2fa_enabled,c.configuration_version,c.admin_step_up_lifetime_minutes,u.u_type,u.avail_status,u.data5 email,
              EXISTS(SELECT 1 FROM admin_mfa_factors f WHERE f.admin_user_id=u.u_id AND f.factor_type='TOTP' AND f.factor_status='ACTIVE') totp_available,
              (SELECT factor_id FROM admin_mfa_factors f WHERE f.admin_user_id=u.u_id AND f.factor_type='TOTP' AND f.factor_status='ACTIVE' ORDER BY f.confirmed_at DESC,f.factor_id DESC LIMIT 1) totp_factor_id,
              p.preferred_factor
            FROM user_tbl u CROSS JOIN sys_config c LEFT JOIN admin_mfa_preferences p ON p.admin_user_id=u.u_id
            WHERE u.u_id=:admin_id AND c.singleton_key=1";
        $R=$this->pdo->prepare($Q);$R->execute([':admin_id'=>$adminId]);return $R->fetch(PDO::FETCH_ASSOC);
    }

    public function admin_mfa_active_factor_for_update($adminId){
        $Q="SELECT * FROM admin_mfa_factors WHERE admin_user_id=:admin_id AND factor_type='TOTP' AND factor_status='ACTIVE' ORDER BY confirmed_at DESC,factor_id DESC LIMIT 1 FOR UPDATE";
        $R=$this->pdo->prepare($Q);$R->execute([':admin_id'=>$adminId]);return $R->fetch(PDO::FETCH_ASSOC);
    }

    public function admin_mfa_pending_factor_for_qr($adminId,$factorId,$sessionHash,$browserDigest){
        $Q="SELECT factor_id,admin_user_id,encrypted_secret,secret_nonce,key_version FROM admin_mfa_factors
            WHERE factor_id=:factor_id AND admin_user_id=:admin_id AND factor_type='TOTP' AND factor_status='PENDING'
              AND enrollment_session_hash=:session_hash AND enrollment_browser_digest=:browser";
        $R=$this->pdo->prepare($Q);$R->execute([':factor_id'=>$factorId,':admin_id'=>$adminId,':session_hash'=>$sessionHash,':browser'=>$browserDigest]);return $R->fetch(PDO::FETCH_ASSOC);
    }

    public function admin_mfa_set_preference($adminId,$factor,$actor,$correlation){
        $Q="INSERT INTO admin_mfa_preferences(admin_user_id,preferred_factor,updated_by,correlation_id)
            VALUES(:admin_id,:factor,:actor,:correlation)
            ON DUPLICATE KEY UPDATE preferred_factor=VALUES(preferred_factor),updated_by=VALUES(updated_by),correlation_id=VALUES(correlation_id),updated_at=NOW()";
        $R=$this->pdo->prepare($Q);$R->execute([':admin_id'=>$adminId,':factor'=>$factor,':actor'=>$actor,':correlation'=>$correlation]);return $R->rowCount();
    }

    public function admin_step_up_rebind_grant($adminId,$purpose,$correlation,$oldSessionHash,$newSessionHash,$browserDigest){
        $verify=$this->pdo->prepare("SELECT COUNT(*) FROM admin_step_up_grants
            WHERE admin_user_id=:admin_id AND purpose=:purpose AND correlation_id=:correlation
              AND session_binding_hash=:old_session AND browser_digest=:browser
              AND revoked_at IS NULL AND expires_at>NOW()");
        $verify->execute([':admin_id'=>$adminId,':purpose'=>$purpose,':correlation'=>$correlation,':old_session'=>$oldSessionHash,':browser'=>$browserDigest]);
        if((int)$verify->fetchColumn()!==1){return 0;}

        // Session fixation protection must not strand other, independently scoped grants.
        // Keep each purpose unchanged; migrate only active grants from this exact session/browser.
        $Q="UPDATE admin_step_up_grants SET session_binding_hash=:new_session
            WHERE admin_user_id=:admin_id AND session_binding_hash=:old_session
              AND browser_digest=:browser AND revoked_at IS NULL AND expires_at>NOW()";
        $R=$this->pdo->prepare($Q);$R->execute([':new_session'=>$newSessionHash,':admin_id'=>$adminId,':old_session'=>$oldSessionHash,':browser'=>$browserDigest]);return $R->rowCount()>0?1:0;
    }

    public function admin_2fa_enable_by_version($configId,$expectedVersion){
        $Q="UPDATE sys_config SET admin_2fa_enabled=1,configuration_version=configuration_version+1
            WHERE id=:id AND singleton_key=1 AND configuration_version=:version AND admin_2fa_enabled=0";
        $R=$this->pdo->prepare($Q);$R->execute([':id'=>$configId,':version'=>$expectedVersion]);return $R->rowCount();
    }



}

?>
