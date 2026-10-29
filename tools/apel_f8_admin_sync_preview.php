<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);require_once$root.'/lib/config.php';require_once$root.'/lib/Database.php';require_once$root.'/bootstrap/sync_runtime.php';
use OneId\App\Sync\Adapters\{DatabaseSyncPersistenceAdapter,InMemorySyncApprovalStore,LegacySyncPolicy,SourceScopedSyncPersistenceAdapter};
use OneId\App\Sync\Apel\{ApelOperationalConfig,ApelStudentSource};use OneId\App\Sync\{SyncApprovalService,SyncPlanFingerprinter,SyncPlanner,SyncPreviewService,SyncSafetyPolicy,SyncSourceScope};
try{$operation=new Database();$scope=SyncSourceScope::fromCode(ApelStudentSource::SOURCE_CODE);$persistence=new SourceScopedSyncPersistenceAdapter(new DatabaseSyncPersistenceAdapter($operation),$scope->categoryIds,fn():array=>$operation->sync_get_active_user_ids_by_source($scope->sourceCode),fn():array=>$operation->sync_get_inactive_user_ids_by_source($scope->sourceCode));
$preview=(new SyncPreviewService($scope->source,$persistence,new SyncPlanner(new LegacySyncPolicy(),true,true),300,5.0,new SyncSafetyPolicy(requiredSourceCode:$scope->sourceCode),fn(array$rows)=>$operation->sync_assert_source_snapshot_isolated($rows,$scope->sourceCode)))->previewForApproval('F8-READONLY',$scope->baselineRows,new SyncApprovalService(new InMemorySyncApprovalStore(),new SyncPlanFingerprinter()));
$gate=ApelOperationalConfig::fromPrivateRuntime();$preview['source_code']=$scope->sourceCode;$preview['operational_apply_available']=$gate->canApply()&&($preview['approval_ready']??false)===true&&array_sum($preview['counts']??[])>0;$preview['cron_enabled']=false;$preview['mutation_statements']=0;if(!$preview['operational_apply_available'])unset($preview['approval_id']);
echo json_encode($preview,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
}catch(Throwable$e){printf("RESULT ready=no code=%s can_apply=false cron_enabled=false mutation_statements=0\n",preg_replace('/[^A-Z0-9_]/','',$e->getMessage())?:'APEL_F8_ADMIN_PREVIEW_FAILED');exit(1);}
