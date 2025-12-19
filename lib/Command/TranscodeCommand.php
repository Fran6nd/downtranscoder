<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Command;

use OCA\DownTranscoder\Service\TranscodingQueueService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class TranscodeCommand extends Command {
    private TranscodingQueueService $queueService;

    public function __construct(TranscodingQueueService $queueService) {
        parent::__construct();
        $this->queueService = $queueService;
    }

    protected function configure(): void {
        $this
            ->setName('downtranscoder:transcode')
            ->setDescription('Manage and process the transcoding queue')
            ->addOption(
                'start',
                's',
                InputOption::VALUE_NONE,
                'Start transcoding all queued files'
            )
            ->addOption(
                'list',
                'l',
                InputOption::VALUE_NONE,
                'List all files in the transcode queue'
            )
            ->addOption(
                'status',
                null,
                InputOption::VALUE_NONE,
                'Show transcoding status'
            )
            ->addOption(
                'add',
                'a',
                InputOption::VALUE_REQUIRED,
                'Add a file to the queue by file ID'
            )
            ->addOption(
                'remove',
                'r',
                InputOption::VALUE_REQUIRED,
                'Remove a file from the queue by file ID'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        // List queue
        if ($input->getOption('list')) {
            return $this->listQueue($output);
        }

        // Show status
        if ($input->getOption('status')) {
            return $this->showStatus($output);
        }

        // Add to queue
        if ($fileId = $input->getOption('add')) {
            return $this->addToQueue((int)$fileId, $output);
        }

        // Remove from queue
        if ($fileId = $input->getOption('remove')) {
            return $this->removeFromQueue((int)$fileId, $output);
        }

        // Start transcoding
        if ($input->getOption('start')) {
            return $this->startTranscoding($output);
        }

        // No options provided, show usage
        $output->writeln('<comment>Please specify an option. Use --help for available options.</comment>');
        return Command::INVALID;
    }

    private function listQueue(OutputInterface $output): int {
        $queue = $this->queueService->getQueue();

        if (empty($queue)) {
            $output->writeln('<comment>Transcode queue is empty.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>Transcode Queue (%d items):</info>', count($queue)));
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['ID', 'Name', 'Size (GB)', 'Status', 'Added']);

        foreach ($queue as $item) {
            $sizeGB = $item['size'] / (1024 * 1024 * 1024);
            $addedAt = date('Y-m-d H:i:s', $item['added_at']);
            $status = $item['status'] ?? 'pending';

            $table->addRow([
                $item['id'],
                $item['name'],
                number_format($sizeGB, 2),
                $status,
                $addedAt
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }

    private function showStatus(OutputInterface $output): int {
        $status = $this->queueService->getStatus();

        $output->writeln('<info>Transcoding Status:</info>');
        $output->writeln('');

        $isTranscoding = $status['is_transcoding'] ?? false;
        $output->writeln(sprintf('  Status: %s', $isTranscoding ? '<comment>Transcoding in progress</comment>' : '<info>Idle</info>'));

        if ($isTranscoding) {
            $currentIndex = $status['current_index'] ?? 0;
            $totalItems = $status['total_items'] ?? 0;
            $currentFile = $status['current_file'] ?? 'Unknown';

            $output->writeln(sprintf('  Progress: %d/%d', $currentIndex, $totalItems));
            $output->writeln(sprintf('  Current file: %s', $currentFile));
        }

        $output->writeln(sprintf('  Queued items: %d', $status['queued_items'] ?? 0));
        $output->writeln(sprintf('  Completed: %d', $status['completed_items'] ?? 0));
        $output->writeln(sprintf('  Failed: %d', $status['failed_items'] ?? 0));

        return Command::SUCCESS;
    }

    private function addToQueue(int $fileId, OutputInterface $output): int {
        $output->writeln(sprintf('<info>Adding file %d to queue...</info>', $fileId));

        $success = $this->queueService->addToQueue($fileId);

        if ($success) {
            $output->writeln('<info>File added to queue successfully.</info>');
            return Command::SUCCESS;
        } else {
            $output->writeln('<error>Failed to add file to queue.</error>');
            return Command::FAILURE;
        }
    }

    private function removeFromQueue(int $fileId, OutputInterface $output): int {
        $output->writeln(sprintf('<info>Removing file %d from queue...</info>', $fileId));

        $this->queueService->removeFromQueue($fileId);
        $output->writeln('<info>File removed from queue.</info>');

        return Command::SUCCESS;
    }

    private function startTranscoding(OutputInterface $output): int {
        $queue = $this->queueService->getQueue();

        if (empty($queue)) {
            $output->writeln('<comment>Transcode queue is empty. Nothing to transcode.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>Starting transcoding of %d files...</info>', count($queue)));
        $output->writeln('');

        $success = $this->queueService->startTranscoding();

        if ($success) {
            $output->writeln('');
            $output->writeln('<info>Transcoding completed.</info>');
            return Command::SUCCESS;
        } else {
            $output->writeln('');
            $output->writeln('<error>Transcoding failed. Check logs for details.</error>');
            return Command::FAILURE;
        }
    }
}
