<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$output = [];
$status = 1;
exec('php ' . escapeshellarg($root . '/tools/ml8c_english_changelog_preview.php'), $output, $status);
$result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
};
$report($status === 0 && $result['blocking_codes'] === [], 'English draft Preview is unblocked');
$report(
    $result['release_count'] === 38 && $result['item_count'] === 229,
    'all release and changelog identities have English drafts'
);
$report(
    $result['empty_items'] === 0
    && $result['source_mismatches'] === 0
    && $result['duplicate_identities'] === 0,
    'draft catalogue is complete and bound to canonical BM source'
);
$report(
    $result['html_mismatches'] === 0 && $result['code_token_mismatches'] === 0,
    'HTML and code tokens remain invariant'
);
$report(
    $result['review_status'] === 'REVIEW_REQUIRED'
    && $result['can_apply'] === false
    && $result['can_activate'] === false
    && $result['automatic_approval'] === false,
    'all draft content remains behind owner-review and activation gates'
);
$report(
    $result['mutation_statements'] === 0
    && preg_match('/\A[a-f0-9]{64}\z/', $result['manifest_digest']) === 1,
    'Preview is zero mutation with deterministic digest'
);

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
