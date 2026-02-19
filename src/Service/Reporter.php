<?php

declare(strict_types=1);

namespace Orchard\Service;

use Orchard\DTO\OutdatedResult;
use Orchard\DTO\Project;
use Orchard\DTO\StatusResult;
use Orchard\DTO\UpdateResult;
use Orchard\DTO\UpdateStatus;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

class Reporter
{
    // Icons
    private const ICON_SUCCESS = '✔';
    private const ICON_SKIPPED = '⚠';
    private const ICON_FAILED  = '✖';
    private const ICON_TREE    = '🍃';

    // -------------------------------------------------------------------------
    // Update reporting
    // -------------------------------------------------------------------------

    /**
     * @param list<UpdateResult> $results
     */
    public function renderUpdateHuman(OutputInterface $output, array $results, float $totalTime): void
    {
        $output->writeln('');

        foreach ($results as $result) {
            $icon = match ($result->status) {
                UpdateStatus::SUCCESS => sprintf('<info>%s</info>', self::ICON_SUCCESS),
                UpdateStatus::SKIPPED => sprintf('<comment>%s</comment>', self::ICON_SKIPPED),
                UpdateStatus::FAILED  => sprintf('<error>%s</error>', self::ICON_FAILED),
            };

            $detail = $result->reason
                ? sprintf(' (%s)', $result->reason)
                : sprintf(' [%.1fs]', $result->duration);

            $output->writeln(sprintf('  %s <options=bold>%s</> — %s%s', $icon, $result->project->name, $result->status->value, $detail));
        }

        $this->renderUpdateSummary($output, $results, $totalTime);
    }

    /**
     * @param list<UpdateResult> $results
     */
    public function renderUpdateJson(OutputInterface $output, array $results, float $totalTime): void
    {
        $counts = $this->countResults($results);

        $payload = [
            'summary' => [
                'updated'    => $counts['success'],
                'skipped'    => $counts['skipped'],
                'failed'     => $counts['failed'],
                'total_time' => round($totalTime, 2),
            ],
            'projects' => array_map(fn (UpdateResult $r) => [
                'name'     => $r->project->name,
                'path'     => $r->project->path,
                'status'   => $r->status->value,
                'reason'   => $r->reason,
                'duration' => round($r->duration, 2),
            ], $results),
        ];

        $output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    // -------------------------------------------------------------------------
    // Status reporting
    // -------------------------------------------------------------------------

    /**
     * @param list<StatusResult> $results
     */
    public function renderStatusHuman(OutputInterface $output, array $results): void
    {
        if (empty($results)) {
            $output->writeln('<comment>No Laravel projects found.</comment>');
            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Project', 'Laravel', 'PHP', 'Git', 'Composer', 'Path']);

        foreach ($results as $result) {
            $p = $result->project;

            $git = match (true) {
                !$p->isGitRepo  => '<comment>no repo</comment>',
                $p->isGitClean  => '<info>clean</info>',
                default         => '<error>dirty</error>',
            };

            $table->addRow([
                $p->name,
                $p->laravelVersion ?? '<comment>unknown</comment>',
                $p->phpConstraint  ?? '<comment>unknown</comment>',
                $git,
                $result->composerVersion ?? '<comment>unknown</comment>',
                $p->path,
            ]);
        }

        $table->render();
    }

    /**
     * @param list<StatusResult> $results
     */
    public function renderStatusJson(OutputInterface $output, array $results): void
    {
        $payload = array_map(fn (StatusResult $r) => [
            'name'            => $r->project->name,
            'path'            => $r->project->path,
            'laravel_version' => $r->project->laravelVersion,
            'php_constraint'  => $r->project->phpConstraint,
            'git_status'      => $r->project->gitStatus(),
            'composer'        => $r->composerVersion,
        ], $results);

        $output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    // -------------------------------------------------------------------------
    // Scan reporting
    // -------------------------------------------------------------------------

    /**
     * @param list<Project> $projects
     */
    public function renderScanHuman(OutputInterface $output, array $projects): void
    {
        if (empty($projects)) {
            $output->writeln('<comment>No Laravel projects found.</comment>');
            return;
        }

        $output->writeln(sprintf('<info>Found %d Laravel project(s):</info>', count($projects)));
        $output->writeln('');

        foreach ($projects as $project) {
            $git = match (true) {
                !$project->isGitRepo => ' <comment>[no git]</comment>',
                !$project->isGitClean => ' <error>[dirty]</error>',
                default => '',
            };

            $output->writeln(sprintf(
                '  • <options=bold>%s</> %s<fg=gray>%s</>',
                $project->name,
                $git,
                $project->path,
            ));
        }

        $output->writeln('');
    }

    /**
     * @param list<Project> $projects
     */
    public function renderScanJson(OutputInterface $output, array $projects): void
    {
        $payload = array_map(fn (Project $p) => [
            'name'            => $p->name,
            'path'            => $p->path,
            'laravel_version' => $p->laravelVersion,
            'php_constraint'  => $p->phpConstraint,
            'git_status'      => $p->gitStatus(),
        ], $projects);

        $output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    // -------------------------------------------------------------------------
    // Outdated reporting
    // -------------------------------------------------------------------------

    /**
     * @param list<OutdatedResult> $results
     */
    public function renderOutdatedHuman(OutputInterface $output, array $results): void
    {
        if (empty($results)) {
            $output->writeln('<comment>No projects to check.</comment>');
            return;
        }

        foreach ($results as $result) {
            $output->writeln(sprintf('<info>Project: %s</info>', $result->projectName));

            if ($result->failed) {
                $output->writeln(sprintf('  <error>Error: %s</error>', $result->errorOutput));
                $output->writeln('');
                continue;
            }

            if (!$result->hasOutdated()) {
                $output->writeln('  <comment>Up to date.</comment>');
                $output->writeln('');
                continue;
            }

            $table = new Table($output);
            $table->setHeaders(['Package', 'Current', 'Latest', 'Status']);

            foreach ($result->packages as $pkg) {
                $table->addRow([
                    $pkg['name'] ?? '',
                    $pkg['version'] ?? '',
                    $pkg['latest'] ?? '',
                    $pkg['latest-status'] ?? '',
                ]);
            }

            $table->render();
            $output->writeln('');
        }
    }

    /**
     * @param list<OutdatedResult> $results
     */
    public function renderOutdatedJson(OutputInterface $output, array $results): void
    {
        $payload = array_map(fn (OutdatedResult $r) => [
            'project'  => $r->projectName,
            'path'     => $r->projectPath,
            'failed'   => $r->failed,
            'error'    => $r->errorOutput,
            'packages' => $r->packages,
        ], $results);

        $output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @param list<UpdateResult> $results
     */
    private function renderUpdateSummary(OutputInterface $output, array $results, float $totalTime): void
    {
        $counts = $this->countResults($results);

        $minutes = (int) ($totalTime / 60);
        $seconds = (int) ($totalTime % 60);
        $timeStr = $minutes > 0
            ? sprintf('%dm %ds', $minutes, $seconds)
            : sprintf('%ds', $seconds);

        $output->writeln('');
        $output->writeln(sprintf('<options=bold>%s Orchard Summary</>', self::ICON_TREE));
        $output->writeln(str_repeat('-', 19));
        $output->writeln(sprintf('<info>%s %d updated</info>', self::ICON_SUCCESS, $counts['success']));
        $output->writeln(sprintf('<comment>%s %d skipped</comment>', self::ICON_SKIPPED, $counts['skipped']));
        $output->writeln(sprintf('<error>%s %d failed</error>', self::ICON_FAILED, $counts['failed']));
        $output->writeln(sprintf('Total time: %s', $timeStr));
        $output->writeln('');
    }

    /**
     * @param list<UpdateResult> $results
     * @return array{success: int, skipped: int, failed: int}
     */
    private function countResults(array $results): array
    {
        $counts = ['success' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($results as $result) {
            match ($result->status) {
                UpdateStatus::SUCCESS => $counts['success']++,
                UpdateStatus::SKIPPED => $counts['skipped']++,
                UpdateStatus::FAILED  => $counts['failed']++,
            };
        }

        return $counts;
    }
}
