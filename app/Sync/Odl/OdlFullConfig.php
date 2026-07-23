<?php
declare(strict_types=1);
namespace OneId\App\Sync\Odl;

final class OdlFullConfig
{
    private function __construct(
        public readonly bool $previewEnabled,
        public readonly bool $applyEnabled,
        public readonly int $expectedSourceRows,
        public readonly int $expectedNew,
        public readonly int $expectedKeep
    ) {}

    public static function fromPrivateRuntime(): self
    {
        return self::fromValues(
            (string)\oneid_config('ONEID_ODL_FULL_PREVIEW_ENABLED','false'),
            (string)\oneid_config('ONEID_ODL_FULL_APPLY_ENABLED','false'),
            (string)\oneid_config('ONEID_ODL_FULL_EXPECTED_SOURCE_ROWS',''),
            (string)\oneid_config('ONEID_ODL_FULL_EXPECTED_NEW',''),
            (string)\oneid_config('ONEID_ODL_FULL_EXPECTED_KEEP','')
        );
    }

    public static function fromValues(
        string $preview,
        string $apply,
        string $sourceRows,
        string $new,
        string $keep
    ): self {
        foreach([$preview,$apply]as$flag)if(!in_array($flag,['true','false'],true)){
            throw new \RuntimeException('ODL_FULL_FLAG_INVALID');
        }
        if($apply==='true'&&$preview!=='true'){
            throw new \RuntimeException('ODL_FULL_FLAG_COMBINATION_INVALID');
        }
        $values=[];
        foreach([$sourceRows,$new,$keep]as$raw){
            if(preg_match('/^(?:0|[1-9][0-9]*)$/',$raw)!==1){
                throw new \RuntimeException('ODL_FULL_EXPECTED_COUNT_INVALID');
            }
            $values[]=(int)$raw;
        }
        return new self($preview==='true',$apply==='true',...$values);
    }
}
