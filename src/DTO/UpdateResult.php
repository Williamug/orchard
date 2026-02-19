<?php

declare(strict_types=1);

namespace Orchard\DTO;

enum UpdateStatus: string
{
    case SUCCESS = 'success';
    case SKIPPED = 'skipped';
    case FAILED  = 'failed';
}

final readonly class UpdateResult
{
    public function __construct(
        public Project $project,
        public UpdateStatus $status,
        public ?string $reason,
        public float $duration,
        public string $output,
        public string $errorOutput,
        public ?string $branchCreated = null,
    ) {}

    public static function skipped(Project $project, string $reason): self
    {
        return new self(
            project: $project,
            status: UpdateStatus::SKIPPED,
            reason: $reason,
            duration: 0.0,
            output: '',
            errorOutput: '',
        );
    }

    public static function success(Project $project, float $duration, string $output, ?string $branchCreated = null): self
    {
        return new self(
            project: $project,
            status: UpdateStatus::SUCCESS,
            reason: null,
            duration: $duration,
            output: $output,
            errorOutput: '',
            branchCreated: $branchCreated,
        );
    }

    public static function failed(Project $project, float $duration, string $output, string $errorOutput): self
    {
        return new self(
            project: $project,
            status: UpdateStatus::FAILED,
            reason: 'Composer exited with non-zero code',
            duration: $duration,
            output: $output,
            errorOutput: $errorOutput,
        );
    }

    public static function dryRun(Project $project): self
    {
        return new self(
            project: $project,
            status: UpdateStatus::SKIPPED,
            reason: 'DRY_RUN',
            duration: 0.0,
            output: '',
            errorOutput: '',
        );
    }
}
