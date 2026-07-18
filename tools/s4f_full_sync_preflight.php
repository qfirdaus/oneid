<?php

/** Read-only effective full-sync configuration check. */
if (PHP_SAPI !== 'cli') { exit(2); }
$root=dirname(__DIR__);require_once $root.'/bootstrap/app.php';require_once $root.'/bootstrap/sync_runtime.php';
try {
    $runtime=\OneId\App\Sync\SyncRuntimeConfig::fromEnvironment();
    $pilot=\OneId\App\Sync\SyncPilotConfig::fromEnvironment();
    $full=\OneId\App\Sync\SyncFullConfig::fromEnvironment();
    printf("apply_enabled=%s engine=%s pilot_enabled=%s full_enabled=%s\n",$runtime->applyEnabled?'true':'false',$runtime->engine,$pilot->enabled?'true':'false',$full->enabled?'true':'false');
    printf("expected new=%d update=%d deactivate=%d reactivate=%d plan_hash=%s\n",$full->expectedCounts['New'],$full->expectedCounts['Update'],$full->expectedCounts['Deactivate'],$full->expectedCounts['Reactivate'],$full->expectedPlanHash===''?'-':substr($full->expectedPlanHash,0,12).'...');
    $ready=$runtime->canApply()&&$full->enabled&&!$pilot->enabled;
    printf("RESULT full_runtime_ready=%s mutation_statements=0\n",$ready?'yes':'no');
    exit($ready?0:1);
} catch(Throwable $e) {
    printf("RESULT full_runtime_ready=no code=%s mutation_statements=0\n",$e->getMessage());exit(1);
}
