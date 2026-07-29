<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}
require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/lib/session_security.php';
require_once dirname(__DIR__) . '/lib/request_security.php';

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$identity = $pdo->prepare(
    'SELECT u_id,data1,u_type,password_change_required,data5
       FROM user_tbl WHERE data3=:login_alias AND avail_status=1 LIMIT 1'
);
$identity->execute([':login_alias' => '0530-09']);
$user = $identity->fetch(PDO::FETCH_ASSOC);
if (!is_array($user)
    || filter_var((string) $user['data5'], FILTER_VALIDATE_EMAIL) === false
) {
    fwrite(STDERR, "FAIL PILOT_IDENTITY_MISMATCH\n");
    exit(1);
}

session_name('ONEID_U7_CONTROLLED');
session_id(bin2hex(random_bytes(16)));
oneid_start_secure_session();
$before = session_id();
$csrfBefore = oneid_csrf_token();
oneid_establish_authenticated_session($user);
$after = session_id();
$csrfCleared = !isset($_SESSION['oneid_csrf_token']);
$csrfAfter = oneid_csrf_token();
$passed = $before !== $after
    && $csrfCleared
    && !hash_equals($csrfBefore, $csrfAfter)
    && ($_SESSION['login_status'] ?? '') === 'true'
    && hash_equals((string) $_SESSION['login_user'], (string) $user['u_id']);
$_SESSION = [];
session_destroy();

printf(
    "%s real PHP session rotates after authentication and regenerates CSRF canonical_id_output=0 session_id_output=0\n",
    $passed ? 'PASS' : 'FAIL'
);
printf(
    "RESULT checks=1 failures=%d database_mutations=0 persistent_session_artifacts=0\n",
    $passed ? 0 : 1
);
exit($passed ? 0 : 1);
