<?php

declare(strict_types=1);

namespace Orchard\Tests\Integration;

use Orchard\DTO\Project;
use Orchard\DTO\UpdateStatus;
use Orchard\Service\ComposerRunner;
use Orchard\Service\GitBranchManager;
use Orchard\Service\GitGuard;
use Orchard\Service\UpdateOrchestrator;
use PHPUnit\Framework\TestCase;

class UpdateOrchestratorTest extends TestCase
{
    private UpdateOrchestrator $orchestrator;
    private GitGuard $gitGuard;
    private ComposerRunner $composerRunner;
    private GitBranchManager $branchManager;

    protected function setUp(): void
    {
        $this->gitGuard       = $this->createMock(GitGuard::class);
        $this->composerRunner = $this->createMock(ComposerRunner::class);
        $this->branchManager   = $this->createMock(GitBranchManager::class);
        $this->orchestrator   = new UpdateOrchestrator($this->gitGuard, $this->composerRunner, $this->branchManager);
    }

    public function testSkipsDirtyRepository(): void
    {
        $project = $this->makeProject(isGitRepo: true, isGitClean: false);

        $results = $this->orchestrator->run([$project]);

        self::assertCount(1, $results);
        self::assertSame(UpdateStatus::SKIPPED, $results[0]->status);
        self::assertSame('DIRTY_GIT', $results[0]->reason);
    }

    public function testSkipsNonGitRepository(): void
    {
        $project = $this->makeProject(isGitRepo: false, isGitClean: false);

        $results = $this->orchestrator->run([$project]);

        self::assertCount(1, $results);
        self::assertSame(UpdateStatus::SKIPPED, $results[0]->status);
        self::assertSame('NOT_GIT_REPO', $results[0]->reason);
    }

    public function testSkipsExcludedProject(): void
    {
        $project = $this->makeProject(name: 'legacy-app', isGitRepo: true, isGitClean: true);

        $results = $this->orchestrator->run([$project], exclude: ['legacy-app']);

        self::assertCount(1, $results);
        self::assertSame(UpdateStatus::SKIPPED, $results[0]->status);
        self::assertSame('EXCLUDED', $results[0]->reason);
    }

    public function testDryRunSkipsComposerExecution(): void
    {
        $this->composerRunner->expects(self::never())->method('update');

        $project = $this->makeProject(isGitRepo: true, isGitClean: true);
        $results = $this->orchestrator->run([$project], dryRun: true);

        self::assertCount(1, $results);
        self::assertSame(UpdateStatus::SKIPPED, $results[0]->status);
        self::assertSame('DRY_RUN', $results[0]->reason);
    }

    public function testSuccessfulComposerUpdate(): void
    {
        $this->composerRunner
            ->method('update')
            ->willReturn([
                'exitCode'    => 0,
                'output'      => 'Nothing to install or update',
                'errorOutput' => '',
                'duration'    => 1.23,
            ]);

        $project = $this->makeProject(isGitRepo: true, isGitClean: true);
        $results = $this->orchestrator->run([$project]);

        self::assertCount(1, $results);
        self::assertSame(UpdateStatus::SUCCESS, $results[0]->status);
    }

    public function testFailedComposerUpdateMarksProjectFailed(): void
    {
        $this->composerRunner
            ->method('update')
            ->willReturn([
                'exitCode'    => 1,
                'output'      => '',
                'errorOutput' => 'Some composer error',
                'duration'    => 0.5,
            ]);

        $project = $this->makeProject(isGitRepo: true, isGitClean: true);
        $results = $this->orchestrator->run([$project]);

        self::assertCount(1, $results);
        self::assertSame(UpdateStatus::FAILED, $results[0]->status);
    }

    public function testContinuesOnFailure(): void
    {
        $this->composerRunner
            ->method('update')
            ->willReturnOnConsecutiveCalls(
                ['exitCode' => 1, 'output' => '', 'errorOutput' => 'Error', 'duration' => 0.1],
                ['exitCode' => 0, 'output' => 'OK', 'errorOutput' => '', 'duration' => 1.0],
            );

        $p1 = $this->makeProject(name: 'app-a', isGitRepo: true, isGitClean: true);
        $p2 = $this->makeProject(name: 'app-b', isGitRepo: true, isGitClean: true);

        $results = $this->orchestrator->run([$p1, $p2]);

        self::assertCount(2, $results);
        self::assertSame(UpdateStatus::FAILED, $results[0]->status);
        self::assertSame(UpdateStatus::SUCCESS, $results[1]->status);
    }

    public function testHasFailedDetectsFailures(): void
    {
        $this->composerRunner
            ->method('update')
            ->willReturn(['exitCode' => 1, 'output' => '', 'errorOutput' => 'Err', 'duration' => 0.1]);

        $project = $this->makeProject(isGitRepo: true, isGitClean: true);
        $results = $this->orchestrator->run([$project]);

        self::assertTrue($this->orchestrator->hasFailed($results));
    }

    public function testAutoBranchCreation(): void
    {
        $this->branchManager
            ->expects(self::once())
            ->method('createBranch')
            ->with('/tmp/test-project', 'chore/orchard-update-2026-02-19')
            ->willReturn(true);

        $this->composerRunner
            ->method('update')
            ->willReturn(['exitCode' => 0, 'output' => 'OK', 'errorOutput' => '', 'duration' => 1.0]);

        $project = $this->makeProject(isGitRepo: true, isGitClean: true);
        $results = $this->orchestrator->run([$project], autoBranchName: 'chore/orchard-update-2026-02-19');

        self::assertCount(1, $results);
        self::assertSame(UpdateStatus::SUCCESS, $results[0]->status);
        self::assertSame('chore/orchard-update-2026-02-19', $results[0]->branchCreated);
    }

    public function testSkipsIfBranchAlreadyExists(): void
    {
        $this->branchManager
            ->method('createBranch')
            ->willReturn(false);

        $this->composerRunner->expects(self::never())->method('update');

        $project = $this->makeProject(isGitRepo: true, isGitClean: true);
        $results = $this->orchestrator->run([$project], autoBranchName: 'chore/orchard-update-exists');

        self::assertCount(1, $results);
        self::assertSame(UpdateStatus::SKIPPED, $results[0]->status);
        self::assertSame('BRANCH_EXISTS', $results[0]->reason);
    }

    // -------------------------------------------------------------------------

    private function makeProject(
        string $name = 'test-project',
        bool $isGitRepo = true,
        bool $isGitClean = true,
    ): Project {
        return new Project(
            name: $name,
            path: '/tmp/' . $name,
            laravelVersion: 'v11.0.0',
            phpConstraint: '^8.2',
            isGitRepo: $isGitRepo,
            isGitClean: $isGitClean,
        );
    }
}
