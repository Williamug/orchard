<?php

declare(strict_types=1);

namespace Orchard\Tests\Unit;

use Orchard\Service\GitBranchManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class GitBranchManagerTest extends TestCase
{
    private string $tmpDir;
    private GitBranchManager $manager;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/orchard_branch_' . uniqid();
        mkdir($this->tmpDir, 0755, true);

        (new Process(['git', 'init'], $this->tmpDir))->run();
        (new Process(['git', 'config', 'user.email', 'test@orchard.dev'], $this->tmpDir))->run();
        (new Process(['git', 'config', 'user.name', 'Orchard Test'], $this->tmpDir))->run();

        // Git needs a commit to branch
        file_put_contents($this->tmpDir . '/README.md', '# Test');
        (new Process(['git', 'add', '.'], $this->tmpDir))->run();
        (new Process(['git', 'commit', '-m', 'Initial commit'], $this->tmpDir))->run();

        $this->manager = new GitBranchManager();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testBranchExists(): void
    {
        self::assertTrue($this->manager->branchExists($this->tmpDir, 'master') || $this->manager->branchExists($this->tmpDir, 'main'));
        self::assertFalse($this->manager->branchExists($this->tmpDir, 'non-existent'));
    }

    public function testCreateBranch(): void
    {
        $branch = 'feature/test-branch';
        self::assertTrue($this->manager->createBranch($this->tmpDir, $branch));
        self::assertTrue($this->manager->branchExists($this->tmpDir, $branch));

        // Cannot create existing
        self::assertFalse($this->manager->createBranch($this->tmpDir, $branch));
    }

    public function testGetCurrentBranch(): void
    {
        $current = $this->manager->getCurrentBranch($this->tmpDir);
        self::assertNotNull($current);

        $branch = 'feature/current';
        $this->manager->createBranch($this->tmpDir, $branch);
        self::assertSame($branch, $this->manager->getCurrentBranch($this->tmpDir));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $target = $dir . '/' . $file;
            is_dir($target) ? $this->removeDir($target) : unlink($target);
        }

        rmdir($dir);
    }
}
