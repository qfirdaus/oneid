<?php
declare(strict_types=1);
// Narrow, reproducible PHP 8.4 compatibility patch for locked Jumbojett v1.0.2.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$apply = in_array('--apply', $argv, true);
$file = dirname(__DIR__) . '/vendor/jumbojett/openid-connect-php/src/OpenIDConnectClient.php';
$originalHash = '77e9fd82a5977de3557e209082a51d7ea51feb43fdc94e3b231bf1bde83fbaeb';
$patchedHash = '99c0c8358d7deebdef4f60a5db03d02cb66b1397a78d1d1a0cec511ac4381db0';
try {
    $source = file_get_contents($file);
    if ($source === false) throw new RuntimeException('Dependency source missing; run Composer install first.');
    $hash = hash('sha256', $source);
    if (hash_equals($patchedHash, $hash)) { echo "PASS: Jumbojett nullable patch present.\n"; exit(0); }
    if (!hash_equals($originalHash, $hash)) throw new RuntimeException('Unexpected dependency checksum; review patch against the installed version. No changes made.');
    if (!$apply) throw new RuntimeException('Patch required: run php tools/patch_openid_php84.php --apply');
    $patched = preg_replace('/(?<![?\\w])string (\\$\\w+ = null)/', '?string $1', $source, -1, $count);
    if ($count !== 12 || !is_string($patched) || !hash_equals($patchedHash, hash('sha256', $patched))) {
        throw new RuntimeException('Patch verification failed. No changes made.');
    }
    $temporary = tempnam(dirname($file), '.php84-');
    if ($temporary === false) throw new RuntimeException('Cannot create temporary file.');
    try {
        if (file_put_contents($temporary, $patched) !== strlen($patched)) throw new RuntimeException('Cannot write patch.');
        if (!chmod($temporary, fileperms($file) & 0777)) throw new RuntimeException('Cannot preserve file permissions.');
        if (!rename($temporary, $file)) throw new RuntimeException('Cannot replace dependency source.');
    } finally { if (is_file($temporary)) unlink($temporary); }
    echo "PASS: 12 nullable parameter declarations patched; dependency version unchanged.\n";
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
