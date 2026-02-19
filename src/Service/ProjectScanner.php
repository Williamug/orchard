<?php

declare(strict_types=1);

namespace Orchard\Service;

use Orchard\DTO\Project;
use Symfony\Component\Finder\Finder;

class ProjectScanner
{
    public function __construct(
        private readonly LaravelDetector $detector,
        private readonly GitGuard $gitGuard,
    ) {}

    /**
     * @param list<string> $exclude
     * @return list<Project>
     */
    public function scan(string $basePath, bool $recursive = false, array $exclude = []): array
    {
        $projects = [];
        $depth    = $recursive ? '>= 1' : '== 1';

        $finder = (new Finder())
            ->files()
            ->name('composer.json')
            ->depth($depth)
            ->ignoreDotFiles(false)
            ->ignoreVCS(false)
            ->in($basePath);

        foreach ($finder as $file) {
            $projectPath = $file->getPath();
            $projectName = basename($projectPath);

            // Skip excluded projects
            if (in_array($projectName, $exclude, true)) {
                continue;
            }

            // Must be a Laravel project
            if (!$this->detector->isLaravel($projectPath)) {
                continue;
            }

            $isGitRepo   = $this->gitGuard->isRepo($projectPath);
            $isGitClean  = $isGitRepo && $this->gitGuard->isClean($projectPath);

            $projects[] = new Project(
                name: $projectName,
                path: $projectPath,
                laravelVersion: $this->detector->getLaravelVersion($projectPath),
                phpConstraint: $this->detector->getPhpConstraint($projectPath),
                isGitRepo: $isGitRepo,
                isGitClean: $isGitClean,
            );
        }

        return $projects;
    }
}
