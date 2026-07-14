<?php
 
class Database {
  
    protected $pdo;
    private ?bool $userProvenanceSupported = null;
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
    }

    public function set_user_password($userId,$password,$changeRequired=0){
        $this->updatePasswordHash($userId, oneid_password_hash($password), (int) $changeRequired);
        return 1;
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
        $Q = "SELECT * FROM sys_config";
        $R = $this->pdo->prepare($Q);      
        $R->execute();
        $result = $R->fetch(PDO::FETCH_ASSOC);
        return $result;
    }


    public function update_configuration($token_timeout,$multi_session,$email_OTP){
            $Q = "UPDATE sys_config SET token_timeout = :token_timeout, multi_session=:multi_session,email_OTP=:email_OTP";
            $R = $this->pdo->prepare($Q);
        $R->bindParam(':token_timeout', $token_timeout);
        $R->bindParam(':multi_session', $multi_session);
        $R->bindParam(':email_OTP', $email_OTP);
        $R->execute();
        $result = $R->rowCount();
        return $result;
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
        $Q = "SELECT A.u_id,A.data1,A.data2,A.data3,A.data4,A.u_category,A.u_type,'1' as source,A.avail_status,B.uc_name,A.u_update_datetime,A.u_changes_hash,A.data5,A.data6,A.data7
                FROM user_tbl A 
                LEFT JOIN user_category B ON B.uc_id=A.u_category
                WHERE A.u_id=:u_id";
        $R = $this->pdo->prepare($Q);        
        $R->bindParam(':u_id', $u_id);  
        $R->execute();
        $result = $R->fetch(PDO::FETCH_ASSOC);
        return $result;
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
                WHERE A.uc_id=:uc_id";
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
                WHERE A.u_id=:u_id";
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

  public function admin_get_specific_web_app_category_info($sp_id){
        $Q = "SELECT sp_group_id,sp_group_name,sp_group_seq
                FROM sp_group where sp_group_id = :sp_id";
        $R = $this->pdo->prepare($Q);   
        $R->bindParam(':sp_id', $sp_id);
        $R->execute();
        $result = $R->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    public function reset_web_app_category($sp_id){
            $Q = "UPDATE sp_list SET sp_group_id = 0 WHERE sp_id = :sp_id";
            $R = $this->pdo->prepare($Q);
        $R->bindParam(':sp_id', $sp_id);
        $R->execute();
        $result = $R->rowCount();
        return $result;
    }


    public function action_remove_app_category($sp_group_id){
        $Q = "DELETE FROM sp_group
                WHERE sp_group_id = :sp_group_id";
        $R = $this->pdo->prepare($Q);
        $R->bindParam(':sp_group_id', $sp_group_id);
        $R->execute();
        $result = $R->rowCount();
        return $result;
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
        $Q = "SELECT A.aclblk_id,B.data1,C.sp_name
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

    public function admin_change_user_category($u_id,$u_category,$u_type){
            $Q = "UPDATE user_tbl SET u_category = :u_category,u_type = :u_type WHERE u_id = :u_id";
            $R = $this->pdo->prepare($Q);
        $R->bindParam(':u_id', $u_id);
        $R->bindParam(':u_category', $u_category);
        $R->bindParam(':u_type', $u_type);
        $R->execute();
        $result = $R->rowCount();
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
        $Q = "INSERT INTO token_tbl(token_id,token_datetime,user_id,status,device_info,site_id) VALUES (:token_id,NOW(),:user_id,1,:device,0)";
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
        $Q = "SELECT * FROM sp_group";
        $R = $this->pdo->prepare($Q);
        $R->execute();
        $result = $R->fetchAll(PDO::FETCH_ASSOC);
        return $result;
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
        $Q = "SELECT A.log_detail,A.ip_addr,A.datetime,B.syslog_event_name as log_type
                FROM syslog A
                LEFT JOIN syslog_event_conf B ON B.syslog_event_id = A.log_type 
                WHERE A.datetime BETWEEN  :date_start AND :date_end ORDER BY A.datetime DESC LIMIT 50";
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
