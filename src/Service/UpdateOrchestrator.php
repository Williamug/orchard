<?php

declare(strict_types=1);

namespace Orchard\Service;

use Orchard\DTO\Project;
use Orchard\DTO\UpdateResult;
use Orchard\DTO\UpdateStatus;
use Symfony\Component\Process\Process;

class UpdateOrchestrator
{
    private const SKIP_REASON_DIRTY         = 'DIRTY_GIT';
    private const SKIP_REASON_NO_REPO       = 'NOT_GIT_REPO';
    private const SKIP_REASON_EXCLUDED      = 'EXCLUDED';
    private const SKIP_REASON_DRY_RUN       = 'DRY_RUN';
    private const SKIP_REASON_BRANCH_EXISTS = 'BRANCH_EXISTS';

    public function __construct(
        private readonly GitGuard $gitGuard,
        private readonly ComposerRunner $composerRunner,
        private readonly ?GitBranchManager $branchManager = null,
    ) {}

    /**
     * @param list<Project> $projects
     * @param list<string>  $exclude
     * @return list<UpdateResult>
     */
    public function run(
        array $projects,
        array $exclude = [],
        bool $dryRun = false,
        int $parallel = 1,
        ?string $autoBranchName = null,
    ): array {
        // Cap parallel to CPU cores * 2
        $maxParallel = $this->getMaxParallel();
        $parallel    = max(1, min($parallel, $maxParallel));

        if ($parallel > 1) {
            return $this->runParallel($projects, $exclude, $dryRun, $parallel, $autoBranchName);
        }

        return $this->runSequential($projects, $exclude, $dryRun, $autoBranchName);
    }

    public function getMaxParallel(): int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $cpuCores = (int) getenv('NUMBER_OF_PROCESSORS');
        } else {
            $cpuCores = (int) shell_exec('nproc 2>/dev/null || sysctl -n hw.ncpu 2>/dev/null');
        }

        return max(1, ($cpuCores ?: 4) * 2);
    }

    /**
     * @param list<Project> $projects
     * @param list<string>  $exclude
     * @return list<UpdateResult>
     */
    private function runSequential(array $projects, array $exclude, bool $dryRun, ?string $autoBranchName): array
    {
        $results = [];

        foreach ($projects as $project) {
            $results[] = $this->processProject($project, $exclude, $dryRun, $autoBranchName);
        }

        return $results;
    }

    /**
     * @param list<Project> $projects
     * @param list<string>  $exclude
     * @return list<UpdateResult>
     */
    private function runParallel(array $projects, array $exclude, bool $dryRun, int $parallel, ?string $autoBranchName): array
    {
        $results = [];
        $batches = array_chunk($projects, $parallel);

        foreach ($batches as $batch) {
            $running = [];

            foreach ($batch as $project) {
                // Handle skips synchronously (no process needed)
                if (in_array($project->name, $exclude, true)) {
                    $results[] = UpdateResult::skipped($project, self::SKIP_REASON_EXCLUDED);
                    continue;
                }

                if (!$project->isGitRepo) {
                    $results[] = UpdateResult::skipped($project, self::SKIP_REASON_NO_REPO);
                    continue;
                }

                if (!$project->isGitClean) {
                    $results[] = UpdateResult::skipped($project, self::SKIP_REASON_DIRTY);
                    continue;
                }

                // Auto-branch before update
                $branchCreated = null;
                if ($autoBranchName !== null && $this->branchManager !== null) {
                    $created = $this->branchManager->createBranch($project->path, $autoBranchName);
                    if (!$created) {
                        $results[] = UpdateResult::skipped($project, self::SKIP_REASON_BRANCH_EXISTS);
                        continue;
                    }
                    $branchCreated = $autoBranchName;
                }

                if ($dryRun) {
                    $results[] = UpdateResult::dryRun($project);
                    continue;
                }

                // Start process asynchronously
                $process = new Process(
                    command: ['composer', 'update', '--with-all-dependencies', '--no-interaction', '--ansi'],
                    cwd: $project->path,
                    timeout: null,
                );

                $start = microtime(true);
                $process->start();
                $running[] = [
                    'process'       => $process,
                    'project'       => $project,
                    'start'         => $start,
                    'branchCreated' => $branchCreated,
                ];
            }

            // Wait for all running processes in this batch
            foreach ($running as $item) {
                /** @var Process $process */
                $process  = $item['process'];
                $project  = $item['project'];
                $start    = $item['start'];
                $branch   = $item['branchCreated'];

                $process->wait();
                $duration = microtime(true) - $start;

                if ($process->isSuccessful()) {
                    $results[] = UpdateResult::success($project, $duration, $process->getOutput(), $branch);
                } else {
                    $results[] = UpdateResult::failed($project, $duration, $process->getOutput(), $process->getErrorOutput());
                }
            }
        }

        return $results;
    }

    private function processProject(Project $project, array $exclude, bool $dryRun, ?string $autoBranchName): UpdateResult
    {
        if (in_array($project->name, $exclude, true)) {
            return UpdateResult::skipped($project, self::SKIP_REASON_EXCLUDED);
        }

        if (!$project->isGitRepo) {
            return UpdateResult::skipped($project, self::SKIP_REASON_NO_REPO);
        }

        if (!$project->isGitClean) {
            return UpdateResult::skipped($project, self::SKIP_REASON_DIRTY);
        }

        // Auto-branch before update
        $branchCreated = null;
        if ($autoBranchName !== null && $this->branchManager !== null) {
            $created = $this->branchManager->createBranch($project->path, $autoBranchName);
            if (!$created) {
                return UpdateResult::skipped($project, self::SKIP_REASON_BRANCH_EXISTS);
            }
            $branchCreated = $autoBranchName;
        }

        if ($dryRun) {
            return UpdateResult::dryRun($project);
        }

        $result = $this->composerRunner->update($project->path);

        if ($result['exitCode'] === 0) {
            return UpdateResult::success($project, $result['duration'], $result['output'], $branchCreated);
        }

        return UpdateResult::failed($project, $result['duration'], $result['output'], $result['errorOutput']);
    }

    /**
     * @param list<UpdateResult> $results
     */
    public function hasFailed(array $results): bool
    {
        foreach ($results as $result) {
            if ($result->status === UpdateStatus::FAILED) {
                return true;
            }
        }

        return false;
    }
}
