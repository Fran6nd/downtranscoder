<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Command;

use OCA\DownTranscoder\Service\TranscodingQueueService;
use OCA\DownTranscoder\Service\MediaStateService;
use OCA\DownTranscoder\Util\FormatHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class TranscodeCommand extends Command {
    private TranscodingQueueService $queueService;
    private MediaStateService $stateService;

    public function __construct(
        TranscodingQueueService $queueService,
        MediaStateService $stateService
    ) {
        parent::__construct();
        $this->queueService = $queueService;
        $this->stateService = $stateService;
    }

    protected function configure(): void {
        $this
            ->setName('downtranscoder:transcode')
            ->setDescription('Manage and process the transcoding workflow')
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
                'List all media items by state'
            )
            ->addOption(
                'status',
                't',
                InputOption::VALUE_NONE,
                'Show transcoding status'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $start = $input->getOption('start');
        $list = $input->getOption('list');
        $status = $input->getOption('status');

        // Count how many options are set
        $optionsSet = 0;
        if ($start) $optionsSet++;
        if ($list) $optionsSet++;
        if ($status) $optionsSet++;

        // Validate mutually exclusive options
        if ($optionsSet > 1) {
            $output->writeln('<error>Error: Only one option can be used at a time.</error>');
            $output->writeln('');
            $output->writeln('Usage: downtranscoder:transcode [--start|--list|--status]');
            $output->writeln('');
            $output->writeln('Options:');
            $output->writeln('  -s, --start   Start transcoding all queued files');
            $output->writeln('  -l, --list    List all media items by state');
            $output->writeln('  -t, --status  Show transcoding status');
            return Command::INVALID;
        }

        // List media items
        if ($list) {
            return $this->listMediaItems($output);
        }

        // Show status
        if ($status) {
            return $this->showStatus($output);
        }

        // Start transcoding
        if ($start) {
            return $this->startTranscoding($output);
        }

        // No options provided, show usage
        $output->writeln('<comment>Please specify an option.</comment>');
        $output->writeln('');
        $output->writeln('Usage: downtranscoder:transcode [--start|--list|--status]');
        $output->writeln('');
        $output->writeln('Options:');
        $output->writeln('  -s, --start   Start transcoding all queued files');
        $output->writeln('  -l, --list    List all media items by state');
        $output->writeln('  -t, --status  Show transcoding status');
        return Command::INVALID;
    }

    private function listMediaItems(OutputInterface $output): int {
        $allItems = $this->stateService->getAllMediaItems();

        if (empty($allItems)) {
            $output->writeln('<comment>No media items found.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>Media Items (%d total):</info>', count($allItems)));
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['ID', 'Name', 'Size (GB)', 'State', 'Preset', 'Updated']);

        foreach ($allItems as $item) {
            $updatedAt = date('Y-m-d H:i:s', $item['updatedAt']);
            $preset = $item['transcodePreset'] ?? 'default';

            $table->addRow([
                $item['id'],
                $item['name'],
                FormatHelper::formatSizeGB($item['size']),
                $item['state'],
                $preset,
                $updatedAt
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

        $output->writeln('');
        $output->writeln('<info>Media Items by State:</info>');
        $output->writeln(sprintf('  Found: %d', count($this->stateService->getMediaItemsByState('found'))));
        $output->writeln(sprintf('  Queued: %d', $status['queued_items'] ?? 0));
        $output->writeln(sprintf('  Transcoding: %d', $status['transcoding_items'] ?? 0));
        $output->writeln(sprintf('  Transcoded: %d', $status['transcoded_items'] ?? 0));
        $output->writeln(sprintf('  Aborted: %d', $status['aborted_items'] ?? 0));
        $output->writeln(sprintf('  Discarded: %d', count($this->stateService->getMediaItemsByState('discarded'))));

        return Command::SUCCESS;
    }

    private function startTranscoding(OutputInterface $output): int {
        $queuedItems = $this->stateService->getMediaItemsByState('queued');

        if (empty($queuedItems)) {
            $output->writeln('<comment>No queued items. Nothing to transcode.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>Starting transcoding of %d queued files...</info>', count($queuedItems)));
        $output->writeln('');

        $success = $this->queueService->startTranscoding();

        if ($success) {
            $output->writeln('');
            $output->writeln('<info>Transcoding started successfully.</info>');
            $output->writeln('Use <comment>occ downtranscoder:transcode --status</comment> to monitor progress.');
            return Command::SUCCESS;
        } else {
            $output->writeln('');
            $output->writeln('<error>Failed to start transcoding. Check logs for details.</error>');
            return Command::FAILURE;
        }
    }
}
