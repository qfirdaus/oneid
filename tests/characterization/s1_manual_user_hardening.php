<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

require_once dirname(__DIR__, 2) . '/lib/auth_security.php';
require_once dirname(__DIR__, 2) . '/app/Sync/SyncDataTransformer.php';
require_once dirname(__DIR__, 2) . '/app/User/ManualUserInput.php';
require_once dirname(__DIR__, 2) . '/app/User/ManualUserCreator.php';

use OneId\App\User\ManualUserCreator;
use OneId\App\User\ManualUserInput;

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $item) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $item);
};

function s1_valid_manual_post(array $overrides = []): array
{
    return array_merge([
        'add_new_manual_user_id' => '0530-09',
        'add_new_manual_user_category' => '3',
        'add_new_manual_user_name' => 'Fixture User',
        'add_new_user_data2' => 'EMP-0530',
        'add_new_user_data3' => 'STAFF-0530',
        'add_new_user_data5' => 'fixture.user@example.edu.my',
        'add_new_user_data6' => 'Fixture Department',
        'add_new_user_data7' => 'Fixture Position',
        'add_new_user_data8' => '',
        'add_new_user_data9' => '',
        'add_new_user_data10' => '',
        'add_new_user_data11' => '',
        'add_new_user_data12' => '',
    ], $overrides);
}

final class S1ManualFakeOperation
{
    public bool $provenance = true;
    public bool $existing = false;
    public bool $activeCategory = true;
    public bool $began = false;
    public bool $committed = false;
    public int $rollbacks = 0;
    public array $insertArguments = [];
    public array $auditArguments = [];
    public ?Throwable $insertException = null;

    public function supportsUserProvenance(): bool { return $this->provenance; }
    public function beginTransaction(): void { $this->began = true; }
    public function commit(): void { $this->committed = true; }
    public function rollback(): void { $this->rollbacks++; }
    public function get_specific_user_info(string $userId): array { return $this->existing ? ['u_id' => $userId] : []; }
    public function isActiveUserCategory(int $categoryId): bool { return $this->activeCategory; }
    public function action_add_new_user(...$arguments): int
    {
        if ($this->insertException !== null) {
            throw $this->insertException;
        }
        $this->insertArguments = $arguments;
        return 1;
    }
    public function syslog_record(...$arguments): int
    {
        $this->auditArguments = $arguments;
        return 1;
    }
}

$input = ManualUserInput::fromPost(s1_valid_manual_post());
$report($input->userId === '0530-09' && $input->categoryId === 3, 'valid input normalized');
$report($input->data['data4'] === '0530-09', 'data4 is canonical user ID');
$report(strlen($input->changeHash()) === 64, 'canonical manual change hash generated');
$report($input->changeHash() === ManualUserInput::fromPost(s1_valid_manual_post())->changeHash(), 'manual hash deterministic');

$invalidCases = [
    'blank ID' => ['add_new_manual_user_id' => ''],
    'oversized ID' => ['add_new_manual_user_id' => str_repeat('A', 21)],
    'invalid ID characters' => ['add_new_manual_user_id' => 'bad/id'],
    'blank name' => ['add_new_manual_user_name' => ''],
    'invalid category' => ['add_new_manual_user_category' => 'x'],
    'blank email' => ['add_new_user_data5' => ''],
    'invalid email' => ['add_new_user_data5' => 'not-an-email'],
    'oversized field' => ['add_new_user_data6' => str_repeat('X', 101)],
    'markup payload' => ['add_new_manual_user_name' => '<script>fixture</script>'],
];
foreach ($invalidCases as $label => $override) {
    $rejected = false;
    try {
        ManualUserInput::fromPost(s1_valid_manual_post($override));
    } catch (InvalidArgumentException) {
        $rejected = true;
    }
    $report($rejected, 'validation rejects ' . $label);
}

$operation = new S1ManualFakeOperation();
$result = (new ManualUserCreator($operation))->create($input, 'admin-fixture', '127.0.0.1');
$report($result['status'] === 1 && $result['code'] === 'CREATED', 'successful creation response');
$report($operation->began && $operation->committed && $operation->rollbacks === 0, 'successful creation commits transaction');
$report(count($operation->insertArguments) === 18, 'complete persistence argument contract');
$report($operation->insertArguments[16] === 'manual' && $operation->insertArguments[17] === 1, 'manual provenance and protection persisted');
$report(password_get_info($operation->insertArguments[2])['algoName'] !== 'unknown', 'initial password is modern hash');
$report($operation->auditArguments[0] === 23, 'audit event 23 is inside successful flow');

$noSchemaOperation = new S1ManualFakeOperation();
$noSchemaOperation->provenance = false;
$noSchemaResult = (new ManualUserCreator($noSchemaOperation))->create($input, 'admin-fixture', '127.0.0.1');
$report($noSchemaResult['code'] === 'PROVENANCE_MIGRATION_REQUIRED', 'missing migration fails closed');
$report(!$noSchemaOperation->began && $noSchemaOperation->insertArguments === [], 'missing migration performs zero mutation');

$duplicateOperation = new S1ManualFakeOperation();
$duplicateOperation->existing = true;
$duplicateResult = (new ManualUserCreator($duplicateOperation))->create($input, 'admin-fixture', '127.0.0.1');
$report($duplicateResult['code'] === 'USER_ID_EXISTS', 'existing user rejected');
$report($duplicateOperation->rollbacks === 1 && !$duplicateOperation->committed, 'existing user rolls back');

$categoryOperation = new S1ManualFakeOperation();
$categoryOperation->activeCategory = false;
$categoryResult = (new ManualUserCreator($categoryOperation))->create($input, 'admin-fixture', '127.0.0.1');
$report($categoryResult['code'] === 'INVALID_CATEGORY', 'inactive category rejected');
$report($categoryOperation->rollbacks === 1 && $categoryOperation->insertArguments === [], 'invalid category rolls back before insert');

$failureOperation = new S1ManualFakeOperation();
$failureOperation->insertException = new RuntimeException('sensitive fixture detail');
$failureResult = (new ManualUserCreator($failureOperation))->create($input, 'admin-fixture', '127.0.0.1');
$report($failureResult['code'] === 'CREATE_FAILED' && !str_contains($failureResult['msg'], 'sensitive'), 'failure response hides exception detail');
$report($failureOperation->rollbacks === 1 && !$failureOperation->committed, 'unexpected insert failure rolls back');

$pdoException = new PDOException('duplicate fixture', 23000);
$pdoException->errorInfo = ['23000', 1062, 'duplicate fixture'];
$raceOperation = new S1ManualFakeOperation();
$raceOperation->insertException = $pdoException;
$raceResult = (new ManualUserCreator($raceOperation))->create($input, 'admin-fixture', '127.0.0.1');
$report($raceResult['code'] === 'USER_ID_EXISTS', 'duplicate-key race gets safe response');
$report($raceOperation->rollbacks === 1, 'duplicate-key race rolls back');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
