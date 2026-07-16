<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/config.php';

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$userId = 'S1TEST-20260714';
$siteId = 'BTOG4WZNQP';
$rawToken = bin2hex(random_bytes(24));
$oldHash = oneid_token_hash($rawToken);
$cleanupHashes = [$oldHash];
$checks = 0; $failures = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failures): void {
    $checks++; if (!$ok) $failures++; printf("%s: %s\n", $ok ? 'PASS' : 'FAIL', $label);
};
$callApi = static function (string $token) use ($siteId): array {
    $handle = curl_init('https://oneid.local/api.php');
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['flag'=>'1','data'=>['token'=>$token,'site_id'=>$siteId]]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    return ['status'=>$status,'body'=>is_string($body)?json_decode($body,true):null,'error'=>$error];
};

try {
    $user = $pdo->query("SELECT avail_status FROM user_tbl WHERE u_id='S1TEST-20260714'")->fetchColumn();
    $allow = (int) $pdo->query("SELECT COUNT(*) FROM acl_single WHERE u_id='S1TEST-20260714' AND sp_id='BTOG4WZNQP'")->fetchColumn();
    $report((int)$user === 1 && $allow === 1, 'controlled pilot identity is active and allowed for IQS-Framework');
    $operation->add_new_token($rawToken, $userId, 'SC4 controlled refresh pilot');
    $age = $pdo->prepare("UPDATE token_tbl SET token_issued_at=DATE_SUB(NOW(),INTERVAL 31 MINUTE),token_datetime=NOW() WHERE token_id=:hash AND user_id=:user");
    $age->execute([':hash'=>$oldHash,':user'=>$userId]);
    $report($age->rowCount() === 1, 'pilot token is placed one minute inside legacy refresh window');

    $refresh = $callApi($rawToken);
    $newToken = is_array($refresh['body']) ? (string)($refresh['body']['respond_new_token'] ?? '') : '';
    $report($refresh['status'] === 200 && $refresh['error'] === '', 'real SSO validation endpoint returns HTTP 200 without transport error');
    $report(is_array($refresh['body']) && (string)($refresh['body']['respond_flag']??'') === '2' && (string)($refresh['body']['respond']??'') === '1' && $newToken !== '', 'expired pilot token receives successful legacy refresh contract');
    if ($newToken !== '') {
        $cleanupHashes[] = oneid_token_hash($newToken);
        $validation = $callApi($newToken);
        $report($validation['status'] === 200 && (string)($validation['body']['respond_flag']??'') === '1' && (string)($validation['body']['respond']??'') === '1', 'newly issued token validates as active for IQS-Framework');
    }
} finally {
    $placeholders = implode(',', array_fill(0, count($cleanupHashes), '?'));
    $delete = $pdo->prepare("DELETE FROM token_tbl WHERE user_id=? AND token_id IN ({$placeholders})");
    $delete->execute(array_merge([$userId], $cleanupHashes));
    $remaining = $pdo->prepare("SELECT COUNT(*) FROM token_tbl WHERE user_id=? AND token_id IN ({$placeholders})");
    $remaining->execute(array_merge([$userId], $cleanupHashes));
    $report((int)$remaining->fetchColumn() === 0, 'all controlled pilot tokens are removed after the test');
}

printf("RESULT: checks=%d failures=%d\n", $checks, $failures);
exit($failures === 0 ? 0 : 1);
