<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$document = (string) file_get_contents($root . '/docs/F7_6_UAT_CONTROLLED_ROLLOUT_DAN_OBSERVATION.md');
$snapshot = (string) file_get_contents($root . '/tools/f7_6_uat_snapshot.php');
$checks = [
    'single_owner_scope' => str_contains($document, 'admin kedua tidak terlibat')
        && str_contains($document, 'tidak dibawa terus ke production'),
    'observation_window' => str_contains($document, '27.90')
        && str_contains($document, 'continuous')
        && str_contains($document, 'operational monitoring'),
    'functional_evidence' => str_contains($document, 'QR berjenama UPNM')
        && str_contains($document, 'Preference per-admin')
        && str_contains($document, 'Session/CSRF rotation'),
    'rollback_order' => str_contains($document, 'admin_2fa_enabled=0')
        && str_contains($document, 'feature rollback, bukan pemadaman keyring'),
    'final_gate' => str_contains($document, 'f7_6_uat_snapshot.php --final')
        && str_contains($document, 'ACCEPTED / CLOSED'),
    'snapshot_read_only' => !preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|CREATE)\s+(?:INTO|TABLE|DATABASE|FROM|sys_config|admin_)/i', $snapshot),
    'snapshot_secret_safe' => !str_contains($snapshot, 'encrypted_secret')
        && !str_contains($snapshot, 'secret_nonce')
        && str_contains($snapshot, 'audit_secret_hygiene'),
    'snapshot_final_fails_early' => str_contains($snapshot, '$finalGate && !$observationComplete'),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
printf("F7_6_DOCUMENT checks=%d passed=%d\n", count($checks), count($checks) - count($failed));
if ($failed !== []) {
    fwrite(STDERR, 'FAIL ' . implode(',', $failed) . "\n");
    exit(1);
}
echo "PASS FUNCTIONAL_UAT_CONTROLLED_OBSERVATION_ROLLBACK_GATES\n";
