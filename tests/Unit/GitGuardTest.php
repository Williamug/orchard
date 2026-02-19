<?php

declare(strict_types=1);

namespace Orchard\Tests\Unit;

use Orchard\Service\GitGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class GitGuardTest extends TestCase
{
    public function testIsRepoReturnsTrueForGitRepository(): void
    {
        $tmpDir = sys_get_temp_dir() . '/orchard_git_' . uniqid();
        mkdir($tmpDir, 0755, true);

        // Initialize a real git repository
        (new Process(['git', 'init'], $tmpDir))->run();

        $guard = new GitGuard();
        self::assertTrue($guard->isRepo($tmpDir));

        $this->removeDir($tmpDir);
    }

    public function testIsRepoReturnsFalseForNonGitDirectory(): void
    {
        $tmpDir = sys_get_temp_dir() . '/orchard_nogit_' . uniqid();
        mkdir($tmpDir, 0755, true);

        $guard = new GitGuard();
        self::assertFalse($guard->isRepo($tmpDir));

        $this->removeDir($tmpDir);
    }

    public function testIsCleanReturnsTrueForCleanRepo(): void
    {
        $tmpDir = sys_get_temp_dir() . '/orchard_clean_' . uniqid();
        mkdir($tmpDir, 0755, true);

        (new Process(['git', 'init'], $tmpDir))->run();
        (new Process(['git', 'config', 'user.email', 'test@orchard.dev'], $tmpDir))->run();
        (new Process(['git', 'config', 'user.name', 'Orchard Test'], $tmpDir))->run();

        // Empty repo with no commits — git status --porcelain returns empty
        $guard = new GitGuard();
        self::assertTrue($guard->isClean($tmpDir));

        $this->removeDir($tmpDir);
    }

    public function testIsCleanReturnsFalseForDirtyRepo(): void
    {
        $tmpDir = sys_get_temp_dir() . '/orchard_dirty_' . uniqid();
        mkdir($tmpDir, 0755, true);

        (new Process(['git', 'init'], $tmpDir))->run();
        (new Process(['git', 'config', 'user.email', 'test@orchard.dev'], $tmpDir))->run();
        (new Process(['git', 'config', 'user.name', 'Orchard Test'], $tmpDir))->run();

        // Add an uncommitted file
        file_put_contents($tmpDir . '/dirty.txt', 'uncommitted change');

        $guard = new GitGuard();
        self::assertFalse($guard->isClean($tmpDir));

        $this->removeDir($tmpDir);
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

            if (is_dir($target)) {
                $this->removeDir($target);
            } else {
                unlink($target);
            }
        }

        rmdir($dir);
    }
}
