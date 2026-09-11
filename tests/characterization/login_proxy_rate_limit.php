<?php

declare(strict_types=1);

define('ONEID_CONFIG_SKIP_DATABASE', true);
require_once dirname(__DIR__, 2) . '/lib/config.php';
require_once dirname(__DIR__, 2) . '/lib/client_ip.php';

$failures = 0;
$check = static function (bool $condition, string $label) use (&$failures): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    $failures += $condition ? 0 : 1;
};

$check(
    oneid_client_ip([
        'REMOTE_ADDR' => '172.16.4.65',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.19',
    ]) === '203.0.113.19',
    'trusted WAF resolves the originating client'
);
$check(
    oneid_client_ip([
        'REMOTE_ADDR' => '198.51.100.30',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.99',
    ]) === '198.51.100.30',
    'untrusted peer cannot spoof a forwarded client'
);
$check(
    oneid_client_ip([
        'REMOTE_ADDR' => '172.16.4.65',
        'HTTP_X_FORWARDED_FOR' => '198.51.100.99, 203.0.113.20',
    ]) === '203.0.113.20',
    'right-most untrusted address wins in an appended proxy chain'
);
$check(oneid_ip_matches_network('2001:db8::4', '2001:db8::/32'), 'IPv6 CIDR matching works');
$check(!oneid_ip_matches_network('2001:dead::4', '2001:db8::/32'), 'IPv6 CIDR mismatch is rejected');

$databaseSource = (string) file_get_contents(dirname(__DIR__, 2) . '/lib/Database.php');
$check(
    substr_count($databaseSource, "NOT LIKE '%reason=AUTH_RATE_LIMITED%'") >= 2,
    'rate-limit rejection events cannot perpetuate the lock'
);

$loginSource = (string) file_get_contents(dirname(__DIR__, 2) . '/index.php');
$check(
    str_contains($loginSource, 'xhr.responseJSON')
        && str_contains($loginSource, 'response.login_response_msg'),
    'HTTP error responses display the server login message'
);

exit($failures === 0 ? 0 : 1);
