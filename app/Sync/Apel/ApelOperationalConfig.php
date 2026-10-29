<?php
declare(strict_types=1);
namespace OneId\App\Sync\Apel;

final class ApelOperationalConfig
{
    private function __construct(
        public readonly bool $previewEnabled,
        public readonly bool $applyEnabled,
        public readonly bool $onDemandEnabled,
        public readonly bool $manualEnabled
    ) {}
    public static function fromPrivateRuntime():self{return self::fromValues(
        self::value('ONEID_APEL_OPERATIONAL_PREVIEW_ENABLED','false'),self::value('ONEID_APEL_OPERATIONAL_APPLY_ENABLED','false'),
        self::value('ONEID_APEL_OPERATIONAL_ON_DEMAND_ENABLED','false'),self::value('ONEID_APEL_MANUAL_OPERATIONAL_ENABLED','false'));}
    public static function fromValues(string$preview,string$apply,string$onDemand,string$manual):self{
        foreach([$preview,$apply,$onDemand,$manual]as$f)if(!in_array($f,['true','false'],true))throw new \RuntimeException('APEL_OPERATIONAL_FLAG_INVALID');
        if(($apply==='true'||$onDemand==='true'||$manual==='true')&&$preview!=='true')throw new \RuntimeException('APEL_OPERATIONAL_FLAG_COMBINATION_INVALID');
        if(count(array_filter([$apply,$onDemand,$manual],static fn(string$f):bool=>$f==='true'))>1)throw new \RuntimeException('APEL_OPERATIONAL_FLAG_COMBINATION_INVALID');
        return new self($preview==='true',$apply==='true',$onDemand==='true',$manual==='true');
    }
    public function assertPreviewEnabled():void{if(!$this->previewEnabled)throw new \RuntimeException('APEL_OPERATIONAL_PREVIEW_DISABLED');}
    public function assertApplyEnabled():void{if(!$this->canApply())throw new \RuntimeException('APEL_OPERATIONAL_APPLY_DISABLED');}
    public function canApply():bool{return $this->applyEnabled||$this->onDemandEnabled||$this->manualEnabled;}
    private static function value(string$key,string$default):string{try{return trim((string)\oneid_secret($key));}catch(\Throwable){return$default;}}
}
