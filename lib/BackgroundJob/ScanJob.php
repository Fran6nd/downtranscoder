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
        $this->logger->info('DownTranscoder scan job started');

        try {
            $files = $this->scannerService->scanForLargeFiles();
            $this->logger->info('Scan job completed successfully. Found ' . count($files) . ' files');
        } catch (\Exception $e) {
            $this->logger->error('Scan job failed: ' . $e->getMessage(), [
                'exception' => $e
            ]);
        }
    }
}
