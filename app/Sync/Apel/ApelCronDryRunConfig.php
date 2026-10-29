<?php
declare(strict_types=1);
namespace OneId\App\Sync\Apel;

final class ApelCronDryRunConfig
{
    private function __construct(public readonly bool$enabled,public readonly bool$dryRun){}
    public static function fromPrivateRuntime():self{return self::fromValues(self::value('ONEID_APEL_CRON_ENABLED','false'),self::value('ONEID_APEL_CRON_DRY_RUN','true'));}
    public static function fromValues(string$enabled,string$dryRun):self{
        foreach([$enabled,$dryRun]as$f)if(!in_array($f,['true','false'],true))throw new \RuntimeException('APEL_CRON_FLAG_INVALID');
        if($enabled==='true'&&$dryRun!=='true')throw new \RuntimeException('APEL_CRON_WRITE_MODE_UNSUPPORTED');
        return new self($enabled==='true',$dryRun==='true');
    }
    public function assertRunnable():void{if(!$this->enabled)throw new \RuntimeException('APEL_CRON_DISABLED');if(!$this->dryRun)throw new \RuntimeException('APEL_CRON_WRITE_MODE_UNSUPPORTED');}
    private static function value(string$key,string$default):string{try{return trim((string)\oneid_secret($key));}catch(\Throwable){return$default;}}
}
