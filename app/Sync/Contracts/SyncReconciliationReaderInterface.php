<?php

namespace OneId\App\Sync\Contracts;

interface SyncReconciliationReaderInterface
{
    /** @return array{New:int,Update:int,Deactivate:int,Reactivate:int} */
    public function changeCounts(int $headerId): array;
}
