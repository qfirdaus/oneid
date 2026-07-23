<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/lib/readonly_odbc.php';
require_once dirname(__DIR__) . '/bootstrap/sync_runtime.php';

use OneId\App\Sync\Adapters\PdoOdlPilotPersistenceAdapter;
use OneId\App\Sync\Adapters\SecureInitialPasswordFactory;
use OneId\App\Sync\Contracts\SyncApprovalStoreInterface;
use OneId\App\Sync\DTO\SyncApproval;
use OneId\App\Sync\Odl\OdlPilotConfig;
use OneId\App\Sync\Odl\OdlPilotPlanner;
use OneId\App\Sync\Odl\OdlPilotWriter;
use OneId\App\Sync\Odl\OdlShadowPreviewReader;
use OneId\App\Sync\Odl\OdlSourceConfig;
use OneId\App\Sync\Odl\OdlStudentSource;
use OneId\App\Sync\SyncApprovalService;
use OneId\App\Sync\SyncPlanFingerprinter;

const CHANGE_ID = 'ONEID-ODL-F7-20260723-01';
const BACKUP_REFERENCE = 'ONEID-UAT-BACKUP-20260723-01';
const EXPECTED_PLAN_HASH =
    'afaa7e87a2bfc1029b5f849a4eceb97f4dbb93ccc399fd24b923e915b4802b25';
const EXPECTED_PREVIEW_DIGEST =
    '496a8e71c8089e90087f2b5d63aaf98352f0d9915993b5840e211590e79d29f4';
const WINDOW_START = '2026-07-23 22:00:00 Asia/Kuala_Lumpur';
const WINDOW_END = '2026-07-23 22:30:00 Asia/Kuala_Lumpur';

/**
 * This intentionally process-local store makes the approval single-use and
 * impossible to carry into a later CLI invocation.
 */
final class OdlF7ProcessApprovalStore implements SyncApprovalStoreInterface
{
    private ?SyncApproval $approval = null;

    public function save(SyncApproval $approval): void
    {
        if ($this->approval !== null) {
            throw new RuntimeException('ODL_F7_APPROVAL_ALREADY_ISSUED');
        }
        $this->approval = $approval;
    }

    public function consume(string $approvalId): ?SyncApproval
    {
        $approval = $this->approval;
        $this->approval = null;
        return $approval?->approvalId === $approvalId ? $approval : null;
    }
}

try {
    if ((getenv('ONEID_ODL_F7_EXECUTION') ?: '')
        !== 'AUTHORIZE_F7_PILOT_APPLY'
        || (getenv('ONEID_ODL_F7_CHANGE_ID') ?: '') !== CHANGE_ID
        || (getenv('ONEID_ODL_F7_BACKUP_REFERENCE') ?: '')
            !== BACKUP_REFERENCE
    ) {
        throw new RuntimeException('ODL_F7_EXECUTION_AUTHORIZATION_REQUIRED');
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur'));
    $start = new DateTimeImmutable(WINDOW_START);
    $end = new DateTimeImmutable(WINDOW_END);
    if ($now < $start || $now > $end) {
        throw new RuntimeException('ODL_F7_OUTSIDE_CHANGE_WINDOW');
    }

    $config = OdlPilotConfig::fromPrivateRuntime();
    if (!$config->previewEnabled || !$config->applyEnabled) {
        throw new RuntimeException('ODL_PILOT_APPLY_NOT_AUTHORIZED');
    }

    $pdo = new PDO(
        DB_DSN,
        DB_USERNAME,
        DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    $source = new OdlStudentSource(OdlSourceConfig::fromPrivateRuntime());
    $reader = new OdlShadowPreviewReader($pdo);
    $planner = new OdlPilotPlanner($config->allowedIdentityDigests);
    $freshPlan = static fn() => $planner->plan(
        $source->fetchAll(),
        $reader->users(),
        $reader->memberships()
    );

    $plan = $freshPlan();
    if (!hash_equals(EXPECTED_PLAN_HASH, $plan->planHash())) {
        throw new RuntimeException('ODL_F7_PLAN_HASH_MISMATCH');
    }
    $preview = [
        'status' => 1,
        'mode' => 'odl_controlled_pilot_preview',
        'can_apply' => false,
        'execution_authorized' => false,
        'counts' => $plan->legacyCounts(),
        'source_rows' => $plan->sourceRows,
        'plan_hash' => $plan->planHash(),
        'actions' => $plan->safeProjection(),
        'mutation_statements' => 0,
    ];
    $preview['preview_digest'] = hash(
        'sha256',
        json_encode($preview, JSON_UNESCAPED_SLASHES) ?: ''
    );
    if (!hash_equals(EXPECTED_PREVIEW_DIGEST, $preview['preview_digest'])) {
        throw new RuntimeException('ODL_F7_PREVIEW_DIGEST_MISMATCH');
    }

    $approval = new SyncApprovalService(
        new OdlF7ProcessApprovalStore(),
        new SyncPlanFingerprinter(),
        300
    );
    $receipt = $approval->issue('firdaus', $plan, 53);
    $writer = new OdlPilotWriter(
        new PdoOdlPilotPersistenceAdapter($pdo),
        new SecureInitialPasswordFactory()
    );
    $result = $writer->applyApproved(
        $freshPlan,
        $receipt->approvalId,
        'firdaus',
        $approval,
        true
    );
    echo json_encode(
        [
            'status' => 1,
            'mode' => 'odl_controlled_pilot_apply',
            'change_id' => CHANGE_ID,
            'backup_reference' => BACKUP_REFERENCE,
            'plan_hash' => EXPECTED_PLAN_HASH,
            'preview_digest' => EXPECTED_PREVIEW_DIGEST,
            'result' => $result,
            'automatic_scheduler' => false,
            'full_apply' => false,
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ), PHP_EOL;
} catch (Throwable $exception) {
    printf(
        "RESULT applied=no code=%s automatic_scheduler=false full_apply=false\n",
        preg_replace('/[^A-Z0-9_]/', '', $exception->getMessage())
            ?: 'ODL_F7_APPLY_FAILED'
    );
    exit(1);
}
