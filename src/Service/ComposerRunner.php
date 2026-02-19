<?php

declare(strict_types=1);

namespace Orchard\Service;

use Symfony\Component\Process\Process;

class ComposerRunner
{
    private const COMPOSER_COMMAND = ['composer', 'update', '--with-all-dependencies', '--no-interaction', '--ansi'];

    public function __construct(
        private readonly int $timeout = 0,
    ) {}

    /**
     * @return array{exitCode: int, output: string, errorOutput: string, duration: float}
     */
    public function update(string $path): array
    {
        $process = new Process(
            command: self::COMPOSER_COMMAND,
            cwd: $path,
            timeout: $this->timeout > 0 ? $this->timeout : null,
        );

        $start = microtime(true);
        $process->run();
        $duration = microtime(true) - $start;

        return [
            'exitCode'    => $process->getExitCode() ?? 1,
            'output'      => $process->getOutput(),
            'errorOutput' => $process->getErrorOutput(),
            'duration'    => $duration,
        ];
    }

    public function getVersion(string $path): ?string
    {
        $process = new Process(
            command: ['composer', '--version', '--no-ansi'],
            cwd: $path,
        );

        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        $output = trim($process->getOutput());

        if (preg_match('/Composer version (\S+)/', $output, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
