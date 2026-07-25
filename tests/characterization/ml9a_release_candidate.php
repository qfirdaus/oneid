<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$candidate = require $root . '/config/content/ml9a_v263_release_candidate.php';
$checks = 0;
$failed = 0;
$assert = static function (bool $condition, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$condition) {
        $failed++;
    }
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
};

$assert($candidate['version'] === '2.6.3', 'candidate is exact successor to v2.6.2');
$assert($candidate['status'] === 'REVIEW_REQUIRED', 'content remains unapproved');
$assert($candidate['automatic_approval'] === false, 'automatic approval disabled');
$assert(count($candidate['bm']) === 12 && count($candidate['en']) === 12, 'BM and English item parity 12/12');
$assert(count(array_filter($candidate['bm'])) === 12, 'BM items are complete');
$assert(count(array_filter($candidate['en'])) === 12, 'English items are complete');
$assert(
    'release:' . $candidate['version'] === 'release:2.6.3',
    'projected release identity follows the canonical release namespace'
);

echo "RESULT checks={$checks} failed={$failed}" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
