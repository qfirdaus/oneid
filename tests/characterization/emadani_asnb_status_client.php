<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Integration/EmadaniAsnbStatusClient.php';

use OneId\App\Integration\EmadaniAsnbStatusClient;

function assert_emadani(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$secret = str_repeat('a', 32);
$transport = static function (string $url, array $headers, string $body, int $timeout) use ($secret): array {
    $map = [];
    foreach ($headers as $header) {
        [$name, $value] = array_pad(explode(':', $header, 2), 2, '');
        $map[strtolower(trim($name))] = trim($value);
    }
    $path = (string)parse_url($url, PHP_URL_PATH);
    $canonical = "POST\n{$path}\n{$map['x-timestamp']}\n{$map['x-nonce']}\n" . hash('sha256', $body);
    assert_emadani(hash_equals(hash_hmac('sha256', $canonical, $secret), $map['x-signature']), 'HMAC mismatch');
    assert_emadani($timeout === 5, 'Timeout mismatch');
    assert_emadani(json_decode($body, true) === ['matrik' => 'UPNM001'], 'Body mismatch');
    return ['status' => 200, 'body' => '{"ok":true,"applicable":true,"asnb_complete":false}'];
};

$client = new EmadaniAsnbStatusClient('https://emadani.example/api/asnb-status.php', 'oneid', $secret, 5, $transport);
assert_emadani($client->check(' upnm001 ') === ['applicable' => true, 'asnb_complete' => false], 'Valid response rejected');

$invalid = new EmadaniAsnbStatusClient('http://emadani.example/api/asnb-status.php', 'oneid', $secret, 5, $transport);
assert_emadani($invalid->check('UPNM001') === null, 'Insecure HTTP endpoint accepted');

$unavailable = new EmadaniAsnbStatusClient(
    'https://emadani.example/api/asnb-status.php', 'oneid', $secret, 5,
    static fn(): array => ['status' => 503, 'body' => '{"ok":false}']
);
assert_emadani($unavailable->check('UPNM001') === null, 'Provider failure did not fail open');

echo "emadani_asnb_status_client: passed\n";
