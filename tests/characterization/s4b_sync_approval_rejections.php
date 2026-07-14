<?php

/** S4B in-memory approval fixture. No session, database, source or HTTP I/O. */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$root = dirname(__DIR__, 2);
foreach ([
    'app/Sync/Contracts/SyncApprovalStoreInterface.php',
    'app/Sync/Contracts/SyncPlanApprovalGateInterface.php',
    'app/Sync/DTO/SyncPlan.php',
    'app/Sync/DTO/SyncApproval.php',
    'app/Sync/DTO/SyncApprovalReceipt.php',
    'app/Sync/SyncPlanFingerprinter.php',
    'app/Sync/SyncApprovalService.php',
    'app/Sync/Adapters/SessionSyncApprovalStore.php',
] as $file) {
    require_once $root . '/' . $file;
}

use OneId\App\Sync\Contracts\SyncApprovalStoreInterface;
use OneId\App\Sync\DTO\SyncApproval;
use OneId\App\Sync\DTO\SyncPlan;
use OneId\App\Sync\SyncApprovalService;
use OneId\App\Sync\SyncPlanFingerprinter;
use OneId\App\Sync\Adapters\SessionSyncApprovalStore;

final class S4BMemoryStore implements SyncApprovalStoreInterface
{
    /** @var array<string, SyncApproval> */
    public array $records = [];
    public int $saveCalls = 0;
    public int $consumeCalls = 0;

    public function save(SyncApproval $approval): void
    {
        $this->saveCalls++;
        $this->records[$approval->approvalId] = $approval;
    }

    public function consume(string $approvalId): ?SyncApproval
    {
        $this->consumeCalls++;
        $approval = $this->records[$approvalId] ?? null;
        unset($this->records[$approvalId]);
        return $approval;
    }
}

function s4b_action(string $action, string $userId, string $changeHash): array
{
    return [
        'action' => $action,
        'u_id' => $userId,
        'row' => [
            'data1' => 'Sensitive Person',
            'data4' => $userId,
        ],
        'old_data' => null,
        'new_data' => null,
        'changed_fields' => $action === 'UPDATE' ? 'data1' : null,
        'category_id' => $action === 'NEW' ? 3 : null,
        'change_hash' => $changeHash,
    ];
}

function s4b_plan(bool $reverse = false, string $newHash = 'hash-new'): SyncPlan
{
    $actions = [
        s4b_action('NEW', 'RAW-USER-NEW', $newHash),
        s4b_action('UPDATE', 'RAW-USER-UPDATE', 'hash-update'),
    ];
    if ($reverse) {
        $actions = array_reverse($actions);
    }
    return new SyncPlan($actions, 6485, 0, 0, [], 1, 0);
}

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) $failed++;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};

$fingerprinter = new SyncPlanFingerprinter();
$forwardFingerprint = $fingerprinter->fingerprint(s4b_plan());
$reverseFingerprint = $fingerprinter->fingerprint(s4b_plan(true));
$report($forwardFingerprint === $reverseFingerprint, 'canonical fingerprint ignores upstream action order');
$report(preg_match('/^[a-f0-9]{64}$/', $forwardFingerprint) === 1, 'canonical fingerprint has SHA-256 format');
$report($forwardFingerprint !== $fingerprinter->fingerprint(s4b_plan(false, 'changed-hash')), 'material plan change changes fingerprint');

$store = new S4BMemoryStore();
$service = new SyncApprovalService($store, $fingerprinter, 300);
$receipt = $service->issue('0530-09', s4b_plan(), 6485, 1_000_000);
$safeJson = json_encode($receipt->toSafeArray());
$report($store->saveCalls === 1 && count($store->records) === 1, 'approval is stored server-side');
$report(preg_match('/^[a-f0-9]{64}$/', $receipt->approvalId) === 1, 'approval ID is cryptographically shaped');
$report(preg_match('/^[a-f0-9]{16}$/', $receipt->correlationId) === 1, 'separate safe correlation ID is issued');
$report($receipt->expiresAt - $receipt->issuedAt === 300, 'approval expiry is capped at five minutes');
$report(!str_contains($safeJson, '0530-09'), 'safe receipt excludes bound admin ID');
$report(!str_contains($safeJson, 'RAW-USER') && !str_contains($safeJson, 'Sensitive Person'), 'safe receipt excludes raw plan PII');
$pendingApproval = array_values($store->records)[0];
try {
    (new SessionSyncApprovalStore())->save($pendingApproval);
    $sessionRequired = false;
} catch (RuntimeException $exception) {
    $sessionRequired = $exception->getMessage() === 'SYNC_APPROVAL_SESSION_REQUIRED';
}
$report($sessionRequired, 'production session store refuses use without active server session');

$validated = $service->consumeAndValidate($receipt->approvalId, '0530-09', s4b_plan(), 1_000_100);
$report($validated->acceptedBaseline === 6485, 'matching admin and canonical plan authorize once');
$report($store->records === [], 'successful validation consumes approval');
try {
    $service->consumeAndValidate($receipt->approvalId, '0530-09', s4b_plan(), 1_000_101);
    $replayBlocked = false;
} catch (RuntimeException $exception) {
    $replayBlocked = $exception->getMessage() === 'SYNC_APPROVAL_NOT_AVAILABLE';
}
$report($replayBlocked, 'replay is rejected');

$wrongAdmin = $service->issue('0530-09', s4b_plan(), 6485, 2_000_000);
try {
    $service->consumeAndValidate($wrongAdmin->approvalId, 'OTHER-ADMIN', s4b_plan(), 2_000_001);
    $wrongAdminBlocked = false;
} catch (RuntimeException $exception) {
    $wrongAdminBlocked = $exception->getMessage() === 'SYNC_APPROVAL_ADMIN_MISMATCH';
}
$report($wrongAdminBlocked, 'wrong admin is rejected');
$report(!isset($store->records[$wrongAdmin->approvalId]), 'wrong-admin attempt burns approval');

$expired = $service->issue('0530-09', s4b_plan(), 6485, 3_000_000);
try {
    $service->consumeAndValidate($expired->approvalId, '0530-09', s4b_plan(), 3_000_300);
    $expiredBlocked = false;
} catch (RuntimeException $exception) {
    $expiredBlocked = $exception->getMessage() === 'SYNC_APPROVAL_EXPIRED';
}
$report($expiredBlocked, 'approval expires at the exact expiry boundary');
$report(!isset($store->records[$expired->approvalId]), 'expired attempt burns approval');

$mismatch = $service->issue('0530-09', s4b_plan(), 6485, 4_000_000);
try {
    $service->consumeAndValidate(
        $mismatch->approvalId,
        '0530-09',
        s4b_plan(false, 'changed-hash'),
        4_000_001
    );
    $mismatchBlocked = false;
} catch (RuntimeException $exception) {
    $mismatchBlocked = $exception->getMessage() === 'SYNC_APPROVAL_PLAN_MISMATCH';
}
$report($mismatchBlocked, 'plan mismatch is rejected');
$report(!isset($store->records[$mismatch->approvalId]), 'plan mismatch burns approval');

$consumeCallsBeforeInvalid = $store->consumeCalls;
try {
    $service->consumeAndValidate('not-an-approval', '0530-09', s4b_plan(), 5_000_000);
    $invalidBlocked = false;
} catch (RuntimeException $exception) {
    $invalidBlocked = $exception->getMessage() === 'SYNC_APPROVAL_INVALID';
}
$report($invalidBlocked, 'malformed approval ID is rejected');
$report($store->consumeCalls === $consumeCallsBeforeInvalid, 'malformed ID never reaches server store');

try {
    new SyncApprovalService($store, $fingerprinter, 301);
    $ttlBlocked = false;
} catch (RuntimeException $exception) {
    $ttlBlocked = $exception->getMessage() === 'SYNC_APPROVAL_TTL_INVALID';
}
$report($ttlBlocked, 'TTL above five minutes is rejected');

try {
    $service->issue('', s4b_plan(), 6485, 6_000_000);
    $adminBlocked = false;
} catch (RuntimeException $exception) {
    $adminBlocked = $exception->getMessage() === 'SYNC_APPROVAL_ADMIN_INVALID';
}
$report($adminBlocked, 'empty admin binding is rejected');
try {
    $service->issue('0530-09', s4b_plan(), 0, 6_000_000);
    $baselineBlocked = false;
} catch (RuntimeException $exception) {
    $baselineBlocked = $exception->getMessage() === 'SYNC_APPROVAL_BASELINE_INVALID';
}
$report($baselineBlocked, 'missing accepted source baseline is rejected');

$serviceSource = (string) file_get_contents($root . '/app/Sync/SyncApprovalService.php');
$report(!str_contains($serviceSource, 'SyncPersistenceInterface') && !str_contains($serviceSource, 'Database'), 'approval service has no application persistence dependency');
$report(!str_contains($serviceSource, 'begin(') && !str_contains($serviceSource, 'commit('), 'all approval rejection paths have zero database transaction capability');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
