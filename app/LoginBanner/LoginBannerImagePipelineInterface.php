<?php

declare(strict_types=1);

namespace OneId\App\LoginBanner;

interface LoginBannerImagePipelineInterface
{
    /** @param array<string,mixed>|null $file @return array<string,mixed> */
    public function stageUpload(?array $file, string $stagingDirectory): array;

    /** @param array<string,mixed> $staged */
    public function publish(array $staged, string $publishedDirectory): string;

    /** @param array<string,mixed> $staged */
    public function discardStaged(array $staged, string $stagingDirectory): void;

    public function discardPublished(string $publishedPath, string $publishedDirectory): void;
}
