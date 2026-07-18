<?php

if (PHP_SAPI !== 'cli') { exit(2); }
require_once dirname(__DIR__).'/bootstrap/app.php';
require_once dirname(__DIR__).'/bootstrap/sync_runtime.php';
try {
    $runtime=\OneId\App\Sync\SyncRuntimeConfig::fromEnvironment();
    $pilot=\OneId\App\Sync\SyncPilotConfig::fromEnvironment();
    $full=\OneId\App\Sync\SyncFullConfig::fromEnvironment();
    $operational=\OneId\App\Sync\SyncOperationalConfig::fromEnvironment();
    $exclusive=(int)$pilot->enabled+(int)$full->enabled+(int)$operational->enabled===1;
    $ready=$runtime->canApply()&&$operational->enabled&&!$pilot->enabled&&!$full->enabled&&$exclusive;
    printf("apply_enabled=%s engine=%s pilot_enabled=%s full_enabled=%s operational_enabled=%s\n",$runtime->applyEnabled?'true':'false',$runtime->engine,$pilot->enabled?'true':'false',$full->enabled?'true':'false',$operational->enabled?'true':'false');
    printf("thresholds warn_new=%d warn_update=%d warn_reactivate=%d warn_total=%d max_deactivate=%d\n",$operational->warnNew,$operational->warnUpdate,$operational->warnReactivate,$operational->warnTotal,$operational->maxDeactivate);
    printf("RESULT operational_runtime_ready=%s mutation_statements=0\n",$ready?'yes':'no');
    exit($ready?0:1);
} catch(Throwable $e) {
    printf("RESULT operational_runtime_ready=no code=%s mutation_statements=0\n",$e->getMessage());exit(1);
}
