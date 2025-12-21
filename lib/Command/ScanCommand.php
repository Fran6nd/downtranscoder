<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Command;

use OCA\DownTranscoder\Service\MediaScannerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class ScanCommand extends Command {
    private MediaScannerService $scannerService;

    public function __construct(MediaScannerService $scannerService) {
        parent::__construct();
        $this->scannerService = $scannerService;
    }

    protected function configure(): void {
        $this
            ->setName('downtranscoder:scan')
            ->setDescription('Scan for large media files that exceed the configured trigger size');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        // Check if a scan is already running
        $status = $this->scannerService->getScanStatus();
        if ($status['is_scanning'] ?? false) {
            $output->writeln('<error>A scan is already in progress.</error>');
            $output->writeln('Please wait for the current scan to complete before starting a new one.');
            return Command::FAILURE;
        }

        $output->writeln('<info>Scanning for large media files...</info>');
        $output->writeln('');

        $files = $this->scannerService->scanForLargeFiles();

        if (empty($files)) {
            $output->writeln('<comment>No large media files found.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>Found %d large media files:</info>', count($files)));
        $output->writeln('');

        // Create table
        $table = new Table($output);
        $table->setHeaders(['ID', 'Name', 'Size (GB)', 'Type', 'Path']);

        foreach ($files as $file) {
            $table->addRow([
                $file['id'],
                $file['name'],
                number_format($file['sizeGB'], 2),
                $file['type'],
                $file['path']
            ]);
        }

        $table->render();

        // Statistics
        $output->writeln('');
        $output->writeln('<info>Statistics:</info>');

        $totalSize = array_sum(array_column($files, 'size'));
        $videoCount = count(array_filter($files, fn($f) => $f['type'] === 'Video'));
        $imageCount = count(array_filter($files, fn($f) => $f['type'] === 'Image'));

        $output->writeln(sprintf('  Total files: %d', count($files)));
        $output->writeln(sprintf('  Videos: %d', $videoCount));
        $output->writeln(sprintf('  Images: %d', $imageCount));
        $output->writeln(sprintf('  Total size: %.2f GB', $totalSize / (1024 * 1024 * 1024)));

        $output->writeln('');
        $output->writeln('<comment>Use "occ downtranscoder:queue <file-id>" to add files to the transcode queue.</comment>');

        return Command::SUCCESS;
    }
}
