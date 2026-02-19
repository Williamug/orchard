<?php

declare(strict_types=1);

namespace Orchard\DTO;

final readonly class Project
{
    public function __construct(
        public string $name,
        public string $path,
        public ?string $laravelVersion,
        public ?string $phpConstraint,
        public bool $isGitRepo,
        public bool $isGitClean,
    ) {}

    public function gitStatus(): string
    {
        if (!$this->isGitRepo) {
            return 'NOT_GIT_REPO';
        }

        return $this->isGitClean ? 'clean' : 'dirty';
    }
}
