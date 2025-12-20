<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\BackgroundJob;

use OCA\DownTranscoder\Service\MediaScannerService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use Psr\Log\LoggerInterface;

class ScanJob extends QueuedJob {
    private MediaScannerService $scannerService;
    private LoggerInterface $logger;

    public function __construct(
        ITimeFactory $time,
        MediaScannerService $scannerService,
        LoggerInterface $logger
    ) {
        parent::__construct($time);
        $this->scannerService = $scannerService;
        $this->logger = $logger;
    }

    /**
     * Execute the background scan job
     *
     * @param mixed $argument
     */
    protected function run($argument): void {
        $this->logger->info('=================================');
        $this->logger->info('DownTranscoder SCAN JOB STARTED');
        $this->logger->info('=================================');

        try {
            $files = $this->scannerService->scanForLargeFiles();
            $this->logger->info('=================================');
            $this->logger->info('SCAN JOB COMPLETED SUCCESSFULLY');
            $this->logger->info('Total files found: ' . count($files));
            $this->logger->info('=================================');
        } catch (\Exception $e) {
            $this->logger->error('=================================');
            $this->logger->error('SCAN JOB FAILED');
            $this->logger->error('Error: ' . $e->getMessage());
            $this->logger->error('Stack trace: ' . $e->getTraceAsString());
            $this->logger->error('=================================', [
                'exception' => $e
            ]);
        }
    }
}
