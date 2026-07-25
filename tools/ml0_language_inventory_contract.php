<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$requiredSurfaces = [
    'index.php' => 'login_recovery_otp',
    'page/dashboard.php' => 'user_dashboard',
    'admin/dashboard.php' => 'admin_dashboard',
    'page/admin_step_up.php' => 'admin_step_up',
    'lib/q_func.php' => 'api_ajax',
    'app/Mail/OneIdEmailTemplate.php' => 'email',
    'app/Auth/AdminStepUpPhpMailerSender.php' => 'security_email',
];
$excludedParts = [
    '/.git/',
    '/vendor/',
    '/vendors/',
    '/node_modules/',
    '/tests/',
    '/tools/',
];
$entries = [];

$looksHumanFacing = static function (string $value): bool {
    $value = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if (strlen($value) < 3 || !preg_match('/[A-Za-z\x{00C0}-\x{024F}]/u', $value)) {
        return false;
    }
    if (preg_match('/^(?:[A-Z0-9_]{3,}|[a-z0-9_.\/-]+\.(?:php|js|css|png|jpg|json))$/', $value)) {
        return false;
    }
    if (preg_match('/^(?:SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP)\b/i', $value)) {
        return false;
    }
    return preg_match('/\s|[.!?:]/u', $value) === 1;
};

$addEntry = static function (
    string $file,
    int $line,
    string $channel,
    string $value
) use (&$entries, $looksHumanFacing): void {
    $value = preg_replace('/\s+/u', ' ', trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '';
    if (!$looksHumanFacing($value)) {
        return;
    }
    $entries[] = [
        'file' => $file,
        'line' => $line,
        'channel' => $channel,
        'text' => $value,
    ];
};

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || !in_array(strtolower($fileInfo->getExtension()), ['php', 'js'], true)) {
        continue;
    }
    $absolute = $fileInfo->getPathname();
    $relative = str_replace('\\', '/', substr($absolute, strlen($root) + 1));
    $wrapped = '/' . $relative;
    foreach ($excludedParts as $excludedPart) {
        if (str_contains($wrapped, $excludedPart)) {
            continue 2;
        }
    }
    $source = file_get_contents($absolute);
    if ($source === false) {
        fwrite(STDERR, "FAIL unreadable source: {$relative}\n");
        exit(1);
    }
    if (strtolower($fileInfo->getExtension()) === 'js') {
        foreach (preg_split('/\R/', $source) ?: [] as $index => $line) {
            preg_match_all('/([\'"])(.{3,}?)\1/', $line, $matches);
            foreach ($matches[2] ?? [] as $literal) {
                $addEntry($relative, $index + 1, 'javascript', $literal);
            }
        }
        continue;
    }
    foreach (token_get_all($source) as $token) {
        if (!is_array($token)) {
            continue;
        }
        [$type, $text, $line] = $token;
        if ($type === T_CONSTANT_ENCAPSED_STRING) {
            $quote = $text[0] ?? "'";
            $literal = substr($text, 1, -1);
            if ($quote === "'") {
                $literal = str_replace(["\\\\", "\\'"], ["\\", "'"], $literal);
            } else {
                $literal = stripcslashes($literal);
            }
            $addEntry($relative, $line, 'php_or_javascript', $literal);
        } elseif ($type === T_INLINE_HTML) {
            foreach (preg_split('/\R/', $text) ?: [] as $offset => $htmlLine) {
                $addEntry($relative, $line + $offset, 'html', $htmlLine);
                preg_match_all('/([\'"])(.{3,}?)\1/', $htmlLine, $matches);
                foreach ($matches[2] ?? [] as $literal) {
                    $addEntry($relative, $line + $offset, 'javascript_or_attribute', $literal);
                }
            }
        }
    }
}

usort($entries, static fn(array $a, array $b): int => [$a['file'], $a['line'], $a['text']] <=> [$b['file'], $b['line'], $b['text']]);
$deduplicated = [];
foreach ($entries as $entry) {
    $key = $entry['file'] . ':' . $entry['line'] . ':' . $entry['channel'] . ':' . $entry['text'];
    $deduplicated[$key] = $entry;
}
$entries = array_values($deduplicated);

$failures = 0;
$check = static function (bool $condition, string $label) use (&$failures): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$condition) {
        $failures++;
    }
};

foreach ($requiredSurfaces as $file => $surface) {
    $count = count(array_filter($entries, static fn(array $entry): bool => $entry['file'] === $file));
    $check(is_file($root . '/' . $file) && $count > 0, "inventory surface {$surface}: {$file} entries={$count}");
}

$inventoryDocument = file_get_contents($root . '/docs/ML0_LANGUAGE_INVENTORY_AND_COMPATIBILITY.md') ?: '';
$glossaryDocument = file_get_contents($root . '/docs/ML0_GLOSSARY_BM_EN_V1.md') ?: '';
$auditDocument = file_get_contents($root . '/docs/AUDIT_DAN_PELAN_PELAKSANAAN_MULTILANGUAGE_BM_ENGLISH.md') ?: '';

$check(count($entries) >= 400, 'per-location candidate inventory has expected coverage floor');
$check(str_contains($inventoryDocument, 'ONEID-ML0-20260725-01'), 'inventory evidence reference recorded');
$check(str_contains($inventoryDocument, 'stable response code') && str_contains($inventoryDocument, 'translation key'), 'legacy literal compatibility mapped');
$check(str_contains($glossaryDocument, 'FROZEN v1') && str_contains($glossaryDocument, 'Exact confirmation'), 'glossary v1 frozen with canonical boundary');
$check(str_contains($auditDocument, 'ML0: PASS / CLOSED'), 'audit records approved ML0 closure');
$check(str_contains(file_get_contents($root . '/lib/q_func.php') ?: '', "'msg'"), 'legacy msg retained during compatibility window');

$digest = hash('sha256', json_encode($entries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo 'INVENTORY candidates=' . count($entries) . ' digest=' . $digest . PHP_EOL;

if (in_array('--list', $argv, true)) {
    echo "file\tline\tchannel\ttext\n";
    foreach ($entries as $entry) {
        echo $entry['file'] . "\t" . $entry['line'] . "\t" . $entry['channel'] . "\t"
            . str_replace(["\t", "\n", "\r"], ' ', $entry['text']) . PHP_EOL;
    }
}

echo 'RESULT checks=' . (count($requiredSurfaces) + 6) . ' failed=' . $failures . PHP_EOL;
exit($failures === 0 ? 0 : 1);
