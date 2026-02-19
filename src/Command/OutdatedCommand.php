<?php

declare(strict_types=1);

namespace Orchard\Command;

use Orchard\Exception\OrchardException;
use Orchard\Service\OutdatedRunner;
use Orchard\Service\ProjectScanner;
use Orchard\Service\Reporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'outdated',
    description: 'Check for outdated dependencies across all detected Laravel projects.',
)]
class OutdatedCommand extends Command
{
    public function __construct(
        private readonly ProjectScanner $scanner,
        private readonly OutdatedRunner $runner,
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
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED, 'Comma-separated list of project names to exclude')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $basePath  = $this->resolvePath($input);
            $recursive = (bool) $input->getOption('recursive')
                         ?: (bool) ($this->config['recursive'] ?? false);
            $json      = (bool) $input->getOption('json');
            $exclude   = $this->resolveExclude($input);

            $projects = $this->scanner->scan($basePath, $recursive, $exclude);

            if (empty($projects)) {
                if (!$json) {
                    $output->writeln('<comment>No Laravel projects found in: ' . $basePath . '</comment>');
                }
                return Command::SUCCESS;
            }

            if (!$json) {
                $output->writeln(sprintf('<info>🍃 Orchard – checking outdated packages for %d project(s)</info>', count($projects)));
            }

            $results = [];
            foreach ($projects as $project) {
                // Determine if we should report loading... (only in human mode)
                // Note: sequential execution for now as this is a read-only check
                $results[] = $this->runner->check($project->name, $project->path);
            }

            if ($json) {
                $this->reporter->renderOutdatedJson($output, $results);
            } else {
                $this->reporter->renderOutdatedHuman($output, $results);
            }

            return Command::SUCCESS;
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
}
