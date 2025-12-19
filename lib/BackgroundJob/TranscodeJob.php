<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\BackgroundJob;

use OCA\DownTranscoder\Service\TranscodingQueueService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class TranscodeJob extends TimedJob {
    private TranscodingQueueService $queueService;
    private LoggerInterface $logger;

    public function __construct(
        ITimeFactory $time,
        TranscodingQueueService $queueService,
        LoggerInterface $logger
    ) {
        parent::__construct($time);
        $this->queueService = $queueService;
        $this->logger = $logger;

        // Run every hour
        $this->setInterval(3600);
    }

    /**
     * Execute the background job
     *
     * @param mixed $argument
     */
    protected function run($argument): void {
        $this->logger->info('DownTranscoder background job started');

        $status = $this->queueService->getStatus();

        // Check if already transcoding
        if ($status['is_transcoding'] ?? false) {
            $this->logger->info('Transcoding already in progress, skipping');
            return;
        }

        $queue = $this->queueService->getQueue();

        if (empty($queue)) {
            $this->logger->debug('Transcode queue is empty, nothing to do');
            return;
        }

        // Count pending items
        $pendingItems = array_filter($queue, fn($item) => ($item['status'] ?? 'pending') === 'pending');

        if (empty($pendingItems)) {
            $this->logger->info('No pending items in queue');
            return;
        }

        $this->logger->info(sprintf('Starting background transcoding of %d pending items', count($pendingItems)));

        try {
            $this->queueService->startTranscoding();
            $this->logger->info('Background transcoding completed successfully');
        } catch (\Exception $e) {
            $this->logger->error('Background transcoding failed: ' . $e->getMessage(), [
                'exception' => $e
            ]);
        }
    }
}
