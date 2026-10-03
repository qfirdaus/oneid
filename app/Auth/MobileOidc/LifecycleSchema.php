<?php
declare(strict_types=1);
namespace OneId\App\Auth\MobileOidc;

use PDO;

/** Additive MySQL 8 schema. Never executes itself; observer initially OFF. */
final class LifecycleSchema
{
    public static function statements(): array
    {
        $sql = [
            'CREATE TABLE mobile_oidc_control (singleton_id TINYINT PRIMARY KEY,observer_enabled TINYINT NOT NULL DEFAULT 0,policy_epoch BIGINT UNSIGNED NOT NULL DEFAULT 1) ENGINE=InnoDB',
            'INSERT INTO mobile_oidc_control VALUES(1,0,1)',
            'CREATE TABLE mobile_oidc_identity_epoch (u_id VARCHAR(20) NOT NULL PRIMARY KEY,generation CHAR(48) CHARACTER SET ascii NOT NULL,security_version BIGINT UNSIGNED NOT NULL DEFAULT 1,deleted TINYINT NOT NULL DEFAULT 0,updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)) ENGINE=InnoDB',
            'INSERT INTO mobile_oidc_identity_epoch(u_id,generation) SELECT u_id,HEX(RANDOM_BYTES(24)) FROM user_tbl',
            "CREATE TRIGGER mobile_oidc_control_bu BEFORE UPDATE ON mobile_oidc_control FOR EACH ROW BEGIN IF OLD.observer_enabled<>NEW.observer_enabled THEN SET NEW.policy_epoch=OLD.policy_epoch+1; END IF; END",
        ];
        $guard = '(SELECT observer_enabled FROM mobile_oidc_control WHERE singleton_id=1)=1';
        $bump = static fn(string $uid): string => "INSERT INTO mobile_oidc_identity_epoch(u_id,generation) VALUES($uid,HEX(RANDOM_BYTES(24))) ON DUPLICATE KEY UPDATE security_version=security_version+1;";
        $sql[] = "CREATE TRIGGER mobile_oidc_user_ai AFTER INSERT ON user_tbl FOR EACH ROW BEGIN IF $guard THEN INSERT INTO mobile_oidc_identity_epoch(u_id,generation) VALUES(NEW.u_id,HEX(RANDOM_BYTES(24))) ON DUPLICATE KEY UPDATE generation=HEX(RANDOM_BYTES(24)),security_version=security_version+1,deleted=0; END IF; END";
        $changes = [];
        foreach (['u_id','avail_status','password_change_required','u_type','u_category','data2','data3','data4','data5'] as $column) $changes[] = "NOT (BINARY OLD.$column <=> BINARY NEW.$column)";
        $changes[] = 'NOT (BINARY OLD.u_password <=> BINARY NEW.u_password) AND COALESCE(@oneid_mobile_password_rehash,0)<>1';
        $sql[] = 'CREATE TRIGGER mobile_oidc_user_au AFTER UPDATE ON user_tbl FOR EACH ROW BEGIN IF ' . $guard . ' AND (' . implode(' OR ', $changes) . ') THEN ' . $bump('NEW.u_id') . " IF OLD.u_id<>NEW.u_id THEN " . $bump('OLD.u_id') . ' END IF; END IF; END';
        $sql[] = "CREATE TRIGGER mobile_oidc_user_ad AFTER DELETE ON user_tbl FOR EACH ROW BEGIN IF $guard THEN " . $bump('OLD.u_id') . ' UPDATE mobile_oidc_identity_epoch SET deleted=1 WHERE u_id=OLD.u_id; END IF; END';
        foreach (['user_mfa_factors' => 'factor', 'user_external_identity' => 'membership',
            'user_login_mfa_exemptions' => 'exemption', 'user_login_mfa_pilot_users' => 'pilot'] as $table => $label) {
            foreach (['INSERT' => 'ai', 'UPDATE' => 'au', 'DELETE' => 'ad'] as $event => $suffix) {
                $condition = 'TRUE';
                if ($event === 'UPDATE' && $table === 'user_mfa_factors') {
                    $condition = implode(' OR ', array_map(static fn($c) => "NOT (BINARY OLD.$c <=> BINARY NEW.$c)",
                        ['u_id','factor_type','factor_status','encrypted_secret','secret_nonce','key_version']));
                } elseif ($event === 'UPDATE' && $table === 'user_external_identity') {
                    $condition = 'NOT (OLD.u_id <=> NEW.u_id) OR NOT (OLD.source_code <=> NEW.source_code) OR NOT (OLD.source_active <=> NEW.source_active)';
                }
                $body = $bump($event === 'DELETE' ? 'OLD.u_id' : 'NEW.u_id');
                if ($event === 'UPDATE') $body .= ' IF OLD.u_id<>NEW.u_id THEN ' . $bump('OLD.u_id') . ' END IF;';
                $sql[] = "CREATE TRIGGER mobile_oidc_{$label}_$suffix AFTER $event ON $table FOR EACH ROW BEGIN IF $guard AND ($condition) THEN $body END IF; END";
            }
        }
        foreach (['user_login_mfa_policy' => 'policy', 'user_login_mfa_category_policy' => 'category',
            'user_mfa_policy_change_requests' => 'operational', 'external_source' => 'source'] as $table => $label) {
            foreach (['INSERT' => 'ai','UPDATE' => 'au','DELETE' => 'ad'] as $event => $suffix) {
                $sql[] = "CREATE TRIGGER mobile_oidc_{$label}_$suffix AFTER $event ON $table FOR EACH ROW BEGIN IF $guard THEN UPDATE mobile_oidc_control SET policy_epoch=policy_epoch+1 WHERE singleton_id=1; END IF; END";
            }
        }
        return $sql;
    }

    public static function triggerNames(): array
    {
        $names = [];
        foreach (self::statements() as $sql) if (preg_match('/^CREATE TRIGGER (\w+)/', $sql, $match)) $names[] = $match[1];
        return $names;
    }

    public static function ready(PDO $pdo): bool
    {
        $names = self::triggerNames();
        $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME IN (' . implode(',', array_fill(0, count($names), '?')) . ')');
        $q->execute($names);
        return (int) $q->fetchColumn() === count($names)
            && (int) $pdo->query('SELECT observer_enabled FROM mobile_oidc_control WHERE singleton_id=1')->fetchColumn() === 1;
    }
}
