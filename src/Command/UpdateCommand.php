<?php

declare(strict_types=1);

namespace Orchard\Command;

use Orchard\Exception\OrchardException;
use Orchard\Service\ProjectScanner;
use Orchard\Service\Reporter;
use Orchard\Service\UpdateOrchestrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'update',
    description: 'Run composer update across all detected Laravel projects.',
)]
class UpdateCommand extends Command
{
    public function __construct(
        private readonly ProjectScanner $scanner,
        private readonly UpdateOrchestrator $orchestrator,
        private readonly Reporter $reporter,
        private readonly array $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Base path to scan')
            ->addOption('recursive', 'r', InputOption::VALUE_NONE, 'Scan recursively')
            ->addOption('parallel', null, InputOption::VALUE_REQUIRED, 'Run N projects in parallel (default: 1)')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED, 'Comma-separated list of project names to exclude')
            ->addOption('auto-branch', null, InputOption::VALUE_NONE, 'Create a git branch before updating')
            ->addOption('branch-prefix', null, InputOption::VALUE_REQUIRED, 'Prefix for auto-created branches')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate updates without running composer')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $basePath  = $this->resolvePath($input);
            $recursive = (bool) $input->getOption('recursive')
                         ?: (bool) ($this->config['recursive'] ?? false);
            $dryRun    = (bool) $input->getOption('dry-run');
            $json      = (bool) $input->getOption('json');
            $parallel  = $this->resolveParallel($input);
            $exclude   = $this->resolveExclude($input);
            $autoBranch = $this->resolveAutoBranch($input);

            $projects = $this->scanner->scan($basePath, $recursive, $exclude);

            if (empty($projects)) {
                if (!$json) {
                    $output->writeln('<comment>No Laravel projects found in: ' . $basePath . '</comment>');
                }
                return Command::SUCCESS;
            }

            if (!$json) {
                $maxParallel = $this->orchestrator->getMaxParallel();
                $parallelInfo = $parallel > 1
                    ? sprintf(' <comment>[parallel: %d (max: %d)]</comment>', $parallel, $maxParallel)
                    : '';

                $output->writeln(sprintf(
                    '<info>🍃 Orchard – updating %d project(s)%s%s</info>',
                    count($projects),
                    $dryRun ? ' <comment>[dry-run]</comment>' : '',
                    $parallelInfo,
                ));

                if ($autoBranch !== null) {
                    $output->writeln(sprintf('   <comment>Auto-branch enabled: %s</comment>', $autoBranch));
                }
            }

            $start   = microtime(true);
            $results = $this->orchestrator->run($projects, $exclude, $dryRun, $parallel, $autoBranch);
            $elapsed = microtime(true) - $start;

            if ($json) {
                $this->reporter->renderUpdateJson($output, $results, $elapsed);
            } else {
                $this->reporter->renderUpdateHuman($output, $results, $elapsed);
            }

            return $this->orchestrator->hasFailed($results) ? 1 : Command::SUCCESS;
        } catch (OrchardException $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return 2;
        }
    }

    private function resolvePath(InputInterface $input): string
    {
        $cliPath = $input->getOption('path');
        if ($cliPath !== null) {
            if (!is_dir((string) $cliPath)) {
                throw OrchardException::invalidPath((string) $cliPath);
            }
            return (string) $cliPath;
        }

        $configPath = $this->config['base_path'] ?? null;
        if ($configPath !== null && $configPath !== getcwd() && is_dir((string) $configPath)) {
            return (string) $configPath;
        }

        return getcwd() ?: '.';
    }

    private function resolveParallel(InputInterface $input): int
    {
        $cliValue = $input->getOption('parallel');

        if ($cliValue !== null) {
            $value = (int) $cliValue;
            if ($value < 1) {
                throw OrchardException::invalidConfig('--parallel must be >= 1');
            }
            return $value;
        }

        return (int) ($this->config['parallel'] ?? 1);
    }

    /**
     * @return list<string>
     */
    private function resolveExclude(InputInterface $input): array
    {
        $cliValue = $input->getOption('exclude');

        if ($cliValue !== null && $cliValue !== '') {
            return array_filter(array_map('trim', explode(',', (string) $cliValue)));
        }

        return $this->config['exclude'] ?? [];
    }

    private function resolveAutoBranch(InputInterface $input): ?string
    {
        // 1. CLI flag
        if ($input->getOption('auto-branch')) {
            $prefix = $input->getOption('branch-prefix')
                ?? $this->config['branch_prefix']
                ?? 'chore/orchard-update';

            return sprintf('%s-%s', rtrim((string) $prefix, '-'), date('Y-m-d'));
        }

        // 2. Config
        if (!empty($this->config['auto_branch'])) {
            $prefix = $input->getOption('branch-prefix')
                ?? $this->config['branch_prefix']
                ?? 'chore/orchard-update';

            return sprintf('%s-%s', rtrim((string) $prefix, '-'), date('Y-m-d'));
        }

        return null;
    }
}
