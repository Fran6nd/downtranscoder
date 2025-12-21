<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Command;

use OCA\DownTranscoder\Service\MediaStateService;
use OCA\DownTranscoder\Service\MediaScannerService;
use OCA\DownTranscoder\Service\TranscodingQueueService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class ResetCommand extends Command {
    private MediaStateService $stateService;
    private MediaScannerService $scannerService;
    private TranscodingQueueService $queueService;

    public function __construct(
        MediaStateService $stateService,
        MediaScannerService $scannerService,
        TranscodingQueueService $queueService
    ) {
        parent::__construct();
        $this->stateService = $stateService;
        $this->scannerService = $scannerService;
        $this->queueService = $queueService;
    }

    protected function configure(): void {
        $this
            ->setName('downtranscoder:reset')
            ->setDescription('Reset the DownTranscoder app - clear all data and abort all running tasks')
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Skip confirmation prompt'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $force = $input->getOption('force');

        // Confirmation prompt unless --force is used
        if (!$force) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                '<question>Are you sure you want to reset DownTranscoder? This will:
  - Clear all media items from the database
  - Abort any running scans
  - Abort any running transcoding tasks
  - Clear all scan/transcode status flags
This action cannot be undone! Continue? (y/N)</question> ',
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Reset cancelled.</comment>');
                return Command::SUCCESS;
            }
        }

        $output->writeln('');
        $output->writeln('<info>Resetting DownTranscoder...</info>');
        $output->writeln('');

        // 1. Clear database
        $output->write('  Clearing database... ');
        try {
            $clearedCount = $this->stateService->resetAllData();
            $output->writeln(sprintf('<info>✓ Cleared %d items</info>', $clearedCount));
        } catch (\Exception $e) {
            $output->writeln('<error>✗ Failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        // 2. Abort scan
        $output->write('  Aborting scan tasks... ');
        try {
            $scanStatus = $this->scannerService->getScanStatus();
            if ($scanStatus['is_scanning'] ?? false) {
                $this->scannerService->setScanning(false);
                $output->writeln('<info>✓ Scan aborted</info>');
            } else {
                $output->writeln('<comment>○ No scan in progress</comment>');
            }
        } catch (\Exception $e) {
            $output->writeln('<error>✗ Failed: ' . $e->getMessage() . '</error>');
        }

        // 3. Abort transcoding
        $output->write('  Aborting transcoding tasks... ');
        try {
            $transcodeStatus = $this->queueService->getStatus();
            if ($transcodeStatus['is_transcoding'] ?? false) {
                $this->queueService->clearStatus();
                $output->writeln('<info>✓ Transcoding aborted</info>');
            } else {
                $output->writeln('<comment>○ No transcoding in progress</comment>');
            }
        } catch (\Exception $e) {
            $output->writeln('<error>✗ Failed: ' . $e->getMessage() . '</error>');
        }

        $output->writeln('');
        $output->writeln('<info>Reset complete!</info>');
        $output->writeln('');
        $output->writeln('You can now:');
        $output->writeln('  - Run <comment>occ downtranscoder:scan</comment> to scan for media files');
        $output->writeln('  - Access the app UI to manage transcoding');

        return Command::SUCCESS;
    }
}
