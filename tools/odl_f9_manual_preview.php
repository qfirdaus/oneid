<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require_once dirname(__DIR__).'/lib/config.php';
require_once dirname(__DIR__).'/bootstrap/sync_runtime.php';

use OneId\App\Sync\Adapters\DatabaseSyncPersistenceAdapter;
use OneId\App\Sync\Adapters\LegacySyncPolicy;
use OneId\App\Sync\Adapters\SourceScopedSyncPersistenceAdapter;
use OneId\App\Sync\Contracts\SyncApprovalStoreInterface;
use OneId\App\Sync\DTO\SyncApproval;
use OneId\App\Sync\Odl\OdlOperationalConfig;
use OneId\App\Sync\Odl\OdlStudentSource;
use OneId\App\Sync\SyncApprovalService;
use OneId\App\Sync\SyncPlanFingerprinter;
use OneId\App\Sync\SyncPlanner;
use OneId\App\Sync\SyncPreviewService;
use OneId\App\Sync\SyncSafetyPolicy;
use OneId\App\Sync\SyncSourceScope;

try{
 $gate=OdlOperationalConfig::fromPrivateRuntime();$gate->assertPreviewEnabled();
 $scope=SyncSourceScope::fromCode(OdlStudentSource::SOURCE_CODE);
 $persistence=new SourceScopedSyncPersistenceAdapter(
  new DatabaseSyncPersistenceAdapter($operation),[10],
  fn():array=>$operation->sync_get_active_user_ids_by_source($scope->sourceCode),
  fn():array=>$operation->sync_get_inactive_user_ids_by_source($scope->sourceCode)
 );
 $store=new class implements SyncApprovalStoreInterface{
  public function save(SyncApproval$approval):void{}
  public function consume(string$approvalId):?SyncApproval{return null;}
 };
 $service=new SyncPreviewService(
  $scope->source,$persistence,
  new SyncPlanner(new LegacySyncPolicy(),true),300,5.0,
  new SyncSafetyPolicy(requiredSourceCode:$scope->sourceCode),
  fn(array$rows)=>$operation->sync_assert_source_snapshot_isolated(
   $rows,$scope->sourceCode
  )
 );
 $preview=$service->previewForApproval(
  'F9_PREVIEW',$scope->baselineRows,
  new SyncApprovalService($store,new SyncPlanFingerprinter())
 );
 unset($preview['approval_id'],$preview['sample']);
 $preview['source_code']=$scope->sourceCode;
 $preview['can_apply']=$gate->applyEnabled&&($preview['approval_ready']??false);
 $preview['apply_enabled']=$gate->applyEnabled;
 $preview['automatic_scheduler']=false;
 $preview['mutation_statements']=0;
 echo json_encode($preview,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
}catch(Throwable$e){
 printf("RESULT preview=no code=%s apply=false scheduler=false mutation=0\n",
  preg_replace('/[^A-Z0-9_]/','',$e->getMessage())?:'ODL_F9_PREVIEW_FAILED');
 exit(1);
}
