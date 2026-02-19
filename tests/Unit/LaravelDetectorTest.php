<?php

declare(strict_types=1);

namespace Orchard\Tests\Unit;

use Orchard\Service\LaravelDetector;
use PHPUnit\Framework\TestCase;

class LaravelDetectorTest extends TestCase
{
    private LaravelDetector $detector;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->detector = new LaravelDetector();
        $this->tmpDir   = sys_get_temp_dir() . '/orchard_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testIsLaravelReturnsTrueWhenFrameworkInRequire(): void
    {
        $this->writeComposerJson($this->tmpDir, [
            'require' => ['laravel/framework' => '^11.0'],
        ]);

        self::assertTrue($this->detector->isLaravel($this->tmpDir));
    }

    public function testIsLaravelReturnsTrueWhenFrameworkInRequireDev(): void
    {
        $this->writeComposerJson($this->tmpDir, [
            'require-dev' => ['laravel/framework' => '^11.0'],
        ]);

        self::assertTrue($this->detector->isLaravel($this->tmpDir));
    }

    public function testIsLaravelReturnsFalseForNonLaravelProject(): void
    {
        $this->writeComposerJson($this->tmpDir, [
            'require' => ['symfony/console' => '^7.0'],
        ]);

        self::assertFalse($this->detector->isLaravel($this->tmpDir));
    }

    public function testIsLaravelReturnsFalseWhenComposerJsonMissing(): void
    {
        self::assertFalse($this->detector->isLaravel($this->tmpDir));
    }

    public function testIsLaravelReturnsFalseForMalformedJson(): void
    {
        file_put_contents($this->tmpDir . '/composer.json', 'NOT_VALID_JSON');

        self::assertFalse($this->detector->isLaravel($this->tmpDir));
    }

    public function testIsLaravelReturnsFalseWhenRequireIsEmpty(): void
    {
        $this->writeComposerJson($this->tmpDir, ['require' => []]);

        self::assertFalse($this->detector->isLaravel($this->tmpDir));
    }

    public function testGetPhpConstraintReturnsValue(): void
    {
        $this->writeComposerJson($this->tmpDir, [
            'require' => [
                'php'               => '^8.2',
                'laravel/framework' => '^11.0',
            ],
        ]);

        self::assertSame('^8.2', $this->detector->getPhpConstraint($this->tmpDir));
    }

    public function testGetPhpConstraintReturnsNullWhenMissing(): void
    {
        $this->writeComposerJson($this->tmpDir, [
            'require' => ['laravel/framework' => '^11.0'],
        ]);

        self::assertNull($this->detector->getPhpConstraint($this->tmpDir));
    }

    public function testGetLaravelVersionReturnsNullWhenLockMissing(): void
    {
        self::assertNull($this->detector->getLaravelVersion($this->tmpDir));
    }

    public function testGetLaravelVersionReadsFromComposerLock(): void
    {
        $lock = [
            'packages' => [
                ['name' => 'laravel/framework', 'version' => 'v11.28.0'],
                ['name' => 'symfony/console', 'version' => 'v7.0.0'],
            ],
            'packages-dev' => [],
        ];

        file_put_contents($this->tmpDir . '/composer.lock', json_encode($lock));

        self::assertSame('v11.28.0', $this->detector->getLaravelVersion($this->tmpDir));
    }

    // -------------------------------------------------------------------------

    private function writeComposerJson(string $path, array $data): void
    {
        file_put_contents($path . '/composer.json', json_encode($data, JSON_PRETTY_PRINT));
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
