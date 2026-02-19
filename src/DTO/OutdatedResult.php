<?php

declare(strict_types=1);

namespace Orchard\DTO;

final readonly class OutdatedResult
{
    public function __construct(
        public string $projectName,
        public string $projectPath,
        /** @var list<array<string, string>> */
        public array $packages,
        public bool $failed,
        public string $errorOutput,
    ) {}

    public static function success(string $projectName, string $projectPath, array $packages): self
    {
        return new self(
            projectName: $projectName,
            projectPath: $projectPath,
            packages: $packages,
            failed: false,
            errorOutput: '',
        );
    }

    public static function failed(string $projectName, string $projectPath, string $errorOutput): self
    {
        return new self(
            projectName: $projectName,
            projectPath: $projectPath,
            packages: [],
            failed: true,
            errorOutput: $errorOutput,
        );
    }

    public function hasOutdated(): bool
    {
        return !empty($this->packages);
    }
}
