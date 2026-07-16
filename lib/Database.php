<?php
 
class Database {
  
    protected $pdo;
    private ?bool $userProvenanceSupported = null;
    private ?bool $userAppFavouritesSupported = null;
    public function __construct()
    {
        try
        {
            $this->pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '" . DB_CHARACSET . "';"));
            $this->pdo->exec("SET CHARACTER SET " . DB_CHARACSET);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->query("set names " . DB_CHARACSET);
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
        $Q = "SELECT id, token_timeout, multi_session, password_reset_email_enabled FROM sys_config WHERE singleton_key = 1";
        $R = $this->pdo->prepare($Q);      
        $R->execute();
        $result = $R->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    public function get_system_config_for_update(){
        $Q = "SELECT id, token_timeout, multi_session, password_reset_email_enabled FROM sys_config WHERE singleton_key = 1 FOR UPDATE";
        $R = $this->pdo->prepare($Q);
        $R->execute();
        return $R->fetch(PDO::FETCH_ASSOC);
    }

    public function update_configuration_by_id($configId,$token_timeout,$multi_session){
        $Q = "UPDATE sys_config SET token_timeout = :token_timeout, multi_session=:multi_session WHERE id = :config_id AND singleton_key = 1";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':config_id', $configId, PDO::PARAM_INT);
        $R->bindParam(':token_timeout', $token_timeout);
        $R->bindParam(':multi_session', $multi_session, PDO::PARAM_INT);
        $R->execute();
        return $R->rowCount();
    }

    public function update_password_recovery_by_id($configId,$enabled){
        $Q = "UPDATE sys_config SET password_reset_email_enabled=:enabled WHERE id=:config_id AND singleton_key=1";
        $R=$this->pdo->prepare($Q);$R->execute([':config_id'=>$configId,':enabled'=>$enabled]);
        return $R->rowCount();
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


    public function action_change_password($user_id,$password){
        $Q = "UPDATE user_tbl SET u_password=:password
                WHERE u_id = :user_id";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':user_id', $user_id);
        $R->bindParam(':password', $password);
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
        $Q = "SELECT A.sp_id,B.sp_name,B.sp_description,B.sp_domain,B.sp_image,B.sp_group_id
                FROM acl_group A 
                LEFT JOIN sp_list B ON B.sp_id = A.sp_id
                WHERE A.uc_id=:uc_id AND B.avail_status=1";
        $R = $this->pdo->prepare($Q);        
        $R->bindParam(':uc_id', $uc_id);  
        $R->execute();
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function specfic_user_get_sp_list_by_specific_sp($u_id){
        $Q = "SELECT A.sp_id,B.sp_name,B.sp_description,B.sp_domain,B.sp_image,B.sp_group_id
                FROM acl_single A
                LEFT JOIN sp_list B ON B.sp_id = A.sp_id
                WHERE A.u_id=:u_id AND B.avail_status=1";
        $R = $this->pdo->prepare($Q);        
        $R->bindParam(':u_id', $u_id);  
        $R->execute();
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function specfic_user_get_sp_blacklist($u_id){
        $Q = "SELECT A.sp_id,B.sp_name,B.sp_description,B.sp_domain,B.sp_image,A.aclblk_id,B.sp_group_id
                FROM acl_blacklist A 
                LEFT JOIN sp_list B ON B.sp_id = A.sp_id
                WHERE A.u_id=:u_id";
        $R = $this->pdo->prepare($Q);        
        $R->bindParam(':u_id', $u_id);  
        $R->execute();
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }


    public function admin_get_all_service_provider(){
        $Q = "SELECT sp_id,sp_name,sp_description,sp_domain,sp_image,sp_sso_support
                FROM sp_list where avail_status = 1 AND sp_sso_support = 0";
        $R = $this->pdo->prepare($Q);   
        $R->execute();
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function admin_get_all_service_provider_non_sso(){
        $Q = "SELECT sp_id,sp_name,sp_description,sp_domain,sp_image,sp_sso_support
                FROM sp_list where avail_status = 1 AND sp_sso_support = 1";
        $R = $this->pdo->prepare($Q);   
        $R->execute();
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

    public function admin_create_app_category(string $name): int{
        $Q = "INSERT INTO sp_group(sp_group_name,sp_group_seq)
              VALUES (:name,COALESCE((SELECT MAX(sequence_value)+1 FROM (SELECT sp_group_seq AS sequence_value FROM sp_group) seq),1))";
        $R = $this->pdo->prepare($Q);
        $R->execute([':name'=>$name]);
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
        $Q = "SELECT sp_id,sp_name,avail_status,sp_group_id FROM sp_list
              WHERE sp_id=:app_id FOR UPDATE";
        $R = $this->pdo->prepare($Q);
        $R->execute([':app_id'=>$appId]);
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
        $Q = "SELECT *
                FROM sp_list
                WHERE sp_id=:sp_id";
        $R = $this->pdo->prepare($Q);   
        $R->bindParam(':sp_id', $sp_id);  
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

    public function get_all_token_for_specific_user($user_id){
        $Q = "SELECT *
                FROM token_tbl WHERE user_id=:user_id AND status=1";
        $R = $this->pdo->prepare($Q);        
        $R->bindParam(':user_id', $user_id);  
        $R->execute();
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function get_all_token_for_all_active_user(){
        $Q = "SELECT A.*,B.data1 as name
                FROM token_tbl A 
                LEFT JOIN user_tbl B ON B.u_id = A.user_id
                WHERE A.status=1 ORDER BY A.token_datetime";
        $R = $this->pdo->prepare($Q);        
        $R->execute();
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
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
                     s.sp_id,s.sp_name,s.sp_description,s.sp_domain,s.sp_image,s.sp_sso_support
              FROM sp_group g
              INNER JOIN sp_list s ON s.sp_group_id=g.sp_group_id AND s.avail_status=1
              ORDER BY (g.sp_group_id=0) ASC,g.sp_group_seq DESC,g.sp_group_name ASC,s.sp_name ASC";
        $R = $this->pdo->prepare($Q);
        $R->execute();
        return $R->fetchAll(PDO::FETCH_ASSOC);
    }


    public function admin_get_all_service_provider_byGroup($sp_group_id){
        $Q = "SELECT sp_id,sp_name,sp_description,sp_domain,sp_image,sp_sso_support,sp_group_id
                FROM sp_list where avail_status = 1 AND sp_sso_support = 0 AND sp_group_id=:sp_group_id";
        $R = $this->pdo->prepare($Q);   
        $R->bindParam(':sp_group_id', $sp_group_id);
        $R->execute();
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function admin_get_all_service_provider_non_sso_byGroup($sp_group_id){
        $Q = "SELECT sp_id,sp_name,sp_description,sp_domain,sp_image,sp_sso_support,sp_group_id
                FROM sp_list where avail_status = 1 AND sp_sso_support = 1 AND sp_group_id=:sp_group_id";
        $R = $this->pdo->prepare($Q);   
        $R->bindParam(':sp_group_id', $sp_group_id);
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



}

?>
