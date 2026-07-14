<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/config.php';

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$summarySql = "SELECT g.sp_group_id,g.sp_group_name,g.sp_group_seq,
                      SUM(CASE WHEN s.avail_status=1 THEN 1 ELSE 0 END) AS active_apps,
                      SUM(CASE WHEN s.avail_status=0 THEN 1 ELSE 0 END) AS inactive_apps,
                      COUNT(s.sp_id) AS total_assigned
               FROM sp_group g
               LEFT JOIN sp_list s ON s.sp_group_id=g.sp_group_id
               GROUP BY g.sp_group_id,g.sp_group_name,g.sp_group_seq
               ORDER BY g.sp_group_seq DESC,g.sp_group_id";

echo "W0 web-app category audit (read-only)\n";
foreach ($pdo->query($summarySql, PDO::FETCH_ASSOC) as $row) {
    printf(
        "GROUP id=%s name=%s active=%d inactive=%d assigned=%d\n",
        $row['sp_group_id'],
        $row['sp_group_name'],
        $row['active_apps'],
        $row['inactive_apps'],
        $row['total_assigned']
    );
}

$scalar = static function (PDO $pdo, string $sql): int {
    return (int) $pdo->query($sql)->fetchColumn();
};

$metrics = [
    'orphan_group_refs' => $scalar($pdo, "SELECT COUNT(*) FROM sp_list s LEFT JOIN sp_group g ON g.sp_group_id=s.sp_group_id WHERE g.sp_group_id IS NULL"),
    'orphan_active_refs' => $scalar($pdo, "SELECT COUNT(*) FROM sp_list s LEFT JOIN sp_group g ON g.sp_group_id=s.sp_group_id WHERE g.sp_group_id IS NULL AND s.avail_status=1"),
    'inactive_apps' => $scalar($pdo, "SELECT COUNT(*) FROM sp_list WHERE avail_status=0"),
    'inactive_direct_acl' => $scalar($pdo, "SELECT COUNT(*) FROM acl_single a JOIN sp_list s ON s.sp_id=a.sp_id WHERE s.avail_status=0"),
    'inactive_group_acl' => $scalar($pdo, "SELECT COUNT(*) FROM acl_group a JOIN sp_list s ON s.sp_id=a.sp_id WHERE s.avail_status=0"),
    'inactive_deny_acl' => $scalar($pdo, "SELECT COUNT(*) FROM acl_blacklist a JOIN sp_list s ON s.sp_id=a.sp_id WHERE s.avail_status=0"),
    'truly_empty_non_system_categories' => $scalar($pdo, "SELECT COUNT(*) FROM sp_group g WHERE g.sp_group_id<>0 AND NOT EXISTS (SELECT 1 FROM sp_list s WHERE s.sp_group_id=g.sp_group_id)"),
];
foreach ($metrics as $name => $value) {
    printf("METRIC %s=%d\n", $name, $value);
}

$defaultExists = $scalar($pdo, "SELECT COUNT(*) FROM sp_group WHERE sp_group_id=0") === 1;
printf("%s default category ID 0 exists\n", $defaultExists ? 'PASS' : 'FAIL');
exit($defaultExists ? 0 : 1);
