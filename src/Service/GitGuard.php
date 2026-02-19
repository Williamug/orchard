<?php

declare(strict_types=1);

namespace Orchard\Service;

use Symfony\Component\Process\Process;

class GitGuard
{
    public function isRepo(string $path): bool
    {
        $process = new Process(
            command: ['git', 'rev-parse', '--is-inside-work-tree'],
            cwd: $path,
        );

        $process->run();

        return $process->isSuccessful()
            && trim($process->getOutput()) === 'true';
    }

    public function isClean(string $path): bool
    {
        $process = new Process(
            command: ['git', 'status', '--porcelain'],
            cwd: $path,
        );

        $process->run();

        if (!$process->isSuccessful()) {
            return false;
        }

        return trim($process->getOutput()) === '';
    }
}
