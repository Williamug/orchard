<?php

declare(strict_types=1);

namespace Orchard\Service;

class LaravelDetector
{
    private const FRAMEWORK_PACKAGE = 'laravel/framework';

    public function isLaravel(string $path): bool
    {
        $composerJson = $this->readComposerJson($path);

        if ($composerJson === null) {
            return false;
        }

        $require    = $composerJson['require'] ?? [];
        $requireDev = $composerJson['require-dev'] ?? [];

        return isset($require[self::FRAMEWORK_PACKAGE])
            || isset($requireDev[self::FRAMEWORK_PACKAGE]);
    }

    public function getLaravelVersion(string $path): ?string
    {
        $lockFile = $path . '/composer.lock';

        if (!file_exists($lockFile)) {
            return null;
        }

        $content = file_get_contents($lockFile);

        if ($content === false) {
            return null;
        }

        $lock = json_decode($content, true);

        if (!is_array($lock)) {
            return null;
        }

        $packages = array_merge(
            $lock['packages'] ?? [],
            $lock['packages-dev'] ?? [],
        );

        foreach ($packages as $package) {
            if (($package['name'] ?? '') === self::FRAMEWORK_PACKAGE) {
                return $package['version'] ?? null;
            }
        }

        return null;
    }

    public function getPhpConstraint(string $path): ?string
    {
        $composerJson = $this->readComposerJson($path);

        return $composerJson['require']['php'] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readComposerJson(string $path): ?array
    {
        $file = $path . '/composer.json';

        if (!file_exists($file)) {
            return null;
        }

        $content = file_get_contents($file);

        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);

        if (!is_array($data)) {
            return null;
        }

        return $data;
    }
}
