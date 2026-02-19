<?php

declare(strict_types=1);

namespace Orchard\Command;

use Orchard\Exception\OrchardException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'self-update',
    description: 'Update the Orchard binary to the latest version.',
)]
class SelfUpdateCommand extends Command
{
    private const GITHUB_API_URL = 'https://api.github.com/repos/Williamug/orchard/releases/latest';
    private const USER_AGENT     = 'Orchard-CLI-Updater';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (\Phar::running() === '') {
            $io->warning('Orchard is not running as a PHAR. Self-update is only available for PHAR installations.');
            $io->note('If you installed via source, please use "git pull" and "composer install" instead.');
            return Command::INVALID;
        }

        $io->title('🍃 Orchard Self-Update');

        $currentVersion = $this->getApplication()->getVersion();
        $io->text("Current version: <comment>{$currentVersion}</comment>");

        $io->text('Checking for latest version...');

        try {
            $latest = $this->getLatestRelease();
        } catch (\Exception $e) {
            $io->error('Failed to check for updates: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $latestVersion = ltrim($latest['tag_name'], 'v');
        $io->text("Latest version:  <info>{$latestVersion}</info>");

        if (version_compare($currentVersion, $latestVersion, '>=')) {
            $io->success('You are already using the latest version.');
            return Command::SUCCESS;
        }

        if (!$io->confirm("Update to {$latestVersion}?", true)) {
            $io->text('Update cancelled.');
            return Command::SUCCESS;
        }

        $downloadUrl = $this->findPharAsset($latest['assets']);
        if (!$downloadUrl) {
            $io->error('Could not find a PHAR asset in the latest release.');
            return Command::FAILURE;
        }

        $io->text('Downloading update...');
        $localPath = realpath(\Phar::running(false));
        if (!$localPath) {
            $io->error('Could not resolve the path to the current PHAR.');
            return Command::FAILURE;
        }
        $tmpPath   = $localPath . '.tmp';

        try {
            $this->downloadFile($downloadUrl, $tmpPath);

            // Set permissions
            chmod($tmpPath, fileperms($localPath));

            // Atomic swap
            if (!rename($tmpPath, $localPath)) {
                throw new \RuntimeException("Failed to replace binary at {$localPath}");
            }

            $io->success("Successfully updated to {$latestVersion}!");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            if (file_exists($tmpPath)) {
                unlink($tmpPath);
            }
            $io->error('Update failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getLatestRelease(): array
    {
        $opts = [
            'http' => [
                'method' => 'GET',
                'ignore_errors' => true,
                'header' => [
                    'User-Agent: ' . self::USER_AGENT,
                    'Accept: application/vnd.github.v3+json',
                ],
            ],
        ];

        $context = stream_context_create($opts);
        $content = file_get_contents(self::GITHUB_API_URL, false, $context);

        // Check for HTTP status code in response headers
        if (isset($http_response_header)) {
            preg_match('{HTTP\/\S+\s+(\d+)}', $http_response_header[0], $matches);
            $status = (int) $matches[1];

            if ($status === 404) {
                throw new \RuntimeException('No public releases found for this repository. Please ensure you have created at least one release on GitHub.');
            }

            if ($status !== 200) {
                throw new \RuntimeException("GitHub API returned HTTP status {$status}");
            }
        }

        if ($content === false) {
            $error = error_get_last();
            throw new \RuntimeException($error['message'] ?? 'Unknown network error');
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['tag_name'])) {
            throw new \RuntimeException('Invalid JSON response from GitHub API. The repository may not have any releases.');
        }

        return $data;
    }

    private function findPharAsset(array $assets): ?string
    {
        foreach ($assets as $asset) {
            if (str_ends_with($asset['name'], '.phar')) {
                return $asset['browser_download_url'];
            }
            if ($asset['name'] === 'orchard') {
                return $asset['browser_download_url'];
            }
        }

        return null;
    }

    private function downloadFile(string $url, string $destination): void
    {
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: ' . self::USER_AGENT,
                ],
            ],
        ];

        $context = stream_context_create($opts);
        $fp = fopen($url, 'rb', false, $context);
        if (!$fp) {
            throw new \RuntimeException("Could not open URL: {$url}");
        }

        if (file_put_contents($destination, $fp) === false) {
            fclose($fp);
            throw new \RuntimeException("Failed to write to {$destination}");
        }

        fclose($fp);
    }
}
