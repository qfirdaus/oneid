<?php

declare(strict_types=1);

namespace OneId\App\Sync\Apel;

final class ApelSourceDefinition
{
    public const SOURCE_CODE = 'STUDENT_APEL';
    public const SOURCE_FAMILY = 'student';
    public const VIEW_NAME = 'vw_oneid_active_students';
    public const CATEGORY_LABEL = 'Pelajar';
    public const CATEGORY_ID = 10;

    private function __construct()
    {
    }
}
