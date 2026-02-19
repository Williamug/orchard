<?php

declare(strict_types=1);

namespace Orchard\Service;

use Symfony\Component\Process\Process;

class GitBranchManager
{
    public function branchExists(string $path, string $branch): bool
    {
        $process = new Process(
            command: ['git', 'branch', '--list', $branch],
            cwd: $path,
        );

        $process->run();

        return $process->isSuccessful()
            && trim($process->getOutput()) !== '';
    }

    public function createBranch(string $path, string $branch): bool
    {
        if ($this->branchExists($path, $branch)) {
            return false;
        }

        $process = new Process(
            command: ['git', 'checkout', '-b', $branch],
            cwd: $path,
        );

        $process->run();

        return $process->isSuccessful();
    }

    public function getCurrentBranch(string $path): ?string
    {
        $process = new Process(
            command: ['git', 'rev-parse', '--abbrev-ref', 'HEAD'],
            cwd: $path,
        );

        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        $branch = trim($process->getOutput());

        return $branch !== '' ? $branch : null;
    }
}
