<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$imageDirectory = $root . '/public/assetsM/images';
$tracked = [];
for ($number = 3; $number <= 7; $number++) {
    $filename = 'banner' . $number . '.png';
    $path = $imageDirectory . '/' . $filename;
    $exists = is_file($path);
    $size = $exists ? filesize($path) : false;
    $info = $exists ? @getimagesize($path) : false;
    $tracked[] = [
        'filename' => $filename,
        'exists' => $exists,
        'bytes' => $size === false ? 0 : $size,
        'width' => is_array($info) ? (int) ($info[0] ?? 0) : 0,
        'height' => is_array($info) ? (int) ($info[1] ?? 0) : 0,
        'mime' => is_array($info) ? (string) ($info['mime'] ?? '') : '',
        'sha256' => $exists ? hash_file('sha256', $path) : false,
    ];
}

$index = (string) file_get_contents($root . '/index.php');
$metrics = [
    'tracked_banner_candidates' => count($tracked),
    'existing_banner_candidates' => count(array_filter($tracked, static fn(array $item): bool => $item['exists'])),
    'active_markup_items' => str_contains($index, "\$bannerIndex === 0 ? ' active' : ''") ? 1 : 0,
    'live_markup_items' => preg_match_all("/'src' => 'assetsM\/images\/banner[0-9]+\.png'/", $index),
    'banner_management_schema_files' => count(glob($root . '/docs/migrations/*login_banner*.sql') ?: []),
];

echo "LB0 login banner audit (read-only)\n";
echo 'timestamp=' . date(DATE_ATOM) . "\n";
echo 'root=' . $root . "\n";
foreach ($metrics as $name => $value) {
    printf("METRIC %s=%d\n", $name, $value);
}
foreach ($tracked as $item) {
    printf(
        "ASSET filename=%s exists=%s bytes=%d dimensions=%dx%d mime=%s sha256=%s\n",
        $item['filename'],
        $item['exists'] ? 'yes' : 'no',
        $item['bytes'],
        $item['width'],
        $item['height'],
        $item['mime'],
        $item['sha256'] === false ? '-' : $item['sha256']
    );
}

echo "MUTATION statements=0\n";
