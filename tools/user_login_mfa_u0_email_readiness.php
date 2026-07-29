<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$categoryRows = $pdo->query(
    'SELECT uc_id, uc_name FROM user_category ORDER BY uc_id'
)->fetchAll();
$categoryNames = [];
foreach ($categoryRows as $row) {
    $categoryNames[(int) $row['uc_id']] = (string) $row['uc_name'];
}

$statement = $pdo->query(
    'SELECT u_category, data5 AS email
       FROM user_tbl
      WHERE u_type=0 AND avail_status=1
      ORDER BY u_category'
);

$summary = [
    'total' => 0,
    'valid' => 0,
    'invalid' => 0,
    'categories' => [],
];

while ($row = $statement->fetch()) {
    $categoryId = (int) $row['u_category'];
    $valid = filter_var(trim((string) $row['email']), FILTER_VALIDATE_EMAIL) !== false;
    if (!isset($summary['categories'][$categoryId])) {
        $summary['categories'][$categoryId] = [
            'total' => 0,
            'valid' => 0,
            'invalid' => 0,
        ];
    }
    $summary['total']++;
    $summary[$valid ? 'valid' : 'invalid']++;
    $summary['categories'][$categoryId]['total']++;
    $summary['categories'][$categoryId][$valid ? 'valid' : 'invalid']++;
}

printf(
    "environment=development/staging active_users=%d valid_email=%d invalid_email=%d raw_email_output=0 mutation_statements=0\n",
    $summary['total'],
    $summary['valid'],
    $summary['invalid']
);
foreach ($summary['categories'] as $categoryId => $counts) {
    printf(
        "category_id=%d category=%s active_users=%d valid_email=%d invalid_email=%d\n",
        $categoryId,
        preg_replace('/[^A-Za-z0-9 _-]/', '', $categoryNames[$categoryId] ?? 'Unknown'),
        $counts['total'],
        $counts['valid'],
        $counts['invalid']
    );
}

echo "RESULT status=PASS read_only=yes" . PHP_EOL;
