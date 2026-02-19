<?php

declare(strict_types=1);

namespace Orchard\DTO;

final readonly class StatusResult
{
    public function __construct(
        public Project $project,
        public ?string $composerVersion,
    ) {}
}
