<?php

declare(strict_types=1);

namespace Orchard\Service;

use Orchard\DTO\OutdatedResult;
use Symfony\Component\Process\Process;

class OutdatedRunner
{
    private const COMPOSER_COMMAND = [
        'composer', 'outdated', '--direct', '--format=json', '--no-interaction',
    ];

    public function __construct(
        private readonly int $timeout = 0,
    ) {}

    public function check(string $projectName, string $projectPath): OutdatedResult
    {
        $process = new Process(
            command: self::COMPOSER_COMMAND,
            cwd: $projectPath,
            timeout: $this->timeout > 0 ? $this->timeout : null,
        );

        $process->run();

        // composer outdated exits 1 when there are outdated packages (not an error)
        // It only truly fails if JSON output is unparseable or process crashes
        $output = trim($process->getOutput());

        if ($output === '') {
            // No output at all — likely an error
            if (!$process->isSuccessful() && trim($process->getErrorOutput()) !== '') {
                return OutdatedResult::failed($projectName, $projectPath, $process->getErrorOutput());
            }

            return OutdatedResult::success($projectName, $projectPath, []);
        }

        $data = json_decode($output, true);

        if (!is_array($data)) {
            return OutdatedResult::failed($projectName, $projectPath, 'Failed to parse composer outdated JSON output.');
        }

        $packages = $data['installed'] ?? [];

        return OutdatedResult::success($projectName, $projectPath, $packages);
    }
}
