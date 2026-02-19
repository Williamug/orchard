<?php

declare(strict_types=1);

namespace Orchard;

use Orchard\Command\OutdatedCommand;
use Orchard\Command\ScanCommand;
use Orchard\Command\StatusCommand;
use Orchard\Command\UpdateCommand;
use Orchard\Service\ComposerRunner;
use Orchard\Service\GitBranchManager;
use Orchard\Service\GitGuard;
use Orchard\Service\LaravelDetector;
use Orchard\Service\OutdatedRunner;
use Orchard\Service\ProjectScanner;
use Orchard\Service\Reporter;
use Orchard\Service\UpdateOrchestrator;
use Symfony\Component\Console\Application as SymfonyApplication;

class Application extends SymfonyApplication
{
    private const VERSION = '1.0.0';

    public function __construct()
    {
        parent::__construct('🍃 Orchard', self::VERSION);

        $config = $this->loadConfig();

        // Instantiate services
        $detector    = new LaravelDetector();
        $gitGuard    = new GitGuard();
        $branchManager = new GitBranchManager();
        $scanner     = new ProjectScanner($detector, $gitGuard);
        $composer    = new ComposerRunner((int) ($config['timeout'] ?? 0));
        $outdated    = new OutdatedRunner((int) ($config['timeout'] ?? 0));
        $orchestrator = new UpdateOrchestrator($gitGuard, $composer, $branchManager);
        $reporter    = new Reporter();

        // Register commands
        $this->add(new ScanCommand($scanner, $reporter, $config));
        $this->add(new StatusCommand($scanner, $composer, $reporter, $config));
        $this->add(new UpdateCommand($scanner, $orchestrator, $reporter, $config));
        $this->add(new OutdatedCommand($scanner, $outdated, $reporter, $config));
    }

    /**
     * Load config from ~/.orchard.json merged over internal defaults.
     *
     * @return array<string, mixed>
     */
    private function loadConfig(): array
    {
        $defaults = require __DIR__ . '/../config/default.php';

        $userConfigPath = ($_SERVER['HOME'] ?? getenv('HOME') ?: '~') . '/.orchard.json';

        if (!file_exists($userConfigPath)) {
            return $defaults;
        }

        $content = file_get_contents($userConfigPath);

        if ($content === false) {
            return $defaults;
        }

        $userConfig = json_decode($content, true);

        if (!is_array($userConfig)) {
            return $defaults;
        }

        return array_merge($defaults, $userConfig);
    }
}
