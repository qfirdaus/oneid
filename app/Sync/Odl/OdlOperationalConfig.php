<?php
declare(strict_types=1);
namespace OneId\App\Sync\Odl;

final class OdlOperationalConfig
{
    private function __construct(
        public readonly bool $previewEnabled,
        public readonly bool $applyEnabled,
        public readonly int $expectedSourceRows,
        /** @var array{New:int,Update:int,Deactivate:int,Reactivate:int} */
        public readonly array $expectedCounts,
        public readonly string $expectedPlanHash,
        public readonly string $changeReference,
        public readonly string $backupReference,
        public readonly string $windowStart,
        public readonly string $windowEnd,
        public readonly bool $onDemandEnabled,
        public readonly string $environment,
        public readonly bool $manualOperationalEnabled
    ) {}

    public static function fromPrivateRuntime(): self
    {
        return self::fromValues(
            (string) \oneid_config('ONEID_ODL_OPERATIONAL_PREVIEW_ENABLED', 'false'),
            (string) \oneid_config('ONEID_ODL_OPERATIONAL_APPLY_ENABLED', 'false'),
            (string) \oneid_config('ONEID_ODL_OPERATIONAL_EXPECTED_SOURCE_ROWS', '0'),
            (string) \oneid_config('ONEID_ODL_OPERATIONAL_EXPECTED_NEW', '0'),
            (string) \oneid_config('ONEID_ODL_OPERATIONAL_EXPECTED_UPDATE', '0'),
            (string) \oneid_config('ONEID_ODL_OPERATIONAL_EXPECTED_DEACTIVATE', '0'),
            (string) \oneid_config('ONEID_ODL_OPERATIONAL_EXPECTED_REACTIVATE', '0'),
            (string) \oneid_config('ONEID_ODL_OPERATIONAL_EXPECTED_PLAN_HASH', ''),
            (string) \oneid_config('ONEID_ODL_OPERATIONAL_CHANGE_REFERENCE', ''),
            (string) \oneid_config('ONEID_ODL_OPERATIONAL_BACKUP_REFERENCE', ''),
            (string) \oneid_config('ONEID_ODL_OPERATIONAL_WINDOW_START', ''),
            (string) \oneid_config('ONEID_ODL_OPERATIONAL_WINDOW_END', ''),
            (string) \oneid_config('ONEID_ODL_OPERATIONAL_ON_DEMAND_ENABLED', 'false'),
            (string) \oneid_config('ONEID_ENVIRONMENT', ''),
            (string) \oneid_config('ONEID_ODL_MANUAL_OPERATIONAL_ENABLED', 'false')
        );
    }

    public static function fromValues(
        string $preview,string $apply,string $sourceRows='0',string $new='0',
        string $update='0',string $deactivate='0',string $reactivate='0',
        string $planHash='',string $changeReference='',string $backupReference='',
        string $windowStart='',string $windowEnd='',string $onDemand='false',
        string $environment='',string $manualOperational='false'
    ): self {
        foreach ([$preview, $apply, $onDemand, $manualOperational] as $flag) {
            if (!in_array($flag, ['true', 'false'], true)) {
                throw new \RuntimeException('ODL_OPERATIONAL_FLAG_INVALID');
            }
        }
        if (($apply === 'true' || $onDemand === 'true' || $manualOperational === 'true')
            && $preview !== 'true') {
            throw new \RuntimeException('ODL_OPERATIONAL_FLAG_COMBINATION_INVALID');
        }
        if (count(array_filter(
            [$apply, $onDemand, $manualOperational],
            static fn (string $flag): bool => $flag === 'true'
        )) > 1) {
            throw new \RuntimeException('ODL_OPERATIONAL_FLAG_COMBINATION_INVALID');
        }
        $normalizedEnvironment = strtolower(trim($environment));
        if ($onDemand === 'true'
            && !in_array($normalizedEnvironment, ['staging', 'uat'], true)
        ) {
            throw new \RuntimeException('ODL_OPERATIONAL_ON_DEMAND_ENVIRONMENT_INVALID');
        }
        if ($manualOperational === 'true'
            && !in_array($normalizedEnvironment, ['staging', 'uat', 'production'], true)
        ) {
            throw new \RuntimeException('ODL_MANUAL_OPERATIONAL_ENVIRONMENT_INVALID');
        }
        foreach([$sourceRows,$new,$update,$deactivate,$reactivate]as$value){
            if(preg_match('/^(?:0|[1-9][0-9]*)$/',$value)!==1){
                throw new \RuntimeException('ODL_OPERATIONAL_EXPECTED_COUNTS_INVALID');
            }
        }
        if($apply==='true'){
            if((int)$sourceRows<1
                ||array_sum(array_map('intval',[$new,$update,$deactivate,$reactivate]))<1
                ||preg_match('/^[a-f0-9]{64}$/',$planHash)!==1
                ||preg_match('/^ONEID-ODL-F9A?-[0-9]{8}-[0-9]{2}$/',$changeReference)!==1
                ||preg_match('/^ONEID-UAT-BACKUP-[0-9]{8}-[0-9]{2}$/',$backupReference)!==1){
                throw new \RuntimeException('ODL_OPERATIONAL_AUTHORIZATION_INVALID');
            }
            try{
                $start=new \DateTimeImmutable($windowStart);
                $end=new \DateTimeImmutable($windowEnd);
            }catch(\Throwable){
                throw new \RuntimeException('ODL_OPERATIONAL_WINDOW_INVALID');
            }
            $duration=$end->getTimestamp()-$start->getTimestamp();
            if($start->format('P')!=='+08:00'||$end->format('P')!=='+08:00'
                ||$start->format('Y-m-d')!==$end->format('Y-m-d')
                ||$duration<300||$duration>3600){
                throw new \RuntimeException('ODL_OPERATIONAL_WINDOW_INVALID');
            }
        }
        return new self(
            $preview==='true',$apply==='true',(int)$sourceRows,
            ['New'=>(int)$new,'Update'=>(int)$update,
             'Deactivate'=>(int)$deactivate,'Reactivate'=>(int)$reactivate],
            $planHash,$changeReference,$backupReference,$windowStart,$windowEnd,
            $onDemand==='true',$normalizedEnvironment,$manualOperational==='true'
        );
    }

    public function assertPreviewEnabled(): void
    {
        if (!$this->previewEnabled) {
            throw new \RuntimeException('ODL_OPERATIONAL_PREVIEW_DISABLED');
        }
    }

    public function assertApplyEnabled(): void
    {
        if (!$this->canApply()) {
            throw new \RuntimeException('ODL_OPERATIONAL_APPLY_DISABLED');
        }
    }

    public function canApply(): bool
    {
        return $this->applyEnabled || $this->onDemandEnabled || $this->manualOperationalEnabled;
    }

    /** @param array{New:int,Update:int,Deactivate:int,Reactivate:int} $counts */
    public function assertApprovedPlan(int $sourceRows,array $counts,string $planHash):void
    {
        $this->assertApplyEnabled();
        if ($this->onDemandEnabled || $this->manualOperationalEnabled) {
            return;
        }
        if($sourceRows!==$this->expectedSourceRows
            ||$counts!==$this->expectedCounts
            ||!hash_equals($this->expectedPlanHash,$planHash)){
            throw new \RuntimeException('ODL_OPERATIONAL_EXACT_PLAN_MISMATCH');
        }
    }

    public function assertWithinChangeWindow(?\DateTimeImmutable $now=null):void
    {
        $this->assertApplyEnabled();
        if ($this->onDemandEnabled || $this->manualOperationalEnabled) {
            return;
        }
        $now??=new \DateTimeImmutable('now',new \DateTimeZone('Asia/Kuala_Lumpur'));
        if($now<new \DateTimeImmutable($this->windowStart)
            ||$now>new \DateTimeImmutable($this->windowEnd)){
            throw new \RuntimeException('ODL_OPERATIONAL_OUTSIDE_CHANGE_WINDOW');
        }
    }
}
