<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\BackgroundJob;

use OCA\DownTranscoder\Service\TranscodingQueueService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class TranscodeJob extends TimedJob {
    private TranscodingQueueService $queueService;
    private LoggerInterface $logger;
    private IConfig $config;

    public function __construct(
        ITimeFactory $time,
        TranscodingQueueService $queueService,
        LoggerInterface $logger,
        IConfig $config
    ) {
        parent::__construct($time);
        $this->queueService = $queueService;
        $this->logger = $logger;
        $this->config = $config;

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

        // Check if scheduling is enabled
        $scheduleEnabled = $this->config->getAppValue('downtranscoder', 'enable_schedule', 'false') === 'true';

        if ($scheduleEnabled) {
            // Get scheduled time
            $scheduleStart = $this->config->getAppValue('downtranscoder', 'schedule_start', '02:00');

            // Get current time and scheduled time in minutes
            $currentTime = date('H:i');
            $currentMinutes = $this->timeToMinutes($currentTime);
            $scheduledMinutes = $this->timeToMinutes($scheduleStart);

            // Check if we've already run in this hour by checking last run time
            $lastRunKey = 'last_scheduled_run';
            $lastRun = $this->config->getAppValue('downtranscoder', $lastRunKey, '0');
            $currentHour = date('Y-m-d-H');

            // Only run if current hour matches scheduled hour and we haven't run this hour yet
            $scheduledHour = (int)floor($scheduledMinutes / 60);
            $currentHourNum = (int)date('H');

            if ($currentHourNum !== $scheduledHour) {
                $this->logger->debug("Current hour ({$currentHourNum}) does not match scheduled hour ({$scheduledHour}), skipping");
                return;
            }

            if ($lastRun === $currentHour) {
                $this->logger->debug("Already ran scheduled transcoding this hour ({$currentHour}), skipping");
                return;
            }

            $this->logger->info("Scheduled time reached ({$scheduleStart}), starting transcoding");

            // Mark this hour as processed
            $this->config->setAppValue('downtranscoder', $lastRunKey, $currentHour);
        }

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

    /**
     * Convert time string to minutes since midnight
     *
     * @param string $time Time in HH:MM format
     * @return int Minutes since midnight
     */
    private function timeToMinutes(string $time): int {
        $parts = explode(':', $time);
        return ((int)$parts[0] * 60) + (int)$parts[1];
    }
}
