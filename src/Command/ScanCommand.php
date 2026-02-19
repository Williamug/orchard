<?php

declare(strict_types=1);

namespace Orchard\Command;

use Orchard\Exception\OrchardException;
use Orchard\Service\ProjectScanner;
use Orchard\Service\Reporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'scan',
    description: 'Scan a directory for Laravel projects.',
)]
class ScanCommand extends Command
{
    public function __construct(
        private readonly ProjectScanner $scanner,
        private readonly Reporter $reporter,
        private readonly array $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Base path to scan (overrides config and cwd)')
            ->addOption('recursive', 'r', InputOption::VALUE_NONE, 'Scan recursively')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $basePath  = $this->resolvePath($input);
            $recursive = (bool) $input->getOption('recursive')
                         ?: (bool) ($this->config['recursive'] ?? false);
            $json      = (bool) $input->getOption('json');

            $projects = $this->scanner->scan($basePath, $recursive);

            if ($json) {
                $this->reporter->renderScanJson($output, $projects);
            } else {
                $this->reporter->renderScanHuman($output, $projects);
            }

            return Command::SUCCESS;
        } catch (OrchardException $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return 2;
        }
    }

    private function resolvePath(InputInterface $input): string
    {
        // 1. CLI --path flag
        $cliPath = $input->getOption('path');
        if ($cliPath !== null) {
            if (!is_dir((string) $cliPath)) {
                throw OrchardException::invalidPath((string) $cliPath);
            }
            return (string) $cliPath;
        }

        // 2. Config file
        $configPath = $this->config['base_path'] ?? null;
        if ($configPath !== null && $configPath !== getcwd() && is_dir((string) $configPath)) {
            return (string) $configPath;
        }

        // 3. Current working directory
        return getcwd() ?: '.';
    }
}
