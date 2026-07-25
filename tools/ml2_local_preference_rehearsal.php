<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/app/Locale/LocaleResolver.php';
require_once dirname(__DIR__) . '/app/Locale/PdoLocalePreferenceRepository.php';

use OneId\App\Locale\PdoLocalePreferenceRepository;

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$userId = 'ML2-' . strtoupper(bin2hex(random_bytes(5)));
$repository = new PdoLocalePreferenceRepository($pdo);
$pdo->beginTransaction();
try {
    $repository->save($userId, 'en');
    $stored = $repository->find($userId);
    $invalidRejected = false;
    try {
        $repository->save($userId, '../en');
    } catch (InvalidArgumentException $exception) {
        $invalidRejected = $exception->getMessage() === 'LOCALE_NOT_ALLOWED';
    }
    if ($stored !== 'en' || !$invalidRejected) {
        throw new RuntimeException('ML2_PREFERENCE_REHEARSAL_FAILED');
    }
    echo "PASS authenticated preference save/read locale=en invalid_rejected=yes\n";
} finally {
    $pdo->rollBack();
}
$remaining = $pdo->prepare('SELECT COUNT(*) FROM user_locale_preference WHERE u_id=:u_id');
$remaining->execute([':u_id' => $userId]);
if ((int) $remaining->fetchColumn() !== 0) {
    throw new RuntimeException('ML2_PREFERENCE_REHEARSAL_ROLLBACK_FAILED');
}
echo "PASS transaction rollback preference_rows=0\n";
echo "RESULT checks=2 failed=0 persistent_mutations=0\n";
