<?php

declare(strict_types=1);

namespace Orchard\Tests\Unit;

use Orchard\Service\GitGuard;
use Orchard\Service\LaravelDetector;
use Orchard\Service\ProjectScanner;
use PHPUnit\Framework\TestCase;

class ProjectScannerTest extends TestCase
{
    private string $tmpDir;
    private ProjectScanner $scanner;

    protected function setUp(): void
    {
        $this->tmpDir  = sys_get_temp_dir() . '/orchard_scan_' . uniqid();
        mkdir($this->tmpDir, 0755, true);

        // Use real LaravelDetector; mock GitGuard to always return clean/not-repo
        $gitGuard = $this->createMock(GitGuard::class);
        $gitGuard->method('isRepo')->willReturn(false);
        $gitGuard->method('isClean')->willReturn(false);

        $this->scanner = new ProjectScanner(new LaravelDetector(), $gitGuard);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testScanDetectsLaravelProjects(): void
    {
        $this->createLaravelProject('my-app');
        $this->createNonLaravelProject('some-lib');

        $projects = $this->scanner->scan($this->tmpDir);

        self::assertCount(1, $projects);
        self::assertSame('my-app', $projects[0]->name);
    }

    public function testScanIgnoresNonLaravelProjects(): void
    {
        $this->createNonLaravelProject('plain-php');

        $projects = $this->scanner->scan($this->tmpDir);

        self::assertCount(0, $projects);
    }

    public function testScanReturnsEmptyArrayForEmptyDirectory(): void
    {
        $projects = $this->scanner->scan($this->tmpDir);
        self::assertSame([], $projects);
    }

    public function testScanRespectsExcludeList(): void
    {
        $this->createLaravelProject('app-a');
        $this->createLaravelProject('app-b');

        $projects = $this->scanner->scan($this->tmpDir, false, ['app-a']);

        self::assertCount(1, $projects);
        self::assertSame('app-b', $projects[0]->name);
    }

    public function testScanDetectsMultipleProjects(): void
    {
        $this->createLaravelProject('alpha');
        $this->createLaravelProject('beta');
        $this->createLaravelProject('gamma');

        $projects = $this->scanner->scan($this->tmpDir);

        self::assertCount(3, $projects);
    }

    // -------------------------------------------------------------------------

    private function createLaravelProject(string $name): void
    {
        $dir = $this->tmpDir . '/' . $name;
        mkdir($dir, 0755, true);

        file_put_contents($dir . '/composer.json', json_encode([
            'require' => ['laravel/framework' => '^11.0'],
        ]));
    }

    private function createNonLaravelProject(string $name): void
    {
        $dir = $this->tmpDir . '/' . $name;
        mkdir($dir, 0755, true);

        file_put_contents($dir . '/composer.json', json_encode([
            'require' => ['symfony/console' => '^7.0'],
        ]));
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
